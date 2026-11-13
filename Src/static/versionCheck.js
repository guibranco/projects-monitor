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
  // Matches `from "x"`, `import "x"` and `import("x")`. The specifier is
  // filtered to JS files in code, keeping this pattern linear-time.
  var IMPORT_PATTERN = /\b(?:from|import)[\s(]*["']([^"'\n]+)["']/g;
  var JS_FILE_PATTERN = /\.m?js(?:\?|$)/;

  // Resolves `spec` against `base` with an anchor element (no URL API
  // needed) and returns the parts used below.
  function resolve(spec, base) {
    var target = spec;
    if (!/^(?:[a-z][a-z0-9+.-]*:|\/)/i.test(spec) && base) {
      target = base.slice(0, base.lastIndexOf("/") + 1) + spec;
    }
    var anchor = document.createElement("a");
    anchor.href = target;
    var origin = anchor.protocol + "//" + anchor.host;
    return {
      href: anchor.href,
      bare: origin + anchor.pathname,
      sameOrigin:
        origin === window.location.protocol + "//" + window.location.host,
    };
  }

  // Runs each task (a function taking a callback) and calls `done` once all
  // of them have called back.
  function runAll(tasks, done) {
    var pending = tasks.length;
    if (pending === 0) {
      done();
      return;
    }
    tasks.forEach(function (task) {
      task(function () {
        pending -= 1;
        if (pending === 0) {
          done();
        }
      });
    });
  }

  function collectPageAssets() {
    var assets = [];
    var selector =
      "script[src], link[rel~='stylesheet'][href], link[rel='modulepreload'][href]";
    var nodes = document.querySelectorAll(selector);
    Array.prototype.forEach.call(nodes, function (node) {
      var asset = resolve(node.src || node.href, null);
      if (asset.sameOrigin) {
        asset.module = node.type === "module";
        assets.push(asset);
      }
    });
    return assets;
  }

  function refetch(url) {
    // Only reached when fetch exists (checked in bustCacheAndReload).
    // eslint-disable-next-line compat/compat
    return window.fetch(url, { cache: "reload", credentials: "same-origin" });
  }

  // Re-fetches an asset (both its stamped and bare URL, since modules import
  // the bare one); for JS modules it also parses the static and dynamic
  // imports and recurses, so the whole import graph is refreshed.
  function refreshAsset(asset, seen, done) {
    if (seen.indexOf(asset.href) !== -1) {
      done();
      return;
    }
    seen.push(asset.href);

    var tasks = [];
    if (asset.bare !== asset.href && seen.indexOf(asset.bare) === -1) {
      seen.push(asset.bare);
      tasks.push(function (callback) {
        refetch(asset.bare).then(callback, callback);
      });
    }
    tasks.push(function (callback) {
      refetch(asset.href)
        .then(function (response) {
          if (!asset.module || !response.ok) {
            callback();
            return;
          }
          response.text().then(function (source) {
            refreshImports(source, asset.href, seen, callback);
          }, callback);
        })
        .catch(callback);
    });
    runAll(tasks, done);
  }

  function refreshImports(source, base, seen, done) {
    var children = [];
    var match = IMPORT_PATTERN.exec(source);
    while (match !== null) {
      if (JS_FILE_PATTERN.test(match[1])) {
        var child = resolve(match[1], base);
        if (child.sameOrigin) {
          child.module = true;
          children.push(child);
        }
      }
      match = IMPORT_PATTERN.exec(source);
    }
    runAll(
      children.map(function (child) {
        return function (callback) {
          refreshAsset(child, seen, callback);
        };
      }),
      done,
    );
  }

  function clearCacheStorage(done) {
    if (!window.caches || typeof window.caches.keys !== "function") {
      done();
      return;
    }
    window.caches
      .keys()
      .then(function (keys) {
        runAll(
          keys.map(function (key) {
            return function (callback) {
              window.caches.delete(key).then(callback, callback);
            };
          }),
          done,
        );
      })
      .catch(done);
  }

  function reloadWithBuster(version) {
    var search = window.location.search.replace(/^\?/, "");
    var params = search.split("&").filter(function (part) {
      return part !== "" && part.indexOf(BUST_PARAM + "=") !== 0;
    });
    params.push(
      BUST_PARAM + "=" + encodeURIComponent(version + "-" + Date.now()),
    );
    window.location.replace(
      window.location.pathname + "?" + params.join("&") + window.location.hash,
    );
  }

  function bustCacheAndReload(version) {
    var finished = false;
    function finish() {
      if (!finished) {
        finished = true;
        reloadWithBuster(version);
      }
    }

    if (typeof window.fetch !== "function") {
      finish();
      return;
    }

    setTimeout(finish, BUST_TIMEOUT_MS);

    var seen = [];
    var tasks = collectPageAssets().map(function (asset) {
      return function (callback) {
        refreshAsset(asset, seen, callback);
      };
    });
    tasks.push(clearCacheStorage);
    runAll(tasks, finish);
  }

  // Removes the one-off cache-buster parameter from the address bar after a
  // busted reload, so it doesn't end up in bookmarks or shared links.
  function cleanBusterParam() {
    var search = window.location.search.replace(/^\?/, "");
    var params = search.split("&").filter(function (part) {
      return part !== "" && part.indexOf(BUST_PARAM + "=") !== 0;
    });
    if (params.length === search.split("&").filter(Boolean).length) {
      return;
    }
    try {
      var query = params.length > 0 ? "?" + params.join("&") : "";
      window.history.replaceState(
        window.history.state,
        "",
        window.location.pathname + query + window.location.hash,
      );
    } catch (e) {
      // History API unavailable — the parameter is harmless.
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
