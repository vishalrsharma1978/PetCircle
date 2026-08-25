// Admin dashboard: Servers panel (infrastructure nodes, location globe &
// health status). Ported directly from eSamaj's vendor.js EsamajGlobe class
// (a small wrapper around the "cobe" WebGL globe library, loaded on demand
// from esm.sh — see the added CSP entry in security_headers.php) — this
// panel was never religion-specific, just infra/ops tooling, so the port is
// close to 1:1 with `religion` swapped for `pet_type`.

function hexToRgbPercent(hex) {
  let r = 0, g = 0, b = 0;
  hex = hex.startsWith("#") ? hex.slice(1) : hex;
  if (hex.length === 3) {
    r = parseInt(hex[0] + hex[0], 16);
    g = parseInt(hex[1] + hex[1], 16);
    b = parseInt(hex[2] + hex[2], 16);
  } else if (hex.length === 6) {
    r = parseInt(hex.slice(0, 2), 16);
    g = parseInt(hex.slice(2, 4), 16);
    b = parseInt(hex.slice(4, 6), 16);
  }
  return [r / 255, g / 255, b / 255];
}

function mixRgbPercent(colorA, colorB, ratio = 0.5) {
  const clamped = Math.max(0, Math.min(1, ratio));
  return [
    colorA[0] * (1 - clamped) + colorB[0] * clamped,
    colorA[1] * (1 - clamped) + colorB[1] * clamped,
    colorA[2] * (1 - clamped) + colorB[2] * clamped,
  ];
}

function currentBrandAccentHex() {
  const val = getComputedStyle(document.documentElement).getPropertyValue("--brand-500").trim();
  return val || "#e04848";
}

let cobeModule = null;
async function loadCobe() {
  if (!cobeModule) {
    cobeModule = await import("https://esm.sh/cobe@2.0.1?bundle");
  }
  return cobeModule.default;
}

class PawCircleGlobe {
  constructor(canvasId, wrapperId, labelsId, servers = [], accentColorHex = "#e04848") {
    this.canvas = document.getElementById(canvasId);
    this.wrapper = document.getElementById(wrapperId);
    this.labelsLayer = document.getElementById(labelsId);
    this.servers = servers;
    this.accentColor = hexToRgbPercent(accentColorHex);
    this.baseColor = mixRgbPercent([0.02, 0.03, 0.08], this.accentColor, 0.26);
    this.glowColor = mixRgbPercent([0.08, 0.1, 0.16], this.accentColor, 0.4);

    this.globeInstance = null;
    this.animationFrame = null;
    this.phi = 5.6;
    this.theta = -0.5;
    this.targetPhi = 5.6;
    this.targetTheta = -0.5;
    this.isDragging = false;
    this.lastPointerX = 0;
    this.lastPointerY = 0;
    this.GLOBE_RADIUS_MULTIPLIER = 0.445;

    this.init();
  }

  async init() {
    if (!this.canvas || !this.wrapper) return;
    if (this.labelsLayer) this.labelsLayer.innerHTML = "";

    this.markers = this.servers.map((s) => ({
      location: [parseFloat(s.latitude), parseFloat(s.longitude)],
      size: s.status === "offline" ? 0.03 : 0.06,
      color: s.status === "offline" ? [0.5, 0.5, 0.5] : this.accentColor,
    }));

    this.labelElements = new Map();
    if (this.labelsLayer) {
      for (const s of this.servers) {
        const label = document.createElement("div");
        Object.assign(label.style, {
          position: "absolute", transform: "translate(-50%, -145%)", padding: "4px 8px",
          background: s.status === "offline" ? "#475569" : "var(--brand-500, #e04848)",
          color: "white", fontSize: "10px", fontWeight: "800", letterSpacing: "0.05em",
          fontFamily: "monospace", whiteSpace: "nowrap", pointerEvents: "none", borderRadius: "4px",
          boxShadow: s.status === "offline" ? "none" : "0 0 15px var(--brand-500, #e04848)",
          opacity: "0", transition: "opacity 0.18s ease, transform 0.18s ease", zIndex: "50",
        });
        label.textContent = s.name.toUpperCase();
        this.labelsLayer.appendChild(label);
        this.labelElements.set(s.name, label);
      }
    }

    try {
      const createGlobe = await loadCobe();
      const { width, height, dpr } = this.resizeCanvas();
      this.globeInstance = createGlobe(this.canvas, {
        devicePixelRatio: dpr, width, height, phi: this.phi, theta: this.theta,
        dark: 1, diffuse: 1.4, scale: 1, mapSamples: 16000, mapBrightness: 24, mapBaseBrightness: 0.05,
        baseColor: this.baseColor, markerColor: this.accentColor, glowColor: this.glowColor,
        markers: this.markers, arcColor: this.accentColor, arcWidth: 0.45, arcHeight: 0.28,
        markerElevation: 0.025, offset: [0, 0], opacity: 1,
      });
      this.bindEvents();
      this.animate();
    } catch (err) {
      console.error("Globe init error:", err);
    }
  }

