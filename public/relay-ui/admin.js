import { uiLoader } from "/relay-ui/helper-ui-bundle.js";
import { createRelaySessionClient } from "/relay-ui/session.js";
import { postUrlEncodedJson } from "/relay-ui/request-utils.js";

async function bootRelayAdminScreen() {
  const payloadNode = document.getElementById("relay-admin-data");

  if (!payloadNode) {
    return;
  }

  const payload = JSON.parse(payloadNode.textContent || "{}");
  const sessionClient = await createRelaySessionClient(payload);
  const container = document.getElementById("relay-admin-grid");
  const searchInput = document.getElementById("relay-admin-search");
  const countNode = document.getElementById("relay-admin-count");

  if (!container) {
    return;
  }

  await uiLoader.loadMany(["ui.grid", "ui.skeleton", "ui.form.modal", "ui.dialog.alert", "ui.dialog.confirm", "ui.action.modal"]);
  const createGrid = await uiLoader.get("ui.grid");
  const createSkeleton = await uiLoader.get("ui.skeleton");
  const createFormModal = await uiLoader.get("ui.form.modal");
  const uiAlert = await uiLoader.get("ui.dialog.alert");
  const uiConfirm = await uiLoader.get("ui.dialog.confirm");
  const createActionModal = await uiLoader.get("ui.action.modal");

  bootClientModal(createFormModal, sessionClient, payload, uiAlert);
  bootUserModal(createFormModal, sessionClient, payload);

  createSkeleton(container, { rows: 8 }, {
    variant: "grid",
    columns: 6,
    className: "relay-grid-skeleton",
  });

  const response = await sessionClient.fetch(payload.dataUrl, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  if (!response.ok) {
    throw new Error(`Admin section request failed with status ${response.status}.`);
  }

  const data = await response.json();
  let rows = Array.isArray(data.rows) ? data.rows : [];
  const columns = normalizeColumns(Array.isArray(data.columns) ? data.columns : []);
  let filteredRows = rows;

  const applySectionRows = (nextRows, preserveSearch = true) => {
    rows = Array.isArray(nextRows) ? nextRows : [];
    const term = preserveSearch ? String(searchInput?.value || "").trim().toLowerCase() : "";
    filteredRows = term === ""
      ? rows
      : rows.filter((row) => Object.values(row).some((value) => String(value ?? "").toLowerCase().includes(term)));
    grid.update(filteredRows, {
      emptyText: `No ${String(data.sectionTitle || payload.sectionTitle || "records").toLowerCase()} available.`,
    });
    syncGridEmptyState(container, filteredRows);
    updateCount(countNode, filteredRows);
  };

  const refreshSectionRows = async () => {
    const nextResponse = await sessionClient.fetch(payload.dataUrl, {
      headers: {
        Accept: "application/json",
        "X-Requested-With": "XMLHttpRequest",
      },
      credentials: "same-origin",
    });

    if (!nextResponse.ok) {
      throw new Error(`Admin section refresh failed with status ${nextResponse.status}.`);
    }

    const nextData = await nextResponse.json();
    applySectionRows(Array.isArray(nextData.rows) ? nextData.rows : []);
    return rows;
  };

  const grid = createGrid(container, filteredRows, {
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
    columns,
    emptyText: `No ${String(data.sectionTitle || payload.sectionTitle || "records").toLowerCase()} available.`,
    async onRowClick(row) {
      if ((data.sectionKey || payload.sectionKey) === "users") {
        await openUserActionModal(row, payload, sessionClient, createActionModal, uiConfirm, uiAlert, refreshSectionRows);
        return;
      }

      const href = detailHref(data.sectionKey || payload.sectionKey, row);

      if (href) {
        window.location.href = href;
      }
    },
  });

  syncGridEmptyState(container, filteredRows);
  updateCount(countNode, filteredRows);

  searchInput?.addEventListener("input", () => {
    applySectionRows(rows);
  });
}

function syncGridEmptyState(container, rows) {
  const gridRoot = container?.querySelector(".relay-admin-grid-ui.ui-grid");

  if (!gridRoot) {
    return;
  }

  gridRoot.classList.toggle("is-empty", !Array.isArray(rows) || rows.length === 0);
}

function normalizeColumns(columns) {
  return columns.map((column) => ({
    ...column,
    width: defaultColumnWidth(column),
    renderCell: ({ row }) => renderCell(column.key, row[column.key], row),
  }));
}

function defaultColumnWidth(column) {
  const key = String(column.key || "");
  if (["relay_id", "session_id", "last_error", "delivery_statuses"].includes(key)) {
    return 220;
  }

  if (["message_type", "message_type_pattern", "attachment_name", "targets"].includes(key)) {
    return 180;
  }

  if (["status", "receipt_status", "transfer_status", "role"].includes(key)) {
    return 140;
  }

  return 150;
}

function renderCell(key, value, row) {
  const normalizedKey = String(key || "");

  if (["status", "receipt_status", "transfer_status"].includes(normalizedKey)) {
    return statusBadge(row.kind ? `${row.kind}:${value}` : value);
  }

  if (normalizedKey === "role") {
    const badge = document.createElement("span");
    badge.className = "relay-status-badge is-info";
    badge.textContent = String(value || "unknown");
    return badge;
  }

  if (typeof value === "string" && value.length > 48) {
    return stackedCell(value.slice(0, 48), value.slice(48));
  }

  const text = document.createElement("span");
  text.textContent = value == null ? "" : String(value);
  return text;
}

function statusBadge(status) {
  const normalized = String(status || "").toLowerCase();
  const badge = document.createElement("span");
  badge.className = `relay-status-badge ${statusTone(normalized)}`;
  badge.textContent = normalized.includes(":") ? normalized.split(":")[1] : normalized || "unknown";
  return badge;
}

function statusTone(status) {
  if (status.includes("delivered") || status.includes("completed") || status.includes("processed") || status.includes("succeeded")) {
    return "is-ok";
  }

  if (status.includes("failed") || status.includes("sending") || status.includes("uploading") || status.includes("duplicate")) {
    return "is-warn";
  }

  if (status.includes("dead") || status.includes("rejected")) {
    return "is-danger";
  }

  return "is-info";
}

function detailHref(sectionKey, row) {
  switch (sectionKey) {
    case "outbox":
    case "inbox":
      return row.id ? `/relay/messages/${row.id}` : null;
    case "deliveries":
      return row.id ? `/relay/delivery/${row.id}` : null;
    case "uploads":
      return row.id ? `/relay/upload/${row.id}` : null;
    case "clients":
      return row.id ? `/relay/client/${row.id}` : null;
    case "users":
      return null;
    case "dead-letters":
      if (row.kind === "handler_dispatch" && row.id) {
        return `/relay/handler-dispatch/${row.id}`;
      }
      if (row.kind === "delivery" && row.id) {
        return `/relay/delivery/${row.id}`;
      }
      return null;
    default:
      return null;
  }
}

function stackedCell(primary, secondary) {
  const wrapper = document.createElement("div");
  wrapper.className = "relay-grid-cell";

  const strong = document.createElement("strong");
  strong.textContent = primary == null ? "" : String(primary);

  const small = document.createElement("small");
  small.textContent = secondary == null ? "" : String(secondary);

  wrapper.append(strong, small);
  return wrapper;
}

function updateCount(node, rows) {
  if (!node) {
    return;
  }

  node.textContent = `${rows.length.toLocaleString()} row(s)`;
}

function bootClientModal(createFormModal, sessionClient, payload, uiAlert) {
  if (payload.sectionKey !== "clients") {
    return;
  }

  const trigger = document.getElementById("relay-admin-new-client");

  if (!(trigger instanceof HTMLElement)) {
    return;
  }

  trigger.addEventListener("click", () => {
    openClientModal(createFormModal, sessionClient, payload, uiAlert);
  });

  if (payload.openClientModal) {
    openClientModal(createFormModal, sessionClient, payload, uiAlert);
  }
}

function bootUserModal(createFormModal, sessionClient, payload) {
  if (payload.sectionKey !== "users") {
    return;
  }

  const trigger = document.getElementById("relay-admin-new-user");

  if (!(trigger instanceof HTMLElement)) {
    return;
  }

  trigger.addEventListener("click", () => {
    openUserModal(createFormModal, sessionClient, payload);
  });

  if (payload.openUserModal) {
    openUserModal(createFormModal, sessionClient, payload);
  }
}

function openClientModal(createFormModal, sessionClient, payload, uiAlert) {
  const modal = createFormModal({
    title: "New Client",
    size: "md",
    submitLabel: "Create Client Token",
    busyMessage: "Creating client...",
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
          input: "text",
          name: "system_code",
          label: "System Code",
          placeholder: "sitrep.app",
          required: true,
        },
      ],
      [
        {
          type: "textarea",
          name: "description",
          label: "Description",
          placeholder: "What this client is allowed to do",
        },
      ],
    ],
    initialValues: payload.oldInput || {},
    async onSubmit(values, ctx) {
      ctx.clearErrors();
      ctx.clearFormError();

      try {
        const { response, result } = await postUrlEncodedJson(sessionClient, "/relay/clients", {
          name: values.name || "",
          system_code: values.system_code || "",
          description: values.description || "",
        });

        if (!response.ok || result?.success === false) {
          const mapped = ctx.applyApiErrors(result || {});
          if (!mapped.formError) {
            ctx.setFormError(result?.status_message || result?.error?.message || "Unable to create client.");
          }
          return false;
        }

        if (result.generated_api_key) {
          await uiAlert(`${result.status_message}\n\nGenerated API Key:\n${result.generated_api_key}`, {
            title: "Client Created",
            size: "md",
          });
        }

        if (result.redirect_url) {
          window.location.href = result.redirect_url;
        }

        return true;
      } catch (error) {
        ctx.setFormError(error?.message || "Unable to create client.");
        return false;
      }
    },
  });

  if (payload.validationError) {
    modal.setFormError(String(payload.validationError));
  }

  modal.open();
}

