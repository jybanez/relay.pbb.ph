export class PbbHubRelayClient {
  constructor({ baseUrl, apiKey, protocolVersion = "1.1", fetchImpl = globalThis.fetch } = {}) {
    if (!baseUrl) {
      throw new Error("baseUrl is required.");
    }

    if (!apiKey) {
      throw new Error("apiKey is required.");
    }

    if (typeof fetchImpl !== "function") {
      throw new Error("A fetch implementation is required.");
    }

    this.baseUrl = String(baseUrl).replace(/\/+$/, "");
    this.apiKey = apiKey;
    this.protocolVersion = protocolVersion;
    this.fetchImpl = fetchImpl;
    this.lastCompatibility = null;
  }

  diagnostics() {
    return this.request("GET", "/api/v1/diagnostics");
  }

  compatibility() {
    return this.request("GET", "/api/v1/compatibility");
  }

  submitMessage(payload) {
    return this.request("POST", "/api/v1/messages", payload);
  }

  listMessages(query = {}) {
    return this.request("GET", "/api/v1/messages", null, query);
  }

  getMessage(messageId) {
    return this.request("GET", `/api/v1/messages/${messageId}`);
  }

  listDeliveries(query = {}) {
    return this.request("GET", "/api/v1/deliveries", null, query);
  }

  retryDelivery(deliveryId) {
    return this.request("POST", `/api/v1/deliveries/${deliveryId}/retry`);
  }

  cancelDelivery(deliveryId) {
    return this.request("POST", `/api/v1/deliveries/${deliveryId}/cancel`);
  }

  listInbox(query = {}) {
    return this.request("GET", "/api/v1/inbox", null, query);
  }

  listHandlers() {
    return this.request("GET", "/api/v1/handlers");
  }

  createHandler(payload) {
    return this.request("POST", "/api/v1/handlers", payload);
  }

  listHandlerDispatches(query = {}) {
    return this.request("GET", "/api/v1/handler-dispatches", null, query);
  }

  retryHandlerDispatch(dispatchId) {
    return this.request("POST", `/api/v1/handler-dispatches/${dispatchId}/retry`);
  }

  async ensureCompatibility() {
    const compatibility = await this.compatibility();
    const supported = compatibility.supported_protocol_versions || [compatibility.version?.relay_protocol_version || "1.0"];

    if (!supported.includes(this.protocolVersion)) {
      throw new Error(`Configured protocol version [${this.protocolVersion}] is not supported by the relay server.`);
    }

    this.lastCompatibility = compatibility;

    return compatibility;
  }

  async supportsCapability(capability) {
    const compatibility = this.lastCompatibility || await this.ensureCompatibility();
    return Array.isArray(compatibility.relay_protocol_capabilities)
      && compatibility.relay_protocol_capabilities.includes(capability);
  }

  async request(method, path, payload = null, query = {}) {
    const url = new URL(`${this.baseUrl}${path}`);

    Object.entries(query || {}).forEach(([key, value]) => {
      if (value !== undefined && value !== null && value !== "") {
        url.searchParams.set(key, String(value));
      }
    });

    const response = await this.fetchImpl(url, {
      method,
      headers: {
        Accept: "application/json",
        "Content-Type": "application/json",
        "X-Relay-Key": this.apiKey,
        "X-Relay-Protocol-Version": this.protocolVersion,
      },
      body: payload == null ? undefined : JSON.stringify(payload),
    });

    const data = await response.json();

    if (!response.ok) {
      throw new Error(data.error || `Relay request failed with HTTP ${response.status}`);
    }

    return data;
  }
}
