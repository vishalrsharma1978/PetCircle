<!-- Playful sign-up. Same two-composition approach as public_login_v2.php,
     with the form split into four wizard steps.

     On >=1024px exactly one .av2-step is visible at a time and the paw trail
     tracks progress. Below 1024px auth_scene.css shows every step at once and
     hides the trail and the Back/Next controls, which renders as the single
     scrolling form this page already had on mobile.

     One consequence worth knowing: because all four steps exist in the DOM at
     both breakpoints, there is one input per field, one set of ids, and one
     submit handler. The wizard is a presentation layer over an ordinary form,
     not a separate flow — the final submit posts the same single
     api("signup", {...}) payload the classic page does. -->
<div id="view-signup-v2" class="view-section hidden av2-shell w-full flex-col lg:flex-row"
  style="--av2-accent: #F2A93B;">

  <div class="av2-paw-field" aria-hidden="true">
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
  </div>

  <!-- ── LEFT: the night-den diorama (populated by av2SceneBuild) ─────── -->
  <div class="av2-stage av2-stage--den av2-split-stage" id="su2-stage"></div>

  <!-- ── RIGHT: the wizard ────────────────────────────────────────────── -->
  <div class="av2-panel av2-split-panel w-full flex flex-col justify-center p-5 sm:p-8 lg:px-12 lg:py-10">
    <div class="w-full max-w-md mx-auto av2-card p-6 sm:p-8">

      <div class="flex items-center justify-between mb-6">
        <div class="flex items-center gap-2">
          <svg class="w-7 h-7" viewBox="0 0 24 24" style="fill: var(--av2-accent, #F2A93B);" aria-hidden="true">
            <use href="#icon-paw" xlink:href="#icon-paw"></use>
          </svg>
          <span class="av2-heading text-xl text-[color:var(--charcoal,#2B2420)]">PawCircle</span>
        </div>
        <span class="av2-try-link" onclick="closeAuthV2Signup()">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            aria-hidden="true">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Classic view
        </span>
      </div>

      <!-- Paw-print progress trail (desktop only). Built by av2RenderTrail(). -->
      <div id="su2-trail" class="av2-trail" role="progressbar" aria-valuemin="1" aria-valuemax="4" aria-valuenow="1"
        aria-label="Sign-up progress"></div>

      <div id="su2-error" class="hidden mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded-lg text-sm text-red-700">
      </div>

      <form id="su2-form" novalidate>

        <!-- ── Step 1: the pet ──────────────────────────────────────── -->
        <section class="av2-step is-active" data-step="1">
          <div class="av2-step-caption">Step 1 of 4</div>
          <h1 class="av2-heading text-3xl mb-1.5 text-[color:var(--charcoal,#2B2420)]">Who's your pet?</h1>
          <p class="text-sm mb-6" style="color:#8a7a5f;">Pick their kind and watch them show up next door.</p>

          <div class="space-y-5">
            <div class="av2-field">
              <label for="su2-name">Pet name</label>
              <input type="text" id="su2-name" required placeholder="What do you call them?" autocomplete="off"
                class="av2-input w-full px-4 py-3 text-sm mt-1.5 text-gray-900">
            </div>

            <div class="av2-field">
              <label>What kind of pet?</label>
              <div id="su2-pet-tiles" class="grid grid-cols-4 sm:grid-cols-7 gap-2 mt-1.5"></div>
              <!-- The real form field driving signup — visually replaced by the
                   sticker tiles above, kept for actual submission and for
                   required-field validation. -->
              <select id="su2-pet-type" required class="sr-only" tabindex="-1" aria-hidden="true">
                <option value="">Select Pet Type...</option>
                <option value="Dog">Dog</option>
                <option value="Cat">Cat</option>
                <option value="Bird">Bird</option>
                <option value="Fish">Fish</option>
                <option value="Small Pet">Small Pet</option>
                <option value="Reptile">Reptile</option>
                <option value="Other">Other</option>
              </select>
            </div>
          </div>

          <div class="av2-nav-row">
            <button type="button" class="av2-btn av2-btn-next flex-1 py-3 text-white text-base"
              style="background: var(--av2-accent, var(--marigold, #F2A93B));" onclick="av2WizardNext()">
              Next 🐾
            </button>
          </div>
        </section>

        <!-- ── Step 2: breed + parent ───────────────────────────────── -->
        <section class="av2-step" data-step="2">
          <div class="av2-step-caption">Step 2 of 4</div>
          <h1 class="av2-heading text-3xl mb-1.5 text-[color:var(--charcoal,#2B2420)]">Tell us more</h1>
          <p class="text-sm mb-6" style="color:#8a7a5f;">Breed helps us match you with the right playmates.</p>

          <div class="space-y-5">
            <div class="av2-field">
              <label for="su2-breed">Breed</label>
              <select id="su2-breed" required onchange="av2ToggleCustomBreedInput()"
                class="av2-input w-full px-4 py-3 text-sm mt-1.5 text-gray-900">
                <option value="">Select Breed...</option>
              </select>
            </div>

            <div id="su2-custom-breed-wrap" class="av2-field" style="display:none;">
              <label for="su2-custom-breed">Add breed</label>
              <input type="text" id="su2-custom-breed" placeholder="Enter breed name"
                class="av2-input w-full px-4 py-3 text-sm mt-1.5 text-gray-900">
            </div>

            <div class="av2-field">
              <label for="su2-parent-name">Your name</label>
              <input type="text" id="su2-parent-name" required placeholder="And you?" autocomplete="name"
                class="av2-input w-full px-4 py-3 text-sm mt-1.5 text-gray-900">
            </div>
          </div>

          <div class="av2-nav-row">
            <button type="button" class="av2-btn-ghost" onclick="av2WizardBack()">Back</button>
            <button type="button" class="av2-btn av2-btn-next flex-1 py-3 text-white text-base"
              style="background: var(--av2-accent, var(--marigold, #F2A93B));" onclick="av2WizardNext()">
              Next 🐾
            </button>
          </div>
        </section>

        <!-- ── Step 3: email ────────────────────────────────────────── -->
        <section class="av2-step" data-step="3">
          <div class="av2-step-caption">Step 3 of 4</div>
          <h1 class="av2-heading text-3xl mb-1.5 text-[color:var(--charcoal,#2B2420)]">How do we reach you?</h1>
          <p class="text-sm mb-6" style="color:#8a7a5f;">Where playdate invites and pack news should land.</p>

          <div class="av2-field">
            <label for="su2-email">Paw parent email</label>
            <div class="av2-input-wrap mt-1.5">
              <svg class="av2-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                aria-hidden="true">
                <rect x="2.5" y="5" width="19" height="14" rx="2.5" />
                <path class="av2-icon-flap" d="M2.5 7 L12 14 L21.5 7" stroke-linecap="round" stroke-linejoin="round" />
              </svg>
              <input type="email" id="su2-email" required placeholder="you@example.com" autocomplete="email"
                class="av2-input w-full px-4 py-3 text-sm text-gray-900">
            </div>
          </div>

          <div class="av2-nav-row">
            <button type="button" class="av2-btn-ghost" onclick="av2WizardBack()">Back</button>
            <button type="button" class="av2-btn av2-btn-next flex-1 py-3 text-white text-base"
              style="background: var(--av2-accent, var(--marigold, #F2A93B));" onclick="av2WizardNext()">
              Next 🐾
            </button>
          </div>
        </section>

        <!-- ── Step 4: password ─────────────────────────────────────── -->
        <section class="av2-step" data-step="4">
          <div class="av2-step-caption">Step 4 of 4</div>
          <h1 class="av2-heading text-3xl mb-1.5 text-[color:var(--charcoal,#2B2420)]">Lock it up</h1>
          <p class="text-sm mb-6" style="color:#8a7a5f;">Don't worry — they'll cover their eyes while you type.</p>

          <div class="av2-field">
            <label for="su2-password">Password</label>
            <div class="av2-input-wrap mt-1.5">
              <svg class="av2-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
                aria-hidden="true">
                <path class="av2-icon-shackle" d="M8 10.5 V7.5 a4 4 0 0 1 8 0 V10.5" stroke-linecap="round" />
                <rect x="4.5" y="10.5" width="15" height="10" rx="2.6" />
              </svg>
              <input id="su2-password" type="password" required placeholder="Minimum 10 characters"
                autocomplete="new-password" class="av2-input w-full px-4 py-3 pr-11 text-sm text-gray-900">
              <button type="button" id="su2-eyeToggle"
                class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
                aria-label="Show password">
                <i data-lucide="eye" id="su2-eyeIcon" class="w-5 h-5"></i>
              </button>
            </div>
            <p class="text-xs mt-1.5" style="color:#a89570;">Minimum 10 characters. Use letters, numbers, and a dash of
              personality.</p>
          </div>

          <div class="av2-nav-row">
            <button type="button" class="av2-btn-ghost" onclick="av2WizardBack()">Back</button>
            <button type="submit" class="av2-btn av2-btn-submit flex-1 justify-center py-3 text-white text-base"
              id="su2-submit-btn" style="background: var(--av2-accent, var(--marigold, #F2A93B));">
              Join the pack 🐾
            </button>
          </div>
        </section>

      </form>

      <p class="text-center text-sm mt-6" style="color:#8a7a5f;">
        Already part of the pack?
        <a onclick="closeAuthV2Signup('view-public-login-v2')"
          style="cursor:pointer; color:var(--marigold-dark,#C9851F); font-weight:800;">Sign in</a>
      </p>
    </div>
  </div>
</div>
