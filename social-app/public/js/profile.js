
const BASE =
    typeof window.__APP_BASE__ === "string"
        ? window.__APP_BASE__.replace(/\/$/, "")
        : "";

function mediaViewUrl(keyOrUrl) {
    const raw = String(keyOrUrl || "").trim();
    if (!raw) return "";

    if (/^https?:\/\//i.test(raw)) return raw;
    if (!BASE) return raw;

    if (raw.startsWith(BASE + "/")) return raw;
    if (raw.startsWith("/")) return BASE + raw;

    const normalized = raw.replace(/\\/g, "/").replace(/^\/+/, "");

    if (/^(avatars|posts|chat)\//i.test(normalized)) {
        return BASE + "/media/view?key=" + encodeURIComponent(normalized);
    }

    if (/^media\//i.test(normalized)) {
        return BASE + "/public/" + normalized;
    }

    if (/^public\//i.test(normalized)) {
        return BASE + "/" + normalized;
    }

    return BASE + "/public/media/" + normalized;
}

// ===== ELEMENT =====
const avatarInput = document.getElementById("avatarInput");
const avatarContainer = document.getElementById("profileAvatarContainer");

let isEditing = false;

function showProfileNotice(message, type = "success") {
    const area = document.getElementById("badgeArea") || document.getElementById("bioText");
    if (!area) return;

    const old = document.getElementById("profileNotice");
    if (old) old.remove();

    const el = document.createElement("div");
    el.id = "profileNotice";
    el.className = `alert alert-${type} py-2 px-3 mt-2 mb-2`;
    el.textContent = message;
    area.parentElement?.insertBefore(el, area);

    setTimeout(() => el.remove(), 2200);
}

// ===== EDIT MODE FULL =====
const editBtn = document.getElementById("editBtn");

editBtn?.addEventListener("click", () => {
    isEditing = true;

    const bioText = document.getElementById("bioText");
    const originalBio = (bioText?.innerText || "").trim();
    if (!bioText) return;

    bioText.innerHTML = `
        <textarea id="bioInput" class="form-control mb-2" rows="3">${originalBio}</textarea>
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-brand-follow btn-sm" id="saveBtn">Lưu</button>
            <button class="btn btn-secondary btn-sm" id="cancelBtn">Hủy</button>
        </div>
    `;

    document.getElementById("addBadgeBtn")?.classList.remove("d-none");
    editBtn.classList.add("d-none");

    document.getElementById("saveBtn").onclick = async () => {
        const bio = (document.getElementById("bioInput")?.value || "").trim();

        const res = await fetch(BASE + "/user-api/update-profile", {
            method: "POST",
            credentials: "same-origin",
            body: new URLSearchParams({ bio }),
        });

        if (!res.ok) {
            showProfileNotice("Không thể lưu thông tin.", "danger");
            return;
        }

        bioText.textContent = bio;
        isEditing = false;
        editBtn.classList.remove("d-none");
        showProfileNotice("Đã lưu thông tin.");
    };

    document.getElementById("cancelBtn").onclick = () => {
        bioText.textContent = originalBio;
        isEditing = false;
        editBtn.classList.remove("d-none");
    };
});

avatarInput?.addEventListener("change", () => {
    const file = avatarInput.files && avatarInput.files[0];
    if (!file) return;

    let form = new FormData();
    form.append("avatar", file);

    fetch(BASE + "/user-api/upload-avatar", {
        method: "POST",
        body: form,
        credentials: "same-origin",
    })
        .then((r) => r.json())
        .then((data) => {
            if (data.error) {
                console.error("Upload error:", data.error);
                let msg = "Không tải được ảnh: " + data.error;
                if (data.hint) msg += "\n\n" + data.hint;
                alert(msg);
            } else if (data.success || data.url) {
                location.reload();
            } else {
                console.error("Unexpected response:", data);
                alert("Tải ảnh thất bại.");
            }
        })
        .catch((err) => {
            console.error("Avatar upload error:", err);
            alert("Lỗi tải ảnh: " + err.message);
        })
        .finally(() => {
            avatarInput.value = "";
        });
});

// ===== FOLLOW =====
document.getElementById("followersBtn")?.addEventListener("click", () => {
    fetch(BASE + "/user-api/followers?user_id=" + USER_ID, { credentials: "same-origin" })
        .then((r) => r.json())
        .then((data) => {
            renderList(data.followers, "Chưa có follower nào 😢");
            new bootstrap.Modal(document.getElementById("followModal")).show();
        });
});

document.getElementById("followingBtn")?.addEventListener("click", () => {
    fetch(BASE + "/user-api/following?user_id=" + USER_ID, { credentials: "same-origin" })
        .then((r) => r.json())
        .then((data) => {
            renderList(data.following, "Bạn chưa follow ai cả 😆");
            new bootstrap.Modal(document.getElementById("followModal")).show();
        });
});

function escHtml(s) {
    return String(s)
        .replace(/&/g, "&amp;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;")
        .replace(/"/g, "&quot;");
}

    function escAttr(s) {
        return String(s)
        .replace(/&/g, "&amp;")
        .replace(/"/g, "&quot;")
        .replace(/</g, "&lt;")
        .replace(/>/g, "&gt;");
    }

function initialOf(name) {
    const value = String(name || "").trim();
    return value ? value.charAt(0).toUpperCase() : "?";
}

function renderList(list, emptyMsg) {
    let html = "";

    if (!list || list.length === 0) {
        html = `<p class="text-center text-muted">${emptyMsg}</p>`;
    } else {
        list.forEach((u) => {
            const uname = String(u.username || "");
            const href = BASE + "/profile?u=" + encodeURIComponent(uname);
            const rawAv = u.avatar_src
                ? String(u.avatar_src)
                : (u.avatar_url ? String(u.avatar_url) : "");
            const av = rawAv ? mediaViewUrl(rawAv) : "";
            const initial = initialOf(uname);
            html += `
                <a href="${escHtml(href)}" class="d-flex align-items-center gap-2 mb-2 text-decoration-none text-body">
                    ${
                        av
                            ? `<img
                                    src="${escHtml(av)}"
                                    width="40"
                                    height="40"
                                    class="rounded-circle object-fit-cover flex-shrink-0"
                                    alt="${escHtml(uname)}"
                                    onerror="this.style.display='none'; this.nextElementSibling.style.display='inline-flex';"
                               >
                               <span
                                    class="rounded-circle align-items-center justify-content-center flex-shrink-0"
                                    style="width:40px; height:40px; background:#8adfd7; color:#0a3d3a; font-weight:700; display:none;"
                               >${escHtml(initial)}</span>`
                            : `<span
                                    class="rounded-circle d-inline-flex align-items-center justify-content-center flex-shrink-0"
                                    style="width:40px; height:40px; background:#8adfd7; color:#0a3d3a; font-weight:700;"
                               >${escHtml(initial)}</span>`
                    }
                    <span>${escHtml(uname)}</span>
                </a>
            `;
        });
    }

    document.getElementById("followList").innerHTML = html;
}

// ===== BADGE REMOVE =====
function bindBadgeItemClick(badge) {
    badge.addEventListener("click", (e) => {
        if (!isEditing) return;

        e.stopPropagation();

        document.querySelectorAll(".badge-popup").forEach((p) => p.remove());

        let popup = document.createElement("div");
        popup.className = "badge-popup position-absolute bg-white border rounded px-2 py-1";

        popup.innerHTML = `
            <button class="btn btn-sm btn-danger">Xóa</button>
        `;

        badge.appendChild(popup);

        popup.querySelector("button").onclick = async () => {
            const res = await fetch(BASE + "/user-api/remove-badge", {
                method: "POST",
                credentials: "same-origin",
                body: new URLSearchParams({ badge_id: badge.dataset.id }),
            });

            if (!res.ok) {
                showProfileNotice("Không thể xóa badge.", "danger");
                return;
            }

            badge.remove();
            const badgeArea = document.getElementById("badgeArea");
            const left = badgeArea?.querySelectorAll(".badge-item")?.length || 0;
            if (left === 0 && badgeArea && !badgeArea.querySelector("[data-empty-badge='1']")) {
                const empty = document.createElement("div");
                empty.className = "text-muted small";
                empty.setAttribute("data-empty-badge", "1");
                empty.textContent = "Chưa có badge nào ✨";
                badgeArea.insertBefore(empty, document.getElementById("addBadgeBtn") || null);
            }
            showProfileNotice("Đã xóa badge.");
        };
    });
}

document.querySelectorAll(".badge-item").forEach(bindBadgeItemClick);

// ===== ADD BADGE =====
const addBtn = document.getElementById("addBadgeBtn");
const popup = document.getElementById("badgePopup");
const searchInput = document.getElementById("badgeSearch");
const resultBox = document.getElementById("badgeResult");

addBtn?.addEventListener("click", () => {
    popup.classList.remove("d-none");
    searchInput.focus();
});

function renderBadgeSearchResult(list, q) {
    let html = "";
    const safeQ = escHtml(q);
    const attrQ = escAttr(q);
    const hasExact = list.some((b) => String(b.name || "").toLowerCase() === q.toLowerCase());

    if (q !== "" && !hasExact) {
        html += `
            <button type="button" class="btn btn-link text-decoration-none fw-semibold p-2 w-100 text-start" data-create-badge="1" data-name="${attrQ}">
                + Tạo mới "${safeQ}"
            </button>
        `;
    }

    list.forEach((b) => {
        const name = escHtml(String(b.name || ""));
        const attrName = escAttr(String(b.name || ""));
        html += `
            <button type="button" class="btn btn-light p-2 w-100 text-start border-bottom rounded-0 badge-select" data-name="${attrName}">
                ${name}
            </button>
        `;
    });

    if (!html) {
        html = `<div class="text-muted small p-2">Không có kết quả.</div>`;
    }

    resultBox.innerHTML = html;

    resultBox.querySelectorAll(".badge-select,[data-create-badge='1']").forEach((el) => {
        el.addEventListener("click", () => addBadge(el.getAttribute("data-name") || ""));
    });
}

searchInput?.addEventListener("input", () => {
    const q = searchInput.value.trim();

    fetch(BASE + "/user-api/search-badge?q=" + encodeURIComponent(q), { credentials: "same-origin" })
        .then((r) => {
            if (!r.ok) throw new Error("search_failed");
            return r.json();
        })
        .then((res) => {
            const list = Array.isArray(res.data) ? res.data : [];
            renderBadgeSearchResult(list, q);
        })
        .catch(() => {
            resultBox.innerHTML = `<div class="text-danger small p-2">Không tải được kết quả.</div>`;
        });
});

searchInput?.addEventListener("keydown", (e) => {
    if (e.key === "Enter") {
        e.preventDefault();
        addBadge(searchInput.value.trim());
    }
});

function addBadge(name) {
    if (!name) return;

    fetch(BASE + "/user-api/add-badge", {
        method: "POST",
        credentials: "same-origin",
        body: new URLSearchParams({ name }),
    })
        .then((r) => r.json())
        .then((res) => {
            if (!res || !res.success) {
                showProfileNotice("Không thể thêm badge.", "danger");
                return;
            }

            const badgeArea = document.getElementById("badgeArea");
            if (!badgeArea) return;

            badgeArea.querySelector("[data-empty-badge='1']")?.remove();

            const id = Number(res.badge?.id || 0);
            const badgeName = String(res.badge?.name || name).trim();

            const duplicated = Array.from(badgeArea.querySelectorAll(".badge-item")).some((el) => {
                const n = String(el.textContent || "").trim().toLowerCase();
                return n === badgeName.toLowerCase();
            });
            if (duplicated) {
                showProfileNotice("Badge đã tồn tại.", "info");
                return;
            }

            const node = document.createElement("div");
            node.className = "badge badge-item px-3 py-2 rounded-pill text-white";
            node.style.cssText = "cursor:pointer; font-size:13px; background: var(--brand-primary);";
            if (id > 0) node.dataset.id = String(id);
            node.textContent = badgeName;

            const addButton = document.getElementById("addBadgeBtn");
            badgeArea.insertBefore(node, addButton || null);
            bindBadgeItemClick(node);

            if (popup) popup.classList.add("d-none");
            if (searchInput) searchInput.value = "";
            if (resultBox) resultBox.innerHTML = "";
            showProfileNotice("Đã thêm badge.");
        })
        .catch(() => showProfileNotice("Không thể thêm badge.", "danger"));
}

document.addEventListener("click", (e) => {
    if (!popup) return;

    if (!popup.contains(e.target) && e.target !== addBtn) {
        popup.classList.add("d-none");
    }
});

let activityLoaded = false;

document.querySelectorAll("[data-tab]").forEach((tab) => {
    tab.addEventListener("click", () => {
        document.querySelectorAll("[data-tab]").forEach((t) => t.classList.remove("active"));
        tab.classList.add("active");

        const postsEl = document.getElementById("tabContentPosts");
        const activityEl = document.getElementById("tabContentActivity");

        if (tab.dataset.tab === "posts") {
            postsEl?.classList.remove("d-none");
            activityEl?.classList.add("d-none");
        } else if (tab.dataset.tab === "activity") {
            postsEl?.classList.add("d-none");
            activityEl?.classList.remove("d-none");
            if (!activityLoaded) {
                activityLoaded = true;
                loadActivity();
            }
        }
    });
});

function loadActivity() {
    const el = document.getElementById("tabContentActivity");
    if (!el) return;

    el.innerHTML = "<p class='text-muted mb-0'>Đang tải...</p>";

    fetch(BASE + "/user-api/activity?user_id=" + USER_ID, { credentials: "same-origin" })
        .then((r) => r.json())
        .then((data) => {
            let html = "";

            if (!data.activities || data.activities.length === 0) {
                html = "<p class='text-muted mb-0'>Chưa có hoạt động nào 😴</p>";
            } else {
                data.activities.forEach((a) => {
                    const postId = Number(a.post_id || 0);
                    const href = postId > 0 ? `${BASE}/post/${postId}` : "#";
                    const typeLabel = a.type === "like" ? "Đã thích bài viết" : "Đã bình luận bài viết";
                    const content = escHtml(String(a.content || ""));
                    const when = escHtml(String(a.created_at || ""));

                    html += `
                        <a href="${href}"
                           class="card p-3 mb-2 text-start text-decoration-none text-body ${postId > 0 ? "js-open-post-modal" : ""}"
                           ${postId > 0 ? `data-post-id="${postId}"` : ""}
                           style="border-color:#dbe7f3;">
                            <small class="text-secondary d-block mb-1">${typeLabel}</small>
                            <p class="mb-1">${content || "(Bài viết không có nội dung)"}</p>
                            <small class="text-muted">${when}</small>
                        </a>
                    `;
                });
            }

            el.innerHTML = html;
        })
        .catch(() => {
            el.innerHTML = "<p class='text-danger small mb-0'>Không tải được hoạt động.</p>";
        });
}

// ===== PROFILE: follow / unfollow (trang người khác) =====
(function () {
    const btn = document.getElementById("profileFollowBtn");
    if (!btn) return;

    const targetId = parseInt(btn.getAttribute("data-user-id") || "0", 10);
    const username = btn.getAttribute("data-username") || "";
    const modalEl = document.getElementById("unfollowConfirmModal");
    const modalText = document.getElementById("unfollowConfirmText");
    const confirmBtn = document.getElementById("unfollowConfirmBtn");

    function setFollowingUi(following) {
        btn.setAttribute("data-following", following ? "true" : "false");
        if (following) {
            btn.textContent = "Đã theo dõi";
            btn.classList.remove("btn-brand-follow");
            btn.classList.add("btn-brand-follow-outline");
        } else {
            btn.textContent = "Theo dõi";
            btn.classList.remove("btn-brand-follow-outline");
            btn.classList.add("btn-brand-follow");
        }
    }

    setFollowingUi(btn.getAttribute("data-following") === "true");

    btn.addEventListener("click", async function () {
        const isFollowing = btn.getAttribute("data-following") === "true";
        if (!isFollowing) {
            btn.disabled = true;
            const fd = new FormData();
            fd.append("target_id", String(targetId));
            try {
                const r = await fetch(BASE + "/user-api/follow?action=follow", {
                    method: "POST",
                    body: fd,
                    credentials: "same-origin",
                });
                const data = await r.json();
                if (data && data.success) {
                    setFollowingUi(true);
                }
            } catch (e) {}
            btn.disabled = false;
            return;
        }

        if (modalText) {
            modalText.textContent = "Bạn có chắc muốn hủy theo dõi @" + username + "?";
        }
        if (modalEl && typeof bootstrap !== "undefined") {
            bootstrap.Modal.getOrCreateInstance(modalEl).show();
        }
    });

    confirmBtn?.addEventListener("click", async function () {
        confirmBtn.disabled = true;
        const fd = new FormData();
        fd.append("target_id", String(targetId));
        try {
            const r = await fetch(BASE + "/user-api/follow?action=unfollow", {
                method: "POST",
                body: fd,
                credentials: "same-origin",
            });
            const data = await r.json();
            if (data && data.success) {
                setFollowingUi(false);
                if (modalEl && typeof bootstrap !== "undefined") {
                    bootstrap.Modal.getInstance(modalEl)?.hide();
                }
            }
        } catch (e) {}
        confirmBtn.disabled = false;
    });
})();
