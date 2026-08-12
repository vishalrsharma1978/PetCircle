// Pack profile: edit modal (avatar/cover upload, pet + parent details) and
// the read-only "My Pet's Profile" page. Loads/saves against api/routes/core.php.

// Hides a skeleton only once the image has actually painted (or failed),
// not as soon as the API call resolves — setting img.src just starts the
// fetch/decode, so hiding the skeleton immediately after leaves a gap where
// nothing but the container's own background (a colored circle, a gradient)
// is visible until the bitmap arrives. That gap is what reads as "flash of
// the placeholder" before the real content snaps in.
function revealImageWhenLoaded(img, skeletonEl) {
  if (!img) {
    skeletonEl?.classList.add("hidden");
    return;
  }
  const hide = () => skeletonEl?.classList.add("hidden");
  img.onload = hide;
  img.onerror = hide;
  if (img.complete && img.naturalWidth > 0) hide();
}

function setAvatarPreview(imgId, textId, url, fallbackLetter, skeletonId) {
  const img = document.getElementById(imgId);
  const text = document.getElementById(textId);
  const skeleton = skeletonId ? document.getElementById(skeletonId) : null;
  if (url) {
    if (img) {
      revealImageWhenLoaded(img, skeleton);
      img.src = url;
      img.classList.remove("hidden");
    } else {
      skeleton?.classList.add("hidden");
    }
    if (text) text.classList.add("hidden");
  } else {
    if (img) img.classList.add("hidden");
    if (text) {
      text.textContent = fallbackLetter || "P";
      text.classList.remove("hidden");
    }
    skeleton?.classList.add("hidden");
  }
}

function setCoverPreview(containerImgId, url, skeletonId) {
  const img = document.getElementById(containerImgId);
  const skeleton = skeletonId ? document.getElementById(skeletonId) : null;
  if (!img) {
    skeleton?.classList.add("hidden");
    return;
  }
  if (url) {
    revealImageWhenLoaded(img, skeleton);
    img.src = url;
    img.classList.remove("hidden");
  } else {
    img.classList.add("hidden");
    skeleton?.classList.add("hidden");
  }
}

function selectBreedWithValue(typeSelectId, breedSelectId, petType, breedValue) {
  const typeSelect = document.getElementById(typeSelectId);
  if (typeSelect) typeSelect.value = petType || "";
  updateBreedOptions(typeSelectId, breedSelectId);
  const breedSelect = document.getElementById(breedSelectId);
  if (!breedSelect || !breedValue) return;
  const hasOption = Array.from(breedSelect.options).some((o) => o.value === breedValue);
  if (!hasOption) {
    const opt = document.createElement("option");
    opt.value = breedValue;
    opt.textContent = breedValue;
    breedSelect.appendChild(opt);
  }
  breedSelect.value = breedValue;
}

async function openProfileModal() {
  const modal = document.getElementById("profile-modal");
  if (!modal) return;
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  document.getElementById("profile-modal-error")?.classList.add("hidden");

  if (window.lucide) lucide.createIcons();

  const profile = currentUserObj || {};
  document.getElementById("prof-pet-name").value = profile.pet_name || profile.name || "";
  document.getElementById("prof-parent-name").value = profile.parent_name || "";
  document.getElementById("prof-gender").value = profile.gender || "";
  document.getElementById("prof-city").value = profile.current_city || "";
  document.getElementById("prof-contact").value = profile.mobile_number || "";
  document.getElementById("prof-bio").value = profile.bio || "";
  document.getElementById("profile-modal-title-name").textContent = profile.pet_name || profile.name || "Pet Name";
  document.getElementById("prof-public-toggle").checked = profile.visibility !== "private";
  selectBreedWithValue("prof-pet-type", "prof-breed", profile.pet_type, profile.breed);
  setAvatarPreview("profile-modal-avatar-img", "profile-modal-avatar-text", profile.profile_photo_url, (profile.pet_name || "P")[0]);
  setCoverPreview("prof-banner-img", profile.cover_photo_url);

  // Fields not carried in the session payload (microchip, dob) — fetch fresh.
  try {
    const data = await api("get_profile", {});
    if (data.status === "success" && data.profile) {
      document.getElementById("prof-microchip").value = data.profile.microchip_number || "";
      document.getElementById("prof-dob").value = data.profile.date_of_birth || "";
    }
  } catch (err) {
    console.warn("Could not load full profile:", err);
  }
}

function closeProfileModal() {
  document.getElementById("profile-modal")?.classList.add("hidden");
  document.getElementById("profile-modal")?.classList.remove("flex");
}

async function uploadPhotoFile(file, bucket, folder = "") {
  const formData = new FormData();
  formData.append("photo", file);
  formData.append("bucket", bucket);
  if (folder) formData.append("folder", folder);
  const response = await fetch("api/index.php?action=upload_photo", {
    method: "POST",
    credentials: "include",
    headers: secureUploadHeaders(),
    body: formData,
  });
  return response.json();
}

async function handleAvatarUpload(input) {
  const file = input.files?.[0];
  if (!file) return;
  try {
    const data = await uploadPhotoFile(file, "profile-photos");
    if (data.status !== "success") {
      showToast(data.message || "Could not upload photo.", "error");
      return;
    }
    setAvatarPreview("profile-modal-avatar-img", "profile-modal-avatar-text", data.photo_url, "P");
    input.dataset.pendingUrl = data.photo_url;
  } catch (err) {
    console.error(err);
    showToast("Could not upload photo.", "error");
  }
}

