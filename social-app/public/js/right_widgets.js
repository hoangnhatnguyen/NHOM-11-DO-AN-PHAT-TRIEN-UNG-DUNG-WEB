document.addEventListener("DOMContentLoaded", () => {
  loadTrending();
  loadSuggestUsers();

});

// 🔥 BẮT ENTER GLOBAL (KHÔNG PHỤ THUỘC ID)
document.addEventListener("keydown", function (e) {

  const input = e.target;

  // chỉ bắt input trong search-box
  if (input.tagName !== "INPUT") return;
  if (!input.closest(".search-box")) return;

  if (e.key === "Enter") {
    e.preventDefault();

    const keyword = input.value.trim();
    if (!keyword) return;

    window.location.href = `/search?q=${encodeURIComponent(keyword)}&tab=top`;
  }
});

// ======================
// 🔥 TRENDING
// ======================
function loadTrending() {
  fetch("/api/search.php?type=trending_full")
    .then(res => res.json())
    .then(res => {
      const box = document.getElementById("right-trending");
      if (!box) return;

      const data = res.data || [];

      box.innerHTML = data.map((t, i) => `
        <div class="trend" onclick="goSearch('#${t.name}')">
          <small>#${i + 1} Trending</small><br>
          <b>#${t.name}</b>
        </div>
      `).join("");
    })
    .catch(err => console.error("Trending error:", err));
}

// ======================
// 👥 SUGGEST USERS
// ======================
function loadSuggestUsers() {
  fetch("/api/search.php?type=suggest_users")
    .then(res => res.json())
    .then(res => {

      const box = document.getElementById("suggestBox");
      if (!box) return;

      const data = res.data || [];

      if (!Array.isArray(data)) {
        console.error("❌ data không phải array:", data);
        return;
      }

      box.innerHTML = data.map(u => `
        <li class="d-flex justify-content-between align-items-center mb-2">
          <span>@${u.username}</span>
          <button class="btn btn-sm rounded-pill btn-follow">Theo dõi</button>
        </li>
      `).join("");
    });
}

function goSearch(q) {
  if (!q) return;

  window.location.href = `/search?q=${encodeURIComponent(q)}&tab=top`;
}