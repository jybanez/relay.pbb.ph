import { uiLoader } from "/relay-ui/helper-ui-bundle.js";
import { createRelaySessionClient } from "/relay-ui/session.js";

async function bootRelayPublicHome() {
  const configNode = document.getElementById("relay-public-home-config");
  const config = configNode ? JSON.parse(configNode.textContent || "{}") : {};
  const sessionClient = await createRelaySessionClient();

  await uiLoader.loadMany(["ui.form.modal.login"]);
  const createLoginFormModal = await uiLoader.get("ui.form.modal.login");

  function openLoginModal() {
    const modal = createLoginFormModal({
      title: "Operator Login",
      message: "Please sign in with your Relay operator account to continue.",
      initialValues: {
        email: String(config.oldEmail || "").trim(),
      },
      busyMessage: "Signing in...",
      async onSubmit(values, ctx) {
        ctx.clearErrors();
        ctx.clearFormError();

        if (!String(values.email || "").trim() || !String(values.password || "")) {
          ctx.setFormError("Email and password are required.");
          return false;
        }

        try {
          await sessionClient.login(values);
          window.location.href = "/relay";
          return true;
        } catch (error) {
          ctx.setFormError(error?.payload?.error?.message || error.message || "Unable to sign in.");
          return false;
        }
      },
    });

    const initialError = String(config.loginError || "").trim();

    if (initialError) {
      modal.setFormError(initialError);
    }

    modal.open();
  }

  document.querySelectorAll("[data-open-relay-login]").forEach((trigger) => {
    trigger.addEventListener("click", (event) => {
      event.preventDefault();
      openLoginModal();
      clearLoginQueryFlag();
    });
  });

  if (document.body.dataset.relayLoginOpen === "true") {
    openLoginModal();
    clearLoginQueryFlag();
  }
}

function clearLoginQueryFlag() {
  const url = new URL(window.location.href);

  if (!url.searchParams.has("login")) {
    return;
  }

  url.searchParams.delete("login");
  const next = `${url.pathname}${url.searchParams.toString() ? `?${url.searchParams}` : ""}${url.hash}`;
  window.history.replaceState({}, "", next);
}

bootRelayPublicHome().catch((error) => {
  console.error("Failed to boot relay public home UI.", error);
});
