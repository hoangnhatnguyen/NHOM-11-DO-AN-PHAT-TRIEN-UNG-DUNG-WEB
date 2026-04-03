
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
    "/notifications",
    "/messages",
    "/search",
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
        var nid = parseInt(n.id || "0", 10);
        var nidAttr = nid > 0 ? ' data-notification-id="' + String(nid) + '"' : "";
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
          "<div class=\"list-group-item list-group-item-action py-2 px-3 d-flex gap-2 align-items-start text-decoration-none noti-row\" tabindex=\"0\" role=\"link\" data-href=\"" +
          href.replace(/"/g, "&quot;") +
          "\"" +
          nidAttr +
          ">" +
          avBlock +
          "<div class=\"small text-body\">" +
          msg +
          "</div></div>";
      });
      box.innerHTML = html;
    })
    .catch(function () {});
}

function markNotificationReadThenNavigate(notificationId, href) {
  var base = appBaseUrl();
  var fd = new FormData();
  fd.append("id", String(notificationId));
  var url = base + "/user-api/notification-mark-read";
  fetch(url, {
    method: "POST",
    body: fd,
    credentials: "same-origin",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
  })
    .then(function (r) {
      return r.text().then(function (text) {
        var data = null;
        try {
          data = JSON.parse(text);
        } catch (e) {
          data = null;
        }
        return { okHttp: r.ok, data: data };
      });
    })
    .then(function (pack) {
      var data = pack.data;
      if (data && data.ok && typeof data.unread !== "undefined") {
        updateSidebarNotifBadge(data.unread);
        document
          .querySelectorAll('.noti-row[data-notification-id="' + String(notificationId) + '"] .noti-dot')
          .forEach(function (el) {
            el.remove();
          });
      }
      if (href && href !== "#") {
        navigateWithModalIfPossible(href);
      }
    })
    .catch(function () {
      if (href && href !== "#") {
        navigateWithModalIfPossible(href);
      }
    });
}

function extractPostInfoFromHref(href) {
  if (!href || href === "#") return null;
  try {
    var u = new URL(href, window.location.origin);
    var m = u.pathname.match(/\/post\/(\d+)\/?$/);
    if (!m) return null;
    return {
      postId: m[1],
      hash: u.hash || "",
    };
  } catch (e) {
    return null;
  }
}

function navigateWithModalIfPossible(href) {
  var info = extractPostInfoFromHref(href);
  if (info && typeof window.openPostDetail === "function") {
    window.openPostDetail(info.postId);

    if (info.hash && info.hash.length > 1) {
      setTimeout(function () {
        var target = document.querySelector('#postDetailContent ' + info.hash);
        if (target && typeof target.scrollIntoView === "function") {
          target.scrollIntoView({ behavior: "smooth", block: "center" });
        }
      }, 350);
    }
    return;
  }

  window.location.assign(href);
}

function notiRowNavigate(row, e) {
  var nid = parseInt(row.getAttribute("data-notification-id") || "0", 10);
  if (nid <= 0) return;
  if (e.button !== 0 || e.ctrlKey || e.metaKey || e.shiftKey) return;

  var rowHref = row.getAttribute("data-href") || row.getAttribute("href") || "";
  var innerLink = e.target.closest("a[href]");
  var navigateHref = rowHref;

  if (innerLink && row.contains(innerLink)) {
    var ih = (innerLink.getAttribute("href") || "").trim();
    if (ih && ih !== "#" && ih.toLowerCase().indexOf("javascript:") !== 0) {
      navigateHref = ih;
    }
    e.preventDefault();
  } else {
    if (!rowHref || rowHref === "#") return;
    e.preventDefault();
  }

  if (!navigateHref || navigateHref === "#") return;

  markNotificationReadThenNavigate(nid, navigateHref);
}

document.addEventListener(
  "click",
  function (e) {
    var row = e.target.closest(".noti-row");
    if (!row) return;
    notiRowNavigate(row, e);
  },
  true
);

document.addEventListener("keydown", function (e) {
  if (e.key !== "Enter") return;
  var row = e.target.closest(".noti-row");
  if (!row || document.activeElement !== row) return;
  e.preventDefault();
  var nid = parseInt(row.getAttribute("data-notification-id") || "0", 10);
  var href = row.getAttribute("data-href") || row.getAttribute("href") || "";
  if (nid > 0 && href && href !== "#") {
    markNotificationReadThenNavigate(nid, href);
  } else if (href && href !== "#") {
    navigateWithModalIfPossible(href);
  }
});

document.addEventListener("DOMContentLoaded", function () {
  loadNoti();
  setInterval(loadNoti, 8000);
});
