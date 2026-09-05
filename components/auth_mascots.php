<?php
// components/auth_mascots.php — the character library for the playful auth
// pages (views/public_login_v2.php, views/signup_v2.php).
//
// Included ONCE from main.php. auth_scene.js clones the requested species out
// of here into a view's stage with cloneNode(true) rather than referencing it
// with <use href="#...">: a <use> instance lives in a shadow tree that document
// CSS selectors cannot reach, so the .pcm-tail / .pcm-eye / .pcm-ear keyframes
// in auth_scene.css would never apply to it. Cloning keeps the artwork authored
// as readable markup here (one copy, shared by both views) while still ending
// up as ordinary, styleable, animatable DOM nodes.
//
// Every species is drawn on the SAME rig — identical group class names in the
// same order — so one set of CSS animations (blink, breathe, wag, ear-flop,
// hop, droop) and one JS pupil-tracker drive all eight without special cases.
// The order below is also the PAINT order, back to front:
//
//   .pcm-shadow  ground contact ellipse
//   .pcm-tail    wags; transform-origin is set per-species in the CSS
//   .pcm-hind    static hind haunches + feet on the six quadrupeds; painted
//                before the body so only their outer half shows
//   .pcm-body    breathes
//   .pcm-ear-l/r ears (crest on Bird, gill covers on Fish, brows on Reptile)
//   .pcm-head    tilts; contains .pcm-eye-l/r (blink) with .pcm-pupil (tracks
//                the cursor), .pcm-blush, and .pcm-muzzle
//   .pcm-paw-l/r front limbs (wings on Bird, pectoral fins on Fish)
//
// The paws MUST stay last. SVG has no z-index — painting order is document
// order — and the .is-shy state lifts them up over the face to cover the eyes.
// Anywhere earlier in the group and .pcm-head paints straight over the top of
// them, which silently reduces the whole gag to the eyes closing on their own.
// At rest they sit around y=180 while the lowest head geometry reaches y=122,
// so being last costs nothing in the neutral pose.
//
// .pcm-paw-* are the FRONT pair only. .pcm-hind is a separate, unanimated
// group precisely so it stays put when the front paws lift — otherwise a
// four-legged animal loses every leg it has the moment someone types a
// password.
//
// Colours are per-species CSS custom properties on the root .pcm group, so the
// shapes below never hardcode a hex value. The scene tints around the animal
// via --av2-accent; the animal keeps its own natural palette, which reads far
// better than recolouring the character itself.
?>
<div id="pcm-library" hidden aria-hidden="true">
  <svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 200 200" width="0" height="0" focusable="false">

    <!-- ─────────────────────────── DOG ─────────────────────────── -->
    <!-- Shaded rebuild. The construction rule throughout: every base shape keeps
         the geometry the flat version had, and all modelling is added INSIDE it.
         auth_mascots.css resolves each animated group's transform-origin as a
         percentage of its own fill-box, so growing a group's geometry silently
         relocates its pivot. Two things are exempt and used freely — stroke and
         filters do not contribute to the object bounding box.
         The fur fringe exploits exactly that: a duplicate of the silhouette,
         one step darker, sitting BEHIND the clean shape with a displacement
         filter on it. The filter shoves its edge pixels out into an irregular
         fringe while its geometry, and therefore the pivot, is untouched.
         Eye centres (83,71)/(117,71) and paw centres (72,181)/(128,181) are
         also load-bearing: --pcm-shy-x/y in auth_mascots.css:219 is literally
         the measured delta between them. -->
    <g class="pcm" data-species="Dog"
      style="--pcm-fur:#F2A93B;--pcm-dark:#D9891C;--pcm-belly:#FFF4E0;--pcm-inner:#FF9E7A;--pcm-ink:#3B2E26;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="52" ry="7.5" filter="url(#pcm-blur)" />

      <!-- Plumed tail, sweeping up the right flank. -->
      <g class="pcm-tail">
        <path d="M116 162 C138 162 157 144 163 121 C166 107 159 96 151 99 C144 102 147 115 141 128 C134 142 124 152 112 156 Z"
          fill="var(--pcm-dark)" filter="url(#pcm-fur-soft)" />
        <path d="M116 162 C138 162 157 144 163 121 C166 107 159 96 151 99 C144 102 147 115 141 128 C134 142 124 152 112 156 Z"
          fill="var(--pcm-fur)" />
        <path d="M136 148 C150 146 160 134 163 119 C165 108 160 101 155 103 C150 105 152 115 147 126 C143 135 139 143 134 146 Z"
          fill="url(#pcm-sh)" />
        <path d="M139 142 C150 138 157 128 160 117 C161 110 158 106 155 108 C151 111 153 118 148 127 Z"
          fill="url(#pcm-hi)" />
        <path d="M157 100 C163 97 168 103 166 112 C164 106 161 101 157 100 Z" fill="var(--pcm-belly)" opacity=".9" />
      </g>

      <!-- Hind quarters. Painted BEFORE the body so only the haunch shows at
           each flank, which is what a seated quadruped actually looks like —
           and it means the animal still has hind legs once .is-shy lifts the
           front pair to its eyes. -->
      <g class="pcm-hind">
        <ellipse cx="66" cy="167" rx="16" ry="21" fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="134" cy="167" rx="16" ry="21" fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="66" cy="162" rx="11" ry="14" fill="url(#pcm-hi)" />
        <ellipse cx="134" cy="162" rx="11" ry="14" fill="url(#pcm-hi)" />
        <ellipse cx="60" cy="186" rx="12" ry="7" fill="var(--pcm-dark)" />
        <ellipse cx="140" cy="186" rx="12" ry="7" fill="var(--pcm-dark)" />
      </g>

      <!-- Body: narrow chest opening to a broad seated base, with the front
           legs carried as part of the same mass. -->
      <g class="pcm-body">
        <path d="M100 102 C118 102 129 117 133 136 C138 156 141 176 138 185 C135 192 120 195 100 195 C80 195 65 192 62 185 C59 176 62 156 67 136 C71 117 82 102 100 102 Z"
          fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <path d="M100 102 C118 102 129 117 133 136 C138 156 141 176 138 185 C135 192 120 195 100 195 C80 195 65 192 62 185 C59 176 62 156 67 136 C71 117 82 102 100 102 Z"
          fill="var(--pcm-fur)" />
        <path d="M100 102 C118 102 129 117 133 136 C138 156 141 176 138 185 C135 192 120 195 100 195 C80 195 65 192 62 185 C59 176 62 156 67 136 C71 117 82 102 100 102 Z"
          fill="url(#pcm-sh-v)" />
        <ellipse cx="116" cy="160" rx="28" ry="34" fill="url(#pcm-sh)" />
        <ellipse cx="82" cy="132" rx="24" ry="24" fill="url(#pcm-hi)" />
        <path d="M100 102 C118 102 129 117 133 136 C138 156 141 176 138 185 C135 192 120 195 100 195 C80 195 65 192 62 185 C59 176 62 156 67 136 C71 117 82 102 100 102 Z"
          fill="url(#pcm-rim)" />

        <!-- Chest ruff and belly, lighter than the coat. -->
        <path d="M100 120 C110 120 116 137 117 155 C118 171 111 184 100 184 C89 184 82 171 83 155 C84 137 90 120 100 120 Z"
          fill="var(--pcm-belly)" filter="url(#pcm-fur-soft)" />
        <path d="M100 120 C110 120 116 137 117 155 C118 171 111 184 100 184 C89 184 82 171 83 155 C84 137 90 120 100 120 Z"
          fill="var(--pcm-belly)" />
        <ellipse cx="107" cy="166" rx="17" ry="22" fill="url(#pcm-sh)" />
        <ellipse cx="94" cy="134" rx="13" ry="12" fill="url(#pcm-hi)" />

        <!-- Front legs: tapered, in the ruff colour, each with a shaded inner
             edge so the pair reads as two rounded limbs rather than one slab. -->
        <path d="M83 146 C78 160 75 174 75 186 L89 186 C89 174 90 160 93 146 Z" fill="var(--pcm-belly)" />
        <path d="M117 146 C122 160 125 174 125 186 L111 186 C111 174 110 160 107 146 Z" fill="var(--pcm-belly)" />
        <path d="M86 150 C82 162 80 174 80 186 L89 186 C89 174 90 162 92 150 Z" fill="url(#pcm-sh)" />
        <path d="M114 150 C118 162 120 174 120 186 L111 186 C111 174 110 162 108 150 Z" fill="url(#pcm-sh)" />
        <ellipse cx="100" cy="150" rx="14" ry="9" fill="url(#pcm-ao)" />

        <!-- Contact shade where the head sits on the chest. -->
        <ellipse cx="100" cy="108" rx="30" ry="13" fill="url(#pcm-ao)" />
      </g>

      <!-- Erect ears, hinged at the skull. -->
      <g class="pcm-ear pcm-ear-l">
        <path d="M72 50 C67 35 65 20 70 14 C75 9 85 19 91 31 C94 38 95 45 94 50 Z"
          fill="var(--pcm-dark)" opacity=".85" filter="url(#pcm-fur-edge)" />
        <path d="M72 50 C67 35 65 20 70 14 C75 9 85 19 91 31 C94 38 95 45 94 50 Z" fill="var(--pcm-dark)" />
        <path d="M76 47 C72 35 71 24 74 20 C78 17 84 25 88 34 C90 39 91 44 90 47 Z"
          fill="var(--pcm-inner)" opacity=".5" />
        <path d="M78 46 C75 36 74 27 76 23 C79 21 83 28 86 35 Z" fill="url(#pcm-sh)" />
        <path d="M70 30 C68 22 68 16 70 14 C73 12 76 18 77 24 Z" fill="url(#pcm-hi)" />
        <ellipse cx="83" cy="52" rx="10" ry="7" fill="url(#pcm-ao)" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <path d="M128 50 C133 35 135 20 130 14 C125 9 115 19 109 31 C106 38 105 45 106 50 Z"
          fill="var(--pcm-dark)" opacity=".85" filter="url(#pcm-fur-edge)" />
        <path d="M128 50 C133 35 135 20 130 14 C125 9 115 19 109 31 C106 38 105 45 106 50 Z" fill="var(--pcm-dark)" />
        <path d="M124 47 C128 35 129 24 126 20 C122 17 116 25 112 34 C110 39 109 44 110 47 Z"
          fill="var(--pcm-inner)" opacity=".5" />
        <path d="M122 46 C125 36 126 27 124 23 C121 21 117 28 114 35 Z" fill="url(#pcm-sh)" />
        <path d="M130 30 C132 22 132 16 130 14 C127 12 124 18 123 24 Z" fill="url(#pcm-hi)" />
        <ellipse cx="117" cy="52" rx="10" ry="7" fill="url(#pcm-ao)" />
      </g>

      <!-- Head: rounded skull flaring to cheeks, then tapering into a
           projecting muzzle. This silhouette is what carries the read as a dog
           rather than a sphere; the shading only models it. -->
      <g class="pcm-head">
        <path d="M100 33 C119 33 133 47 134 66 C135 78 131 87 125 93 C122 96 119 99 117 104 C115 110 108 114 100 114 C92 114 85 110 83 104 C81 99 78 96 75 93 C69 87 65 78 66 66 C67 47 81 33 100 33 Z"
          fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <path d="M100 33 C119 33 133 47 134 66 C135 78 131 87 125 93 C122 96 119 99 117 104 C115 110 108 114 100 114 C92 114 85 110 83 104 C81 99 78 96 75 93 C69 87 65 78 66 66 C67 47 81 33 100 33 Z"
          fill="var(--pcm-fur)" />
        <path d="M100 33 C119 33 133 47 134 66 C135 78 131 87 125 93 C122 96 119 99 117 104 C115 110 108 114 100 114 C92 114 85 110 83 104 C81 99 78 96 75 93 C69 87 65 78 66 66 C67 47 81 33 100 33 Z"
          fill="url(#pcm-hi-v)" />
        <ellipse cx="114" cy="86" rx="24" ry="26" fill="url(#pcm-sh)" />
        <ellipse cx="87" cy="53" rx="20" ry="16" fill="url(#pcm-hi)" />
        <path d="M100 33 C119 33 133 47 134 66 C135 78 131 87 125 93 C122 96 119 99 117 104 C115 110 108 114 100 114 C92 114 85 110 83 104 C81 99 78 96 75 93 C69 87 65 78 66 66 C67 47 81 33 100 33 Z"
          fill="url(#pcm-rim)" />

        <!-- No forehead blaze: every version of one read as a stripe or a horn
             at this size. The skull's own key light carries the forehead. -->

        <ellipse class="pcm-blush" cx="74" cy="83" rx="8.5" ry="5.5" fill="var(--pcm-inner)" opacity=".34" />
        <ellipse class="pcm-blush" cx="126" cy="83" rx="8.5" ry="5.5" fill="var(--pcm-inner)" opacity=".34" />

        <!-- Almond eyes set into the skull, with a brow shadow above rather
             than a drawn-on eyebrow. .pcm-pupil is a GROUP so the whole iris
             tracks together; auth_scene.js only writes a translate, which a
             <g> takes as readily as a <circle>. -->
        <g class="pcm-eye pcm-eye-l">
          <ellipse cx="85" cy="66" rx="10" ry="8.4" fill="#FFFDF7" />
          <ellipse cx="85" cy="66" rx="10" ry="8.4" fill="url(#pcm-lid)" />
          <g class="pcm-pupil">
            <circle cx="85" cy="66.6" r="6.6" fill="var(--pcm-ink)" />
            <circle cx="85" cy="66.6" r="6.6" fill="url(#pcm-iris)" />
            <circle cx="85" cy="66.6" r="3.3" fill="#171009" />
          </g>
          <circle class="pcm-glint" cx="86.4" cy="64" r="1.7" fill="#FFFFFF" />
          <circle cx="81.6" cy="69.4" r="0.9" fill="#FFFFFF" opacity=".45" />
          <path d="M75.6 63 C79 58.4 90 58 94.6 63.4 C90 60.6 79.6 60.6 75.6 63 Z" fill="var(--pcm-ink)" opacity=".5" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <ellipse cx="115" cy="66" rx="10" ry="8.4" fill="#FFFDF7" />
          <ellipse cx="115" cy="66" rx="10" ry="8.4" fill="url(#pcm-lid)" />
          <g class="pcm-pupil">
            <circle cx="115" cy="66.6" r="6.6" fill="var(--pcm-ink)" />
            <circle cx="115" cy="66.6" r="6.6" fill="url(#pcm-iris)" />
            <circle cx="115" cy="66.6" r="3.3" fill="#171009" />
          </g>
          <circle class="pcm-glint" cx="116.4" cy="64" r="1.7" fill="#FFFFFF" />
          <circle cx="111.6" cy="69.4" r="0.9" fill="#FFFFFF" opacity=".45" />
          <path d="M105.4 63.4 C110 58 121 58.4 124.4 63 C120.4 60.6 110 60.6 105.4 63.4 Z" fill="var(--pcm-ink)" opacity=".5" />
        </g>

        <g class="pcm-muzzle">
          <path d="M100 82 C110 82 117 90 117 98 C117 107 109 113 100 113 C91 113 83 107 83 98 C83 90 90 82 100 82 Z"
            fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
          <path d="M100 82 C110 82 117 90 117 98 C117 107 109 113 100 113 C91 113 83 107 83 98 C83 90 90 82 100 82 Z"
            fill="var(--pcm-belly)" />
          <ellipse cx="105" cy="105" rx="13" ry="8" fill="url(#pcm-sh)" />
          <ellipse cx="93" cy="90" rx="10" ry="6" fill="url(#pcm-hi)" />

          <!-- Nose: a rounded wedge with its own core shade and a wet spot. -->
          <path d="M100 84 C105 84 108.5 87 108.5 90.5 C108.5 94.5 104.5 97 100 97 C95.5 97 91.5 94.5 91.5 90.5 C91.5 87 95 84 100 84 Z"
            fill="var(--pcm-ink)" />
          <path d="M100 86 C104 86 107 88.5 107 91.5 C107 94.5 104 96 100 96 C96 96 93 94.5 93 91.5 C93 88.5 96 86 100 86 Z"
            fill="url(#pcm-sh)" />
          <ellipse cx="97" cy="88" rx="2.6" ry="1.8" fill="url(#pcm-spec)" />
          <g class="pcm-mouth">
            <path d="M100 97 v5 M91 103 q4.5 5.5 9 1 M100 103 q4.5 4.5 9 -1" fill="none" stroke="var(--pcm-ink)"
              stroke-width="2.1" stroke-linecap="round" />
          </g>
          <path class="pcm-tongue" d="M96 108 h8 a4 4 0 0 1 -8 0 z" fill="var(--pcm-inner)" />
          <circle cx="90" cy="96" r="0.9" fill="var(--pcm-ink)" opacity=".28" />
          <circle cx="110" cy="96" r="0.9" fill="var(--pcm-ink)" opacity=".28" />
          <circle cx="91" cy="101" r="0.9" fill="var(--pcm-ink)" opacity=".28" />
          <circle cx="109" cy="101" r="0.9" fill="var(--pcm-ink)" opacity=".28" />
        </g>
      </g>

      <!-- Front paws. MUST stay the last children: .is-shy lifts them over the
           face and SVG resolves overlap by document order alone. -->
      <g class="pcm-paw pcm-paw-l">
        <ellipse cx="80" cy="185" rx="14" ry="9.5" fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="80" cy="185" rx="14" ry="9.5" fill="var(--pcm-belly)" />
        <ellipse cx="82" cy="188" rx="11" ry="6" fill="url(#pcm-sh)" />
        <ellipse cx="76" cy="182" rx="8" ry="5" fill="url(#pcm-hi)" />
        <path d="M75 180 v5 M80 179 v5.5 M85 180 v5" fill="none" stroke="var(--pcm-dark)" stroke-width="1.4"
          stroke-linecap="round" opacity=".38" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <ellipse cx="120" cy="185" rx="14" ry="9.5" fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="120" cy="185" rx="14" ry="9.5" fill="var(--pcm-belly)" />
        <ellipse cx="122" cy="188" rx="11" ry="6" fill="url(#pcm-sh)" />
        <ellipse cx="116" cy="182" rx="8" ry="5" fill="url(#pcm-hi)" />
        <path d="M115 180 v5 M120 179 v5.5 M125 180 v5" fill="none" stroke="var(--pcm-dark)" stroke-width="1.4"
          stroke-linecap="round" opacity=".38" />
      </g>
    </g>

    <!-- ─────────────────────────── CAT ─────────────────────────── -->
    <g class="pcm" data-species="Cat"
      style="--pcm-fur:#B4A0E8;--pcm-dark:#8E78C9;--pcm-belly:#FFF6E8;--pcm-inner:#FFA8C0;--pcm-ink:#332B4A;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="50" ry="7.5" filter="url(#pcm-blur)" />

      <!-- Long tail curling out from behind the right flank and round toward
           the front paws. Only the stretch clearing the body silhouette shows,
           since .pcm-tail paints behind .pcm-body. -->
      <g class="pcm-tail">
        <path d="M118 168 C142 172 160 164 164 146 C167 132 158 122 149 126 C141 130 146 143 138 152 C131 160 124 163 116 164 Z"
          fill="var(--pcm-dark)" filter="url(#pcm-fur-soft)" />
        <path d="M118 168 C142 172 160 164 164 146 C167 132 158 122 149 126 C141 130 146 143 138 152 C131 160 124 163 116 164 Z"
          fill="var(--pcm-fur)" />
        <path d="M124 167 C144 169 157 161 160 146 C162 135 156 128 151 130 C146 133 149 144 142 152 C136 159 130 162 123 163 Z"
          fill="url(#pcm-sh)" />
        <path d="M132 162 C147 161 155 152 158 143 C159 136 156 132 153 134 C150 137 152 145 146 151 Z"
          fill="url(#pcm-hi)" />
        <path d="M150 127 C157 124 163 130 162 138 C160 132 155 128 150 127 Z" fill="var(--pcm-belly)" opacity=".85" />
      </g>

      <!-- Haunches: painted before the body so only the outer curve shows at
           each flank, and so the cat keeps hind legs when .is-shy lifts the
           front pair. -->
      <g class="pcm-hind">
        <ellipse cx="68" cy="168" rx="15" ry="20" fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="132" cy="168" rx="15" ry="20" fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="68" cy="163" rx="10" ry="13" fill="url(#pcm-hi)" />
        <ellipse cx="132" cy="163" rx="10" ry="13" fill="url(#pcm-hi)" />
        <ellipse cx="62" cy="186" rx="11" ry="6.5" fill="var(--pcm-dark)" />
        <ellipse cx="138" cy="186" rx="11" ry="6.5" fill="var(--pcm-dark)" />
      </g>

      <!-- Body: a narrower, more upright seat than the dog's — the silhouette
           is most of what separates the two species at a glance. -->
      <g class="pcm-body">
        <path d="M100 100 C115 100 125 116 129 134 C134 154 137 176 134 185 C131 192 118 195 100 195 C82 195 69 192 66 185 C63 176 66 154 71 134 C75 116 85 100 100 100 Z"
          fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <path d="M100 100 C115 100 125 116 129 134 C134 154 137 176 134 185 C131 192 118 195 100 195 C82 195 69 192 66 185 C63 176 66 154 71 134 C75 116 85 100 100 100 Z"
          fill="var(--pcm-fur)" />
        <path d="M100 100 C115 100 125 116 129 134 C134 154 137 176 134 185 C131 192 118 195 100 195 C82 195 69 192 66 185 C63 176 66 154 71 134 C75 116 85 100 100 100 Z"
          fill="url(#pcm-sh-v)" />
        <ellipse cx="114" cy="158" rx="26" ry="33" fill="url(#pcm-sh)" />
        <ellipse cx="84" cy="130" rx="22" ry="23" fill="url(#pcm-hi)" />
        <path d="M100 100 C115 100 125 116 129 134 C134 154 137 176 134 185 C131 192 118 195 100 195 C82 195 69 192 66 185 C63 176 66 154 71 134 C75 116 85 100 100 100 Z"
          fill="url(#pcm-rim)" />

        <!-- Chest bib. -->
        <path d="M100 118 C109 118 115 135 116 153 C117 169 110 183 100 183 C90 183 83 169 84 153 C85 135 91 118 100 118 Z"
          fill="var(--pcm-belly)" filter="url(#pcm-fur-soft)" />
        <path d="M100 118 C109 118 115 135 116 153 C117 169 110 183 100 183 C90 183 83 169 84 153 C85 135 91 118 100 118 Z"
          fill="var(--pcm-belly)" />
        <ellipse cx="106" cy="163" rx="15" ry="20" fill="url(#pcm-sh)" />
        <ellipse cx="94" cy="133" rx="11" ry="11" fill="url(#pcm-hi)" />

        <!-- Front legs, slimmer than the dog's. -->
        <path d="M85 146 C81 160 79 174 79 186 L91 186 C91 174 92 160 94 146 Z" fill="var(--pcm-belly)" />
        <path d="M115 146 C119 160 121 174 121 186 L109 186 C109 174 108 160 106 146 Z" fill="var(--pcm-belly)" />
        <path d="M88 150 C85 162 83 174 83 186 L91 186 C91 174 92 162 93 150 Z" fill="url(#pcm-sh)" />
        <path d="M112 150 C115 162 117 174 117 186 L109 186 C109 174 108 162 107 150 Z" fill="url(#pcm-sh)" />
        <ellipse cx="100" cy="148" rx="13" ry="8" fill="url(#pcm-ao)" />
        <ellipse cx="100" cy="106" rx="27" ry="12" fill="url(#pcm-ao)" />
      </g>

      <!-- Tall triangular ears, wider at the base than the dog's. -->
      <g class="pcm-ear pcm-ear-l">
        <path d="M70 48 C64 32 62 15 67 11 C72 7 84 19 92 33 C95 39 96 44 95 48 Z"
          fill="var(--pcm-dark)" opacity=".85" filter="url(#pcm-fur-edge)" />
        <path d="M70 48 C64 32 62 15 67 11 C72 7 84 19 92 33 C95 39 96 44 95 48 Z" fill="var(--pcm-fur)" />
        <path d="M74 45 C70 32 69 20 72 17 C76 14 83 25 88 35 C90 40 91 43 90 45 Z"
          fill="var(--pcm-inner)" opacity=".55" />
        <path d="M76 44 C73 34 72 24 74 21 C77 19 82 28 85 36 Z" fill="url(#pcm-sh)" />
        <path d="M67 28 C65 20 65 14 67 12 C70 10 73 16 74 22 Z" fill="url(#pcm-hi)" />
        <!-- Ear tufts: strokes only, so they roughen the outline without
             enlarging the fill-box that the hinge percentage resolves against. -->
        <path d="M72 24 l-4 -5 M75 32 l-5 -4 M79 39 l-6 -3" fill="none" stroke="var(--pcm-belly)"
          stroke-width="1.6" stroke-linecap="round" opacity=".65" />
        <ellipse cx="82" cy="50" rx="10" ry="6" fill="url(#pcm-ao)" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <path d="M130 48 C136 32 138 15 133 11 C128 7 116 19 108 33 C105 39 104 44 105 48 Z"
          fill="var(--pcm-dark)" opacity=".85" filter="url(#pcm-fur-edge)" />
        <path d="M130 48 C136 32 138 15 133 11 C128 7 116 19 108 33 C105 39 104 44 105 48 Z" fill="var(--pcm-fur)" />
        <path d="M126 45 C130 32 131 20 128 17 C124 14 117 25 112 35 C110 40 109 43 110 45 Z"
          fill="var(--pcm-inner)" opacity=".55" />
        <path d="M124 44 C127 34 128 24 126 21 C123 19 118 28 115 36 Z" fill="url(#pcm-sh)" />
        <path d="M133 28 C135 20 135 14 133 12 C130 10 127 16 126 22 Z" fill="url(#pcm-hi)" />
        <path d="M128 24 l4 -5 M125 32 l5 -4 M121 39 l6 -3" fill="none" stroke="var(--pcm-belly)"
          stroke-width="1.6" stroke-linecap="round" opacity=".65" />
        <ellipse cx="118" cy="50" rx="10" ry="6" fill="url(#pcm-ao)" />
      </g>

      <!-- Head: rounder than the dog's, with a short muzzle and full cheeks. -->
      <g class="pcm-head">
        <path d="M100 34 C120 34 134 48 135 66 C136 80 130 92 120 98 C114 102 108 105 100 105 C92 105 86 102 80 98 C70 92 64 80 65 66 C66 48 80 34 100 34 Z"
          fill="var(--pcm-dark)" filter="url(#pcm-fur-edge)" />
        <path d="M100 34 C120 34 134 48 135 66 C136 80 130 92 120 98 C114 102 108 105 100 105 C92 105 86 102 80 98 C70 92 64 80 65 66 C66 48 80 34 100 34 Z"
          fill="var(--pcm-fur)" />
        <path d="M100 34 C120 34 134 48 135 66 C136 80 130 92 120 98 C114 102 108 105 100 105 C92 105 86 102 80 98 C70 92 64 80 65 66 C66 48 80 34 100 34 Z"
          fill="url(#pcm-hi-v)" />
        <ellipse cx="115" cy="84" rx="23" ry="24" fill="url(#pcm-sh)" />
        <ellipse cx="86" cy="54" rx="20" ry="16" fill="url(#pcm-hi)" />
        <path d="M100 34 C120 34 134 48 135 66 C136 80 130 92 120 98 C114 102 108 105 100 105 C92 105 86 102 80 98 C70 92 64 80 65 66 C66 48 80 34 100 34 Z"
          fill="url(#pcm-rim)" />
        <!-- Tabby forehead markings: strokes, so no fill-box growth. -->
        <path d="M89 40 C91 45 91 49 90 52 M100 38 C101 43 101 47 100 50 M111 40 C109 45 109 49 110 52"
          fill="none" stroke="var(--pcm-dark)" stroke-width="2.2" stroke-linecap="round" opacity=".26" />

        <ellipse class="pcm-blush" cx="74" cy="80" rx="8.5" ry="5.5" fill="var(--pcm-inner)" opacity=".4" />
        <ellipse class="pcm-blush" cx="126" cy="80" rx="8.5" ry="5.5" fill="var(--pcm-inner)" opacity=".4" />

        <!-- Eyes: almond, with a vertical slit pupil. The slit is what reads as
             "cat" more than any other single feature, so it stays crisp rather
             than being softened by the iris gradient. -->
        <g class="pcm-eye pcm-eye-l">
          <ellipse cx="85" cy="64" rx="10.5" ry="9" fill="#FFFDF7" />
          <ellipse cx="85" cy="64" rx="10.5" ry="9" fill="url(#pcm-lid)" />
          <g class="pcm-pupil">
            <ellipse cx="85" cy="64.6" rx="7.4" ry="8" fill="var(--pcm-fur)" />
            <ellipse cx="85" cy="64.6" rx="7.4" ry="8" fill="url(#pcm-iris)" />
            <ellipse cx="85" cy="64.6" rx="2.6" ry="7.4" fill="#171009" />
          </g>
          <circle class="pcm-glint" cx="86.6" cy="61.6" r="1.7" fill="#FFFFFF" />
          <circle cx="81.6" cy="67.4" r="0.9" fill="#FFFFFF" opacity=".45" />
          <path d="M75.2 61 C79 56.4 91 56 95 61.4 C90.4 58.6 79.4 58.6 75.2 61 Z" fill="var(--pcm-ink)" opacity=".5" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <ellipse cx="115" cy="64" rx="10.5" ry="9" fill="#FFFDF7" />
          <ellipse cx="115" cy="64" rx="10.5" ry="9" fill="url(#pcm-lid)" />
          <g class="pcm-pupil">
            <ellipse cx="115" cy="64.6" rx="7.4" ry="8" fill="var(--pcm-fur)" />
            <ellipse cx="115" cy="64.6" rx="7.4" ry="8" fill="url(#pcm-iris)" />
            <ellipse cx="115" cy="64.6" rx="2.6" ry="7.4" fill="#171009" />
          </g>
          <circle class="pcm-glint" cx="116.6" cy="61.6" r="1.7" fill="#FFFFFF" />
          <circle cx="111.6" cy="67.4" r="0.9" fill="#FFFFFF" opacity=".45" />
          <path d="M105 61.4 C109 56 121 56.4 124.8 61 C120.6 58.6 109.6 58.6 105 61.4 Z" fill="var(--pcm-ink)" opacity=".5" />
        </g>

        <g class="pcm-muzzle">
          <ellipse cx="91" cy="90" rx="12" ry="9" fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
          <ellipse cx="109" cy="90" rx="12" ry="9" fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
          <ellipse cx="91" cy="90" rx="12" ry="9" fill="var(--pcm-belly)" />
          <ellipse cx="109" cy="90" rx="12" ry="9" fill="var(--pcm-belly)" />
          <ellipse cx="100" cy="94" rx="17" ry="7" fill="url(#pcm-sh)" />
          <ellipse cx="93" cy="86" rx="9" ry="5" fill="url(#pcm-hi)" />
          <!-- Small inverted-triangle nose. -->
          <path d="M93.5 80 L106.5 80 Q107 81 100 88 Q93 81 93.5 80 Z" fill="var(--pcm-inner)" />
          <path d="M95 81.6 L105 81.6 Q105 82.4 100 86.6 Q95 82.4 95 81.6 Z" fill="url(#pcm-sh)" />
          <ellipse cx="97.5" cy="81.6" rx="2" ry="1.2" fill="url(#pcm-spec)" />
          <g class="pcm-mouth">
            <path d="M100 88 v3 M92 93 q4 5 8 -2 M100 91 q4 7 8 2" fill="none" stroke="var(--pcm-ink)"
              stroke-width="2" stroke-linecap="round" />
          </g>
          <!-- Whiskers. Strokes have no effect on the fill-box, but the path
               geometry itself does, so these widen .pcm-head — which is why the
               head pivot is remeasured for Cat in auth_mascots.css. -->
          <path d="M79 87 C71 84 66 82 62 81 M78 92 C70 92 65 92 61 93 M79 97 C71 99 67 101 63 103
                   M121 87 C129 84 134 82 138 81 M122 92 C130 92 135 92 139 93 M121 97 C129 99 133 101 137 103"
            fill="none" stroke="var(--pcm-ink)" stroke-width="1.3" stroke-linecap="round" opacity=".3" />
        </g>
      </g>

      <!-- Front paws. MUST stay last: .is-shy raises them over the face and SVG
           resolves overlap by document order alone. -->
      <g class="pcm-paw pcm-paw-l">
        <ellipse cx="80" cy="185" rx="13" ry="9" fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="80" cy="185" rx="13" ry="9" fill="var(--pcm-belly)" />
        <ellipse cx="82" cy="188" rx="10" ry="5.5" fill="url(#pcm-sh)" />
        <ellipse cx="76" cy="182" rx="7.5" ry="4.5" fill="url(#pcm-hi)" />
        <path d="M75 180 v4.5 M80 179 v5 M85 180 v4.5" fill="none" stroke="var(--pcm-dark)" stroke-width="1.3"
          stroke-linecap="round" opacity=".4" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <ellipse cx="120" cy="185" rx="13" ry="9" fill="var(--pcm-belly)" filter="url(#pcm-fur-edge)" />
        <ellipse cx="120" cy="185" rx="13" ry="9" fill="var(--pcm-belly)" />
        <ellipse cx="122" cy="188" rx="10" ry="5.5" fill="url(#pcm-sh)" />
        <ellipse cx="116" cy="182" rx="7.5" ry="4.5" fill="url(#pcm-hi)" />
        <path d="M115 180 v4.5 M120 179 v5 M125 180 v4.5" fill="none" stroke="var(--pcm-dark)" stroke-width="1.3"
          stroke-linecap="round" opacity=".4" />
      </g>
    </g>

    <!-- Rig remap: .pcm-ear-* are the head crest feathers, .pcm-paw-* are the
         wings. Same class names, so the same flap/wiggle keyframes apply. -->
    <g class="pcm" data-species="Bird"
      style="--pcm-fur:#5CC8F2;--pcm-dark:#2C9ED0;--pcm-belly:#FFF6E8;--pcm-inner:#F5A623;--pcm-ink:#22384A;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="46" ry="7.5" />
      <g class="pcm-tail">
        <path d="M138 152 L182 138 L176 156 L184 168 L138 168 Z" fill="var(--pcm-dark)" />
        <path d="M141 154 L172 144 M141 160 L178 158 M141 166 L174 166" fill="none" stroke="var(--pcm-fur)"
          stroke-width="1.8" opacity=".55" />
      </g>
      <g class="pcm-body">
        <ellipse cx="100" cy="146" rx="41" ry="39" fill="var(--pcm-fur)" />
        <ellipse cx="100" cy="155" rx="25" ry="27" fill="var(--pcm-belly)" />
        <path d="M86 180 v8 h-9 M92 180 v8 h9 M114 180 v8 h-9 M108 180 v8 h9" fill="none" stroke="var(--pcm-inner)"
          stroke-width="3.4" stroke-linecap="round" stroke-linejoin="round" />
      </g>
      <g class="pcm-ear pcm-ear-l">
        <path d="M92 38 q-6 -26 -22 -32 q6 20 12 34 z" fill="var(--pcm-dark)" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <path d="M104 36 q4 -30 22 -38 q-4 24 -12 40 z" fill="var(--pcm-dark)" />
      </g>
      <g class="pcm-head">
        <circle cx="100" cy="76" r="44" fill="var(--pcm-fur)" />
        <ellipse class="pcm-blush" cx="66" cy="92" rx="10" ry="6.5" fill="#FF9E7A" opacity=".45" />
        <ellipse class="pcm-blush" cx="134" cy="92" rx="10" ry="6.5" fill="#FF9E7A" opacity=".45" />
        <g class="pcm-eye pcm-eye-l">
          <circle cx="83" cy="70" r="13" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="83" cy="71" r="6.8" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="84.3" cy="69.4" r="1.8" fill="#FFFFFF" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <circle cx="117" cy="70" r="13" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="117" cy="71" r="6.8" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="118.3" cy="69.4" r="1.8" fill="#FFFFFF" />
        </g>
        <g class="pcm-muzzle">
          <path d="M86 96 L114 96 L100 116 Z" fill="var(--pcm-inner)" />
          <path d="M86 96 L114 96 L100 104 Z" fill="var(--pcm-ink)" opacity=".18" />
        </g>
      </g>
      <g class="pcm-paw pcm-paw-l">
        <path d="M64 132 q-24 8 -18 32 q16 6 26 -18 z" fill="var(--pcm-dark)" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <path d="M136 132 q24 8 18 32 q-16 6 -26 -18 z" fill="var(--pcm-dark)" />
      </g>
    </g>

    <!-- ────────────────────────── RABBIT ───────────────────────── -->
    <g class="pcm" data-species="Rabbit"
      style="--pcm-fur:#F7E6CE;--pcm-dark:#E3CDAE;--pcm-belly:#FFFDF8;--pcm-inner:#FBA6C4;--pcm-ink:#4A3A34;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="50" ry="8" />
      <g class="pcm-tail">
        <circle cx="146" cy="162" r="14" fill="var(--pcm-belly)" />
        <circle cx="142" cy="158" r="5" fill="var(--pcm-dark)" opacity=".35" />
      </g>
      <!-- Hind legs. Painted BEFORE the body so the body hides their inner
           half and only the haunch and foot show at each flank — which is
           what a seated quadruped actually looks like, and means the animal
           still has four legs once .is-shy lifts the front pair to its eyes.
           Drawn in --pcm-dark to sit back in shadow. -->
      <g class="pcm-hind">
        <ellipse cx="67" cy="165" rx="18" ry="22" fill="var(--pcm-dark)" />
        <ellipse cx="133" cy="165" rx="18" ry="22" fill="var(--pcm-dark)" />
        <ellipse cx="60" cy="187" rx="23" ry="8.5" fill="var(--pcm-dark)" />
        <ellipse cx="140" cy="187" rx="23" ry="8.5" fill="var(--pcm-dark)" />
      </g>
      <g class="pcm-body">
        <ellipse cx="100" cy="150" rx="43" ry="40" fill="var(--pcm-fur)" />
        <ellipse cx="100" cy="159" rx="25" ry="27" fill="var(--pcm-belly)" />
      </g>
      <g class="pcm-ear pcm-ear-l">
        <ellipse cx="78" cy="28" rx="13" ry="30" transform="rotate(-9 78 28)" fill="var(--pcm-fur)" />
        <ellipse cx="78" cy="30" rx="6.5" ry="21" transform="rotate(-9 78 30)" fill="var(--pcm-inner)" opacity=".6" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <ellipse cx="122" cy="28" rx="13" ry="30" transform="rotate(9 122 28)" fill="var(--pcm-fur)" />
        <ellipse cx="122" cy="30" rx="6.5" ry="21" transform="rotate(9 122 30)" fill="var(--pcm-inner)" opacity=".6" />
      </g>
      <g class="pcm-head">
        <circle cx="100" cy="80" r="44" fill="var(--pcm-fur)" />
        <ellipse class="pcm-blush" cx="66" cy="94" rx="11" ry="7" fill="var(--pcm-inner)" opacity=".55" />
        <ellipse class="pcm-blush" cx="134" cy="94" rx="11" ry="7" fill="var(--pcm-inner)" opacity=".55" />
        <g class="pcm-eye pcm-eye-l">
          <circle cx="83" cy="75" r="13" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="83" cy="76" r="6.8" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="84.3" cy="74.4" r="1.8" fill="#FFFFFF" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <circle cx="117" cy="75" r="13" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="117" cy="76" r="6.8" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="118.3" cy="74.4" r="1.8" fill="#FFFFFF" />
        </g>
        <g class="pcm-muzzle">
          <ellipse cx="100" cy="104" rx="20" ry="13" fill="var(--pcm-belly)" />
          <path d="M100 94 L106 100 L94 100 Z" fill="var(--pcm-inner)" />
          <path d="M100 100 v4 M91 104 q4.5 5 9 0 M100 104 q4.5 5 9 0" fill="none" stroke="var(--pcm-ink)"
            stroke-width="2.2" stroke-linecap="round" />
          <rect x="95.5" y="109" width="4" height="8" rx="1.4" fill="#FFFFFF" />
          <rect x="100.5" y="109" width="4" height="8" rx="1.4" fill="#FFFFFF" />
        </g>
      </g>
      <g class="pcm-paw pcm-paw-l">
        <ellipse cx="74" cy="182" rx="16" ry="11" fill="var(--pcm-belly)" />
        <circle cx="74" cy="182" r="3.6" fill="var(--pcm-inner)" opacity=".55" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <ellipse cx="126" cy="182" rx="16" ry="11" fill="var(--pcm-belly)" />
        <circle cx="126" cy="182" r="3.6" fill="var(--pcm-inner)" opacity=".55" />
      </g>
    </g>

    <!-- ─────────────────────────── FISH ────────────────────────── -->
    <!-- Rig remap: .pcm-ear-* are pectoral fins, .pcm-paw-* are pelvic fins.
         The head circle and body ellipse share a fill and overlap, so they
         read as one teardrop silhouette rather than a head on a body. -->
    <g class="pcm" data-species="Fish"
      style="--pcm-fur:#FB8C4A;--pcm-dark:#E0662B;--pcm-belly:#FFE7CE;--pcm-inner:#FFD9A8;--pcm-ink:#3B2E26;">
      <!-- Front-facing veiltail goldfish. The previous attempt was drawn
           face-on but with the caudal fin poking out to ONE side, which is the
           view you would never see it from, and it read as a lump rather than
           a tail. Here the tail sits symmetrically BEHIND the body and shows as
           flowing lobes on both flanks — a shape that makes sense head-on. The
           head circle is also fully contained inside the body ellipse now, so
           the silhouette is one clean egg instead of two mismatched circles. -->
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="38" ry="6" />
      <g class="pcm-tail">
        <path d="M100 122 C74 114 40 118 24 138 C40 162 76 164 100 148 Z" fill="var(--pcm-dark)" opacity=".92" />
        <path d="M100 122 C126 114 160 118 176 138 C160 162 124 164 100 148 Z" fill="var(--pcm-dark)" opacity=".92" />
        <path d="M84 124 L40 124 M86 134 L34 140 M88 144 L48 154" fill="none" stroke="var(--pcm-fur)"
          stroke-width="2" opacity=".45" />
        <path d="M116 124 L160 124 M114 134 L166 140 M112 144 L152 154" fill="none" stroke="var(--pcm-fur)"
          stroke-width="2" opacity=".45" />
      </g>
      <g class="pcm-body">
        <!-- Dorsal fin, on the back above the body line. -->
        <path d="M72 74 Q100 26 128 76 Q100 58 72 74 Z" fill="var(--pcm-dark)" />
        <ellipse cx="100" cy="114" rx="48" ry="46" fill="var(--pcm-fur)" />
        <ellipse cx="100" cy="132" rx="30" ry="26" fill="var(--pcm-belly)" opacity=".75" />
        <circle cx="132" cy="102" r="6" fill="var(--pcm-inner)" opacity=".6" />
        <circle cx="140" cy="124" r="4.5" fill="var(--pcm-inner)" opacity=".6" />
        <circle cx="66" cy="106" r="5" fill="var(--pcm-inner)" opacity=".6" />
      </g>
      <!-- Rig remap: .pcm-ear-* are the gill covers, so the ear-flop keyframe
           reads as a gill flutter. -->
      <g class="pcm-ear pcm-ear-l">
        <path d="M70 100 q-9 18 0 36" fill="none" stroke="var(--pcm-dark)" stroke-width="4" stroke-linecap="round"
          opacity=".5" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <path d="M130 100 q9 18 0 36" fill="none" stroke="var(--pcm-dark)" stroke-width="4" stroke-linecap="round"
          opacity=".5" />
      </g>
      <!-- Head circle sits wholly inside the body ellipse and shares its fill,
           so the sway moves the face without ever exposing an edge. -->
      <g class="pcm-head">
        <circle cx="100" cy="106" r="38" fill="var(--pcm-fur)" />
        <ellipse class="pcm-blush" cx="70" cy="116" rx="10" ry="6.5" fill="#FF7A5C" opacity=".45" />
        <ellipse class="pcm-blush" cx="130" cy="116" rx="10" ry="6.5" fill="#FF7A5C" opacity=".45" />
        <g class="pcm-eye pcm-eye-l">
          <circle cx="82" cy="96" r="14" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="82" cy="97" r="7.2" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="83.5" cy="95.2" r="2" fill="#FFFFFF" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <circle cx="118" cy="96" r="14" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="118" cy="97" r="7.2" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="119.5" cy="95.2" r="2" fill="#FFFFFF" />
        </g>
        <!-- A small mouth low on the face. The old version put a big pale disc
             dead centre, which read as a snout on a mammal, not a fish. -->
        <g class="pcm-muzzle">
          <path d="M92 126 Q100 134 108 126 Q100 130 92 126 Z" fill="var(--pcm-ink)" opacity=".75" />
          <ellipse cx="100" cy="124" rx="7" ry="4.5" fill="var(--pcm-dark)" opacity=".45" />
        </g>
      </g>
      <circle class="pcm-bubble" cx="152" cy="62" r="4" fill="#FFFFFF" opacity=".45" />
      <circle class="pcm-bubble" cx="164" cy="44" r="2.6" fill="#FFFFFF" opacity=".4" />
      <circle class="pcm-bubble" cx="144" cy="36" r="3.2" fill="#FFFFFF" opacity=".35" />
      <!-- Rig remap: .pcm-paw-* are the pectoral fins, symmetric about the
           body's centre line so the mirrored eye-cover offset lands both of
           them on their own eye. -->
      <g class="pcm-paw pcm-paw-l">
        <path d="M80 136 q-18 6 -16 22 q13 6 21 -9 z" fill="var(--pcm-fur)" stroke="var(--pcm-dark)"
          stroke-width="2" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <path d="M120 136 q18 6 16 22 q-13 6 -21 -9 z" fill="var(--pcm-fur)" stroke="var(--pcm-dark)"
          stroke-width="2" />
      </g>
    </g>

    <!-- ──────────────────────── SMALL PET ──────────────────────── -->
    <g class="pcm" data-species="Small Pet"
      style="--pcm-fur:#F2C94C;--pcm-dark:#D4A72C;--pcm-belly:#FFF7E0;--pcm-inner:#FFB3A0;--pcm-ink:#3B2E26;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="50" ry="8" />
      <g class="pcm-tail">
        <circle cx="144" cy="166" r="8" fill="var(--pcm-dark)" />
      </g>
      <!-- Hind legs. Painted BEFORE the body so the body hides their inner
           half and only the haunch and foot show at each flank — which is
           what a seated quadruped actually looks like, and means the animal
           still has four legs once .is-shy lifts the front pair to its eyes.
           Drawn in --pcm-dark to sit back in shadow. -->
      <g class="pcm-hind">
        <ellipse cx="73" cy="174" rx="11" ry="12" fill="var(--pcm-dark)" />
        <ellipse cx="127" cy="174" rx="11" ry="12" fill="var(--pcm-dark)" />
        <ellipse cx="67" cy="186" rx="9" ry="6" fill="var(--pcm-dark)" />
        <ellipse cx="133" cy="186" rx="9" ry="6" fill="var(--pcm-dark)" />
      </g>
      <g class="pcm-body">
        <ellipse cx="100" cy="152" rx="44" ry="38" fill="var(--pcm-fur)" />
        <ellipse cx="100" cy="160" rx="26" ry="26" fill="var(--pcm-belly)" />
      </g>
      <g class="pcm-ear pcm-ear-l">
        <circle cx="62" cy="42" r="16" fill="var(--pcm-dark)" />
        <circle cx="63" cy="43" r="8.5" fill="var(--pcm-inner)" opacity=".6" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <circle cx="138" cy="42" r="16" fill="var(--pcm-dark)" />
        <circle cx="137" cy="43" r="8.5" fill="var(--pcm-inner)" opacity=".6" />
      </g>
      <g class="pcm-head">
        <circle cx="100" cy="78" r="45" fill="var(--pcm-fur)" />
        <ellipse cx="63" cy="96" rx="17" ry="14" fill="var(--pcm-fur)" />
        <ellipse cx="137" cy="96" rx="17" ry="14" fill="var(--pcm-fur)" />
        <ellipse class="pcm-blush" cx="62" cy="98" rx="10" ry="6.5" fill="var(--pcm-inner)" opacity=".55" />
        <ellipse class="pcm-blush" cx="138" cy="98" rx="10" ry="6.5" fill="var(--pcm-inner)" opacity=".55" />
        <g class="pcm-eye pcm-eye-l">
          <circle cx="83" cy="73" r="12.5" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="83" cy="74" r="6.6" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="84.2" cy="72.5" r="1.7" fill="#FFFFFF" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <circle cx="117" cy="73" r="12.5" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="117" cy="74" r="6.6" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="118.2" cy="72.5" r="1.7" fill="#FFFFFF" />
        </g>
        <g class="pcm-muzzle">
          <ellipse cx="100" cy="102" rx="18" ry="12" fill="var(--pcm-belly)" />
          <ellipse cx="100" cy="95" rx="5.5" ry="4" fill="var(--pcm-ink)" />
          <path d="M100 99 v4 M92 103 q4 5 8 0 M100 103 q4 5 8 0" fill="none" stroke="var(--pcm-ink)"
            stroke-width="2.1" stroke-linecap="round" />
          <rect x="96.2" y="107" width="3.4" height="7" rx="1.2" fill="#FFFFFF" />
          <rect x="100.4" y="107" width="3.4" height="7" rx="1.2" fill="#FFFFFF" />
        </g>
      </g>
      <g class="pcm-paw pcm-paw-l">
        <ellipse cx="78" cy="180" rx="14" ry="10" fill="var(--pcm-belly)" />
        <circle cx="78" cy="180" r="3.2" fill="var(--pcm-inner)" opacity=".55" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <ellipse cx="122" cy="180" rx="14" ry="10" fill="var(--pcm-belly)" />
        <circle cx="122" cy="180" r="3.2" fill="var(--pcm-inner)" opacity=".55" />
      </g>
    </g>

    <!-- ───────────────────────── REPTILE ───────────────────────── -->
    <!-- Rig remap: .pcm-ear-* are the brow ridges (geckos have no pinnae), so
         the ear-flop keyframe reads as an eyebrow raise. -->
    <g class="pcm" data-species="Reptile"
      style="--pcm-fur:#6FCF97;--pcm-dark:#45A97A;--pcm-belly:#E9FBF1;--pcm-inner:#FFD166;--pcm-ink:#26382F;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="52" ry="8" />
      <g class="pcm-tail">
        <path d="M136 158 q40 6 44 -22 q4 -22 -14 -22 q-10 0 -8 12 q2 12 -8 14 q-10 2 -14 -6 z"
          fill="var(--pcm-dark)" />
        <circle cx="160" cy="122" r="4" fill="var(--pcm-inner)" opacity=".7" />
        <circle cx="170" cy="140" r="3.4" fill="var(--pcm-inner)" opacity=".7" />
      </g>
      <!-- Hind legs. Painted BEFORE the body so the body hides their inner
           half and only the haunch and foot show at each flank — which is
           what a seated quadruped actually looks like, and means the animal
           still has four legs once .is-shy lifts the front pair to its eyes.
           Drawn in --pcm-dark to sit back in shadow. -->
      <g class="pcm-hind">
        <ellipse cx="60" cy="176" rx="16" ry="9" fill="var(--pcm-dark)" />
        <ellipse cx="140" cy="176" rx="16" ry="9" fill="var(--pcm-dark)" />
        <ellipse cx="46" cy="185" rx="10" ry="6.5" fill="var(--pcm-dark)" />
        <ellipse cx="154" cy="185" rx="10" ry="6.5" fill="var(--pcm-dark)" />
        <path d="M38 181 l-8 -3 M37 186 l-9 1 M39 190 l-7 4" fill="none" stroke="var(--pcm-dark)"
          stroke-width="3.4" stroke-linecap="round" />
        <path d="M162 181 l8 -3 M163 186 l9 1 M161 190 l7 4" fill="none" stroke="var(--pcm-dark)"
          stroke-width="3.4" stroke-linecap="round" />
      </g>
      <g class="pcm-body">
        <ellipse cx="100" cy="150" rx="45" ry="39" fill="var(--pcm-fur)" />
        <ellipse cx="100" cy="159" rx="26" ry="26" fill="var(--pcm-belly)" />
        <circle cx="70" cy="136" r="5" fill="var(--pcm-inner)" opacity=".7" />
        <circle cx="130" cy="140" r="4.4" fill="var(--pcm-inner)" opacity=".7" />
        <circle cx="86" cy="122" r="3.6" fill="var(--pcm-inner)" opacity=".7" />
      </g>
      <g class="pcm-ear pcm-ear-l">
        <path d="M62 50 q18 -14 34 -4" fill="none" stroke="var(--pcm-dark)" stroke-width="7" stroke-linecap="round" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <path d="M138 50 q-18 -14 -34 -4" fill="none" stroke="var(--pcm-dark)" stroke-width="7"
          stroke-linecap="round" />
      </g>
      <g class="pcm-head">
        <ellipse cx="100" cy="80" rx="47" ry="42" fill="var(--pcm-fur)" />
        <ellipse class="pcm-blush" cx="64" cy="94" rx="10" ry="6" fill="#FF9E7A" opacity=".4" />
        <ellipse class="pcm-blush" cx="136" cy="94" rx="10" ry="6" fill="#FF9E7A" opacity=".4" />
        <g class="pcm-eye pcm-eye-l">
          <circle cx="82" cy="74" r="13.5" fill="var(--pcm-inner)" />
          <ellipse class="pcm-pupil" cx="82" cy="75" rx="4" ry="9" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="82" cy="72.2" r="1.6" fill="#FFFFFF" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <circle cx="118" cy="74" r="13.5" fill="var(--pcm-inner)" />
          <ellipse class="pcm-pupil" cx="118" cy="75" rx="4" ry="9" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="118" cy="72.2" r="1.6" fill="#FFFFFF" />
        </g>
        <g class="pcm-muzzle">
          <ellipse cx="100" cy="102" rx="21" ry="13" fill="var(--pcm-belly)" opacity=".85" />
          <circle cx="94" cy="96" r="2.4" fill="var(--pcm-ink)" />
          <circle cx="106" cy="96" r="2.4" fill="var(--pcm-ink)" />
          <path d="M86 104 q14 10 28 0" fill="none" stroke="var(--pcm-ink)" stroke-width="2.4"
            stroke-linecap="round" />
        </g>
      </g>
      <g class="pcm-paw pcm-paw-l">
        <ellipse cx="70" cy="180" rx="17" ry="11" fill="var(--pcm-dark)" />
        <path d="M56 176 l-8 -4 M55 181 l-9 1 M57 186 l-7 5" fill="none" stroke="var(--pcm-dark)" stroke-width="4"
          stroke-linecap="round" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <ellipse cx="130" cy="180" rx="17" ry="11" fill="var(--pcm-dark)" />
        <path d="M144 176 l8 -4 M145 181 l9 1 M143 186 l7 5" fill="none" stroke="var(--pcm-dark)" stroke-width="4"
          stroke-linecap="round" />
      </g>
    </g>

    <!-- ─────────────────────────── OTHER ───────────────────────── -->
    <!-- The catch-all critter: deliberately not any real species, with one
         floppy and one perky ear so it still reads as a character rather than
         a placeholder. -->
    <g class="pcm" data-species="Other"
      style="--pcm-fur:#9AA7F0;--pcm-dark:#7280DA;--pcm-belly:#FFF6E8;--pcm-inner:#FFC2C2;--pcm-ink:#2E3350;">
      <ellipse class="pcm-shadow" cx="100" cy="191" rx="52" ry="8" />
      <g class="pcm-tail">
        <path d="M138 156 q34 4 34 -20 q0 -16 -14 -16 q-12 0 -12 12 q0 8 8 8 q6 0 6 -6" fill="none"
          stroke="var(--pcm-dark)" stroke-width="10" stroke-linecap="round" />
      </g>
      <!-- Hind legs. Painted BEFORE the body so the body hides their inner
           half and only the haunch and foot show at each flank — which is
           what a seated quadruped actually looks like, and means the animal
           still has four legs once .is-shy lifts the front pair to its eyes.
           Drawn in --pcm-dark to sit back in shadow. -->
      <g class="pcm-hind">
        <ellipse cx="72" cy="171" rx="15" ry="18" fill="var(--pcm-dark)" />
        <ellipse cx="128" cy="171" rx="15" ry="18" fill="var(--pcm-dark)" />
        <ellipse cx="62" cy="187" rx="11" ry="7.5" fill="var(--pcm-dark)" />
        <ellipse cx="138" cy="187" rx="11" ry="7.5" fill="var(--pcm-dark)" />
      </g>
      <g class="pcm-body">
        <ellipse cx="100" cy="150" rx="44" ry="40" fill="var(--pcm-fur)" />
        <ellipse cx="100" cy="159" rx="26" ry="27" fill="var(--pcm-belly)" />
      </g>
      <g class="pcm-ear pcm-ear-l">
        <ellipse cx="58" cy="60" rx="14" ry="28" transform="rotate(-26 58 60)" fill="var(--pcm-dark)" />
        <ellipse cx="60" cy="62" rx="7" ry="17" transform="rotate(-26 60 62)" fill="var(--pcm-inner)" opacity=".5" />
      </g>
      <g class="pcm-ear pcm-ear-r">
        <ellipse cx="138" cy="36" rx="13" ry="26" transform="rotate(14 138 36)" fill="var(--pcm-dark)" />
        <ellipse cx="138" cy="38" rx="6.5" ry="16" transform="rotate(14 138 38)" fill="var(--pcm-inner)" opacity=".5" />
      </g>
      <g class="pcm-head">
        <circle cx="100" cy="78" r="45" fill="var(--pcm-fur)" />
        <ellipse class="pcm-blush" cx="65" cy="94" rx="10.5" ry="6.5" fill="var(--pcm-inner)" opacity=".6" />
        <ellipse class="pcm-blush" cx="135" cy="94" rx="10.5" ry="6.5" fill="var(--pcm-inner)" opacity=".6" />
        <g class="pcm-eye pcm-eye-l">
          <circle cx="83" cy="73" r="13.5" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="83" cy="74" r="7" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="84.4" cy="72.3" r="1.9" fill="#FFFFFF" />
        </g>
        <g class="pcm-eye pcm-eye-r">
          <circle cx="117" cy="73" r="13.5" fill="#FFFDF7" />
          <circle class="pcm-pupil" cx="117" cy="74" r="7" fill="var(--pcm-ink)" />
          <circle class="pcm-glint" cx="118.4" cy="72.3" r="1.9" fill="#FFFFFF" />
        </g>
        <g class="pcm-muzzle">
          <ellipse cx="100" cy="103" rx="21" ry="14" fill="var(--pcm-belly)" />
          <ellipse cx="100" cy="95" rx="6.5" ry="4.5" fill="var(--pcm-ink)" />
          <path d="M100 100 v5 M90 105 q5 6 10 0 M100 105 q5 6 10 0" fill="none" stroke="var(--pcm-ink)"
            stroke-width="2.3" stroke-linecap="round" />
        </g>
      </g>
      <g class="pcm-paw pcm-paw-l">
        <ellipse cx="73" cy="181" rx="16" ry="11.5" fill="var(--pcm-belly)" />
        <circle cx="73" cy="181" r="3.6" fill="var(--pcm-inner)" opacity=".6" />
      </g>
      <g class="pcm-paw pcm-paw-r">
        <ellipse cx="127" cy="181" rx="16" ry="11.5" fill="var(--pcm-belly)" />
        <circle cx="127" cy="181" r="3.6" fill="var(--pcm-inner)" opacity=".6" />
      </g>
    </g>

  </svg>
</div>
