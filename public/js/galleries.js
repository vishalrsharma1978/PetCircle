// Galleries: full "Gallery Library" experience ported from eSamaj's
// public/js/galleries.js — photo-stack collage cards, search/filter/sort,
// a create/edit modal with file+URL media staging, and a lightbox slideshow.
// Differences from eSamaj's version:
//  - Accent color is keyed on pet_type (reusing PET_TYPE_PREVIEW_ACCENTS from
//    core.js) instead of religion.
//  - No-media placeholder tiles are a generic icon treatment, not eSamaj's
//    static religion-themed images (img/bg_hindu.png etc. don't exist here,
//    and inventing fake asset paths would violate the no-fabrication rule).
//  - This tab shows only the signed-in pet parent's own galleries (matches
//    eSamaj's actual behavior — its "Gallery library" is a personal
//    management view with edit/delete on every card, not a public feed).
//  - No "Edit event" button or link-gallery-to-existing-event flow — this
//    rebuild has no event-edit modal to hook it to.
//  - Confirmations use the existing plain confirm() convention used
//    elsewhere in this codebase rather than porting eSamaj's showConfirm().

const GALLERY_MEDIA_MAX_BYTES = 25 * 1024 * 1024;
const GALLERY_IMAGE_TYPES = ["image/jpeg", "image/png", "image/webp", "image/gif"];
const GALLERY_VIDEO_TYPES = ["video/mp4", "video/webm", "video/quicktime", "video/x-m4v"];
const SAFE_GALLERY_MEDIA_URL_EXTENSIONS = /\.(jpe?g|png|webp|gif|mp4|webm|mov|m4v)(\?.*)?$/i;
const GALLERY_EDIT_VISIBLE_LIMIT = 5;

let galleriesCache = [];
let eventsCacheForGalleries = [];
let galleryLibraryFilters = { query: "", type: "all", sort: "newest" };
let galleryModalUploadInProgress = false;
let galleryModalStagedFiles = [];
let galleryEditItemsExpanded = false;
let activeGallerySlideshow = { galleryId: null, index: 0, items: [] };

function normalizeSafeMediaUrl(rawUrl) {
  const value = String(rawUrl || "").trim();
  if (!value) return "";
  let parsed;
  try {
    parsed = new URL(value);
  } catch (e) {
    return "";
  }
  if (parsed.protocol !== "https:") return "";
  if (!SAFE_GALLERY_MEDIA_URL_EXTENSIONS.test(parsed.pathname + parsed.search)) return "";
  return parsed.href;
}

function isTemporaryBrowserMediaUrl(url = "") {
  return /^(blob:|data:|filesystem:)/i.test(String(url || "").trim());
}

// ---------------- Data loading ----------------

async function loadGalleriesTab() {
  const container = document.getElementById("social-tab-galleries");
  if (!container) return;
  container.innerHTML = `
    <section class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sm:p-6">
      <div class="h-8 w-56 rounded-full bg-gray-100 dark:bg-gray-800 animate-pulse"></div>
      <div class="mt-5 grid grid-cols-1 gap-5">
        ${Array.from({ length: 4 }).map(() => `<div class="max-w-md mx-auto w-full h-72 rounded-[1.75rem] bg-gray-100 dark:bg-gray-800 animate-pulse"></div>`).join("")}
      </div>
    </section>`;
  try {
    const [galleriesData, eventsData] = await Promise.all([
      api("get_galleries", { owner_user_id: currentUserObj?.id || "" }),
      api("get_events", { pet_type: currentUserObj?.pet_type || "" }),
    ]);
    galleriesCache = galleriesData.status === "success" ? (galleriesData.galleries || []) : [];
    eventsCacheForGalleries = eventsData.status === "success" ? (eventsData.events || []) : [];
  } catch (err) {
    console.error(err);
    galleriesCache = [];
    eventsCacheForGalleries = [];
  }
  renderMainGalleriesTab();
}

// ---------------- Filter / sort ----------------

function filteredGalleryLibraryItems() {
  const { query, type, sort } = galleryLibraryFilters;
  const q = query.trim().toLowerCase();
  const eventTitleById = new Map(eventsCacheForGalleries.map((e) => [String(e.id), e.title || "Untitled event"]));

  let items = galleriesCache.filter((gallery) => {
    const itemCount = Array.isArray(gallery.items) ? gallery.items.length : 0;
    const haystack = [
      gallery.title,
      gallery.description,
      gallery.visibility,
      gallery.event_id ? eventTitleById.get(String(gallery.event_id)) : "independent",
      String(itemCount),
    ].filter(Boolean).join(" ").toLowerCase();
    if (q && !haystack.includes(q)) return false;
    if (type === "linked" && !gallery.event_id) return false;
    if (type === "independent" && gallery.event_id) return false;
    if (type === "empty" && itemCount > 0) return false;
    if (type === "media" && itemCount === 0) return false;
    return true;
  });

  items.sort((a, b) => {
    const aCount = Array.isArray(a.items) ? a.items.length : 0;
    const bCount = Array.isArray(b.items) ? b.items.length : 0;
    if (sort === "oldest") return new Date(a.created_at || 0) - new Date(b.created_at || 0);
    if (sort === "title") return String(a.title || "").localeCompare(String(b.title || ""));
    if (sort === "items") return bCount - aCount;
    return new Date(b.created_at || 0) - new Date(a.created_at || 0);
  });
  return items;
}

function updateGalleryLibraryFilter(key, value) {
  galleryLibraryFilters = { ...galleryLibraryFilters, [key]: value };
  renderGalleryLibraryResults();
}