  resizeCanvas() {
    const rect = this.wrapper.getBoundingClientRect();
    const dpr = Math.min(window.devicePixelRatio || 1, 2);
    this.canvas.width = Math.floor(rect.width * dpr);
    this.canvas.height = Math.floor(rect.height * dpr);
    return { width: this.canvas.width, height: this.canvas.height, dpr };
  }

  projectLocation(location) {
    const [lat, lon] = location;
    const rect = this.wrapper.getBoundingClientRect();
    const centerX = rect.width / 2;
    const centerY = rect.height / 2;
    const radius = Math.min(rect.width, rect.height) * this.GLOBE_RADIUS_MULTIPLIER;
    const latRad = (lat * Math.PI) / 180;
    const lonRad = (lon * Math.PI) / 180;
    const cosLat = Math.cos(latRad);
    const x = cosLat * Math.sin(lonRad - this.phi);
    const y = Math.sin(latRad);
    const z = cosLat * Math.cos(lonRad - this.phi);
    const cosTheta = Math.cos(this.theta);
    const sinTheta = Math.sin(this.theta);
    const y2 = y * cosTheta + z * sinTheta;
    const z2 = -y * sinTheta + z * cosTheta;
    return { x: centerX + x * radius, y: centerY - y2 * radius, visible: z2 < 0.05 };
  }

  updateLabels() {
    if (!this.labelsLayer) return;
    for (const s of this.servers) {
      const label = this.labelElements.get(s.name);
      if (!label) continue;
      const point = this.projectLocation([parseFloat(s.latitude), parseFloat(s.longitude)]);
      label.style.left = `${point.x}px`;
      label.style.top = `${point.y}px`;
      label.style.opacity = point.visible ? "1" : "0";
      label.style.transform = point.visible ? "translate(-38%, -128%) scale(1)" : "translate(-38%, -128%) scale(0.9)";
    }
  }

  animate() {
    if (!this.isDragging) this.targetPhi += 0.0018;
    this.phi += (this.targetPhi - this.phi) * 0.12;
    this.theta += (this.targetTheta - this.theta) * 0.12;
    if (this.globeInstance) this.globeInstance.update({ phi: this.phi, theta: this.theta, markers: this.markers });
    this.updateLabels();
    this.animationFrame = requestAnimationFrame(() => this.animate());
  }

  bindEvents() {
    this.canvas.addEventListener("pointerdown", (event) => {
      this.isDragging = true;
      this.lastPointerX = event.clientX;
      this.lastPointerY = event.clientY;
      this.canvas.setPointerCapture(event.pointerId);
    });
    this.canvas.addEventListener("pointermove", (event) => {
      if (!this.isDragging) return;
      const deltaX = event.clientX - this.lastPointerX;
      const deltaY = event.clientY - this.lastPointerY;
      this.lastPointerX = event.clientX;
      this.lastPointerY = event.clientY;
      this.targetPhi += deltaX * 0.008;
      this.targetTheta = Math.max(-0.9, Math.min(0.9, this.targetTheta + deltaY * 0.006));
    });
    const pointerUp = () => { this.isDragging = false; };
    this.canvas.addEventListener("pointerup", pointerUp);
    this.canvas.addEventListener("pointercancel", pointerUp);
    this.resizeHandler = () => {
      if (!this.globeInstance) return;
      const { width, height, dpr } = this.resizeCanvas();
      this.globeInstance.update({ devicePixelRatio: dpr, width, height });
      this.updateLabels();
    };
    window.addEventListener("resize", this.resizeHandler);
  }

