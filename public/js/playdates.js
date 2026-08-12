// Playdates/matchmaking: deck swiping, matches list, profile+preferences setup.

let playdateDeck = [];
let playdateDeckIndex = 0;

function switchPlaydateTab(tab) {
  ["deck", "matches", "setup"].forEach((t) => {
    document.getElementById(`pd-panel-${t}`)?.classList.toggle("hidden", t !== tab);
    const btn = document.getElementById(`pd-tab-${t}`);
    if (btn) {
      btn.classList.toggle("bg-white", t === tab);
      btn.classList.toggle("dark:bg-gray-700", t === tab);
      btn.classList.toggle("text-brand-500", t === tab);
      btn.classList.toggle("shadow-sm", t === tab);
    }
  });
  if (tab === "deck") loadPlaydateDeck();
  if (tab === "matches") loadPlaydateMatches();
  if (tab === "setup") loadPlaydateSetupForm();
  if (window.lucide) lucide.createIcons();
}

function deckCardHtml(candidate) {
  const initial = escapeHtml((candidate.pet_name || "P")[0]);
  const photo = candidate.profile_photo_url
    ? `<img src="${escapeHtml(candidate.profile_photo_url)}" alt="" class="absolute inset-0 w-full h-full object-cover">`
    : `<div class="absolute inset-0 w-full h-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center text-6xl font-bold text-brand-700 dark:text-brand-300">${initial}</div>`;

  return `
  <div class="absolute inset-0 bg-white dark:bg-gray-900 rounded-3xl shadow-xl border border-gray-100 dark:border-gray-800 overflow-hidden flex flex-col">
    <div class="relative h-64 flex-shrink-0">
      ${photo}
      <span class="absolute top-3 right-3 bg-white/90 dark:bg-gray-900/90 backdrop-blur-sm text-brand-600 dark:text-brand-300 text-xs font-bold px-2.5 py-1 rounded-full">${candidate.compatibility_score}% match</span>
    </div>
    <div class="p-5 flex-1 overflow-y-auto">
      <h3 class="text-xl font-bold text-gray-900 dark:text-white">${escapeHtml(candidate.pet_name)}</h3>
      <p class="text-sm text-gray-400">${[candidate.pet_type, candidate.breed, candidate.current_city].filter(Boolean).map(escapeHtml).join(" · ")}</p>
      <div class="flex flex-wrap gap-1.5 mt-3">
        ${candidate.size ? `<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">${escapeHtml(candidate.size)}</span>` : ""}
        ${candidate.energy_level ? `<span class="px-2 py-1 rounded-full text-[10px] font-bold bg-gray-100 dark:bg-gray-800 text-gray-500 dark:text-gray-400">${escapeHtml(candidate.energy_level)} energy</span>` : ""}
      </div>
      ${candidate.bio ? `<p class="text-sm text-gray-600 dark:text-gray-300 mt-3">${escapeHtml(candidate.bio)}</p>` : ""}
      ${candidate.favorite_activities ? `<p class="text-xs text-gray-400 mt-2"><span class="font-semibold">Loves:</span> ${escapeHtml(candidate.favorite_activities)}</p>` : ""}
    </div>
  </div>`;
}

function renderCurrentDeckCard() {
  const wrap = document.getElementById("pd-deck-card-wrap");
  const empty = document.getElementById("pd-deck-empty");
  const actions = document.getElementById("pd-deck-actions");

  if (playdateDeckIndex >= playdateDeck.length) {
    wrap.innerHTML = "";
    wrap.appendChild(empty);
    empty.classList.remove("hidden");
    actions.classList.add("hidden");
    return;
  }

  empty.classList.add("hidden");
  actions.classList.remove("hidden");
  wrap.innerHTML = deckCardHtml(playdateDeck[playdateDeckIndex]);
  wrap.appendChild(empty);
  if (window.lucide) lucide.createIcons();
}

async function loadPlaydateDeck() {
  try {
    const data = await api("get_playdate_deck", {});
    if (data.status !== "success") {
      showToast(data.message || "Could not load the playdate deck.", "error");
      return;
    }
    playdateDeck = data.deck || [];
    playdateDeckIndex = 0;
    renderCurrentDeckCard();
  } catch (err) {
    console.error(err);
    showToast("Could not load the playdate deck.", "error");
  }
}

async function swipeCurrentDeckCard(direction) {
  const candidate = playdateDeck[playdateDeckIndex];
  if (!candidate) return;
  playdateDeckIndex++;
  renderCurrentDeckCard();

  try {
    const data = await api("swipe_playdate", { target_user_id: candidate.user_id, direction });
    if (data.status === "success" && data.is_match) {
      showToast(`🎉 It's a match with ${candidate.pet_name}!`, "success");
    }
  } catch (err) {
    console.error(err);
  }
}

