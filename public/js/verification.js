// "Verified Pet Parent" flow: submit modal, status banner, and the badge
// shown on the pet profile page once approved. See api/routes/verification.php
// for why this replaces eSamaj's Aadhaar/PAN KYC flow.

function onVerificationProofTypeChange() {
  const proofType = document.getElementById("vf-proof-type").value;
  document.getElementById("vf-microchip-wrap").classList.toggle("hidden", proofType !== "microchip");
  document.getElementById("vf-document-wrap").classList.toggle("hidden", !["vet_record", "adoption_papers"].includes(proofType));
}

async function handleVerificationDocumentUpload(input) {
  await verificationUploadTo(input, "vf-document-status", "Document");
}
async function handleVerificationPetPhotoUpload(input) {
  await verificationUploadTo(input, "vf-pet-photo-status", "Pet photo");
}
async function handleVerificationOwnerPhotoUpload(input) {
  await verificationUploadTo(input, "vf-owner-photo-status", "Owner photo");
}

async function verificationUploadTo(input, statusElId, label) {
  const file = input.files?.[0];
  const statusEl = document.getElementById(statusElId);
  if (!file) return;
  if (statusEl) statusEl.textContent = "Uploading…";
  try {
    const data = await uploadPhotoFile(file, "verification");
    if (data.status !== "success") {
      if (statusEl) statusEl.textContent = data.message || "Upload failed.";
      return;
    }
    input.dataset.uploadedUrl = data.photo_url;
    if (statusEl) statusEl.textContent = `${label} uploaded.`;
  } catch (err) {
    console.error(err);
    if (statusEl) statusEl.textContent = "Upload failed.";
  }
}

function verificationStatusBannerHtml(status) {
  const styles = {
    pending: "bg-amber-50 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
    approved: "bg-emerald-50 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-300",
    rejected: "bg-red-50 text-red-700 dark:bg-red-900/30 dark:text-red-300",
  };
  const messages = {
    pending: "Your verification request is pending review.",
    approved: "You're a Verified Pet Parent!",
    rejected: "Your last request wasn't approved. You can submit a new one below.",
  };
  return { cls: styles[status] || "", text: messages[status] || "" };
}

async function openVerificationModal() {
  const modal = document.getElementById("verification-modal");
  if (!modal) return;
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  document.getElementById("verification-modal-error")?.classList.add("hidden");

  document.getElementById("vf-parent-name").value = currentUserObj?.parent_name || "";
  document.getElementById("vf-current-city").value = currentUserObj?.current_city || "";
  document.getElementById("vf-proof-type").value = "microchip";
  onVerificationProofTypeChange();

  const banner = document.getElementById("verification-status-banner");
  const formWrap = document.getElementById("verification-form-wrap");
  banner.classList.add("hidden");
  formWrap.classList.remove("hidden");

  if (window.lucide) lucide.createIcons();

  try {
    const data = await api("get_my_verification_status", {});
    if (data.status !== "success") return;

    if (data.is_verified) {
      const { cls, text } = verificationStatusBannerHtml("approved");
      banner.className = `mb-4 p-3 rounded-xl text-sm ${cls}`;
      banner.textContent = text;
      banner.classList.remove("hidden");
      formWrap.classList.add("hidden");
      return;
    }

    if (data.latest_request?.status === "pending") {
      const { cls, text } = verificationStatusBannerHtml("pending");
      banner.className = `mb-4 p-3 rounded-xl text-sm ${cls}`;
      banner.textContent = text;
      banner.classList.remove("hidden");
      formWrap.classList.add("hidden");
      return;
    }

    if (data.latest_request?.status === "rejected") {
      const { cls, text } = verificationStatusBannerHtml("rejected");
      banner.className = `mb-4 p-3 rounded-xl text-sm ${cls}`;
      banner.textContent = text;
      banner.classList.remove("hidden");
    }
  } catch (err) {
    console.error(err);
  }
}

function closeVerificationModal() {
  pcHideModal("verification-modal");
}

async function submitVerificationRequest() {
  const btn = document.getElementById("submit-verification-btn");
  const errorEl = document.getElementById("verification-modal-error");
  errorEl?.classList.add("hidden");
  setButtonLoading(btn, true, "Submitting…");

  try {
    const proofType = document.getElementById("vf-proof-type").value;
    const payload = {
      parent_name: document.getElementById("vf-parent-name").value.trim(),
      proof_type: proofType,
      microchip_number: document.getElementById("vf-microchip-number").value.trim(),
      current_city: document.getElementById("vf-current-city").value.trim(),
      reason: document.getElementById("vf-reason").value.trim(),
      pet_photo_url: document.getElementById("vf-pet-photo-input").dataset.uploadedUrl || "",
      owner_photo_url: document.getElementById("vf-owner-photo-input").dataset.uploadedUrl || "",
      proof_document_url: document.getElementById("vf-document-input").dataset.uploadedUrl || "",
    };

    const data = await api("submit_verification", payload);
    if (data.status !== "success") {
      errorEl.textContent = data.message || "Could not submit your request.";
      errorEl.classList.remove("hidden");
      return;
    }

    showToast("Verification request submitted.", "success");
    closeVerificationModal();
    if (document.getElementById("view-pet-profile")?.classList.contains("active")) {
      loadPetProfileVerificationBadge();
    }
  } catch (err) {
    console.error(err);
    errorEl.textContent = "Something went wrong. Please try again.";
    errorEl.classList.remove("hidden");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function loadPetProfileVerificationBadge() {
  const badges = ["pp-verified-badge", "hub-verified-badge"]
    .map((id) => document.getElementById(id))
    .filter(Boolean);
  if (!badges.length) return;
  try {
    const data = await api("get_my_verification_status", {});
    const isVerified = data.status === "success" && data.is_verified;
    badges.forEach((badge) => badge.classList.toggle("hidden", !isVerified));
  } catch (err) {
    console.error(err);
  }
}

document.addEventListener("click", (e) => {
  if (e.target.id === "verification-modal") closeVerificationModal();
});