  destroy() {
    if (this.animationFrame) cancelAnimationFrame(this.animationFrame);
    if (this.globeInstance) this.globeInstance.destroy();
    if (this.resizeHandler) window.removeEventListener("resize", this.resizeHandler);
  }
}

// ---------------- Servers panel ----------------

let activeServersGlobe = null;
let cachedAdminServers = [];

async function loadAdminServers() {
  const box = document.getElementById("admin-panel-servers");
  if (!box) return;

  box.innerHTML = `
    <div class="grid grid-cols-1 xl:grid-cols-[320px_minmax(0,1fr)] gap-6">
      <div class="flex flex-col rounded-2xl border border-gray-800 bg-gray-950 p-5 h-fit">
        <h4 class="font-bold text-white mb-2">Network Globe</h4>
        <p class="text-xs text-gray-500 mb-4">Click and drag to rotate. Markers reflect server location and status.</p>
        <div id="admin-servers-globe-wrapper" class="relative w-[260px] h-[260px] mx-auto mb-4 bg-slate-950/40 rounded-full border border-gray-800 overflow-hidden">
          <canvas id="admin-servers-globe" class="w-full h-full cursor-grab active:cursor-grabbing"></canvas>
          <div id="admin-servers-globe-labels" class="pointer-events-none absolute inset-0 z-50 overflow-visible"></div>
        </div>
        <div class="border-t border-gray-800 pt-4 mt-2 space-y-2 text-xs">
          <div class="flex items-center gap-2.5"><span class="w-3 h-3 rounded-full" style="background:var(--brand-500,#e04848)"></span><span class="text-gray-300 font-semibold">Online</span></div>
          <div class="flex items-center gap-2.5"><span class="w-3 h-3 rounded-full bg-slate-500"></span><span class="text-gray-300 font-semibold">Offline</span></div>
        </div>
      </div>

      <div class="flex flex-col rounded-2xl border border-gray-800 bg-gray-950 p-5">
        <div class="flex items-center justify-between gap-4 mb-4">
          <h4 class="font-bold text-white">Infrastructure Roster</h4>
          <button type="button" onclick="openAdminServerModal()" class="rounded-xl bg-brand-500 hover:bg-brand-600 text-white font-bold text-sm px-4 py-2 flex items-center gap-2"><i data-lucide="plus" class="w-4 h-4"></i> Create Node</button>
        </div>
        <div id="admin-servers-list" class="space-y-3 max-h-[500px] overflow-y-auto pr-1"><p class="text-sm text-gray-400 py-6 text-center">Loading…</p></div>
      </div>
    </div>
    <div id="admin-server-modal" class="fixed inset-0 bg-gray-950/70 hidden flex-col items-center justify-center p-4 z-[170]">
      <div class="bg-gray-900 border border-gray-800 rounded-2xl w-full max-w-md p-6 space-y-3">
        <h4 id="admin-server-modal-title" class="font-bold text-white mb-2">Create Server Node</h4>
        <input type="hidden" id="admin-server-id">
        <input id="admin-server-name" placeholder="Name" class="w-full px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
        <input id="admin-server-host" placeholder="Host / IP" class="w-full px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
        <div class="grid grid-cols-2 gap-2">
          <input id="admin-server-port" type="number" placeholder="Port" class="px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
          <select id="admin-server-status" class="px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
            <option value="online">Online</option>
            <option value="offline">Offline</option>
          </select>
        </div>
        <div class="grid grid-cols-2 gap-2">
          <input id="admin-server-latitude" type="number" step="any" placeholder="Latitude" class="px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
          <input id="admin-server-longitude" type="number" step="any" placeholder="Longitude" class="px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
        </div>
        <select id="admin-server-pet-type" class="w-full px-3 py-2 rounded-lg text-sm bg-gray-800 border border-gray-700 text-white">
          <option value="global">Global scope</option>
          ${ADMIN_PET_TYPES.map((t) => `<option value="${escapeHtml(t)}">${escapeHtml(t)}</option>`).join("")}
        </select>
        <div class="flex gap-2 pt-2">
          <button id="admin-server-submit-btn" onclick="saveAdminServerNode()" class="flex-1 px-4 py-2 rounded-lg bg-brand-500 hover:bg-brand-600 text-white text-sm font-bold">Save</button>
          <button onclick="closeAdminServerModal()" class="px-4 py-2 rounded-lg bg-gray-800 text-gray-200 text-sm font-bold">Cancel</button>
        </div>
      </div>
    </div>`;

  await fetchAdminServers();
}

