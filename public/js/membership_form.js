// ==================== MULTI-STEP FORM ====================




// Tracks whether the membership form is currently rendered in its compact
// ("just the essentials") variant — used so validation only enforces the
// smaller set of required fields shown in that mode.






// Friendly labels for the required membership fields, used in validation messages.







function renderStepIndicator() {
  let html = '<div class="flex items-start w-full">';
  STEPS_CONFIG.forEach((step, i) => {
    const num = i + 1;
    const isDone = num < currentStep;
    const isActive = num === currentStep;
    const circleClass = isDone
      ? "completed"
      : isActive
        ? "active"
        : "inactive";
    const labelClass = isDone
      ? "completed"
      : isActive
        ? "active"
        : "inactive";
    html += `<div class="flex flex-col items-center" style="min-width:0; flex:1">
                    <div class="step-circle ${circleClass}">
                        ${isDone ? '<svg xmlns="http://www.w3.org/2000/svg" width="16" height="16" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : num}
                    </div>
                    <div class="step-label ${labelClass} hidden sm:block" style="font-size:10px; white-space:pre-line; text-align:center; margin-top:4px">${step.label}</div>
                </div>`;
    if (i < STEPS_CONFIG.length - 1) {
      html += `<div class="step-line ${isDone ? "completed" : ""}" style="margin-top:18px"></div>`;
    }
  });
  html += "</div>";
  return html;
}



window.updateThemeBackgrounds = function (religion) {
  const pastelBg = getPastelBgClass(religion);
  if (!pastelBg) return;

  const bClasses = pastelBg.split(' ');

  // Target the body itself so the entire page background is set seamlessly
  document.body.className = document.body.className.replace(/\bbg-(gray|orange|green|blue|purple|yellow|red|brand)-50\b/g, '').trim();
  document.body.className = document.body.className.replace(/\bdark:bg-gray-9[0-5]0\b/g, '').trim();
  bClasses.forEach(c => document.body.classList.add(c));
  document.body.classList.add('transition-colors', 'duration-500');

  // Clear conflicting bg-color classes from the specific view sections so body background shows through
  const views = ['view-member-dashboard', 'view-social-feed', 'view-family-tree', 'view-horoscope'];
  views.forEach(id => {
    const el = document.getElementById(id);
    if (el) {
      el.className = el.className.replace(/\bbg-(gray|orange|green|blue|purple|yellow|red|brand)-50\b/g, '').trim();
      el.className = el.className.replace(/\bdark:bg-gray-9[0-5]0\b/g, '').trim();
    }
  });
};







// opts.compact = true  → single-page form asking only the key info (shown
//   right after signup; everything else is optional and can be finished later
//   from the profile / "complete membership" notification).
// opts.compact = false → the full single-page form with every detail.
function renderMembershipForm(user, opts = {}) {
  const compact = !!opts.compact;
  membershipFormIsCompact = compact;
  const container = document.getElementById("membership-form-container");
  const bgImages = {
    Hindu: "img/bg_hindu.png",
    Muslim: "img/bg_muslim.png",
    Sikh: "img/bg_sikh.png",
    Christian: "img/bg_christian.png",
    Buddhist: "img/bg_buddhist.png",
    Jain: "img/bg_jain.png",
    Parsi: "img/bg_parsi.png",
  };
  const bgUrl =
    user.religion && bgImages[user.religion]
      ? `url(${bgImages[user.religion]})`
      : "";
  const darkColorClass = getDarkColorClass(user.religion);

  // Populate Pack dropdown based on religion if available
  setTimeout(() => {
    const relEl = document.getElementById("s2-religion");
    if (relEl && user.religion) {
      relEl.value = user.religion;
      updatePackOptions("s2-religion", "s2-Pack");
      const desired = (user.socialProfile && user.socialProfile.Pack) || user.Pack || "";
      if (desired) {
        applyStoredPackSelection("s2-Pack", desired, "s2-sub-Pack-field", "s2-sub-Pack");
      }
    }
  }, 150);

  const watermarkHtml = bgUrl
    ? `
                <div class="absolute inset-0 pointer-events-none opacity-25 mix-blend-multiply z-0" style="isolation: isolate;">
                    <div class="w-full h-full ${darkColorClass}"></div>
                    <div class="absolute inset-0 mix-blend-screen grayscale" style="background-image: ${bgUrl}; background-position: center 30%; background-size: cover; background-repeat: no-repeat;"></div>
                </div>`
    : "";

  const headerSubtitle = compact
    ? "Just the essentials to get you started — you can fill in the rest anytime."
    : "eCircle Pack Association — Complete your profile";
  const primaryLabel = compact
    ? '<i data-lucide="arrow-right" class="w-4 h-4"></i> Save &amp; Continue'
    : '<i data-lucide="send" class="w-4 h-4"></i> Save profile';

  container.innerHTML = `
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden relative">
                <!-- Header -->
                <div class="px-6 pt-6 pb-4 border-b border-gray-100 relative z-10">
                    <div class="flex items-start justify-between mb-1">
                        <div>
                            <h3 class="font-bold text-gray-900 text-lg" style="font-family:'DM Serif Display'">${compact ? "Set up your profile" : "Complete your profile"}</h3>
                            <p class="text-xs text-gray-500 mt-0.5">${headerSubtitle}</p>
                        </div>
                    </div>
                </div>

                <!-- Form body -->
                <div class="px-6 py-6">
                    ${compact ? `
                    <div class="mb-6 flex items-start gap-3 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                        <i data-lucide="info" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            We only need a few key details now. You can add the rest — occupation, skills, documents and more — anytime from your profile.
                        </p>
                    </div>` : `
                    <div class="mb-6 flex items-start gap-3 bg-blue-50 border border-blue-100 rounded-xl px-4 py-3">
                        <i data-lucide="shield" class="w-4 h-4 text-blue-600 mt-0.5 flex-shrink-0"></i>
                        <p class="text-xs text-blue-700 leading-relaxed">
                            <span class="font-semibold">Data Privacy Notice:</span> Information on this form is used solely for membership management and Pack outreach. Fields marked Optional are never mandatory. All data is protected under the <span class="font-semibold text-blue-800">DPDP Act, 2023</span>.
                        </p>
                    </div>`}

                    <!-- Form fields (single page) -->
                    <div id="form-steps-container">
                        ${compact ? renderKeyInfoFields(user) : renderAllSteps(user)}
                    </div>

                    <!-- Actions -->
                    <div class="mt-8 pt-5 border-t border-gray-100 flex flex-col gap-3">
                        <button id="btn-submit-membership" onclick="submitMembership()" class="flex items-center justify-center gap-2 px-5 py-3 text-sm font-bold text-white bg-brand-400 hover:bg-brand-300 rounded-xl transition-colors shadow-sm w-full">
                            ${primaryLabel}
                        </button>
                        <button onclick="skipMembership()" class="flex items-center justify-center gap-2 px-5 py-3 text-sm font-bold text-gray-700 bg-gray-100 hover:bg-gray-200 border border-gray-300 rounded-xl transition-colors w-full">
                            <i data-lucide="clock" class="w-4 h-4"></i> Skip for now — I'll finish this later
                        </button>
                    </div>
                </div>
            </div>`;

  // Render every section on one page (no step navigation).
  container.querySelectorAll(".form-step").forEach((p) => p.classList.add("active"));

  lucide.createIcons();
  bindGenderButtons(user.religion);
  bindInterestTags();
  if (typeof initPhoneMounts === "function") initPhoneMounts();

  // Clear the invalid highlight as soon as the user starts fixing a field.
  // Bound once per container so repeated renders don't stack listeners.
  if (container.dataset.invalidClearBound !== "1") {
    const clearInvalid = (e) => markFieldInvalid(e.target, false);
    container.addEventListener("input", clearInvalid);
    container.addEventListener("change", clearInvalid);
    container.dataset.invalidClearBound = "1";
  }

  // Pre-populate form fields from currentUserObj if available
  setTimeout(() => {
    const nameEl = document.getElementById("s1-name");
    if (nameEl && !nameEl.value && user.name) {
      nameEl.value = user.name;
    }
    const relEl = document.getElementById("s2-religion");
    if (relEl && user.religion) {
      relEl.value = user.religion;
      updatePackOptions("s2-religion", "s2-Pack");
    }
    const ageEl = document.getElementById("s4-age");
    const ageGroup = getUserAgeGroup(user);
    if (ageEl && ageGroup) {
      setSelectValueNormalized(ageEl, ageGroup);
    }
  }, 150);
}

