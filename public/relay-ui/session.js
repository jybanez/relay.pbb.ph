import { uiLoader } from "/relay-ui/helper-ui-bundle.js";

const DEFAULT_BOOTSTRAP = {
  app: {
    name: "PBB - Hub Relay Server",
    page: "relay",
  },
  auth: {
    authenticated: false,
    account: null,
  },
  security: {
    csrfToken: "",
    sessionLifetimeMinutes: 0,
    keepaliveThresholdSeconds: 120,
  },
  settings: {
    bootstrapUrl: "/api/bootstrap",
    csrfTokenUrl: "/api/csrf-token",
    sessionPingUrl: "/api/session/ping",
  },
};

const runtime = {
  bootstrap: null,
  activeRenewal: null,
  bootstrapPromise: null,
  keepaliveTimer: null,
  keepaliveBound: false,
  keepaliveInFlight: false,
  keepaliveCooldownUntil: 0,
  reauthOpen: false,
  lastActivityAt: Date.now(),
  lastSessionTouchAt: Date.now(),
  lastMouseActivityAt: 0,
  loaded: false,
  createReauthFormModal: null,
  uiAlert: null,
};

runtime.bootstrap = hydrateBootstrap();

export async function createRelaySessionClient(config = {}) {
  await ensureUi();
  await ensureBootstrap(config);
  ensureKeepalive();

  return {
    alert: runtime.uiAlert,
    fetch(input, init = {}, options = {}) {
      return relayFetch(input, init, options);
    },
    async login(credentials) {
      const payload = await performLoginRequest(config.loginUrl || "/api/login", credentials);

      applySessionPayload(payload.data || {});
      return payload;
    },
    async logout() {
      const payload = await requestJson(config.logoutUrl || "/api/logout", {
        method: "POST",
      }, {
        retryOnSessionExpired: false,
      });

      clearSession(payload.data?.csrf_token || "");
      window.location.href = "/";
      return payload;
    },
    async fetchCurrentUser() {
      const payload = await requestJson(config.userUrl || "/api/user", {
        method: "GET",
      }, {
        retryOnSessionExpired: false,
      });

      if (payload?.data) {
        applySessionPayload(payload.data);
      }

      return payload;
    },
    getCsrfToken,
    setCsrfToken,
    applySessionPayload,
    getState() {
      return runtime.bootstrap;
    },
  };
}

async function ensureUi() {
  if (runtime.loaded) {
    return;
  }

  await uiLoader.loadMany(["ui.form.modal.reauth", "ui.dialog.alert"]);
  runtime.createReauthFormModal = await uiLoader.get("ui.form.modal.reauth");
  runtime.uiAlert = await uiLoader.get("ui.dialog.alert");
  runtime.loaded = true;
}

async function ensureBootstrap(config = {}) {
  if (runtime.bootstrapPromise) {
    return runtime.bootstrapPromise;
  }

  runtime.bootstrapPromise = fetchBootstrap(config)
    .catch((error) => {
      console.warn("Relay bootstrap fetch failed, falling back to embedded bootstrap.", error);
      return runtime.bootstrap;
    })
    .finally(() => {
      runtime.bootstrapPromise = null;
    });

  return runtime.bootstrapPromise;
}

function hydrateBootstrap() {
  const existing = typeof window.__PBB_BOOTSTRAP__ === "object" && window.__PBB_BOOTSTRAP__ !== null
    ? window.__PBB_BOOTSTRAP__
    : {};
  const metaToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

  const bootstrap = {
    ...DEFAULT_BOOTSTRAP,
    ...existing,
    app: {
      ...DEFAULT_BOOTSTRAP.app,
      ...(existing.app || {}),
    },
    auth: {
      ...DEFAULT_BOOTSTRAP.auth,
      ...(existing.auth || {}),
    },
    security: {
      ...DEFAULT_BOOTSTRAP.security,
      ...(existing.security || {}),
    },
    settings: {
      ...DEFAULT_BOOTSTRAP.settings,
      ...(existing.settings || {}),
    },
  };

  if (!bootstrap.security.csrfToken && metaToken) {
    bootstrap.security.csrfToken = metaToken;
  }

  window.__PBB_BOOTSTRAP__ = bootstrap;

  if (bootstrap.security.csrfToken) {
    syncMetaToken(bootstrap.security.csrfToken);
  }

  touchSession();

  return bootstrap;
}

async function relayFetch(input, init = {}, options = {}) {
  let response = await fetch(input, normalizeRequest(init));

  if (!isSessionExpiredResponse(response) || options.retryOnSessionExpired === false || !isAuthenticated()) {
    return response;
  }

  const restored = await ensureSession();

  if (!restored) {
    return response;
  }

  response = await fetch(input, normalizeRequest(init));
  return response;
}

async function requestJson(input, init = {}, options = {}) {
  const response = await relayFetch(input, init, options);
  const payload = await parseJson(response);

  if (response.ok && payload?.status !== false) {
    touchSession();
    return payload;
  }

  const error = new Error(payload?.error?.message || payload?.message || `Request failed with status ${response.status}.`);
  error.payload = payload;
  error.response = response;
  throw error;
}

