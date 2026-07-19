// Cloudflare Worker — получает verify-запросы от мода
// wrangler.toml:
//   kv_namespaces = [{ binding = "LICENSE_KV", id = "..." }]
// Деплой: wrangler deploy

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const path = url.pathname;

    const cors = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type',
    };

    if (request.method === 'OPTIONS') {
      return new Response(null, { headers: cors });
    }

    if (path === '/api/verify') {
      const key = url.searchParams.get('key') || '';
      const hwid = url.searchParams.get('hwid') || '';
      const username = url.searchParams.get('username') || '';

      if (!key || !hwid || !username) {
        return json({ success: false, error: 'Missing parameters' }, cors);
      }

      if (!/^MA-[A-Z0-9]{8}$/.test(key)) {
        return json({ success: false, error: 'Invalid key format' }, cors);
      }

      const userStr = await env.LICENSE_KV.get('user:' + key);
      if (!userStr) {
        return json({ success: false, error: 'License key not found' }, cors);
      }

      const user = JSON.parse(userStr);

      if (!user.subscription_active) {
        return json({ success: false, error: 'Subscription is inactive' }, cors);
      }

      if (!user.hwid) {
        user.hwid = hwid;
        user.username = username;
        await env.LICENSE_KV.put('user:' + key, JSON.stringify(user));
        return json({ success: true, message: 'Activated' }, cors);
      } else if (user.hwid === hwid) {
        user.username = username;
        await env.LICENSE_KV.put('user:' + key, JSON.stringify(user));
        return json({ success: true, message: 'Verified' }, cors);
      } else {
        return json({ success: false, error: 'HWID mismatch' }, cors);
      }
    }

    return new Response('Not found', { status: 404 });
  }
};

function json(data, cors) {
  return new Response(JSON.stringify(data), {
    headers: { ...cors, 'Content-Type': 'application/json' }
  });
}
