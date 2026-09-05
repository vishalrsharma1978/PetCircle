// Social Hub: hero/cover card (the logged-in pet's own summary, shown above
// the tab strip content) and the right-rail widgets (Highlight card, Ads
// rail, Calendar) — the structural pieces eSamaj's social feed page has that
// this rebuild's hub was missing entirely until now. Every widget here is
// backed by an already-built, already-tested endpoint (get_profile,
// get_community_hub, get_ads, get_events) rather than any new or fabricated
// content.

// Bundles the pet-type-theme + hero + highlight + ads + calendar widgets'
// backing reads into one HTTP round trip (step 24 Part C) instead of the
// 6 separate requests goToDashboard() used to fire on every login. Seeds
// the api() cache with each sub-result first, then calls the existing
// widget loaders completely unchanged — each one's own internal api(...)
// call transparently resolves from the just-seeded cache instead of
// hitting the network again. If the batch call itself fails for any
// reason, the loaders still run right after and just make their normal,
// uncached calls — no special-case fallback needed.
async function loadHubWidgetsBatched() {
  const requests = [
    { action: "get_app_config", payload: {} },
    { action: "get_profile", payload: {} },
    { action: "get_friends", payload: {} },
    { action: "get_community_hub", payload: { pet_type: currentUserObj?.pet_type || "" } },
    { action: "get_ads", payload: {} },
    { action: "get_events", payload: { limit: 100 } },
  ];
  try {
    const batchData = await apiBatch(requests);
    if (batchData.status === "success" && Array.isArray(batchData.results)) {
      batchData.results.forEach((result, i) => {
        const resultData = { ...result };
        delete resultData.action;
        seedApiCache(requests[i].action, requests[i].payload, resultData);
      });
    }
  } catch (err) {
    console.error("Hub widget batch prefetch failed, falling back to individual calls:", err);
  }
  applyPetTypeTheme();
  loadHubHero();
  loadHubHighlight();
  loadHubAdsWidget();
  loadHubCalendarWidget();
}

async function loadHubHero() {
  const avatarSkeleton = document.getElementById("hub-hero-avatar-skeleton");
  const coverSkeleton = document.getElementById("hub-hero-cover-skeleton");
  try {
    const data = await api("get_profile", {});
    if (data.status !== "success" || !data.profile) {
      avatarSkeleton?.classList.add("hidden");
      coverSkeleton?.classList.add("hidden");
      return;
    }
    const p = data.profile;

    document.getElementById("hub-hero-pet-name").textContent = p.pet_name || "Unnamed pet";
    // Both skeletons are hidden by revealImageWhenLoaded() (profile.js) once
    // their actual <img> paints, not as soon as this API call resolves — see
    // that function's comment for why the timing matters.
    setAvatarPreview("hub-hero-avatar-img", "hub-hero-avatar-text", p.profile_photo_url, (p.pet_name || "P")[0], "hub-hero-avatar-skeleton");

    const tagsBox = document.getElementById("hub-hero-tags");
    if (tagsBox) {
      const tags = [p.pet_type, p.breed].filter(Boolean);
      tagsBox.innerHTML = tags.map((t) => `<span class="px-2.5 py-1 rounded-full text-xs font-bold bg-brand-100 dark:bg-brand-900/40 text-brand-700 dark:text-brand-300">${escapeHtml(t)}</span>`).join("");
    }

    api("get_friends", {}).then((fd) => {
      const countEl = document.getElementById("hub-hero-friends-count");
      if (!countEl || fd.status !== "success") return;
      const total = (fd.friends || []).length;
      // pcCountUp also clears the inline skeleton span this element ships with.
      if (typeof pcCountUp === "function") pcCountUp(countEl, total);
      else countEl.textContent = total;
    }).catch((err) => console.error(err));

    setCoverPreview("hub-hero-cover-img", p.cover_photo_url, "hub-hero-cover-skeleton");

    loadPetProfileVerificationBadge();
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    avatarSkeleton?.classList.add("hidden");
    coverSkeleton?.classList.add("hidden");
  }
}

// ---------------- Highlight slideshow (Care Guides + Rescue & Seva) ----------------
// Both tabs were removed from the primary nav bar, so this rotating
// slideshow is now their entry point from the Hub.

const HUB_SPOTLIGHT_ROTATE_MS = 7000;
let hubSpotlightSlides = [];
let hubSpotlightIndex = 0;
let hubSpotlightTimer = null;

