// Pet Community Hub: a live, DB-backed discovery tab (trending posts,
// spotlighted care guides, active groups, upcoming events, fresh galleries).
// See api/routes/community_hub.php for why this replaces eSamaj's "Pravachan
// Hub" instead of porting it as-is — that view was 100% hardcoded demo data.

function hubGroupCardHtml(group) {
  const initial = escapeHtml((group.name || "G")[0]);
  return `
    <button onclick="switchSocialTab('groups')" class="text-left warm-glass warm-lift rounded-2xl p-4 flex items-center gap-3">
      <div class="w-11 h-11 rounded-xl bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden flex-shrink-0">
        ${group.avatar_url ? `<img src="${escapeHtml(group.avatar_url)}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300">${initial}</span>`}
      </div>
      <div class="min-w-0 flex-1">
        <p class="font-bold text-sm text-gray-900 dark:text-white truncate">${escapeHtml(group.name)}</p>
        <p class="text-xs text-gray-400">${group.member_count} member${group.member_count === 1 ? "" : "s"}</p>
      </div>
    </button>`;
}

function hubEventCardHtml(ev) {
  const date = ev.event_date ? new Date(ev.event_date + "T00:00:00") : null;
  const day = date ? date.getDate() : "?";
  const month = date ? date.toLocaleString(undefined, { month: "short" }).toUpperCase() : "";
  return `
    <button onclick="switchSocialTab('events')" class="text-left w-full warm-glass warm-lift rounded-2xl p-4 flex items-center gap-4">
      <div class="w-12 h-12 rounded-xl bg-brand-50 dark:bg-brand-900/30 flex flex-col items-center justify-center flex-shrink-0">
        <span class="text-[10px] font-bold text-brand-500 leading-none">${month}</span>
        <span class="text-base font-extrabold text-gray-900 dark:text-white leading-tight">${day}</span>
      </div>
      <div class="min-w-0 flex-1">
        <p class="font-bold text-sm text-gray-900 dark:text-white truncate">${escapeHtml(ev.title)}</p>
        <p class="text-xs text-gray-400 truncate">${escapeHtml(ev.location || (ev.is_online ? "Online" : ""))}</p>
      </div>
    </button>`;
}

function hubGalleryCardHtml(gallery) {
  return `
    <button onclick="switchSocialTab('galleries')" class="relative aspect-square rounded-xl overflow-hidden bg-gray-100 dark:bg-gray-800 group">
      ${gallery.cover_url
      ? `<img src="${escapeHtml(gallery.cover_url)}" alt="" class="w-full h-full object-cover">`
      : `<div class="w-full h-full flex items-center justify-center text-gray-300 dark:text-gray-600"><i data-lucide="image" class="w-6 h-6"></i></div>`}
      <span class="absolute inset-x-0 bottom-0 bg-gradient-to-t from-black/70 to-transparent text-white text-xs font-semibold p-2 truncate">${escapeHtml(gallery.title || "Gallery")}</span>
    </button>`;
}

function hubSectionHtml(title, icon, innerHtml, emptyText) {
  return `
    <section>
      <div class="flex items-center gap-2 mb-3">
        <i data-lucide="${icon}" class="w-4 h-4 text-brand-500"></i>
        <h3 class="text-sm font-bold uppercase tracking-wide text-gray-500 dark:text-gray-400">${escapeHtml(title)}</h3>
      </div>
      ${innerHtml || `<p class="text-sm text-gray-400 py-4">${escapeHtml(emptyText)}</p>`}
    </section>`;
}

async function loadCommunityHubTab() {
  const container = document.getElementById("hub-content");
  if (!container) return;
  container.innerHTML = `<div class="space-y-8">
    ${hubSectionHtml("Trending in your pack", "flame", postCardSkeletonListHtml(2), "")}
    ${hubSectionHtml("Care guide spotlight", "book-open-text", `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">${rowCardSkeletonListHtml(3)}</div>`, "")}
    ${hubSectionHtml("Active groups", "users", `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">${rowCardSkeletonListHtml(2)}</div>`, "")}
  </div>`;

  try {
    const data = await api("get_community_hub", { pet_type: currentUserObj?.pet_type || "" });
    if (data.status !== "success") {
      container.innerHTML = `<p class="text-center text-sm text-gray-400 py-8">Could not load the community hub.</p>`;
      return;
    }

    (data.spotlight_guides || []).forEach((g) => { guidesCache[g.id] = g; });

    const sections = [
      hubSectionHtml("Trending in your pack", "flame",
        (data.trending_posts || []).length ? `<div class="space-y-4">${data.trending_posts.map(postCardHtml).join("")}</div>` : "",
        "No posts yet — be the first to share something with your pack."),
      hubSectionHtml("Care guide spotlight", "book-open-text",
        (data.spotlight_guides || []).length ? `<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-3 gap-3">${data.spotlight_guides.map(guideCardHtml).join("")}</div>` : "",
        "Guides are on the way."),
      hubSectionHtml("Active groups", "users",
        (data.active_groups || []).length ? `<div class="grid grid-cols-1 sm:grid-cols-2 gap-3">${data.active_groups.map(hubGroupCardHtml).join("")}</div>` : "",
        "No groups yet — start one from the Groups tab."),
      hubSectionHtml("Upcoming events", "calendar",
        (data.upcoming_events || []).length ? `<div class="space-y-2">${data.upcoming_events.map(hubEventCardHtml).join("")}</div>` : "",
        "No upcoming events."),
      hubSectionHtml("Fresh galleries", "images",
        (data.fresh_galleries || []).length ? `<div class="grid grid-cols-3 sm:grid-cols-6 gap-2">${data.fresh_galleries.map(hubGalleryCardHtml).join("")}</div>` : "",
        "No public galleries yet."),
    ];

    container.innerHTML = `<div class="space-y-8">${sections.join("")}</div>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    container.innerHTML = `<p class="text-center text-sm text-gray-400 py-8">Could not load the community hub.</p>`;
  }
}
