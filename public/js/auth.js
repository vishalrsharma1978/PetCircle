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

const LOADING_SPINNER_SVG = '<svg class="inline-block w-4 h-4 animate-spin align-[-0.15em]" viewBox="0 0 24 24" fill="none"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8V0C5.373 0 0 5.373 0 12h4z"></path></svg>';

// loadingText omitted -> spinner only (icon-only buttons); passed -> spinner + text
// (text-only buttons, e.g. "Deleting…"). Restores the button's exact prior
// innerHTML (icon or text) when loading is turned back off.
function setButtonLoading(button, loading, loadingText = "") {
  if (!button) return;
  if (loading) {
    button.dataset.originalHtml = button.innerHTML;
    button.disabled = true;
    button.innerHTML = LOADING_SPINNER_SVG + (loadingText ? `<span class="ml-1.5">${escapeHtml(loadingText)}</span>` : "");
  } else {
    button.disabled = false;
    if (button.dataset.originalHtml !== undefined) {
      button.innerHTML = button.dataset.originalHtml;
      delete button.dataset.originalHtml;
    }
  }
}

function goToDashboard(user, isSessionRestore = false) {
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
  loadHubWidgetsBatched();

  switchView("view-social-feed");
  
  const params = new URLSearchParams(window.location.search);
  const postId = params.get('post');
  if (postId) {
    if (typeof openPostPage === 'function') {
      openPostPage(postId);
    } else {
      setTimeout(() => openPostPage(postId), 100);
    }
  } else {
    switchSocialTab("feed");
  }
  loadNotifications();
  startNotificationPolling();
  startPresenceHeartbeat();

  if (!isSessionRestore) maybePromptSetHandle(user);
}

// Nudges a pet parent to pick a handle right after signing up or logging in
// (not on a mere page-reload session restore) — skippable, and always
// changeable later from Settings > Security's existing Handle card.
function maybePromptSetHandle(user) {
  if (!user || user.handle) return;
  const modal = document.getElementById("set-handle-modal");
  if (!modal) return;
  const input = document.getElementById("set-handle-input");
  if (input) input.value = "";
  document.getElementById("set-handle-modal-error")?.classList.add("hidden");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeSetHandleModal() {
  const modal = document.getElementById("set-handle-modal");
  modal?.classList.add("hidden");
  modal?.classList.remove("flex");
}

async function submitSetHandleModal() {
  const input = document.getElementById("set-handle-input");
  const handle = input.value.trim();
  const errorEl = document.getElementById("set-handle-modal-error");
  errorEl?.classList.add("hidden");
  if (!/^[a-zA-Z0-9_]{5,20}$/.test(handle)) {
    if (errorEl) {
      errorEl.textContent = "Handle must be 5-20 characters — letters, numbers, and underscores only.";
      errorEl.classList.remove("hidden");
    }
    return;
  }
  const btn = document.getElementById("set-handle-modal-submit-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("set_handle", { handle });
    if (data.status !== "success") {
      if (errorEl) {
        errorEl.textContent = data.message || "Could not save handle.";
        errorEl.classList.remove("hidden");
      }
      return;
    }
    if (currentUserObj) {
      currentUserObj.handle = handle;
      persistCurrentSession(currentUserObj);
    }
    showToast("Handle set.", "success");
    closeSetHandleModal();
  } catch (err) {
    console.error(err);
    if (errorEl) {
      errorEl.textContent = "Could not save handle.";
      errorEl.classList.remove("hidden");
    }
  } finally {
    setButtonLoading(btn, false);
  }
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
      goToDashboard(data.user, true);
    }
  } catch (err) {
    console.warn("Session restore failed:", err);
  } finally {
    document.body.style.visibility = "visible";
  }
});

