// ============================================================
// auth_scene.js — the runtime for the playful auth pages' immersive
// desktop layer: the parallax diorama, the character rig's reactions,
// the rotating signpost headline, and the transition out of the page.
//
// Split from auth_v2.js on responsibility: auth_v2.js owns the forms
// (fields, validation, submit, wizard state), this owns everything
// visual around them. auth_v2.js calls into the av2Scene* functions
// below; nothing here reaches back into form state.
//
// The stage layers are built here rather than authored in the two
// view files so the login and sign-up pages share exactly one scene
// definition instead of duplicating ~60 lines of SVG each. The
// characters are the opposite trade-off — they are detailed artwork,
// so they live as readable markup in components/auth_mascots.php and
// are cloned out of it here. Cloning (rather than <use href>) matters:
// a <use> instance lives in a shadow tree that document CSS cannot
// select into, so auth_scene.css's .pcm-* keyframes would never run.
// ============================================================

function av2SceneReducedMotion() {
  return window.matchMedia && window.matchMedia("(prefers-reduced-motion: reduce)").matches;
}

// How far the eye highlight travels relative to the pupil. The glint resting
// offsets in components/auth_mascots.php are sized against this value — raising
// it is safe, lowering it is not, because a bigger gap between pupil and glint
// is what pushes the highlight over the pupil's edge.
const AV2_GLINT_FOLLOW = 0.5;

// Two worlds, one builder. The sign-in stands in a golden-hour park; the
// sign-up stands in a night den — the same dark forest green the CLASSIC
// sign-up's left panel already uses (portal.css's .den, #16241C -> #1E332A),
// so the two playful pages differ from each other along an axis the app had
// already established rather than one invented here.
//
// Layer fills are color-mix() expressions against --av2-accent rather than
// fixed hexes, so re-theming the scene for a pet type is a single custom
// property write — hills, treeline and grass all re-light together. Note how
// much lower the den's accent percentages are: the park wants the pet's colour
// to wash over the whole scene, but the den has to stay night-time even under
// Bird's #0ea5e9 or Small Pet's #eab308, so the accent only tints it.
const AV2_SCENE_VARIANTS = {
  park: {
    hills: "color-mix(in srgb, var(--av2-accent, #F2A93B) 22%, #A8CDA0)",
    trees: "color-mix(in srgb, var(--av2-accent, #F2A93B) 16%, #7FB682)",
    ground: "color-mix(in srgb, var(--av2-accent, #F2A93B) 13%, #93C98C)",
    fore: "color-mix(in srgb, var(--av2-accent, #F2A93B) 10%, #5E9A6B)",
    trunk: "#8E6534",
    canopy: "round",
    motes: 14,
    clouds: 3,
  },
  den: {
    // Hills sit lighter than the treeline on purpose: the pines have to read
    // as silhouettes cut out against something, and the sky alone is too close
    // to their own value to do that job.
    hills: "color-mix(in srgb, var(--av2-accent, #F2A93B) 14%, #2A4636)",
    trees: "color-mix(in srgb, var(--av2-accent, #F2A93B) 6%, #0E1A14)",
    ground: "color-mix(in srgb, var(--av2-accent, #F2A93B) 10%, #1E332A)",
    fore: "color-mix(in srgb, var(--av2-accent, #F2A93B) 7%, #101C17)",
    trunk: "#101C17",
    canopy: "pine",
    motes: 10,
    clouds: 1,
  },
};

function av2SceneFills(variant) {
  return AV2_SCENE_VARIANTS[variant] || AV2_SCENE_VARIANTS.park;
}

// x offset, canopy radius, trunk height — a fixed layout rather than
// Math.random() so the treeline does not reshuffle itself on every view switch.
// Heights are a large fraction of the 300-unit viewBox on purpose: the ground
// and foreground layers overlap the bottom of this one, so short trees end up
// entirely buried behind them.
const AV2_TREES = [
  [70, 54, 232], [190, 40, 178], [300, 60, 262], [430, 44, 196],
  [560, 52, 244], [690, 38, 168], [800, 58, 254], [930, 42, 188],
  [1050, 54, 226], [1160, 40, 172],
];