async function loadPlaydateMatches() {
  const list = document.getElementById("pd-matches-list");
  if (!list) return;
  try {
    const data = await api("get_playdate_matches", {});
    if (data.status !== "success") return;
    const matches = data.matches || [];
    list.innerHTML = matches.length
      ? matches.map((m) => `
        <button onclick="switchView('view-social-feed'); switchSocialTab('friends'); openFriendChat('${m.user_id}', '${escapeHtml(m.pet_name)}', ${m.profile_photo_url ? `'${escapeHtml(m.profile_photo_url)}'` : "null"})"
          class="bg-white dark:bg-gray-900 rounded-xl border border-gray-100 dark:border-gray-800 p-3 text-center hover:shadow-md transition-shadow">
          <div class="w-14 h-14 mx-auto rounded-full bg-brand-100 dark:bg-brand-900/40 flex items-center justify-center overflow-hidden mb-2">
            ${m.profile_photo_url ? `<img src="${escapeHtml(m.profile_photo_url)}" class="w-full h-full object-cover">` : `<span class="font-bold text-brand-700 dark:text-brand-300">${escapeHtml((m.pet_name || "P")[0])}</span>`}
          </div>
          <p class="text-sm font-bold text-gray-900 dark:text-white truncate">${escapeHtml(m.pet_name)}</p>
          <p class="text-xs text-gray-400">${escapeHtml(m.pet_type || "")}</p>
        </button>`).join("")
      : `<p class="text-sm text-gray-400 col-span-3 text-center py-12">No matches yet — swipe right on some pets in the Deck tab!</p>`;
  } catch (err) {
    console.error(err);
  }
}

async function loadPlaydateSetupForm() {
  try {
    const data = await api("get_playdate_profile", {});
    if (data.status !== "success") return;

    const p = data.playdate_profile || {};
    document.getElementById("pd-is-active").checked = p.is_active !== false;
    document.getElementById("pd-size").value = p.size || "";
    document.getElementById("pd-energy-level").value = p.energy_level || "";
    document.getElementById("pd-weight-kg").value = p.weight_kg || "";
    document.getElementById("pd-vaccination-status").value = p.vaccination_status || "";
    document.getElementById("pd-friendly-dogs").value = p.friendliness_to_dogs || "";
    document.getElementById("pd-friendly-cats").value = p.friendliness_to_cats || "";
    document.getElementById("pd-favorite-activities").value = p.favorite_activities || "";
    document.getElementById("pd-dietary-restrictions").value = p.dietary_restrictions || "";

    const prefs = data.preferences || {};
    document.getElementById("pd-pref-pet-type").value = prefs.pref_pet_type || "Any";
    document.getElementById("pd-pref-breed").value = (prefs.pref_breed && prefs.pref_breed !== "Any") ? prefs.pref_breed : "";
    document.getElementById("pd-pref-size").value = prefs.pref_size || "Any";
    document.getElementById("pd-pref-energy-level").value = prefs.pref_energy_level || "Any";
    document.getElementById("pd-pref-gender").value = prefs.pref_gender || "Any";
    document.getElementById("pd-pref-age-min").value = prefs.pref_age_min_months ?? 0;
    document.getElementById("pd-pref-age-max").value = prefs.pref_age_max_months ?? 240;
  } catch (err) {
    console.error(err);
  }
}

async function savePlaydateProfileForm() {
  const btn = document.getElementById("pd-profile-save-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("save_playdate_profile", {
      is_active: document.getElementById("pd-is-active").checked ? "1" : "",
      size: document.getElementById("pd-size").value,
      energy_level: document.getElementById("pd-energy-level").value,
      weight_kg: document.getElementById("pd-weight-kg").value,
      vaccination_status: document.getElementById("pd-vaccination-status").value.trim(),
      friendliness_to_dogs: document.getElementById("pd-friendly-dogs").value,
      friendliness_to_cats: document.getElementById("pd-friendly-cats").value,
      favorite_activities: document.getElementById("pd-favorite-activities").value.trim(),
      dietary_restrictions: document.getElementById("pd-dietary-restrictions").value.trim(),
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not save playdate profile.", "error");
      return;
    }
    showToast("Playdate profile saved.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not save playdate profile.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}

async function savePlaydatePreferencesForm() {
  const btn = document.getElementById("pd-prefs-save-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("save_playdate_preferences", {
      pref_pet_type: document.getElementById("pd-pref-pet-type").value,
      pref_breed: document.getElementById("pd-pref-breed").value.trim() || "Any",
      pref_size: document.getElementById("pd-pref-size").value,
      pref_energy_level: document.getElementById("pd-pref-energy-level").value,
      pref_gender: document.getElementById("pd-pref-gender").value,
      pref_age_min_months: document.getElementById("pd-pref-age-min").value,
      pref_age_max_months: document.getElementById("pd-pref-age-max").value,
    });
    if (data.status !== "success") {
      showToast(data.message || "Could not save preferences.", "error");
      return;
    }
    showToast("Preferences saved.", "success");
  } catch (err) {
    console.error(err);
    showToast("Could not save preferences.", "error");
  } finally {
    setButtonLoading(btn, false);
  }
}