const PET_REAL_PHOTOS = {
  Dog: 'https://images.unsplash.com/photo-1548199973-03cce0bbc87b?w=1200&q=85&auto=format&fit=crop',
  Cat: 'https://images.unsplash.com/photo-1514888286974-6c03e2ca1dba?w=1200&q=85&auto=format&fit=crop',
  Bird: 'https://images.unsplash.com/photo-1444465693019-aa0b6392460d?w=1200&q=85&auto=format&fit=crop',
  Fish: 'https://images.unsplash.com/photo-1524704796725-9fc3044a58b2?w=1200&q=85&auto=format&fit=crop',
  Reptile: 'https://images.unsplash.com/photo-1484406596175-b408d56a7350?w=1200&q=85&auto=format&fit=crop',
  Rabbit: 'https://images.unsplash.com/photo-1585110396000-c9faf4e4f9ba?w=1200&q=85&auto=format&fit=crop',
  Hamster: 'https://images.unsplash.com/photo-1425082661705-1834bfd0999c?w=1200&q=85&auto=format&fit=crop',
  Other: 'https://images.unsplash.com/photo-1544568100-847a948585b9?w=1200&q=85&auto=format&fit=crop'
};

// Fallback used only when PET_REAL_PHOTOS has no entry for a given pet type
// (currently unreachable in practice — every pet type the breed-strip cycles
// through already has a PET_REAL_PHOTOS entry — but kept as a real, safe
// fallback rather than leaving the call site pointed at an undefined
// function). A flat accent-colored tile, no external asset/URL involved.
function getPetIllustrationDataUrl(theme, variant) {
  var accent = (theme && theme.accent) || '#f97316';
  var svg = '<svg xmlns="http://www.w3.org/2000/svg" width="200" height="200"><rect width="200" height="200" fill="' + accent + '"/></svg>';
  return 'data:image/svg+xml;utf8,' + encodeURIComponent(svg);
}

// Reuses the same pet_type -> accent-color map as the login page's own
// left-pane preview (core.js) and the galleries accent mechanism (step 13e)
// — a small hex-color lookup, not a full theming object, so it's wrapped
// here into the {accent, label} shape the login feed's render code expects.
function getPetTheme(petType) {
  var accents = (typeof PET_TYPE_PREVIEW_ACCENTS !== 'undefined') ? PET_TYPE_PREVIEW_ACCENTS : {};
  return {
    accent: accents[petType] || accents[''] || '#f97316',
    label: petType || 'Pet',
  };
}

