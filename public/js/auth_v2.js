// ============================================================
// auth_v2.js — form wiring for the playful sign-in/sign-up pages
// (views/public_login_v2.php, views/signup_v2.php).
//
// Deliberately kept separate from auth.js/core.js's existing handlers
// rather than touching them: every view in this app is always present
// in the DOM (switchView() just toggles visibility), so the classic
// pages' element ids (public-email, reg-name, ...) can't be reused here
// without creating duplicate ids across the page. Instead this file uses
// its own su2-*/lp2-* ids and calls the same shared, already-parameterized
// helpers the classic pages use wherever possible (api(), switchView(),
// goToDashboard(), getPetTheme(), PET_REAL_PHOTOS, loginFeedFeeds,
// breedsByPetType, clearErrors(), showFieldError(), setButtonLoading()).
//
// Division of labour with auth_scene.js: that file owns everything visual
// around the form (the diorama, the character rig, the signpost, the
// transition out). This file owns fields, validation, the sign-up wizard's
// step state, and submission. Calls only ever go one way — from here into
// av2Scene*.
// ============================================================

const AV2_SIGNUP_PET_TILES = [
  { value: "Dog", emoji: "🐕" },
  { value: "Cat", emoji: "🐈" },
  { value: "Bird", emoji: "🐦" },
  { value: "Fish", emoji: "🐟" },
  { value: "Small Pet", emoji: "🐹" },
  { value: "Reptile", emoji: "🦎" },
  { value: "Other", emoji: "🐾" },
];

// Matches the classic login page's own preview-only dropdown values exactly
// (includes Rabbit, which isn't a real signup pet_type — this row is just a
// decorative "what kind of pet do you have" preview, same as the classic page).
const AV2_LOGIN_PET_PILLS = [
  { value: "", emoji: "✨" },
  { value: "Dog", emoji: "🐕" },
  { value: "Cat", emoji: "🐈" },
  { value: "Bird", emoji: "🐦" },
  { value: "Rabbit", emoji: "🐰" },
  { value: "Fish", emoji: "🐟" },
  { value: "Reptile", emoji: "🦎" },
  { value: "Small Pet", emoji: "🐹" },
  { value: "Other", emoji: "🐾" },
];

const AV2_WIZARD_STEPS = 4;

// Per-step signpost copy, so the scene stays part of the conversation as the
// wizard advances rather than sitting inert behind it.
const AV2_WIZARD_SIGNPOST = {
  1: ["Welcome", "Every pack starts with one very good animal."],
  2: ["Getting to know you", "Breed and name help us find their people."],
  3: ["Almost there", "We'll only send things worth opening."],
  4: ["Last one", "Pick something memorable — they're counting on you."],
};

