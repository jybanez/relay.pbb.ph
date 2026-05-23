import { uiLoader } from "/relay-ui/helper-ui-bundle.js";
import { createRelaySessionClient } from "/relay-ui/session.js";

async function bootRelayDashboard() {
  const configNode = document.getElementById("relay-dashboard-config");

  if (!configNode) {
    return;
  }

  document.documentElement.setAttribute("data-theme", "dark");

  const config = JSON.parse(configNode.textContent || "{}");
  const sessionClient = await createRelaySessionClient(config);

  await uiLoader.loadMany(["ui.grid", "ui.progress", "ui.skeleton"]);

  const createGrid = await uiLoader.get("ui.grid");
  const createProgress = await uiLoader.get("ui.progress");
  const createSkeleton = await uiLoader.get("ui.skeleton");

  mountMetricSkeletons(createSkeleton);
  mountGridSkeletons(createSkeleton);

  const response = await sessionClient.fetch(config.dataUrl, {
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
    },
    credentials: "same-origin",
  });

  if (!response.ok) {
    throw new Error(`Dashboard data request failed with status ${response.status}.`);
  }

  const payload = await response.json();

  updateHeader(payload);
  mountMetricCards(createProgress, payload.metrics || {});

  mountGrid(createGrid, "hub-status-grid", payload.hubStatus || [], {
    emptyText: "No target hubs have delivery history yet.",
    columns: [
      { key: "target_hub_id", label: "Target Hub", width: 180 },
      { key: "queued_count", label: "Queued", width: 110 },
      { key: "failed_count", label: "Failed", width: 110 },
      { key: "dead_count", label: "Dead", width: 110 },
      { key: "delivered_count", label: "Delivered", width: 120 },
      { key: "last_delivered_at_human", label: "Last Delivered", width: 180 },
    ],
  });

  mountGrid(createGrid, "recent-deliveries-grid", payload.recentDeliveries || [], {
    emptyText: "No deliveries have been created yet.",
    columns: [
      {
        key: "relay",
        label: "Relay",
        width: 240,
        renderCell: ({ row }) => stackedCell(row.relay_id, row.message_type),
      },
      { key: "target_hub_id", label: "Target", width: 140 },
      {
        key: "status",
        label: "Status",
        width: 140,
        renderCell: ({ row }) => statusBadge(row.status),
      },
      { key: "attempt_count", label: "Attempts", width: 110 },
      { key: "updated_at_human", label: "Updated", width: 160 },
    ],
  });

  mountGrid(createGrid, "recent-uploads-grid", payload.recentUploads || [], {
    emptyText: "No upload sessions yet.",
    columns: [
      {
        key: "attachment",
        label: "Attachment",
        width: 240,
        renderCell: ({ row }) => stackedCell(row.attachment_name, row.session_id),
      },
      { key: "direction", label: "Direction", width: 150 },
      {
        key: "transfer_status",
        label: "Status",
        width: 140,
        renderCell: ({ row }) => statusBadge(row.transfer_status),
      },
      { key: "progress_percent", label: "Progress", width: 120 },
    ],
  });

  mountGrid(createGrid, "recent-messages-grid", payload.recentMessages || [], {
    emptyText: "No messages yet.",
    columns: [
      {
        key: "message_type",
        label: "Message",
        width: 240,
        renderCell: ({ row }) => stackedCell(row.message_type, row.relay_id),
      },
      { key: "source", label: "Source", width: 180 },
      { key: "received_at_human", label: "Received", width: 160 },
    ],
  });

  mountGrid(createGrid, "recent-receipts-grid", payload.recentReceipts || [], {
    emptyText: "No receipts yet.",
    columns: [
      { key: "message_type", label: "Message Type", width: 180 },
      { key: "source_hub_id", label: "Source Hub", width: 140 },
      {
        key: "status",
        label: "Status",
        width: 140,
        renderCell: ({ row }) => statusBadge(row.status),
      },
      { key: "relay_id", label: "Relay ID", width: 220 },
    ],
  });

  mountGrid(createGrid, "clients-grid", payload.clients || [], {
    emptyText: "No relay clients registered yet.",
    columns: [
      { key: "name", label: "Name", width: 180 },
      { key: "system_code", label: "System", width: 160 },
      { key: "last_used", label: "Activity", width: 220 },
    ],
  });

  mountGrid(createGrid, "handlers-grid", payload.handlers || [], {
    emptyText: "No local handlers registered yet.",
    columns: [
      { key: "name", label: "Handler", width: 180 },
      { key: "client", label: "Client", width: 140 },
      { key: "message_type_pattern", label: "Pattern", width: 180 },
      { key: "status", label: "Activity", width: 220 },
    ],
  });

  mountGrid(createGrid, "handler-dispatches-grid", payload.handlerDispatches || [], {
    emptyText: "No handler dispatch activity yet.",
    columns: [
      { key: "handler_name", label: "Handler", width: 180 },
      {
        key: "message",
        label: "Message",
        width: 240,
        renderCell: ({ row }) => stackedCell(row.relay_id, row.message_type),
      },
      {
        key: "status",
        label: "Status",
        width: 140,
        renderCell: ({ row }) => statusBadge(row.status),
      },
      { key: "attempt_count", label: "Attempts", width: 110 },
      { key: "next_retry_at", label: "Next Retry", width: 180 },
    ],
  });
}