// `fit` is the layer's preserveAspectRatio. Anything with recognisable shapes
// (round tree canopies, bushes) must use "xMidYMax slice" so it scales
// uniformly and crops at the sides — stretching a 1200x300 viewBox into a wide
// short box with "none" flattens the canopies into unreadable smears. Only the
// plain rolling bands can afford "none".
function av2SceneLayer(className, depth, viewBox, fit, inner) {
  return (
    `<svg class="av2-layer ${className}" data-depth="${depth}" viewBox="${viewBox}" ` +
    `preserveAspectRatio="${fit}" aria-hidden="true" focusable="false">${inner}</svg>`
  );
}

// Both canopy shapes reuse the same AV2_TREES placement table, so the park and
// the den share one skyline rhythm and only the silhouette changes.
function av2SceneTreelineMarkup(variant) {
  const f = av2SceneFills(variant);

  const canopies = AV2_TREES.map(([x, r, h]) => {
    const top = 300 - h;
    const trunk = `<rect x="${x - 7}" y="${top}" width="14" height="${h}" fill="${f.trunk}" rx="4"/>`;

    if (f.canopy === "pine") {
      // Three stacked triangles, each wider and lower than the one above, which
      // is what separates a conifer silhouette from a lollipop at this scale.
      const tier = (i) => {
        const w = r * (0.62 + i * 0.22);
        const yTop = top + i * r * 0.52;
        const yBot = yTop + r * 1.08;
        return `<path d="M${x} ${yTop} L${x + w} ${yBot} L${x - w} ${yBot} Z" fill="${f.trees}"/>`;
      };
      return trunk + tier(0) + tier(1) + tier(2);
    }

    return (
      trunk +
      `<ellipse cx="${x}" cy="${top}" rx="${r}" ry="${r * 0.92}" fill="${f.trees}"/>` +
      `<ellipse cx="${x - r * 0.42}" cy="${top + r * 0.5}" rx="${r * 0.62}" ry="${r * 0.58}" fill="${f.trees}"/>` +
      `<ellipse cx="${x + r * 0.44}" cy="${top + r * 0.46}" rx="${r * 0.58}" ry="${r * 0.54}" fill="${f.trees}"/>`
    );
  }).join("");

  return canopies + `<rect x="0" y="286" width="1200" height="14" fill="${f.trees}"/>`;
}

// Pollen in the park, fireflies in the den — fewer, slower and drifting less
// far, so they read as insects hanging in the dark rather than blown seed.
function av2SceneMotesMarkup(variant) {
  const f = av2SceneFills(variant);
  const den = f.canopy === "pine";
  let out = "";
  for (let i = 0; i < f.motes; i++) {
    const size = (den ? 4 : 3) + (i % 4) * 1.6;
    const left = 6 + ((i * 37) % 88);
    const bottom = 4 + ((i * 23) % 46);
    const dur = (den ? 14 : 9) + (i % 6) * 2.4;
    const delay = -(i * 1.7).toFixed(1);
    const mx = ((i % 5) - 2) * (den ? 18 : 26);
    const my = -((den ? 70 : 110) + (i % 4) * 46);
    out +=
      `<span class="av2-mote" style="width:${size}px;height:${size}px;left:${left}%;bottom:${bottom}%;` +
      `animation-duration:${dur}s;animation-delay:${delay}s;--mx:${mx}px;--my:${my}px;"></span>`;
  }
  return out;
}

