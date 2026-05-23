import { createIcon, uiLoader } from "/relay-ui/helper-ui-bundle.js";

const configNode = document.getElementById("relay-installer-config");
const config = configNode ? JSON.parse(configNode.textContent || "{}") : {};
const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute("content") || "";

const elements = {
  stepItems: Array.from(document.querySelectorAll("[data-step-item]")),
  stepPanels: Array.from(document.querySelectorAll("[data-step-panel]")),
  groups: document.getElementById("installer-groups"),
  summary: document.getElementById("installer-summary"),
  statusTitle: document.getElementById("installer-status-title"),
  statusDetail: document.getElementById("installer-status-detail"),
  stateLabel: document.getElementById("installer-state-label"),
  continueButton: document.getElementById("installer-continue"),
  refreshButton: document.getElementById("installer-refresh"),
  nextPhase: document.getElementById("installer-next-phase"),
  executeButton: document.getElementById("installer-execute"),
  cleanupButton: document.getElementById("installer-cleanup"),
  successReview: document.getElementById("installer-success-review"),
  hqCard: document.getElementById("installer-hq-card"),
  hqForm: document.getElementById("installer-hq-form"),
  hqReview: document.getElementById("installer-hq-review"),
  settingsCard: document.getElementById("installer-settings-card"),
  settingsForm: document.getElementById("installer-settings-form"),
  settingsReview: document.getElementById("installer-settings-review"),
  databaseDriver: document.getElementById("installer-database-driver"),
  sqliteFields: document.getElementById("installer-sqlite-fields"),
  mysqlFields: document.getElementById("installer-mysql-fields"),
};

const state = {
  checks: null,
  uiAlert: null,
  createActionModal: null,
  createProgress: null,
  hqValidationModal: null,
  hqValidationInFlight: false,
  autoCleanupInFlight: false,
  completionNoticeModal: null,
  completionNoticeShown: false,
  execution: config.execution || null,
  executionLoopRunning: false,
  executionModal: null,
  executionModalRefs: null,
};

boot().catch((error) => {
  console.error(error);
});

async function boot() {
  document.documentElement.setAttribute("data-theme", "dark");
  await uiLoader.loadMany(["ui.dialog.alert", "ui.action.modal", "ui.progress", "ui.icons"]);
  state.uiAlert = await uiLoader.get("ui.dialog.alert");
  state.createActionModal = await uiLoader.get("ui.action.modal");
  state.createProgress = await uiLoader.get("ui.progress");

  elements.refreshButton?.addEventListener("click", () => {
    loadEnvironmentChecks({ force: true }).catch(handleUiError);
  });

  elements.continueButton?.addEventListener("click", () => {
    continueEnvironment().catch(handleUiError);
  });

  elements.hqForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    submitHqValidation().catch(handleUiError);
  });

  elements.settingsForm?.addEventListener("submit", (event) => {
    event.preventDefault();
    submitSettings().catch(handleUiError);
  });

  elements.executeButton?.addEventListener("click", () => {
    executeInstallation().catch(handleUiError);
  });

  elements.cleanupButton?.addEventListener("click", () => {
    runInstallerCleanup().catch(handleUiError);
  });

  elements.databaseDriver?.addEventListener("change", syncDatabaseFieldVisibility);

  updateInstallerStateLabel(config.state?.status || "fresh");
  hydrateState(config.state || {});
  await loadExecutionProgress({ silent: true });
  syncDatabaseFieldVisibility();
  await loadEnvironmentChecks();
}

