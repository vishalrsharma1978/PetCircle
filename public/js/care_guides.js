// Pet Care & Training Guides library: category chips, guide grid, reader modal.
// Ported from community_proj's holy-books/"Dharmic Granth" tab, simplified to a
// single reader (inline text for guides that only have written content yet,
// falling back to an <iframe> over the guide_redirect proxy once a PDF/EPUB is
// actually uploaded) rather than the full custom pdf.js canvas renderer — that
// avoids a third-party CDN dependency this codebase doesn't otherwise use.

let currentGuidesCategory = "";
let guidesCache = {};

const GUIDE_CATEGORY_LABELS = {
  "": "All",
  training: "Training",
  health: "Health",
  nutrition: "Nutrition",
  behavior: "Behavior",
  "first-aid": "First Aid",
  grooming: "Grooming",
};

function filterGuidesByCategory(category) {
  currentGuidesCategory = category;
  document.querySelectorAll(".guides-category-chip").forEach((chip) => {
    chip.classList.toggle("active", chip.dataset.guidesCategory === category);
  });
  loadGuidesTab();
}

function renderGuidesCategoryChips(categories) {
  const wrap = document.getElementById("guides-category-chips");
  if (!wrap) return;
  const all = [{ key: "", title: "All", count: categories.reduce((sum, c) => sum + (c.count || 0), 0) }, ...categories];
  wrap.innerHTML = all.map((c) => `
    <button data-guides-category="${escapeHtml(c.key)}" onclick="filterGuidesByCategory('${escapeHtml(c.key)}')"
      class="guides-category-chip ${c.key === currentGuidesCategory ? "active" : ""} whitespace-nowrap px-3.5 py-1.5 rounded-full text-xs font-bold border transition-colors">
      ${escapeHtml(c.title)}${c.count ? ` <span class="opacity-60">${c.count}</span>` : ""}
    </button>`).join("");
}

function guideCardHtml(guide) {
  const bg = guide.bg || "bg-brand-50 text-brand-600";
  return `
    <button onclick="openGuideReader('${escapeHtml(guide.id)}')" class="text-left warm-glass warm-lift rounded-2xl p-4 flex items-start gap-3">
      <div class="${escapeHtml(bg)} p-2.5 rounded-xl shrink-0 flex items-center justify-center">
        <i data-lucide="${escapeHtml(guide.icon || "book-open")}" class="w-5 h-5"></i>
      </div>
      <div class="min-w-0 flex-1">
        <div class="font-bold text-sm text-gray-900 dark:text-white leading-tight">${escapeHtml(guide.title)}</div>
        <div class="text-xs text-gray-500 dark:text-gray-400 mt-1 line-clamp-2">${escapeHtml(guide.desc || "")}</div>
      </div>
    </button>`;
}

async function loadGuidesTab() {
  const grid = document.getElementById("guides-grid");
  if (!grid) return;
  grid.innerHTML = rowCardSkeletonListHtml(6);

  try {
    const data = await api("get_care_guides", currentGuidesCategory ? { category: currentGuidesCategory } : {});
    if (data.status !== "success") {
      grid.innerHTML = `<p class="text-center text-sm text-gray-400 py-8 col-span-full">Could not load guides.</p>`;
      return;
    }

    renderGuidesCategoryChips((data.categories || []).map((c) => ({ key: c.key, title: c.title, count: c.count })));

    const guides = data.guides || [];
    guides.forEach((g) => { guidesCache[g.id] = g; });

    grid.innerHTML = guides.length
      ? guides.map(guideCardHtml).join("")
      : `<p class="text-center text-sm text-gray-400 py-8 col-span-full">No guides in this category yet.</p>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    grid.innerHTML = `<p class="text-center text-sm text-gray-400 py-8 col-span-full">Could not load guides.</p>`;
  }
}

function guideDownloadButtonHtml(guide, key, label, icon) {
  const item = guide.downloads?.[key] || {};
  if (!item.available || !item.url) return "";
  return `<a href="${escapeHtml(item.url)}" download target="_blank" rel="noopener" class="px-3 py-2 rounded-lg border border-gray-200 dark:border-gray-700 text-xs font-bold text-gray-700 dark:text-gray-200 bg-white dark:bg-gray-900 hover:bg-gray-50 dark:hover:bg-gray-800 inline-flex items-center gap-1.5">
    <i data-lucide="${icon}" class="w-3.5 h-3.5"></i>${label}
  </a>`;
}

function openGuideReader(guideId) {
  const guide = guidesCache[guideId];
  if (!guide) return;

  let modal = document.getElementById("guide-reader-modal");
  if (!modal) {
    modal = document.createElement("div");
    modal.id = "guide-reader-modal";
    modal.className = "fixed inset-0 z-[160] flex items-center justify-center p-0 sm:p-4 bg-gray-950/80 backdrop-blur-sm";
    modal.onclick = (e) => { if (e.target === modal) closeGuideReader(); };
    document.body.appendChild(modal);
  }

  const hasPdf = guide.source_types?.pdf && guide.source_types.pdf !== "none";
  const pdfUrl = guide.read?.page || guide.read?.scroll || "";

  const body = hasPdf && pdfUrl
    ? `<iframe src="${escapeHtml(pdfUrl)}" title="${escapeHtml(guide.title)}" class="w-full h-full border-0"></iframe>`
    : `<div class="max-w-2xl mx-auto p-5 sm:p-8 whitespace-pre-line text-sm leading-relaxed text-gray-700 dark:text-gray-200">${escapeHtml(guide.description || guide.desc || "This guide is coming soon.")}</div>`;

  modal.innerHTML = `
    <div class="bg-white dark:bg-gray-900 sm:rounded-2xl shadow-2xl w-full sm:max-w-3xl h-full sm:h-[85vh] overflow-hidden flex flex-col border border-white/10">
      <div class="px-4 sm:px-6 py-3 sm:py-4 border-b border-gray-100 dark:border-gray-800 flex items-center gap-3 bg-gray-50 dark:bg-gray-800/60 flex-shrink-0">
        <div class="${escapeHtml(guide.bg || "bg-brand-50 text-brand-600")} w-10 h-10 rounded-xl flex items-center justify-center shrink-0">
          <i data-lucide="${escapeHtml(guide.icon || "book-open")}" class="w-5 h-5"></i>
        </div>
        <div class="min-w-0 flex-1">
          <div class="font-bold text-gray-900 dark:text-white truncate">${escapeHtml(guide.title)}</div>
          <div class="text-xs text-gray-500 dark:text-gray-400">${escapeHtml(guide.desc || "")}</div>
        </div>
        <div class="hidden sm:flex items-center gap-2">
          ${guideDownloadButtonHtml(guide, "pdf", "PDF", "file-text")}
          ${guideDownloadButtonHtml(guide, "epub", "EPUB", "book-down")}
        </div>
        <button onclick="closeGuideReader()" class="p-2 rounded-lg text-gray-400 hover:text-gray-700 dark:hover:text-gray-200 no-accent-hover" aria-label="Close reader">
          <i data-lucide="x" class="w-5 h-5"></i>
        </button>
      </div>
      <div class="flex-1 overflow-y-auto bg-white dark:bg-gray-900">${body}</div>
    </div>`;
  modal.classList.remove("hidden");
  if (window.lucide) lucide.createIcons();
}

function closeGuideReader() {
  document.getElementById("guide-reader-modal")?.remove();
}
