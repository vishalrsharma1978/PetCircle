<!-- Playful sign-in. Two compositions from one markup tree:
     - >=1024px: a full-viewport diorama (left) beside a full-height form
       panel (right). auth_scene.js builds the diorama's layers and drops the
       character in; nothing about the scene is authored here, so the sign-up
       page shares the identical scene definition.
     - <1024px: the stage collapses to a 210px animated banner above the
       existing v2 card treatment, which is the mobile experience this page
       already had.
     Everything interactive lives in the panel (not overlaid on the stage) so
     there is exactly one copy of each control at both breakpoints. -->
<div id="view-public-login-v2" class="view-section hidden av2-shell w-full flex-col lg:flex-row"
  style="--av2-accent: #F2A93B;">

  <!-- Ambient drifting paws: the mobile backdrop. auth_scene.css hides these
       at >=1024px, where the diorama is the backdrop instead. -->
  <div class="av2-paw-field" aria-hidden="true">
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
    <svg viewBox="0 0 24 24"><use href="#icon-paw" xlink:href="#icon-paw"></use></svg>
  </div>

  <!-- ── LEFT: the diorama (populated by av2SceneBuild) ───────────────── -->
  <div class="av2-stage av2-split-stage" id="lp2-stage"></div>

  <!-- ── RIGHT: sign-in form ──────────────────────────────────────────── -->
  <div class="av2-panel av2-split-panel w-full flex flex-col justify-center p-5 sm:p-8 lg:px-12 lg:py-10">
    <div class="w-full max-w-md mx-auto av2-card p-6 sm:p-8">

      <div class="flex items-center justify-between mb-8">
        <div class="flex items-center gap-2">
          <svg class="w-7 h-7" viewBox="0 0 24 24" style="fill: var(--av2-accent, #F2A93B);" aria-hidden="true">
            <use href="#icon-paw" xlink:href="#icon-paw"></use>
          </svg>
          <span class="av2-heading text-xl text-[color:var(--charcoal,#2B2420)]">PawCircle</span>
        </div>
        <span class="av2-try-link" onclick="closeAuthV2Login()">
          <svg class="w-3.5 h-3.5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.5"
            aria-hidden="true">
            <path d="M15 18l-6-6 6-6" stroke-linecap="round" stroke-linejoin="round" />
          </svg>
          Classic view
        </span>
      </div>

      <h1 id="lp2-headline" class="av2-heading text-3xl sm:text-4xl leading-tight text-[color:var(--charcoal,#2B2420)]">
        Welcome back!</h1>
      <p class="text-sm mt-2 mb-6" style="color:#8a7a5f;">Your pack has been waiting by the door.</p>

      <div id="lp2-error" class="hidden mb-4 bg-red-50 border-l-4 border-red-400 p-3 rounded-lg text-sm text-red-700">
      </div>

      <form id="lp2-login-form" novalidate autocomplete="off" class="space-y-5">

        <div class="av2-field">
          <label for="lp2-email">Email address</label>
          <div class="av2-input-wrap mt-1.5">
            <svg class="av2-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
              aria-hidden="true">
              <rect x="2.5" y="5" width="19" height="14" rx="2.5" />
              <path class="av2-icon-flap" d="M2.5 7 L12 14 L21.5 7" stroke-linecap="round" stroke-linejoin="round" />
            </svg>
            <input type="email" id="lp2-email" required autocomplete="off" autocapitalize="off" autocorrect="off"
              spellcheck="false" placeholder="you@example.com"
              class="av2-input w-full px-4 py-3 text-sm text-gray-900">
          </div>
        </div>

        <div class="av2-field">
          <div class="flex items-center justify-between mb-1.5">
            <label for="lp2-password" class="!mb-0">Password</label>
            <button type="button"
              onclick="av2StopHeadlineRotation(); switchView('view-forgot-password'); showForgotStepRequest();"
              class="text-xs font-extrabold" style="color: var(--av2-accent, var(--marigold, #F2A93B));">Forgot
              password?</button>
          </div>
          <div class="av2-input-wrap">
            <svg class="av2-field-icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.9"
              aria-hidden="true">
              <path class="av2-icon-shackle" d="M8 10.5 V7.5 a4 4 0 0 1 8 0 V10.5" stroke-linecap="round" />
              <rect x="4.5" y="10.5" width="15" height="10" rx="2.6" />
            </svg>
            <input type="password" id="lp2-password" required autocomplete="new-password" placeholder="••••••••"
              class="av2-input w-full px-4 py-3 pr-11 text-sm text-gray-900">
            <button type="button" id="lp2-eyeToggle"
              class="absolute right-3 top-1/2 -translate-y-1/2 text-gray-400 hover:text-gray-600 transition-colors"
              aria-label="Show password">
              <i data-lucide="eye" id="lp2-eyeIcon" class="w-5 h-5"></i>
            </button>
          </div>
        </div>

        <button type="submit" id="lp2-submit-btn" class="av2-btn w-full py-3.5 text-white text-base"
          style="background: var(--av2-accent, var(--marigold, #F2A93B));">
          Sign in 🐾
        </button>
      </form>

      <!-- Decorative pet picker: recolours the whole scene and swaps the
           character. Not a form field — the classic page has the same
           preview-only control (handleLoginPetTypeChange). -->
      <div class="mt-7">
        <label class="av2-heading text-xs mb-2 block text-[color:var(--charcoal,#2B2420)]">Who's keeping you
          company?</label>
        <div id="lp2-pet-pills" class="av2-pill-row"></div>
      </div>

      <p class="text-center text-sm mt-6" style="color:#8a7a5f;">
        New to PawCircle?
        <a onclick="closeAuthV2Login('view-signup-v2')"
          style="cursor:pointer; color:var(--marigold-dark,#C9851F); font-weight:800;">Create an account</a>
      </p>
    </div>
  </div>
</div>
