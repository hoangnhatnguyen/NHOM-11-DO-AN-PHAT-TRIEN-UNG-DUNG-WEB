function appBaseUrl() {
  const b = window.__APP_BASE__;
  return typeof b === "string" ? b.replace(/\/$/, "") : "";
}

function escapeAttr(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

function saveRecent(q) {
  const key = String(q || "").trim();
  if (!key) return;

  let arr = JSON.parse(localStorage.getItem("recent") || "[]");
  if (!Array.isArray(arr)) arr = [];
  arr = arr.filter((item) => item !== key);
  arr.unshift(key);
  localStorage.setItem("recent", JSON.stringify(arr.slice(0, 20)));
}

function goSearch(q, e) {
  if (e) e.preventDefault();

  const key = String(q || "").trim();
  if (!key) return;

  saveRecent(key);
  const base = appBaseUrl();
  window.location.href =
    base + "/search?q=" + encodeURIComponent(key) + "&tab=top";
}

document.addEventListener("DOMContentLoaded", () => {
  bindTabs();
  bindSearch();

  const params = new URLSearchParams(window.location.search);
  const q = params.get("q");
  const tab = params.get("tab") || "recent";

  if (q) {
    enterSearchMode();
    setActive(tab);
  } else {
    exitSearchMode();
    loadDefault();
  }
});

function bindSearch() {
  const inputs = document.querySelectorAll(".search-box input");

  inputs.forEach((input) => {
    input.addEventListener("keydown", function (e) {
      if (e.key === "Enter") {
        e.preventDefault();

        const q = this.value.trim();
        if (!q) return;

        goSearch(q);
      }
    });
  });
}

function bindTabs() {
  document.addEventListener("click", function (e) {
    if (e.target.closest(".mini-card")) return;
    const tabEl = e.target.closest(".tab");
    if (!tabEl) return;

    const tab = tabEl.dataset.tab;
    if (!tab) return;

    const params = new URLSearchParams(window.location.search);
    const q = params.get("q");
    const base = appBaseUrl();

    if (q) {
      window.location.href =
        base +
        "/search?q=" +
        encodeURIComponent(q) +
        "&tab=" +
        encodeURIComponent(tab);
      return;
    }

    document.querySelectorAll(".tab").forEach((t) => t.classList.remove("active"));
    tabEl.classList.add("active");

    const box = document.getElementById("default-content");
    if (!box) return;

    if (tab === "trending") {
      renderTrending(box);
    } else {
      renderRecent(box);
    }

    window.history.replaceState({}, "", base + "/search?tab=" + encodeURIComponent(tab));
  });
}

function setActive(tab) {
  document.querySelectorAll(".tab").forEach((t) => {
    t.classList.remove("active");
    if (t.dataset.tab === tab) t.classList.add("active");
  });
}

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

  show('[data-tab="recent"]', defaultMode);
  show('[data-tab="trending"]', defaultMode);

  show('[data-tab="top"]', !defaultMode);
  show('[data-tab="latest"]', !defaultMode);
  show('[data-tab="users"]', !defaultMode);
}

function loadDefault() {
  const box = document.getElementById("default-content");
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

function renderRecent(box) {
  let list = JSON.parse(localStorage.getItem("recent") || "[]");
  if (!Array.isArray(list)) list = [];

  if (list.length === 0) {
    box.innerHTML = "Chưa có tìm kiếm";
    return;
  }

  box.innerHTML = list
    .map((i) => {
      const raw = String(i);
      return `
    <div class="mini-card" data-q="${escapeAttr(raw)}" role="button" tabindex="0">
      <div class="mini-icon">🕘</div>
      <div class="mini-text">${escapeHtml(raw)}</div>
    </div>
  `;
    })
    .join("");
}

function renderTrending(box) {
  const base = appBaseUrl();
  fetch(base + "/api/search.php?type=trending", { credentials: "same-origin" })
    .then((r) => r.json())
    .then((res) => {
      let hashtags = [];

      if (Array.isArray(res.data)) {
        hashtags = res.data;
      } else if (res.data && res.data.hashtags) {
        hashtags = res.data.hashtags;
      }

      if (!hashtags.length) {
        box.innerHTML = `<div class="text-muted small">Không có trending</div>`;
        return;
      }

      box.innerHTML = hashtags
        .map((t, i) => {
          const name = typeof t === "string" ? t : String(t.name || "");
          const qVal = "#" + name;
          return `
          <div class="mini-card" data-q="${escapeAttr(qVal)}" role="button" tabindex="0">
            <div class="mini-icon">🔥</div>
            <div class="mini-text">${i + 1}. #${escapeHtml(name)}</div>
          </div>
        `;
        })
        .join("");
    })
    .catch(() => {});
}

document.addEventListener("click", function (e) {
  const card = e.target.closest(".mini-card[data-q]");
  if (!card) return;

  const q = card.getAttribute("data-q");
  if (!q) return;

  e.preventDefault();
  goSearch(q);
});

const form = document.getElementById("searchFilterForm");

if (form) {
  form.addEventListener("submit", function (e) {
    e.preventDefault();

    const url = new URL(window.location.href);
    const params = url.searchParams;

    const formData = new FormData(this);

    for (let [key, value] of formData.entries()) {
      if (value) params.set(key, value);
      else params.delete(key);
    }

    let tab = params.get("tab") || "users";

    if (tab === "people") tab = "users";

    params.set("tab", tab);

    window.location.href = appBaseUrl() + "/search?" + params.toString();
  });
}

function applyFilter() {
  const url = new URL(window.location.href);

  const q = url.searchParams.get("q");
  if (q) url.searchParams.set("q", q);

  const activeTab = document.querySelector(".tab.active");
  let currentTab = activeTab ? activeTab.dataset.tab : undefined;

  if (currentTab === "people") currentTab = "users";
  if (!currentTab) currentTab = "users";

  url.searchParams.set("tab", currentTab);

  const userChecked = document.querySelector('input[name="filter_user"]:checked');
  if (userChecked) {
    url.searchParams.set("filter_user", userChecked.value);
  }

  const from = document.querySelector('input[type="date"]:nth-of-type(1)');
  const to = document.querySelector('input[type="date"]:nth-of-type(2)');
  const fromVal = from ? from.value : "";
  const toVal = to ? to.value : "";

  if (fromVal) url.searchParams.set("from", fromVal);
  if (toVal) url.searchParams.set("to", toVal);

  window.location.href = url.toString();
}