function renderNoGalleriesFoundHtml(title, text) {
  return `
    <div class="rounded-2xl border border-dashed border-gray-200 dark:border-gray-800 bg-white dark:bg-gray-900 p-8 text-center">
      <i data-lucide="image-off" class="w-10 h-10 mx-auto text-gray-300"></i>
      <p class="mt-3 font-bold text-gray-900 dark:text-white">${escapeHtml(title)}</p>
      <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">${escapeHtml(text)}</p>
    </div>`;
}

function renderGalleryLibraryResultsHtml(galleries) {
  return galleries.length
    ? `<div class="grid grid-cols-1 gap-5">${galleries.map(renderGalleryAlbumCardHtml).join("")}</div>`
    : renderNoGalleriesFoundHtml("No galleries match", "Try a different search, filter, or sort option.");
}

function renderGalleryLibraryResults() {
  const visible = filteredGalleryLibraryItems();
  const countEl = document.getElementById("gallery-library-result-count");
  const resultsEl = document.getElementById("gallery-library-results");
  if (countEl) countEl.textContent = `${visible.length} result${visible.length === 1 ? "" : "s"}`;
  if (resultsEl) {
    resultsEl.innerHTML = renderGalleryLibraryResultsHtml(visible);
    if (window.lucide) lucide.createIcons();
  }
}

// ---------------- Main tab render ----------------

function renderMainGalleriesTab() {
  const container = document.getElementById("social-tab-galleries");
  if (!container) return;
  const visible = filteredGalleryLibraryItems();
  const filters = galleryLibraryFilters;
  const linkedCount = galleriesCache.filter((g) => g.event_id).length;
  const independentCount = galleriesCache.length - linkedCount;
  const mediaCount = galleriesCache.reduce((total, g) => total + (Array.isArray(g.items) ? g.items.length : 0), 0);
  const typeLabels = { all: "All galleries", linked: "Event linked", independent: "Independent", media: "Has media", empty: "Empty" };
  const sortLabels = { newest: "Newest first", oldest: "Oldest first", title: "Title A-Z", items: "Most media" };

  container.innerHTML = `
    <section class="rounded-2xl border border-gray-100 dark:border-gray-800 bg-white dark:bg-gray-900 p-5 sm:p-6">
      <div class="flex flex-col lg:flex-row lg:items-start lg:justify-between gap-4">
        <div>
          <h3 class="text-2xl font-black text-gray-900 dark:text-white font-heading">Gallery library</h3>
          <p class="text-sm text-gray-500 dark:text-gray-400 mt-1">Search, filter, and sort every gallery on your account.</p>
        </div>
        <div class="grid grid-cols-3 gap-2 text-center">
          <div class="rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-100 dark:border-gray-800 px-4 py-3">
            <p class="text-lg font-black text-gray-900 dark:text-white">${galleriesCache.length}</p>
            <p class="text-[11px] font-bold uppercase text-gray-400">Galleries</p>
          </div>
          <div class="rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-100 dark:border-gray-800 px-4 py-3">
            <p class="text-lg font-black text-gray-900 dark:text-white">${mediaCount}</p>
            <p class="text-[11px] font-bold uppercase text-gray-400">Media</p>
          </div>
          <div class="rounded-2xl bg-gray-50 dark:bg-gray-950 border border-gray-100 dark:border-gray-800 px-4 py-3">
            <p class="text-lg font-black text-gray-900 dark:text-white">${linkedCount}/${independentCount}</p>
            <p class="text-[11px] font-bold uppercase text-gray-400">Linked/free</p>
          </div>
        </div>
      </div>
      <div class="mt-6 grid lg:grid-cols-[1fr_180px_180px] gap-3">
        <label class="relative">
          <i data-lucide="search" class="absolute left-3 top-1/2 -translate-y-1/2 w-4 h-4 text-gray-400"></i>
          <input value="${escapeHtml(filters.query || "")}" oninput="updateGalleryLibraryFilter('query', this.value)" placeholder="Search title, event, visibility, or media count"
            class="w-full pl-10 pr-3 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-brand-500">
        </label>
        <select onchange="updateGalleryLibraryFilter('type', this.value)" class="px-3 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-brand-500">
          ${Object.keys(typeLabels).map((value) => `<option value="${value}" ${filters.type === value ? "selected" : ""}>${escapeHtml(typeLabels[value])}</option>`).join("")}
        </select>
        <select onchange="updateGalleryLibraryFilter('sort', this.value)" class="px-3 py-3 rounded-xl border border-gray-200 dark:border-gray-700 bg-gray-50 dark:bg-gray-800 text-sm text-gray-900 dark:text-white focus:outline-none focus:border-brand-500">
          ${Object.keys(sortLabels).map((value) => `<option value="${value}" ${filters.sort === value ? "selected" : ""}>${escapeHtml(sortLabels[value])}</option>`).join("")}
        </select>
      </div>
      <div class="mt-6 flex items-center justify-between gap-3">
        <p id="gallery-library-result-count" class="text-sm text-gray-500 dark:text-gray-400">${visible.length} result${visible.length === 1 ? "" : "s"}</p>
        <button type="button" onclick="openCreateGalleryModal()" class="inline-flex items-center justify-center gap-2 px-3 py-2 rounded-xl bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold">
          <i data-lucide="plus" class="w-4 h-4"></i> Create gallery
        </button>
      </div>
      <div id="gallery-library-results" class="mt-5">${renderGalleryLibraryResultsHtml(visible)}</div>
    </section>`;
  if (window.lucide) lucide.createIcons();
}

// ---------------- Media preview + photo-stack collage ----------------

function galleryItemIsVideo(item) {
  const type = String(item?.media_type || item?.type || "").toLowerCase();
  const url = String(item?.media_url || item?.url || "");
  return type === "video" || /\.(mp4|webm|mov|m4v)(\?|$)/i.test(url);
}