// Den only. A still field of pinpricks high in the sky, distinct from both the
// drifting fireflies and the constellation — those twinkle in place and never
// move, so they read as depth behind everything else.
function av2SceneStarsMarkup() {
  let out = "";
  for (let i = 0; i < 26; i++) {
    const size = 1.5 + (i % 3) * 0.9;
    const left = 2 + ((i * 53) % 96);
    const top = 2 + ((i * 29) % 46);
    const dur = 3 + (i % 5) * 1.3;
    const delay = -(i * 0.9).toFixed(1);
    out +=
      `<span class="av2-star" style="width:${size}px;height:${size}px;left:${left}%;top:${top}%;` +
      `animation-duration:${dur}s;animation-delay:${delay}s;"></span>`;
  }
  return out;
}

// Builds the diorama into a stage element. `variant` is "park" (the sign-in's
// golden-hour default) or "den" (the sign-up's night forest). Idempotent —
// calling it again for an already-built stage is a no-op, so view switches
// stay cheap.
function av2SceneBuild(stage, variant) {
  if (!stage || stage.dataset.built === "1") return;
  const name = AV2_SCENE_VARIANTS[variant] ? variant : "park";
  const f = av2SceneFills(name);
  stage.dataset.built = "1";
  stage.dataset.variant = name;

  const hills = av2SceneLayer(
    "av2-layer-hills", 7, "0 0 1200 300", "none",
    `<path d="M0 300 L0 188 Q150 118 300 174 Q462 234 620 158 Q790 78 950 164 Q1080 234 1200 178 L1200 300 Z" fill="${f.hills}"/>`
  );

  // 75 units of headroom above y=0. A canopy's top is 300 - h - 0.92r, and the
  // two tallest entries in AV2_TREES come out negative (-17.2 and -7.4), so on
  // a plain "0 0 1200 300" box the SVG viewport sliced them flat across the top.
  //
  // MUST stay in step with .av2-layer-trees's height in auth_scene.css, which
  // carries the matching 375/300 factor. The layer is bottom-anchored with
  // preserveAspectRatio="xMidYMax slice", so scaling the box height by exactly
  // the same ratio as the viewBox height leaves scale (boxH/vbH) untouched, and
  // the Y-max edge stays at coordinate 300 (-75 + 375) so the ground line does
  // not move. Change one without the other and the treeline resizes or drifts.
  const trees = av2SceneLayer(
    "av2-layer-trees", 15, "0 -75 1200 375", "xMidYMax slice", av2SceneTreelineMarkup(name)
  );

  const ground = av2SceneLayer(
    "av2-layer-ground", 24, "0 0 1200 300", "none",
    `<path d="M0 300 L0 112 Q300 52 600 104 Q900 154 1200 94 L1200 300 Z" fill="${f.ground}"/>`
  );

  const fore = av2SceneLayer(
    "av2-layer-fore", 46, "0 0 1200 300", "xMidYMax slice",
    `<ellipse cx="90" cy="250" rx="150" ry="86" fill="${f.fore}"/>` +
    `<ellipse cx="330" cy="286" rx="120" ry="66" fill="${f.fore}"/>` +
    `<ellipse cx="1110" cy="248" rx="168" ry="94" fill="${f.fore}"/>` +
    `<ellipse cx="860" cy="290" rx="130" ry="70" fill="${f.fore}"/>` +
    `<rect x="0" y="264" width="1200" height="40" fill="${f.fore}"/>`
  );

  // .av2-sun is restyled into a moon by auth_scene.css under .av2-stage--den;
  // it is the same element in both worlds, so the parallax and pulse rules
  // written for it keep applying without a second code path.
  const clouds = Array.from({ length: f.clouds }, () => `<div class="av2-cloud" aria-hidden="true"></div>`).join("");
  const stars = name === "den" ? `<div class="av2-stars" aria-hidden="true">${av2SceneStarsMarkup()}</div>` : "";
  const lantern = name === "den" ? `<div class="av2-lantern" data-depth="30" aria-hidden="true"></div>` : "";

  stage.insertAdjacentHTML(
    "afterbegin",
    `<div class="av2-sun" aria-hidden="true"></div>` +
    stars + clouds +
    hills + trees + ground +
    lantern +
    `<div class="av2-hero" data-depth="34" aria-hidden="true"></div>` +
    fore +
    `<div class="av2-motes" aria-hidden="true">${av2SceneMotesMarkup(name)}</div>`
  );

  av2SceneBindParallax(stage);
}