async function performLoginRequest(url, credentials) {
  try {
    return await requestJson(url, {
      method: "POST",
      body: credentialsToParams(credentials),
    }, {
      retryOnSessionExpired: false,
    });
  } catch (error) {
    if (error?.response?.status !== 419) {
      throw error;
    }

    const sessionPayload = await requestJson(runtime.bootstrap.settings?.csrfTokenUrl || "/api/csrf-token", {
      method: "GET",
    }, {
      retryOnSessionExpired: false,
    });

    if (sessionPayload?.csrfToken) {
      setCsrfToken(sessionPayload.csrfToken);
    }

    return requestJson(url, {
      method: "POST",
      body: credentialsToParams(credentials),
    }, {
      retryOnSessionExpired: false,
    });
  }
}

function normalizeRequest(init = {}) {
  const method = String(init.method || "GET").toUpperCase();
  const headers = new Headers(init.headers || {});

  if (!headers.has("Accept")) {
    headers.set("Accept", "application/json");
  }

  headers.set("X-Requested-With", "XMLHttpRequest");

  if (method !== "GET" && method !== "HEAD" && !headers.has("X-CSRF-TOKEN")) {
    headers.set("X-CSRF-TOKEN", getCsrfToken());
  }

  return {
    credentials: "same-origin",
    ...init,
    method,
    headers,
  };
}

function isSessionExpiredResponse(response) {
  return Boolean(response) && (response.status === 401 || response.status === 419);
}

function isAuthenticated() {
  return Boolean(runtime.bootstrap?.auth?.authenticated);
}

function getAccount() {
  return runtime.bootstrap?.auth?.account || null;
}

function getCsrfToken() {
  return String(runtime.bootstrap?.security?.csrfToken || "");
}

function setCsrfToken(token) {
  const nextToken = String(token || "").trim();
  runtime.bootstrap.security = runtime.bootstrap.security || {};
  runtime.bootstrap.security.csrfToken = nextToken;
  window.__PBB_BOOTSTRAP__ = runtime.bootstrap;
  syncMetaToken(nextToken);
}

function syncMetaToken(token) {
  let meta = document.querySelector('meta[name="csrf-token"]');

  if (!(meta instanceof HTMLMetaElement)) {
    meta = document.createElement("meta");
    meta.name = "csrf-token";
    document.head.append(meta);
  }

  meta.setAttribute("content", String(token || ""));
}

function applySessionPayload(data = {}) {
  runtime.bootstrap.auth = {
    authenticated: Boolean(data.authenticated ?? data.account),
    account: data.account || null,
  };

  if (data.csrf_token) {
    setCsrfToken(data.csrf_token);
  } else {
    window.__PBB_BOOTSTRAP__ = runtime.bootstrap;
  }

  touchSession();

  dispatchSessionEvent("relay:session-updated", {
    account: runtime.bootstrap.auth.account,
    authenticated: runtime.bootstrap.auth.authenticated,
    csrfToken: getCsrfToken(),
  });
}

function clearSession(csrfToken = "") {
  runtime.bootstrap.auth = {
    authenticated: false,
    account: null,
  };
  setCsrfToken(csrfToken);
  dispatchSessionEvent("relay:session-cleared", {
    csrfToken: getCsrfToken(),
  });
}