async function fetchAdminServers() {
  try {
    const data = await api("admin_get_servers", {});
    if (data.status !== "success") {
      showToast(data.message || "Could not load servers.", "error");
      return;
    }
    cachedAdminServers = data.servers || [];
    renderAdminServersList(cachedAdminServers);
    initializeAdminServersGlobe(cachedAdminServers);
  } catch (err) {
    console.error(err);
  }
}

function renderAdminServersList(servers) {
  const box = document.getElementById("admin-servers-list");
  if (!box) return;
  box.innerHTML = servers.length
    ? servers.map((s) => {
        const isOnline = s.status === "online";
        const scopeLabel = s.pet_type === "global" || !s.pet_type ? "Global Scope" : s.pet_type;
        return `
          <div class="rounded-xl border border-gray-800 bg-gray-900 p-3 flex items-center justify-between gap-3">
            <div class="min-w-0 flex items-start gap-2.5">
              <div class="p-2 rounded-lg ${isOnline ? "bg-emerald-500/10 text-emerald-400" : "bg-rose-500/10 text-rose-400"}"><i data-lucide="${isOnline ? "check-circle-2" : "x-circle"}" class="w-4 h-4"></i></div>
              <div class="min-w-0">
                <div class="flex items-center gap-2"><h5 class="font-bold text-white text-sm truncate">${escapeHtml(s.name)}</h5><span class="text-[10px] font-bold px-2 py-0.5 rounded-full bg-gray-800 text-gray-400">${escapeHtml(scopeLabel)}</span></div>
                <p class="text-xs text-gray-500 mt-0.5">${escapeHtml(s.host)}:${escapeHtml(String(s.port))} · ${s.latency_ms !== null ? s.latency_ms + " ms" : "—"}</p>
              </div>
            </div>
            <div class="flex items-center gap-1 flex-shrink-0">
              <button onclick="pingAdminServer(${s.id}, this)" title="Ping" class="p-1.5 rounded-lg text-gray-400 hover:text-emerald-400 hover:bg-gray-800"><i data-lucide="refresh-cw" class="w-4 h-4"></i></button>
              <button onclick="openAdminServerModal(${s.id})" title="Edit" class="p-1.5 rounded-lg text-gray-400 hover:text-brand-300 hover:bg-gray-800"><i data-lucide="pencil" class="w-4 h-4"></i></button>
              <button onclick="deleteAdminServer(${s.id}, this)" title="Delete" class="p-1.5 rounded-lg text-gray-400 hover:text-rose-400 hover:bg-gray-800"><i data-lucide="trash-2" class="w-4 h-4"></i></button>
            </div>
          </div>`;
      }).join("")
    : `<p class="text-sm text-gray-400 py-6 text-center">No registered server nodes.</p>`;
  if (window.lucide) lucide.createIcons();
}

function initializeAdminServersGlobe(servers) {
  if (activeServersGlobe) {
    activeServersGlobe.destroy();
    activeServersGlobe = null;
  }
  activeServersGlobe = new PawCircleGlobe("admin-servers-globe", "admin-servers-globe-wrapper", "admin-servers-globe-labels", servers, currentBrandAccentHex());
}

