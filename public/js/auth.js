// Signup / verify / login / forgot-password / logout wiring, plus the
// session-restore routine that runs once on page load.

let pendingSignupEmail = "";
let pendingSignupPassword = ""; // kept in memory only, resent silently at verify time so the user isn't asked to retype it.

function initOtpInputGroup(inputs) {
  inputs.forEach((input, i) => {
    input.addEventListener("input", () => {
      input.value = input.value.replace(/\D/g, "").slice(0, 1);
      if (input.value && inputs[i + 1]) inputs[i + 1].focus();
    });
    input.addEventListener("keydown", (e) => {
      if (e.key === "Backspace" && !input.value && inputs[i - 1]) {
        inputs[i - 1].focus();
      }
    });
    input.addEventListener("paste", (e) => {
      const pasted = (e.clipboardData || window.clipboardData).getData("text").replace(/\D/g, "");
      if (!pasted) return;
      e.preventDefault();
      pasted
        .slice(0, inputs.length)
        .split("")
        .forEach((digit, idx) => {
          if (inputs[idx]) inputs[idx].value = digit;
        });
      const next = inputs[Math.min(pasted.length, inputs.length - 1)];
      if (next) next.focus();
    });
  });
}

function getOtpValue(inputs) {
  return inputs.map((i) => i.value).join("");
}

function setButtonLoading(button, loading, loadingText = "Please wait…") {
  if (!button) return;
  if (loading) {
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.textContent = loadingText;
  } else {
    button.disabled = false;
    if (button.dataset.originalHtml) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }
}

function goToDashboard(user) {
  persistCurrentSession(user);

  const letterEl = document.getElementById("header-avatar-letter");
  const imgEl = document.getElementById("header-avatar-img");
  if (user?.profile_photo_url && imgEl) {
    imgEl.src = user.profile_photo_url;
    imgEl.classList.remove("hidden");
    letterEl?.classList.add("hidden");
  } else if (letterEl) {
    letterEl.textContent = (user?.pet_name || user?.name || "P")[0];
  }

  document.getElementById("admin-entry-btn")?.classList.toggle("hidden", !(user?.admin_capabilities?.length > 0));
  applyPetTypeTheme();
  loadHubHero();
  loadHubHighlight();
  loadHubAdsWidget();
  loadHubCalendarWidget();

  switchView("view-social-feed");
  switchSocialTab("feed");
  loadNotifications();
  startNotificationPolling();
}

async function logout() {
  try {
    await api("logout", {});
  } catch (e) {
    console.warn("Logout request failed:", e);
  }
  persistCurrentSession(null);
  switchView("view-public-login");
}

// ---------------- Signup ----------------