function openUserModal(createFormModal, sessionClient, payload) {
  const modal = createFormModal({
    title: "New User",
    size: "md",
    submitLabel: "Create Relay User",
    busyMessage: "Creating user...",
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
          input: "email",
          name: "email",
          label: "Email",
          required: true,
        },
      ],
      [
        {
          type: "select",
          name: "role",
          label: "Role",
          required: true,
          options: [
            { label: "operator", value: "operator" },
            { label: "admin", value: "admin" },
          ],
        },
        {
          type: "input",
          input: "password",
          name: "password",
          label: "Password",
          required: true,
        },
      ],
    ],
    initialValues: payload.userOldInput || { role: "operator" },
    async onSubmit(values, ctx) {
      ctx.clearErrors();
      ctx.clearFormError();

      try {
        const { response, result } = await postUrlEncodedJson(sessionClient, "/relay/users", {
          name: values.name || "",
          email: values.email || "",
          role: values.role || "operator",
          password: values.password || "",
        });

        if (!response.ok || result?.success === false) {
          const mapped = ctx.applyApiErrors(result || {});
          if (!mapped.formError) {
            ctx.setFormError(result?.status_message || result?.error?.message || "Unable to create relay user.");
          }
          return false;
        }

        if (result.redirect_url) {
          window.location.href = result.redirect_url;
        }

        return true;
      } catch (error) {
        ctx.setFormError(error?.message || "Unable to create relay user.");
        return false;
      }
    },
  });

  if (payload.userValidationError) {
    modal.setFormError(String(payload.userValidationError));
  }

  modal.open();
}

