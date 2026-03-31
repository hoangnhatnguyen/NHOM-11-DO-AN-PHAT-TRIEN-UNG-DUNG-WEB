console.log("🔥 search.js loaded");

document.addEventListener("DOMContentLoaded", () => {

  bindTabs();
  bindSearch();
  console.log("BIND SEARCH RUNNING");

  const params = new URLSearchParams(window.location.search);
const q = params.get("q");
const tab = params.get("tab") || "recent";

if (q) {
  enterSearchMode();
  setActive(tab);
  
}

else {
  exitSearchMode();
  loadDefault(); // luôn load lại đúng tab
}
});


// ===== SEARCH INPUT =====
function bindSearch() {
  const inputs = document.querySelectorAll('.search-box input');

  inputs.forEach(input => {

    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();

        const q = this.value.trim();
        if (!q) return;

        saveRecent(q); // 🔥 QUAN TRỌNG

        window.location.href = `/search?q=${encodeURIComponent(q)}&tab=top`;
      }
    });

  });
}


// ===== TAB =====
function bindTabs() {
  document.addEventListener("click", function (e) {
    if (e.target.closest(".mini-card")) return;
    const tabEl = e.target.closest(".tab");
    if (!tabEl) return;

    const tab = tabEl.dataset.tab;
    if (!tab) return;

    const params = new URLSearchParams(window.location.search);
    const q = params.get("q");

    // 👉 nếu đang search → reload
    if (q) {
      window.location.href = `/search?q=${encodeURIComponent(q)}&tab=${tab}`;
      return;
    }

    // 👉 default page (recent / trending)
    document.querySelectorAll(".tab").forEach(t => t.classList.remove("active"));
    tabEl.classList.add("active");

    const box = document.getElementById("default-content");
    if (!box) return;

    if (tab === "trending") {
      renderTrending(box);
    } else {
      renderRecent(box);
    }

    window.history.replaceState({}, "", `/search?tab=${tab}`);
  });
}


// ===== SET ACTIVE =====
function setActive(tab) {
  document.querySelectorAll(".tab").forEach(t => {
    t.classList.remove("active");
    if (t.dataset.tab === tab) t.classList.add("active");
  });
}


// ===== MODE =====
function enterSearchMode() {
  toggleTabs(false);
}

function exitSearchMode() {
  toggleTabs(true);
}

function toggleTabs(defaultMode) {
  const show = (selector, state) => {
    const el = document.querySelector(selector);
    if (el) el.style.display = state ? "block" : "none";
  };

  // default mode
  show('[data-tab="recent"]', defaultMode);
  show('[data-tab="trending"]', defaultMode);

  // search mode
  show('[data-tab="top"]', !defaultMode);
  show('[data-tab="latest"]', !defaultMode);
  show('[data-tab="users"]', !defaultMode);
}


// ===== RECENT =====

function saveRecent(q) {
    console.log("SAVE:", q);

    let arr = JSON.parse(localStorage.getItem("recent")) || [];

    arr.unshift(q);

    localStorage.setItem("recent", JSON.stringify(arr));
}

// ===== DEFAULT PAGE =====
function loadDefault() {

  const box = document.getElementById("default-content");
  console.log("LOAD DEFAULT", box); 
  if (!box) return;

  const params = new URLSearchParams(window.location.search);
  const tab = params.get("tab") || "recent";

  if (tab === "trending") {
    renderTrending(box);
  } else {
    renderRecent(box);
  }

  setActive(tab);
}


// ===== CLICK SEARCH =====
function goSearch(q, e) {
    if (e) e.preventDefault(); // 🔥 chặn form

    saveRecent(q);
    window.location.href = `/search?q=${encodeURIComponent(q)}&tab=top`;
}


// ===== DEFAULT RENDER =====
function renderRecent(box) {
  let list = JSON.parse(localStorage.getItem("recent") || "[]");

  if (list.length === 0) {
    box.innerHTML = "Chưa có tìm kiếm";
    return;
  }

    box.innerHTML = list.map(i => `
    <div class="mini-card" data-q="${i}">
      <div class="mini-icon">🕘</div>
      <div class="mini-text">${i}</div>
    </div>
  `).join("");
}


function renderTrending(box) {
  fetch("/api/search.php?type=trending")
    .then(r => r.json())
    .then(res => {

      let hashtags = [];

      if (Array.isArray(res.data)) {
        hashtags = res.data;
      } else if (res.data?.hashtags) {
        hashtags = res.data.hashtags;
      }

      if (!hashtags.length) {
        box.innerHTML = `<div class="text-muted small">Không có trending</div>`;
        return;
      }

      box.innerHTML = hashtags.map((t, i) => {

        const name = typeof t === "string" ? t : t.name;

        return `
          <div class="mini-card" onclick="goSearch('#${name}')">
            <div class="mini-icon">🔥</div>
            <div class="mini-text">${i + 1}. #${name}</div>
          </div>
        `;
      }).join("");
    });
}


// ===== FILTER FORM =====
const form = document.getElementById("searchFilterForm");

if (form) {
  form.addEventListener("submit", function(e) {
    e.preventDefault();

    const url = new URL(window.location.href);
    const params = url.searchParams;

    const formData = new FormData(this);

    for (let [key, value] of formData.entries()) {
      if (value) params.set(key, value);
      else params.delete(key);
    }

    // 🔥 QUAN TRỌNG: GIỮ TAB
    let tab = params.get("tab") || "users";

    if (tab === "people") tab = "users"; // fix lệch tên

    params.set("tab", tab);

    window.location.href = "/search?" + params.toString();
  });
}


// ===== APPLY FILTER BUTTON (OPTIONAL) =====
function applyFilter() {

  const url = new URL(window.location.href);

  // ===== giữ q =====
  const q = url.searchParams.get("q");
  if (q) url.searchParams.set("q", q);

  // ===== 🔥 FIX TAB MAPPING =====
  let currentTab = document.querySelector(".tab.active")?.dataset.tab;

  // map lại cho backend hiểu
  if (currentTab === "people") currentTab = "users";
  if (!currentTab) currentTab = "users";

  url.searchParams.set("tab", currentTab);

  // ===== USER FILTER =====
  const userChecked = document.querySelector('input[name="filter_user"]:checked');
  if (userChecked) {
    url.searchParams.set("filter_user", userChecked.value);
  }

  // ===== DATE =====
  const from = document.querySelector('input[type="date"]:nth-of-type(1)')?.value;
  const to = document.querySelector('input[type="date"]:nth-of-type(2)')?.value;

  if (from) url.searchParams.set("from", from);
  if (to) url.searchParams.set("to", to);

  window.location.href = url.toString();
}

document.addEventListener("click", function(e) {
  const card = e.target.closest(".mini-card");
  if (!card) return;

  const q = card.dataset.q;
  if (!q) return;

  goSearch(q);
});