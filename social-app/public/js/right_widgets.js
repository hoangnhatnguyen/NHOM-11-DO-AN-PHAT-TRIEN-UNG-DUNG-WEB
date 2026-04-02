function appBaseUrl() {
  const b = window.__APP_BASE__;
  return typeof b === "string" ? b.replace(/\/$/, "") : "";
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
  if (document.getElementById("suggestBox")) {
    loadSuggestUsers();
  }
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
    .then((res) => res.json())
    .then((res) => {
      if (!res || res.status !== "success" || !Array.isArray(res.data)) {
        return;
      }
      const data = res.data;
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

function loadSuggestUsers() {
  const base = appBaseUrl();
  fetch(base + "/api/search.php?type=suggest_users", { credentials: "same-origin" })
    .then((res) => res.json())
    .then((res) => {
      const box = document.getElementById("suggestBox");
      if (!box) return;

      const data = res.data || [];
      if (!Array.isArray(data)) return;

      box.innerHTML = data
        .map(
          (u) => {
            const uname = String(u.username || "");
            const profileHref = base + "/profile?u=" + encodeURIComponent(uname);
            return `
        <li class="d-flex justify-content-between align-items-center mb-2 gap-2">
          <a href="${escapeAttr(profileHref)}" class="text-decoration-none text-body text-truncate min-w-0">@${escapeHtml(uname)}</a>
          <button type="button" class="btn btn-sm rounded-pill btn-brand-follow btn-follow-suggest flex-shrink-0" data-user-id="${escapeAttr(String(u.id))}">Theo dõi</button>
        </li>
      `;
          }
        )
        .join("");

      box.querySelectorAll(".btn-follow-suggest").forEach(function (btn) {
        btn.addEventListener("click", function () {
          const id = parseInt(this.getAttribute("data-user-id") || "0", 10);
          if (!id) return;
          const self = this;
          self.disabled = true;
          const fd = new FormData();
          fd.append("target_id", String(id));
          fetch(base + "/user-api/follow?action=follow", {
            method: "POST",
            body: fd,
            credentials: "same-origin",
          })
            .then((r) => r.json())
            .then((data) => {
              if (data && data.success) {
                var li = self.closest("li");
                if (li) li.remove();
              }
            })
            .catch(() => {})
            .finally(() => {
              self.disabled = false;
            });
        });
      });
    })
    .catch(() => {});
}
