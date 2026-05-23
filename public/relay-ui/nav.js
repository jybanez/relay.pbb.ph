import { uiLoader } from "/relay-ui/helper-ui-bundle.js";
import { createRelaySessionClient } from "/relay-ui/session.js";
import { postUrlEncodedJson } from "/relay-ui/request-utils.js";

const HOME_ICON = `
  <svg viewBox="0 0 24 24" aria-hidden="true">
    <path d="M3 11.5 12 4l9 7.5v8a1 1 0 0 1-1 1h-5v-6H9v6H4a1 1 0 0 1-1-1z"></path>
  </svg>
`;

async function bootRelayOperatorNav() {
  const host = document.getElementById("relay-operator-nav");
  const payloadNode = document.getElementById("relay-operator-nav-data");

  if (!host || !payloadNode) {
    return;
  }

  const payload = JSON.parse(payloadNode.textContent || "{}");
  const sessionClient = await createRelaySessionClient();

  await uiLoader.loadMany([
    "ui.navbar",
    "ui.dialog.alert",
    "ui.form.modal.account",
    "ui.form.modal.change.password",
  ]);

  const createNavbar = await uiLoader.get("ui.navbar");
  const uiAlert = await uiLoader.get("ui.dialog.alert");
  const createAccountFormModal = await uiLoader.get("ui.form.modal.account");
  const createChangePasswordFormModal = await uiLoader.get("ui.form.modal.change.password");

  const items = Array.isArray(payload.items) ? payload.items.map(normalizeItem) : [];
  const navbar = createNavbar(host, {}, buildNavbarOptions(payload, items, sessionClient, {
    uiAlert,
    createAccountFormModal,
    createChangePasswordFormModal,
  }));

  window.addEventListener("relay:session-updated", () => {
    navbar.update({}, buildNavbarOptions(payload, items, sessionClient, {
      uiAlert,
      createAccountFormModal,
      createChangePasswordFormModal,
    }));
  });
}

function buildNavbarOptions(payload, items, sessionClient, helpers) {
  const account = sessionClient.getState()?.auth?.account || null;

  return {
    className: "relay-operator-nav",
    brandText: "PBB - Hub Relay Server",
    activeId: String(payload.activeId || "home"),
    items,
    actions: [
      buildUserMenuAction(account),
    ],
    onNavigate(item) {
      if (item.id === "brand") {
        window.location.href = "/relay";
        return;
      }

      if (item.href) {
        window.location.href = item.href;
      }
    },
    onActionMenuSelect(action, item) {
      if (action?.id !== "user-menu") {
        return;
      }

      if (item?.id === "account") {
        openAccountModal(sessionClient, helpers).catch((error) => {
          console.error("Failed to open relay account modal.", error);
        });
        return;
      }

      if (item?.id === "logout") {
        sessionClient.logout().catch((error) => {
          console.error("Failed to log out relay operator.", error);
        });
      }
    },
  };
}

function buildUserMenuAction(account) {
  const labelSource = String(account?.name || account?.email || "U").trim();
  const initial = labelSource.charAt(0).toUpperCase() || "U";

  return {
    id: "user-menu",
    label: initial,
    menuItems: [
      { id: "account", label: "Account" },
      { id: "logout", label: "Logout", danger: true },
    ],
  };
}

async function openAccountModal(sessionClient, { uiAlert, createAccountFormModal, createChangePasswordFormModal }) {
  if (typeof createAccountFormModal !== "function" || typeof createChangePasswordFormModal !== "function") {
    throw new Error("Required Helper account presets are unavailable.");
  }

  const account = sessionClient.getState()?.auth?.account || null;

  const modal = createAccountFormModal({
    title: "Account",
    size: "md",
    nameLabel: "Full name",
    emailLabel: "Email address",
    busyMessage: "Saving account...",
    initialValues: {
      name: account?.name || "",
      email: account?.email || "",
    },
    extraActionsPlacement: "start",
    extraActions: [
      {
        id: "change-password",
        label: "Change Password",
        variant: "ghost",
        closeOnClick: false,
        onClick() {
          openChangePasswordModal(sessionClient, { uiAlert, createChangePasswordFormModal }).catch((error) => {
            console.error("Failed to open relay change-password modal.", error);
          });
        },
      },
    ],
    async onSubmit(values, ctx) {
      ctx.clearErrors();
      ctx.clearFormError();

      try {
        const { response, result } = await postUrlEncodedJson(sessionClient, "/api/user", {
          name: values.name || "",
          email: values.email || "",
        });

        if (!response.ok || result?.success === false) {
          const mapped = ctx.applyApiErrors(result || {});
          if (!mapped.formError) {
            ctx.setFormError(result?.status_message || result?.error?.message || "Unable to update account.");
          }
          return false;
        }

        sessionClient.applySessionPayload({
          authenticated: true,
          account: result.account || account,
          csrf_token: result.csrf_token || sessionClient.getCsrfToken(),
        });

        await uiAlert("Account details updated.", {
          title: "Account Updated",
          size: "md",
        });

        return true;
      } catch (error) {
        ctx.setFormError(error?.message || "Unable to update account.");
        return false;
      }
    },
  });

  modal.open();
}

async function openChangePasswordModal(sessionClient, { uiAlert, createChangePasswordFormModal }) {
  const account = sessionClient.getState()?.auth?.account || null;
  const modal = createChangePasswordFormModal({
    title: "Change Password",
    size: "md",
    currentPasswordLabel: "Current Password",
    newPasswordLabel: "New Password",
    confirmPasswordLabel: "Confirm Password",
    fields: {
      currentPassword: "current_password",
      newPassword: "password",
      confirmPassword: "password_confirmation",
    },
    busyMessage: "Updating password...",
    async onSubmit(values, ctx) {
      ctx.clearErrors();
      ctx.clearFormError();

      try {
        const { response, result } = await postUrlEncodedJson(sessionClient, "/api/user/password", {
          current_password: values.current_password || "",
          password: values.password || "",
          password_confirmation: values.password_confirmation || "",
        });

        if (!response.ok || result?.success === false) {
          const mapped = ctx.applyApiErrors(result || {});
          if (!mapped.formError) {
            ctx.setFormError(result?.status_message || result?.error?.message || "Unable to update password.");
          }
          return false;
        }

        sessionClient.applySessionPayload({
          authenticated: true,
          account: result.account || account,
          csrf_token: result.csrf_token || sessionClient.getCsrfToken(),
        });

        await uiAlert("Password updated.", {
          title: "Account Updated",
          size: "md",
        });

        return true;
      } catch (error) {
        ctx.setFormError(error?.message || "Unable to update password.");
        return false;
      }
    },
  });

  modal.open();
}

function normalizeItem(item) {
  if (!item || typeof item !== "object") {
    return {};
  }

  return {
    ...item,
    icon: item.id === "home" ? HOME_ICON : item.icon,
  };
}

bootRelayOperatorNav().catch((error) => {
  console.error("Failed to boot relay operator navigation.", error);
});