// Compact, key-info-only version of the profile form shown right after signup.
// Uses the same field IDs as the full form so submitMembership() works unchanged;
// anything not present here is simply left for the user to complete later.
function renderKeyInfoFields(user) {
  return `
            <div class="form-step active" id="step-panel-1">
                <div class="section-header">
                    <div class="section-icon"><i data-lucide="user" class="w-4 h-4 text-brand-400"></i></div>
                    <div>
                        <div class="text-xs font-bold text-brand-400 tracking-widest uppercase">The essentials</div>
                        <div class="text-base font-bold text-gray-900">Tell us about you</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="user" class="icon w-4 h-4"></i>
                            <input type="text" id="s1-name" value="${user.name || ""}" required placeholder="As per official document"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Date of Birth <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="calendar" class="icon w-4 h-4"></i>
                            <input type="date" id="s1-dob"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300 focus:border-transparent">
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-2">Gender <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div class="flex gap-2 flex-wrap" id="gender-btn-group">
                        <button type="button" class="gender-btn" data-gender="Male" onclick="selectGender('Male')"><span>♂</span> Male</button>
                        <button type="button" class="gender-btn" data-gender="Female" onclick="selectGender('Female')"><span>♀</span> Female</button>
                        <button type="button" class="gender-btn" data-gender="Other" onclick="selectGender('Other')">Other</button>
                        <button type="button" class="gender-btn" data-gender="Prefer not to say" onclick="selectGender('Prefer not to say')">Prefer not to say</button>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Mobile Number <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div data-phone-mount="s1-phone" data-phone-placeholder="98765 43210"></div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Religion <span class="text-red-500">*</span></label>
                        <select id="s2-religion" required onchange="updatePackOptions('s2-religion','s2-Pack');updateSubPackOptions('s2-Pack','s2-sub-Pack-field','s2-sub-Pack')"
                            class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <option value="">Select Religion...</option>
                            <option ${user.religion === "Hindu" ? "selected" : ""}>Hindu</option>
                            <option ${user.religion === "Muslim" ? "selected" : ""}>Muslim</option>
                            <option ${user.religion === "Sikh" ? "selected" : ""}>Sikh</option>
                            <option ${user.religion === "Christian" ? "selected" : ""}>Christian</option>
                            <option ${user.religion === "Jain" ? "selected" : ""}>Jain</option>
                            <option ${user.religion === "Buddhist" ? "selected" : ""}>Buddhist</option>
                            <option ${user.religion === "Parsi" ? "selected" : ""}>Parsi</option>
                            <option ${user.religion === "Other" ? "selected" : ""}>Other</option>
                        </select>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Pack / Caste <span class="text-red-500">*</span></label>
                        <select id="s2-Pack" required onchange="updateSubPackOptions('s2-Pack','s2-sub-Pack-field','s2-sub-Pack')"
                            class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <option value="">Select Pack...</option>
                        </select>
                    </div>
                    <div id="s2-sub-Pack-field" class="hidden">
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Sub-Pack <span class="text-red-500">*</span></label>
                        <select id="s2-sub-Pack" disabled
                            class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <option value="">Select sub-Pack...</option>
                        </select>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4 mt-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Current City <span class="text-gray-400 font-normal">(optional)</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="building-2" class="icon w-4 h-4"></i>
                            <input type="text" id="s2-city" placeholder="e.g. Mumbai, Delhi, Bangalore"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Age Group <span class="text-gray-400 font-normal">(optional)</span></label>
                        <select id="s4-age"
                            class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <option value="">Select...</option>
                            <option>Under 18</option><option>18 – 25</option>
                            <option>26 – 40</option><option>41 – 60</option><option>60+</option>
                        </select>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-2">Primary Interests <span class="text-gray-400 font-normal">(optional)</span></label>
                    <div class="flex flex-wrap gap-2" id="interest-tags-container">
                        ${INTERESTS.map(
    (i) => `
                        <button type="button" class="interest-tag" data-interest="${i.label}" onclick="toggleInterest('${i.label}', this)">
                            ${i.icon} ${i.label}
                        </button>`,
  ).join("")}
                    </div>
                </div>
            </div>`;
}