async function loadEnvironmentChecks() {
  setBusyState("Running checks...", "Gathering environment requirements for this host.");

  const response = await fetch(config.endpoints.environment, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  const result = await response.json();

  if (!response.ok) {
    throw new Error(result?.message || "Failed to load environment checks.");
  }

  state.checks = result;
  renderChecks(result);
}

function renderChecks(payload) {
  const groups = payload?.groups || {};
  const summary = payload?.summary || {};
  const canContinue = Boolean(payload?.can_continue);
  const status = String(payload?.status || "blocked");

  elements.groups.innerHTML = "";

  Object.entries(groups).forEach(([groupKey, checks]) => {
    const section = document.createElement("section");
    section.className = "relay-installer-group";

    const groupStatus = deriveGroupStatus(checks);
    section.innerHTML = `
      <div class="relay-installer-group-head">
        <div>
          <p class="ui-eyebrow">${escapeHtml(groupKey)}</p>
          <h3 class="relay-installer-group-title">${escapeHtml(formatLabel(groupKey))}</h3>
        </div>
        <span class="relay-installer-group-badge is-${groupStatus}" aria-hidden="true"></span>
      </div>
      <div class="relay-installer-group-list"></div>
    `;

    const list = section.querySelector(".relay-installer-group-list");

    checks.forEach((check) => {
      const item = document.createElement("article");
      item.className = `relay-installer-check is-${check.status}`;
      item.innerHTML = `
        <span class="relay-installer-check-indicator" aria-hidden="true"></span>
        <div>
          <strong>${escapeHtml(check.label || check.key || "Check")}</strong>
          <p>${escapeHtml(check.message || "")}</p>
          ${check.hint ? `<small>${escapeHtml(check.hint)}</small>` : ""}
        </div>
      `;
      list?.appendChild(item);
    });

    elements.groups.appendChild(section);
  });

  elements.summary.innerHTML = `<span class="ui-badge relay-installer-badge is-${status === "ready" ? "ready" : "blocked"}">${status === "ready" ? "READY" : "BLOCKED"}</span>`;
  elements.statusTitle.textContent = status === "ready" ? "Environment is ready." : "Environment has blocking issues.";
  elements.statusDetail.textContent = `Checks: ${summary.total_checks || 0}, blocking failures: ${summary.blocking_failures || 0}, warnings: ${summary.warnings || 0}.`;
  elements.continueButton.disabled = !canContinue;
}

async function continueEnvironment() {
  if (!state.checks?.can_continue) {
    return;
  }

  elements.continueButton.disabled = true;

  const response = await fetch(config.endpoints.continue, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken,
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
    body: JSON.stringify({}),
  });

  const result = await response.json();

  if (!response.ok) {
    throw new Error(result?.message || "Failed to continue installer state.");
  }

  updateInstallerStateLabel(result?.state?.status || "environment_checked");
  setActiveStep(2);
  elements.statusTitle.textContent = "Environment checks accepted.";
  elements.statusDetail.textContent = "Continue with HQ identity validation for this Relay installation.";
}

async function submitHqValidation() {
  if (state.hqValidationInFlight) {
    return;
  }

  const form = new FormData(elements.hqForm);
  const payload = Object.fromEntries(form.entries());
  const submitButton = elements.hqForm?.querySelector('button[type="submit"]');

  state.hqValidationInFlight = true;
  if (submitButton instanceof HTMLButtonElement) {
    submitButton.disabled = true;
  }
  openHqValidationModal();

  try {
    const response = await fetch(config.endpoints.hqValidate, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      body: JSON.stringify(payload),
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(readValidationError(result) || "HQ validation failed.");
    }

    hydrateState(result.state || {});
    renderHqReview(result.hub || {});
    elements.hqReview.hidden = false;
    setActiveStep(3);
    elements.statusTitle.textContent = "HQ identity validated.";
    elements.statusDetail.textContent = "Review the HQ-derived hub identity, then capture the local Relay admin and install settings.";
  } finally {
    state.hqValidationInFlight = false;
    if (submitButton instanceof HTMLButtonElement) {
      submitButton.disabled = false;
    }
    closeHqValidationModal();
  }
}

async function submitSettings() {
  const form = new FormData(elements.settingsForm);
  const payload = Object.fromEntries(form.entries());

  const response = await fetch(config.endpoints.settings, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken,
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
    body: JSON.stringify(payload),
  });

  const result = await response.json();

  if (!response.ok) {
    throw new Error(readValidationError(result) || "Saving install settings failed.");
  }

  hydrateState(result.state || {});
  renderSettingsReview(result.settings || {});
  elements.settingsReview.hidden = false;
  setActiveStep(4);
  elements.statusTitle.textContent = "Install settings saved.";
  elements.statusDetail.textContent = "This installer session is now ready for installation execution.";
}

async function executeInstallation() {
  elements.executeButton.disabled = true;
  setExecutionModalOpen(true);
  renderExecutionState({
    status: "running",
    current_step: "prepare_workspace",
    steps: [],
  });

  const response = await fetch(config.endpoints.execute, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken,
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
    body: JSON.stringify({}),
  });

  const result = await response.json();

  if (!response.ok) {
    elements.executeButton.disabled = false;
    setExecutionModalOpen(false);
    throw new Error(readValidationError(result) || "Installation execution failed.");
  }

  syncExecution(result.execution || {});
  await advanceExecutionLoop();
}

