
const BASE =
    typeof window.__APP_BASE__ === "string"
        ? window.__APP_BASE__.replace(/\/$/, "")
        : "";

function mediaViewUrl(keyOrUrl) {
    const s = String(keyOrUrl || "").trim();
    if (!s) return "";
    if (/^https?:\/\//i.test(s)) return s;
    if (!BASE) return s;
    return BASE + "/media/view?key=" + encodeURIComponent(s);
}

// ===== ELEMENT =====
const avatarInput = document.getElementById("avatarInput");
const avatarContainer = document.getElementById("profileAvatarContainer");

let isEditing = false;

// ===== EDIT MODE FULL =====
const editBtn = document.getElementById("editBtn");

editBtn?.addEventListener("click", () => {
    isEditing = true;

    let bioText = document.getElementById("bioText");
    let originalBio = bioText.innerText;

    bioText.innerHTML = `
        <textarea id="bioInput" class="form-control mb-2">${originalBio}</textarea>
        <div class="d-flex gap-2 justify-content-center">
            <button class="btn btn-success btn-sm" id="saveBtn">Lưu</button>
            <button class="btn btn-secondary btn-sm" id="cancelBtn">Hủy</button>
        </div>
    `;

    document.getElementById("addBadgeBtn")?.classList.remove("d-none");
    editBtn.style.display = "none";

    document.getElementById("saveBtn").onclick = () => {
        let bio = document.getElementById("bioInput").value;

        fetch(BASE + "/user-api/update-profile", {
            method: "POST",
            credentials: "same-origin",
            body: new URLSearchParams({ bio }),
        }).then(() => location.reload());
    };

    document.getElementById("cancelBtn").onclick = () => {
        location.reload();
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
            const rawAv = u.avatar_url ? String(u.avatar_url) : "";
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
document.querySelectorAll(".badge-item").forEach((badge) => {
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

        popup.querySelector("button").onclick = () => {
            fetch(BASE + "/user-api/remove-badge", {
                method: "POST",
                credentials: "same-origin",
                body: new URLSearchParams({ badge_id: badge.dataset.id }),
            }).then(() => location.reload());
        };
    });
});

// ===== ADD BADGE =====
const addBtn = document.getElementById("addBadgeBtn");
const popup = document.getElementById("badgePopup");
const searchInput = document.getElementById("badgeSearch");
const resultBox = document.getElementById("badgeResult");

addBtn?.addEventListener("click", () => {
    popup.classList.remove("d-none");
    searchInput.focus();
});

searchInput?.addEventListener("input", () => {
    let q = searchInput.value.trim();

    fetch(BASE + "/user-api/search-badge?q=" + encodeURIComponent(q), { credentials: "same-origin" })
        .then((r) => r.json())
        .then((res) => {
            let list = res.data || [];
            let html = "";

            let hasExact = list.some((b) => b.name.toLowerCase() === q.toLowerCase());

            if (q !== "" && !hasExact) {
                html += `
                    <div class="p-2 text-primary fw-bold"
                         style="cursor:pointer"
                         onclick="addBadge('${q}')">
                        + Tạo mới "${q}"
                    </div>
                `;
            }

            list.forEach((b) => {
                html += `
                    <div class="p-2 border-bottom badge-select"
                         style="cursor:pointer"
                         data-name="${b.name}">
                        ${b.name}
                    </div>
                `;
            });

            resultBox.innerHTML = html;

            document.querySelectorAll(".badge-select").forEach((el) => {
                el.onclick = () => addBadge(el.dataset.name);
            });
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
        .then(() => location.reload());
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
                    html += `
                        <div class="card p-2 mb-2 text-start">
                            <small class="text-muted">${a.type}</small>
                            <p class="mb-0">${a.content}</p>
                        </div>
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
