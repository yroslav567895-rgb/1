/**
 * ModerUtills License Server — Cloudflare Worker
 *
 * Деплой:
 *   1. Установи Node.js, выполни: npm install -g wrangler
 *   2. Создай KV Namespace: wrangler kv:namespace create LICENSE_KV
 *   3. wrangler.toml:
 *        name = "moderutills-license"
 *        main = "worker.js"
 *        kv_namespaces = [{ binding = "LICENSE_KV", id = "твой-id" }]
 *   4. wrangler deploy
 *
 * После деплоя получишь URL вида https://moderutills-license.твой-username.workers.dev
 * Этот URL укажи в index.html как API_URL
 */

const ADMIN_PASS = 'admin123';

export default {
  async fetch(request, env) {
    const url = new URL(request.url);
    const path = url.pathname;
    const method = request.method;

    const cors = {
      'Access-Control-Allow-Origin': '*',
      'Access-Control-Allow-Methods': 'GET, POST, OPTIONS',
      'Access-Control-Allow-Headers': 'Content-Type, Authorization',
    };

    if (method === 'OPTIONS') {
      return new Response(null, { headers: cors });
    }

    // ---- VERIFY ----
    if (path === '/api/verify' && method === 'GET') {
      const hwid = url.searchParams.get('hwid') || '';
      const licenseKey = url.searchParams.get('key') || '';
      const username = url.searchParams.get('username') || '';

      // Ping check
      if (hwid === 'ping') {
        return json({ success: false, ping: true }, cors);
      }

      // Key-only check (с сайта — ввод ключа)
      if (licenseKey && !hwid) {
        const list = await env.LICENSE_KV.list({ prefix: 'user:' });
        let found = null;
        for (const key of list.keys) {
          const val = JSON.parse(await env.LICENSE_KV.get(key.name));
          if (val.license_key === licenseKey) {
            found = val;
            break;
          }
        }

        if (!found) {
          return json({ success: false, error: 'Ключ не найден' }, cors);
        }

        if (!found.subscription_active) {
          return json({ success: false, error: 'Подписка неактивна' }, cors);
        }

        return json({
          success: true,
          message: 'Доступ разрешён',
        }, cors);
      }

      if (!hwid) {
        return json({ success: false, error: 'Missing HWID' }, cors);
      }

      // Если передан ключ — ищем по ключу (формат от Java-мода)
      if (licenseKey) {
        const list = await env.LICENSE_KV.list({ prefix: 'user:' });
        let found = null;
        for (const key of list.keys) {
          const val = JSON.parse(await env.LICENSE_KV.get(key.name));
          if (val.license_key === licenseKey) {
            found = val;
            break;
          }
        }

        if (!found) {
          return json({ success: false, error: 'License key not found' }, cors);
        }

        if (!found.subscription_active) {
          return json({ success: false, error: 'Subscription inactive' }, cors);
        }

        if (!found.hwid) {
          found.hwid = hwid;
          found.username = username || found.username;
          await env.LICENSE_KV.put('user:' + found.id, JSON.stringify(found));
          return json({ success: true, message: 'Activated', license_key: found.license_key }, cors);
        } else if (found.hwid.toLowerCase() === hwid.toLowerCase()) {
          found.username = username || found.username;
          await env.LICENSE_KV.put('user:' + found.id, JSON.stringify(found));
          return json({ success: true, message: 'Verified', license_key: found.license_key }, cors);
        } else {
          return json({ success: false, error: 'HWID mismatch' }, cors);
        }
      }

      // Поиск пользователя по HWID (формат с сайта)
      const list = await env.LICENSE_KV.list({ prefix: 'user:' });
      let found = null;

      for (const key of list.keys) {
        const val = JSON.parse(await env.LICENSE_KV.get(key.name));
        if (val.hwid && val.hwid.toLowerCase() === hwid.toLowerCase()) {
          found = val;
          break;
        }
      }

      if (!found) {
        return json({ success: false, error: 'HWID not found' }, cors);
      }

      if (!found.subscription_active) {
        return json({ success: false, error: 'Subscription inactive' }, cors);
      }

      return json({
        success: true,
        message: 'Доступ разрешён',
        license_key: found.license_key,
        username: found.username,
      }, cors);
    }

    // ---- ADMIN ----
    if (path === '/api/admin' && method === 'POST') {
      const auth = request.headers.get('Authorization');
      if (auth !== ADMIN_PASS) {
        return new Response('Unauthorized', { status: 401, headers: cors });
      }

      try {
        const data = await request.json();
        const { action, id, username, hwid, subscription_active } = data;

        switch (action) {
          case 'list': {
            const list = await env.LICENSE_KV.list({ prefix: 'user:' });
            const users = [];
            for (const key of list.keys) {
              users.push(JSON.parse(await env.LICENSE_KV.get(key.name)));
            }
            users.sort((a, b) => (b.id || 0) - (a.id || 0));
            return json({ success: true, users }, cors);
          }

          case 'add': {
            const maxId = Date.now();
            const key = genKey();
            const user = {
              id: maxId,
              username: username || 'unknown',
              hwid: hwid || '',
              license_key: key,
              subscription_active: subscription_active == 1 || subscription_active === true ? 1 : 0,
              created_at: new Date().toISOString().replace('T', ' ').slice(0, 19),
            };
            await env.LICENSE_KV.put('user:' + maxId, JSON.stringify(user));
            return json({ success: true, user }, cors);
          }

          case 'delete': {
            await env.LICENSE_KV.delete('user:' + id);
            return json({ success: true }, cors);
          }

          case 'toggle': {
            const ut = await env.LICENSE_KV.get('user:' + id);
            if (ut) {
              const u = JSON.parse(ut);
              u.subscription_active = u.subscription_active ? 0 : 1;
              await env.LICENSE_KV.put('user:' + id, JSON.stringify(u));
            }
            return json({ success: true }, cors);
          }

          case 'regen': {
            const ur = await env.LICENSE_KV.get('user:' + id);
            if (ur) {
              const u = JSON.parse(ur);
              u.license_key = genKey();
              await env.LICENSE_KV.put('user:' + id, JSON.stringify(u));
            }
            return json({ success: true }, cors);
          }

          case 'resethwid': {
            const uh = await env.LICENSE_KV.get('user:' + id);
            if (uh) {
              const u = JSON.parse(uh);
              u.hwid = '';
              await env.LICENSE_KV.put('user:' + id, JSON.stringify(u));
            }
            return json({ success: true }, cors);
          }

          default:
            return json({ error: 'Unknown action' }, cors);
        }
      } catch (e) {
        return json({ error: 'Bad request' }, cors);
      }
    }

    return new Response('Not found', { status: 404, headers: cors });
  }
};

function genKey() {
  const chars = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789';
  let s = '';
  for (let i = 0; i < 8; i++) s += chars[Math.floor(Math.random() * chars.length)];
  return 'MA-' + s;
}

function json(data, cors) {
  return new Response(JSON.stringify(data), {
    headers: { ...cors, 'Content-Type': 'application/json' }
  });
}