async function handleCoverUpload(input) {
  const file = input.files?.[0];
  if (!file) return;
  try {
    const data = await uploadPhotoFile(file, "cover-photos");
    if (data.status !== "success") {
      showToast(data.message || "Could not upload photo.", "error");
      return;
    }
    setCoverPreview("prof-banner-img", data.photo_url);
    input.dataset.pendingUrl = data.photo_url;
  } catch (err) {
    console.error(err);
    showToast("Could not upload photo.", "error");
  }
}

async function saveProfile() {
  const btn = document.getElementById("save-profile-btn");
  const errorEl = document.getElementById("profile-modal-error");
  errorEl?.classList.add("hidden");
  setButtonLoading(btn, true, "Saving…");

  try {
    const petType = document.getElementById("prof-pet-type").value;
    const breed = document.getElementById("prof-breed").value;
    const currentProfile = currentUserObj || {};

    // pet_type/breed changes go through their own action (it also drops
    // now-mismatched group memberships), everything else through update_profile.
    if (petType && breed && (petType !== currentProfile.pet_type || breed !== currentProfile.breed)) {
      const typeRes = await api("change_pet_type_breed", { pet_type: petType, breed });
      if (typeRes.status !== "success") {
        errorEl.textContent = typeRes.message || "Could not update pet type/breed.";
        errorEl.classList.remove("hidden");
        return;
      }
      if (typeRes.removed_group_count > 0) {
        showToast(`Removed from ${typeRes.removed_group_count} group(s) that no longer match.`, "info");
      }
    }

    const avatarInput = document.getElementById("avatar-upload-input");
    const coverInput = document.getElementById("cover-photo-input");

    const payload = {
      pet_name: document.getElementById("prof-pet-name").value.trim(),
      parent_name: document.getElementById("prof-parent-name").value.trim(),
      gender: document.getElementById("prof-gender").value,
      date_of_birth: document.getElementById("prof-dob").value,
      current_city: document.getElementById("prof-city").value.trim(),
      mobile_number: document.getElementById("prof-contact").value.trim(),
      microchip_number: document.getElementById("prof-microchip").value.trim(),
      bio: document.getElementById("prof-bio").value.trim(),
      visibility: document.getElementById("prof-public-toggle").checked ? "public" : "private",
    };
    if (avatarInput?.dataset.pendingUrl) payload.profile_photo_url = avatarInput.dataset.pendingUrl;
    if (coverInput?.dataset.pendingUrl) payload.cover_photo_url = coverInput.dataset.pendingUrl;

    const data = await api("update_profile", payload);
    if (data.status !== "success") {
      errorEl.textContent = data.message || "Could not save your changes.";
      errorEl.classList.remove("hidden");
      return;
    }

    if (avatarInput) delete avatarInput.dataset.pendingUrl;
    if (coverInput) delete coverInput.dataset.pendingUrl;

    // Refresh the in-memory session copy so the rest of the UI reflects the edit immediately.
    const fresh = await api("session_me", {});
    if (fresh.status === "success" && fresh.user) {
      persistCurrentSession(fresh.user);
      setAvatarPreview("header-avatar-img", "header-avatar-letter", fresh.user.profile_photo_url, (fresh.user.pet_name || "P")[0]);
    }

    showToast("Profile updated.", "success");
    closeProfileModal();
    if (document.getElementById("view-pet-profile")?.classList.contains("active")) {
      loadPetProfileView();
    }
    loadHubHero();
  } catch (err) {
    console.error(err);
    errorEl.textContent = "Something went wrong. Please try again.";
    errorEl.classList.remove("hidden");
  } finally {
    setButtonLoading(btn, false);
  }
}

function formatDob(dob) {
  if (!dob) return "—";
  const d = new Date(dob);
  if (Number.isNaN(d.getTime())) return "—";
  return d.toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" });
}

async function loadPetProfileView() {
  try {
    const data = await api("get_profile", {});
    if (data.status !== "success" || !data.profile) {
      showToast(data.message || "Could not load profile.", "error");
      return;
    }
    const p = data.profile;
    document.getElementById("pp-pet-name").textContent = p.pet_name || "Unnamed pet";
    document.getElementById("pp-type-breed").textContent = [p.pet_type, p.breed].filter(Boolean).join(" · ") || "Pet type not set";
    document.getElementById("pp-parent-name").textContent = p.parent_name || "—";
    document.getElementById("pp-city").textContent = p.current_city || "—";
    document.getElementById("pp-gender").textContent = p.gender || "—";
    document.getElementById("pp-dob").textContent = formatDob(p.date_of_birth);
    document.getElementById("pp-bio").textContent = p.bio || "No bio yet.";
    setAvatarPreview("pp-avatar-img", "pp-avatar-text", p.profile_photo_url, (p.pet_name || "P")[0]);
    setCoverPreview("pp-cover-img", p.cover_photo_url);

    const microchipWrap = document.getElementById("pp-microchip-wrap");
    if (p.microchip_number) {
      document.getElementById("pp-microchip").textContent = p.microchip_number;
      microchipWrap?.classList.remove("hidden");
    } else {
      microchipWrap?.classList.add("hidden");
    }

    loadPetProfileVerificationBadge();
  } catch (err) {
    console.error(err);
    showToast("Could not load profile.", "error");
  }
}

document.addEventListener("click", (e) => {
  if (e.target.id === "profile-modal") closeProfileModal();
});
