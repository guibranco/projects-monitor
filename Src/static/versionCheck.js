// versionCheck.js — standalone, dependency-free version watchdog.
//
// Deliberately NOT an ES module and NOT wired into main.js/DataLoader: this
// dashboard keeps a tab open for a long time and polls the API in the
// background, so a deploy that lands while a tab is open leaves that tab
// running stale JS against a newer backend, which is where the "random JS
// errors after a deploy" reports have come from. This script has to keep
// working even if main.js itself is the thing broken by that deploy, so it
// avoids every other file (jQuery, Bootstrap, constants.js, main.js) and
// builds its own minimal UI.
(function () {
  "use strict";

  var VERSION_ENDPOINT = "api/v1/version";
  var STORAGE_KEY = "pm-app-version";
  var POLL_INTERVAL_MS = 60000;
  var BANNER_ID = "version-update-banner";

  var knownVersion = null;
  var bannerShown = false;

  function fetchVersion(onSuccess) {
    var xhr = new XMLHttpRequest();
    xhr.open("GET", VERSION_ENDPOINT, true);
    xhr.onreadystatechange = function () {
      if (xhr.readyState !== 4 || xhr.status !== 200) {
        return;
      }
      try {
        var data = JSON.parse(xhr.responseText);
        if (data && typeof data.version === "string" && data.version !== "") {
          onSuccess(data.version);
        }
      } catch (e) {
        // Malformed response — try again on the next poll.
      }
    };
    xhr.send();
  }

  function persistVersion(version) {
    try {
      localStorage.setItem(STORAGE_KEY, version);
    } catch (e) {
      // Storage unavailable (private mode, quota, disabled) — the in-memory
      // baseline below still lets this tab detect future deploys.
    }
  }

  function injectStyles() {
    var style = document.createElement("style");
    style.textContent =
      "#" + BANNER_ID + "{position:fixed;top:0;left:0;right:0;z-index:2000;" +
      "display:flex;flex-wrap:wrap;align-items:center;justify-content:center;gap:12px;" +
      "padding:10px 16px;background:#ffc107;color:#212529;" +
      "font:14px/1.4 -apple-system,BlinkMacSystemFont,'Segoe UI',Roboto,Arial,sans-serif;" +
      "box-shadow:0 2px 8px rgba(0,0,0,.25);}" +
      "#" + BANNER_ID + " button{border:0;padding:6px 14px;border-radius:4px;" +
      "cursor:pointer;font-weight:600;font-size:13px;}" +
      "#" + BANNER_ID + " .version-reload{background:#212529;color:#fff;}" +
      "#" + BANNER_ID + " .version-reload:hover{background:#000;}" +
      "#" + BANNER_ID + " .version-dismiss{background:transparent;color:#212529;" +
      "text-decoration:underline;font-weight:400;}";
    document.head.appendChild(style);
  }

  function showReloadBanner(newVersion) {
    if (bannerShown || !document.body) {
      return;
    }
    bannerShown = true;

    injectStyles();

    var banner = document.createElement("div");
    banner.id = BANNER_ID;
    banner.setAttribute("role", "alert");

    var text = document.createElement("span");
    text.textContent = "A new version (" + newVersion + ") of Projects Monitor is available.";

    var reloadBtn = document.createElement("button");
    reloadBtn.type = "button";
    reloadBtn.className = "version-reload";
    reloadBtn.textContent = "Reload now";
    reloadBtn.addEventListener("click", function () {
      window.location.reload();
    });

    var dismissBtn = document.createElement("button");
    dismissBtn.type = "button";
    dismissBtn.className = "version-dismiss";
    dismissBtn.textContent = "Dismiss";
    dismissBtn.addEventListener("click", function () {
      banner.remove();
      bannerShown = false;
    });

    banner.appendChild(text);
    banner.appendChild(reloadBtn);
    banner.appendChild(dismissBtn);
    document.body.appendChild(banner);
  }

  function checkForUpdate(serverVersion) {
    if (knownVersion !== null && serverVersion !== knownVersion) {
      showReloadBanner(serverVersion);
      knownVersion = serverVersion;
      persistVersion(serverVersion);
    }
  }

  function init(serverVersion) {
    knownVersion = serverVersion;
    persistVersion(serverVersion);
    setInterval(function () {
      fetchVersion(checkForUpdate);
    }, POLL_INTERVAL_MS);
  }

  fetchVersion(init);
})();