async function fetchBootstrap(config = {}) {
  const response = await fetch(resolveBootstrapUrl(config), {
    method: "GET",
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  const payload = await parseJson(response);
  if (!response.ok || !payload || typeof payload !== "object") {
    throw new Error(`Bootstrap request failed with status ${response.status}.`);
  }

  applyBootstrapPayload(payload);
  return runtime.bootstrap;
}

function applyBootstrapPayload(payload = {}) {
  runtime.bootstrap = {
    ...DEFAULT_BOOTSTRAP,
    ...runtime.bootstrap,
    ...payload,
    app: {
      ...DEFAULT_BOOTSTRAP.app,
      ...(runtime.bootstrap.app || {}),
      ...(payload.app || {}),
    },
    auth: {
      ...DEFAULT_BOOTSTRAP.auth,
      ...(runtime.bootstrap.auth || {}),
      ...(payload.auth || {}),
    },
    security: {
      ...DEFAULT_BOOTSTRAP.security,
      ...(runtime.bootstrap.security || {}),
      ...(payload.security || {}),
    },
    settings: {
      ...DEFAULT_BOOTSTRAP.settings,
      ...(runtime.bootstrap.settings || {}),
      ...(payload.settings || {}),
    },
  };

  if (runtime.bootstrap.security.csrfToken) {
    syncMetaToken(runtime.bootstrap.security.csrfToken);
  }

  window.__PBB_BOOTSTRAP__ = runtime.bootstrap;
  touchSession();
}

function ensureKeepalive() {
  if (!runtime.keepaliveBound) {
    ["pointerdown", "click", "keydown", "touchstart", "scroll"].forEach((eventName) => {
      window.addEventListener(eventName, recordActivity, { passive: true });
    });
    window.addEventListener("mousemove", recordMouseActivity, { passive: true });
    document.addEventListener("visibilitychange", recordActivity);
    runtime.keepaliveBound = true;
  }

  if (runtime.keepaliveTimer !== null) {
    return;
  }

  runtime.keepaliveTimer = window.setInterval(() => {
    maybePingSession().catch((error) => {
      console.warn("Relay session keepalive failed.", error);
    });
  }, 30000);
}

function recordActivity() {
  runtime.lastActivityAt = Date.now();
}

function recordMouseActivity() {
  const now = Date.now();
  if (now - runtime.lastMouseActivityAt < 15000) {
    return;
  }

  runtime.lastMouseActivityAt = now;
  recordActivity();
}

async function maybePingSession() {
  if (!shouldPingSession()) {
    return;
  }

  runtime.keepaliveInFlight = true;

  try {
    const payload = await requestJson(
      runtime.bootstrap.settings?.sessionPingUrl || "/api/session/ping",
      { method: "GET" },
      { retryOnSessionExpired: true },
    );

    const data = payload?.data || {};
    applyBootstrapPayload({
      auth: {
        authenticated: Boolean(runtime.bootstrap.auth?.authenticated),
        account: runtime.bootstrap.auth?.account || null,
      },
      security: {
        csrfToken: data.csrf_token || getCsrfToken(),
        sessionLifetimeMinutes: data.session_lifetime_minutes || runtime.bootstrap.security?.sessionLifetimeMinutes || 0,
        keepaliveThresholdSeconds: runtime.bootstrap.security?.keepaliveThresholdSeconds || 120,
      },
      settings: runtime.bootstrap.settings || {},
    });
  } catch (error) {
    runtime.keepaliveCooldownUntil = Date.now() + 60000;
    throw error;
  } finally {
    runtime.keepaliveInFlight = false;
  }
}

function shouldPingSession() {
  if (!isAuthenticated()) {
    return false;
  }

  if (document.visibilityState === "hidden") {
    return false;
  }

  if (runtime.reauthOpen || runtime.keepaliveInFlight || Date.now() < runtime.keepaliveCooldownUntil) {
    return false;
  }

  const lifetimeMinutes = Number(runtime.bootstrap.security?.sessionLifetimeMinutes || 0);
  const thresholdSeconds = Number(runtime.bootstrap.security?.keepaliveThresholdSeconds || 120);

  if (!Number.isFinite(lifetimeMinutes) || lifetimeMinutes <= 0) {
    return false;
  }

  const now = Date.now();
  const thresholdMs = Math.max(60000, thresholdSeconds * 1000);
  const lifetimeMs = lifetimeMinutes * 60 * 1000;
  const recentActivity = now - runtime.lastActivityAt <= thresholdMs;
  const nearExpiry = now - runtime.lastSessionTouchAt >= Math.max(60000, lifetimeMs - thresholdMs);

  return recentActivity && nearExpiry;
}

function resolveBootstrapUrl(config = {}) {
  const baseUrl = config.bootstrapUrl || runtime.bootstrap.settings?.bootstrapUrl || "/api/bootstrap";
  const url = new URL(baseUrl, window.location.origin);
  url.searchParams.set("page", String(runtime.bootstrap.app?.page || "relay"));
  return url.toString();
}

function touchSession() {
  runtime.lastSessionTouchAt = Date.now();
}

function dispatchSessionEvent(name, detail) {
  window.dispatchEvent(new CustomEvent(name, { detail }));
}

async function ensureSession() {
  if (runtime.activeRenewal) {
    return runtime.activeRenewal;
  }

  runtime.activeRenewal = openReloginModal()
    .finally(() => {
      runtime.activeRenewal = null;
    });

  return runtime.activeRenewal;
}

async function openReloginModal() {
  const account = getAccount();
  const email = String(account?.email || "").trim();
  return new Promise((resolve) => {
    runtime.reauthOpen = true;
    const modal = runtime.createReauthFormModal({
      title: "Session Expired",
      size: "md",
      message: "Your session has expired. To continue, please enter your password again.",
      identifierValue: email,
      submitLabel: "Login",
      busyMessage: "Re-authenticating...",
      closeOnBackdrop: false,
      closeOnEscape: false,
      showCloseButton: false,
      onClose(meta) {
        runtime.reauthOpen = false;
        const restored = meta?.reason === "submit";
        resolve(restored);

        if (!restored) {
          window.location.reload();
        }
      },
      async onSubmit(values, ctx) {
        ctx.clearErrors();
        ctx.clearFormError();

        try {
          const payload = await performLoginRequest("/api/login", {
            email,
            password: values.password,
          });

          applySessionPayload(payload.data || {});
          return true;
        } catch (requestError) {
          ctx.setFormError(requestError?.payload?.error?.message || requestError.message || "Unable to restore the session.");
          return false;
        }
      },
    });

    modal.open();
  });
}

function credentialsToParams(credentials = {}) {
  return new URLSearchParams({
    email: String(credentials.email || "").trim(),
    password: String(credentials.password || ""),
  });
}

async function parseJson(response) {
  try {
    return await response.json();
  } catch (error) {
    return null;
  }
}
