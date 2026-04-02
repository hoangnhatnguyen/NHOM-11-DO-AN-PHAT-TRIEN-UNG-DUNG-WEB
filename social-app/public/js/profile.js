// ===== CONFIG =====
const BASE = window.location.origin;

// ===== ELEMENT =====
const avatarImg = document.getElementById("avatarImg");
const avatarInput = document.getElementById("avatarInput");
const avatarOverlay = document.getElementById("avatarOverlay");

let isEditing = false;

// ===== EDIT MODE FULL =====
const editBtn = document.getElementById("editBtn");

editBtn?.addEventListener("click", () => {
    isEditing = true;
    avatarOverlay?.classList.remove("d-none");

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
            body: new URLSearchParams({ bio })
        }).then(() => location.reload());
    };

    document.getElementById("cancelBtn").onclick = () => {
        location.reload();
    };
});

// ===== AVATAR =====
avatarImg?.addEventListener("click", () => {
    if (isEditing) avatarInput.click();
});

avatarOverlay?.addEventListener("click", () => {
    if (isEditing) avatarInput.click();
});

avatarInput?.addEventListener("change", () => {
    let form = new FormData();
    form.append("avatar", avatarInput.files[0]);

    fetch(BASE + "/user-api/upload-avatar", {
        method: "POST",
        body: form
    })
        .then(r => r.json())
        .then(data => {
            if (data.error) {
                console.error('Upload error:', data.error);
                alert('Upload failed: ' + data.error);
            } else if (data.success || data.url) {
                // Reload page để render avatar mới
                location.reload();
            } else {
                console.error('Unexpected response:', data);
                alert('Upload failed');
            }
        })
        .catch(err => {
            console.error('Avatar upload error:', err);
            alert('Upload error: ' + err.message);
        });
});

// ===== POSTS =====
function loadPosts() {
    fetch(BASE + "/user-api/posts?user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {
            let html = "";

            if (!data.posts || data.posts.length === 0) {
                html = "<p class='text-muted'>Chưa có bài viết nào</p>";
            } else {
                data.posts.forEach(p => {
                    let mediaHtml = "";
                    if (p.media && Array.isArray(p.media)) {
                        p.media.forEach(m => {
                            let displayUrl = m.display_url || '';
                            if (displayUrl) {
                                mediaHtml += `<img src="${displayUrl}" class="img-fluid rounded mb-2" alt="">`;
                            }
                        });
                    }
                    
                    html += `
                        <div class="card p-3 mb-3">
                            <p>${p.content ?? ''}</p>
                            ${mediaHtml}
                        </div>
                    `;
                });
            }

            document.getElementById("tabContent").innerHTML = html;
        });
}

// ===== FOLLOW =====
document.getElementById("followersBtn")?.addEventListener("click", () => {
    fetch(BASE + "/user-api/followers?user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {
            renderList(data.followers, "Chưa có follower nào 😢");
            new bootstrap.Modal(document.getElementById("followModal")).show();
        });
});

document.getElementById("followingBtn")?.addEventListener("click", () => {
    fetch(BASE + "/user-api/following?user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {
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

function renderList(list, emptyMsg) {
    let html = "";

    if (!list || list.length === 0) {
        html = `<p class="text-center text-muted">${emptyMsg}</p>`;
    } else {
        list.forEach(u => {
            const uname = String(u.username || "");
            const href = BASE + "/profile?u=" + encodeURIComponent(uname);
            const av = u.avatar_url ? String(u.avatar_url) : "/public/default-avatar.png";
            html += `
                <a href="${escHtml(href)}" class="d-flex align-items-center gap-2 mb-2 text-decoration-none text-body">
                    <img src="${escHtml(av)}" width="40" height="40" class="rounded-circle" alt="">
                    <span>${escHtml(uname)}</span>
                </a>
            `;
        });
    }

    document.getElementById("followList").innerHTML = html;
}

// ===== BADGE REMOVE =====
document.querySelectorAll(".badge-item").forEach(badge => {
    badge.addEventListener("click", (e) => {
        if (!isEditing) return;

        e.stopPropagation();

        document.querySelectorAll(".badge-popup").forEach(p => p.remove());

        let popup = document.createElement("div");
        popup.className = "badge-popup position-absolute bg-white border rounded px-2 py-1";

        popup.innerHTML = `
            <button class="btn btn-sm btn-danger">Xóa</button>
        `;

        badge.appendChild(popup);

        popup.querySelector("button").onclick = () => {
            fetch(BASE + "/user-api/remove-badge", {
                method: "POST",
                body: new URLSearchParams({ badge_id: badge.dataset.id })
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

    fetch(BASE + "/user-api/search-badge?q=" + encodeURIComponent(q))
        .then(r => r.json())
        .then(res => {
            let list = res.data || [];
            let html = "";

            // check exact match
            let hasExact = list.some(
                b => b.name.toLowerCase() === q.toLowerCase()
            );

            // nếu KHÔNG có exact → cho tạo mới
            if (q !== "" && !hasExact) {
                html += `
                    <div class="p-2 text-primary fw-bold"
                         style="cursor:pointer"
                         onclick="addBadge('${q}')">
                        + Tạo mới "${q}"
                    </div>
                `;
            }

            // list badge có sẵn
            list.forEach(b => {
                html += `
                    <div class="p-2 border-bottom badge-select"
                         style="cursor:pointer"
                         data-name="${b.name}">
                        ${b.name}
                    </div>
                `;
            });

            resultBox.innerHTML = html;

            document.querySelectorAll(".badge-select").forEach(el => {
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

// ADD
function addBadge(name) {
    if (!name) return;

    fetch(BASE + "/user-api/add-badge", {
        method: "POST",
        body: new URLSearchParams({ name })
    })
        .then(r => r.json())
        .then(() => location.reload());
}

// CLICK OUTSIDE CLOSE
document.addEventListener("click", (e) => {
    if (!popup) return;

    if (!popup.contains(e.target) && e.target !== addBtn) {
        popup.classList.add("d-none");
    }
});
document.querySelectorAll("[data-tab]").forEach(tab => {
    tab.addEventListener("click", () => {

        document.querySelectorAll("[data-tab]").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");

        if (tab.dataset.tab === "posts") {
            loadPosts();
        } else if (tab.dataset.tab === "activity") {
            loadActivity();
        }
    });
});
function loadActivity() {
    fetch(BASE + "/user-api/activity?user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {

            let html = "";

            if (!data.activities || data.activities.length === 0) {
                html = "<p class='text-muted'>Chưa có hoạt động nào 😴</p>";
            } else {
                data.activities.forEach(a => {
                    html += `
                        <div class="card p-2 mb-2 text-start">
                            <small class="text-muted">${a.type}</small>
                            <p class="mb-0">${a.content}</p>
                        </div>
                    `;
                });
            }

            document.getElementById("tabContent").innerHTML = html;
        });
}
// INIT
loadPosts();

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
            modalText.textContent =
                "Bạn có chắc muốn hủy theo dõi @" + username + "?";
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