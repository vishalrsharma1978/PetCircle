<?php
// components/mascot_defs.php — shared paint servers and filters for the
// character rigs in components/auth_mascots.php.
//
// Included ONCE from main.php, immediately before the mascot library, and
// never cloned. auth_scene.js and motion.js both cloneNode(true) species out
// of the library into live SVGs — sometimes two at once (the auth hero and the
// kennel hover stage) — so anything carrying an `id` had to live outside the
// cloned subtree or every clone would duplicate those ids.
//
// WHY EVERY GRADIENT HERE IS COLOURLESS
// ------------------------------------
// The obvious design is a per-species gradient whose stops read the species'
// own --pcm-fur / --pcm-dark. That does not work: a var() inside a referenced
// paint server resolves against the GRADIENT element's position in the tree,
// not against the shape referencing it. A shared gradient would therefore
// always resolve to whatever --pcm-fur is in scope here, and every species
// would come out the same colour.
//
// So these are pure light and shade — white-to-transparent and
// black-to-transparent — layered OVER a base shape that is still filled with
// the species' own var(--pcm-*). Colour keeps coming from underneath, which is
// how vector shading is normally built anyway (tint the form, don't recolour
// the light), and it means one definition serves all eight species.
//
// All gradients use the default objectBoundingBox units, so a single
// definition auto-fits whatever shape references it — an ear, a haunch and a
// whole body all get a correctly-proportioned falloff from the same gradient.
//
// SAFE FOR THE PIVOTS
// -------------------
// auth_mascots.css puts `transform-box: fill-box` on the animated groups and
// expresses every transform-origin as a percentage of that box. `fill-box`
// resolves to the OBJECT BOUNDING BOX, which is geometry only — it excludes
// stroke width and filter regions. So stroked fur and the displacement filter
// below cannot move a pivot. Added *filled* shapes can, which is why the
// shading in auth_mascots.php is always drawn inset.
?>
<svg class="sr-only" aria-hidden="true" style="display:none;" focusable="false">
  <defs>

    <!-- Form light: broad upper-left key light. The workhorse — put this on an
         inset copy of a rounded mass to make it read as a sphere. -->
    <radialGradient id="pcm-hi" cx="0.36" cy="0.28" r="0.78">
      <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0.55" />
      <stop offset="55%"  stop-color="#FFFFFF" stop-opacity="0.16" />
      <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
    </radialGradient>

    <!-- Specular: tight and bright, for wet noses, eyes and the top of a skull. -->
    <radialGradient id="pcm-spec" cx="0.38" cy="0.26" r="0.42">
      <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0.9" />
      <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
    </radialGradient>

    <!-- Core shade: the turning edge away from the key light, lower-right. -->
    <radialGradient id="pcm-sh" cx="0.68" cy="0.76" r="0.72">
      <stop offset="0%"   stop-color="#2B1F14" stop-opacity="0.34" />
      <stop offset="60%"  stop-color="#2B1F14" stop-opacity="0.12" />
      <stop offset="100%" stop-color="#2B1F14" stop-opacity="0" />
    </radialGradient>

    <!-- Ambient occlusion: tight and dark, for the crease where two parts meet
         (head onto body, ear root, haunch into flank). This contact darkening
         is what actually separates the parts; without it a shaded figure still
         reads flat. -->
    <radialGradient id="pcm-ao" cx="0.5" cy="0.5" r="0.5">
      <stop offset="0%"   stop-color="#241A12" stop-opacity="0.5" />
      <stop offset="55%"  stop-color="#241A12" stop-opacity="0.2" />
      <stop offset="100%" stop-color="#241A12" stop-opacity="0" />
    </radialGradient>

    <!-- Vertical pair: quick top-light / bottom-grounding on a whole mass. -->
    <linearGradient id="pcm-hi-v" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0.42" />
      <stop offset="62%"  stop-color="#FFFFFF" stop-opacity="0.05" />
      <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0" />
    </linearGradient>

    <linearGradient id="pcm-sh-v" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#2B1F14" stop-opacity="0" />
      <stop offset="55%"  stop-color="#2B1F14" stop-opacity="0.06" />
      <stop offset="100%" stop-color="#2B1F14" stop-opacity="0.3" />
    </linearGradient>

    <!-- Rim light along the right edge: separates the silhouette from the
         background and is most of what sells "rendered" rather than "flat". -->
    <linearGradient id="pcm-rim" x1="0" y1="0" x2="1" y2="0.25">
      <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0" />
      <stop offset="78%"  stop-color="#FFFFFF" stop-opacity="0" />
      <stop offset="100%" stop-color="#FFFFFF" stop-opacity="0.42" />
    </linearGradient>

    <!-- Iris: darkens toward the rim so the eye reads as a wet sphere rather
         than a flat disc. Neutral, so it works over any --pcm-ink. -->
    <radialGradient id="pcm-iris" cx="0.5" cy="0.42" r="0.6">
      <stop offset="0%"   stop-color="#FFFFFF" stop-opacity="0.30" />
      <stop offset="48%"  stop-color="#FFFFFF" stop-opacity="0.06" />
      <stop offset="82%"  stop-color="#000000" stop-opacity="0.18" />
      <stop offset="100%" stop-color="#000000" stop-opacity="0.42" />
    </radialGradient>

    <!-- Upper-lid shadow cast down onto the eyeball. -->
    <linearGradient id="pcm-lid" x1="0" y1="0" x2="0" y2="1">
      <stop offset="0%"   stop-color="#1A1208" stop-opacity="0.38" />
      <stop offset="45%"  stop-color="#1A1208" stop-opacity="0.08" />
      <stop offset="100%" stop-color="#1A1208" stop-opacity="0" />
    </linearGradient>

    <!-- Fur edge: roughens a silhouette so it stops reading as a vector
         primitive. Kept deliberately cheap (one octave, small scale) because
         it also runs on the 34px kennel sprite while it is moving. Filters do
         not affect the object bounding box, so this cannot disturb a pivot. -->
    <filter id="pcm-fur-edge" x="-12%" y="-12%" width="124%" height="124%">
      <feTurbulence type="fractalNoise" baseFrequency="0.09" numOctaves="1" seed="7" result="n" />
      <feDisplacementMap in="SourceGraphic" in2="n" scale="2.6"
                         xChannelSelector="R" yChannelSelector="G" />
    </filter>

    <!-- Coarser variant for longer coats (tail plume, ruff). -->
    <filter id="pcm-fur-soft" x="-16%" y="-16%" width="132%" height="132%">
      <feTurbulence type="fractalNoise" baseFrequency="0.055" numOctaves="2" seed="3" result="n" />
      <feDisplacementMap in="SourceGraphic" in2="n" scale="3.4"
                         xChannelSelector="R" yChannelSelector="G" />
    </filter>

    <!-- Plain blur, for contact shadows drawn as their own shape. -->
    <filter id="pcm-blur" x="-30%" y="-30%" width="160%" height="160%">
      <feGaussianBlur stdDeviation="3.2" />
    </filter>

  </defs>
</svg>