// ---------------- Parallax ----------------

// One pointermove listener per stage, coalesced into a single rAF write.
// Depth comes from each layer's data-depth: the nearer the layer, the further
// it travels, which is what sells the diorama as having actual depth.
function av2SceneBindParallax(stage) {
  if (av2SceneReducedMotion()) return;

  let queued = false;
  let nx = 0;
  let ny = 0;

  function apply() {
    queued = false;
    stage.querySelectorAll("[data-depth]").forEach((el) => {
      const depth = parseFloat(el.dataset.depth) || 0;
      el.style.setProperty("--px", (-nx * depth).toFixed(2) + "px");
      el.style.setProperty("--py", (-ny * depth * 0.45).toFixed(2) + "px");
    });
  }

  stage.addEventListener("pointermove", (e) => {
    const rect = stage.getBoundingClientRect();
    if (!rect.width || !rect.height) return;
    nx = (e.clientX - rect.left) / rect.width - 0.5;
    ny = (e.clientY - rect.top) / rect.height - 0.5;
    if (!queued) {
      queued = true;
      requestAnimationFrame(apply);
    }
  });

  stage.addEventListener("pointerleave", () => {
    nx = 0;
    ny = 0;
    if (!queued) {
      queued = true;
      requestAnimationFrame(apply);
    }
  });
}

// ---------------- The hero character ----------------

