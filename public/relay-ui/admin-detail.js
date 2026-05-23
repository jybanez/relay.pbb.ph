import { uiLoader } from "/relay-ui/helper-ui-bundle.js";
import { createRelaySessionClient } from "/relay-ui/session.js";
import { postUrlEncodedJson } from "/relay-ui/request-utils.js";

async function bootRelayAdminDetail() {
  const configNode = document.getElementById("relay-admin-detail-config");
  const inspectorContainer = document.getElementById("relay-admin-inspector");

  if (!configNode) {
    return;
  }

  const config = JSON.parse(configNode.textContent || "{}");
  const sessionClient = await createRelaySessionClient(config);

  const needsInspector = config.detailMode !== "client";

  await uiLoader.loadMany(["ui.skeleton", "ui.grid", "ui.dialog.alert", "ui.dialog.confirm", ...(needsInspector ? ["ui.data.inspector"] : []), ...(config.detailMode === "client" ? ["ui.form.modal"] : [])]);
  const createSkeleton = await uiLoader.get("ui.skeleton");
  const createGrid = await uiLoader.get("ui.grid");
  const createDataInspector = needsInspector ? await uiLoader.get("ui.data.inspector") : null;
  const uiAlert = await uiLoader.get("ui.dialog.alert");
  const uiConfirm = await uiLoader.get("ui.dialog.confirm");
  const createFormModal = config.detailMode === "client" ? await uiLoader.get("ui.form.modal") : null;

  mountSkeletons(createSkeleton);
  const payload = await fetchDetailPayload(config.dataUrl, sessionClient);

  updateHeading(payload);

  if (config.detailMode === "client") {
    const refreshClientDetail = async (flash = null) => {
      const nextPayload = await fetchDetailPayload(config.dataUrl, sessionClient);
      updateHeading(nextPayload);
      await showClientFeedback(uiAlert, flash);
      mountClientToolbar(nextPayload.summary || [], nextPayload.actions || [], config, uiConfirm, refreshClientDetail, uiAlert, sessionClient);
      mountHandlerManager(nextPayload.extra || null, config, createGrid, uiConfirm, createFormModal, sessionClient, refreshClientDetail, uiAlert);
    };

    await showClientFeedback(uiAlert, {
      statusMessage: config.initialStatusMessage || "",
      generatedApiKey: config.initialGeneratedApiKey || "",
    });
    mountClientToolbar(payload.summary || [], payload.actions || [], config, uiConfirm, refreshClientDetail, uiAlert, sessionClient);
    mountHandlerManager(payload.extra || null, config, createGrid, uiConfirm, createFormModal, sessionClient, refreshClientDetail, uiAlert);
    return;
  }

  const refreshGenericDetail = async (flash = null) => {
    const nextPayload = await fetchDetailPayload(config.dataUrl, sessionClient);
    updateHeading(nextPayload);
    mountSummary(nextPayload.summary || []);
    mountActions(nextPayload.actions || [], config, sessionClient, uiConfirm, refreshGenericDetail, uiAlert);
    mountRelated(nextPayload.relatedRecords || {});

    if (createDataInspector && inspectorContainer) {
      inspectorContainer.innerHTML = "";
      createDataInspector(inspectorContainer, nextPayload.inspector || {}, {
        chrome: false,
        expandDepth: 2,
      });
    }

    if (flash?.statusMessage) {
      await uiAlert(String(flash.statusMessage), {
        title: "Notice",
        size: "md",
      });
    }
  };

  await refreshGenericDetail();
}

