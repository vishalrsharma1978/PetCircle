
let currentStep = 1;
const TOTAL_STEPS = 3;
// Tracks whether the membership form is currently rendered in its compact
// ("just the essentials") variant — used so validation only enforces the
// smaller set of required fields shown in that mode.
let membershipFormIsCompact = false;

// Friendly labels for the required membership fields, used in validation messages.
const MEMBERSHIP_FIELD_LABELS = {
  "s1-name": "Full Name",
  "s1-dob": "Date of Birth",
  "s1-phone": "Mobile Number",
  "s2-religion": "Religion",
  "s2-Pack": "Pack / Caste",
  "s2-sub-Pack": "Sub-Pack",
  "s2-mothertongue": "Mother Tongue",
  "s2-native": "Native Village / Town",
  "s2-city": "Current City",
  "s3-occupation": "Occupation / Profession",
  "s3-education": "Highest Education",
  "s4-age": "Age Group",
};

const STEPS_CONFIG = [
  { label: "Personal &\nPack", icon: "user" },
  { label: "Professional\n& Interests", icon: "briefcase" },
  { label: "Declaration\n& Consent", icon: "check-square" },
];

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