document.getElementById("signup-step-1")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();

  const petName = document.getElementById("reg-name").value.trim();
  const parentName = document.getElementById("reg-parent-name").value.trim();
  const petType = document.getElementById("reg-pet_type").value;
  const breedSelect = document.getElementById("reg-breed").value;
  const customBreed = document.getElementById("reg-custom-breed")?.value.trim();
  const breed = breedSelect === "other" ? customBreed : breedSelect;
  const email = document.getElementById("reg-email").value.trim();
  const password = document.getElementById("reg-password").value;

  if (!petName || !parentName || !petType || !breed || !email || !password) {
    showFieldError("signup-error", "Please fill in all fields.");
    return;
  }
  if (password.length < 10) {
    showFieldError("signup-error", "Password must be at least 10 characters.");
    return;
  }

  const btn = document.getElementById("signup-submit-btn");
  setButtonLoading(btn, true, "Creating your account…");
  try {
    const data = await api("signup", {
      pet_name: petName,
      parent_name: parentName,
      pet_type: petType,
      breed,
      email,
      password,
    });

    if (data.status !== "success") {
      showFieldError("signup-error", data.message || "Could not create your account.");
      return;
    }

    if (data.verification_required) {
      pendingSignupEmail = email;
      pendingSignupPassword = password;
      document.getElementById("verify-email-target").textContent = email;
      switchView("view-verify-email");
      document.querySelector("#otp-inputs .otp-box")?.focus();
      return;
    }

    // Email verification disabled server-side — account created immediately.
    goToDashboard(data.user);
  } catch (err) {
    console.error(err);
    showFieldError("signup-error", "Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

const otpInputs = Array.from(document.querySelectorAll("#otp-inputs .otp-box"));
initOtpInputGroup(otpInputs);

document.getElementById("verify-email-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();
  const code = getOtpValue(otpInputs);
  if (code.length !== 6) {
    showFieldError("verify-error", "Enter the 6-digit code from your email.");
    return;
  }
  if (!pendingSignupPassword) {
    showFieldError("verify-error", "For security, please return to the signup form and enter your password again before verifying.");
    return;
  }

  const btn = document.getElementById("verify-submit-btn");
  setButtonLoading(btn, true, "Verifying…");
  try {
    const data = await api("verify_signup", {
      email: pendingSignupEmail,
      code,
      password: pendingSignupPassword,
    });

    if (data.status !== "success") {
      showFieldError("verify-error", data.message || "Incorrect code.");
      return;
    }

    pendingSignupPassword = "";
    goToDashboard(data.user);
    showToast("Welcome to PawCircle!", "success");
  } catch (err) {
    console.error(err);
    showFieldError("verify-error", "Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

document.getElementById("verify-resend-btn")?.addEventListener("click", async () => {
  const btn = document.getElementById("verify-resend-btn");
  setButtonLoading(btn, true, "Sending…");
  try {
    const data = await api("resend_signup_code", { email: pendingSignupEmail });
    if (data.status === "success") {
      showToast(data.message || "A new code is on its way.", "success");
    } else {
      showFieldError("verify-error", data.message || "Could not resend the code.");
    }
  } catch (err) {
    console.error(err);
    showFieldError("verify-error", "Could not resend the code.");
  } finally {
    setButtonLoading(btn, false);
  }
});

// ---------------- Login ----------------

document.getElementById("public-login-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();
  const email = document.getElementById("public-email").value.trim();
  const password = document.getElementById("public-password").value;
  if (!email || !password) {
    showFieldError("public-error", "Enter your email and password.");
    return;
  }

  const btn = document.getElementById("public-submit-btn");
  setButtonLoading(btn, true, "Signing in…");
  try {
    const data = await api("public_login", { email, password });
    if (data.status !== "success") {
      showFieldError("public-error", data.message || "Invalid email or password.");
      return;
    }
    goToDashboard(data.user);
  } catch (err) {
    console.error(err);
    showFieldError("public-error", "Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

// ---------------- Forgot password ----------------

let pendingResetEmail = "";
const forgotOtpInputs = Array.from(document.querySelectorAll("#forgot-otp-inputs .forgot-otp-box"));
initOtpInputGroup(forgotOtpInputs);

function showForgotStepRequest() {
  document.getElementById("forgot-step-request")?.classList.remove("hidden");
  document.getElementById("forgot-step-verify-code")?.classList.add("hidden");
  document.getElementById("forgot-step-new-password")?.classList.add("hidden");
  clearErrors();
}

document.getElementById("forgot-request-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();
  const email = document.getElementById("forgot-email").value.trim();
  if (!email) {
    showFieldError("forgot-request-error", "Enter your email address.");
    return;
  }

  const btn = document.getElementById("forgot-request-submit-btn");
  setButtonLoading(btn, true, "Sending…");
  try {
    const data = await api("request_password_reset", { email });
    if (data.status !== "success") {
      showFieldError("forgot-request-error", data.message || "Could not send the reset code.");
      return;
    }
    pendingResetEmail = email;
    document.getElementById("forgot-verify-email-target").textContent = email;
    document.getElementById("forgot-step-request").classList.add("hidden");
    document.getElementById("forgot-step-verify-code").classList.remove("hidden");
    forgotOtpInputs[0]?.focus();
  } catch (err) {
    console.error(err);
    showFieldError("forgot-request-error", "Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

document.getElementById("forgot-verify-code-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();
  const code = getOtpValue(forgotOtpInputs);
  if (code.length !== 6) {
    showFieldError("forgot-verify-code-error", "Enter the 6-digit code from your email.");
    return;
  }

  const btn = document.getElementById("forgot-verify-code-submit-btn");
  setButtonLoading(btn, true, "Verifying…");
  try {
    const data = await api("verify_password_reset_code", { email: pendingResetEmail, code });
    if (data.status !== "success") {
      showFieldError("forgot-verify-code-error", data.message || "Incorrect code.");
      return;
    }
    document.getElementById("forgot-step-verify-code").classList.add("hidden");
    document.getElementById("forgot-step-new-password").classList.remove("hidden");
    document.getElementById("forgot-step-new-password").dataset.verifiedCode = code;
  } catch (err) {
    console.error(err);
    showFieldError("forgot-verify-code-error", "Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

document.getElementById("forgot-new-password-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();
  const newPassword = document.getElementById("forgot-new-password").value;
  const code = document.getElementById("forgot-step-new-password").dataset.verifiedCode || getOtpValue(forgotOtpInputs);
  if (newPassword.length < 10) {
    showFieldError("forgot-new-password-error", "New password must be at least 10 characters.");
    return;
  }

  const btn = document.getElementById("forgot-new-password-submit-btn");
  setButtonLoading(btn, true, "Resetting…");
  try {
    const data = await api("reset_password", { email: pendingResetEmail, code, new_password: newPassword });
    if (data.status !== "success") {
      showFieldError("forgot-new-password-error", data.message || "Could not reset your password.");
      return;
    }
    showToast("Password reset. Please sign in with your new password.", "success");
    pendingResetEmail = "";
    switchView("view-public-login");
    showForgotStepRequest();
  } catch (err) {
    console.error(err);
    showFieldError("forgot-new-password-error", "Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

// ---------------- Session restore on load ----------------

document.addEventListener("DOMContentLoaded", async () => {
  // We check for the CSRF token instead of the session token,
  // because the session token is HttpOnly and cannot be read by JavaScript.
  const hasSessionCookie = !!getCookieValue("pawcircle_csrf_token");
  if (!hasSessionCookie) {
    document.body.style.visibility = "visible";
    return;
  }

  try {
    const data = await api("session_me", {});
    if (data.status === "success" && data.user) {
      goToDashboard(data.user);
    }
  } catch (err) {
    console.warn("Session restore failed:", err);
  } finally {
    document.body.style.visibility = "visible";
  }
});