async function fetchDetailPayload(dataUrl, sessionClient) {
  const response = await sessionClient.fetch(dataUrl, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  if (!response.ok) {
    throw new Error(`Detail data request failed with status ${response.status}.`);
  }

  return response.json();
}

function mountSkeletons(createSkeleton) {
  const summary = document.getElementById("relay-detail-summary");
  const actions = document.getElementById("relay-detail-actions");
  const related = document.getElementById("relay-detail-related");
  const inspector = document.getElementById("relay-admin-inspector");
  const handlersGrid = document.getElementById("relay-client-handlers-grid");
  const clientSummary = document.getElementById("relay-client-summary-inline");
  const clientActions = document.getElementById("relay-client-header-actions");
  const toolbarSearchLoading = document.getElementById("relay-client-toolbar-search-loading");
  const toolbarActionsLoading = document.getElementById("relay-client-toolbar-actions-loading");

  if (summary) {
    createSkeleton(summary, { rows: 4 }, { variant: "grid", columns: 2, className: "relay-detail-skeleton" });
  }

  if (actions) {
    createSkeleton(actions, { lines: 2 }, { variant: "lines", className: "relay-detail-skeleton" });
  }

  if (related) {
    createSkeleton(related, { lines: 3 }, { variant: "lines", className: "relay-detail-skeleton" });
  }

  if (inspector) {
    createSkeleton(inspector, { lines: 10 }, { variant: "lines", className: "relay-detail-skeleton" });
  }

  if (handlersGrid) {
    createSkeleton(handlersGrid, { lines: 8 }, { variant: "lines", className: "relay-grid-skeleton" });
  }

  if (clientSummary) {
    mountClientSummarySkeleton(createSkeleton, clientSummary, 5);
  }

  if (clientActions) {
    createSkeleton(clientActions, { lines: 2 }, { variant: "lines", className: "relay-client-actions-skeleton" });
  }

  if (toolbarSearchLoading) {
    createSkeleton(toolbarSearchLoading, { lines: 1 }, { variant: "lines", className: "relay-client-toolbar-search-skeleton" });
  }

  if (toolbarActionsLoading) {
    createSkeleton(toolbarActionsLoading, { lines: 1 }, { variant: "lines", className: "relay-client-toolbar-actions-skeleton" });
  }
}

function updateHeading(payload) {
  const titleNode = document.getElementById("relay-detail-title");
  const subtitleNode = document.getElementById("relay-detail-subtitle");
  const summaryTitleNode = document.getElementById("relay-detail-summary-title");

  if (titleNode) {
    titleNode.textContent = payload.title || "Detail";
  }

  if (summaryTitleNode) {
    summaryTitleNode.textContent = payload.title || "Detail";
  }

  if (subtitleNode) {
    subtitleNode.textContent = payload.subtitle || "";
  }
}

function mountSummary(summaryItems) {
  const container = document.getElementById("relay-detail-summary");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  summaryItems.forEach((item) => {
    const card = document.createElement("article");
    card.className = "relay-detail-card ui-surface";

    const label = document.createElement("p");
    label.className = "ui-eyebrow";
    label.textContent = item.label || "";

    const value = document.createElement("strong");
    value.textContent = item.value == null ? "" : String(item.value);

    card.append(label, value);
    container.append(card);
  });
}

function mountActions(actions, config, sessionClient, uiConfirm, refreshDetail, uiAlert) {
  const container = document.getElementById("relay-detail-actions");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  actions
    .filter((action) => action.visibleWhen !== false)
    .filter((action) => !action.adminOnly || Boolean(config.isRelayAdmin))
    .forEach((action) => {
      if (action.fetch) {
        const button = document.createElement("button");
        button.type = "button";
        button.className = buttonClass(action.tone || "ghost");
        button.textContent = action.label || "Action";
        button.addEventListener("click", async (event) => {
          event.preventDefault();
          await confirmDetailAction(uiConfirm, action, sessionClient, refreshDetail, uiAlert);
        });
        container.append(button);
        return;
      }

      const form = document.createElement("form");
      form.method = "POST";
      form.action = action.action;

      const token = document.createElement("input");
      token.type = "hidden";
      token.name = "_token";
      token.value = sessionClient.getCsrfToken();
      form.append(token);

      if ((action.method || "POST").toUpperCase() !== "POST") {
        const method = document.createElement("input");
        method.type = "hidden";
        method.name = "_method";
        method.value = action.method;
        form.append(method);
      }

      (action.fields || []).forEach((field) => {
        const input = document.createElement("input");
        input.type = "hidden";
        input.name = field.name;
        input.value = field.value;
        form.append(input);
      });

      const button = document.createElement("button");
      button.type = "submit";
      button.className = buttonClass(action.tone || "ghost");
      button.textContent = action.label || "Action";
      form.append(button);

      container.append(form);
    });
}

async function confirmDetailAction(uiConfirm, action, sessionClient, refreshDetail, uiAlert) {
  const confirmed = await uiConfirm(action.confirm?.body || "Continue with this action?", {
    title: action.confirm?.title || action.label || "Confirm Action",
    confirmText: action.confirm?.confirmLabel || action.label || "Confirm",
    confirmVariant: action.tone === "danger" ? "danger" : "primary",
    variant: action.tone === "danger" ? "warning" : "info",
    size: "md",
  });

  if (!confirmed) {
    return;
  }

  const result = await postDetailAction(action, sessionClient, uiAlert);
  await refreshDetail({
    statusMessage: result.status_message || "",
  });
}

async function postDetailAction(action, sessionClient, uiAlert) {
  const values = Object.fromEntries((action.fields || []).map((field) => [field.name, field.value]));
  const { response, result } = await postUrlEncodedJson(sessionClient, action.action, values, {
    method: action.method || "POST",
  });

  if (!response.ok || result?.success === false) {
    await uiAlert(String(result?.status_message || result?.error?.message || `Action failed with status ${response.status}.`), {
      title: "Action Failed",
      size: "md",
    });
    throw new Error(result?.status_message || `Detail action failed with status ${response.status}.`);
  }

  return result;
}

function mountRelated(groups) {
  const container = document.getElementById("relay-detail-related");

  if (!container) {
    return;
  }

  container.innerHTML = "";

  Object.entries(groups).forEach(([label, items]) => {
    if (!Array.isArray(items) || items.length === 0) {
      return;
    }

    const section = document.createElement("section");
    section.className = "relay-related-group ui-surface";

    const title = document.createElement("p");
    title.className = "ui-eyebrow";
    title.textContent = label;

    const links = document.createElement("div");
    links.className = "relay-related-links";

    items.forEach((item) => {
      if (item.href) {
        const link = document.createElement("a");
        link.href = item.href;
        link.className = "ui-button ui-button-ghost";
        link.textContent = item.label || "";
        links.append(link);
        return;
      }

      const badge = document.createElement("span");
      badge.className = "ui-badge";
      badge.textContent = item.label || "";
      links.append(badge);
    });

    section.append(title, links);
    container.append(section);
  });
}

function buttonClass(tone) {
  if (tone === "primary") {
    return "ui-button ui-button-primary";
  }

  if (tone === "danger") {
    return "ui-button relay-button-danger";
  }

  return "ui-button ui-button-ghost";
}

function mountHandlerManager(extra, config, createGrid, uiConfirm, createFormModal, sessionClient, refreshClientDetail, uiAlert) {
  const searchNode = document.getElementById("relay-client-handlers-search");
  const countNode = document.getElementById("relay-client-handlers-count");
  const newButton = document.getElementById("relay-client-handlers-new");
  const gridContainer = document.getElementById("relay-client-handlers-grid");

  const manager = extra?.rows ? extra : null;

  if (!gridContainer || !manager) {
    return;
  }

  let rows = Array.isArray(manager.rows) ? manager.rows : [];
  let filteredRows = rows;

  const grid = createGrid(gridContainer, filteredRows, {
    className: "relay-admin-grid-ui",
    chrome: false,
    enableSearch: false,
    enableSort: false,
    enablePagination: false,
    enableColumnResize: true,
    enableVirtualization: true,
    virtualRowHeight: 58,
    virtualOverscan: 12,
    virtualThreshold: 20,
    wrapCellContent: true,
    rowKey: "id",
    columns: handlerColumns(config, uiConfirm, createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, () => rows, (nextRows) => {
      rows = nextRows;
      filteredRows = applyHandlerSearch(rows, searchNode?.value || "");
      grid.update(filteredRows, { emptyText: "No handlers available." });
      syncGridEmptyState(gridContainer, filteredRows);
      updateHandlerCount(countNode, filteredRows);
    }),
    emptyText: "No handlers available.",
  });

  syncGridEmptyState(gridContainer, filteredRows);
  updateHandlerCount(countNode, filteredRows);

  if (manager.canManage && newButton) {
    newButton.hidden = false;
    newButton.addEventListener("click", () => {
      openHandlerModal(createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, null);
    });
  }

  searchNode?.addEventListener("input", () => {
    filteredRows = applyHandlerSearch(rows, searchNode.value || "");
    grid.update(filteredRows, { emptyText: "No handlers available." });
    syncGridEmptyState(gridContainer, filteredRows);
    updateHandlerCount(countNode, filteredRows);
  });
}

function mountClientToolbar(summary, actions, config, uiConfirm, refreshClientDetail, uiAlert, sessionClient) {
  const summaryContainer = document.getElementById("relay-client-summary-inline");
  const actionsContainer = document.getElementById("relay-client-header-actions");
  const searchInput = document.getElementById("relay-client-handlers-search");
  const countBadge = document.getElementById("relay-client-handlers-count");
  const toolbarSearchLoading = document.getElementById("relay-client-toolbar-search-loading");
  const toolbarActionsLoading = document.getElementById("relay-client-toolbar-actions-loading");

  if (summaryContainer) {
    summaryContainer.innerHTML = "";

    summary.forEach((item) => {
      const card = document.createElement("article");
      card.className = "relay-client-summary-item ui-surface";

      const label = document.createElement("p");
      label.className = "ui-eyebrow";
      label.textContent = item.label || "";

      const value = document.createElement("strong");
      value.textContent = item.value == null ? "" : String(item.value);

      card.append(label, value);
      summaryContainer.append(card);
    });
  }

  if (actionsContainer) {
    actionsContainer.innerHTML = "";

    actions
      .filter((action) => action.visibleWhen !== false)
      .filter((action) => !action.adminOnly || Boolean(config.isRelayAdmin))
      .forEach((action) => {
        const button = document.createElement("button");
        button.type = "submit";
        button.className = buttonClass(action.tone || "ghost");
        button.textContent = action.label || "Action";
        button.addEventListener("click", async (event) => {
          event.preventDefault();
          await confirmClientAction(uiConfirm, action, refreshClientDetail, uiAlert, sessionClient);
        });
        actionsContainer.append(button);
      });
  }

  if (searchInput) {
    searchInput.hidden = false;
  }

  if (countBadge) {
    countBadge.hidden = false;
  }

  if (toolbarSearchLoading) {
    toolbarSearchLoading.remove();
  }

  if (toolbarActionsLoading) {
    toolbarActionsLoading.remove();
  }
}

async function confirmClientAction(uiConfirm, action, refreshClientDetail, uiAlert, sessionClient) {
  const confirmed = await uiConfirm(action.confirm?.body || "Continue with this action?", {
    title: action.confirm?.title || action.label || "Confirm Action",
    confirmText: action.confirm?.confirmLabel || action.label || "Confirm",
    confirmVariant: action.tone === "danger" ? "danger" : "primary",
    variant: action.tone === "danger" ? "warning" : "info",
    size: "md",
  });

  if (!confirmed) {
    return;
  }

  const result = await postClientAction(action, sessionClient);
  await refreshClientDetail({
    statusMessage: result.status_message || "",
    generatedApiKey: result.generated_api_key || "",
  });
}

async function postClientAction(action, sessionClient) {
  const values = Object.fromEntries((action.fields || []).map((field) => [field.name, field.value]));
  const { response, result } = await postUrlEncodedJson(sessionClient, action.action, values, {
    method: action.method || "POST",
  });

  if (!response.ok) {
    throw new Error(`Client action failed with status ${response.status}.`);
  }

  return result;
}

async function showClientFeedback(uiAlert, flash) {
  if (!uiAlert) {
    return;
  }

  const statusMessage = String(flash?.statusMessage || "").trim();
  const generatedApiKey = String(flash?.generatedApiKey || "").trim();

  if (!statusMessage && !generatedApiKey) {
    return;
  }

  let message = statusMessage;

  if (generatedApiKey) {
    message = statusMessage
      ? `${statusMessage}\n\nGenerated API Key:\n${generatedApiKey}`
      : `Generated API Key:\n${generatedApiKey}`;
  }

  await uiAlert(message, {
    title: generatedApiKey ? "Client Updated" : "Notice",
    size: "md",
  });
}

function mountClientSummarySkeleton(createSkeleton, container, count) {
  container.innerHTML = "";

  for (let index = 0; index < count; index += 1) {
    const card = document.createElement("div");
    card.className = "relay-client-summary-item ui-surface relay-client-summary-item-skeleton";
    container.append(card);
    createSkeleton(card, { lines: 2 }, { variant: "lines", className: "relay-client-summary-card-skeleton" });
  }
}

function handlerColumns(config, uiConfirm, createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, getRows, onRowsUpdated) {
  return [
    { key: "name", label: "Name", width: "180px", renderCell: ({ row }) => row.name || "" },
    { key: "endpoint_url", label: "Endpoint", width: "280px", renderCell: ({ row }) => row.endpoint_url || "" },
    { key: "message_type_pattern", label: "Message Type", width: "160px" },
    { key: "source_system", label: "Source System", width: "140px" },
    { key: "source_hub_id", label: "Source Hub", width: "140px" },
    {
      key: "status",
      label: "Status",
      width: "110px",
      renderCell: ({ row }) => statusBadge(row.status),
    },
    { key: "last_dispatched_at", label: "Last Dispatch", width: "180px" },
    { key: "updated_at", label: "Updated", width: "140px" },
    {
      key: "actions",
      label: "Actions",
      width: "210px",
      resizable: false,
      renderCell: ({ row }) => actionCell(uiConfirm, createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, row),
    },
  ];
}

function actionCell(uiConfirm, createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, row) {
  const wrap = document.createElement("div");
  wrap.className = "relay-grid-actions";

  const edit = document.createElement("button");
  edit.type = "button";
  edit.className = "ui-button ui-button-ghost";
  edit.textContent = "Edit";
  edit.disabled = !manager.canManage;
  edit.addEventListener("click", (event) => {
    event.preventDefault();
    event.stopPropagation();
    openHandlerModal(createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, row);
  });

  const toggleButton = document.createElement("button");
  toggleButton.type = "button";
  toggleButton.className = row.is_active ? "ui-button ui-button-ghost" : "ui-button ui-button-primary";
  toggleButton.textContent = row.is_active ? "Deactivate" : "Activate";
  toggleButton.disabled = !manager.canManage;
  toggleButton.addEventListener("click", async (event) => {
    event.preventDefault();
    event.stopPropagation();

    const confirmed = await uiConfirm(
      row.is_active
        ? "This handler will stop receiving matched inbound messages until reactivated."
        : "This handler will resume receiving matched inbound messages.",
      {
        title: row.is_active ? "Deactivate Handler" : "Activate Handler",
        confirmText: row.is_active ? "Deactivate Handler" : "Activate Handler",
        confirmVariant: row.is_active ? "danger" : "primary",
        variant: row.is_active ? "warning" : "info",
        size: "md",
      },
    );

    if (!confirmed) {
      return;
    }

    const { response, result } = await postUrlEncodedJson(sessionClient, row.toggle_active_url);

    if (!response.ok || result?.success === false) {
      await uiAlert(String(result?.status_message || result?.error?.message || "Unable to update handler."), {
        title: "Action Failed",
        size: "md",
      });
      return;
    }

    await refreshClientDetail({
      statusMessage: result.status_message || "",
    });
  });

  wrap.append(edit, toggleButton);

  return wrap;
}

function openHandlerModal(createFormModal, manager, sessionClient, refreshClientDetail, uiAlert, row) {
  const modal = createFormModal({
    title: row ? "Edit Handler" : "New Handler",
    size: "lg",
    submitLabel: row ? "Save Handler" : "Create Handler",
    busyMessage: row ? "Saving handler..." : "Creating handler...",
    rows: [
      [
        {
          type: "input",
          input: "text",
          name: "name",
          label: "Name",
          required: true,
        },
        {
          type: "input",
          input: "url",
          name: "endpoint_url",
          label: "Endpoint URL",
          required: true,
        },
      ],
      [
        {
          type: "input",
          input: "text",
          name: "message_type_pattern",
          label: "Message Type Pattern",
        },
        {
          type: "input",
          input: "text",
          name: "source_system",
          label: "Source System",
        },
      ],
      [
        {
          type: "input",
          input: "text",
          name: "source_hub_id",
          label: "Source Hub",
        },
        {
          type: "input",
          input: "text",
          name: "auth_token",
          label: "Auth Token",
          placeholder: row?.auth_token_set ? "Stored token" : "",
        },
      ],
      [
        {
          type: "checkbox",
          name: "is_active",
          label: "Active",
        },
      ],
    ],
    initialValues: {
      name: row?.name || "",
      endpoint_url: row?.endpoint_url || "",
      message_type_pattern: row?.message_type_pattern || "*",
      source_system: normalizeAnyField(row?.source_system),
      source_hub_id: normalizeAnyField(row?.source_hub_id),
      auth_token: row?.auth_token || "",
      is_active: row ? Boolean(row.is_active) : true,
    },
    async onSubmit(values, ctx) {
      ctx.clearErrors();
      ctx.clearFormError();

      const { response, result } = await postUrlEncodedJson(sessionClient, row ? row.update_url : manager.createUrl, {
        name: values.name || "",
        endpoint_url: values.endpoint_url || "",
        message_type_pattern: values.message_type_pattern || "",
        source_system: values.source_system || "",
        source_hub_id: values.source_hub_id || "",
        auth_token: values.auth_token || "",
        is_active: values.is_active ? "1" : "0",
      });

      if (!response.ok || result?.success === false) {
        const mapped = ctx.applyApiErrors(result || {});
        if (!mapped.formError) {
          ctx.setFormError(result?.status_message || result?.error?.message || "Unable to save handler.");
        }
        return false;
      }

      await refreshClientDetail({
        statusMessage: result.status_message || (row ? "Handler updated." : "Handler created."),
      });

      return true;
    },
  });

  modal.open();
}

function applyHandlerSearch(rows, term) {
  const needle = String(term || "").trim().toLowerCase();

  if (needle === "") {
    return rows;
  }

  return rows.filter((row) => Object.values(row).some((value) => String(value ?? "").toLowerCase().includes(needle)));
}

function updateHandlerCount(node, rows) {
  if (!node) {
    return;
  }

  const count = Array.isArray(rows) ? rows.length : 0;
  node.textContent = `${count} handler(s)`;
}

function syncGridEmptyState(container, rows) {
  const gridRoot = container?.querySelector(".relay-admin-grid-ui.ui-grid");

  if (!gridRoot) {
    return;
  }

  gridRoot.classList.toggle("is-empty", !Array.isArray(rows) || rows.length === 0);
}

function statusBadge(value) {
  const badge = document.createElement("span");
  badge.className = "ui-badge";
  badge.textContent = String(value || "").toUpperCase();
  return badge;
}

function normalizeAnyField(value) {
  return value === "Any" ? "" : value || "";
}

bootRelayAdminDetail().catch((error) => {
  console.error("Failed to boot relay admin detail screen.", error);
});