function av2SceneCloneSpecies(species) {
  const library = document.getElementById("pcm-library");
  if (!library) return null;
  const escaped = String(species || "").replace(/"/g, '\\"');
  const source =
    library.querySelector(`.pcm[data-species="${escaped}"]`) ||
    library.querySelector('.pcm[data-species="Other"]');
  return source ? source.cloneNode(true) : null;
}

// Swaps the stage's character. The outgoing one animates away over the top of
// the incoming one (hence position:absolute on .is-leaving) so the swap reads
// as a transformation rather than a cut to black.
function av2SceneSetSpecies(stage, species) {
  if (!stage) return;
  const hero = stage.querySelector(".av2-hero");
  if (!hero) return;
  if (hero.dataset.species === species) return;
  hero.dataset.species = species || "";

  const group = av2SceneCloneSpecies(species);
  if (!group) return;

  const svg = document.createElementNS("http://www.w3.org/2000/svg", "svg");
  svg.setAttribute("viewBox", "0 0 200 200");
  svg.setAttribute("aria-hidden", "true");
  svg.setAttribute("focusable", "false");
  svg.classList.add("av2-hero-svg", "is-entering");
  svg.appendChild(group);

  // querySelectorAll, not querySelector: two swaps can land inside the 320ms
  // retirement window — the login rotates its headline (and with it the
  // species) every 6s while the pet pills can fire a swap at any moment — and
  // retiring only the first stale <svg> leaves any others parked in the DOM
  // forever, un-animated and never removed. They then win
  // ":not(.is-leaving)", so the character visibly stops tracking the
  // selection and stale ghosts stack up behind it.
  hero.querySelectorAll(".av2-hero-svg").forEach((previous) => {
    previous.classList.remove("is-entering");
    previous.classList.add("is-leaving");
    setTimeout(() => previous.remove(), 320);
  });
  hero.appendChild(svg);
  av2SceneHop(stage);
}

function av2SceneCharacter(stage) {
  return stage ? stage.querySelector(".av2-hero-svg:not(.is-leaving) .pcm") : null;
}

// Retriggers the hop keyframe. Removing the class and forcing a reflow before
// re-adding is what makes a repeated hop actually replay.
function av2SceneHop(stage) {
  const pcm = av2SceneCharacter(stage);
  if (!pcm || av2SceneReducedMotion()) return;
  pcm.classList.remove("is-hop");
  void pcm.offsetWidth;
  pcm.classList.add("is-hop");
  setTimeout(() => pcm.classList.remove("is-hop"), 700);
}

function av2SceneReact(stage, state, ms) {
  const pcm = av2SceneCharacter(stage);
  if (!pcm) return;
  const cls = state === "sad" ? "is-sad" : "is-happy";
  pcm.classList.add(cls);
  if (ms) setTimeout(() => pcm.classList.remove(cls), ms);
}

// Paws over the eyes while a password is being typed — the same gag as the
// classic sign-up mascot (initPasswordMascot in core.js), applied to the
// full-size stage character.
function av2SceneSetShy(stage, shy) {
  const pcm = av2SceneCharacter(stage);
  if (pcm) pcm.classList.toggle("is-shy", !!shy);
}

// ---------------- Pupil tracking ----------------

// A single document-level listener drives every stage character's pupils.
// Offsets are in SVG user units (the rig is drawn on a 200x200 viewBox), so
// the small maxima below are roughly 3px of travel at typical render sizes.
function av2SceneBindPupils() {
  if (av2SceneReducedMotion()) return;

  let queued = false;
  let clientX = 0;
  let clientY = 0;

  function apply() {
    queued = false;
    document.querySelectorAll(".av2-hero-svg:not(.is-leaving)").forEach((svg) => {
      const pcm = svg.querySelector(".pcm");
      if (!pcm || pcm.classList.contains("is-shy")) return;
      const rect = svg.getBoundingClientRect();
      if (!rect.width) return;
      const cx = rect.left + rect.width / 2;
      const cy = rect.top + rect.height * 0.38;
      const max = 4;
      const dx = Math.max(-max, Math.min(max, (clientX - cx) / 42));
      const dy = Math.max(-max, Math.min(max, (clientY - cy) / 42));
      svg.querySelectorAll(".pcm-pupil").forEach((p) => {
        p.style.transform = `translate(${dx.toFixed(2)}px, ${dy.toFixed(2)}px)`;
      });
      // The highlight follows at half speed. Pinning it would make it look
      // painted onto the pupil; leaving it still — which is what happened
      // before it had a class to select — lets the pupil slide out from under
      // it at full deflection and strands the glint on the white of the eye.
      // Half is the amount that still reads as a fixed reflection on a curved
      // wet surface while never coming off the pupil.
      const gx = dx * AV2_GLINT_FOLLOW;
      const gy = dy * AV2_GLINT_FOLLOW;
      svg.querySelectorAll(".pcm-glint").forEach((g) => {
        g.style.transform = `translate(${gx.toFixed(2)}px, ${gy.toFixed(2)}px)`;
      });
    });
  }

  document.addEventListener("pointermove", (e) => {
    clientX = e.clientX;
    clientY = e.clientY;
    if (!queued) {
      queued = true;
      requestAnimationFrame(apply);
    }
  });
}

// ---------------- Signpost headline ----------------

function av2SceneSetSignpost(stage, eyebrow, text, total, index) {
  if (!stage) return;
  let post = stage.querySelector(".av2-signpost");
  if (!post) {
    post = document.createElement("div");
    post.className = "av2-signpost";
    post.dataset.depth = "12";
    post.setAttribute("aria-hidden", "true");
    stage.appendChild(post);
  }
  const dots = Array.from({ length: total || 0 }, (_, i) =>
    `<span class="${i === index ? "av2-active" : ""}"></span>`
  ).join("");
  post.innerHTML =
    `<div class="av2-signpost-eyebrow">${eyebrow || ""}</div>` +
    `<div class="av2-signpost-text">${text || ""}</div>` +
    (dots ? `<div class="av2-signpost-dots">${dots}</div>` : "");
}

// ---------------- Constellation (sign-up progress, den only) ----------------

// Four stars high in the den's sky, one per wizard step, joined by three
// segments. Completing a step lights its star and draws the segment leading to
// it, so by the time the form is submitted the shape is finished.
//
// Positions are a deliberate shallow arc rather than a straight line — a row of
// evenly spaced dots reads as a progress bar someone put in the sky, which is
// the opposite of the intent.
// Percentages of the constellation box, x then y.
const AV2_CONSTELLATION = [
  [16, 78], [40, 34], [66, 52], [88, 12],
];

function av2SceneSetConstellation(stage, step, total) {
  if (!stage || stage.dataset.variant !== "den") return;

  let sky = stage.querySelector(".av2-constellation");
  if (!sky) {
    const pts = AV2_CONSTELLATION.slice(0, total || AV2_CONSTELLATION.length);

    // The joining segments live in an SVG stretched to the box
    // (preserveAspectRatio="none"), which is fine for straight lines: its
    // 0-100 user space maps exactly onto the percentage space the stars are
    // positioned in, so the two always agree about where a point is.
    // pathLength="1" then makes drawing a segment in a plain 1 -> 0 dash
    // offset whatever its real length or the box's aspect.
    const lines = pts.slice(1).map((p, i) => {
      const [x1, y1] = pts[i];
      const [x2, y2] = p;
      return `<line class="av2-cst-line" data-index="${i + 1}" pathLength="1" x1="${x1}" y1="${y1}" x2="${x2}" y2="${y2}"/>`;
    }).join("");

    // The stars themselves are HTML, NOT circles in that SVG: the same
    // non-uniform stretch that is harmless for a line would squash a circle
    // into a visible oval.
    const stars = pts.map(([x, y], i) =>
      `<span class="av2-cst-star" data-index="${i}" style="left:${x}%;top:${y}%;"></span>`
    ).join("");

    sky = document.createElement("div");
    sky.className = "av2-constellation";
    sky.dataset.depth = "5";
    sky.setAttribute("aria-hidden", "true");
    sky.innerHTML =
      `<svg viewBox="0 0 100 100" preserveAspectRatio="none" focusable="false">${lines}</svg>${stars}`;
    stage.appendChild(sky);
  }

  sky.querySelectorAll(".av2-cst-star").forEach((s) => {
    s.classList.toggle("is-lit", Number(s.dataset.index) < step);
  });
  sky.querySelectorAll(".av2-cst-line").forEach((l) => {
    l.classList.toggle("is-lit", Number(l.dataset.index) < step);
  });
}

// ---------------- Headline letter drop-in ----------------

function av2SceneSplitLetters(el) {
  if (!el || el.dataset.split === "1") return;
  el.dataset.split = "1";
  const text = el.textContent;
  el.textContent = "";
  Array.from(text).forEach((ch, i) => {
    const span = document.createElement("span");
    span.className = "av2-letter";
    span.style.setProperty("--i", String(i));
    span.textContent = ch;
    el.appendChild(span);
  });
}

// ---------------- Transition out of the auth pages ----------------

// A paw-stamped iris wipe from the button that was pressed. `done` fires while
// the overlay covers the viewport, so the underlying view switch is hidden.
function av2ScenePlayWipe(originEl, done) {
  if (av2SceneReducedMotion()) {
    if (done) done();
    return;
  }

  const wipe = document.createElement("div");
  wipe.className = "av2-wipe";
  if (originEl) {
    const rect = originEl.getBoundingClientRect();
    wipe.style.setProperty("--wx", ((rect.left + rect.width / 2) / window.innerWidth) * 100 + "%");
    wipe.style.setProperty("--wy", ((rect.top + rect.height / 2) / window.innerHeight) * 100 + "%");
  }
  wipe.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>';
  document.body.appendChild(wipe);

  setTimeout(() => {
    if (done) done();
  }, 470);

  setTimeout(() => {
    wipe.style.transition = "opacity 0.4s ease";
    wipe.style.opacity = "0";
    setTimeout(() => wipe.remove(), 420);
  }, 700);
}

av2SceneBindPupils();
