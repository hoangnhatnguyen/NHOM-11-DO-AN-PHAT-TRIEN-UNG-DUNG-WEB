function appBaseUrl() {
  const b = window.__APP_BASE__;
  return typeof b === "string" ? b.replace(/\/$/, "") : "";
}

function updateSidebarNotifBadge(unread) {
  var el = document.getElementById("sidebar-notif-unread-badge");
  if (!el) return;
  var n = parseInt(unread, 10);
  if (!n || n <= 0) {
    el.classList.add("d-none");
    el.textContent = "";
    return;
  }
  el.classList.remove("d-none");
  el.textContent = n > 99 ? "99+" : String(n);
}

function loadNoti() {
  const base = appBaseUrl();
  fetch(base + "/api/notification.php", {
    credentials: "same-origin",
  })
    .then(function (res) {
      return res.json();
    })
    .then(function (data) {
      if (!data || !data.ok) return;

      if (typeof data.unread !== "undefined") {
        updateSidebarNotifBadge(data.unread);
      }

      var notifications = data.notifications || [];
      var box = document.getElementById("noti-list");
      if (!box) return;

      if (notifications.length === 0) {
        box.innerHTML = "<div class='text-muted small p-2'>Không có thông báo</div>";
        return;
      }

      var html = "";
      notifications.forEach(function (n) {
        var href = base + (n.link && n.link.charAt(0) === "/" ? n.link : "/" + (n.link || ""));
        var msg = (n.message || "").replace(/</g, "&lt;");
        var av = (n.avatar || "").replace(/"/g, "&quot;");
        var ini = String(n.avatar_initial || "?").replace(/</g, "&lt;");
        var bg = String(n.avatar_bg || "#E6F4FF").replace(/"/g, "");
        var fg = String(n.avatar_fg || "#005B96").replace(/"/g, "");
        var avBlock = av
          ? "<img src=\"" +
            av +
            "\" alt=\"\" width=\"36\" height=\"36\" class=\"rounded-circle flex-shrink-0\" style=\"object-fit:cover\">"
          : "<div class=\"rounded-circle flex-shrink-0 d-inline-flex align-items-center justify-content-center fw-semibold\" style=\"width:36px;height:36px;background:" +
            bg +
            ";color:" +
            fg +
            "\">" +
            ini +
            "</div>";
        html +=
          "<a href=\"" +
          href +
          "\" class=\"list-group-item list-group-item-action py-2 px-3 d-flex gap-2 align-items-start text-decoration-none\">" +
          avBlock +
          "<div class=\"small text-body\">" +
          msg +
          "</div></a>";
      });
      box.innerHTML = html;
    })
    .catch(function () {});
}

function markNotificationReadThenNavigate(notificationId, href) {
  var base = appBaseUrl();
  var fd = new FormData();
  fd.append("id", String(notificationId));
  fetch(base + "/api/notification_mark_read.php", {
    method: "POST",
    body: fd,
    credentials: "same-origin",
  })
    .then(function (r) {
      return r.json();
    })
    .then(function (data) {
      if (data && typeof data.unread !== "undefined") {
        updateSidebarNotifBadge(data.unread);
      }
      if (href && href !== "#") {
        window.location.assign(href);
      }
    })
    .catch(function () {
      if (href && href !== "#") {
        window.location.assign(href);
      }
    });
}

document.addEventListener(
  "click",
  function (e) {
    var a = e.target.closest("a.noti-row");
    if (!a) return;
    var nid = parseInt(a.getAttribute("data-notification-id") || "0", 10);
    if (nid <= 0) return;
    if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;
    var href = a.getAttribute("href") || "";
    if (!href || href === "#") return;
    e.preventDefault();
    markNotificationReadThenNavigate(nid, href);
  },
  true
);

document.addEventListener("DOMContentLoaded", function () {
  loadNoti();
  setInterval(loadNoti, 8000);
});