var loginFeedFeeds = null;

    var loginFeedRotateTimer = null;

    var loginFeedRenderFn = null;

    var loginFeedCurrentIdx = 0;



    function initLoginFeed() {

      var pane = document.getElementById('lp-left-pane');

      var card = document.getElementById('lp-feed-card');

      var onlineEl = document.getElementById('lp-online-count');

      if (!card) return;



      loginFeedFeeds = [

        {

          petTheme: 'Dog',

          icon: '🐕', grad: 'linear-gradient(135deg,#fffbeb 0%,#fef9c3 60%,#f0fdf4 100%)',

          chipText: 'Dog Breed · Active Now', chipCls: 'text-amber-700 border-amber-200',

          headline: 'Golden Hour Walk Guide for Summer 2026',

          excerpt: 'Vets recommend shifting walks to early morning and post-sunset as temperatures peak. Cool pavement prevents burned paws, while shorter shaded routes cut heatstroke risk. Always carry water and watch for excessive panting.',

          tips: [

            { icon: '💧', label: 'Fresh Water', sub: 'Every Hour', cls: 'bg-sky-50 border-sky-100 text-sky-700' },

            { icon: '🌅', label: 'Walk at', sub: 'Dawn / Dusk', cls: 'bg-amber-50 border-amber-100 text-amber-700' },

            { icon: '🐾', label: 'Paw Check', sub: 'After Walk', cls: 'bg-lime-50 border-lime-100 text-lime-700' },

          ],

          tags: ['🐕 Dogs', '☀️ Summer', '🌿 Wellness'],

          activity: ['🐕 Dog Walk at Central Park · Sunday 7 am', '🙌 28 new dog parents joined today', '🏥 Vet Q&amp;A thread trending'],

          online: 142,

        },

        {

          petTheme: 'Cat',

          icon: '🐈', grad: 'linear-gradient(135deg,#fdf4ff 0%,#ede9fe 60%,#f0f9ff 100%)',

          chipText: 'Cat Breed · Active Now', chipCls: 'text-purple-700 border-purple-200',

          headline: 'Keeping Indoor Cats Cool &amp; Stimulated',

          excerpt: 'Indoor cats need mental enrichment when hot weather keeps windows closed. Puzzle feeders, elevated perches, and window bird-feeders provide daily stimulation. Rotate toys weekly and schedule two play sessions daily to prevent boredom stress.',

          tips: [

            { icon: '🧩', label: 'Puzzle', sub: 'Feeders', cls: 'bg-purple-50 border-purple-100 text-purple-700' },

            { icon: '🪟', label: 'Window', sub: 'Bird Feeder', cls: 'bg-sky-50 border-sky-100 text-sky-700' },

            { icon: '🎾', label: 'Play 2×', sub: 'Daily', cls: 'bg-pink-50 border-pink-100 text-pink-700' },

          ],

          tags: ['🐈 Cats', '🏡 Indoor', '🌿 Enrichment'],

          activity: ['🐈 New group: Indoor Cat Club · 52 members', '📸 52 photos shared today', '🏥 Live Vet Q&amp;A · Tonight 8 pm'],

          online: 89,

        },

        {

          petTheme: 'Bird',

          icon: '🐦', grad: 'linear-gradient(135deg,#f0f9ff 0%,#e0f2fe 60%,#ecfdf5 100%)',

          chipText: 'Bird Breed · Active Now', chipCls: 'text-sky-700 border-sky-200',

          headline: 'Socialising Your Parrot: A Step-by-Step Guide',

          excerpt: 'Building trust with a new parrot takes patience. Start with short 10-minute sessions near the cage without reaching in, offering treats through the bars. Gradually progress to step-up training with calm voices and positive reinforcement.',

          tips: [

            { icon: '🤝', label: 'Trust', sub: 'Sessions', cls: 'bg-sky-50 border-sky-100 text-sky-700' },

            { icon: '🍎', label: 'Treat', sub: 'Training', cls: 'bg-green-50 border-green-100 text-green-700' },

            { icon: '🔇', label: 'Calm', sub: 'Environment', cls: 'bg-indigo-50 border-indigo-100 text-indigo-700' },

          ],

          tags: ['🐦 Birds', '🦜 Parrots', '💬 Training'],

          activity: ['🐦 Parrot enrichment tips · 41 saves', '🎶 14 bird-call recordings shared', '📅 Bird Meet-Up · This Saturday'],

          online: 34,

        },

        {

          petTheme: 'Rabbit',

          icon: '🐇', grad: 'linear-gradient(135deg,#fff1f2 0%,#fce7f3 60%,#fff7ed 100%)',

          chipText: 'Rabbit Breed · Active Now', chipCls: 'text-pink-700 border-pink-200',

          headline: 'Rabbit-Proofing Your Home: The Essential Checklist',

          excerpt: 'Free-roaming rabbits are happier and healthier, but preparation is key. Cover electrical cords, block gaps behind furniture, and protect baseboards. Rabbits chew naturally — redirect them with safe hay bundles and willow toys.',

          tips: [

            { icon: '🔌', label: 'Cover', sub: 'All Cords', cls: 'bg-pink-50 border-pink-100 text-pink-700' },

            { icon: '🌾', label: 'Hay', sub: 'Bundles', cls: 'bg-amber-50 border-amber-100 text-amber-700' },

            { icon: '🚧', label: 'Block', sub: 'All Gaps', cls: 'bg-rose-50 border-rose-100 text-rose-700' },

          ],

          tags: ['🐇 Rabbits', '🏡 Safety', '🛡️ Proofing'],

          activity: ['🐇 New bonded-pair photo posted', '🐣 Rescue spotlight: 3 bunnies available', '📦 Hay-haul bulk buy thread'],

          online: 27,

        },

        {

          petTheme: 'Fish',

          icon: '🐠', grad: 'linear-gradient(135deg,#ecfeff 0%,#cffafe 60%,#f0fdf4 100%)',

          chipText: 'Aquatics Breed · Active Now', chipCls: 'text-cyan-700 border-cyan-200',

          headline: 'Summer Aquarium Care: Keeping Water Temperatures Safe',

          excerpt: 'Rising room temperatures push tank water above safe thresholds. Aim for 24–26 °C for tropical fish by running lights fewer hours, adding a fan across the water surface, or using frozen water bottles as a chiller. Test ammonia twice weekly.',

          tips: [

            { icon: '🌡️', label: '24–26°C', sub: 'Target Temp', cls: 'bg-cyan-50 border-cyan-100 text-cyan-700' },

            { icon: '💨', label: 'Fan Over', sub: 'Water Surface', cls: 'bg-sky-50 border-sky-100 text-sky-700' },

            { icon: '🧪', label: 'Test 2×', sub: 'Per Week', cls: 'bg-teal-50 border-teal-100 text-teal-700' },

          ],

          tags: ['🐠 Fish', '🌊 Aquascaping', '☀️ Summer'],

          activity: ['🐠 Aquascape build posted · 88 likes', '📊 Water test results thread', '🏆 Tank of the Month — vote now'],

          online: 18,

        },

        {

          petTheme: 'Reptile',

          icon: '🦎', grad: 'linear-gradient(135deg,#f7fee7 0%,#ecfccb 60%,#fefce8 100%)',

          chipText: 'Reptile Breed · Active Now', chipCls: 'text-lime-700 border-lime-200',

          headline: 'UVB Lighting &amp; Basking Zones: Getting It Right',

          excerpt: 'Proper UVB exposure is critical for bone health in diurnal reptiles. Replace UVB bulbs every 12 months even if they still emit visible light — output degrades invisibly. Maintain a clear gradient: 35–38 °C hot side, 24–27 °C cool side.',

          tips: [

            { icon: '☀️', label: 'Replace UVB', sub: 'Every 12 mo', cls: 'bg-lime-50 border-lime-100 text-lime-700' },

            { icon: '🌡️', label: 'Basking', sub: '35–38 °C', cls: 'bg-yellow-50 border-yellow-100 text-yellow-700' },

            { icon: '❄️', label: 'Cool Side', sub: '24–27 °C', cls: 'bg-sky-50 border-sky-100 text-sky-700' },

          ],

          tags: ['🦎 Reptiles', '💡 UVB', '🐍 Husbandry'],

          activity: ['🦎 Bioactive enclosure photos posted', '📷 Shed skin gallery · 9 new shots', '🩺 Exotic vet check-up guide posted'],

          online: 22,

        },

      ];



      loginFeedCurrentIdx = Math.floor(Math.random() * loginFeedFeeds.length);



      loginFeedRenderFn = function (forceTheme) {

        if (forceTheme) {

          var found = loginFeedFeeds.findIndex(function (f) { return f.petTheme === forceTheme; });

          if (found >= 0) loginFeedCurrentIdx = found;

        }



        var f = loginFeedFeeds[loginFeedCurrentIdx];

        var theme = getPetTheme(f.petTheme);

        if (onlineEl) onlineEl.textContent = (f.online + Math.floor(Math.random() * 14) - 7) + ' online';



        var tipsHtml = f.tips.map(function (t) {

          return '<div class="rounded-xl border px-2 py-2 text-center min-h-[78px] flex flex-col items-center justify-center ' + t.cls + '">' +

            '<div class="text-sm mb-0.5">' + t.icon + '</div>' +

            '<div class="text-[11px] font-semibold leading-tight">' + t.label + '<br>' + t.sub + '</div></div>';

        }).join('');



        var tagsHtml = f.tags.map(function (tag) {

          return '<span class="px-2 py-0.5 rounded-full bg-white border border-gray-200/70 text-xs font-medium text-gray-600">' + tag + '</span>';

        }).join('');



        var galleryThemes = ['Dog', 'Cat', 'Bird', 'Rabbit', 'Fish', 'Reptile']

          .filter(function (themeName) { return themeName !== f.petTheme; });

        var breedGalleryHtml = galleryThemes.concat(galleryThemes).map(function (themeName) {

          var theme = getPetTheme(themeName);

          var art = PET_REAL_PHOTOS[themeName] || getPetIllustrationDataUrl(theme, 'badge');

          return '<div class="pet-breed-strip-item pet-breed-strip-item--login rounded-xl border border-white/85 bg-white/76 p-1.5 shadow-sm">' +

            '<div class="pet-thumb rounded-lg" style="background-image:linear-gradient(135deg, color-mix(in srgb, ' + theme.accent + ' 18%, white), transparent 70%), url(\'' + art + '\');background-size:cover;background-position:center;"></div>' +

            '<div class="mt-1 text-[10px] font-bold text-gray-700 text-center leading-tight">' + theme.label + '</div>' +

            '</div>';

        }).join('');



        card.innerHTML =

          '<div class="h-full flex flex-col">' +

          '<div class="flex items-center justify-between mb-1.5">' +

          '<span class="inline-flex items-center gap-1.5 text-xs font-bold px-2.5 py-0.5 rounded-full bg-white border uppercase tracking-wide ' + f.chipCls + '">' +

          f.icon + ' ' + f.chipText +

          '</span>' +

          '<span class="text-xs text-gray-400">5 min read</span>' +

          '</div>' +

          '<h3 class="text-[15px] font-bold text-gray-800 leading-snug mb-1" style="font-family:\'DM Serif Display\';">' + f.headline + '</h3>' +

          '<p class="text-[13px] text-gray-600 leading-relaxed mb-2" style="display:-webkit-box;-webkit-line-clamp:3;-webkit-box-orient:vertical;overflow:hidden;">' + f.excerpt + '</p>' +

          '<div class="grid grid-cols-3 gap-2 mb-2 auto-rows-fr">' + tipsHtml + '</div>' +

          '<div class="mt-auto pt-2 border-t border-white/80">' +

          '<div class="text-[10px] font-black uppercase tracking-[0.16em] text-gray-500 mb-1.5">Pet Breeds Near You</div>' +

          '<div class="pet-breed-strip rounded-xl">' +

          '<div class="pet-breed-strip-track">' + breedGalleryHtml + '</div>' +

          '</div>' +

          '</div>' +

          '<div class="mt-2 flex items-center gap-2 flex-wrap">' + tagsHtml + '</div>' +

          '</div>';



        card.style.opacity = '0';

        card.style.transform = 'translateY(8px)';

        requestAnimationFrame(function () {

          card.style.transition = 'opacity 0.5s ease, transform 0.5s ease';

          card.style.opacity = '1';

          card.style.transform = 'translateY(0)';

        });



        if (!forceTheme) {

          // Sync accent color

          var rightPane = document.getElementById('lp-right-pane');

          var loginView = document.getElementById('view-public-login');

          if (theme && loginView) {

            loginView.style.setProperty('--login-accent', theme.accent);

          }

          if (theme && rightPane) {

            rightPane.style.setProperty('--login-accent', theme.accent);

          }

          

          // Sync dropdown selection

          var loginPetTypeSelect = document.getElementById('login-pet-type');

          if (loginPetTypeSelect) {

            loginPetTypeSelect.value = f.petTheme;

          }

          

          loginFeedCurrentIdx = (loginFeedCurrentIdx + 1) % loginFeedFeeds.length;

        }

      }



      function start() {

        clearInterval(loginFeedRotateTimer);

        loginFeedRenderFn();

        loginFeedRotateTimer = setInterval(loginFeedRenderFn, 7000);

      }



      start();



      // Re-trigger each time the login view becomes active (after logout or back-nav)

      var loginView = document.getElementById('view-public-login');

      if (loginView) {

        new MutationObserver(function () {

          if (loginView.classList.contains('active')) {

            if (document.getElementById('login-pet-type').value) {

              // Keep stopped

            } else {

              start();

            }

          }

        }).observe(loginView, { attributes: true, attributeFilter: ['class'] });

      }

    }



    function handleLoginPetTypeChange(type) {

      var rightPane = document.getElementById('lp-right-pane');

      var loginView = document.getElementById('view-public-login');

      if (!type) {

        if (loginFeedRotateTimer) clearInterval(loginFeedRotateTimer);

        loginFeedRotateTimer = setInterval(loginFeedRenderFn, 7000);

        if (loginView) loginView.style.setProperty('--login-accent', '#f97316'); // Default brand

        if (rightPane) rightPane.style.setProperty('--login-accent', '#f97316'); // Default brand

        return;

      }



      // Stop rotation

      if (loginFeedRotateTimer) clearInterval(loginFeedRotateTimer);



      // Render specific theme

      if (loginFeedRenderFn) loginFeedRenderFn(type);



      // Update accent color

      var theme = getPetTheme(type);

      if (loginView && theme) {

        loginView.style.setProperty('--login-accent', theme.accent);

      }

      if (rightPane && theme) {

        rightPane.style.setProperty('--login-accent', theme.accent);

      }

    }

    initLoginFeed();



  

// Add DOM listener
document.addEventListener('DOMContentLoaded', function() { if (typeof initLoginFeed === 'function') { initLoginFeed(); } });
