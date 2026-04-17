/**
 * BASE_URL từ PHP; nếu rỗng thì suy ra từ pathname để fetch /api/* và /user-api/* không bị trỏ nhầm lên gốc host.
 */
function appBaseUrl() {
  var b = window.__APP_BASE__;
  if (typeof b === "string") {
    b = b.replace(/\/$/, "");
    if (b !== "") return b;
  }
  var path = window.location.pathname.replace(/\/$/, "");
  if (path === "") path = "/";
  var lower = path.toLowerCase();
  var markers = [
    "/search",
    "/notifications",
    "/messages",
    "/settings",
    "/login",
    "/register",
    "/post/",
    "/profile",
    "/user/",
    "/users/finder",
  ];
  for (var i = 0; i < markers.length; i++) {
    var m = markers[i];
    var idx = lower.indexOf(m);
    if (idx !== -1) {
      return path.slice(0, idx) || "";
    }
  }
  var slash = path.lastIndexOf("/");
  if (slash > 0) return path.slice(0, slash);
  return "";
}

/** Cùng key localStorage "recent" với search.js — luôn gọi hàm này khi mở kết quả tìm từ sidebar */
function persistRecentAndGoSearch(rawQ) {
  const q = String(rawQ || "").trim();
  if (!q) return;

  try {
    let arr = JSON.parse(localStorage.getItem("recent") || "[]");
    if (!Array.isArray(arr)) arr = [];
    arr = arr.filter((item) => item !== q);
    arr.unshift(q);
    localStorage.setItem("recent", JSON.stringify(arr.slice(0, 20)));
  } catch (e) {}

  const base = appBaseUrl();
  window.location.href =
    base + "/search?q=" + encodeURIComponent(q) + "&tab=top";
}

function escapeAttr(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/"/g, "&quot;")
    .replace(/'/g, "&#39;");
}

document.addEventListener("click", function (e) {
  const trend = e.target.closest(".trend[data-q]");
  if (!trend) return;
  const q = trend.getAttribute("data-q");
  if (!q) return;
  e.preventDefault();
  persistRecentAndGoSearch(q);
});

document.addEventListener("DOMContentLoaded", () => {
  loadTrending();
});

document.addEventListener("keydown", function (e) {
  const input = e.target;

  if (input.tagName !== "INPUT") return;
  if (!input.closest(".search-box")) return;

  if (e.key === "Enter") {
    e.preventDefault();

    const keyword = input.value.trim();
    if (!keyword) return;

    persistRecentAndGoSearch(keyword);
  }
});

function loadTrending() {
  const base = appBaseUrl();
  const box = document.getElementById("right-trending");
  if (!box) return;

  fetch(base + "/api/search.php?type=trending_full", { credentials: "same-origin" })
    .then((res) => {
      if (!res.ok) throw new Error("trending");
      return res.json();
    })
    .then((res) => {
      if (!res || res.status !== "success" || !Array.isArray(res.data)) {
        return;
      }
      const data = res.data.slice(0, 5);
      if (data.length === 0) {
        box.innerHTML =
          '<p class="text-muted small mb-0">Chưa có hashtag nào trong bài viết active.</p>';
        return;
      }

      box.className = "d-flex flex-column gap-2";
      box.innerHTML = data
        .map((t, i) => {
          const name = typeof t === "string" ? t : String(t.name || "");
          if (!name) return "";
          const qVal = "#" + name;
          return `
        <div class="trend" data-q="${escapeAttr(qVal)}" role="button" tabindex="0">
          <small class="text-secondary">#${i + 1} Trending</small><br>
          <b>#${escapeHtml(name)}</b>
        </div>
      `;
        })
        .join("");
    })
    .catch(() => {});
}

function escapeHtml(s) {
  return String(s)
    .replace(/&/g, "&amp;")
    .replace(/</g, "&lt;")
    .replace(/>/g, "&gt;");
}

