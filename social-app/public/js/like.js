
document.addEventListener("DOMContentLoaded", () => {
  document.addEventListener("click", async function (e) {
    const btn = e.target.closest(".ajax-post-like button");
    if (!btn) return;

    e.preventDefault();
    e.stopPropagation();

    const form = btn.closest("form");
    const postId = form.dataset.postId;
    const formData = new FormData(form);

    try {
      const res = await fetch(form.action, {
        method: "POST",
        body: formData,
        credentials: "same-origin"
      });

      let data = null;
      try {
        data = await res.json();
      } catch {
        return;
      }

      const icon = document.getElementById(`like-icon-${postId}`);
      const count = document.getElementById(`like-count-${postId}`);
      const likeBtn = document.getElementById(`like-btn-${postId}`);

      if (!data || !data.ok) {
        if (data && data.msg === "not login") {
          const loginUrl = form.action.replace(/\/api\/like\.php$/i, "/login");
          window.location.href = loginUrl;
        }
        return;
      }

      const liked = !!data.is_liked;
      const likeCount =
        typeof data.like_count === "number"
          ? data.like_count
          : parseInt(count?.innerText ?? "0", 10) || 0;

      if (icon) {
        icon.classList.toggle("bi-heart", !liked);
        icon.classList.toggle("bi-heart-fill", liked);
      }
      if (count) {
        count.textContent = String(likeCount);
        count.classList.toggle("text-danger", liked);
        count.classList.toggle("text-secondary", !liked);
      }
      if (likeBtn) {
        likeBtn.classList.toggle("text-danger", liked);
        likeBtn.classList.toggle("text-secondary", !liked);
      }
    } catch (err) {
      console.error("Like error:", err);
    }
  });
});
