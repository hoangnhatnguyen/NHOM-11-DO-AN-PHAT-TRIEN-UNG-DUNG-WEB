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
    .then((res) => {
      if (!res.ok) throw new Error("trending");
      return res.json();
    })
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

function followErrorMessage(err) {
  if (err === "blocked_relationship") {
    return "Không thể theo dõi người dùng này vì hai bên đang có trạng thái chặn.";
  }
  if (err === "follow_requires_mutual") {
    return "Người dùng này chỉ cho phép bạn chung theo dõi.";
  }
  if (err === "user_not_found") {
    return "Không tìm thấy người dùng.";
  }
  return "Không thể theo dõi lúc này.";
}

function showFollowErrorPopup(message) {
  if (typeof bootstrap === "undefined") {
    window.alert(message);
    return;
  }

  let modalEl = document.getElementById("followErrorModal");
  if (!modalEl) {
    const wrapper = document.createElement("div");
    wrapper.innerHTML = `
      <div class="modal fade" id="followErrorModal" tabindex="-1" aria-hidden="true">
        <div class="modal-dialog modal-dialog-centered">
          <div class="modal-content border-0 shadow">
            <div class="modal-header border-0 pb-0">
              <h5 class="modal-title fw-semibold">Thông báo</h5>
              <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Đóng"></button>
            </div>
            <div class="modal-body pt-2">
              <p class="mb-0" id="followErrorModalText"></p>
            </div>
            <div class="modal-footer border-0 pt-0">
              <button type="button" class="btn btn-primary rounded-pill px-4" data-bs-dismiss="modal">OK</button>
            </div>
          </div>
        </div>
      </div>
    `;
    document.body.appendChild(wrapper.firstElementChild);
    modalEl = document.getElementById("followErrorModal");
  }

  const textEl = document.getElementById("followErrorModalText");
  if (textEl) {
    textEl.textContent = message;
  }
  bootstrap.Modal.getOrCreateInstance(modalEl).show();
}

function loadSuggestUsers() {
  const base = appBaseUrl();
  const box = document.getElementById("suggestBox");
  if (!box) return;

  fetch(base + "/api/search.php?type=suggest_users", { credentials: "same-origin" })
    .then((res) => {
      if (!res.ok) throw new Error("suggest_http");
      return res.json();
    })
    .then((res) => {
      if (!box) return;

      const data = res.data || [];
      if (!Array.isArray(data)) return;

      if (data.length === 0) {
        box.innerHTML =
          '<li class="text-muted small mb-0">Chưa có tài khoản để gợi ý (hoặc bạn đã theo dõi hết).</li>';
        return;
      }

      box.innerHTML = data
        .map(
          (u) => {
            const uname = String(u.username || "");
            const profileHref = base + "/profile?u=" + encodeURIComponent(uname);
            const avatarSrc = String(u.avatar_src || "").trim();
            const initials = escapeHtml(String(u.initials || "").slice(0, 4));
            const bg = escapeAttr(String(u.avatar_bg || "#6c757d"));
            const fg = escapeAttr(String(u.avatar_fg || "#ffffff"));
            const avatarHtml = avatarSrc
              ? `<img src="${escapeAttr(avatarSrc)}" alt="" width="36" height="36" class="rounded-circle flex-shrink-0" style="object-fit:cover" onerror="this.style.display='none'; var s=this.nextElementSibling; if(s) s.style.display='flex';">
          <span class="avatar-sm flex-shrink-0 align-items-center justify-content-center" style="background:${bg};color:${fg};display:none;width:36px;height:36px;border-radius:50%;font-weight:600;font-size:0.85rem;">${initials}</span>`
              : `<span class="avatar-sm flex-shrink-0 d-flex align-items-center justify-content-center" style="background:${bg};color:${fg};width:36px;height:36px;border-radius:50%;font-weight:600;font-size:0.85rem;">${initials}</span>`;
            return `
        <li class="d-flex justify-content-between align-items-center mb-2 gap-2">
          <a href="${escapeAttr(profileHref)}" class="text-decoration-none text-body d-flex align-items-center gap-2 min-w-0 flex-grow-1">
            ${avatarHtml}
            <span class="text-truncate">@${escapeHtml(uname)}</span>
          </a>
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
            .then(async (r) => {
              const data = await r.json().catch(() => ({}));
              if (!r.ok || !data || !data.success) {
                throw new Error((data && data.error) || "follow_failed");
              }
              return data;
            })
            .then(() => {
              var li = self.closest("li");
              if (li) li.remove();
            })
            .catch((err) => {
              showFollowErrorPopup(followErrorMessage(err && err.message));
            })
            .finally(() => {
              self.disabled = false;
            });
        });
      });
    })
    .catch(function () {
      if (!box) return;
      box.innerHTML =
        '<li class="text-muted small mb-0">Không tải được gợi ý. Hãy tải lại trang (Ctrl+F5) hoặc kiểm tra đường dẫn ứng dụng (BASE_URL).</li>';
    });
}
