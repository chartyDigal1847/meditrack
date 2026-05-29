/**
 * module-bridge.js - centralized module-side SSO bridge.
 *
 * Required delivery:
 *   <script src="https://deoris.test/module-bridge.js"></script>
 *
 * Security model:
 * - The portal at https://deoris.test is the only identity provider.
 * - Modules are identity consumers. They receive user identity, then boot UI.
 * - Tokens are single-use handoff artifacts and stay in runtime memory only.
 * - No localStorage, sessionStorage, cookies, shared sessions, or redirects are
 *   used by this bridge. Persistent browser storage would outlive the iframe
 *   lifecycle and increases replay risk after XSS or device sharing.
 * - postMessage is accepted only from the portal origin and only for this
 *   requestId, so sibling iframes or arbitrary sites cannot inject identity.
 * - beforeunload/pagehide clear memory and revoke any unexchanged token.
 */
(function () {
  "use strict";

  if (window.__DEORIS_MODULE_BRIDGE_RUNNING__) return;
  window.__DEORIS_MODULE_BRIDGE_RUNNING__ = true;

  var PORTAL_ORIGIN = (window.MEDITRACK && window.MEDITRACK.portalOrigin) || window.PORTAL_ORIGIN || "https://deoris.test";
  var SSO_TIMEOUT_MS = Number(window.SSO_TIMEOUT_MS || 8000);
  var requestId = String(Date.now()) + "-" + Math.random().toString(36).slice(2);
  var resolved = false;
  var timeoutId = null;

  window.PORTAL_ORIGIN = PORTAL_ORIGIN;
  window.SSO_TOKEN = null;
  window.PORTAL_USER = null;

  function isEmbedded() {
    try {
      return window.self !== window.top;
    } catch (error) {
      return true;
    }
  }

  function emit(name, detail) {
    window.dispatchEvent(new CustomEvent(name, { detail: detail }));
  }

  function cleanupMemory() {
    window.SSO_TOKEN = null;
    window.PORTAL_USER = null;
  }

  function finishError(error, code) {
    if (resolved) return;
    resolved = true;
    window.SSO_TOKEN = null;
    if (timeoutId) clearTimeout(timeoutId);

    emit("module:error", {
      success: false,
      error: error,
      code: code || "sso_failed",
      embedded: isEmbedded(),
      portalOrigin: PORTAL_ORIGIN,
    });
  }

  function finishReady(user) {
    if (resolved) return;
    resolved = true;
    window.SSO_TOKEN = null;
    window.PORTAL_USER = user;
    if (timeoutId) clearTimeout(timeoutId);

    emit("module:ready", {
      success: true,
      user: user,
      embedded: isEmbedded(),
      portalOrigin: PORTAL_ORIGIN,
    });
  }

  function revokePendingToken() {
    var token = window.SSO_TOKEN;
    window.SSO_TOKEN = null;

    if (!token || resolved) return;

    fetch(PORTAL_ORIGIN + "/api/sso/revoke", {
      method: "POST",
      credentials: "include",
      keepalive: true,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
      },
      body: JSON.stringify({ token: token }),
    }).catch(function () {
      // Best-effort cleanup during iframe navigation or portal tab close.
    });
  }

  function exchangeToken(token) {
    return fetch(PORTAL_ORIGIN + "/api/sso/exchange", {
      method: "POST",
      credentials: "include",
      headers: {
        Authorization: "Bearer " + token,
        "Content-Type": "application/json",
        Accept: "application/json",
      },
      body: JSON.stringify({ token: token }),
    }).then(function (response) {
      return response.json()
        .catch(function () { return {}; })
        .then(function (body) {
          if (!response.ok || body.success === false) {
            throw new Error(body.error || ("http_" + response.status));
          }

          if (!body.user || !body.user.id) {
            throw new Error("missing_user");
          }

          return body.user;
        });
    });
  }

  window.addEventListener("message", function (event) {
    if (event.origin !== PORTAL_ORIGIN) {
      console.warn("[module-bridge] Ignored message from untrusted origin:", event.origin);
      return;
    }

    if (!event.data || event.data.requestId !== requestId) return;

    if (event.data.type === "SSO_ERROR") {
      finishError(event.data.error || "sso_failed", "portal_sso_error");
      return;
    }

    if (event.data.type !== "SSO_TOKEN") return;

    if (typeof event.data.token !== "string" || event.data.token.length === 0) {
      finishError("missing_sso_token", "missing_sso_token");
      return;
    }

    window.SSO_TOKEN = event.data.token;

    exchangeToken(window.SSO_TOKEN)
      .then(finishReady)
      .catch(function (error) {
        revokePendingToken();
        finishError(error.message || "exchange_failed", "exchange_failed");
      });
  });

  window.addEventListener("pagehide", function () {
    revokePendingToken();
    cleanupMemory();
  });

  window.addEventListener("beforeunload", function () {
    revokePendingToken();
    cleanupMemory();
  });

  if (!isEmbedded()) {
    finishError("open_from_deoris_portal", "not_embedded");
    return;
  }

  timeoutId = window.setTimeout(function () {
    finishError("sso_timeout", "sso_timeout");
  }, SSO_TIMEOUT_MS);

  window.parent.postMessage({ type: "REQUEST_SSO", requestId: requestId }, PORTAL_ORIGIN);
}());