function openAdminServerModal(id = null) {
  const modal = document.getElementById("admin-server-modal");
  document.getElementById("admin-server-id").value = "";
  document.getElementById("admin-server-name").value = "";
  document.getElementById("admin-server-host").value = "";
  document.getElementById("admin-server-port").value = "";
  document.getElementById("admin-server-latitude").value = "";
  document.getElementById("admin-server-longitude").value = "";
  document.getElementById("admin-server-status").value = "online";
  document.getElementById("admin-server-pet-type").value = "global";
  document.getElementById("admin-server-modal-title").textContent = id ? "Edit Server Node" : "Create Server Node";

  if (id) {
    const server = cachedAdminServers.find((s) => s.id == id);
    if (server) {
      document.getElementById("admin-server-id").value = server.id;
      document.getElementById("admin-server-name").value = server.name;
      document.getElementById("admin-server-host").value = server.host;
      document.getElementById("admin-server-port").value = server.port;
      document.getElementById("admin-server-latitude").value = server.latitude;
      document.getElementById("admin-server-longitude").value = server.longitude;
      document.getElementById("admin-server-status").value = server.status;
      document.getElementById("admin-server-pet-type").value = server.pet_type || "global";
    }
  }
  modal.classList.remove("hidden");
  modal.classList.add("flex");
  if (window.lucide) lucide.createIcons();
}

function closeAdminServerModal() {
  const modal = document.getElementById("admin-server-modal");
  modal.classList.add("hidden");
  modal.classList.remove("flex");
}

async function saveAdminServerNode() {
  const id = document.getElementById("admin-server-id").value || null;
  const payload = {
    id,
    name: document.getElementById("admin-server-name").value.trim(),
    host: document.getElementById("admin-server-host").value.trim(),
    port: document.getElementById("admin-server-port").value,
    latitude: document.getElementById("admin-server-latitude").value,
    longitude: document.getElementById("admin-server-longitude").value,
    status: document.getElementById("admin-server-status").value,
    pet_type: document.getElementById("admin-server-pet-type").value,
  };
  if (!payload.name || !payload.host) {
    showToast("Name and host are required.", "info");
    return;
  }
  const btn = document.getElementById("admin-server-submit-btn");
  setButtonLoading(btn, true, "Saving…");
  try {
    const data = await api("admin_save_server", payload);
    if (data.status !== "success") {
      showToast(data.message || "Could not save server.", "error");
      return;
    }
    showToast(id ? "Server node updated." : "Server node created.", "success");
    closeAdminServerModal();
    fetchAdminServers();
  } catch (err) {
    console.error(err);
  } finally {
    setButtonLoading(btn, false);
  }
}

async function pingAdminServer(id, btn) {
  const icon = btn?.querySelector("i");
  if (btn) btn.disabled = true;
  icon?.classList.add("animate-spin");
  try {
    const data = await api("admin_ping_server", { id });
    if (data.status !== "success") {
      showToast(data.message || "Ping failed.", "error");
      return;
    }
    showToast(`Ping complete: ${data.latency_ms} ms (${data.status}).`, "success");
    fetchAdminServers();
  } catch (err) {
    console.error(err);
  } finally {
    if (btn) btn.disabled = false;
    icon?.classList.remove("animate-spin");
  }
}

async function deleteAdminServer(id, btn) {
  if (!(await confirmAction({ title: "Decommission this server node?", message: "This permanently deletes it.", confirmLabel: "Delete" }))) return;
  setButtonLoading(btn, true);
  try {
    const data = await api("admin_delete_server", { id });
    if (data.status !== "success") {
      showToast(data.message || "Could not delete server.", "error");
      setButtonLoading(btn, false);
      return;
    }
    showToast("Server node deleted.", "success");
    fetchAdminServers();
  } catch (err) {
    console.error(err);
    setButtonLoading(btn, false);
  }
}

document.addEventListener("click", (e) => {
  if (e.target.id === "admin-server-modal") closeAdminServerModal();
});