async function loadHubHighlight() {
  const card = document.getElementById("hub-highlight-card");
  if (!card) return;
  try {
    const [hubData, rescueData] = await Promise.all([
      api("get_community_hub", { pet_type: currentUserObj?.pet_type || "" }),
      api("get_rescue_opportunities", {}),
    ]);

    const guideSlides = (hubData.status === "success" ? hubData.spotlight_guides || [] : []).map((guide) => {
      guidesCache[guide.id] = guide;
      return { type: "guide", id: guide.id, kicker: "Care Guide Spotlight", title: guide.title, text: guide.desc || "", icon: guide.icon || "sparkles" };
    });

    const rescueOpps = rescueData.status === "success" ? rescueData.opportunities || [] : [];
    const rescueSlides = rescueOpps.slice(0, 3).map((o) => ({
      type: "rescue", id: o.id, kicker: "Rescue & Seva Spotlight", title: o.title,
      text: [o.org, o.location].filter(Boolean).join(" · "), icon: "hand-heart",
    }));

    hubSpotlightSlides = [...guideSlides, ...rescueSlides];
    if (!hubSpotlightSlides.length) return;

    hubSpotlightIndex = 0;
    renderHubSpotlightSlide();
    card.classList.remove("hidden");

    clearInterval(hubSpotlightTimer);
    hubSpotlightTimer = setInterval(() => hubSpotlightGoTo(hubSpotlightIndex + 1), HUB_SPOTLIGHT_ROTATE_MS);
  } catch (err) {
    console.error(err);
  }
}

function renderHubSpotlightSlide() {
  const slide = hubSpotlightSlides[hubSpotlightIndex];
  if (!slide) return;

  document.getElementById("hub-highlight-kicker").textContent = slide.kicker;
  document.getElementById("hub-highlight-title").textContent = slide.title;
  document.getElementById("hub-highlight-text").textContent = slide.text;
  document.getElementById("hub-highlight-icon").setAttribute("data-lucide", slide.icon);
  document.getElementById("hub-highlight-cta").textContent = slide.type === "guide" ? "Read guide" : "View opportunity";

  const dotsBox = document.getElementById("hub-highlight-dots");
  if (dotsBox) {
    dotsBox.innerHTML = hubSpotlightSlides.length > 1
      ? hubSpotlightSlides.map((_, i) => `<button onclick="hubSpotlightGoTo(${i})" aria-label="Go to slide ${i + 1}" class="h-1.5 rounded-full transition-all ${i === hubSpotlightIndex ? "w-4 bg-brand-500" : "w-1.5 bg-gray-300 dark:bg-gray-700"}"></button>`).join("")
      : "";
  }
  if (window.lucide) lucide.createIcons();
}

function hubSpotlightGoTo(index) {
  if (!hubSpotlightSlides.length) return;
  hubSpotlightIndex = ((index % hubSpotlightSlides.length) + hubSpotlightSlides.length) % hubSpotlightSlides.length;
  renderHubSpotlightSlide();
  clearInterval(hubSpotlightTimer);
  hubSpotlightTimer = setInterval(() => hubSpotlightGoTo(hubSpotlightIndex + 1), HUB_SPOTLIGHT_ROTATE_MS);
}

function hubSpotlightNext() {
  hubSpotlightGoTo(hubSpotlightIndex + 1);
}

function hubSpotlightPrev() {
  hubSpotlightGoTo(hubSpotlightIndex - 1);
}

function openHubSpotlightItem() {
  const slide = hubSpotlightSlides[hubSpotlightIndex];
  if (!slide) return;
  if (slide.type === "guide") {
    switchSocialTab("guides");
    openGuideReader(slide.id);
  } else {
    switchSocialTab("rescue");
  }
}

// ---------------- Ads rail ----------------

async function loadHubAdsWidget() {
  const box = document.getElementById("hub-ads-widget");
  if (!box) return;
  try {
    const data = await api("get_ads", {});
    const ads = data.status === "success" ? (data.ads || []) : [];

    if (!ads.length) {
      box.innerHTML = `
        <div class="p-5 text-center border-2 border-dashed border-gray-200 dark:border-gray-700 rounded-[20px]">
          <i data-lucide="megaphone" class="w-6 h-6 mx-auto text-gray-400 mb-2"></i>
          <p class="text-xs font-bold text-gray-500 dark:text-gray-400">Advertise with us</p>
          <p class="text-[11px] text-gray-400 mt-1">Sponsored slots for local pet businesses coming soon.</p>
        </div>`;
      return;
    }

    const ad = ads[Math.floor(Math.random() * ads.length)];
    const inner = `
      <img src="${escapeHtml(ad.image_url)}" alt="${escapeHtml(ad.title)}" class="w-full h-32 object-cover">
      <div class="p-3">
        <p class="text-xs font-bold text-gray-800 dark:text-gray-100 truncate">${escapeHtml(ad.title)}</p>
      </div>`;
    box.innerHTML = ad.link_url
      ? `<a href="${escapeHtml(ad.link_url)}" target="_blank" rel="noopener sponsored">${inner}</a>`
      : inner;
  } catch (err) {
    console.error(err);
  }
}