function renderGalleryMediaPreviewHtml(item, classes = "") {
  const url = item?.media_url || item?.url || "";
  if (galleryItemIsVideo(item)) {
    return `<video src="${escapeHtml(url)}" muted playsinline preload="metadata" class="${classes}"></video>`;
  }
  return `<img src="${escapeHtml(url)}" alt="" loading="lazy" class="${classes}">`;
}

// A flex-based overlapping card stack, replacing the earlier absolute-position
// + CSS-custom-property transform math (ported from eSamaj, then re-anchored
// twice) that kept rendering off-center — pixel offsets tuned against a card
// width I can't actually see never lined up right without a browser to check
// against. Flexbox with `justify-content: center` guarantees the whole
// cluster is centered by construction regardless of tile count/size, so
// there's no pixel math left to get wrong. Each tile overlaps the previous
// one via a negative margin (see .gallery-preview-tile in main.css) and gets
// a small fixed rotation for the fanned-deck look; hovering a tile just lifts
// and un-rotates that one tile in place.
const GALLERY_PREVIEW_ROTATIONS = {
  1: [0],
  2: [-6, 6],
  3: [-8, 0, 8],
  4: [-10, -3, 4, 11],
};

function galleryPreviewRotation(index, previewCount) {
  const set = GALLERY_PREVIEW_ROTATIONS[Math.min(Math.max(previewCount, 1), 4)] || GALLERY_PREVIEW_ROTATIONS[4];
  return set[index] ?? 0;
}

function renderGalleryPreviewCardHtml(item, index, extraCount, galleryId, previewCount = 4) {
  const rotation = galleryPreviewRotation(index, previewCount);
  const moreHtml = extraCount > 0
    ? `<div class="settings-gallery-more-card absolute inset-0 flex flex-col items-center justify-center text-white backdrop-blur-[1px]">
         <span class="text-3xl font-black">+${extraCount}</span>
         <span class="text-xs font-bold uppercase tracking-wide text-white/75">more</span>
       </div>`
    : "";
  return `<div class="gallery-preview-tile w-36 h-44 rounded-2xl overflow-hidden shadow-2xl bg-gray-100 dark:bg-gray-800 cursor-pointer" style="transform:rotate(${rotation}deg);z-index:${10 + index};" onclick="event.stopPropagation(); openGallerySlideshow('${galleryId}', ${index})">
      ${renderGalleryMediaPreviewHtml(item, "w-full h-full object-cover pointer-events-none")}
      ${moreHtml}
    </div>`;
}

function renderGalleryPlaceholderPreviewHtml(index, previewCount) {
  const rotation = galleryPreviewRotation(index, previewCount);
  return `<div class="gallery-preview-tile settings-gallery-placeholder w-36 h-44 rounded-2xl overflow-hidden shadow-2xl flex items-center justify-center text-white/80" style="transform:rotate(${rotation}deg);z-index:${10 + index};">
      <i data-lucide="paw-print" class="w-8 h-8"></i>
    </div>`;
}

function getGalleryAccentColor(petType) {
  return (typeof PET_TYPE_PREVIEW_ACCENTS !== "undefined" && (PET_TYPE_PREVIEW_ACCENTS[petType] || PET_TYPE_PREVIEW_ACCENTS[""])) || "#e04848";
}

function renderGalleryAlbumCardHtml(gallery) {
  const linkedEvent = gallery.event_id ? eventsCacheForGalleries.find((e) => String(e.id) === String(gallery.event_id)) : null;
  const items = Array.isArray(gallery.items) ? gallery.items : [];
  const previewHtml = items.length
    ? items.slice(0, 4).map((item, index) => renderGalleryPreviewCardHtml(item, index, index === 3 ? Math.max(0, items.length - 4) : 0, gallery.id, Math.min(items.length, 4))).join("")
    : Array.from({ length: 4 }).map((_, index) => renderGalleryPlaceholderPreviewHtml(index, 4)).join("");
  const safeGalleryId = escapeHtml(String(gallery.id || ""));
  const accentColor = getGalleryAccentColor(currentUserObj?.pet_type);
  const cardStyle = `--gallery-accent:${accentColor};border-color:color-mix(in srgb, var(--gallery-accent) 34%, transparent);box-shadow:inset 0 0 0 1px color-mix(in srgb, var(--gallery-accent) 14%, transparent),0 14px 30px rgba(15,23,42,.06);`;
  return `
    <article class="group max-w-md mx-auto w-full rounded-[1.75rem] border bg-white dark:bg-gray-800 min-h-72 overflow-hidden shadow-sm relative" style="${cardStyle}">
      <div class="absolute inset-x-6 top-0 h-1 rounded-b-full opacity-80" style="background:linear-gradient(90deg, transparent, var(--gallery-accent), transparent);"></div>
      <div class="absolute inset-0 rounded-[1.75rem] pointer-events-none" style="background:linear-gradient(135deg, color-mix(in srgb, var(--gallery-accent) 7%, transparent), transparent 42%);"></div>
      <div class="absolute left-6 top-6 z-10 pointer-events-none">
        <h5 class="text-gray-900 dark:text-white text-xl font-black truncate max-w-52">${escapeHtml(gallery.title || "Untitled gallery")}</h5>
        <p class="text-gray-600 dark:text-gray-400 text-xs mt-1">${items.length} item${items.length === 1 ? "" : "s"}${linkedEvent ? ` · ${escapeHtml(linkedEvent.title || "Linked event")}` : ""}</p>
      </div>
      <div class="absolute left-6 bottom-6 z-20 flex flex-wrap gap-2">
        <button type="button" onclick="event.stopPropagation(); openEditGalleryModal('${safeGalleryId}')" class="no-accent-hover inline-flex items-center gap-1.5 rounded-xl bg-gray-100 dark:bg-gray-700 border border-gray-200 dark:border-gray-600 px-3 py-2 text-xs font-bold text-gray-800 dark:text-gray-100 hover:bg-gray-200 dark:hover:bg-gray-600 transition-colors">
          <i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit
        </button>
        <button type="button" onclick="event.stopPropagation(); openGallerySlideshow('${safeGalleryId}')" class="no-accent-hover inline-flex items-center gap-1.5 rounded-xl px-3 py-2 text-xs font-bold text-white border" style="background:var(--gallery-accent);border-color:color-mix(in srgb, var(--gallery-accent) 80%, #111827);">
          <i data-lucide="images" class="w-3.5 h-3.5"></i> Browse
        </button>
        <button type="button" onclick="event.stopPropagation(); deleteGallery('${safeGalleryId}', this)" class="no-accent-hover inline-flex items-center gap-1.5 rounded-xl bg-gray-50 dark:bg-gray-800 border border-red-200 dark:border-red-900/70 px-3 py-2 text-xs font-bold text-red-600 dark:text-red-300 hover:bg-red-50 dark:hover:bg-red-900/30 transition-colors">
          <i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete
        </button>
      </div>
      <div class="absolute inset-0 flex items-center justify-center cursor-pointer" onclick="openGallerySlideshow('${safeGalleryId}')">
        ${previewHtml}
      </div>
    </article>`;
}