function av2ReducedMotion() {
  return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

function av2SetAccent(scopeEl, accent) {
  if (scopeEl && accent) scopeEl.style.setProperty("--av2-accent", accent);
}

function av2IsDesktop() {
  return window.matchMedia && window.matchMedia("(min-width: 1024px)").matches;
}

function av2Shake(el) {
  if (!el || av2ReducedMotion()) return;
  el.classList.remove("av2-shake");
  void el.offsetWidth;
  el.classList.add("av2-shake");
  setTimeout(() => el.classList.remove("av2-shake"), 560);
}

// ---------------- Signup: pet-type sticker tiles ----------------

function av2RenderSignupPetTiles() {
  const wrap = document.getElementById("su2-pet-tiles");
  if (!wrap) return;
  wrap.innerHTML = AV2_SIGNUP_PET_TILES.map(
    (t) =>
      `<button type="button" class="av2-pet-tile" data-pet-value="${t.value}" onclick="av2SelectSignupPetType('${t.value}')">
        <span class="av2-pet-emoji">${t.emoji}</span>
        <span class="av2-pet-label">${t.value}</span>
      </button>`
  ).join("");
}

function av2SelectSignupPetType(value) {
  const select = document.getElementById("su2-pet-type");
  if (select) select.value = value;
  document.querySelectorAll("#su2-pet-tiles .av2-pet-tile").forEach((tile) => {
    tile.classList.toggle("av2-selected", tile.dataset.petValue === value);
  });
  av2UpdateBreedOptions("su2-pet-type", "su2-breed");

  const theme = typeof getPetTheme === "function" ? getPetTheme(value) : null;
  if (theme) av2SetAccent(document.getElementById("view-signup-v2"), theme.accent);
  av2SceneSetSpecies(document.getElementById("su2-stage"), value);
}

// Mirrors core.js's updateBreedOptions(), but ends by calling this file's own
// av2ToggleCustomBreedInput() instead of the classic page's hardcoded one.
function av2UpdateBreedOptions(typeSelectId, breedSelectId) {
  const typeEl = document.getElementById(typeSelectId);
  const breedSelect = document.getElementById(breedSelectId);
  if (!typeEl || !breedSelect) return;
  const type = typeEl.value;
  breedSelect.innerHTML = '<option value="">Select Breed...</option>';
  if (type && typeof breedsByPetType !== "undefined" && breedsByPetType[type]) {
    breedsByPetType[type].forEach((b) => {
      const opt = document.createElement("option");
      opt.value = b;
      opt.textContent = b === "other" ? "Other..." : b;
      breedSelect.appendChild(opt);
    });
  }
  av2ToggleCustomBreedInput();
}

function av2ToggleCustomBreedInput() {
  const breedSelect = document.getElementById("su2-breed");
  const customWrap = document.getElementById("su2-custom-breed-wrap");
  if (breedSelect && customWrap) {
    customWrap.style.display = breedSelect.value === "other" ? "block" : "none";
  }
}

// ---------------- Signup: the wizard ----------------

let av2WizardStep = 1;

function av2RenderTrail() {
  const trail = document.getElementById("su2-trail");
  if (!trail) return;
  let html = "";
  for (let i = 1; i <= AV2_WIZARD_STEPS; i++) {
    const state = i < av2WizardStep ? "is-done" : i === av2WizardStep ? "is-current" : "";
    html +=
      `<span class="av2-trail-paw ${state}"><svg viewBox="0 0 24 24" aria-hidden="true">` +
      `<use href="#icon-paw" xlink:href="#icon-paw"></use></svg></span>`;
    if (i < AV2_WIZARD_STEPS) {
      html += `<span class="av2-trail-link ${i < av2WizardStep ? "is-done" : ""}"></span>`;
    }
  }
  trail.innerHTML = html;
  trail.setAttribute("aria-valuenow", String(av2WizardStep));
}

function av2WizardGoTo(step, reverse) {
  const clamped = Math.max(1, Math.min(AV2_WIZARD_STEPS, step));
  const form = document.getElementById("su2-form");
  if (!form) return;

  const current = form.querySelector(".av2-step.is-active");
  const next = form.querySelector(`.av2-step[data-step="${clamped}"]`);
  if (!next || current === next) return;

  if (current) {
    current.classList.remove("is-active", "is-reverse");
    current.classList.add("is-leaving");
    if (reverse) current.classList.add("is-reverse");
    const leaving = current;
    setTimeout(() => leaving.classList.remove("is-leaving", "is-reverse"), 300);
  }

  next.classList.remove("is-leaving");
  next.classList.add("is-active");
  next.classList.toggle("is-reverse", !!reverse);

  av2WizardStep = clamped;
  av2RenderTrail();

  const stage = document.getElementById("su2-stage");
  av2SceneHop(stage);
  av2SceneSetConstellation(stage, clamped, AV2_WIZARD_STEPS);
  const copy = AV2_WIZARD_SIGNPOST[clamped];
  if (copy) av2SceneSetSignpost(stage, copy[0], copy[1], AV2_WIZARD_STEPS, clamped - 1);

  // Focus the step's first real control, but only once its entrance animation
  // has settled — focusing mid-transform makes the panel jump.
  setTimeout(() => {
    const field = next.querySelector("input:not([type=hidden]), select:not(.sr-only)");
    if (field && av2IsDesktop()) field.focus();
  }, 300);
}

// Per-step gating. Returns true when the step is complete; otherwise surfaces
// the reason in the shared error box and shakes the card.
function av2ValidateStep(step) {
  const fail = (msg) => {
    showFieldError("su2-error", msg);
    av2Shake(document.querySelector("#view-signup-v2 .av2-card"));
    av2SceneReact(document.getElementById("su2-stage"), "sad", 1400);
    return false;
  };

  if (step === 1) {
    if (!document.getElementById("su2-name").value.trim()) return fail("What's your pet's name?");
    if (!document.getElementById("su2-pet-type").value) return fail("Pick what kind of pet they are.");
  }

  if (step === 2) {
    const breed = document.getElementById("su2-breed").value;
    if (!breed) return fail("Choose a breed — 'Mixed Breed' counts.");
    if (breed === "other" && !document.getElementById("su2-custom-breed").value.trim()) {
      return fail("Type the breed name you'd like to add.");
    }
    if (!document.getElementById("su2-parent-name").value.trim()) return fail("And what should we call you?");
  }

  if (step === 3) {
    const email = document.getElementById("su2-email").value.trim();
    if (!email) return fail("We need an email address to reach you.");
    if (!/^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email)) return fail("That email doesn't look quite right.");
  }

  return true;
}

