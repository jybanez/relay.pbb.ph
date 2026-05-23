export async function postUrlEncodedJson(sessionClient, url, values = {}, options = {}) {
  const {
    method = "POST",
    headers = {},
  } = options;

  const body = new URLSearchParams();
  body.set("_token", sessionClient.getCsrfToken());

  Object.entries(values || {}).forEach(([key, value]) => {
    if (value == null) {
      body.set(key, "");
      return;
    }

    body.set(key, String(value));
  });

  const response = await sessionClient.fetch(url, {
    method: String(method).toUpperCase(),
    headers: {
      Accept: "application/json",
      "X-Requested-With": "XMLHttpRequest",
      "Content-Type": "application/x-www-form-urlencoded;charset=UTF-8",
      ...headers,
    },
    credentials: "same-origin",
    body: body.toString(),
  });

  let result = null;

  try {
    result = await response.json();
  } catch {
    result = null;
  }

  return { response, result };
}
