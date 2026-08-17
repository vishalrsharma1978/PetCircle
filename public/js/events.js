// Events: create/edit/list/RSVP/delete, an auto-linked discussion group,
// friend/group invites, Events/Analytics sub-tabs with Chart.js charts,
// past-events section, and a share sheet (copy link/native share/QR/ICS).

let eventsCache = [];
let pastEventsCache = [];
let eventInviteFriendsCache = [];
let eventInviteGroupsCache = [];
let currentShareEventId = null;
let eventsAttendanceChart = null;
let eventsPetTypeChart = null;
let currentCalendarViewDate = new Date();

// ---------------- Create / edit modal ----------------

function openCreateEventModal(prefillDate = "") {
  document.getElementById("event-modal-id").value = "";
  document.getElementById("event-modal-heading").textContent = "Create an event";
  document.getElementById("event-modal-subtitle").textContent = "Invite friends and groups once it's set up.";
  document.getElementById("event-modal-submit-btn").textContent = "Create event";
  document.getElementById("new-event-title").value = "";
  document.getElementById("new-event-date").value = prefillDate || "";
  document.getElementById("new-event-time").value = "";
  document.getElementById("new-event-location").value = "";
  document.getElementById("new-event-description").value = "";
  document.getElementById("new-event-is-online").checked = false;
  document.getElementById("new-event-meeting-url").value = "";
  document.getElementById("new-event-meeting-url-wrap").classList.add("hidden");
  document.getElementById("new-event-banner-url").value = "";
  document.getElementById("new-event-banner-input").value = "";
  document.getElementById("new-event-banner-preview").classList.add("hidden");
  document.getElementById("new-event-banner-status").textContent = "No image chosen";

  const modal = document.getElementById("create-event-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  switchEventInviteTab("friends");
  loadEventInvitePickers();
  if (window.lucide) lucide.createIcons();
}

function closeCreateEventModal() {
  const modal = document.getElementById("create-event-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

function openEditEventModal(eventId) {
  const event = [...eventsCache, ...pastEventsCache].find((e) => String(e.id) === String(eventId));
  if (!event) {
    showToast("Event not found.", "error");
    return;
  }
  openCreateEventModal();
  document.getElementById("event-modal-id").value = event.id;
  document.getElementById("event-modal-heading").textContent = "Edit event";
  document.getElementById("event-modal-subtitle").textContent = "Newly invited friends/groups will get a fresh notification.";
  document.getElementById("event-modal-submit-btn").textContent = "Save event";
  document.getElementById("new-event-title").value = event.title || "";
  document.getElementById("new-event-date").value = event.event_date || "";
  document.getElementById("new-event-time").value = event.event_time ? event.event_time.slice(0, 5) : "";
  document.getElementById("new-event-location").value = event.location || "";
  document.getElementById("new-event-description").value = event.description || "";
  document.getElementById("new-event-is-online").checked = !!event.is_online;
  document.getElementById("new-event-meeting-url-wrap").classList.toggle("hidden", !event.is_online);
  document.getElementById("new-event-meeting-url").value = event.meeting_url || "";
  if (event.banner_url) {
    document.getElementById("new-event-banner-url").value = event.banner_url;
    document.getElementById("new-event-banner-preview").src = event.banner_url;
    document.getElementById("new-event-banner-preview").classList.remove("hidden");
    document.getElementById("new-event-banner-status").textContent = "Current banner kept";
  }
}

function switchEventInviteTab(tab) {
  document.getElementById("event-invite-friends-list")?.classList.toggle("hidden", tab !== "friends");
  document.getElementById("event-invite-groups-list")?.classList.toggle("hidden", tab !== "groups");
  document.querySelectorAll("[data-invite-tab]").forEach((btn) => {
    btn.classList.toggle("active-subtab", btn.dataset.inviteTab === tab);
  });
}

async function loadEventInvitePickers() {
  const friendsList = document.getElementById("event-invite-friends-list");
  const groupsList = document.getElementById("event-invite-groups-list");
  if (friendsList) friendsList.innerHTML = rowCardSkeletonListHtml(2);
  if (groupsList) groupsList.innerHTML = rowCardSkeletonListHtml(2);
  try {
    const [friendsData, groupsData] = await Promise.all([
      api("get_friends", {}),
      api("get_groups", { pet_type: currentUserObj?.pet_type || "" }),
    ]);
    eventInviteFriendsCache = friendsData.status === "success" ? (friendsData.friends || []) : [];
    eventInviteGroupsCache = groupsData.status === "success" ? (groupsData.groups || []) : [];
  } catch (err) {
    console.error(err);
    eventInviteFriendsCache = [];
    eventInviteGroupsCache = [];
  }
  if (friendsList) {
    friendsList.innerHTML = eventInviteFriendsCache.length
      ? eventInviteFriendsCache.map((f) => `
        <label class="flex items-center gap-2 text-sm px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
          <input type="checkbox" data-invite-friend-id="${escapeHtml(f.user_id)}" class="rounded border-gray-300">
          <span class="truncate text-gray-700 dark:text-gray-200">${escapeHtml(f.name || "Member")}</span>
        </label>`).join("")
      : `<p class="text-xs text-gray-400 px-2 py-1">No friends yet.</p>`;
  }
  if (groupsList) {
    groupsList.innerHTML = eventInviteGroupsCache.length
      ? eventInviteGroupsCache.map((g) => `
        <label class="flex items-center gap-2 text-sm px-2 py-1.5 rounded-lg hover:bg-gray-50 dark:hover:bg-gray-800 cursor-pointer">
          <input type="checkbox" data-invite-group-id="${escapeHtml(g.id)}" class="rounded border-gray-300">
          <span class="truncate text-gray-700 dark:text-gray-200">${escapeHtml(g.name)}</span>
        </label>`).join("")
      : `<p class="text-xs text-gray-400 px-2 py-1">No groups yet.</p>`;
  }
}

async function handleEventBannerUpload(input) {
  const file = input.files?.[0];
  if (!file) return;
  document.getElementById("new-event-banner-status").textContent = "Uploading…";
  try {
    const data = await uploadPhotoFile(file, "event-banner");
    if (data.status !== "success") {
      showToast(data.message || "Could not upload banner.", "error");
      document.getElementById("new-event-banner-status").textContent = "No image chosen";
      return;
    }
    document.getElementById("new-event-banner-url").value = data.photo_url;
    document.getElementById("new-event-banner-preview").src = data.photo_url;
    document.getElementById("new-event-banner-preview").classList.remove("hidden");
    document.getElementById("new-event-banner-status").textContent = "Image uploaded";
  } catch (err) {
    console.error(err);
    showToast("Could not upload banner.", "error");
    document.getElementById("new-event-banner-status").textContent = "No image chosen";
  }
}

async function submitEventModal() {
  const eventId = document.getElementById("event-modal-id").value.trim();
  const title = document.getElementById("new-event-title").value.trim();
  const eventDate = document.getElementById("new-event-date").value;
  if (!title || !eventDate) {
    showToast("Title and date are required.", "info");
    return;
  }

  const inviteFriendIds = Array.from(document.querySelectorAll('#event-invite-friends-list input[data-invite-friend-id]:checked')).map((el) => el.dataset.inviteFriendId);
  const inviteGroupIds = Array.from(document.querySelectorAll('#event-invite-groups-list input[data-invite-group-id]:checked')).map((el) => el.dataset.inviteGroupId);

  const payload = {
    title,
    event_date: eventDate,
    event_time: document.getElementById("new-event-time").value,
    location: document.getElementById("new-event-location").value.trim(),
    description: document.getElementById("new-event-description").value.trim(),
    is_online: document.getElementById("new-event-is-online").checked,
    meeting_url: document.getElementById("new-event-meeting-url").value.trim(),
    banner_url: document.getElementById("new-event-banner-url").value.trim(),
    invite_friend_ids: inviteFriendIds,
    invite_group_ids: inviteGroupIds,
  };

  const btn = document.getElementById("event-modal-submit-btn");
  setButtonLoading(btn, true, eventId ? "Saving…" : "Creating…");
  try {
    const data = eventId
      ? await api("update_event", { ...payload, event_id: eventId })
      : await api("create_event", payload);
    if (data.status !== "success") {
      showToast(data.message || "Could not save event.", "error");
      return;
    }
    closeCreateEventModal();
    showToast(eventId ? "Event updated." : "Event created.", "success");
    loadEventsTab();
  } catch (err) {
    console.error(err);
    showToast("Could not save event.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

// ---------------- List rendering ----------------

function formatEventDate(dateStr, timeStr) {
  if (!dateStr) return "";
  const d = new Date(dateStr + (timeStr ? `T${timeStr}` : ""));
  if (Number.isNaN(d.getTime())) return dateStr;
  const opts = timeStr ? { year: "numeric", month: "short", day: "numeric", hour: "numeric", minute: "2-digit" } : { year: "numeric", month: "short", day: "numeric" };
  return d.toLocaleString(undefined, opts);
}

function eventCardHtml(e, isPast = false) {
  const isMine = currentUserObj && e.created_by === currentUserObj.id;
  const d = e.event_date ? new Date(e.event_date + "T00:00:00") : null;
  const month = d ? d.toLocaleString(undefined, { month: "short" }).toUpperCase() : "";
  const day = d ? d.getDate() : "?";

  const rsvpBtn = isPast
    ? ""
    : e.my_rsvp === "going"
      ? `<button onclick="rsvpEvent('${e.id}', 'not_going', this)" class="text-xs font-bold text-white bg-green-600 px-3 py-1.5 rounded-lg">Going ✓</button>`
      : `<button onclick="rsvpEvent('${e.id}', 'going', this)" class="text-xs font-bold text-brand-500 border border-brand-200 dark:border-brand-800 px-3 py-1.5 rounded-lg">RSVP</button>`;

  const goToGroupBtn = (e.linked_group_id && e.is_group_member)
    ? `<button onclick="openGroupChat('${e.linked_group_id}', '${escapeHtml(e.title)} — Event Group')" class="text-xs font-bold text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg flex items-center gap-1"><i data-lucide="message-square" class="w-3.5 h-3.5"></i> Group</button>`
    : "";

  const menuBtn = isMine
    ? `<div class="relative flex-shrink-0">
         <button onclick="toggleEventMenu(event, '${e.id}')" class="p-1.5 rounded-lg text-gray-400 hover:bg-gray-100 dark:hover:bg-gray-800"><i data-lucide="more-vertical" class="w-4 h-4"></i></button>
         <div id="event-menu-${e.id}" class="hidden absolute right-0 mt-1 w-36 bg-white dark:bg-gray-800 rounded-xl shadow-xl border border-gray-100 dark:border-gray-700 overflow-hidden z-30">
           <button onclick="openEditEventModal('${e.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-gray-50 dark:hover:bg-gray-700 text-gray-700 dark:text-gray-200 flex items-center gap-2"><i data-lucide="pencil" class="w-3.5 h-3.5"></i> Edit</button>
           <button onclick="deleteEvent('${e.id}')" class="w-full text-left px-3 py-2 text-xs font-semibold hover:bg-red-50 dark:hover:bg-red-900/20 text-red-600 dark:text-red-400 flex items-center gap-2"><i data-lucide="trash-2" class="w-3.5 h-3.5"></i> Delete</button>
         </div>
       </div>`
    : "";

  return `
  <div class="warm-glass warm-lift rounded-2xl p-4 ${isPast ? "opacity-80" : ""}" data-event-id="${e.id}">
    <div class="flex items-start gap-3">
      <div class="w-12 h-12 rounded-xl ${isPast ? "bg-gray-100 dark:bg-gray-800" : "bg-brand-50 dark:bg-brand-900/30"} flex flex-col items-center justify-center flex-shrink-0">
        <span class="text-[10px] font-bold ${isPast ? "text-gray-400" : "text-brand-500"} leading-none">${month}</span>
        <span class="text-base font-extrabold text-gray-900 dark:text-white leading-tight">${day}</span>
      </div>
      <div class="min-w-0 flex-1">
        <div class="flex items-start justify-between gap-2">
          <div class="min-w-0">
            <p class="font-bold text-gray-900 dark:text-white truncate">${escapeHtml(e.title)}${isPast ? ` <span class="text-[10px] font-bold uppercase text-gray-400 align-middle">Completed</span>` : ""}</p>
            <p class="text-xs text-gray-400">${formatEventDate(e.event_date, e.event_time)}${e.location ? " · " + escapeHtml(e.location) : ""}${e.is_online ? " · Online" : ""}</p>
            ${e.created_by_name ? `<p class="text-[11px] text-gray-400 mt-0.5">Organized by ${escapeHtml(isMine ? "you" : e.created_by_name)}</p>` : ""}
          </div>
          ${menuBtn}
        </div>
        ${e.banner_url ? `<img src="${escapeHtml(e.banner_url)}" alt="" class="w-full h-32 object-cover rounded-xl mt-2">` : ""}
        ${e.description ? `<p class="text-sm text-gray-600 dark:text-gray-300 mt-2">${escapeHtml(e.description)}</p>` : ""}
        ${e.is_online && e.meeting_url ? `<a href="${escapeHtml(e.meeting_url)}" target="_blank" rel="noopener" class="text-xs font-bold text-brand-500 inline-flex items-center gap-1 mt-2"><i data-lucide="video" class="w-3.5 h-3.5"></i> Join link</a>` : ""}
        <div class="flex items-center flex-wrap gap-2 mt-3 pt-3 border-t border-gray-100 dark:border-gray-800">
          ${rsvpBtn}
          ${goToGroupBtn}
          <button onclick="openEventShareModal('${e.id}')" class="text-xs font-bold text-gray-600 dark:text-gray-300 border border-gray-200 dark:border-gray-700 px-3 py-1.5 rounded-lg flex items-center gap-1"><i data-lucide="share-2" class="w-3.5 h-3.5"></i> Share</button>
          <span class="text-xs text-gray-400 ml-auto">${e.going_count} going</span>
        </div>
      </div>
    </div>
  </div>`;
}

function toggleEventMenu(evt, eventId) {
  evt.stopPropagation();
  document.querySelectorAll('[id^="event-menu-"]').forEach((menu) => {
    if (menu.id !== `event-menu-${eventId}`) menu.classList.add("hidden");
  });
  document.getElementById(`event-menu-${eventId}`)?.classList.toggle("hidden");
}
document.addEventListener("click", () => {
  document.querySelectorAll('[id^="event-menu-"]').forEach((menu) => menu.classList.add("hidden"));
});

async function loadEventsTab() {
  const list = document.getElementById("events-list");
  if (!list) return;
  list.innerHTML = rowCardSkeletonListHtml(3);
  switchEventsSubtab("list");
  try {
    const data = await api("get_events", { pet_type: currentUserObj?.pet_type || "", include_past: true });
    if (data.status !== "success") return;
    const all = data.events || [];
    const today = new Date().toISOString().slice(0, 10);
    eventsCache = all.filter((e) => e.event_date >= today);
    pastEventsCache = all.filter((e) => e.event_date < today).reverse();

    list.innerHTML = eventsCache.length ? eventsCache.map((e) => eventCardHtml(e, false)).join("") : `<p class="text-sm text-gray-400">No upcoming events — tap + to create one.</p>`;

    const pastSection = document.getElementById("past-events-section");
    const pastCount = document.getElementById("past-events-count");
    if (pastSection) pastSection.classList.toggle("hidden", pastEventsCache.length === 0);
    if (pastCount) pastCount.textContent = String(pastEventsCache.length);

    if (window.lucide) lucide.createIcons();
  } catch (err) {
    console.error(err);
  }
}

function togglePastEvents() {
  const list = document.getElementById("past-events-list");
  const chevron = document.getElementById("past-events-chevron");
  if (!list) return;
  const willOpen = list.classList.contains("hidden");
  list.classList.toggle("hidden");
  chevron?.classList.toggle("rotate-180", willOpen);
  if (willOpen && !list.dataset.rendered) {
    list.innerHTML = pastEventsCache.map((e) => eventCardHtml(e, true)).join("");
    list.dataset.rendered = "true";
    if (window.lucide) lucide.createIcons();
  }
}

async function rsvpEvent(eventId, status, btn) {
  if (btn) {
    if (btn.disabled) return;
    btn.disabled = true;
    btn.classList.add("opacity-50", "pointer-events-none");
  }
  try {
    const data = await api("rsvp_event", { event_id: eventId, status });
    if (data.status !== "success") {
      showToast(data.message || "Could not update RSVP.", "error");
      return;
    }
    loadEventsTab();
  } catch (err) {
    console.error(err);
    showToast("Could not update RSVP.", "error");
  } finally {
    if (btn) {
      btn.disabled = false;
      btn.classList.remove("opacity-50", "pointer-events-none");
    }
  }
}

async function deleteEvent(eventId) {
  if (!confirm("Delete this event?")) return;
  try {
    const data = await api("delete_event", { event_id: eventId });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete event.", "error");
      return;
    }
    document.querySelector(`[data-event-id="${eventId}"]`)?.remove();
  } catch (err) {
    console.error(err);
    showToast("Could not delete event.", "error");
  }
}

// ---------------- Share sheet (copy link / native share / QR / ICS) ----------------

function buildEventShareUrl(eventId) {
  return `${window.location.origin}${window.location.pathname}#event-${eventId}`;
}

function openEventShareModal(eventId) {
  const event = [...eventsCache, ...pastEventsCache].find((e) => String(e.id) === String(eventId));
  if (!event) return;
  currentShareEventId = eventId;
  document.getElementById("event-share-title").textContent = event.title || "Share event";
  const qrBox = document.getElementById("event-share-qr");
  if (qrBox) {
    qrBox.innerHTML = "";
    if (window.QRCode) {
      new QRCode(qrBox, { text: buildEventShareUrl(eventId), width: 160, height: 160 });
    }
  }
  const modal = document.getElementById("event-share-modal");
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeEventShareModal() {
  currentShareEventId = null;
  const modal = document.getElementById("event-share-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

async function copyEventLink() {
  if (!currentShareEventId) return;
  try {
    await navigator.clipboard.writeText(buildEventShareUrl(currentShareEventId));
    showToast("Link copied.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not copy link.", "error");
  }
}

async function shareEventNative() {
  if (!currentShareEventId) return;
  const event = [...eventsCache, ...pastEventsCache].find((e) => String(e.id) === String(currentShareEventId));
  const url = buildEventShareUrl(currentShareEventId);
  if (navigator.share) {
    try {
      await navigator.share({ title: event?.title || "Event", url });
    } catch (err) {
      // user cancelled the share sheet — not an error
    }
  } else {
    copyEventLink();
  }
}

function icsEscape(value = "") {
  return String(value).replace(/[\\,;]/g, (m) => `\\${m}`).replace(/\n/g, "\\n");
}

function downloadEventIcs() {
  if (!currentShareEventId) return;
  const e = [...eventsCache, ...pastEventsCache].find((ev) => String(ev.id) === String(currentShareEventId));
  if (!e) return;
  const dateStr = (e.event_date || "").replace(/-/g, "");
  const timeStr = (e.event_time || "00:00:00").replace(/:/g, "").slice(0, 6);
  const dtStart = `${dateStr}T${timeStr}`;
  const ics = [
    "BEGIN:VCALENDAR",
    "VERSION:2.0",
    "PRODID:-//PawCircle//Events//EN",
    "BEGIN:VEVENT",
    `UID:${e.id}@pawcircle`,
    `DTSTART:${dtStart}`,
    `SUMMARY:${icsEscape(e.title)}`,
    e.location ? `LOCATION:${icsEscape(e.location)}` : "",
    e.description ? `DESCRIPTION:${icsEscape(e.description)}` : "",
    "END:VEVENT",
    "END:VCALENDAR",
  ].filter(Boolean).join("\r\n");

  const blob = new Blob([ics], { type: "text/calendar" });
  const url = URL.createObjectURL(blob);
  const a = document.createElement("a");
  a.href = url;
  a.download = `${(e.title || "event").replace(/[^a-z0-9]+/gi, "-")}.ics`;
  document.body.appendChild(a);
  a.click();
  a.remove();
  URL.revokeObjectURL(url);
}

// ---------------- Analytics sub-tab ----------------

function switchEventsSubtab(tab) {
  document.getElementById("events-subtab-list")?.classList.toggle("hidden", tab !== "list");
  document.getElementById("events-subtab-analytics")?.classList.toggle("hidden", tab !== "analytics");
  document.querySelectorAll("[data-events-subtab]").forEach((btn) => {
    btn.classList.toggle("active-subtab", btn.dataset.eventsSubtab === tab);
  });
  if (tab === "analytics") loadEventAnalytics();
}

async function loadEventAnalytics() {
  try {
    const data = await api("get_event_analytics", {});
    if (data.status !== "success") return;

    document.getElementById("events-stat-total").textContent = data.total_events;
    document.getElementById("events-stat-attendees").textContent = data.total_attendees;
    document.getElementById("events-stat-my-rsvps").textContent = data.my_rsvps;
    document.getElementById("events-stat-pet-types").textContent = Object.keys(data.events_by_pet_type || {}).length;

    if (!window.Chart) return;

    const monthLabels = Object.keys(data.attendance_by_month || {});
    const monthValues = Object.values(data.attendance_by_month || {});
    const attendanceCtx = document.getElementById("events-chart-attendance");
    if (attendanceCtx) {
      eventsAttendanceChart?.destroy();
      eventsAttendanceChart = new Chart(attendanceCtx, {
        type: "bar",
        data: { labels: monthLabels, datasets: [{ label: "RSVPs", data: monthValues, backgroundColor: "#e04848" }] },
        options: { plugins: { legend: { display: false } }, scales: { y: { beginAtZero: true } } },
      });
    }

    const petTypeLabels = Object.keys(data.events_by_pet_type || {});
    const petTypeValues = Object.values(data.events_by_pet_type || {});
    const petTypeColors = petTypeLabels.map((label) => (typeof PET_TYPE_PREVIEW_ACCENTS !== "undefined" && PET_TYPE_PREVIEW_ACCENTS[label]) || "#9ca3af");
    const petTypeCtx = document.getElementById("events-chart-pet-types");
    if (petTypeCtx) {
      eventsPetTypeChart?.destroy();
      eventsPetTypeChart = new Chart(petTypeCtx, {
        type: "doughnut",
        data: { labels: petTypeLabels, datasets: [{ data: petTypeValues, backgroundColor: petTypeColors }] },
      });
    }
  } catch (err) {
    console.error(err);
  }
}


function openEnlargedCalendarModal() {
  document
    .getElementById("enlarged-calendar-modal")
    .classList.remove("hidden");
  renderEnlargedCalendar();
}

function closeEnlargedCalendarModal() {
  document
    .getElementById("enlarged-calendar-modal")
    .classList.add("hidden");
}

function renderEnlargedCalendar() {
  const container = document.getElementById("enlarged-calendar-grid");
  const year = currentCalendarViewDate.getFullYear();
  const month = currentCalendarViewDate.getMonth();
  const monthNames = [
    "January",
    "February",
    "March",
    "April",
    "May",
    "June",
    "July",
    "August",
    "September",
    "October",
    "November",
    "December",
  ];

  const firstDay = new Date(year, month, 1).getDay();
  const daysInMonth = new Date(year, month + 1, 0).getDate();

  const monthStr = `${year}-${String(month + 1).padStart(2, "0")}`;
  // Real events only — this app has no religion/festival concept, so unlike
  // eSamaj's calendar there's no separate "official festivals" overlay here.
  const evts = [...eventsCache, ...pastEventsCache];

  let gridHtml = "";
  for (let i = 0; i < firstDay; i++) {
    gridHtml += `<div class="p-2 border-r border-b border-gray-200 dark:border-gray-700 bg-gray-50/50 dark:bg-gray-800/20 min-h-[120px]"></div>`;
  }

  for (let i = 1; i <= daysInMonth; i++) {
    const dateStr = `${monthStr}-${String(i).padStart(2, "0")}`;
    const dayEvents = evts.filter((e) => e.event_date === dateStr);

    let eventsHtml = "";
    dayEvents.forEach((e) => {
      const linkHtml = e.meeting_url
        ? `<a href="${escapeHtml(e.meeting_url)}" target="_blank" rel="noopener" class="ml-1 text-blue-500 hover:underline inline-flex" title="Meeting Link"><i data-lucide="video" class="w-3 h-3"></i></a>`
        : "";
      eventsHtml += `<div class="text-[11px] mt-1 bg-brand-50 dark:bg-brand-900/30 text-brand-600 dark:text-brand-300 px-1.5 py-0.5 rounded flex justify-between items-center gap-1 group">
                        <span class="truncate pr-1">${escapeHtml(e.title || "")}${linkHtml}</span>
                        <span class="flex items-center gap-1 opacity-0 group-hover:opacity-100 transition-opacity">
                          <button onclick="event.stopPropagation(); openEventShareModal('${e.id}')" class="text-gray-400 hover:text-brand-500" title="Share event"><i data-lucide="share-2" class="w-3 h-3"></i></button>
                          <button onclick="event.stopPropagation(); deleteEvent('${e.id}')" class="text-red-500 hover:text-red-700"><i data-lucide="x" class="w-3 h-3"></i></button>
                        </span>
                    </div>`;
    });

    gridHtml += `
                <div id="enlarged-cal-day-${dateStr}" class="p-2 border-r border-b border-gray-200 dark:border-gray-700 min-h-[120px] hover:bg-gray-50 dark:hover:bg-gray-800/50 transition-colors relative group transition-all duration-500">
                    <div class="flex justify-between items-start">
                        <span class="text-sm font-semibold text-gray-700 dark:text-gray-300">${i}</span>
                        <button onclick="openCreateEventModal('${dateStr}')" class="opacity-0 group-hover:opacity-100 text-gray-400 hover:text-brand-500 transition-opacity"><i data-lucide="plus" class="w-4 h-4"></i></button>
                    </div>
                    <div class="mt-1">${eventsHtml}</div>
                </div>`;
  }

  let calendarTitle = `${monthNames[month]} ${year} Calendar`;
  document.getElementById("enlarged-calendar-title").innerText = calendarTitle;
  container.innerHTML = gridHtml;
  lucide.createIcons();
}