function av2WizardNext() {
  clearErrors();
  if (!av2ValidateStep(av2WizardStep)) return;
  av2WizardGoTo(av2WizardStep + 1, false);
}

function av2WizardBack() {
  clearErrors();
  av2WizardGoTo(av2WizardStep - 1, true);
}

function av2WizardReset() {
  const form = document.getElementById("su2-form");
  if (!form) return;
  form.querySelectorAll(".av2-step").forEach((s) => s.classList.remove("is-active", "is-leaving", "is-reverse"));
  const first = form.querySelector('.av2-step[data-step="1"]');
  if (first) first.classList.add("is-active");
  av2WizardStep = 1;
  av2RenderTrail();
  av2SceneSetConstellation(document.getElementById("su2-stage"), 1, AV2_WIZARD_STEPS);
}

// ---------------- Login: pet-preview pills + rotating signpost ----------------

let av2HeadlineTimer = null;
// Starts at -1 so the first (pre-increment) render lands on entry 0 — the Dog
// feed — which is the pet type the page's default marigold accent belongs to.
// Starting at 0 would open the page on entry 1 with a mismatched accent.
let av2HeadlineIdx = -1;

function av2RenderLoginPetPills() {
  const wrap = document.getElementById("lp2-pet-pills");
  if (!wrap) return;
  wrap.innerHTML = AV2_LOGIN_PET_PILLS.map(
    (t) =>
      `<button type="button" class="av2-pill" data-pet-value="${t.value}" title="${t.value || "Just exploring"}" onclick="av2SelectLoginPetPill('${t.value}')">${t.emoji}</button>`
  ).join("");
  av2SetLoginPillSelected("");
}

function av2SetLoginPillSelected(value) {
  document.querySelectorAll("#lp2-pet-pills .av2-pill").forEach((pill) => {
    pill.classList.toggle("av2-selected", pill.dataset.petValue === value);
  });
}

function av2SelectLoginPetPill(value) {
  av2SetLoginPillSelected(value);
  if (value) {
    av2RenderHeadline(value);
    av2StopHeadlineRotation();
  } else {
    av2StartHeadlineRotation();
  }
}

// Drives both the signpost copy and the stage character from the same
// loginFeedFeeds entry auth.js already builds for the classic login page.
function av2RenderHeadline(forceTheme) {
  const stage = document.getElementById("lp2-stage");
  if (!stage || typeof loginFeedFeeds === "undefined" || !loginFeedFeeds || !loginFeedFeeds.length) return;

  if (forceTheme) {
    const found = loginFeedFeeds.findIndex((f) => f.petTheme === forceTheme);
    av2HeadlineIdx = found >= 0 ? found : av2HeadlineIdx;
  } else {
    av2HeadlineIdx = (av2HeadlineIdx + 1) % loginFeedFeeds.length;
  }

  const f = loginFeedFeeds[av2HeadlineIdx];
  const species = forceTheme || f.petTheme;
  const theme = typeof getPetTheme === "function" ? getPetTheme(species) : { accent: "#F2A93B" };

  av2SetAccent(document.getElementById("view-public-login-v2"), theme.accent);
  av2SetLoginPillSelected(forceTheme || "");
  av2SceneSetSpecies(stage, species);
  av2SceneSetSignpost(
    stage,
    `${f.icon || "🐾"} ${species} · active now`,
    f.headline || species,
    loginFeedFeeds.length,
    av2HeadlineIdx
  );
}

function av2StartHeadlineRotation() {
  av2StopHeadlineRotation();
  av2RenderHeadline();
  if (!av2ReducedMotion()) {
    av2HeadlineTimer = setInterval(() => av2RenderHeadline(), 6000);
  }
}

function av2StopHeadlineRotation() {
  if (av2HeadlineTimer) clearInterval(av2HeadlineTimer);
  av2HeadlineTimer = null;
}

// ---------------- View entry/exit ----------------

