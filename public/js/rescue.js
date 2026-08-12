// Rescue & Seva marketplace: post/browse/apply. Genuinely PetCircle-original
// (no eSamaj equivalent) — see api/routes/rescue.php for the bugs fixed
// while porting this.

let currentRescueCategory = "";
let currentRescueApplyOppId = null;

function openCreateRescueModal() {
  const modal = document.getElementById("create-rescue-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeCreateRescueModal() {
  const modal = document.getElementById("create-rescue-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

async function submitCreateRescueOpportunity() {
  const title = document.getElementById("new-rescue-title").value.trim();
  const org = document.getElementById("new-rescue-org").value.trim();
  const location = document.getElementById("new-rescue-location").value.trim();
  if (!title || !org || !location) {
    showToast("Title, organization, and location are required.", "info");
    return;
  }

  const btn = document.getElementById("rescue-modal-submit-btn");
  setButtonLoading(btn, true, "Posting…");
  try {
    const data = await api("create_rescue_opportunity", {
      title,
      org,
      location,
      category: document.getElementById("new-rescue-category").value,
      urgency: document.getElementById("new-rescue-urgency").value,
      event_date: document.getElementById("new-rescue-date").value,
      slots: document.getElementById("new-rescue-slots").value,
      contact: document.getElementById("new-rescue-contact").value.trim(),
      description: document.getElementById("new-rescue-description").value.trim(),
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not post opportunity.", "error");
      return;
    }
    ["new-rescue-title", "new-rescue-org", "new-rescue-location", "new-rescue-date", "new-rescue-contact", "new-rescue-description"].forEach((id) => {
      document.getElementById(id).value = "";
    });
    document.getElementById("new-rescue-slots").value = "10";
    closeCreateRescueModal();
    showToast("Opportunity posted.", "success");
    loadRescueOpportunities();
  } catch (err) {
    console.error(err);
    showToast("Could not post opportunity.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

function filterRescueByCategory(category) {
  currentRescueCategory = category;
  document.querySelectorAll(".rescue-category-chip").forEach((chip) => {
    chip.classList.toggle("active", chip.dataset.rescueCategory === category);
  });
  loadRescueOpportunities();
}

const RESCUE_URGENCY_STYLES = {
  high: "bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-300",
  medium: "bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300",
  low: "bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-300",
};

const RESCUE_CATEGORY_LABELS = {
  seva: "Seva",
  teaching: "Teaching",
  medical: "Medical",
  event: "Event",
  fundraising: "Fundraising",
  environment: "Environment",
  elderly: "Senior Pet Care",
  tech: "Tech",
};

function rescueCardHtml(o) {
  const isMine = currentUserObj && o.owner_id === currentUserObj.id;
  const isFull = o.filled >= o.slots;
  const urgencyClass = RESCUE_URGENCY_STYLES[o.urgency] || RESCUE_URGENCY_STYLES.medium;
  const dateStr = o.date ? new Date(o.date).toLocaleDateString(undefined, { year: "numeric", month: "short", day: "numeric" }) : "";

  const actionBtn = isMine
    ? `<div class="flex items-center gap-2 flex-shrink-0">
         <button onclick="archiveRescueOpportunity('${o.id}', this)" class="text-xs font-bold text-gray-500 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg">Archive</button>
         <button onclick="deleteRescueOpportunityCard('${o.id}', this)" class="text-gray-400 hover:text-red-500"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
       </div>`
    : isFull
      ? `<span class="text-xs font-bold text-gray-400 flex-shrink-0">Full</span>`
      : `<button onclick="openRescueApplyModal('${o.id}', '${escapeHtml(o.title)}')" class="text-xs font-bold text-white bg-brand-500 hover:bg-brand-600 px-3 py-1.5 rounded-lg flex-shrink-0">Apply</button>`;

  return `
  <div class="warm-glass warm-lift rounded-2xl p-4" data-rescue-id="${o.id}">
    <div class="flex items-start justify-between gap-2">
      <div class="min-w-0">
        <div class="flex items-center gap-2 flex-wrap mb-1">
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase ${urgencyClass}">${escapeHtml(o.urgency)} urgency</span>
          <span class="px-2 py-0.5 rounded-full text-[10px] font-bold uppercase bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">${escapeHtml(RESCUE_CATEGORY_LABELS[o.category] || o.category)}</span>
        </div>
        <p class="font-bold text-gray-900 dark:text-white">${escapeHtml(o.title)}</p>
        <p class="text-xs text-gray-400">${escapeHtml(o.org)} · ${escapeHtml(o.location)}${dateStr ? " · " + dateStr : ""}</p>
      </div>
      ${actionBtn}
    </div>
    ${o.desc ? `<p class="text-sm text-gray-600 dark:text-gray-300 mt-2">${escapeHtml(o.desc)}</p>` : ""}
    <p class="text-xs text-gray-400 mt-2">${o.filled}/${o.slots} slots filled${o.contact ? " · Contact: " + escapeHtml(o.contact) : ""}</p>
  </div>`;
}

async function loadRescueOpportunities() {
  const list = document.getElementById("rescue-list");
  if (!list) return;
  list.innerHTML = rowCardSkeletonListHtml(3);
  try {
    const data = await api("get_rescue_opportunities", { category: currentRescueCategory });
    if (data.status !== "success") {
      list.innerHTML = `<p class="text-center text-sm text-red-500 py-8">${escapeHtml(data.message || "Could not load opportunities.")}</p>`;
      return;
    }
    const opportunities = data.opportunities || [];
    list.innerHTML = opportunities.length
      ? opportunities.map(rescueCardHtml).join("")
      : `<p class="text-center text-sm text-gray-400 py-8">No open opportunities in this category yet.</p>`;
    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
    list.innerHTML = `<p class="text-center text-sm text-red-500 py-8">Could not load opportunities.</p>`;
  }
}

function loadRescueTab() {
  filterRescueByCategory("");
}

function openRescueApplyModal(oppId, title) {
  currentRescueApplyOppId = oppId;
  document.getElementById("rescue-apply-title").textContent = `Apply: ${title}`;
  document.getElementById("rescue-apply-name").value = currentUserObj?.parent_name || "";
  document.getElementById("rescue-apply-phone").value = currentUserObj?.mobile_number || "";
  document.getElementById("rescue-apply-error")?.classList.add("hidden");
  const modal = document.getElementById("rescue-apply-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeRescueApplyModal() {
  currentRescueApplyOppId = null;
  const modal = document.getElementById("rescue-apply-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

async function submitRescueApplication() {
  if (!currentRescueApplyOppId) return;
  const name = document.getElementById("rescue-apply-name").value.trim();
  const errorEl = document.getElementById("rescue-apply-error");
  if (!name) {
    errorEl.textContent = "Your name is required.";
    errorEl.classList.remove("hidden");
    return;
  }
  const btn = document.getElementById("rescue-apply-submit-btn");
  setButtonLoading(btn, true, "Submitting…");
  try {
    const data = await api("apply_rescue_opportunity", {
      opportunity_id: currentRescueApplyOppId,
      name,
      phone: document.getElementById("rescue-apply-phone").value.trim(),
    });
    if (data.status !== "success") {
      errorEl.textContent = data.message || "Could not submit application.";
      errorEl.classList.remove("hidden");
      return;
    }
    closeRescueApplyModal();
    showToast("Application submitted!", "success");
    loadRescueOpportunities();
  } catch (err) {
    console.error(err);
    errorEl.textContent = "Something went wrong. Please try again.";
    errorEl.classList.remove("hidden");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function archiveRescueOpportunity(oppId, btn) {
  if (!confirm("Archive this opportunity? It will no longer be listed as open.")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("archive_rescue_opportunity", { opportunity_id: oppId });
    if (data.status !== "success") {
      showToast(data.message || "Could not archive opportunity.", "error");
      return;
    }
    loadRescueOpportunities();
  } catch (err) {
    console.error(err);
    showToast("Could not archive opportunity.", "error");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function deleteRescueOpportunityCard(oppId, btn) {
  if (!confirm("Delete this opportunity? This also removes all applications for it.")) return;
  if (btn) {
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("delete_rescue_opportunity", { opportunity_id: oppId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete opportunity.", "error");
      return;
    }
    document.querySelector(`[data-rescue-id="${oppId}"]`)?.remove();
  } catch (err) {
    console.error(err);
    showToast("Could not delete opportunity.", "error");
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}