function updateHeader(payload) {
  const healthNode = document.getElementById("relay-dashboard-health");
  const timestampNode = document.getElementById("relay-dashboard-timestamp");
  const status = String(payload.health?.status || "healthy").toLowerCase();

  if (healthNode) {
    healthNode.className = `ui-badge relay-health relay-health-${status}`;
    healthNode.textContent = status.toUpperCase();
  }

  if (timestampNode) {
    timestampNode.textContent = `Timestamp ${payload.timestamp || "unknown"}`;
  }
}

function mountMetricSkeletons(createSkeleton) {
  document.querySelectorAll(".relay-metric-progress").forEach((container) => {
    createSkeleton(container, { lines: 2 }, { variant: "lines", className: "relay-metric-skeleton" });
  });
}

function mountGridSkeletons(createSkeleton) {
  [
    "hub-status-grid",
    "recent-deliveries-grid",
    "recent-uploads-grid",
    "recent-messages-grid",
    "recent-receipts-grid",
    "clients-grid",
    "handlers-grid",
    "handler-dispatches-grid",
  ].forEach((id) => {
    const container = document.getElementById(id);

    if (!container) {
      return;
    }

    createSkeleton(container, { rows: 5 }, {
      variant: "grid",
      columns: 5,
      className: "relay-grid-skeleton",
    });
  });
}

function mountMetricCards(createProgress, metrics) {
  const entries = [
    { key: "queuedDeliveries", label: "Queued Deliveries", tone: "#6291db" },
    { key: "failedDeliveries", label: "Failed Deliveries", tone: "#f1a94d" },
    { key: "deadDeliveries", label: "Dead Deliveries", tone: "#ef6b5c" },
    { key: "inboundReceipts", label: "Inbound Receipts", tone: "#53c7b3" },
  ];

  const values = entries.map((entry) => Number(metrics[entry.key] || 0));
  const maxValue = Math.max(1, ...values);

  entries.forEach((entry) => {
    const container = document.querySelector(`[data-metric-key="${entry.key}"]`);

    if (!container) {
      return;
    }

    createProgress(container, {
      label: `${Number(metrics[entry.key] || 0).toLocaleString()} item(s)`,
      value: Number(metrics[entry.key] || 0),
    }, {
      style: "gradient",
      max: maxValue,
      color: entry.tone,
      showPercent: false,
      className: "relay-metric-progress-ui",
      ariaLabel: entry.label,
    });
  });
}

function mountGrid(createGrid, containerId, rows, options) {
  const container = document.getElementById(containerId);

  if (!container) {
    return null;
  }

  return createGrid(container, rows, {
    chrome: false,
    enableSearch: false,
    enablePagination: false,
    enableSort: false,
    enableColumnResize: true,
    enableVirtualization: true,
    virtualRowHeight: 54,
    virtualOverscan: 8,
    virtualThreshold: 12,
    wrapCellContent: true,
    pageSize: Math.max(1, rows.length || 1),
    ...options,
  });
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

function statusBadge(status) {
  const badge = document.createElement("span");
  const normalized = String(status || "").toLowerCase();
  badge.className = `relay-status-badge ${statusTone(normalized)}`;
  badge.textContent = normalized || "unknown";
  return badge;
}

function statusTone(status) {
  if (["delivered", "completed", "processed", "healthy"].includes(status)) {
    return "is-ok";
  }

  if (["failed", "sending", "uploading", "duplicate", "degraded"].includes(status)) {
    return "is-warn";
  }

  if (["dead", "rejected", "unhealthy"].includes(status)) {
    return "is-danger";
  }

  return "is-info";
}

bootRelayDashboard().catch((error) => {
  console.error("Failed to boot relay dashboard UI.", error);
});