function renderAllSteps(user) {
  return `
            <!-- STEP 1: Personal Info -->
            <div class="form-step ${currentStep === 1 ? "active" : ""}" id="step-panel-1">
                <div class="section-header">
                    <div class="section-icon"><i data-lucide="user" class="w-4 h-4 text-brand-400"></i></div>
                    <div>
                        <div class="text-xs font-bold text-brand-400 tracking-widest uppercase">Section 1</div>
                        <div class="text-base font-bold text-gray-900">Personal Information</div>
                    </div>
                </div>
                <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Full Name <span class="text-red-500">*</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="user" class="icon w-4 h-4"></i>
                            <input type="text" id="s1-name" value="${user.name || ""}" required placeholder="As per official document"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300 focus:border-transparent">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Date of Birth <span class="text-red-500">*</span> <span class="text-gray-400 font-normal">(DD/MM/YYYY)</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="calendar" class="icon w-4 h-4"></i>
                            <input type="date" id="s1-dob" required
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300 focus:border-transparent">
                        </div>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-2">Gender <span class="text-red-500">*</span></label>
                    <div class="flex gap-2 flex-wrap" id="gender-btn-group">
                        <button type="button" class="gender-btn" data-gender="Male" onclick="selectGender('Male')">
                            <span>♂</span> Male
                        </button>
                        <button type="button" class="gender-btn" data-gender="Female" onclick="selectGender('Female')">
                            <span>♀</span> Female
                        </button>
                        <button type="button" class="gender-btn" data-gender="Other" onclick="selectGender('Other')">
                            Other
                        </button>
                        <button type="button" class="gender-btn" data-gender="Prefer not to say" onclick="selectGender('Prefer not to say')">
                            Prefer not to say
                        </button>
                    </div>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-2">
                        Aadhaar Number
                        <span class="optional-badge">Optional — For Verification Only</span>
                    </label>
                    <div class="input-icon-wrap">
                        <i data-lucide="credit-card" class="icon w-4 h-4"></i>
                        <input type="text" id="s1-aadhaar" maxlength="14" placeholder="XXXX XXXX XXXX"
                            oninput="formatAadhaar(this)"
                            class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300 focus:border-transparent">
                    </div>
                    <p class="text-xs text-gray-400 mt-1">Stored encrypted. Never shared with third parties.</p>
                </div>
                <div class="mt-4">
                    <label class="block text-xs font-semibold text-gray-700 mb-1.5">Mobile Number <span class="text-red-500">*</span></label>
                    <div data-phone-mount="s1-phone" data-phone-required="1" data-phone-placeholder="98765 43210"></div>
                </div>

                <!-- Lineage & Pack (same page) -->
                <div class="section-header mt-8 pt-6 border-t border-gray-100">
                    <div class="section-icon"><i data-lucide="tree-pine" class="w-4 h-4 text-brand-400"></i></div>
                    <div>
                        <div class="text-xs font-bold text-brand-400 tracking-widest uppercase">Section 2</div>
                        <div class="text-base font-bold text-gray-900">Lineage & Pack</div>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Religion <span class="text-red-500">*</span></label>
                            <select id="s2-religion" required onchange="updatePackOptions('s2-religion','s2-Pack');updateSubPackOptions('s2-Pack','s2-sub-Pack-field','s2-sub-Pack')"
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select Religion...</option>
                                <option ${user.religion === "Hindu" ? "selected" : ""}>Hindu</option>
                                <option ${user.religion === "Muslim" ? "selected" : ""}>Muslim</option>
                                <option ${user.religion === "Sikh" ? "selected" : ""}>Sikh</option>
                                <option ${user.religion === "Christian" ? "selected" : ""}>Christian</option>
                                <option ${user.religion === "Jain" ? "selected" : ""}>Jain</option>
                                <option ${user.religion === "Buddhist" ? "selected" : ""}>Buddhist</option>
                                <option ${user.religion === "Parsi" ? "selected" : ""}>Parsi</option>
                                <option ${user.religion === "Other" ? "selected" : ""}>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Pack / Caste <span class="text-red-500">*</span></label>
                            <select id="s2-Pack" required onchange="updateSubPackOptions('s2-Pack','s2-sub-Pack-field','s2-sub-Pack')"
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select Pack...</option>
                            </select>
                        </div>
                        <div id="s2-sub-Pack-field" class="hidden">
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Sub-Pack <span class="text-red-500">*</span></label>
                            <select id="s2-sub-Pack" disabled
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select sub-Pack...</option>
                            </select>
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Mother Tongue <span class="text-red-500">*</span></label>
                            <div class="input-icon-wrap">
                                <i data-lucide="languages" class="icon w-4 h-4"></i>
                                <input type="text" id="s2-mothertongue" required placeholder="e.g. Hindi, Tamil, Bengali"
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                            </div>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1">
                                Gotra / Clan
                                <span class="optional-badge">Optional</span>
                            </label>
                            <div class="input-icon-wrap">
                                <i data-lucide="git-branch" class="icon w-4 h-4"></i>
                                <input type="text" id="s2-gotra" placeholder="e.g. Kashyap, Bharadwaj"
                                    class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                            </div>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Native Village / Town <span class="text-red-500">*</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="map-pin" class="icon w-4 h-4"></i>
                            <input type="text" id="s2-native" required placeholder="Enter your native place"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Current City of Residence <span class="text-red-500">*</span></label>
                        <div class="input-icon-wrap">
                            <i data-lucide="building-2" class="icon w-4 h-4"></i>
                            <input type="text" id="s2-city" required placeholder="e.g. Mumbai, Delhi, Bangalore"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAGE 2: Professional Background + Participation -->
            <div class="form-step ${currentStep === 2 ? "active" : ""}" id="step-panel-2">
                <div class="section-header">
                    <div class="section-icon"><i data-lucide="briefcase" class="w-4 h-4 text-brand-400"></i></div>
                    <div>
                        <div class="text-xs font-bold text-brand-400 tracking-widest uppercase">Section 3</div>
                        <div class="text-base font-bold text-gray-900">Professional Background</div>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Occupation / Profession <span class="text-red-500">*</span></label>
                            <select id="s3-occupation" required
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select...</option>
                                <option>Student</option><option>Self-Employed / Business</option>
                                <option>Private Sector Employee</option><option>Government / PSU Employee</option>
                                <option>Healthcare Professional</option><option>Educator / Academic</option>
                                <option>Legal / Judiciary</option><option>Artist / Creative</option>
                                <option>Homemaker</option><option>Retired</option><option>Other</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Highest Education <span class="text-red-500">*</span></label>
                            <select id="s3-education" required
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select...</option>
                                <option>Below 10th</option><option>10th / SSC</option><option>12th / HSC</option>
                                <option>Diploma</option><option>Graduate (B.A/B.Sc/B.Com etc.)</option>
                                <option>Post-Graduate</option><option>Doctorate / Ph.D</option><option>Professional Degree (MD/LLB/CA etc.)</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5">Skills You Can Contribute <span class="text-red-500">*</span></label>
                        <div class="flex gap-2 mb-2">
                            <input type="text" id="skill-input" placeholder="Type a skill and press Enter" onkeydown="addSkillTag(event)"
                                class="flex-1 px-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                            <button type="button" onclick="addSkillTag()" class="px-3 py-2 bg-brand-100/30 text-brand-400 font-semibold rounded-xl text-sm hover:bg-brand-100/60 transition-colors">Add</button>
                        </div>
                        <div id="skill-tags" class="flex flex-wrap gap-2 min-h-[32px]"></div>
                        <p class="text-xs text-gray-400 mt-1">Press Enter or click Add to add each skill.</p>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1">
                            Organisation / Institute Name
                            <span class="optional-badge">Optional</span>
                        </label>
                        <div class="input-icon-wrap">
                            <i data-lucide="building" class="icon w-4 h-4"></i>
                            <input type="text" id="s3-org" placeholder="Current employer or institution"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1">
                            LinkedIn / Website
                            <span class="optional-badge">Optional</span>
                        </label>
                        <div class="input-icon-wrap">
                            <i data-lucide="link" class="icon w-4 h-4"></i>
                            <input type="url" id="s3-link" placeholder="https://"
                                class="block w-full pl-10 pr-3 py-2.5 border border-gray-300 rounded-xl text-sm focus:outline-none focus:ring-2 focus:ring-brand-300">
                        </div>
                    </div>
                </div>

            <!-- Participation & Documents (same page) -->
                <div class="section-header mt-8 pt-6 border-t border-gray-100">
                    <div class="section-icon"><i data-lucide="clipboard-list" class="w-4 h-4 text-brand-400"></i></div>
                    <div>
                        <div class="text-xs font-bold text-brand-400 tracking-widest uppercase">Section 4</div>
                        <div class="text-base font-bold text-gray-900">Participation & Documents</div>
                    </div>
                </div>
                <div class="space-y-5">
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-2">Primary Interests <span class="text-red-500">*</span></label>
                        <p class="text-xs text-gray-500 mb-3">Select all that apply — helps us match you to the right Pack programs.</p>
                        <div class="flex flex-wrap gap-2" id="interest-tags-container">
                            ${INTERESTS.map(
    (i) => `
                            <button type="button" class="interest-tag" data-interest="${i.label}" onclick="toggleInterest('${i.label}', this)">
                                ${i.icon} ${i.label}
                            </button>`,
  ).join("")}
                        </div>
                    </div>
                    <div class="grid grid-cols-1 sm:grid-cols-2 gap-4">
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">Age Group <span class="text-red-500">*</span></label>
                            <select id="s4-age" required
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select...</option>
                                <option>Under 18</option><option>18 – 25</option>
                                <option>26 – 40</option><option>41 – 60</option><option>60+</option>
                            </select>
                        </div>
                        <div>
                            <label class="block text-xs font-semibold text-gray-700 mb-1.5">How did you hear about us?</label>
                            <select id="s4-source"
                                class="block w-full px-3 py-2.5 border border-gray-300 dark:border-gray-700 rounded-xl text-sm bg-white dark:bg-gray-900 dark:text-white focus:outline-none focus:ring-2 focus:ring-brand-300">
                                <option value="">Select...</option>
                                <option>Friend / Family</option><option>Social Media</option>
                                <option>Pack Leader</option><option>Event / Mela</option>
                                <option>Newspaper / Magazine</option><option>Other</option>
                            </select>
                        </div>
                    </div>
                    <div>
                        <label class="block text-xs font-semibold text-gray-700 mb-1.5 flex items-center gap-1">
                            Upload Profile Photo
                            <span class="optional-badge">Optional</span>
                        </label>
                        <div class="border-2 border-dashed border-gray-200 rounded-xl p-4 text-center bg-gray-50 hover:border-brand-300 transition-colors cursor-pointer" onclick="document.getElementById('s4-photo').click()">
                            <i data-lucide="camera" class="w-6 h-6 text-gray-300 mx-auto mb-2"></i>
                            <p class="text-xs text-gray-500">Click to upload a photo <span class="text-gray-400">(JPG, PNG up to 2MB)</span></p>
                            <input type="file" id="s4-photo" accept="image/*" class="hidden" onchange="previewPhoto(this)">
                        </div>
                        <div id="photo-preview" class="hidden mt-2 flex items-center gap-2">
                            <img id="photo-img" class="w-12 h-12 rounded-full object-cover border-2 border-brand-200">
                            <span id="photo-name" class="text-xs text-gray-600"></span>
                            <button type="button" onclick="removePhoto()" class="text-xs text-red-500 hover:underline">Remove</button>
                        </div>
                    </div>
                </div>
            </div>

            <!-- PAGE 3: Declaration & Consent -->
            <div class="form-step ${currentStep === 3 ? "active" : ""}" id="step-panel-3">
                <div class="section-header">
                    <div class="section-icon"><i data-lucide="check-square" class="w-4 h-4 text-brand-400"></i></div>
                    <div>
                        <div class="text-xs font-bold text-brand-400 tracking-widest uppercase">Section 5</div>
                        <div class="text-base font-bold text-gray-900">Declaration & Consent</div>
                    </div>
                </div>
                <div class="space-y-4">
                    <div class="bg-gray-50 rounded-xl p-4 border border-gray-100">
                        <h4 class="text-sm font-semibold text-gray-800 mb-2">Terms & Membership Rules</h4>
                        <div class="text-xs text-gray-600 space-y-1.5 max-h-32 overflow-y-auto pr-1">
                            <p>1. I confirm that all information provided is accurate and complete to the best of my knowledge.</p>
                            <p>2. I understand that providing false information may result in immediate termination of membership.</p>
                            <p>3. I agree to abide by the eCircle Code of Conduct and Pack guidelines.</p>
                            <p>4. I consent to receive communications related to Pack activities and membership updates.</p>
                            <p>5. I understand that my membership is subject to annual renewal and council approval.</p>
                            <p>6. I acknowledge that membership fees (if applicable) are non-refundable.</p>
                        </div>
                    </div>

                    <div class="space-y-3">
                        <div class="flex items-start gap-3 cursor-pointer" onclick="toggleConsent('terms')">
                            <div class="custom-checkbox mt-0.5 ${consentChecks.terms ? "checked" : ""}" id="check-terms">
                                ${consentChecks.terms ? '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ""}
                            </div>
                            <p class="text-xs text-gray-700 leading-relaxed">I have read and agree to the <span class="font-semibold text-brand-400">Terms & Conditions</span> and the eCircle Membership Rules stated above. <span class="text-red-500">*</span></p>
                        </div>

                        <div class="flex items-start gap-3 cursor-pointer" onclick="toggleConsent('privacy')">
                            <div class="custom-checkbox mt-0.5 ${consentChecks.privacy ? "checked" : ""}" id="check-privacy">
                                ${consentChecks.privacy ? '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ""}
                            </div>
                            <p class="text-xs text-gray-700 leading-relaxed">I consent to the collection and processing of my personal data for Pack purposes as described in the <span class="font-semibold text-brand-400">Privacy Policy</span> and in accordance with the DPDP Act, 2023. <span class="text-red-500">*</span></p>
                        </div>

                        <div class="flex items-start gap-3 cursor-pointer" onclick="toggleConsent('accuracy')">
                            <div class="custom-checkbox mt-0.5 ${consentChecks.accuracy ? "checked" : ""}" id="check-accuracy">
                                ${consentChecks.accuracy ? '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>' : ""}
                            </div>
                            <p class="text-xs text-gray-700 leading-relaxed">I declare that all information provided in this application is true, correct and complete, and I accept responsibility for its accuracy. <span class="text-red-500">*</span></p>
                        </div>
                    </div>
                </div>
            </div>`;
}

function bindGenderButtons(defaultReligion) {
  // Pre-select nothing initially
}

function selectGender(g) {
  selectedGender = g;
  document.querySelectorAll(".gender-btn").forEach((btn) => {
    btn.classList.toggle("selected", btn.dataset.gender === g);
  });
}

function bindInterestTags() {
  document.querySelectorAll(".interest-tag").forEach((tag) => {
    if (selectedInterests.includes(tag.dataset.interest))
      tag.classList.add("selected");
  });
}

function toggleInterest(label, el) {
  const idx = selectedInterests.indexOf(label);
  if (idx === -1) {
    selectedInterests.push(label);
    el.classList.add("selected");
  } else {
    selectedInterests.splice(idx, 1);
    el.classList.remove("selected");
  }
}

function toggleAccordion() {
  accordionOpen = !accordionOpen;
  const body = document.getElementById("accordion-body");
  const chevron = document.getElementById("accordion-chevron");
  body.classList.toggle("open", accordionOpen);
  chevron.style.transform = accordionOpen ? "rotate(180deg)" : "";
}

function toggleConsent(key) {
  consentChecks[key] = !consentChecks[key];
  const el = document.getElementById(`check-${key}`);
  el.classList.toggle("checked", consentChecks[key]);
  el.innerHTML = consentChecks[key]
    ? '<svg xmlns="http://www.w3.org/2000/svg" width="11" height="11" viewBox="0 0 24 24" fill="none" stroke="white" stroke-width="3.5" stroke-linecap="round" stroke-linejoin="round"><polyline points="20 6 9 17 4 12"></polyline></svg>'
    : "";
}

