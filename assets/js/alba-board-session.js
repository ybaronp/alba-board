/** Keep board requests on the current origin and renew expired tokens once, on demand. */
(function () {
  let refreshing;
  function url(value) {
    const target = new URL(value, window.location.href);
    if (target.username || target.password) throw new Error(window.albaSession.error);
    if (target.hostname === window.location.hostname && /^https?:$/.test(target.protocol)) {
      target.protocol = window.location.protocol;
      target.host = window.location.host;
    }
    if (target.origin !== window.location.origin) throw new Error(window.albaSession.error);
    return target.href;
  }
  function parameters(body) {
    if (body instanceof FormData || body instanceof URLSearchParams) return body;
    return typeof body === 'string' ? new URLSearchParams(body) : null;
  }
  async function refresh() {
    if (!refreshing) {
      refreshing = (async () => {
        const response = await fetch(url(window.albaSession.ajaxurl), {
          method:'POST', credentials:'same-origin', cache:'no-store', redirect:'error',
          body:new URLSearchParams({action:'alba_board_refresh_session', user_id:window.albaSession.user_id})
        });
        const result = await response.json();
        if (!response.ok || !result.success) throw new Error(window.albaSession.error);
        if (window.albaBoard) Object.assign(window.albaBoard, result.data.board);
        if (window.albaBoardFrontend) window.albaBoardFrontend.nonce = result.data.frontend;
        if (window.albaRichText) window.albaRichText.nonce = result.data.actions.alba_upload_card_image;
        if (window.albaCollaboration) window.albaCollaboration.nonce = result.data.board.rest_nonce;
        return result.data;
      })().finally(() => { refreshing = null; });
    }
    return refreshing;
  }
  async function request(input, options = {}) {
    const target = url(input);
    const args = {...options, credentials:'same-origin', redirect:'error'};
    let response;
    try { response = await fetch(target, args); }
    catch (_) { throw new Error(window.albaSession.network_error); }
    if (response.status !== 403) return response;
    const text = await response.clone().text();
    let failure;
    try { failure = JSON.parse(text); } catch (_) { return response; }
    const rest = failure && failure.code === 'rest_cookie_invalid_nonce';
    const ajax = failure === -1 || (failure && failure.success === false && failure.data?.code === 'alba_invalid_nonce');
    if (!rest && !ajax) return response;
    let renewed;
    try { renewed = await refresh(); } catch (_) { throw new Error(window.albaSession.error); }
    if (rest) {
      args.headers = new Headers(args.headers);
      args.headers.set('X-WP-Nonce', renewed.board.rest_nonce);
    } else {
      const body = parameters(args.body);
      const action = body?.get('action');
      if (!action || !renewed.actions[action]) return response;
      const replacement = body instanceof FormData ? new FormData() : new URLSearchParams();
      for (const [key, value] of body) replacement.append(key, value);
      replacement.set('nonce', renewed.actions[action]);
      args.body = typeof args.body === 'string' ? replacement.toString() : replacement;
    }
    // Only an explicit nonce rejection is replayed. Permission/network failures are never replayed.
    try { return await fetch(target, args); }
    catch (_) { throw new Error(window.albaSession.network_error); }
  }
  window.albaBoardRequest = {fetch:request, url};
  document.addEventListener('DOMContentLoaded', () => {
    // jQuery upload controls and integrations also use these localized URLs.
    for (const [config, keys] of [[window.albaBoard, ['ajaxurl','rest_url']], [window.albaBoardFrontend, ['ajaxurl']], [window.albaCollaboration, ['url']], [window.albaRichText, ['url']]]) {
      if (!config) continue;
      for (const key of keys) if (config[key]) {
        try { config[key] = url(config[key]); } catch (_) { /* Requests report an origin error when used. */ }
      }
    }
  });
})();
