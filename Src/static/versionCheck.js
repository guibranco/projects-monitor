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
      reloadBtn.disabled = true;
      dismissBtn.disabled = true;
      reloadBtn.textContent = "Updating…";
      bustCacheAndReload(newVersion);
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

  // ---------------------------------------------------------------------
  // Cache busting
  //
  // A plain location.reload() is not enough after a deploy: index.php only
  // versions the entry points (main.js?<mtime>), but the ES modules they
  // import (./api.js, ./ui.js, ./constants.js, ...) are requested by bare URL
  // and are served from the browser's HTTP cache. The reload then runs the
  // new main.js against stale modules (or the old main.js altogether), which
  // breaks any feature that depends on new JS.
  //
  // So before reloading we:
  //   1. wipe the Cache Storage API (service-worker caches), if any;
  //   2. walk every same-origin script/stylesheet on the page plus the full
  //      ES module import graph, and re-fetch each with cache: "reload",
  //      which bypasses the HTTP cache AND overwrites the stored entry;
  //   3. navigate to the page with a one-off query parameter, so the HTML
  //      (and the mtime-stamped URLs inside it) is fetched fresh too.
  // Every step is best-effort and bounded by a timeout: whatever happens,
  // the page is reloaded.
  // ---------------------------------------------------------------------

  var BUST_PARAM = "_pmv";
  var BUST_TIMEOUT_MS = 8000;
  var IMPORT_PATTERN =
    /(?:\bimport\s*(?:[\w*{}\s,$]+\s*from\s*)?|\bexport\s*[\w*{}\s,$]*\s*from\s*|\bimport\s*\(\s*)["']([^"']+\.m?js(?:\?[^"']*)?)["']/g;

  function isSameOrigin(url) {
    try {
      return new URL(url, window.location.href).origin === window.location.origin;
    } catch (e) {
      return false;
    }
  }

  function stripQuery(url) {
    var u = new URL(url, window.location.href);
    u.search = "";
    u.hash = "";
    return u.href;
  }

  function collectPageAssets() {
    var urls = [];
    var nodes = document.querySelectorAll("script[src], link[rel~='stylesheet'][href], link[rel='modulepreload'][href]");
    for (var i = 0; i < nodes.length; i++) {
      var url = nodes[i].src || nodes[i].href;
      if (url && isSameOrigin(url)) {
        urls.push({ url: url, module: nodes[i].type === "module" });
      }
    }
    return urls;
  }

  function refetch(url) {
    return fetch(url, { cache: "reload", credentials: "same-origin" });
  }

  // Re-fetches an asset; for JS modules also parses its static/dynamic
  // imports and recurses, so the whole import graph is refreshed.
  function refreshAsset(url, isModule, seen) {
    if (seen[url]) {
      return Promise.resolve();
    }
    seen[url] = true;

    // Refresh the bare URL as well: that's the one other modules import.
    var bare = stripQuery(url);
    var extra = bare !== url && !seen[bare] ? (seen[bare] = true, refetch(bare).catch(function () {})) : null;

    return Promise.all([
      extra,
      refetch(url).then(function (response) {
        if (!isModule || !response.ok) {
          return null;
        }
        return response.text().then(function (source) {
          var children = [];
          var match;
          IMPORT_PATTERN.lastIndex = 0;
          while ((match = IMPORT_PATTERN.exec(source)) !== null) {
            var child = new URL(match[1], url).href;
            if (isSameOrigin(child)) {
              children.push(refreshAsset(child, true, seen));
            }
          }
          return Promise.all(children);
        });
      }).catch(function () {
        // Network hiccup on one asset — keep going with the rest.
      })
    ]);
  }

  function clearCacheStorage() {
    if (!window.caches || typeof window.caches.keys !== "function") {
      return Promise.resolve();
    }
    return window.caches.keys().then(function (keys) {
      return Promise.all(keys.map(function (key) {
        return window.caches.delete(key);
      }));
    }).catch(function () {});
  }

  function reloadWithBuster(version) {
    var url = new URL(window.location.href);
    url.searchParams.set(BUST_PARAM, version + "-" + Date.now());
    window.location.replace(url.href);
  }

  function bustCacheAndReload(version) {
    var done = false;
    function finish() {
      if (!done) {
        done = true;
        reloadWithBuster(version);
      }
    }

    if (typeof window.fetch !== "function" || typeof window.URL !== "function" || typeof Promise === "undefined") {
      finish();
      return;
    }

    setTimeout(finish, BUST_TIMEOUT_MS);

    var seen = {};
    var assets = collectPageAssets();
    Promise.all([
      clearCacheStorage(),
      Promise.all(assets.map(function (asset) {
        return refreshAsset(asset.url, asset.module, seen);
      }))
    ]).then(finish, finish);
  }

  // Removes the one-off cache-buster parameter from the address bar after a
  // busted reload, so it doesn't end up in bookmarks or shared links.
  function cleanBusterParam() {
    try {
      var url = new URL(window.location.href);
      if (url.searchParams.has(BUST_PARAM)) {
        url.searchParams.delete(BUST_PARAM);
        window.history.replaceState(window.history.state, "", url.pathname + url.search + url.hash);
      }
    } catch (e) {
      // URL / history API unavailable — the parameter is harmless.
    }
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

  cleanBusterParam();
  fetchVersion(init);
})();