function addSkillTag(e) {
  if (e && e.key !== "Enter") return;
  if (e?.preventDefault) e.preventDefault();

  const input = document.getElementById("skill-input");
  if (!input) return;

  const val = input.value.trim();
  if (!val || skillTags.includes(val)) return;

  skillTags.push(val);
  renderSkillTags();
  input.value = "";
}

function renderSkillTags() {
  const container = document.getElementById("skill-tags");
  if (!container) return;
  container.innerHTML = skillTags
    .map(
      (tag) => `
                <span class="skill-tag">
                    ${tag}
                    <button type="button" onclick="removeSkillTag('${tag}')">×</button>
                </span>`,
    )
    .join("");
}

function removeSkillTag(tag) {
  skillTags = skillTags.filter((t) => t !== tag);
  renderSkillTags();
}

function formatAadhaar(input) {
  let val = input.value.replace(/\D/g, "").substring(0, 12);
  val = val.replace(/(\d{4})(?=\d)/g, "$1 ");
  input.value = val;
}



function prepareZoomSdk() {
  if (zoomSdkReady) return;

  if (!window.ZoomMtg) {
    throw new Error("Zoom Meeting SDK is not loaded. Check your Zoom CDN scripts.");
  }

  ZoomMtg.setZoomJSLib("https://source.zoom.us/6.1.0/lib", "/av");
  ZoomMtg.preLoadWasm();
  ZoomMtg.prepareWebSDK();

  zoomSdkReady = true;
}

function getZoomDisplayName() {
  const candidates = [
    currentUserObj?.name,
    currentUserObj?.socialProfile?.name,
    currentUserObj?.email,
    "eCircle Member",
  ];

  let name = candidates.find((v) => typeof v === "string" && v.trim().length > 0);

  name = String(name || "eCircle Member")
    .replace(/[^\p{L}\p{N}\s._@-]/gu, "")
    .replace(/\s+/g, " ")
    .trim();

  if (!name) name = "eCircle Member";
  if (name.length > 128) name = name.slice(0, 128).trim();

  return name;
}

function getZoomEmail() {
  return typeof currentUserObj?.email === "string" ? currentUserObj.email.trim() : "";
}







function activateZoomCallShell(joinUrl = null) {
  currentZoomJoinUrl = joinUrl || currentZoomJoinUrl;
  zoomCallHasJoined = false;

  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "";
  if (root) root.removeAttribute("aria-hidden");

  document.body.classList.remove("zoom-call-minimized", "zoom-call-compact", "zoom-call-fullscreen");
  document.body.classList.add("zoom-call-active", "zoom-call-large", "zoom-call-prejoin");

  updateZoomSizeToggleButton();
}

function markZoomCallJoined() {
  zoomCallHasJoined = true;
  document.body.classList.remove("zoom-call-prejoin");
  updateZoomSizeToggleButton();
}

function toggleZoomToolbarMenu() {
  const menu = document.getElementById("zoom-toolbar-menu");
  if (menu) menu.classList.toggle("hidden");
}

function setZoomCallView(mode) {
  if (mode === "compact" && !zoomCallHasJoined) {
    showToast("Compact mode is available after you join the meeting.", "warning");
    return;
  }

  document.body.classList.remove(
    "zoom-call-compact",
    "zoom-call-large",
    "zoom-call-fullscreen"
  );

  if (mode === "compact") {
    document.body.classList.add("zoom-call-compact");
  } else if (mode === "fullscreen") {
    document.body.classList.add("zoom-call-fullscreen");
  } else {
    document.body.classList.add("zoom-call-large");
  }

  updateZoomSizeToggleButton();
  nudgeZoomLayoutResize();
}

function nudgeZoomLayoutResize() {
  [0, 80, 220, 500].forEach((delay) => {
    setTimeout(() => {
      window.dispatchEvent(new Event("resize"));
    }, delay);
  });
}

function toggleZoomCallSize() {
  if (!zoomCallHasJoined) {
    showToast("Compact mode is available after you join the meeting.", "warning");
    return;
  }

  if (document.body.classList.contains("zoom-call-compact")) {
    setZoomCallView("large");
  } else {
    setZoomCallView("compact");
  }
}

function toggleZoomFullscreen() {
  if (document.body.classList.contains("zoom-call-fullscreen")) {
    setZoomCallView("large");
  } else {
    setZoomCallView("fullscreen");
  }
}

function updateZoomSizeToggleButton() {
  const sizeBtn = document.getElementById("zoom-size-toggle-btn");
  const fullscreenBtn = document.getElementById("zoom-fullscreen-toggle-btn");

  if (sizeBtn) {
    if (!zoomCallHasJoined || document.body.classList.contains("zoom-call-prejoin")) {
      sizeBtn.textContent = "Compact";
      sizeBtn.title = "Available after joining";
      sizeBtn.disabled = true;
    } else {
      sizeBtn.disabled = false;

      if (document.body.classList.contains("zoom-call-compact")) {
        sizeBtn.textContent = "Large";
        sizeBtn.title = "Switch to large view";
      } else {
        sizeBtn.textContent = "Compact";
        sizeBtn.title = "Switch to compact view";
      }
    }
  }

  if (fullscreenBtn) {
    if (document.body.classList.contains("zoom-call-fullscreen")) {
      fullscreenBtn.textContent = "Exit Fullscreen";
      fullscreenBtn.title = "Return to large view";
    } else {
      fullscreenBtn.textContent = "Fullscreen";
      fullscreenBtn.title = "Enter fullscreen";
    }
  }
}

function popOutZoomCall() {
  if (!currentZoomJoinUrl) {
    showToast("No Zoom join link is available yet.", "warning");
    return;
  }

  window.open(currentZoomJoinUrl, "_blank", "noopener,noreferrer");
}

function unlockZoomPageScroll() {
  const unlock = () => {
    document.documentElement.style.removeProperty("overflow");
    document.documentElement.style.removeProperty("position");
    document.documentElement.style.removeProperty("height");
    document.documentElement.style.removeProperty("width");
    document.documentElement.style.removeProperty("top");
    document.documentElement.style.removeProperty("left");
    document.body.style.removeProperty("overflow");
    document.body.style.removeProperty("position");
    document.body.style.removeProperty("height");
    document.body.style.removeProperty("width");
    document.body.style.removeProperty("top");
    document.body.style.removeProperty("left");
    document.body.classList.remove("ReactModal__Body--open", "ReactModal__Html--open", "zmmtg-body");
    document.documentElement.classList.remove("ReactModal__Body--open", "ReactModal__Html--open", "zmmtg-body");
  };
  unlock();
  setTimeout(unlock, 100);
  setTimeout(unlock, 500);
  setTimeout(unlock, 1500);
}

function stopZoomMediaElements() {
  const root = document.getElementById("zmmtg-root");
  const mediaNodes = [
    ...document.querySelectorAll("video, audio"),
    ...(root ? Array.from(root.querySelectorAll("video, audio")) : []),
  ];

  mediaNodes.forEach((node) => {
    try {
      const stream = node.srcObject;
      if (stream && typeof stream.getTracks === "function") {
        stream.getTracks().forEach((track) => {
          try { track.stop(); } catch (e) { }
        });
      }
      node.pause?.();
      node.srcObject = null;
      node.removeAttribute("src");
      node.load?.();
    } catch (err) {
      console.warn("Could not stop Zoom media element:", err);
    }
  });
}

function clearZoomSdkDom() {
  const root = document.getElementById("zmmtg-root");
  if (!root) return;

  stopZoomMediaElements();
  root.style.setProperty("display", "none", "important");
  root.style.setProperty("width", "0px", "important");
  root.style.setProperty("height", "0px", "important");
  root.style.setProperty("pointer-events", "none", "important");
  root.setAttribute("aria-hidden", "true");

  // Zoom can leave mounted React/iframe/media nodes behind after leaveMeeting.
  // Removing them releases lingering camera/mic tracks in local browser sessions.
  try {
    root.querySelectorAll("iframe, video, audio").forEach((node) => node.remove());
  } catch (err) {
    console.warn("Could not clear Zoom SDK DOM:", err);
  }
}

function markCurrentZoomParticipantLeft() {
  if (!currentZoomCallId || !currentUserObj?.id) return Promise.resolve(null);

  const callId = currentZoomCallId;
  const groupId = currentZoomCallGroupId;
  const friendId = currentZoomCallFriendId;

  // Clear immediately so repeated Leave clicks / beforeunload do not double-send.
  currentZoomCallId = null;
  currentZoomCallGroupId = null;
  currentZoomCallFriendId = null;
  currentZoomCallClientId = null;

  return fetch("api/index.php", {
    method: "POST",
    credentials: "include",
    headers: secureJsonHeaders(),
    keepalive: true,
    body: JSON.stringify({
      action: "zoom_mark_participant",
      user_id: currentUserObj.id,
      call_id: callId,
      participant_status: "left",
    }),
  })
    .then((res) => res.json().catch(() => null))
    .then((data) => {
      if (data?.call_ended && groupId) {
        updateGroupCallLogById(groupId, callId, {
          status: "ended",
          ended_at: data.ended_at || new Date().toISOString(),
        });
      } else if (data?.call_ended && friendId) {
        updateDirectCallLogById(friendId, callId, {
          status: "ended",
          ended_at: data.ended_at || new Date().toISOString(),
        });
      }
      return data;
    })
    .catch((err) => {
      console.warn("Could not mark Zoom participant left:", err);
      return null;
    });
}