async function openUserActionModal(row, payload, sessionClient, createActionModal, uiConfirm, uiAlert, refreshSectionRows) {
  let currentRow = { ...row };
  let modal = null;

  const renderDetails = () => {
    const wrapper = document.createElement("div");
    wrapper.className = "relay-related-group ui-surface";

    [
      ["Name", currentRow.name],
      ["Email", currentRow.email],
      ["Role", currentRow.role],
      ["Status", currentRow.status],
      ["Last Login", currentRow.last_login_at],
      ["Updated", currentRow.updated_at],
    ].forEach(([label, value]) => {
      const item = document.createElement("div");
      item.className = "relay-grid-cell";

      const strong = document.createElement("strong");
      strong.textContent = String(label);

      const small = document.createElement("small");
      small.textContent = value == null ? "" : String(value);

      item.append(strong, small);
      wrapper.append(item);
    });

    return wrapper;
  };

  const buildActions = () => {
    const actions = [
      {
        id: "close",
        label: "Close",
        variant: "ghost",
      },
    ];

    if (!payload.isRelayAdmin) {
      return actions;
    }

    if (currentRow.status === "active") {
      actions.unshift({
        id: "toggle",
        label: "Deactivate User",
        variant: "danger",
        autoFocus: true,
        async onClick() {
          const confirmed = await uiConfirm("This user will lose access to the relay operator console until reactivated.", {
            title: "Deactivate User",
            confirmText: "Deactivate User",
            confirmVariant: "danger",
            variant: "warning",
            size: "md",
          });

          if (!confirmed) {
            return false;
          }

          const { response, result } = await postUrlEncodedJson(sessionClient, currentRow.toggle_active_url);

          if (!response.ok || result?.success === false) {
            await uiAlert(String(result?.status_message || result?.error?.message || "Unable to deactivate user."), {
              title: "Action Failed",
              size: "md",
            });
            return false;
          }

          const refreshedRows = await refreshSectionRows();
          currentRow = refreshedRows.find((candidate) => String(candidate.id) === String(currentRow.id)) || currentRow;
          modal.update({
            title: currentRow.name || "User Actions",
            content: renderDetails(),
            actions: buildActions(),
          });
          return false;
        },
      });
    } else {
      actions.unshift({
        id: "toggle",
        label: "Activate User",
        variant: "primary",
        autoFocus: true,
        async onClick() {
          const confirmed = await uiConfirm("This user will regain access to the relay operator console.", {
            title: "Activate User",
            confirmText: "Activate User",
            confirmVariant: "primary",
            variant: "info",
            size: "md",
          });

          if (!confirmed) {
            return false;
          }

          const { response, result } = await postUrlEncodedJson(sessionClient, currentRow.toggle_active_url);

          if (!response.ok || result?.success === false) {
            await uiAlert(String(result?.status_message || result?.error?.message || "Unable to activate user."), {
              title: "Action Failed",
              size: "md",
            });
            return false;
          }

          const refreshedRows = await refreshSectionRows();
          currentRow = refreshedRows.find((candidate) => String(candidate.id) === String(currentRow.id)) || currentRow;
          modal.update({
            title: currentRow.name || "User Actions",
            content: renderDetails(),
            actions: buildActions(),
          });
          return false;
        },
      });
    }

    actions.splice(1, 0, {
      id: "reset-password",
      label: "Reset Password",
      variant: "danger",
      async onClick() {
        const confirmed = await uiConfirm("This will generate a new random password and invalidate the user's current password.", {
          title: "Reset Password",
          confirmText: "Reset Password",
          confirmVariant: "danger",
          variant: "warning",
          size: "md",
        });

        if (!confirmed) {
          return false;
        }

        const { response, result } = await postUrlEncodedJson(sessionClient, currentRow.reset_password_url);

        if (!response.ok || result?.success === false) {
          await uiAlert(String(result?.status_message || result?.error?.message || "Unable to reset password."), {
            title: "Action Failed",
            size: "md",
          });
          return false;
        }

        await refreshSectionRows();
        await uiAlert(`Relay user password reset.\n\nNew Password:\n${result.generated_password || ""}`, {
          title: "Password Reset",
          size: "md",
          variant: "info",
        });
        return false;
      },
    });

    return actions;
  };

  modal = createActionModal({
    title: currentRow.name || "User Actions",
    size: "md",
    content: renderDetails(),
    actions: buildActions(),
  });

  modal.open();
}

bootRelayAdminScreen().catch((error) => {
  console.error("Failed to boot relay admin screen.", error);
});
