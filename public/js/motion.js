// motion.js — anime.js-driven motion for the logged-in app.
//
// Loaded after core.js (see main.php). Everything here is additive and
// defensive: core.js and the render modules call into it through
// `typeof pcX === "function"` guards, so if this file or its vendor bundle
// fails to load the app keeps its previous, static behaviour.
//
// Three rules hold throughout:
//
//   1. NEVER leave content invisible. Every `opacity: 0` starting state is
//      written from JS, immediately before the animation that undoes it, and
//      only after PC_MOTION.enabled has been checked. CSS never hides anything
//      (see the header comment in motion.css). pcRevealChildren additionally
//      carries a hard timeout that force-reveals anything still hidden.
//   2. Respect prefers-reduced-motion. Every entry point returns early after
//      applying the FINAL state, matching the convention at main.css:4424.
//   3. Never remove `no-accent-hover` from a nav button. The global
//      `button:not(.no-accent-hover):hover` rule at main.css:326 uses
//      !important and will overwrite anything these animations do.

/* global anime, currentUserObj */

var PC_MOTION = { enabled: false };

(function () {
  "use strict";

  var reduceQuery = window.matchMedia("(prefers-reduced-motion: reduce)");
  var hoverQuery = window.matchMedia("(hover: hover)");

  function refreshEnabled() {
    PC_MOTION.enabled = typeof anime !== "undefined" && !reduceQuery.matches;
  }
  refreshEnabled();

  if (typeof reduceQuery.addEventListener === "function") {
    reduceQuery.addEventListener("change", refreshEnabled);
  }

  function el(ref) {
    if (!ref) return null;
    return typeof ref === "string" ? document.getElementById(ref) || document.querySelector(ref) : ref;
  }

  // Clears the inline opacity/transform an animation left behind, so the next
  // render or hover starts from the stylesheet's own values.
  function clearInline(nodes) {
    Array.prototype.forEach.call(nodes, function (n) {
      n.style.opacity = "";
      n.style.transform = "";
    });
  }

  // Tweens a plain number and hands it back each frame. Used wherever the
  // target is not a DOM property anime can write directly (CSS custom
  // properties, text content), which keeps this file off the parts of the v4
  // API most likely to shift between minor versions.
  function tweenValue(params) {
    var box = { v: params.from };
    return [box, {
      v: params.to,
      duration: params.duration,
      ease: params.ease || "outQuad",
      onUpdate: function () { params.onUpdate(box.v); },
      onComplete: function () { params.onUpdate(params.to); }
    }];
  }

  // ==========================================================
  // Shared helpers
  // ==========================================================

  var activeReveals = new WeakMap();
  var REVEAL_SAFETY_MS = 4000;

  function cancelReveal(container) {
    var rec = activeReveals.get(container);
    if (!rec) return;
    if (rec.anim) rec.anim.cancel();
    if (rec.timer) clearTimeout(rec.timer);
    rec.nodes.forEach(function (n) { scrollReveal.unobserve(n); });
    clearInline(rec.nodes);
    activeReveals.delete(container);
  }

  // Staggers a freshly-rendered list in. Nodes past `eager` are held back and
  // revealed by the shared IntersectionObserver as they scroll into view, so a
  // 60-post feed does not run a 60-step stagger on nodes nobody can see yet.
  function pcRevealChildren(container, selector, options) {
    var host = el(container);
    if (!host) return;
    var opts = options || {};
    var nodes = selector
      ? Array.prototype.slice.call(host.querySelectorAll(selector))
      : Array.prototype.slice.call(host.children);

    // The search typeahead re-renders on every keystroke. Without this, an
    // in-flight stagger from the previous render can be cancelled mid-way and
    // strand a row at opacity 0 forever.
    cancelReveal(host);
    if (!PC_MOTION.enabled || !nodes.length) return;

    var eagerCount = typeof opts.eager === "number" ? opts.eager : 8;
    var eager = nodes.slice(0, eagerCount);
    var deferred = nodes.slice(eagerCount);

    anime.utils.set(nodes, { opacity: 0, translateY: opts.distance || 12 });

    var anim = anime.animate(eager, {
      opacity: 1,
      translateY: 0,
      duration: opts.duration || 320,
      delay: anime.stagger(opts.stagger || 40),
      ease: "outQuad",
      onComplete: function () { clearInline(eager); }
    });

    deferred.forEach(function (n) { scrollReveal.observe(n); });

    // Last line of defence: if an IntersectionObserver callback never arrives
    // (container detached, tab never opened, observer throttled), reveal
    // everything rather than leave the user staring at blank cards.
    var timer = setTimeout(function () { cancelReveal(host); }, REVEAL_SAFETY_MS);
    activeReveals.set(host, { anim: anim, nodes: nodes, timer: timer });
  }

  var scrollReveal = new IntersectionObserver(function (entries) {
    entries.forEach(function (entry) {
      if (!entry.isIntersecting) return;
      var node = entry.target;
      scrollReveal.unobserve(node);
      if (!PC_MOTION.enabled) { clearInline([node]); return; }
      anime.animate(node, {
        opacity: 1, translateY: 0, duration: 340, ease: "outQuad",
        onComplete: function () { clearInline([node]); }
      });
    });
  }, { rootMargin: "0px 0px -40px 0px", threshold: 0.01 });

  function pcCountUp(target, value) {
    var node = el(target);
    if (!node) return;
    var end = Number(value) || 0;
    if (!PC_MOTION.enabled) { node.textContent = String(end); return; }
    var args = tweenValue({
      from: 0, to: end, duration: 700, ease: "outExpo",
      onUpdate: function (v) { node.textContent = String(Math.round(v)); }
    });
    anime.animate(args[0], args[1]);
  }

  // Same two-step lookup as auth_scene.js:277-283 — exact species, else the
  // "Other" catch-all critter, so an unrecognised pet_type still gets an animal.
  var speciesMissWarned = {};

  function pcCloneSpecies(petType) {
    var library = document.getElementById("pcm-library");
    if (!library) return null;
    var wanted = String(petType == null ? "" : petType).trim().toLowerCase();
    var source = null;
    var all = library.querySelectorAll(".pcm[data-species]");
    for (var i = 0; i < all.length; i++) {
      if (all[i].getAttribute("data-species").trim().toLowerCase() === wanted) {
        source = all[i];
        break;
      }
    }
    if (!source) {
      // Falling back silently is what made a correct "Other" indistinguishable
      // from a lookup bug. Say which value missed, once per value.
      if (wanted !== "other" && !speciesMissWarned[wanted]) {
        speciesMissWarned[wanted] = true;
        console.info('motion.js: no mascot for pet_type "' + petType + '" — using Other.');
      }
      source = library.querySelector('.pcm[data-species="Other"]');
    }
    return source ? source.cloneNode(true) : null;
  }

  // ==========================================================
  // 1. Kennel tab: expands sideways on hover
  // ==========================================================
  //
  // The whole scene lives INSIDE the button. The previous version floated a
  // stage above the nav, which meant fighting the nav's overflow (it is
  // overflow-x-auto, and `auto` on one axis forces the other away from
  // `visible`) via an external anchor element. Widening the button instead
  // means there is nothing to clip: the five sibling tabs are flex-1, so they
  // absorb the width the Kennel button takes and squeeze on their own.

  var KENNEL_W_REST = 60;
  var KENNEL_W_OPEN = 210;
  // Scene units. The rig is drawn 200 wide and the slot scales it by 0.15, so
  // the animal's centre sits at 15 + translateX. The doorway is centred on 42.
  var ACTOR_START = 215;   // off-scene right (centre 230, past the 210 edge)
  var ACTOR_END = 27;      // centre 42 — on the doorway
  var ACTOR_PEEK = 47;     // centre 62 — just outside the door, to its right

  var kennelTl = null;
  var kennelSpecies = null;

  function kennelParts() {
    var nav = document.getElementById("social-tab-nav");
    var btn = nav && nav.querySelector('.social-tab-strip-item[data-social-tab="hub"]');
    if (!btn) return null;
    return {
      nav: nav,
      btn: btn,
      icon: btn.querySelector(".kennel-icon"),
      scene: btn.querySelector(".kennel-scene"),
      door: btn.querySelector(".kennel-door-panel"),
      actor: btn.querySelector(".kennel-actor"),
      slot: btn.querySelector(".kennel-actor-slot")
    };
  }

  // state.js declares `let currentUserObj`, and a top-level `let` in a classic
  // script binds in the global DECLARATIVE environment — it never becomes a
  // property on `window`. Reaching through window.currentUserObj here meant the
  // guard was permanently undefined and every viewer got the "Other" critter.
  // The rest of the app reads it bare; typeof keeps that safe if state.js ever
  // loads late.
  function currentUser() {
    return typeof currentUserObj !== "undefined" ? currentUserObj : null;
  }

  function ensureKennelActor(slot) {
    var user = currentUser();
    var petType = (user && user.pet_type) || "Other";
    if (kennelSpecies === petType && slot.firstChild) return;
    while (slot.firstChild) slot.removeChild(slot.firstChild);
    var clone = pcCloneSpecies(petType);
    if (clone) slot.appendChild(clone);
    kennelSpecies = petType;
  }

  function cancelKennel() {
    if (kennelTl) { kennelTl.cancel(); kennelTl = null; }
  }

  // Expanding the button shifts every tab to its right, so the active
  // underline has to be re-measured as the width animates. The ResizeObserver
  // on the nav cannot do this — the nav itself never changes size, only its
  // children do.
  // `immediate` matters: the default path STARTS a 420ms tween, and calling
  // that from an onUpdate would spawn one per frame, sixty competing
  // animations a second all lagging behind the button. During the expand the
  // bar has to be written straight to its new position instead.
  function trackUnderline() {
    if (currentTab) pcMoveTabUnderline(currentTab, true);
  }

  function playKennelEnter() {
    var p = kennelParts();
    if (!p || !PC_MOTION.enabled) return;
    cancelKennel();
    ensureKennelActor(p.slot);
    p.btn.classList.add("is-open");

    anime.utils.set(p.actor, { translateX: ACTOR_START, translateY: 0, opacity: 1, scale: 1 });
    anime.utils.set(p.door, { scaleX: 1 });
    anime.utils.set(p.scene, { opacity: 0 });

    kennelTl = anime.createTimeline()
      .add(p.btn, {
        width: KENNEL_W_OPEN,
        duration: 420,
        ease: "outBack",
        onUpdate: trackUnderline
      }, 0)
      .add(p.icon, { opacity: 0, scale: 0.7, duration: 160, ease: "inQuad" }, 0)
      .add(p.scene, { opacity: 1, duration: 220, ease: "outQuad" }, 140)
      .add(p.door, { scaleX: 0.06, duration: 260, ease: "outBack" }, 240)
      // Constant-speed walk with a four-step bob. Tail wag, breathing, blinking
      // and ear flop all come free from auth_mascots.css, which targets the
      // .pcm-* classes the clone already carries.
      .add(p.actor, {
        translateX: ACTOR_END,
        translateY: [0, -3, 0, -3, 0, -3, 0, -3, 0],
        duration: 860,
        ease: "linear"
      }, 300)
      // Fades as it crosses the threshold, so it reads as going inside rather
      // than evaporating in an open doorway.
      .add(p.actor, { opacity: 0, scale: 0.82, duration: 200, ease: "inQuad" }, 980)
      .add(p.door, { scaleX: 1, duration: 320, ease: "outBack" }, 1150);
  }

  function playKennelExit() {
    var p = kennelParts();
    if (!p) return;
    if (!PC_MOTION.enabled) { p.btn.classList.remove("is-open"); return; }
    cancelKennel();

    kennelTl = anime.createTimeline({
      onComplete: function () {
        p.btn.classList.remove("is-open");
        p.btn.style.width = "";
        clearInline([p.icon, p.scene, p.actor]);
        trackUnderline();
      }
    })
      // Head back out of the door before the button closes on it.
      .set(p.actor, { translateX: ACTOR_PEEK, translateY: 0, scale: 0.9, opacity: 0 }, 0)
      .add(p.door, { scaleX: 0.4, duration: 180, ease: "outQuad" }, 0)
      .add(p.actor, { opacity: 1, translateX: ACTOR_PEEK - 14, duration: 260, ease: "outBack" }, 100)
      .add(p.scene, { opacity: 0, duration: 200, ease: "inQuad" }, 420)
      .add(p.btn, {
        width: KENNEL_W_REST,
        duration: 340,
        ease: "outCubic",
        onUpdate: trackUnderline
      }, 440)
      .add(p.icon, { opacity: 1, scale: 1, duration: 200, ease: "outQuad" }, 560);
  }

  function initKennelHover() {
    var p = kennelParts();
    if (!p || !hoverQuery.matches) return;
    p.btn.addEventListener("mouseenter", playKennelEnter);
    p.btn.addEventListener("mouseleave", playKennelExit);
    p.btn.addEventListener("focus", playKennelEnter);
    p.btn.addEventListener("blur", playKennelExit);
  }

  // ==========================================================
  // 2. Sliding tab indicator
  // ==========================================================

  var currentTab = null;
  var underlinePlaced = false;

  function pcMoveTabUnderline(tab, immediate) {
    var nav = document.getElementById("social-tab-nav");
    var bar = document.getElementById("social-tab-underline");
    if (!nav || !bar) return;
    if (tab) currentTab = tab;

    // Settings and post-detail have no button in the strip. Fade the bar out
    // rather than animating it to a meaningless position.
    var btn = nav.querySelector('.social-tab-strip-item[data-social-tab="' + currentTab + '"]');
    if (!btn) {
      if (PC_MOTION.enabled) anime.animate(bar, { opacity: 0, duration: 140, ease: "outQuad" });
      else bar.style.opacity = "0";
      return;
    }

    var x = btn.offsetLeft;
    var w = btn.offsetWidth;

    // switchSocialTab can run while #view-social-feed is still display:none —
    // the login flow sets the tab before the view is shown. Measuring then
    // gives 0, which would pin the bar at zero width until the next tab
    // change. Bail without claiming placement; the ResizeObserver below fires
    // as soon as the nav has real dimensions and calls back in.
    if (w === 0) return;

    var isFirstPlacement = !underlinePlaced;
    if (!immediate) underlinePlaced = true;

    if (!PC_MOTION.enabled || isFirstPlacement || immediate) {
      bar.style.opacity = "1";
      bar.style.width = w + "px";
      bar.style.transform = "translateX(" + x + "px)";
      return;
    }

    anime.animate(bar, { opacity: 1, width: w, translateX: x, duration: 420, ease: "outBack" });
    var icon = btn.querySelector(":scope > svg, :scope > i, :scope > div");
    if (icon) anime.animate(icon, { scale: [1, 1.25, 1], duration: 420, ease: "outQuad" });
  }

  // switchSocialTab calls updateSocialLayoutForTab first, which shows and hides
  // the sidebars — measuring before that reflow lands gives a stale offsetLeft.
  function pcOnSocialTabChanged(tab) {
    currentTab = tab;
    requestAnimationFrame(function () { pcMoveTabUnderline(tab); });
  }

  // Snaps rather than slides: a resize or a first reveal is not a tab change,
  // so there is nothing to animate between.
  function repositionUnderline() {
    if (!currentTab) return;
    underlinePlaced = false;
    pcMoveTabUnderline(currentTab);
  }

  // Fires both on window resize and on the nav going from hidden (zero size)
  // to visible, which is what recovers the placement skipped above.
  function watchNavSize() {
    var nav = document.getElementById("social-tab-nav");
    if (!nav || typeof ResizeObserver === "undefined") return;
    new ResizeObserver(function () { repositionUnderline(); }).observe(nav);
  }

  // ==========================================================
  // 3. Mobile drawer
  // ==========================================================

  var drawerTl = null;

  function cancelDrawer() {
    if (drawerTl) { drawerTl.cancel(); drawerTl = null; }
  }

  function pcDrawerOpen(drawer, backdrop) {
    backdrop.classList.remove("hidden");
    if (!PC_MOTION.enabled) {
      drawer.style.transform = "translateX(0)";
      backdrop.style.opacity = "";
      return;
    }
    cancelDrawer();
    var items = drawer.querySelectorAll(".drawer-nav-btn");
    anime.utils.set(backdrop, { opacity: 0 });
    anime.utils.set(items, { opacity: 0, translateX: -16 });

    drawerTl = anime.createTimeline()
      .add(drawer, { translateX: ["-100%", "0%"], duration: 320, ease: "outCubic" }, 0)
      .add(backdrop, { opacity: 1, duration: 220, ease: "outQuad" }, 0)
      .add(items, {
        opacity: 1, translateX: 0, duration: 260,
        delay: anime.stagger(32), ease: "outQuad",
        onComplete: function () { clearInline(items); }
      }, 140);
  }

  function pcDrawerClose(drawer, backdrop) {
    if (!PC_MOTION.enabled) {
      drawer.style.transform = "translateX(-100%)";
      backdrop.classList.add("hidden");
      return;
    }
    cancelDrawer();
    drawerTl = anime.createTimeline({
      onComplete: function () {
        // A re-open may have landed inside the 180ms. closeMobileNav clears
        // .nav-open synchronously, so this is the authoritative check.
        if (drawer.classList.contains("nav-open")) return;
        backdrop.classList.add("hidden");
        backdrop.style.opacity = "";
      }
    })
      .add(drawer, { translateX: "-100%", duration: 180, ease: "inQuad" }, 0)
      .add(backdrop, { opacity: 0, duration: 180, ease: "inQuad" }, 0);
  }

  // ==========================================================
  // 4. Modal entrance / exit
  // ==========================================================

  var modalOpenState = new WeakMap();
  var modalAnims = new WeakMap();

  function modalPanel(node) {
    return node.querySelector(":scope > div");
  }

  function cancelModalAnim(node) {
    var running = modalAnims.get(node);
    if (running) { running.cancel(); modalAnims.delete(node); }
  }

  function resetModalStyles(node) {
    node.style.opacity = "";
    node.style.pointerEvents = "";
    var panel = modalPanel(node);
    if (panel) clearInline([panel]);
  }

  function playModalEnter(node) {
    cancelModalAnim(node);
    resetModalStyles(node);
    if (!PC_MOTION.enabled) return;
    var panel = modalPanel(node);
    anime.utils.set(node, { opacity: 0 });
    if (panel) anime.utils.set(panel, { opacity: 0, scale: 0.94, translateY: 12 });

    var tl = anime.createTimeline({
      onComplete: function () { modalAnims.delete(node); resetModalStyles(node); }
    });
    tl.add(node, { opacity: 1, duration: 180, ease: "outQuad" }, 0);
    if (panel) tl.add(panel, { opacity: 1, scale: 1, translateY: 0, duration: 260, ease: "outBack" }, 40);
    modalAnims.set(node, tl);
  }

  // Modals are shown by removing `hidden` AND adding `flex` (their base class
  // is `hidden flex-col`). Both halves have to land together at the END of the
  // exit — dropping `flex` up front would collapse the panel to block layout
  // mid-fade and the modal would visibly jump as it disappears.
  function hideModal(node) {
    node.classList.add("hidden");
    node.classList.remove("flex");
    modalOpenState.set(node, false);
    resetModalStyles(node);
  }

  // Called from every close*Modal(). The `hidden` class lands after the fade;
  // pointer-events is dropped immediately so the dying backdrop cannot swallow
  // a click in the meantime.
  function pcModalExit(target) {
    var node = el(target);
    if (!node) return;
    cancelModalAnim(node);
    if (!PC_MOTION.enabled || node.classList.contains("hidden")) { hideModal(node); return; }

    node.style.pointerEvents = "none";
    var panel = modalPanel(node);
    var tl = anime.createTimeline({
      onComplete: function () { modalAnims.delete(node); hideModal(node); }
    });
    tl.add(node, { opacity: [1, 0], duration: 150, ease: "inQuad" }, 0);
    if (panel) tl.add(panel, { opacity: 0, scale: 0.96, translateY: 8, duration: 150, ease: "inQuad" }, 0);
    modalAnims.set(node, tl);
  }

  // Entrance needs no edits to the ~40 open*() functions: every modal shows
  // itself by dropping the `hidden` class, which is observable.
  function initModalMotion() {
    var modals = document.querySelectorAll('body > [id$="-modal"], body > #gallery-lightbox');
    if (!modals.length) return;
    var observer = new MutationObserver(function (records) {
      records.forEach(function (record) {
        var node = record.target;
        var isOpen = !node.classList.contains("hidden");
        if (modalOpenState.get(node) === isOpen) return;
        modalOpenState.set(node, isOpen);
        if (isOpen) playModalEnter(node);
      });
    });
    Array.prototype.forEach.call(modals, function (node) {
      modalOpenState.set(node, !node.classList.contains("hidden"));
      observer.observe(node, { attributes: true, attributeFilter: ["class"] });
    });
  }

  // ==========================================================
  // 5. Brand logo hover
  // ==========================================================

  var lastPuffAt = 0;

  function firePawPuffs(host) {
    if (!host) return;
    for (var i = 0; i < 3; i++) {
      (function (index) {
        var puff = document.createElement("span");
        puff.className = "pc-paw-puff";
        puff.innerHTML = '<svg viewBox="0 0 24 24" aria-hidden="true"><use href="#icon-paw"></use></svg>';
        host.appendChild(puff);
        anime.animate(puff, {
          translateX: [0, 14 + index * 9],
          translateY: [0, -18 - index * 7],
          scale: [0.5, 1, 0.7],
          rotate: [0, 18 + index * 12],
          opacity: [0, 0.9, 0],
          duration: 720,
          delay: index * 70,
          ease: "outQuad",
          onComplete: function () { puff.remove(); }
        });
      })(i);
    }
  }

  function initBrandLogoHover() {
    var wrap = document.querySelector(".pc-brand-logo");
    if (!wrap || !hoverQuery.matches) return;
    var trigger = wrap.closest("button") || wrap;
    var img = wrap.querySelector("img");
    var puffHost = wrap.querySelector(".pc-paw-puffs");

    trigger.addEventListener("mouseenter", function () {
      if (!PC_MOTION.enabled) return;
      var now = Date.now();
      if (now - lastPuffAt < 700) return;
      lastPuffAt = now;
      if (img) {
        anime.animate(img, {
          rotate: [0, -8, 8, -4, 0],
          scale: [1, 1.08, 1.08, 1],
          duration: 620,
          ease: "outQuad",
          onComplete: function () { clearInline([img]); }
        });
      }
      firePawPuffs(puffHost);
    });
  }

  // ==========================================================
  // 6. Pack tree draw-in
  // ==========================================================

  function pcDrawPackTree(target) {
    var host = el(target);
    if (!host) return;
    var cards = host.querySelectorAll(".node-card");
    if (!PC_MOTION.enabled) {
      host.classList.remove("pc-drawing", "pc-revealing");
      host.style.removeProperty("--pc-line");
      return;
    }

    // --pc-line is animated, never `transform` on this element: the container
    // is the pan/zoom target (applyPackTransform, pack_tree.js:12) and writing
    // a transform here would clobber the user's pan.
    host.classList.add("pc-drawing", "pc-revealing");
    host.style.setProperty("--pc-line", "0");
    anime.utils.set(cards, { opacity: 0, scale: 0.8 });

    var line = tweenValue({
      from: 0, to: 1, duration: 520, ease: "outQuad",
      onUpdate: function (v) { host.style.setProperty("--pc-line", String(v)); }
    });

    anime.createTimeline({
      onComplete: function () {
        host.classList.remove("pc-drawing", "pc-revealing");
        host.style.removeProperty("--pc-line");
        clearInline(cards);
      }
    })
      .add(line[0], line[1], 0)
      .add(cards, { opacity: 1, scale: 1, duration: 380, delay: anime.stagger(60), ease: "outBack" }, 160);
  }

  // ==========================================================
  // Boot
  // ==========================================================

  function boot() {
    if (typeof anime === "undefined") {
      console.warn("motion.js: anime.js not present — animations disabled.");
      return;
    }
    var drawer = document.getElementById("mobile-nav-drawer");
    if (drawer) drawer.classList.add("pc-motion-driven");
    initKennelHover();
    initBrandLogoHover();
    initModalMotion();
    watchNavSize();
    window.addEventListener("resize", repositionUnderline);
  }

  if (document.readyState === "loading") {
    document.addEventListener("DOMContentLoaded", boot);
  } else {
    boot();
  }

  window.pcRevealChildren = pcRevealChildren;
  window.pcCountUp = pcCountUp;
  window.pcCloneSpecies = pcCloneSpecies;
  window.pcModalExit = pcModalExit;
  window.pcMoveTabUnderline = pcMoveTabUnderline;
  window.pcOnSocialTabChanged = pcOnSocialTabChanged;
  window.pcDrawerOpen = pcDrawerOpen;
  window.pcDrawerClose = pcDrawerClose;
  window.pcDrawPackTree = pcDrawPackTree;
})();