function hideZoomToolbarMenu() {
  const menu = document.getElementById("zoom-toolbar-menu");
  if (menu) menu.classList.add("hidden");
}

function minimizeZoomCallShell() {
  // Minimize only hides the embedded Zoom UI. It does NOT leave the meeting,
  // so camera/mic may remain active. Use Leave Call to stop media.
  document.body.classList.remove(
    "zoom-call-active",
    "zoom-call-compact",
    "zoom-call-large",
    "zoom-call-fullscreen",
    "zoom-call-prejoin"
  );
  document.body.classList.add("zoom-call-minimized");

  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "none";

  hideZoomToolbarMenu();
  unlockZoomPageScroll();
}

function restoreZoomCallShell() {
  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "";

  document.body.classList.remove("zoom-call-minimized", "zoom-call-compact", "zoom-call-fullscreen", "zoom-call-prejoin");
  document.body.classList.add("zoom-call-active", "zoom-call-large");

  updateZoomSizeToggleButton();
  setTimeout(() => window.dispatchEvent(new Event("resize")), 80);
  setTimeout(() => window.dispatchEvent(new Event("resize")), 250);
}

function cleanupZoomShellState() {
  document.body.classList.remove(
    "zoom-call-active",
    "zoom-call-minimized",
    "zoom-call-compact",
    "zoom-call-large",
    "zoom-call-fullscreen",
    "zoom-call-prejoin"
  );

  hideZoomToolbarMenu();
  unlockZoomPageScroll();
  stopZoomMediaElements();
  setTimeout(stopZoomMediaElements, 500);
  setTimeout(stopZoomMediaElements, 1500);

  const root = document.getElementById("zmmtg-root");
  if (root) root.style.display = "none";
  setTimeout(clearZoomSdkDom, 1200);

  currentZoomJoinUrl = null;
  zoomCallHasJoined = false;
}



function leaveZoomMeetingSdk() {
  return new Promise((resolve) => {
    if (!window.ZoomMtg || typeof ZoomMtg.leaveMeeting !== "function") {
      stopZoomMediaElements();
      resolve();
      return;
    }

    let finished = false;
    const finish = (result) => {
      if (finished) return;
      finished = true;
      resolve(result);
    };

    try {
      ZoomMtg.leaveMeeting({
        success: (result) => {
          try {
            if (typeof ZoomMtg.destroy === "function") ZoomMtg.destroy();
          } catch (err) {
            console.warn("Zoom destroy after leave failed:", err);
          }
          stopZoomMediaElements();
          finish(result);
        },
        error: (err) => {
          console.warn("Zoom leaveMeeting returned an error:", err);
          try {
            if (typeof ZoomMtg.destroy === "function") ZoomMtg.destroy();
          } catch (destroyErr) {
            console.warn("Zoom destroy after leave error failed:", destroyErr);
          }
          stopZoomMediaElements();
          finish(err);
        },
      });

      // Safety fallback: some SDK versions do not reliably call success/error.
      setTimeout(() => finish(), 2500);
    } catch (err) {
      console.warn("Could not leave Zoom meeting cleanly:", err);
      finish(err);
    }
  });
}

async function leaveZoomCallShell() {
  if (zoomLeaveInProgress) return;
  zoomLeaveInProgress = true;

  try {
    await leaveZoomMeetingSdk();
    cleanupZoomShellState();
    await markCurrentZoomParticipantLeft();
  } catch (err) {
    console.warn("leaveZoomCallShell error", err);
  } finally {
    cleanupZoomShellState();
    zoomLeaveInProgress = false;
  }
}

// Backwards-compatible name used by older toolbar markup.
function closeZoomCallShell() {
  minimizeZoomCallShell();
}

window.addEventListener("beforeunload", () => {
  if (!currentZoomCallId || !currentUserObj?.id || !navigator.sendBeacon) return;

  const body = JSON.stringify({
    action: "zoom_mark_participant",
    user_id: currentUserObj.id,
    call_id: currentZoomCallId,
    participant_status: "left",
  });

  navigator.sendBeacon("api/index.php", new Blob([body], { type: "application/json" }));
});

async function joinZoomMeetingInPage(zoom, options = {}) {
  prepareZoomSdk();
  activateZoomCallShell(zoom.joinUrl || null);

  currentZoomCallId = options.callId || zoom.callId || currentZoomCallId;
  currentZoomCallGroupId = options.groupId || zoom.groupId || currentZoomCallGroupId;

  const leaveUrl = `${window.location.origin}${window.location.pathname}`;

  const displayName = String(options.userName || getZoomDisplayName() || "eCircle Member")
    .replace(/\s+/g, " ")
    .trim()
    .slice(0, 128);

  const joinConfig = {
    sdkKey: String(zoom.sdkKey || "").trim(),
    signature: String(zoom.signature || "").trim(),
    meetingNumber: String(zoom.meetingNumber || "").replace(/\D/g, ""),
    passWord: String(zoom.password || ""),
    userName: displayName || "eCircle Member",
    userEmail: String(options.userEmail || getZoomEmail() || "").trim(),
  };

  console.log("[Zoom] Join config check", {
    sdkKeyLength: joinConfig.sdkKey.length,
    meetingNumber: joinConfig.meetingNumber,
    userName: joinConfig.userName,
    userNameLength: joinConfig.userName.length,
    hasSignature: !!joinConfig.signature,
    hasPassword: !!joinConfig.passWord,
  });

  if (!joinConfig.userName || joinConfig.userName.length > 128) {
    throw new Error(`Invalid Zoom display name: "${joinConfig.userName}"`);
  }

  if (!joinConfig.meetingNumber) {
    throw new Error("Missing Zoom meeting number.");
  }

  if (!joinConfig.signature) {
    throw new Error("Missing Zoom SDK signature.");
  }

  return new Promise((resolve, reject) => {
    ZoomMtg.init({
      leaveUrl,
      patchJsMedia: true,

      success: (initSuccess) => {
        console.log("[Zoom] Init success", initSuccess);

        ZoomMtg.join({
          ...joinConfig,

          success: (joinSuccess) => {
            console.log("[Zoom] Join success", joinSuccess);
            markZoomCallJoined();
            setZoomCallView("large");

            if (currentZoomCallId && currentZoomCallGroupId) {
              updateGroupCallLogById(currentZoomCallGroupId, currentZoomCallId, {
                status: "active",
                started_at: options.startedAt || new Date().toISOString(),
                error: null,
              });
            } else if (currentZoomCallId && currentZoomCallFriendId) {
              updateDirectCallLogById(currentZoomCallFriendId, currentZoomCallId, {
                status: "active",
                started_at: options.startedAt || new Date().toISOString(),
                error: null,
              });
            }

            resolve(joinSuccess);
          },

          error: (joinError) => {
            console.error("[Zoom] Join error", joinError);
            showToast(joinError?.errorMessage || joinError?.result || "Could not join Zoom meeting.", "error");
            reject(joinError);
          },
        });
      },

      error: (initError) => {
        console.error("[Zoom] Init error", initError);
        reject(initError);
      },
    });
  });
}

async function startZoomCall({ callType, targetType, participantIds = [], groupId = null }) {
  const isGroupCall = targetType === "group" && !!groupId;
  const directFriendId = targetType === "direct" ? (participantIds || []).find((id) => String(id) !== String(currentUserObj?.id)) : null;
  const isDirectCall = !!directFriendId;
  const localCallId = (isGroupCall || isDirectCall) ? createLocalCallId() : null;

  if (isGroupCall) {
    addOrUpdateGroupCallLog(groupId, {
      client_id: localCallId,
      call_type: callType,
      status: "creating",
      created_by: currentUserObj?.id || null,
      created_by_name: currentUserObj?.socialProfile?.name || currentUserObj?.name || "You",
      started_at: new Date().toISOString(),
    });
  }

  if (isDirectCall) {
    addOrUpdateDirectCallLog(directFriendId, {
      client_id: localCallId,
      call_type: callType,
      status: "creating",
      created_by: currentUserObj?.id || null,
      created_by_name: currentUserObj?.socialProfile?.name || currentUserObj?.name || "You",
      started_at: new Date().toISOString(),
    });
  }

  try {
    const data = await api("zoom_start_call", {
      user_id: currentUserObj.id,
      call_type: callType,
      target_type: targetType,
      participant_ids: participantIds,
      group_id: groupId,
    });

    if (data.status !== "success") {
      throw new Error(data.message || "Could not start Zoom call");
    }

    if (isGroupCall && data.call) {
      updateGroupCallLogByClientId(groupId, localCallId, {
        id: data.call.id,
        call_type: data.call.call_type || callType,
        status: data.call.status || "active",
        created_by: data.call.created_by || currentUserObj.id,
        created_by_name: currentUserObj?.socialProfile?.name || currentUserObj?.name || "You",
        started_at: data.call.started_at || data.call.created_at || new Date().toISOString(),
        ended_at: data.call.ended_at || null,
        error: null,
      });
    }

    if (isDirectCall && data.call) {
      updateDirectCallLogByClientId(directFriendId, localCallId, {
        id: data.call.id,
        call_type: data.call.call_type || callType,
        status: data.call.status || "active",
        created_by: data.call.created_by || currentUserObj.id,
        created_by_name: currentUserObj?.socialProfile?.name || currentUserObj?.name || "You",
        started_at: data.call.started_at || data.call.created_at || new Date().toISOString(),
        ended_at: data.call.ended_at || null,
        error: null,
      });
    }

    currentZoomCallId = data.call?.id || null;
    currentZoomCallGroupId = isGroupCall ? groupId : null;
    currentZoomCallFriendId = isDirectCall ? directFriendId : null;
    currentZoomCallClientId = localCallId;

    await joinZoomMeetingInPage(data.zoom, {
      userName: getZoomDisplayName(),
      userEmail: getZoomEmail(),
      callId: data.call?.id || null,
      groupId: isGroupCall ? groupId : null,
      startedAt: data.call?.started_at || data.call?.created_at || null,
    });
    refreshNotificationsInBackground();
  } catch (err) {
    console.error(err);

    if (isGroupCall && localCallId) {
      updateGroupCallLogByClientId(groupId, localCallId, {
        status: "failed",
        ended_at: new Date().toISOString(),
        error: err?.message || "Could not start call.",
      });
    }

    if (isDirectCall && localCallId) {
      updateDirectCallLogByClientId(directFriendId, localCallId, {
        status: "failed",
        ended_at: new Date().toISOString(),
        error: err?.message || "Could not start call.",
      });
    }

    showToast(err?.errorMessage || err?.result || err?.message || "Something went wrong.", "error");
  }
}

