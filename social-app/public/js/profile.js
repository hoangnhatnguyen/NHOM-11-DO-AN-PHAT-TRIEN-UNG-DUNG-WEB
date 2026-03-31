// ===== CONFIG =====
const BASE = window.location.origin;

// ===== ELEMENT =====
const avatarImg = document.getElementById("avatarImg");
const avatarInput = document.getElementById("avatarInput");
const avatarOverlay = document.getElementById("avatarOverlay");

let editMode = false;

// ===== EDIT MODE =====
document.getElementById("editBtn")?.addEventListener("click", () => {
    editMode = true;

    // hiện overlay đổi ảnh
    avatarOverlay?.classList.remove("d-none");

    // chuyển bio sang textarea
    let bioText = document.getElementById("bioText");

    bioText.innerHTML = `
        <textarea id="bioInput" class="form-control mb-2">${bioText.innerText}</textarea>
        <button class="btn btn-primary btn-sm" id="saveBio">Lưu</button>
    `;

    document.getElementById("saveBio").onclick = () => {
        let bio = document.getElementById("bioInput").value;

        fetch(BASE + "/user-api/update-profile", {
            method: "POST",
            body: new URLSearchParams({ bio })
        }).then(() => location.reload());
    };
});

// ===== AVATAR UPLOAD =====
if (avatarImg && avatarInput) {

    avatarImg.onclick = () => {
        if (!editMode) return;
        avatarInput.click();
    };

    avatarOverlay?.addEventListener("click", () => {
        if (!editMode) return;
        avatarInput.click();
    });

    avatarInput.onchange = () => {
        let form = new FormData();
        form.append("avatar", avatarInput.files[0]);

        fetch(BASE + "/user-api/upload-avatar", {
            method: "POST",
            body: form
        })
        .then(r => r.json())
        .then(data => {
            avatarImg.src = data.url + "?t=" + new Date().getTime();
        });
    };
}

// ===== LOAD POSTS (REAL DB) =====
function loadPosts() {
    fetch(BASE + "/user-api/posts?user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {

            let html = "";

            if (!data.posts || data.posts.length === 0) {
                html = "<p class='text-muted'>Chưa có bài viết nào</p>";
            } else {
                data.posts.forEach(p => {
                    html += `
                        <div class="card p-3 mb-3">
                            <p>${p.content ?? ''}</p>
                            ${p.media_url ? `<img src="${p.media_url}" class="img-fluid rounded">` : ''}
                        </div>
                    `;
                });
            }

            document.getElementById("tabContent").innerHTML = html;
        });
}

// ===== TAB SWITCH =====
document.querySelectorAll("[data-tab]").forEach(tab => {
    tab.onclick = () => {
        document.querySelectorAll("[data-tab]").forEach(t => t.classList.remove("active"));
        tab.classList.add("active");

        if (tab.dataset.tab === "posts") {
            loadPosts();
        } else {
            document.getElementById("tabContent").innerHTML = "<p class='text-muted'>Chưa có hoạt động</p>";
        }
    };
});

// ===== FOLLOWERS / FOLLOWING =====
document.getElementById("followersBtn")?.addEventListener("click", () => {
    fetch(BASE + "/user-api/follow?action=followers&user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {
            document.getElementById("modalTitle").innerText = "Followers";
            renderList(data.followers);
            new bootstrap.Modal(document.getElementById("followModal")).show();
        });
});

document.getElementById("followingBtn")?.addEventListener("click", () => {
    fetch(BASE + "/user-api/follow?action=following&user_id=" + USER_ID)
        .then(r => r.json())
        .then(data => {
            document.getElementById("modalTitle").innerText = "Following";
            renderList(data.following);
            new bootstrap.Modal(document.getElementById("followModal")).show();
        });
});

function renderList(list) {
    let html = "";

    if (!list || list.length === 0) {
        html = "<p class='text-muted'>Không có dữ liệu</p>";
    }

    list.forEach(u => {
        html += `
            <div class="d-flex align-items-center justify-content-between mb-2 p-2 border rounded">
                <div class="d-flex align-items-center gap-2">
                    <img src="${u.avatar_url || '/public/default-avatar.png'}"
                         width="40" height="40"
                         class="rounded-circle">
                    <span>${u.username}</span>
                </div>

                <button class="btn btn-sm btn-danger"
                        onclick="removeFollow(${u.id})">
                    Xóa
                </button>
            </div>
        `;
    });

    document.getElementById("followList").innerHTML = html;
}

function removeFollow(id) {
    fetch(BASE + "/user-api/unfollow", {
        method: "POST",
        body: new URLSearchParams({ target_id: id })
    }).then(() => location.reload());
}

// ===== INIT =====
loadPosts();
// ===== BADGE REMOVE =====
let currentPopup = null;

document.querySelectorAll(".badge-item").forEach(badge => {

    badge.addEventListener("click", (e) => {

        if (!editMode) return;

        e.stopPropagation();

        // remove popup cũ nếu có
        document.querySelectorAll(".badge-popup").forEach(p => p.remove());

        let popup = document.createElement("div");
        popup.className = "badge-popup position-absolute bg-white border rounded shadow-sm px-2 py-1";
        popup.style.top = "110%";
        popup.style.left = "50%";
        popup.style.transform = "translateX(-50%)";
        popup.style.zIndex = "999";

        popup.innerHTML = `
            <button class="btn btn-sm btn-danger">Xóa</button>
        `;

        badge.appendChild(popup);

        popup.querySelector("button").onclick = () => {
            let badgeId = badge.dataset.id;

            fetch(BASE + "/user-api/remove-badge", {
                method: "POST",
                body: new URLSearchParams({ badge_id: badgeId })
            })
            .then(() => {
                badge.remove();
            });
        };

        currentPopup = popup;
    });
});

// click ngoài → đóng popup
document.addEventListener("click", () => {
    document.querySelectorAll(".badge-popup").forEach(p => p.remove());
});