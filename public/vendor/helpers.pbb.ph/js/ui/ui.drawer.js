import { createElement } from "./ui.dom.js";
import { createEventBag } from "./ui.events.js";

export function createBottomDrawer(options = {}) {
  const events = createEventBag();
  const documentEvents = createEventBag();
  const animationMs = Math.max(0, Number(options.animationMs) || 220);
  const position = normalizePosition(options.position);
  const titleId = `ui-drawer-title-${Math.random().toString(36).slice(2, 10)}`;
  let lastFocusedElement = null;
  const backdrop = createElement("div", {
    className: joinClassNames("ui-drawer-backdrop", options.backdropClass),
  });
  const panel = createElement("aside", {
    className: joinClassNames("ui-drawer", options.panelClass, `ui-drawer-pos-${position}`),
    attrs: {
      role: "dialog",
      "aria-modal": "true",
      tabindex: "-1",
    },
  });
  const header = createElement("div", {
    className: joinClassNames("ui-drawer-header", options.headerClass),
  });
  const title = createElement("h4", {
    className: joinClassNames("ui-title", options.titleClass),
    text: options.title || "",
  });
  title.id = titleId;
  const closeButton = createElement("button", {
    className: joinClassNames("ui-drawer-close", options.closeClass),
    html: '<span aria-hidden="true">\u2715</span>',
    attrs: {
      type: "button",
      "aria-label": options.closeLabel || "Close drawer",
    },
  });
  const body = createElement("div", {
    className: joinClassNames("ui-drawer-body", options.bodyClass),
  });
  if (options.title) {
    panel.setAttribute("aria-labelledby", titleId);
  } else {
    panel.setAttribute("aria-label", String(options.ariaLabel || options.title || "Drawer"));
  }

  header.append(title, closeButton);
  panel.append(header, body);

  let isOpen = false;
  let closeNotified = false;
  let closeTimer = null;

  backdrop.style.setProperty("--ui-drawer-animation-ms", `${animationMs}ms`);
  panel.style.setProperty("--ui-drawer-animation-ms", `${animationMs}ms`);

  function notifyClose() {
    if (!closeNotified) {
      closeNotified = true;
      options.onClose?.();
    }
  }

  function close() {
    if (!isOpen) {
      return;
    }
    isOpen = false;
    events.clear();
    documentEvents.clear();
    backdrop.classList.remove("is-open");
    panel.classList.remove("is-open");
    backdrop.classList.add("is-closing");
    panel.classList.add("is-closing");

    if (closeTimer) {
      clearTimeout(closeTimer);
      closeTimer = null;
    }
    closeTimer = setTimeout(() => {
      if (backdrop.parentNode) {
        backdrop.parentNode.removeChild(backdrop);
      }
      if (panel.parentNode) {
        panel.parentNode.removeChild(panel);
      }
      backdrop.classList.remove("is-closing");
      panel.classList.remove("is-closing");
      closeTimer = null;
      if (lastFocusedElement && typeof lastFocusedElement.focus === "function") {
        try {
          lastFocusedElement.focus();
        } catch (_error) {
          // Ignore focus restore failures.
        }
      }
      lastFocusedElement = null;
      notifyClose();
    }, animationMs + 24);
  }

  function open(parent = document.body) {
    if (isOpen) {
      return;
    }
    if (closeTimer) {
      clearTimeout(closeTimer);
      closeTimer = null;
    }
    closeNotified = false;
    lastFocusedElement = document.activeElement instanceof HTMLElement ? document.activeElement : null;
    backdrop.classList.remove("is-open", "is-closing");
    panel.classList.remove("is-open", "is-closing");
    parent.append(backdrop, panel);
    isOpen = true;

    events.on(backdrop, "click", close);
    events.on(closeButton, "click", close);
    documentEvents.on(document, "keydown", (event) => {
      if (!isOpen) {
        return;
      }
      if (event.key === "Escape") {
        event.preventDefault();
        close();
      }
    });

    requestAnimationFrame(() => {
      if (!isOpen) {
        return;
      }
      backdrop.classList.add("is-open");
      panel.classList.add("is-open");
      closeButton.focus();
    });
  }

  return {
    panel,
    body,
    header,
    title,
    closeButton,
    backdrop,
    open,
    close,
    destroy: close,
    isOpen: () => isOpen,
  };
}

function normalizePosition(value) {
  const next = String(value || "bottom").toLowerCase();
  if (next === "top" || next === "left" || next === "right") {
    return next;
  }
  return "bottom";
}

function joinClassNames(...parts) {
  return parts
    .filter((value) => value != null && String(value).trim() !== "")
    .map((value) => String(value).trim())
    .join(" ");
}