// ---------------- Create / edit modal ----------------

function renderGalleryEventOptions(selectedEventId = "") {
  const select = document.getElementById("gallery-modal-event");
  if (!select) return;
  const options = eventsCacheForGalleries.map((e) =>
    `<option value="${escapeHtml(e.id)}" ${String(e.id) === String(selectedEventId) ? "selected" : ""}>${escapeHtml(e.title || "Untitled event")}</option>`).join("");
  select.innerHTML = `<option value="">Independent gallery</option>${options}`;
}

function openCreateGalleryModal() {
  const modal = document.getElementById("create-gallery-modal");
  if (!modal) return;
  document.getElementById("gallery-modal-id").value = "";
  document.getElementById("gallery-modal-heading").textContent = "Create gallery";
  document.getElementById("gallery-modal-subtitle").textContent = "Optional event link included.";
  document.getElementById("gallery-modal-submit").textContent = "Create gallery";
  document.getElementById("gallery-modal-title").value = "";
  document.getElementById("gallery-modal-desc").value = "";
  const visibilityEl = document.getElementById("gallery-modal-visibility");
  if (visibilityEl) visibilityEl.value = "private";
  document.getElementById("gallery-modal-media").value = "";
  resetGalleryModalUploadUi();
  renderEditableGalleryItems(null);
  renderGalleryEventOptions("");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeCreateGalleryModal() {
  const modal = document.getElementById("create-gallery-modal");
  if (!modal) return;
  galleryModalStagedFiles.forEach((item) => {
    if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
  });
  galleryModalStagedFiles = [];
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

function openEditGalleryModal(galleryId) {
  const gallery = galleriesCache.find((item) => String(item.id) === String(galleryId));
  if (!gallery) {
    showToast("Gallery not found.", "error");
    return;
  }
  const modal = document.getElementById("create-gallery-modal");
  if (!modal) return;
  document.getElementById("gallery-modal-id").value = gallery.id || "";
  document.getElementById("gallery-modal-heading").textContent = "Edit gallery";
  document.getElementById("gallery-modal-subtitle").textContent = "Update details, add media URLs, or remove existing items.";
  document.getElementById("gallery-modal-submit").textContent = "Save gallery";
  document.getElementById("gallery-modal-title").value = gallery.title || "";
  document.getElementById("gallery-modal-desc").value = gallery.description || "";
  document.getElementById("gallery-modal-media").value = "";
  resetGalleryModalUploadUi();
  const visibilityEl = document.getElementById("gallery-modal-visibility");
  if (visibilityEl) visibilityEl.value = gallery.visibility || "private";
  renderGalleryEventOptions(gallery.event_id || "");
  renderEditableGalleryItems(gallery);
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function currentEditingGallery() {
  const galleryId = (document.getElementById("gallery-modal-id")?.value || "").trim();
  if (!galleryId) return null;
  return galleriesCache.find((item) => String(item.id) === String(galleryId)) || null;
}

function renderEditableGalleryItems(gallery) {
  const container = document.getElementById("gallery-modal-items");
  if (!container) return;
  const items = Array.isArray(gallery?.items) ? gallery.items : [];
  container.classList.toggle("hidden", !gallery);
  if (!gallery) {
    container.innerHTML = "";
    return;
  }

  const tile = (item) => `
    <div class="relative rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 aspect-[4/3]">
      ${renderGalleryMediaPreviewHtml(item, "w-full h-full object-cover")}
      <button type="button" onclick="deleteGalleryItem('${escapeHtml(gallery.id)}','${escapeHtml(item.id)}', this)" class="no-accent-hover absolute top-2 right-2 w-8 h-8 inline-flex items-center justify-center rounded-full bg-black/70 text-white">
        <i data-lucide="trash-2" class="w-4 h-4"></i>
      </button>
    </div>`;

  const collapse = !galleryEditItemsExpanded && items.length > GALLERY_EDIT_VISIBLE_LIMIT;
  const visible = collapse ? items.slice(0, GALLERY_EDIT_VISIBLE_LIMIT) : items;
  const hiddenCount = items.length - visible.length;

  const lastVisible = visible[visible.length - 1];
  const moreTile = collapse && lastVisible
    ? `<div class="relative rounded-xl overflow-hidden border border-gray-100 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 aspect-[4/3] cursor-pointer" onclick="expandGalleryEditItems()">
         ${renderGalleryMediaPreviewHtml(lastVisible, "w-full h-full object-cover")}
         <div class="absolute inset-0 flex flex-col items-center justify-center text-white bg-black/55 backdrop-blur-[1px]">
           <span class="text-2xl font-black leading-none">+${hiddenCount + 1}</span>
           <span class="text-[10px] font-bold uppercase tracking-wide text-white/80">more</span>
         </div>
       </div>`
    : "";

  const tilesToRender = collapse ? visible.slice(0, -1) : visible;

  container.innerHTML = `
    <div class="flex items-center justify-between gap-3">
      <p class="text-sm font-bold text-gray-700 dark:text-gray-300">Current media</p>
      <div class="flex items-center gap-3">
        <span class="text-xs text-gray-500 dark:text-gray-400">${items.length} item${items.length === 1 ? "" : "s"}</span>
        ${items.length > GALLERY_EDIT_VISIBLE_LIMIT && galleryEditItemsExpanded
      ? `<button type="button" onclick="collapseGalleryEditItems()" class="no-accent-hover text-xs font-bold text-brand-500 hover:underline">Show less</button>`
      : ""}
      </div>
    </div>
    ${items.length
      ? `<div class="mt-2 grid grid-cols-2 sm:grid-cols-3 gap-2">${tilesToRender.map(tile).join("")}${moreTile}</div>`
      : `<div class="mt-2 rounded-xl border border-dashed border-gray-200 dark:border-gray-800 p-4 text-sm text-gray-500 dark:text-gray-400">No media in this gallery yet.</div>`}`;
  if (window.lucide) lucide.createIcons();
}

function expandGalleryEditItems() {
  galleryEditItemsExpanded = true;
  const gallery = currentEditingGallery();
  if (gallery) renderEditableGalleryItems(gallery);
}

function collapseGalleryEditItems() {
  galleryEditItemsExpanded = false;
  const gallery = currentEditingGallery();
  if (gallery) renderEditableGalleryItems(gallery);
}

// ---------------- Media staging (files + pasted URLs) ----------------

function renderGalleryStagedMedia() {
  const container = document.getElementById("gallery-modal-staged");
  const textarea = document.getElementById("gallery-modal-media");
  if (!container || !textarea) return;
  const urls = parseGalleryMediaUrls(textarea.value);
  const stagedFiles = Array.isArray(galleryModalStagedFiles) ? galleryModalStagedFiles : [];
  if (!urls.length && !stagedFiles.length) {
    container.classList.add("hidden");
    container.innerHTML = "";
    return;
  }
  container.classList.remove("hidden");
  const combined = [
    ...urls.map((url, index) => ({ type: "url", url, index })),
    ...stagedFiles.map((item, index) => ({ type: "file", item, index })),
  ];
  container.innerHTML = `
    <p class="text-xs font-bold text-gray-600 dark:text-gray-400 mb-2">New media to add (${combined.length})</p>
    <div class="grid grid-cols-3 sm:grid-cols-4 gap-2">
      ${combined.map((entry) => {
    const previewUrl = entry.type === "file" ? entry.item.previewUrl : entry.url;
    const mediaType = entry.type === "file" ? entry.item.media_type : galleryMediaTypeFromUrl(entry.url);
    const removeCall = entry.type === "file" ? `removeStagedGalleryFile(${entry.index})` : `removeStagedGalleryMedia(${entry.index})`;
    return `
        <div class="relative rounded-lg overflow-hidden border border-gray-100 dark:border-gray-800 bg-gray-100 dark:bg-gray-800 aspect-square">
          ${renderGalleryMediaPreviewHtml({ media_url: previewUrl, media_type: mediaType }, "w-full h-full object-cover")}
          <button type="button" onclick="${removeCall}" class="no-accent-hover absolute top-1 right-1 w-6 h-6 inline-flex items-center justify-center rounded-full bg-black/70 text-white" title="Remove">
            <i data-lucide="x" class="w-3.5 h-3.5"></i>
          </button>
        </div>`;
  }).join("")}
    </div>`;
  if (window.lucide) lucide.createIcons();
}

function removeStagedGalleryMedia(index) {
  const textarea = document.getElementById("gallery-modal-media");
  if (!textarea) return;
  const urls = parseGalleryMediaUrls(textarea.value);
  urls.splice(index, 1);
  textarea.value = urls.join("\n");
  renderGalleryStagedMedia();
}

function removeStagedGalleryFile(index) {
  const item = galleryModalStagedFiles[index];
  if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
  galleryModalStagedFiles = galleryModalStagedFiles.filter((_, itemIndex) => itemIndex !== index);
  renderGalleryStagedMedia();
}

function setGalleryModalUploadBusy(isBusy, message = "") {
  galleryModalUploadInProgress = Boolean(isBusy);
  const status = document.getElementById("gallery-modal-upload-status");
  const submit = document.getElementById("gallery-modal-submit");
  if (status) {
    status.textContent = message || (isBusy ? "Uploading..." : "No files chosen");
    status.classList.toggle("text-brand-500", Boolean(isBusy));
    status.classList.toggle("font-bold", Boolean(isBusy));
  }
  if (submit) {
    submit.disabled = Boolean(isBusy);
    submit.classList.toggle("opacity-60", Boolean(isBusy));
    submit.classList.toggle("cursor-not-allowed", Boolean(isBusy));
  }
}

function resetGalleryModalUploadUi() {
  galleryModalUploadInProgress = false;
  galleryEditItemsExpanded = false;
  galleryModalStagedFiles.forEach((item) => {
    if (item?.previewUrl) URL.revokeObjectURL(item.previewUrl);
  });
  galleryModalStagedFiles = [];
  const uploadInput = document.getElementById("gallery-modal-upload");
  const urlInput = document.getElementById("gallery-modal-url-input");
  const status = document.getElementById("gallery-modal-upload-status");
  const submit = document.getElementById("gallery-modal-submit");
  if (uploadInput) uploadInput.value = "";
  if (urlInput) urlInput.value = "";
  if (status) {
    status.textContent = "No files chosen";
    status.classList.remove("text-brand-500", "font-bold", "text-red-500", "text-emerald-600");
  }
  if (submit) {
    submit.disabled = false;
    submit.classList.remove("opacity-60", "cursor-not-allowed");
  }
  renderGalleryStagedMedia();
}

function parseGalleryMediaUrls(rawValue = "") {
  return String(rawValue || "")
    .split(/\n|,/)
    .map((value) => value.trim())
    .filter(Boolean);
}

function addGalleryMediaUrl() {
  const input = document.getElementById("gallery-modal-url-input");
  const textarea = document.getElementById("gallery-modal-media");
  if (!input || !textarea) return;
  const url = normalizeSafeMediaUrl(input.value);
  if (!url) {
    showToast("Use a secure HTTPS image or video URL ending in JPG, PNG, WebP, GIF, MP4, WebM, MOV, or M4V.", "error");
    return;
  }
  const urls = parseGalleryMediaUrls(textarea.value);
  if (!urls.includes(url)) urls.push(url);
  textarea.value = urls.join("\n");
  input.value = "";
  renderGalleryStagedMedia();
}

function galleryMediaTypeFromUrl(url = "") {
  return /\.(mp4|webm|mov|m4v)(\?|$)/i.test(String(url || "")) ? "video" : "image";
}

function galleryMediaTypeFromFile(file) {
  return String(file?.type || "").startsWith("video/") ? "video" : "image";
}

async function uploadGalleryMediaFile(file, galleryId = "") {
  const isImage = GALLERY_IMAGE_TYPES.includes(file.type);
  const isVideo = GALLERY_VIDEO_TYPES.includes(file.type);
  if (!isImage && !isVideo) {
    throw new Error(`File ${file.name} is not a supported gallery image/video. Use JPG, PNG, WebP, GIF, MP4, WebM, MOV, or M4V.`);
  }
  if (file.size > GALLERY_MEDIA_MAX_BYTES) {
    throw new Error(`File ${file.name} is too large. Gallery media must be 25MB or smaller.`);
  }
  const uploadData = await uploadPhotoFile(file, "gallery-media", galleryId ? String(galleryId) : "");
  if (uploadData.status !== "success") {
    throw new Error(uploadData.message || `Could not upload ${file.name}.`);
  }
  return {
    url: uploadData.photo_url,
    media_type: String(uploadData.mime_type || "").startsWith("video/") ? "video" : galleryMediaTypeFromFile(file),
  };
}

async function handleGalleryModalUpload(event) {
  const files = Array.from(event.target.files || []);
  const textarea = document.getElementById("gallery-modal-media");
  const status = document.getElementById("gallery-modal-upload-status");
  if (!files.length || !textarea) return;
  const galleryId = (document.getElementById("gallery-modal-id")?.value || "").trim();

  if (!galleryId) {
    const staged = [];
    const failed = [];
    files.forEach((file) => {
      const isImage = GALLERY_IMAGE_TYPES.includes(file.type);
      const isVideo = GALLERY_VIDEO_TYPES.includes(file.type);
      if (!isImage && !isVideo) {
        failed.push(`File ${file.name} is not a supported gallery image/video.`);
        return;
      }
      if (file.size > GALLERY_MEDIA_MAX_BYTES) {
        failed.push(`File ${file.name} is too large. Gallery media must be 25MB or smaller.`);
        return;
      }
      staged.push({ file, previewUrl: URL.createObjectURL(file), media_type: galleryMediaTypeFromFile(file) });
    });
    galleryModalStagedFiles = [...galleryModalStagedFiles, ...staged].slice(0, 24);
    event.target.value = "";
    renderGalleryStagedMedia();
    if (status) {
      status.classList.remove("text-brand-500", "font-bold", "text-red-500", "text-emerald-600");
      status.textContent = staged.length ? `${staged.length} file(s) staged` : "No files chosen";
      status.classList.toggle("text-emerald-600", Boolean(staged.length));
      status.classList.toggle("text-red-500", Boolean(failed.length && !staged.length));
    }
    if (failed.length) showToast(failed[0], "error");
    return;
  }

  const uploadedUrls = [];
  const failed = [];
  setGalleryModalUploadBusy(true, `Uploading 0/${files.length}...`);

  for (const [index, file] of files.entries()) {
    try {
      setGalleryModalUploadBusy(true, `Uploading ${index + 1}/${files.length}: ${file.name}`);
      const uploaded = await uploadGalleryMediaFile(file, galleryId);
      uploadedUrls.push(uploaded.url);
      const currentUrls = parseGalleryMediaUrls(textarea.value);
      currentUrls.push(uploaded.url);
      textarea.value = currentUrls.join("\n");
      renderGalleryStagedMedia();
    } catch (err) {
      console.error("[Gallery Upload]", err);
      failed.push(err.message || `Could not upload ${file.name}.`);
    }
  }

  event.target.value = "";
  galleryModalUploadInProgress = false;
  const submit = document.getElementById("gallery-modal-submit");
  if (submit) {
    submit.disabled = false;
    submit.classList.remove("opacity-60", "cursor-not-allowed");
  }
  if (status) {
    status.classList.remove("text-brand-500", "font-bold", "text-red-500", "text-emerald-600");
    if (failed.length && uploadedUrls.length) {
      status.textContent = `${uploadedUrls.length} uploaded, ${failed.length} failed`;
      status.classList.add("text-red-500");
    } else if (failed.length) {
      status.textContent = `${failed.length} upload(s) failed`;
      status.classList.add("text-red-500");
    } else {
      status.textContent = `${uploadedUrls.length} file(s) uploaded`;
      status.classList.add("text-emerald-600");
    }
  }
  if (uploadedUrls.length) showToast(`${uploadedUrls.length} file(s) added.`, "success");
  if (failed.length) showToast(failed[0], "error");
}

// ---------------- Save / delete ----------------

async function createGalleryFromModal() {
  if (galleryModalUploadInProgress) {
    showToast("Please wait for gallery media uploads to finish.", "info");
    return;
  }

  const galleryId = (document.getElementById("gallery-modal-id")?.value || "").trim();
  const title = (document.getElementById("gallery-modal-title")?.value || "").trim();
  if (!title) {
    showToast("Gallery title is required.", "info");
    return;
  }
  const description = (document.getElementById("gallery-modal-desc")?.value || "").trim();
  const eventId = document.getElementById("gallery-modal-event")?.value || "";
  const visibility = document.getElementById("gallery-modal-visibility")?.value || "private";
  const rawMedia = (document.getElementById("gallery-modal-media")?.value || "").trim();
  const mediaUrls = parseGalleryMediaUrls(rawMedia);
  const unsafeUrl = mediaUrls.find((mediaUrl) => !normalizeSafeMediaUrl(mediaUrl));
  if (unsafeUrl) {
    showToast("Gallery URLs must be secure HTTPS media links with a supported image/video extension.", "error");
    return;
  }
  const temporaryUrl = mediaUrls.find(isTemporaryBrowserMediaUrl);
  if (temporaryUrl) {
    showToast("One gallery media entry is only a browser preview URL. Re-select the file and wait for it to upload before saving.", "error");
    return;
  }

  const submitBtn = document.getElementById("gallery-modal-submit");
  if (submitBtn) {
    submitBtn.disabled = true;
    submitBtn.classList.add("opacity-60", "cursor-not-allowed");
  }
  try {
    let gallery;
    if (galleryId) {
      const data = await api("update_gallery", { gallery_id: galleryId, title, description, event_id: eventId || null, visibility });
      if (data.status !== "success") throw new Error(data.message || "Could not update gallery.");
      gallery = data.gallery;
      for (const [index, mediaUrl] of mediaUrls.entries()) {
        const itemData = await api("add_gallery_item", {
          gallery_id: galleryId,
          media_url: mediaUrl,
          media_type: galleryMediaTypeFromUrl(mediaUrl),
          sort_order: (gallery.items?.length || 0) + index,
        });
        if (itemData.status !== "success") throw new Error(itemData.message || "Could not add gallery item.");
      }
      for (const [index, staged] of galleryModalStagedFiles.entries()) {
        const uploaded = await uploadGalleryMediaFile(staged.file, galleryId);
        const itemData = await api("add_gallery_item", {
          gallery_id: galleryId,
          media_url: uploaded.url,
          media_type: uploaded.media_type,
          sort_order: (gallery.items?.length || 0) + mediaUrls.length + index,
        });
        if (itemData.status !== "success") throw new Error(itemData.message || "Could not add gallery item.");
      }
    } else {
      const data = await api("create_gallery", {
        title,
        description,
        event_id: eventId || null,
        visibility,
        items: mediaUrls.map((mediaUrl, index) => ({ media_url: mediaUrl, media_type: galleryMediaTypeFromUrl(mediaUrl), sort_order: index })),
      });
      if (data.status !== "success") throw new Error(data.message || "Could not create gallery.");
      gallery = data.gallery;
      const newGalleryId = gallery?.id;
      if (!newGalleryId && galleryModalStagedFiles.length) {
        throw new Error("Gallery was created but no gallery ID was returned for media upload.");
      }
      for (const [index, staged] of galleryModalStagedFiles.entries()) {
        const uploaded = await uploadGalleryMediaFile(staged.file, newGalleryId);
        const itemData = await api("add_gallery_item", {
          gallery_id: newGalleryId,
          media_url: uploaded.url,
          media_type: uploaded.media_type,
          sort_order: mediaUrls.length + index,
        });
        if (itemData.status !== "success") throw new Error(itemData.message || "Could not add gallery item.");
      }
    }
    closeCreateGalleryModal();
    resetGalleryModalUploadUi();
    showToast(galleryId ? "Gallery updated." : "Gallery created.", "success");
    await loadGalleriesTab();
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not save gallery.", "error");
    if (submitBtn) {
      submitBtn.disabled = false;
      submitBtn.classList.remove("opacity-60", "cursor-not-allowed");
    }
  }
}

async function deleteGalleryItem(galleryId, itemId, btn) {
  if (!galleryId || !itemId) return;
  if (!confirm("Remove this media item from the gallery?")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("delete_gallery_item", { gallery_id: galleryId, item_id: itemId });
    if (data.status !== "success") throw new Error(data.message || "Could not remove media item.");
    const gallery = galleriesCache.find((item) => String(item.id) === String(galleryId));
    if (gallery) {
      gallery.items = (gallery.items || []).filter((item) => String(item.id) !== String(itemId));
      renderEditableGalleryItems(gallery);
    }
    renderMainGalleriesTab();
    showToast("Media item removed.", "success");
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not remove media item.", "error");
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function deleteGallery(galleryId, btn) {
  if (!galleryId) return;
  const gallery = galleriesCache.find((item) => String(item.id) === String(galleryId));
  const name = gallery?.title || "this gallery";
  if (!confirm(`Delete "${name}"? All its media items will be removed too.`)) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("delete_gallery", { gallery_id: galleryId });
    if (data.status !== "success") throw new Error(data.message || "Could not delete gallery.");
    galleriesCache = galleriesCache.filter((item) => String(item.id) !== String(galleryId));
    closeGallerySlideshow();
    renderMainGalleriesTab();
    showToast("Gallery deleted.", "success");
  } catch (err) {
    console.error(err);
    showToast(err.message || "Could not delete gallery.", "error");
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

// ---------------- Lightbox slideshow ----------------

function openGallerySlideshow(galleryId, startIndex = 0) {
  const gallery = galleriesCache.find((item) => String(item.id) === String(galleryId));
  const items = Array.isArray(gallery?.items) ? gallery.items : [];
  if (!gallery || !items.length) {
    showToast("No media found in this gallery.", "info");
    return;
  }
  activeGallerySlideshow = { galleryId, index: Math.max(0, Math.min(startIndex, items.length - 1)), items };
  const overlay = document.getElementById("gallery-lightbox");
  const titleEl = document.getElementById("gallery-lightbox-title");
  const track = document.getElementById("gallery-lightbox-track");
  const thumbs = document.getElementById("gallery-lightbox-thumbs");
  if (!overlay || !track || !thumbs) return;

  if (titleEl) titleEl.textContent = gallery.title || "Gallery";
  track.innerHTML = items.map((item, index) => `
    <div data-gallery-slide="${index}" class="gallery-lightbox-slide min-w-[92vw] sm:min-w-[86vw] min-h-[66vh] sm:min-h-[72vh] flex-shrink-0 cursor-pointer flex items-center justify-center">
      ${galleryItemIsVideo(item)
      ? `<video src="${escapeHtml(item.media_url || item.url || "")}" controls preload="metadata" class="gallery-lightbox-media bg-black"></video>`
      : `<img src="${escapeHtml(item.media_url || item.url || "")}" alt="" loading="lazy" class="gallery-lightbox-media">`}
    </div>`).join("");
  thumbs.innerHTML = items.map((item, index) => `
    <button type="button" onclick="scrollGallerySlideshowTo(${index})" data-gallery-thumb="${index}" class="w-14 h-10 rounded-lg overflow-hidden border-2 border-transparent opacity-50 transition-all bg-black">
      ${renderGalleryMediaPreviewHtml(item, "w-full h-full object-cover")}
    </button>`).join("");

  overlay.classList.add("active");
  document.body.classList.add("overflow-hidden");
  bindGalleryLightboxScroll();
  requestAnimationFrame(() => scrollGallerySlideshowTo(activeGallerySlideshow.index, false));
  if (window.lucide) lucide.createIcons();
}

function closeGallerySlideshow() {
  const overlay = document.getElementById("gallery-lightbox");
  if (overlay) overlay.classList.remove("active");
  document.body.classList.remove("overflow-hidden");
}

function bindGalleryLightboxScroll() {
  const track = document.getElementById("gallery-lightbox-track");
  if (!track || track.dataset.bound === "true") return;
  track.dataset.bound = "true";
  let scrollTimer;
  track.addEventListener("scroll", () => {
    clearTimeout(scrollTimer);
    scrollTimer = setTimeout(() => {
      const center = track.scrollLeft + track.offsetWidth / 2;
      let closest = 0;
      let minDistance = Infinity;
      track.querySelectorAll("[data-gallery-slide]").forEach((slide) => {
        const index = Number(slide.dataset.gallerySlide || 0);
        const slideCenter = slide.offsetLeft + slide.offsetWidth / 2;
        const distance = Math.abs(center - slideCenter);
        if (distance < minDistance) {
          minDistance = distance;
          closest = index;
        }
      });
      setActiveGallerySlide(closest);
    }, 40);
  });
}

function scrollGallerySlideshowTo(index, smooth = true) {
  const track = document.getElementById("gallery-lightbox-track");
  const slide = track?.querySelector(`[data-gallery-slide="${index}"]`);
  if (!track || !slide) return;
  const slideLeft = slide.getBoundingClientRect().left;
  const trackLeft = track.getBoundingClientRect().left;
  track.scrollBy({
    left: slideLeft - trackLeft - (track.offsetWidth - slide.offsetWidth) / 2,
    behavior: smooth ? "smooth" : "auto",
  });
  setActiveGallerySlide(index);
}

function setActiveGallerySlide(index) {
  const total = activeGallerySlideshow.items.length;
  activeGallerySlideshow.index = Math.max(0, Math.min(index, total - 1));
  document.querySelectorAll("[data-gallery-slide]").forEach((slide) => {
    slide.classList.toggle("active", Number(slide.dataset.gallerySlide) === activeGallerySlideshow.index);
  });
  document.querySelectorAll("[data-gallery-thumb]").forEach((thumb) => {
    const active = Number(thumb.dataset.galleryThumb) === activeGallerySlideshow.index;
    thumb.classList.toggle("border-white", active);
    thumb.classList.toggle("opacity-100", active);
    thumb.classList.toggle("opacity-50", !active);
  });
  const counter = document.getElementById("gallery-lightbox-counter");
  if (counter) counter.textContent = total ? `${activeGallerySlideshow.index + 1} / ${total}` : "0 / 0";
  document.getElementById("gallery-lightbox-prev")?.classList.toggle("opacity-30", activeGallerySlideshow.index === 0);
  document.getElementById("gallery-lightbox-next")?.classList.toggle("opacity-30", activeGallerySlideshow.index === total - 1);
}

function moveGallerySlideshow(delta) {
  const total = activeGallerySlideshow.items.length;
  const nextIndex = Math.max(0, Math.min(activeGallerySlideshow.index + delta, total - 1));
  if (nextIndex !== activeGallerySlideshow.index) scrollGallerySlideshowTo(nextIndex);
}