async function loadExecutionProgress({ silent = false } = {}) {
  if (!silent) {
    setExecutionModalOpen(true);
  }

  const response = await fetch(config.endpoints.progress, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  const result = await response.json();

  if (!response.ok) {
    throw new Error(readValidationError(result) || "Unable to load installer execution progress.");
  }

  syncExecution(result.execution || {});

  return result.execution || {};
}

async function advanceExecutionLoop() {
  if (state.executionLoopRunning) {
    return;
  }

  state.executionLoopRunning = true;

  try {
    while (state.execution?.status === "running") {
      const response = await fetch(config.endpoints.advanceExecution, {
        method: "POST",
        headers: {
          Accept: "application/json",
          "Content-Type": "application/json",
          "X-CSRF-TOKEN": csrfToken,
          "X-Requested-With": "XMLHttpRequest",
        },
        credentials: "same-origin",
        body: JSON.stringify({}),
      });

      const result = await response.json();

      if (!response.ok) {
        throw new Error(readValidationError(result) || "Unable to advance installer execution.");
      }

      syncExecution(result.execution || {});

      if (state.execution?.status !== "running") {
        break;
      }

      await delay(350);
    }
  } finally {
    state.executionLoopRunning = false;
  }
}

async function retryExecution() {
  const response = await fetch(config.endpoints.retryExecution, {
    method: "POST",
    headers: {
      Accept: "application/json",
      "Content-Type": "application/json",
      "X-CSRF-TOKEN": csrfToken,
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
    body: JSON.stringify({}),
  });

  const result = await response.json();

  if (!response.ok) {
    throw new Error(readValidationError(result) || "Unable to retry installer execution.");
  }

  syncExecution(result.execution || {});
  await advanceExecutionLoop();
}

async function runInstallerCleanup() {
  return runInstallerCleanupInternal({ autoRedirect: false });
}

async function runInstallerCleanupInternal({ showCompletionNotice = false } = {}) {
  if (state.autoCleanupInFlight) {
    return null;
  }

  state.autoCleanupInFlight = true;
  elements.cleanupButton.disabled = true;

  try {
    const response = await fetch(config.endpoints.cleanup, {
      method: "POST",
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-CSRF-TOKEN": csrfToken,
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
      body: JSON.stringify({}),
    });

    const result = await response.json();

    if (!response.ok) {
      throw new Error(readValidationError(result) || "Installer cleanup failed.");
    }

    elements.cleanupButton.hidden = true;
    elements.statusTitle.textContent = "Installer cleanup completed.";
    elements.statusDetail.textContent = showCompletionNotice
      ? "Temporary installer assets were removed. Review the final notice before opening the installed Relay app."
      : "Temporary installer assets and extracted package files were removed from this host.";

    if (elements.successReview.innerHTML !== "") {
      elements.successReview.insertAdjacentHTML(
        "beforeend",
        `<div class="relay-installer-review-grid">${reviewItem("Cleanup Result", `Deleted ${result?.cleanup?.deleted?.length || 0} installer target(s)`)}</div>`,
      );
    }

    if (state.execution?.install_result?.cleanup) {
      state.execution.install_result.cleanup.result = result.cleanup || { deleted: [] };
    }
    if (state.execution?.install_result?.state?.install_summary) {
      state.execution.install_result.state.install_summary.cleanup_pending = false;
    }

    if (showCompletionNotice) {
      await showCompletionNoticeDialog();
    }

    return result;
  } finally {
    state.autoCleanupInFlight = false;
    elements.cleanupButton.disabled = false;
  }
}

function updateInstallerStateLabel(value) {
  elements.stateLabel.textContent = String(value || "fresh").toUpperCase();
}

function hydrateState(currentState) {
  updateInstallerStateLabel(currentState?.status || "fresh");
  setActiveStep(resolveStep(currentState?.status || "fresh"));

  if (currentState?.status === "environment_checked" || currentState?.status === "hq_validated" || currentState?.status === "settings_collected" || currentState?.status === "installed") {
    elements.hqCard.hidden = false;
  }

  if (currentState?.hq) {
    renderHqReview({
      hq_hub_id: currentState.hq.hq_hub_id,
      relay_hub_id: currentState.hq.relay_hub_id,
      name: currentState.hq.name,
      deployment: currentState.hq.deployment,
      status: currentState.hq.status,
      app_url: currentState.hq.domain,
      uplinks: currentState.hq.uplinks,
    });
    elements.hqReview.hidden = false;
  }

  if (currentState?.admin) {
    setFormValue(elements.settingsForm, "admin_name", currentState.admin.name || "");
    setFormValue(elements.settingsForm, "admin_email", currentState.admin.email || "");
  }

  if (currentState?.hq?.hq_hub_id) {
    setFormValue(elements.hqForm, "hq_hub_id", currentState.hq.hq_hub_id);
  }

  if (currentState?.status === "hq_validated" || currentState?.status === "settings_collected" || currentState?.status === "installed") {
    elements.settingsCard.hidden = false;
  }

  if (currentState?.settings) {
    Object.entries(currentState.settings).forEach(([key, value]) => {
      setFormValue(elements.settingsForm, key, value ?? "");
    });
    renderSettingsReview(currentState.settings);
    elements.settingsReview.hidden = false;
  }

  if (currentState?.status === "settings_collected" || currentState?.status === "installed") {
    elements.nextPhase.hidden = false;
  }

  if (currentState?.status === "installed" && currentState?.install_summary) {
    elements.nextPhase.hidden = false;
    renderExecutionSuccess({
      state: currentState,
      lock: currentState.install_summary,
      admin: null,
      release: currentState.install_summary.release || null,
      cleanup: {
        manifest: currentState.install_summary.cleanup_manifest_path || null,
        auto_run: currentState.install_summary.cleanup_auto_run || false,
      },
    });
    elements.successReview.hidden = false;
    elements.cleanupButton.hidden = Boolean(currentState.install_summary.cleanup_auto_run) || !currentState.install_summary.cleanup_pending;
  }
}

function syncExecution(executionState) {
  state.execution = executionState || {
    status: "idle",
    steps: [],
  };

  renderExecutionState(state.execution);

  const status = state.execution?.status || "idle";
  const installResult = state.execution?.install_result || {};

  if (status === "completed" && installResult?.state) {
    hydrateState(installResult.state);
    renderExecutionSuccess(installResult);
    elements.successReview.hidden = false;
    elements.cleanupButton.hidden = Boolean(installResult?.cleanup?.auto_run) || !installResult?.cleanup?.manifest;
    elements.statusTitle.textContent = "Relay installation completed.";
    elements.statusDetail.textContent = installResult?.cleanup?.auto_run
      ? "Configuration was written, the target database was migrated, the admin user was created, and cleanup has already been applied."
      : "Configuration was written, the target database was migrated, the admin user was created, and the install lock was finalized.";
    elements.executeButton.disabled = true;
    maybeFinalizeCompletedInstall(installResult).catch(handleUiError);
  } else if (status === "failed") {
    elements.executeButton.disabled = false;
  }

  if (["running", "completed", "failed"].includes(status)) {
    setExecutionModalOpen(true);
  } else {
    setExecutionModalOpen(false);
  }
}

function renderExecutionState(executionState) {
  const modal = ensureExecutionModal();
  const refs = state.executionModalRefs;
  const status = executionState?.status || "idle";
  const badgeState = status === "completed" ? "ready" : status === "failed" ? "blocked" : "pending";
  const progress = summarizeExecutionProgress(executionState);
  refs.badge.textContent = status.toUpperCase();
  refs.badge.className = `ui-badge relay-installer-badge is-${badgeState}`;
  refs.progressApi.update(
    {
      label: progress.label,
      value: progress.percent,
    },
    {
      color: progress.color,
      trackColor: "rgba(173, 213, 255, 0.12)",
    },
  );

  if (status === "running") {
    modal.setTitle("Installing Relay");
    refs.detail.textContent = "The installer is running step-by-step. Do not close this page while execution is in progress.";
  } else if (status === "completed") {
    modal.setTitle("Installation Complete");
    refs.detail.textContent = "Relay installation completed. Review the final summary below before leaving this installer.";
  } else if (status === "failed") {
    modal.setTitle("Installation Failed");
    refs.detail.textContent = "The installer stopped at a failed step. Review the error below before retrying or closing.";
  } else {
    modal.setTitle("Installing Relay");
    refs.detail.textContent = "Preparing install execution.";
  }

  modal.setBusy(false);

  refs.warning.hidden = status !== "running";
  refs.steps.innerHTML = "";

  (executionState?.steps || []).forEach((step) => {
    const article = document.createElement("article");
    article.className = `relay-installer-execution-step is-${step.status || "pending"}`;
    article.innerHTML = `
      <div class="relay-installer-execution-step-head">
        <strong>${escapeHtml(step.label || formatLabel(step.key || "step"))}</strong>
        <span class="relay-installer-execution-step-badge is-${escapeHtml(step.status || "pending")}">${escapeHtml((step.status || "pending").toUpperCase())}</span>
      </div>
      <p>${escapeHtml(step.message || step.pending_message || "")}</p>
    `;
    refs.steps.appendChild(article);
  });

  const failure = executionState?.failure;
  refs.failure.hidden = !failure;
  refs.failure.innerHTML = failure
    ? `
      <p class="ui-eyebrow">Failed Step</p>
      <strong>${escapeHtml(formatLabel(failure.step || ""))}</strong>
      <p>${escapeHtml(failure.message || "")}</p>
      ${failure.detail ? `<small>${escapeHtml(failure.detail)}</small>` : ""}
    `
    : "";

  const installResult = executionState?.install_result || {};
  const admin = executionState?.admin_credentials || installResult?.admin || null;
  const lock = installResult?.lock || null;
  refs.success.hidden = status !== "completed";
  refs.success.innerHTML = status === "completed"
    ? `
      <p class="ui-eyebrow">Install Complete</p>
      <div class="relay-installer-review-grid">
        ${reviewItem("Relay Hub ID", lock?.relay_hub_id || installResult?.state?.install_summary?.relay_hub_id || "")}
        ${reviewItem("App URL", lock?.app_url || installResult?.state?.install_summary?.app_url || "")}
        ${reviewItem("Admin Email", admin?.email || "Already provisioned")}
        ${reviewValueWithCopy("Generated Password", admin?.password || "Shown only on first successful execution", admin?.password)}
      </div>
    `
    : "";

  modal.update({
    showCloseButton: status !== "running",
    closeOnEscape: status !== "running",
    closeOnBackdrop: status !== "running",
  });

  modal.setActions(buildExecutionActions(status, executionState));
  bindCopyButtons(refs.success);
}

function setExecutionModalOpen(open) {
  const modal = ensureExecutionModal();

  if (open) {
    modal.open();
    return;
  }

  modal.close();
}

function ensureExecutionModal() {
  if (state.executionModal) {
    return state.executionModal;
  }

  const container = document.createElement("div");
  container.className = "relay-installer-execution-body ui-stack";

  const header = document.createElement("div");
  header.className = "relay-installer-execution-head";
  header.innerHTML = `
    <div>
      <p class="ui-eyebrow">Installation Execution</p>
      <p class="relay-installer-note" data-execution-detail>Preparing install execution.</p>
      <div class="relay-installer-progress-mount" data-execution-progress></div>
    </div>
  `;
  const badge = document.createElement("span");
  badge.className = "ui-badge relay-installer-badge is-pending";
  badge.textContent = "PENDING";
  header.appendChild(badge);

  const warning = document.createElement("div");
  warning.className = "relay-installer-execution-warning";
  warning.textContent = "Do not close this page while installation is running.";

  const steps = document.createElement("div");
  steps.className = "relay-installer-execution-steps";

  const failure = document.createElement("div");
  failure.className = "relay-installer-execution-failure";
  failure.hidden = true;

  const success = document.createElement("div");
  success.className = "relay-installer-execution-success";
  success.hidden = true;

  container.append(header, warning, steps, failure, success);

  const progressMount = header.querySelector("[data-execution-progress]");
  const progressApi = state.createProgress(
    progressMount,
    {
      label: "Waiting to start.",
      value: 0,
    },
    {
      style: "striped",
      size: "sm",
      animate: true,
      rounded: true,
      glow: false,
      showLabel: true,
      showPercent: true,
      ariaLabel: "Installation progress",
      className: "relay-installer-progress-widget",
      trackColor: "rgba(173, 213, 255, 0.12)",
    },
  );

  state.executionModalRefs = {
    detail: header.querySelector("[data-execution-detail]"),
    progressApi,
    badge,
    warning,
    steps,
    failure,
    success,
  };

  state.executionModal = state.createActionModal({
    title: "Installing Relay",
    size: "lg",
    content: container,
    actions: [],
    closeOnBackdrop: false,
    closeOnEscape: false,
    showCloseButton: false,
    onClose() {
      if (state.execution?.status === "running") {
        state.executionModal.open();
      }
    },
  });

  return state.executionModal;
}

function ensureHqValidationModal() {
  if (state.hqValidationModal) {
    return state.hqValidationModal;
  }

  const content = document.createElement("div");
  content.className = "relay-installer-status-modal";
  content.innerHTML = `
    <p class="ui-eyebrow">HQ Validation</p>
    <h3 class="relay-installer-status-modal-title">Validating HQ identity</h3>
    <p class="relay-installer-status-modal-detail">Checking the provided HQ Hub ID and token against PBB HQ. Do not close this page while validation is in progress.</p>
  `;

  state.hqValidationModal = state.createActionModal({
    title: "Validating HQ Identity",
    size: "sm",
    content,
    actions: [],
    closeOnBackdrop: false,
    closeOnEscape: false,
    showCloseButton: false,
    onClose() {
      if (state.hqValidationInFlight) {
        state.hqValidationModal.open();
      }
    },
  });

  return state.hqValidationModal;
}

function openHqValidationModal() {
  ensureHqValidationModal().open();
}

function closeHqValidationModal() {
  if (!state.hqValidationModal) {
    return;
  }

  state.hqValidationModal.close({ reason: "programmatic" });
}

function summarizeExecutionProgress(executionState) {
  const steps = Array.isArray(executionState?.steps) ? executionState.steps : [];
  const total = steps.length;
  const completed = steps.filter((step) => step.status === "completed").length;
  const runningIndex = steps.findIndex((step) => step.status === "running");
  const failedStep = steps.find((step) => step.status === "failed");

  if (!total) {
    return {
      percent: 0,
      label: "Waiting to start.",
      color: "#7baadc",
    };
  }

  if (executionState?.status === "completed") {
    return {
      percent: 100,
      label: `Completed all ${total} installation phases.`,
      color: "#4dad7a",
    };
  }

  if (executionState?.status === "failed" && failedStep) {
    const failedIndex = Math.max(steps.findIndex((step) => step.status === "failed"), 0);

    return {
      percent: Math.max(8, Math.round((completed / total) * 100)),
      label: `Failed on step ${failedIndex + 1} of ${total}: ${formatLabel(failedStep.key || "step")}.`,
      color: "#d95c5c",
    };
  }

  if (runningIndex >= 0) {
    const activeStep = steps[runningIndex];

    return {
      percent: Math.max(8, Math.min(96, Math.round(((completed + 0.5) / total) * 100))),
      label: `Step ${runningIndex + 1} of ${total}: ${formatLabel(activeStep.key || "step")}.`,
      color: "#7baadc",
    };
  }

  return {
    percent: Math.max(6, Math.round((completed / total) * 100)),
    label: `${completed} of ${total} installation phases completed.`,
    color: "#7baadc",
  };
}

function buildExecutionActions(status, executionState) {
  const actions = [];

  if (status === "failed" && executionState?.retry_allowed) {
    actions.push({
      id: "retry",
      label: "Retry Failed Step",
      variant: "ghost",
      onClick() {
        retryExecution().catch(handleUiError);
        return false;
      },
    });
  }

  if (["failed", "completed"].includes(status)) {
    actions.push({
      id: "close",
      label: status === "completed" ? "Open Relay" : "Return To Installer",
      variant: "primary",
      onClick() {
        if (status === "completed") {
          window.location.assign(resolveInstalledAppUrl(executionState?.install_result || {}));
          return false;
        }
        setExecutionModalOpen(false);
        return false;
      },
    });
  }

  return actions;
}

function setBusyState(title, detail) {
  elements.statusTitle.textContent = title;
  elements.statusDetail.textContent = detail;
  elements.summary.innerHTML = `<span class="ui-badge relay-installer-badge is-pending">PENDING</span>`;
}

function setActiveStep(step) {
  elements.stepItems.forEach((item) => {
    item.classList.toggle("is-active", Number(item.dataset.stepItem) === step);
  });

  elements.stepPanels.forEach((panel) => {
    panel.hidden = Number(panel.dataset.stepPanel) !== step;
  });
}

function resolveStep(status) {
  if (status === "environment_checked") {
    return 2;
  }

  if (status === "hq_validated") {
    return 3;
  }

  if (status === "settings_collected" || status === "installed") {
    return 4;
  }

  return 1;
}

function deriveGroupStatus(checks) {
  if ((checks || []).some((check) => check.status === "fail")) {
    return "fail";
  }

  if ((checks || []).some((check) => check.status === "warning")) {
    return "warning";
  }

  return "pass";
}

function renderHqReview(hub) {
  const uplink = Array.isArray(hub.uplinks) && hub.uplinks.length > 0 ? hub.uplinks[0] : null;
  elements.hqReview.innerHTML = `
    <p class="ui-eyebrow">HQ Review</p>
    <div class="relay-installer-review-grid">
      ${reviewItem("HQ Hub ID", hub.hq_hub_id)}
      ${reviewItem("Relay Hub ID", hub.relay_hub_id)}
      ${reviewItem("Hub Name", hub.name)}
      ${reviewItem("Deployment", hub.deployment)}
      ${reviewItem("Status", hub.status)}
      ${reviewItem("App URL", hub.app_url || "Not provided by HQ")}
      ${reviewItem("Primary Uplink", uplink?.hub?.name || "None")}
      ${reviewItem("Uplink Domain", uplink?.uplink_domain || uplink?.hub?.domain || "Not provided")}
    </div>
  `;
}

function renderSettingsReview(settings) {
  const driver = settings.database_driver || "sqlite";
  elements.settingsReview.innerHTML = `
    <p class="ui-eyebrow">Install Settings Review</p>
    <div class="relay-installer-review-grid">
      ${reviewItem("Admin Name", settings.admin_name || elements.settingsForm?.elements?.namedItem("admin_name")?.value || "Not set")}
      ${reviewItem("Admin Email", settings.admin_email || elements.settingsForm?.elements?.namedItem("admin_email")?.value || "Not set")}
      ${reviewItem("Database Driver", driver)}
      ${driver === "sqlite" ? reviewItem("SQLite Path", settings.sqlite_path || "Not set") : reviewItem("Database Host", settings.database_host || "Not set")}
      ${driver === "mysql" ? reviewItem("Database Port", settings.database_port || "Not set") : reviewItem("Database Name", "SQLite file")}
      ${driver === "mysql" ? reviewItem("Database Name", settings.database_name || "Not set") : reviewItem("Username", "SQLite file")}
      ${driver === "mysql" ? reviewItem("Database Username", settings.database_username || "Not set") : reviewItem("Password", "Stored for later execution")}
    </div>
  `;
}

function renderExecutionSuccess(result) {
  const releasePackagePath = result?.release?.package_path || result?.state?.install_summary?.release?.package_path || "";
  const cleanupPending = Boolean(result?.cleanup?.manifest) || Boolean(result?.state?.install_summary?.cleanup_pending);
  const cleanupAutoRun = Boolean(result?.cleanup?.auto_run) || Boolean(result?.state?.install_summary?.cleanup_auto_run);

  elements.successReview.innerHTML = `
    <p class="ui-eyebrow">Install Complete</p>
    <div class="relay-installer-review-grid">
      ${reviewItem("Relay Hub ID", result?.lock?.relay_hub_id || result?.state?.install_summary?.relay_hub_id || "")}
      ${reviewItem("App URL", result?.lock?.app_url || result?.state?.install_summary?.app_url || "")}
      ${reviewItem("Admin Email", result?.admin?.email || "Already provisioned")}
      ${reviewValueWithCopy("Generated Password", result?.admin?.password || "Shown only on first successful execution", result?.admin?.password)}
      ${reviewItem("Installed At", result?.lock?.installed_at || result?.state?.install_summary?.installed_at || "")}
      ${reviewItem("Release Package", releasePackagePath || "Current app runtime")}
      ${reviewItem("Cleanup", cleanupAutoRun ? "Applied automatically" : cleanupPending ? "Pending manual cleanup" : "No cleanup manifest pending")}
      ${reviewItem("Next Step", "Log in to Relay and rotate/store the generated admin password immediately.")}
    </div>
  `;

  bindCopyButtons(elements.successReview);
}

function reviewItem(label, value) {
  return `
    <div class="relay-installer-review-item">
      <strong>${escapeHtml(label)}</strong>
      <span>${escapeHtml(value ?? "")}</span>
    </div>
  `;
}

function reviewValueWithCopy(label, value, copyValue) {
  const canCopy = typeof copyValue === "string" && copyValue !== "";
  const icon = createIcon("actions.copy", {
    size: 14,
    decorative: true,
    className: "relay-installer-copy-icon",
  }).outerHTML;

  return `
    <div class="relay-installer-review-item">
      <strong>${escapeHtml(label)}</strong>
      <span class="relay-installer-review-value">
        <span>${escapeHtml(value ?? "")}</span>
        ${canCopy ? `<button type="button" class="ui-button ui-button-ghost relay-installer-copy-button" data-copy-value="${escapeAttribute(copyValue)}" aria-label="Copy ${escapeAttribute(label)}" title="Copy ${escapeAttribute(label)}">${icon}</button>` : ""}
      </span>
    </div>
  `;
}

function bindCopyButtons(container = document) {
  container?.querySelectorAll?.("[data-copy-value]").forEach((button) => {
    if (button.dataset.copyBound === "true") {
      return;
    }

    button.dataset.copyBound = "true";
    button.addEventListener("click", async () => {
      const value = button.getAttribute("data-copy-value") || "";
      if (!value) {
        return;
      }

      if (navigator?.clipboard?.writeText) {
        await navigator.clipboard.writeText(value);
      } else {
        copyTextFallback(value);
      }

      await state.uiAlert?.("Generated password copied to clipboard.", {
        title: "Copied",
        variant: "success",
      });
    });
  });
}

async function maybeFinalizeCompletedInstall(installResult) {
  const cleanup = installResult?.cleanup || {};
  if (cleanup?.result || cleanup?.auto_run) {
    await showCompletionNoticeDialog(installResult);
    return;
  }

  if (!cleanup?.manifest || state.autoCleanupInFlight || state.completionNoticeShown) {
    return;
  }

  await runInstallerCleanupInternal({ showCompletionNotice: true });
}

async function showCompletionNoticeDialog(installResult = state.execution?.install_result || {}) {
  if (state.completionNoticeShown) {
    return;
  }

  state.completionNoticeShown = true;

  const password = installResult?.admin?.password
    || state.execution?.admin_credentials?.password
    || "";
  const appUrl = resolveInstalledAppUrl(installResult);
  const icon = password
    ? createIcon("actions.copy", { size: 14, decorative: true, className: "relay-installer-copy-icon" }).outerHTML
    : "";

  const content = document.createElement("div");
  content.className = "relay-installer-completion-notice";
  content.innerHTML = `
    <p class="relay-installer-completion-copy">Installation is complete. Copy and store the generated admin password before opening Relay.</p>
    <div class="relay-installer-completion-password">
      <strong>Generated Admin Password</strong>
      <div class="relay-installer-completion-password-row">
        <code>${escapeHtml(password || "Shown only on first successful execution")}</code>
        ${password ? `<button type="button" class="ui-button ui-button-ghost relay-installer-copy-button" data-copy-value="${escapeAttribute(password)}" aria-label="Copy generated admin password" title="Copy generated admin password">${icon}</button>` : ""}
      </div>
    </div>
    <p class="relay-installer-completion-destination">Click <strong>OK</strong> to open the newly installed Relay server at <span>${escapeHtml(appUrl)}</span>.</p>
  `;

  bindCopyButtons(content);

  const modal = ensureCompletionNoticeModal();
  modal.update({
    content,
    actions: [{
      id: "open-relay",
      label: "OK",
      variant: "primary",
      autoFocus: true,
      onClick() {
        window.location.assign(appUrl);
        return false;
      },
    }],
  });
  modal.open();
}

function ensureCompletionNoticeModal() {
  if (state.completionNoticeModal) {
    return state.completionNoticeModal;
  }

  state.completionNoticeModal = state.createActionModal({
    title: "Relay Installed",
    size: "sm",
    content: document.createElement("div"),
    actions: [],
    closeOnBackdrop: false,
    closeOnEscape: false,
    showCloseButton: false,
  });

  return state.completionNoticeModal;
}

function resolveInstalledAppUrl(result = state.execution?.install_result || {}) {
  return result?.lock?.app_url
    || result?.state?.install_summary?.app_url
    || config?.state?.install_summary?.app_url
    || "/";
}

function copyTextFallback(value) {
  const textarea = document.createElement("textarea");
  textarea.value = value;
  textarea.setAttribute("readonly", "readonly");
  textarea.style.position = "absolute";
  textarea.style.left = "-9999px";
  document.body.appendChild(textarea);
  textarea.select();
  document.execCommand("copy");
  textarea.remove();
}

function syncDatabaseFieldVisibility() {
  const driver = elements.databaseDriver?.value || "sqlite";
  const sqlite = driver === "sqlite";
  elements.sqliteFields.hidden = !sqlite;
  elements.mysqlFields.hidden = sqlite;
}

function setFormValue(form, name, value) {
  const field = form?.elements?.namedItem(name);
  if (field && "value" in field) {
    field.value = value;
  }
}

function readValidationError(payload) {
  if (payload?.message) {
    return normalizeMessage(payload.message);
  }

  const errors = payload?.errors;
  if (errors && typeof errors === "object") {
    const first = Object.values(errors)[0];
    if (Array.isArray(first) && first[0]) {
      return normalizeMessage(first[0]);
    }
  }

  if (payload?.error) {
    return normalizeMessage(payload.error);
  }

  return null;
}

async function handleUiError(error) {
  const message = normalizeErrorMessage(error);

  if (typeof state.uiAlert === "function") {
    await state.uiAlert(message, {
      title: "Installer Error",
      variant: "danger",
    });
    return;
  }

  window.alert(message);
}

function normalizeErrorMessage(error) {
  if (error instanceof Error) {
    return normalizeMessage(error.message);
  }

  return normalizeMessage(error) || "Unexpected installer error.";
}

function normalizeMessage(value) {
  if (typeof value === "string") {
    return value.trim() || "Unexpected installer error.";
  }

  if (Array.isArray(value)) {
    const parts = value.map((item) => normalizeMessage(item)).filter(Boolean);
    return parts.join("\n");
  }

  if (value && typeof value === "object") {
    if (typeof value.message === "string") {
      return normalizeMessage(value.message);
    }

    const nested = Object.values(value)
      .map((item) => normalizeMessage(item))
      .filter(Boolean);

    if (nested.length > 0) {
      return nested[0];
    }
  }

  if (value == null) {
    return "";
  }

  return String(value);
}

function formatLabel(value) {
  return String(value || "")
    .split(/[_\s]+/)
    .map((part) => part.charAt(0).toUpperCase() + part.slice(1))
    .join(" ");
}

function escapeHtml(value) {
  return String(value ?? "")
    .replaceAll("&", "&amp;")
    .replaceAll("<", "&lt;")
    .replaceAll(">", "&gt;")
    .replaceAll('"', "&quot;")
    .replaceAll("'", "&#039;");
}

function escapeAttribute(value) {
  return escapeHtml(value).replaceAll("`", "&#096;");
}

function delay(ms) {
  return new Promise((resolve) => window.setTimeout(resolve, ms));
}