// ---------------- Calendar widget ----------------

let hubCalendarMonth = new Date().getMonth();
let hubCalendarYear = new Date().getFullYear();
let hubCalendarEventDays = new Set();

async function loadHubCalendarWidget() {
  try {
    const data = await api("get_events", { limit: 100 });
    hubCalendarEventDays = new Set();
    if (data.status === "success") {
      (data.events || []).forEach((e) => {
        if (e.event_date) hubCalendarEventDays.add(e.event_date);
      });
    }
    renderHubCalendarWidget();
  } catch (err) {
    console.error(err);
  }
}

function hubCalendarChangeMonth(delta) {
  hubCalendarMonth += delta;
  if (hubCalendarMonth < 0) { hubCalendarMonth = 11; hubCalendarYear--; }
  if (hubCalendarMonth > 11) { hubCalendarMonth = 0; hubCalendarYear++; }
  renderHubCalendarWidget();
}

function openHubCalendarDay(dateKey) {
  switchSocialTab("events");
  openCreateEventModal(dateKey);
}

function renderHubCalendarWidget() {
  const box = document.getElementById("hub-calendar-widget");
  if (!box) return;

  const today = new Date();
  const monthLabel = new Date(hubCalendarYear, hubCalendarMonth, 1).toLocaleString(undefined, { month: "long", year: "numeric" });
  const firstWeekday = new Date(hubCalendarYear, hubCalendarMonth, 1).getDay();
  const daysInMonth = new Date(hubCalendarYear, hubCalendarMonth + 1, 0).getDate();

  const cells = [];
  for (let i = 0; i < firstWeekday; i++) cells.push("");
  for (let d = 1; d <= daysInMonth; d++) cells.push(d);

  const dateKey = (d) => `${hubCalendarYear}-${String(hubCalendarMonth + 1).padStart(2, "0")}-${String(d).padStart(2, "0")}`;
  const isToday = (d) => d === today.getDate() && hubCalendarMonth === today.getMonth() && hubCalendarYear === today.getFullYear();

  box.innerHTML = `
    <div class="flex items-center justify-between mb-3">
      <button onclick="hubCalendarChangeMonth(-1)" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500"><i data-lucide="chevron-left" class="w-4 h-4"></i></button>
      <h4 class="text-sm font-bold text-gray-800 dark:text-gray-100">${escapeHtml(monthLabel)}</h4>
      <div class="flex gap-1">
        <button onclick="openEnlargedCalendarModal()" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-brand-500"><i data-lucide="maximize-2" class="w-4 h-4"></i></button>
        <button onclick="hubCalendarChangeMonth(1)" class="p-1 rounded-lg hover:bg-gray-100 dark:hover:bg-gray-800 text-gray-500"><i data-lucide="chevron-right" class="w-4 h-4"></i></button>
      </div>
    </div>
    <div class="grid grid-cols-7 gap-1 text-center text-[10px] font-bold text-gray-400 mb-1">
      ${["S", "M", "T", "W", "T", "F", "S"].map((d) => `<span>${d}</span>`).join("")}
    </div>
    <div class="grid grid-cols-7 gap-1">
      ${cells.map((d) => {
        if (!d) return `<span></span>`;
        const hasEvent = hubCalendarEventDays.has(dateKey(d));
        return `<div class="relative flex flex-col items-center">
          <button type="button" onclick="openHubCalendarDay('${dateKey(d)}')" class="w-7 h-7 rounded-full flex items-center justify-center text-xs hover:ring-2 hover:ring-brand-300 transition-all ${isToday(d) ? "bg-brand-500 text-white font-bold" : "text-gray-700 dark:text-gray-300"}">${d}</button>
          ${hasEvent ? `<span class="w-1 h-1 rounded-full bg-brand-500 -mt-0.5"></span>` : ""}
        </div>`;
      }).join("")}
    </div>`;
  if (window.lucide) lucide.createIcons();
}