async function joinZoomCall(callId) {
  try {
    const data = await api("zoom_join_call", {
      user_id: currentUserObj.id,
      call_id: callId,
    });

    if (data.status !== "success") {
      throw new Error(data.message || "Could not join Zoom call");
    }

    currentZoomCallId = data.call?.id || callId;
    currentZoomCallGroupId = data.call?.group_id || null;
    currentZoomCallFriendId = currentZoomCallGroupId ? null : currentOpenFriendChatId;

    if (currentZoomCallGroupId) {
      updateGroupCallLogById(currentZoomCallGroupId, currentZoomCallId, {
        status: "active",
        participant_status: "joined",
        started_at: data.call?.started_at || data.call?.created_at || new Date().toISOString(),
      });
    } else if (currentZoomCallFriendId && currentZoomCallId) {
      updateDirectCallLogById(currentZoomCallFriendId, currentZoomCallId, {
        status: "active",
        participant_status: "joined",
        started_at: data.call?.started_at || data.call?.created_at || new Date().toISOString(),
      });
    }

    await joinZoomMeetingInPage(data.zoom, {
      userName: getZoomDisplayName(),
      userEmail: getZoomEmail(),
      callId: currentZoomCallId,
      groupId: currentZoomCallGroupId,
      startedAt: data.call?.started_at || data.call?.created_at || null,
    });
  } catch (err) {
    console.error(err);
    showToast(err?.errorMessage || err?.result || err?.message || "Something went wrong.", "error");
  }
}

function isCallLive(call) {
  if (!["ringing", "active", "live"].includes(call.status)) return false;

  const startedAt = new Date(call.started_at || call.created_at).getTime();
  const maxAgeMs = 2 * 60 * 60 * 1000; // 2 hours

  return Date.now() - startedAt < maxAgeMs;
}

function blobToImage(blob) {
  return new Promise((resolve, reject) => {
    const url = URL.createObjectURL(blob);
    const img = new Image();
    img.onload = () => {
      URL.revokeObjectURL(url);
      resolve(img);
    };
    img.onerror = () => {
      URL.revokeObjectURL(url);
      reject(new Error("Could not read image for upload."));
    };
    img.src = url;
  });
}

function canvasToBlob(canvas, type, quality) {
  return new Promise((resolve, reject) => {
    canvas.toBlob((blob) => {
      if (blob) resolve(blob);
      else reject(new Error("Could not compress image for upload."));
    }, type, quality);
  });
}

async function prepareImageForUpload(file, options = {}) {
  const isResizableImage = /^image\/(jpeg|png|webp)$/i.test(file.type || "");
  if (!isResizableImage) return file;

  const bucket = options.bucket || "profile-photos";
  // Compress by default now (opt-out via compressImage:false). Feed/gallery
  // images get a larger, higher-quality tier than avatars/covers so photos
  // still look good while shrinking storage + bandwidth.
  if (options.compressImage === false) return file;
  const isFeedMedia = bucket === "post-media" || bucket === "gallery-media";

  const maxBytes = options.maxBytes || (bucket === "cover-photos" ? 1600 * 1024 : isFeedMedia ? 2200 * 1024 : 900 * 1024);
  const maxWidth = options.imageMaxWidth || (bucket === "cover-photos" ? 1920 : isFeedMedia ? 1600 : 768);
  const maxHeight = options.imageMaxHeight || (bucket === "cover-photos" ? 720 : isFeedMedia ? 1600 : 768);
  if (file.size <= maxBytes && file.type === "image/webp") return file;

  const img = await blobToImage(file);
  const ratio = Math.min(1, maxWidth / img.naturalWidth, maxHeight / img.naturalHeight);
  const width = Math.max(1, Math.round(img.naturalWidth * ratio));
  const height = Math.max(1, Math.round(img.naturalHeight * ratio));
  const canvas = document.createElement("canvas");
  canvas.width = width;
  canvas.height = height;
  const ctx = canvas.getContext("2d");
  ctx.drawImage(img, 0, 0, width, height);

  let quality = options.imageQuality || (bucket === "cover-photos" ? 0.82 : isFeedMedia ? 0.82 : 0.86);
  let blob = await canvasToBlob(canvas, "image/webp", quality);
  while (blob.size > maxBytes && quality > 0.5) {
    quality -= 0.08;
    blob = await canvasToBlob(canvas, "image/webp", quality);
  }

  const baseName = (file.name || "upload").replace(/\.[^.]+$/, "");
  return new File([blob], `${baseName}.webp`, { type: "image/webp", lastModified: Date.now() });
}