function openAuthV2Login() {
  switchView("view-public-login-v2");
  const stage = document.getElementById("lp2-stage");
  av2SceneBuild(stage);
  av2SceneSplitLetters(document.getElementById("lp2-headline"));
  av2StartHeadlineRotation();
  if (window.lucide) lucide.createIcons();
}

function closeAuthV2Login(target) {
  av2StopHeadlineRotation();
  switchView(target || "view-public-login");
  if (target === "view-signup-v2") openAuthV2Signup();
}

function openAuthV2Signup() {
  switchView("view-signup-v2");
  const stage = document.getElementById("su2-stage");
  // "den" — the night forest. The sign-in keeps the default golden-hour park,
  // which is most of what makes the two pages read as different places.
  av2SceneBuild(stage, "den");
  av2WizardReset();
  const selected = document.getElementById("su2-pet-type")?.value || "Dog";
  av2SceneSetSpecies(stage, selected);
  const copy = AV2_WIZARD_SIGNPOST[1];
  av2SceneSetSignpost(stage, copy[0], copy[1], AV2_WIZARD_STEPS, 0);
  if (window.lucide) lucide.createIcons();
}

function closeAuthV2Signup(target) {
  switchView(target || "view-signup");
  if (target === "view-public-login-v2") openAuthV2Login();
}

// ---------------- Password show/hide + the "cover your eyes" gag ----------------

function av2WireEyeToggle(toggleId, inputId, iconId) {
  const toggle = document.getElementById(toggleId);
  const input = document.getElementById(inputId);
  const icon = document.getElementById(iconId);
  if (!toggle || !input) return;
  toggle.addEventListener("click", () => {
    input.type = input.type === "text" ? "password" : "text";
    if (icon) {
      icon.setAttribute("data-lucide", input.type === "text" ? "eye-off" : "eye");
      if (window.lucide) lucide.createIcons();
    }
    input.dispatchEvent(new Event("input"));
  });
}

// The stage character covers its eyes while a password is being typed — the
// same gag core.js's initPasswordMascot() plays with the classic sign-up's
// small inline mascot, moved onto the full-size character so the two pages
// don't end up with two mascots doing the same thing.
function av2WireShy(inputId, stageId) {
  const input = document.getElementById(inputId);
  const stage = document.getElementById(stageId);
  if (!input || !stage) return;
  const update = () => av2SceneSetShy(stage, input.type === "password" && input.value.length > 0);
  input.addEventListener("input", update);
  input.addEventListener("focus", update);
  input.addEventListener("blur", () => av2SceneSetShy(stage, false));
}

// ---------------- Success paw-stamp burst ----------------

function av2BurstPaws(originEl) {
  if (av2ReducedMotion() || !originEl) return;
  const rect = originEl.getBoundingClientRect();
  const originX = rect.left + rect.width / 2;
  const originY = rect.top + rect.height / 2;
  const count = 7;
  for (let i = 0; i < count; i++) {
    const angle = (Math.PI * 2 * i) / count + Math.random() * 0.4;
    const dist = 60 + Math.random() * 40;
    const dx = Math.cos(angle) * dist;
    const dy = Math.sin(angle) * dist - 30;
    const paw = document.createElementNS("http://www.w3.org/2000/svg", "svg");
    paw.setAttribute("viewBox", "0 0 24 24");
    paw.setAttribute("width", "16");
    paw.setAttribute("height", "16");
    paw.classList.add("av2-burst-paw");
    paw.style.left = originX + "px";
    paw.style.top = originY + "px";
    paw.style.setProperty("--av2-burst-transform", `translate(${dx}px, ${dy}px) scale(1) rotate(${(Math.random() * 60 - 30).toFixed(0)}deg)`);
    paw.innerHTML = '<use href="#icon-paw" xlink:href="#icon-paw"></use>';
    document.body.appendChild(paw);
    setTimeout(() => paw.remove(), 750);
  }
}

// ---------------- Signup submit (mirrors auth.js's signup-step-1 handler) ----------------

