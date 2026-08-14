// Social Hub: hero/cover card (the logged-in pet's own summary, shown above
// the tab strip content) and the right-rail widgets (Highlight card, Ads
// rail, Calendar) — the structural pieces eSamaj's social feed page has that
// this rebuild's hub was missing entirely until now. Every widget here is
// backed by an already-built, already-tested endpoint (get_profile,
// get_community_hub, get_ads, get_events) rather than any new or fabricated
// content.

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
      if (countEl && fd.status === "success") countEl.textContent = (fd.friends || []).length;
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

// ---------------- Highlight card (care guide spotlight) ----------------

let hubHighlightGuideId = null;

async function loadHubHighlight() {
  const card = document.getElementById("hub-highlight-card");
  if (!card) return;
  try {
    const data = await api("get_community_hub", { pet_type: currentUserObj?.pet_type || "" });
    if (data.status !== "success") return;

    const guides = data.spotlight_guides || [];
    if (!guides.length) return;

    const guide = guides[Math.floor(Math.random() * guides.length)];
    guidesCache[guide.id] = guide;
    hubHighlightGuideId = guide.id;

    document.getElementById("hub-highlight-title").textContent = guide.title;
    document.getElementById("hub-highlight-text").textContent = guide.desc || "";
    document.getElementById("hub-highlight-icon").setAttribute("data-lucide", guide.icon || "sparkles");
    card.classList.remove("hidden");
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
  }
}

function openHubHighlightGuide() {
  if (!hubHighlightGuideId) return;
  switchSocialTab("guides");
  openGuideReader(hubHighlightGuideId);
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