// Best-effort, dependency-free video compression. Re-encodes the clip through
// canvas.captureStream + MediaRecorder, downscaling to a target longest side and
// capping the bitrate. It runs in real time (roughly the clip's own duration),
// so it's gated to short-ish clips and reasonable sizes. On ANY failure — unsupported
// browser, blocked autoplay, a result that isn't actually smaller — it resolves to
// the ORIGINAL file, so uploads never break. Disable with compressVideo:false.
async function prepareVideoForUpload(file, options = {}) {
  if (!/^video\//i.test(file.type || "")) return file;
  if (options.compressVideo === false) return file;

  const maxDim = options.videoMaxDimension || 720;          // longest side target
  const bitrateCap = options.videoBitrate || 2500000;       // hard ceiling (~2.5 Mbps)
  const bitrateFactor = options.videoBitrateFactor || 0.6;  // aim below the source bitrate
  const minBitrate = options.videoMinBitrate || 300000;     // floor so quality isn't garbage
  const maxSeconds = options.videoMaxSeconds || 180;        // don't tie up the tab too long
  const minBytes = options.videoMinBytes || 512 * 1024;     // skip only truly tiny clips

  const emit = (msg) => {
    if (typeof options.onStatus === "function") {
      try { options.onStatus(msg); } catch (e) { /* status is best-effort */ }
    }
  };

  const testCanvas = document.createElement("canvas");
  if (
    file.size < minBytes ||
    typeof MediaRecorder === "undefined" ||
    typeof testCanvas.captureStream !== "function"
  ) {
    return file;
  }

  const mimeType = [
    "video/mp4;codecs=h264,aac",
    "video/mp4",
    "video/webm;codecs=vp9,opus",
    "video/webm;codecs=vp8,opus",
    "video/webm",
  ].find((m) => { try { return MediaRecorder.isTypeSupported(m); } catch (e) { return false; } });
  if (!mimeType) return file;

  try {
    return await new Promise((resolve) => {
      const url = URL.createObjectURL(file);
      const video = document.createElement("video");
      video.playsInline = true;
      video.preload = "auto";
      video.src = url;

      let audioCtx = null;
      let recorder = null;
      let rafId = null;
      let settled = false;
      const chunks = [];

      const cleanup = () => {
        if (rafId) cancelAnimationFrame(rafId);
        try { if (audioCtx) audioCtx.close(); } catch (e) { }
        try { video.pause(); } catch (e) { }
        URL.revokeObjectURL(url);
      };
      const fallback = () => {
        if (settled) return;
        settled = true;
        try { if (recorder && recorder.state !== "inactive") recorder.stop(); } catch (e) { }
        cleanup();
        resolve(file);
      };

      video.onerror = fallback;

      video.onloadedmetadata = () => {
        const duration = video.duration || 0;
        const w = video.videoWidth, h = video.videoHeight;
        if (!duration || duration > maxSeconds || !w || !h) { fallback(); return; }

        const ratio = Math.min(1, maxDim / Math.max(w, h));
        // Even dimensions keep H.264/VP9 encoders happy.
        const outW = Math.max(2, Math.round((w * ratio) / 2) * 2);
        const outH = Math.max(2, Math.round((h * ratio) / 2) * 2);

        // Aim BELOW the source bitrate so the re-encode is actually smaller
        // (a fixed target above the source would just be discarded by the
        // "keep only if smaller" guard). Clamp to a sane floor and ceiling.
        const sourceBitrate = (file.size * 8) / duration;
        const targetBitrate = Math.max(
          minBitrate,
          Math.min(bitrateCap, Math.round(sourceBitrate * bitrateFactor)),
        );

        const canvas = document.createElement("canvas");
        canvas.width = outW;
        canvas.height = outH;
        const ctx = canvas.getContext("2d");

        const tracks = canvas.captureStream(30).getVideoTracks();
        // Route audio through WebAudio so it's captured but NOT played aloud.
        try {
          const AC = window.AudioContext || window.webkitAudioContext;
          if (AC) {
            audioCtx = new AC();
            const dest = audioCtx.createMediaStreamDestination();
            audioCtx.createMediaElementSource(video).connect(dest);
            dest.stream.getAudioTracks().forEach((t) => tracks.push(t));
          }
        } catch (e) { /* proceed video-only */ }

        try {
          recorder = new MediaRecorder(new MediaStream(tracks), {
            mimeType,
            videoBitsPerSecond: targetBitrate,
          });
        } catch (e) { fallback(); return; }

        recorder.ondataavailable = (ev) => { if (ev.data && ev.data.size) chunks.push(ev.data); };
        recorder.onstop = () => {
          if (settled) return;
          const outType = mimeType.split(";")[0];
          const blob = new Blob(chunks, { type: outType });
          cleanup();
          // Keep the re-encode only if it genuinely saved space.
          if (!blob.size || blob.size >= file.size) { settled = true; resolve(file); return; }
          const ext = outType === "video/mp4" ? "mp4" : "webm";
          const base = (file.name || "video").replace(/\.[^.]+$/, "");
          settled = true;
          resolve(new File([blob], `${base}.${ext}`, { type: outType, lastModified: Date.now() }));
        };

        const draw = () => {
          if (settled || video.ended || video.paused) return;
          ctx.drawImage(video, 0, 0, outW, outH);
          rafId = requestAnimationFrame(draw);
        };
        video.onended = () => { try { recorder.stop(); } catch (e) { fallback(); } };

        try { recorder.start(1000); } catch (e) { fallback(); return; }
        emit("Compressing video… 0%");
        let lastPct = 0;
        const reportProgress = () => {
          const pct = Math.min(99, Math.round((video.currentTime / duration) * 100));
          if (pct > lastPct) { lastPct = pct; emit(`Compressing video… ${pct}%`); }
        };
        video.ontimeupdate = reportProgress;
        video.play().then(() => {
          if (audioCtx && audioCtx.state === "suspended") audioCtx.resume().catch(() => { });
          draw();
        }).catch(fallback);
      };

      // Hard safety net so a stuck encode never hangs the upload forever.
      setTimeout(fallback, (maxSeconds + 30) * 1000);
    });
  } catch (e) {
    return file;
  }
}

// Pick the right compressor for the file kind. Images and videos are both
// shrunk before upload; anything else passes through untouched.
async function prepareMediaForUpload(file, options = {}) {
  if (/^video\//i.test(file.type || "")) return prepareVideoForUpload(file, options);
  return prepareImageForUpload(file, options);
}

// Upload a File object to api/index.php?action=upload_photo.
// Profile/cover images are compressed client-side first to stay under PHP and backend limits.


async function uploadPhotoToSupabase(file, options = {}) {
  if (isOfflineDemoMode() || isOfflineDemoUser()) {
    showToast("Uploads are disabled in offline demo mode.", "info");
    return options.detailed ? { status: "error", message: "Uploads are disabled in offline demo mode.", offline_demo: true } : null;
  }
  const uploadFile = await prepareMediaForUpload(file, options);
  // Compression (if any) is done; the network upload starts now.
  if (typeof options.onStatus === "function") {
    try { options.onStatus("Uploading…"); } catch (e) { /* status is best-effort */ }
  }
  const formData = new FormData();
  formData.append("photo", uploadFile);
  if (options.bucket) formData.append("bucket", options.bucket);
  if (options.prefix) formData.append("prefix", options.prefix);
  if (options.folder) formData.append("folder", options.folder);
  if (options.userId) formData.append("user_id", options.userId);
  const detailed = Boolean(options.detailed);
  try {
    const res = await fetch("api/index.php?action=upload_photo", {
      method: "POST",
      credentials: "include",
      headers: secureUploadHeaders(),
      body: formData, // multipart — do NOT set Content-Type header manually
    });
    const text = await res.text();
    console.log("[Photo Upload] Raw response:", text); // <-- shows exact PHP output
    // Strip any PHP warnings/notices that precede the JSON
    const jsonStart = text.indexOf("{");
    if (jsonStart === -1) {
      console.error("[Photo Upload] No JSON in response:", text);
      if (detailed) throw new Error("Upload failed before the server returned JSON. The file may be too large for the PHP upload limit.");
      return null;
    }
    const data = JSON.parse(text.slice(jsonStart));
    console.log("[Photo Upload] Backend response:", data);
    if (data.status === "success") return detailed ? data : data.photo_url;
    console.error("[Photo Upload] Failed:", data.message, data);
    if (detailed) throw new Error(data.message || "Upload failed.");
    return null;
  } catch (err) {
    console.error("[Photo Upload] Parse/network error:", err);
    if (detailed) throw err;
    return null;
  }
}

function previewPhoto(input) {
  if (input.files && input.files[0]) {
    const reader = new FileReader();
    reader.onload = (e) => {
      document.getElementById("photo-img").src = e.target.result;
      document.getElementById("photo-name").textContent =
        input.files[0].name;
      document.getElementById("photo-preview").classList.remove("hidden");
    };
    reader.readAsDataURL(input.files[0]);
  }
}

function removePhoto() {
  document.getElementById("s4-photo").value = "";
  document.getElementById("photo-preview").classList.add("hidden");
}

function markFieldInvalid(el, invalid) {
  if (el && el.classList) el.classList.toggle("field-invalid", !!invalid);
}

// Validates the membership form before submission. Returns true when every
// required field (and required widget) has been completed. Highlights the
// offending fields, shows a toast, and scrolls to the first problem.
function validateMembershipForm() {
  const container = document.getElementById("membership-form-container");
  if (!container) return true;

  const missing = [];
  let firstInvalidEl = null;

  // Native required inputs/selects — works for both the compact and full forms.
  container
    .querySelectorAll("input[required], select[required], textarea[required]")
    .forEach((el) => {
      const empty = !String(el.value || "").trim();
      markFieldInvalid(el, empty);
      if (empty) {
        missing.push(MEMBERSHIP_FIELD_LABELS[el.id] || "a required field");
        if (!firstInvalidEl) firstInvalidEl = el;
      }
    });

  // Custom widgets that have no native "required" support are only mandatory
  // on the full form (they are optional or absent in the compact variant).
  if (!membershipFormIsCompact) {
    if (!selectedGender) {
      missing.push("Gender");
      if (!firstInvalidEl) firstInvalidEl = document.getElementById("gender-btn-group");
    }
    if (!skillTags.length) {
      missing.push("Skills You Can Contribute");
      if (!firstInvalidEl) firstInvalidEl = document.getElementById("skill-input");
    }
    if (!selectedInterests.length) {
      missing.push("Primary Interests");
      if (!firstInvalidEl) firstInvalidEl = document.getElementById("interest-tags-container");
    }
    if (!consentChecks.terms || !consentChecks.privacy || !consentChecks.accuracy) {
      missing.push("all consent declarations");
      if (!firstInvalidEl) firstInvalidEl = document.getElementById("check-terms");
    }
  }

  if (missing.length) {
    const unique = [...new Set(missing)];
    const preview = unique.slice(0, 3).join(", ");
    const extra = unique.length > 3 ? ` and ${unique.length - 3} more` : "";
    showToast(`Please complete: ${preview}${extra}.`, "error");
    if (firstInvalidEl) {
      firstInvalidEl.scrollIntoView({ behavior: "smooth", block: "center" });
      if (typeof firstInvalidEl.focus === "function") {
        try { firstInvalidEl.focus({ preventScroll: true }); } catch (e) { /* non-focusable */ }
      }
    }
    return false;
  }
  return true;
}

function validateStep(step) {
  // Legacy hook from the old multi-step flow. The form is now single-page,
  // so per-step navigation is unused; full validation runs in submitMembership.
  return true;
}

function nextStep() {
  if (!validateStep(currentStep)) return;

  // Pre-fill step 2 Pack options if we're about to go to step 2
  if (currentStep === 1) {
    setTimeout(() => {
      const relEl = document.getElementById("s2-religion");
      if (relEl && relEl.value)
        updatePackOptions("s2-religion", "s2-Pack");
    }, 50);
  }

  document
    .getElementById(`step-panel-${currentStep}`)
    ?.classList.remove("active");
  currentStep++;
  document
    .getElementById(`step-panel-${currentStep}`)
    ?.classList.add("active");
  updateNav();

  // After moving to step 2, set up Pack dropdown
  if (currentStep === 2) {
    const relEl = document.getElementById("s2-religion");
    if (relEl && relEl.value)
      updatePackOptions("s2-religion", "s2-Pack");
  }

  window.scrollTo({ top: 0, behavior: "smooth" });
}

function prevStep() {
  document
    .getElementById(`step-panel-${currentStep}`)
    ?.classList.remove("active");
  currentStep--;
  document
    .getElementById(`step-panel-${currentStep}`)
    ?.classList.add("active");
  updateNav();
  window.scrollTo({ top: 0, behavior: "smooth" });
}

function updateNav() {
  // Update step indicator
  document.getElementById("step-indicator-container").innerHTML =
    renderStepIndicator();
  document.getElementById("step-count-display").textContent = currentStep;

  const prevBtn = document.getElementById("btn-prev");
  const nextBtn = document.getElementById("btn-next");

  if (prevBtn) prevBtn.classList.toggle("invisible", currentStep === 1);
  if (nextBtn) {
    if (currentStep < TOTAL_STEPS) {
      nextBtn.innerHTML =
        'Next <i data-lucide="arrow-right" class="w-4 h-4"></i>';
      nextBtn.onclick = nextStep;
    } else {
      nextBtn.innerHTML =
        '<i data-lucide="send" class="w-4 h-4"></i> Submit Application';
      nextBtn.onclick = submitMembership;
    }
  }
  lucide.createIcons();
}

async function submitMembership() {
  // Block submission until every required field has been filled in.
  if (!validateMembershipForm()) return;

  const submitBtn = document.getElementById("btn-submit-membership");
  if (submitBtn) {
    submitBtn.innerHTML = '<span class="animate-spin">⟳</span> Saving...';
    submitBtn.disabled = true;
  }

  const applicationData = {
    membership_applied: true,
    s1_name: document.getElementById("s1-name")?.value,
    s1_dob: document.getElementById("s1-dob")?.value,
    s1_gender: selectedGender,
    s1_phone: getPhoneValue("s1-phone"),
    s2_religion: document.getElementById("s2-religion")?.value,
    s2_Pack: joinPackWithSub(
      document.getElementById("s2-Pack")?.value,
      document.getElementById("s2-sub-Pack")?.disabled
        ? ""
        : document.getElementById("s2-sub-Pack")?.value,
    ),
    s2_mothertongue: document.getElementById("s2-mothertongue")?.value,
    s2_native: document.getElementById("s2-native")?.value,
    s2_city: document.getElementById("s2-city")?.value,
    s3_occupation: document.getElementById("s3-occupation")?.value,
    s3_education: document.getElementById("s3-education")?.value,
    s3_skills: skillTags,
    s4_interests: selectedInterests,
    s4_age: normalizeAgeGroup(document.getElementById("s4-age")?.value),
    s4_source: document.getElementById("s4-source")?.value,
  };

  // Update socialProfile with signup data so it persists
  currentUserObj.socialProfile = currentUserObj.socialProfile || {};
  currentUserObj.socialProfile.Pack = applicationData.s2_Pack;
  syncUserAgeGroup(currentUserObj, applicationData.s4_age);
  currentUserObj.socialProfile.occupation = applicationData.s3_occupation;
  currentUserObj.membership_applied = true;
  currentUserObj.name = applicationData.s1_name || currentUserObj.name;
  currentUserObj.religion = applicationData.s2_religion || currentUserObj.religion;
  currentUserObj.Pack = applicationData.s2_Pack || currentUserObj.Pack;
  currentUserObj.dob = applicationData.s1_dob || currentUserObj.dob;
  currentUserObj.gender = applicationData.s1_gender || currentUserObj.gender;
  currentUserObj.mobile_number = applicationData.s1_phone || currentUserObj.mobile_number;
  currentUserObj.city = applicationData.s2_city || currentUserObj.city;
  currentUserObj.occupation = applicationData.s3_occupation || currentUserObj.occupation;
  Object.assign(currentUserObj, applicationData);
  syncUserAgeGroup(currentUserObj, applicationData.s4_age);

  // Persist to Supabase if we have a user_id
  if (currentUserObj?.id) {
    try {
      // Upload photo first if one was selected
      let photoUrl = null;
      const photoInput = document.getElementById("s4-photo");
      if (photoInput?.files?.length > 0) {
        photoUrl = await uploadPhotoToSupabase(photoInput.files[0], { bucket: 'profile-photos', prefix: 'profile', userId: currentUserObj.id });
      }

      if (photoUrl) {
        currentUserObj.profile_photo_url = photoUrl;
        applicationData.profile_photo_url = photoUrl;
      }

      const profilePayload = {
        action: "update_profile",
        user_id: currentUserObj.id,
        full_name: applicationData.s1_name,
        date_of_birth: applicationData.s1_dob,
        gender: applicationData.s1_gender,
        mobile_number: applicationData.s1_phone,
        religion: applicationData.s2_religion,
        Pack: applicationData.s2_Pack,
        mother_tongue: applicationData.s2_mothertongue,
        native_village: applicationData.s2_native,
        current_city: applicationData.s2_city,
        occupation: applicationData.s3_occupation,
        highest_education: applicationData.s3_education,
        skills: applicationData.s3_skills,
        primary_interests: applicationData.s4_interests,
        age_group: applicationData.s4_age,
        source: applicationData.s4_source,
        membership_applied: true,
      };

      // Profile photo and other fields already included in payload

      const profileRes = await fetch("api/index.php", {
        method: "POST",
        credentials: "include",
        headers: secureJsonHeaders(),
        body: JSON.stringify(profilePayload),
      });
      const profileData = await profileRes.json().catch(() => ({}));
      if (!profileRes.ok || profileData.status === "error") {
        throw new Error(profileData.message || "Profile update failed.");
      }
    } catch (err) {
      console.warn(
        "Could not save membership to backend, storing locally.",
        err,
      );
    }
  }

  // Persist updated session with photo and age
  persistCurrentSession(currentUserObj);

  // Profile is now complete — refresh the bell to drop the completion notification.
  if (typeof renderNotifications === "function") renderNotifications();

  // Simulate async delay then show success
  await new Promise((r) => setTimeout(r, 600));

  // Show success state
  document.getElementById("membership-form-container").innerHTML = `
                <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-gray-100 dark:border-gray-800 overflow-hidden">
                    <div class="p-8 text-center">
                        <div class="w-16 h-16 bg-green-50 rounded-full flex items-center justify-center mx-auto mb-4">
                            <i data-lucide="check-circle-2" class="w-8 h-8 text-green-500"></i>
                        </div>
                        <h3 class="text-xl font-bold text-gray-900 mb-2" style="font-family:'DM Serif Display'">Membership Approved!</h3>
                        <p class="text-sm text-gray-600 mb-1">Your eCircle Membership has been automatically accepted.</p>
                        <p class="text-xs text-gray-400 mb-6">Member ID: <span class="font-mono font-semibold text-gray-600">ESJ-${Date.now().toString(36).toUpperCase()}</span></p>
                        <div class="bg-green-50 border border-green-100 rounded-xl p-4 text-left mb-6">
                            <p class="text-xs text-green-800 leading-relaxed">Congratulations! You now have full access to Pack features. Your profile has been pre-filled with the information provided.</p>
                        </div>
                        <div class="grid grid-cols-2 gap-3 text-xs text-left bg-gray-50 rounded-xl p-4">
                            <div class="text-gray-500">Name</div><div class="font-semibold text-gray-800">${applicationData.s1_name || "—"}</div>
                            <div class="text-gray-500">Pack</div><div class="font-semibold text-gray-800">${[applicationData.s2_religion, applicationData.s2_Pack].filter(Boolean).join(" · ") || "—"}</div>
                            <div class="text-gray-500">Age Group</div><div class="font-semibold text-gray-800">${applicationData.s4_age || "—"}</div>
                            <div class="text-gray-500">Occupation</div><div class="font-semibold text-gray-800">${applicationData.s3_occupation || "—"}</div>
                        </div>
                        <div class="mt-6 pt-6 border-t border-gray-100">
                            <button onclick="initializeSocialFeed()" class="text-sm font-bold text-white px-8 py-3 rounded-xl transition-colors w-full sm:w-auto shadow-md flex items-center justify-center mx-auto" style="background: linear-gradient(135deg, color-mix(in srgb, var(--faith-accent, #f59e0b) 80%, white) 0%, color-mix(in srgb, var(--faith-accent, #f59e0b) 55%, white) 55%, color-mix(in srgb, var(--faith-accent, #f59e0b) 35%, white) 100%);">
                                Enter Pack Feed <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                            </button>
                        </div>
                    </div>
                </div>`;
  lucide.createIcons();
}

function renderMembershipPlaceholder() {
  const container = document.getElementById("membership-form-container");
  if (!container) return;
  container.innerHTML = `
            <div class="bg-white dark:bg-gray-900 rounded-2xl shadow-sm border border-dashed border-gray-200 dark:border-gray-800 p-6 text-center">
                <i data-lucide="file-text" class="w-8 h-8 text-gray-300 mx-auto mb-3"></i>
                <p class="text-sm font-medium text-gray-600 mb-1">No membership application yet</p>
                <p class="text-xs text-gray-400 mb-4">Apply for membership to unlock full Pack features.</p>
                <button onclick="goToCompleteProfile()"
                    class="text-sm font-semibold text-brand-400 hover:text-brand-300 border border-brand-200 hover:border-brand-300 px-4 py-2 rounded-xl transition-colors mb-3 w-full sm:w-auto">
                    Apply for Membership →
                </button>
                <div class="pt-4 border-t border-gray-100 mt-2">
                    <button onclick="initializeSocialFeed()" class="text-sm font-bold text-white bg-gray-800 hover:bg-gray-900 px-6 py-2.5 rounded-xl transition-colors w-full sm:w-auto flex items-center justify-center mx-auto">
                        Continue to Feed <i data-lucide="arrow-right" class="w-4 h-4 ml-2"></i>
                    </button>
                </div>
            </div>`;
  lucide.createIcons();
}

function skipMembership() {
  renderMembershipPlaceholder();
  // Refresh the bell so the "Complete your membership" notification appears.
  if (typeof renderNotifications === "function") renderNotifications();
}

function goToCompleteProfile() {
  const panel = document.getElementById("notifications-panel");
  if (panel) panel.classList.add("hidden");
  // Reset the multi-step form state and re-open the membership form.
  currentStep = 1;
  selectedGender = "";
  selectedInterests = [];
  skillTags = [];
  consentChecks = { terms: false, privacy: false, accuracy: false };
  switchView("view-member-dashboard");
  renderMembershipForm(currentUserObj);
  const container = document.getElementById("membership-form-container");
  if (container) container.scrollIntoView({ behavior: "smooth", block: "start" });
}