document.getElementById("su2-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();

  const stage = document.getElementById("su2-stage");
  const card = document.querySelector("#view-signup-v2 .av2-card");
  const failHere = (msg) => {
    showFieldError("su2-error", msg);
    av2Shake(card);
    av2SceneReact(stage, "sad", 1600);
  };

  // On desktop the earlier steps were already gated; on mobile every field is
  // on screen at once and nothing has been validated yet, so re-check all of
  // them here. This is also the guard for a keyboard submit that skipped ahead.
  for (let step = 1; step <= 3; step++) {
    if (!av2ValidateStep(step)) {
      if (av2IsDesktop()) av2WizardGoTo(step, step < av2WizardStep);
      return;
    }
  }

  const petName = document.getElementById("su2-name").value.trim();
  const parentName = document.getElementById("su2-parent-name").value.trim();
  const petType = document.getElementById("su2-pet-type").value;
  const breedSelect = document.getElementById("su2-breed").value;
  const customBreed = document.getElementById("su2-custom-breed")?.value.trim();
  const breed = breedSelect === "other" ? customBreed : breedSelect;
  const email = document.getElementById("su2-email").value.trim();
  const password = document.getElementById("su2-password").value;

  if (password.length < 10) {
    failHere("Password must be at least 10 characters.");
    return;
  }

  const btn = document.getElementById("su2-submit-btn");
  setButtonLoading(btn, true, "Creating your pack…");
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
      failHere(data.message || "Could not create your account.");
      return;
    }

    av2SceneSetShy(stage, false);
    av2SceneReact(stage, "happy", 2000);
    av2BurstPaws(btn);

    if (data.verification_required) {
      pendingSignupEmail = email;
      pendingSignupPassword = password;
      const target = document.getElementById("verify-email-target");
      if (target) target.textContent = email;
      av2ScenePlayWipe(btn, () => {
        switchView("view-verify-email");
        document.querySelector("#otp-inputs .otp-box")?.focus();
      });
      return;
    }

    av2ScenePlayWipe(btn, () => goToDashboard(data.user));
  } catch (err) {
    console.error(err);
    failHere("Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

// Enter should advance the wizard rather than submit from step 1, which is
// what a single <form> spanning all four steps would otherwise do.
document.getElementById("su2-form")?.addEventListener("keydown", (e) => {
  if (e.key !== "Enter") return;
  if (e.target && e.target.tagName === "TEXTAREA") return;
  if (!av2IsDesktop() || av2WizardStep >= AV2_WIZARD_STEPS) return;
  e.preventDefault();
  av2WizardNext();
});

// ---------------- Login submit (mirrors auth.js's public-login-form handler) ----------------

document.getElementById("lp2-login-form")?.addEventListener("submit", async (e) => {
  e.preventDefault();
  clearErrors();

  const stage = document.getElementById("lp2-stage");
  const card = document.querySelector("#view-public-login-v2 .av2-card");
  const failHere = (msg) => {
    showFieldError("lp2-error", msg);
    av2Shake(card);
    av2SceneReact(stage, "sad", 1600);
  };

  const email = document.getElementById("lp2-email").value.trim();
  const password = document.getElementById("lp2-password").value;
  if (!email || !password) {
    failHere("Enter your email and password.");
    return;
  }

  const btn = document.getElementById("lp2-submit-btn");
  setButtonLoading(btn, true, "Signing in…");
  try {
    const data = await api("public_login", { email, password });
    if (data.status !== "success") {
      failHere(data.message || "Invalid email or password.");
      return;
    }
    av2SceneSetShy(stage, false);
    av2SceneReact(stage, "happy", 2000);
    av2BurstPaws(btn);
    av2StopHeadlineRotation();
    av2ScenePlayWipe(btn, () => goToDashboard(data.user));
  } catch (err) {
    console.error(err);
    failHere("Something went wrong. Please try again.");
  } finally {
    setButtonLoading(btn, false);
  }
});

// ---------------- Init (deferred script runs after DOM parse) ----------------

av2RenderSignupPetTiles();
av2RenderLoginPetPills();
av2RenderTrail();
av2WireEyeToggle("lp2-eyeToggle", "lp2-password", "lp2-eyeIcon");
av2WireEyeToggle("su2-eyeToggle", "su2-password", "su2-eyeIcon");
av2WireShy("lp2-password", "lp2-stage");
av2WireShy("su2-password", "su2-stage");

// ?ui=playful / ?ui=playful-signup open the redesigned pages directly, so they
// can be linked and demoed without going through the classic pages' toggle.
// auth.js's own DOMContentLoaded session restore runs after this deferred
// script and calls goToDashboard() when a session exists, so an already
// signed-in visitor still lands on the feed rather than on a login screen.
(function av2ApplyUiParam() {
  let ui = "";
  try {
    ui = new URLSearchParams(window.location.search).get("ui") || "";
  } catch (e) {
    return;
  }
  if (ui === "playful") openAuthV2Login();
  else if (ui === "playful-signup") openAuthV2Signup();
})();
