# ModerUtills License Panel

Готовый сайт для GitHub Pages + Cloudflare Worker.

## Состав
- `index.html` — главная страница (HWID-логин + админка)
- `worker.js` — Cloudflare Worker (уже задеплоен)
- `wrangler.toml` — конфиг для деплоя Worker
- `api/index.php` — PHP-альтернатива для бэкенда

## Ссылки
- **Worker (API):** https://moderutills-license.moderutills-license.workers.dev
- **Сайт (GitHub Pages):** https://yroslav567895-rgb.github.io/1/

## Как обновить Worker
```
cd G:\Holytime\moderutills-license
set CLOUDFLARE_API_TOKEN=cfut_JRCapfEK9CRCtJcB4CSqPQ8lJnFVI652Naqgecv6ef71ca0e
wrangler deploy
```

## Пароль админки
`admin123`
