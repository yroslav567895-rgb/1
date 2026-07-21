# ModerUtills Website

License management panel for ModerUtills Minecraft mod.

## Features

- Login/password authentication with "Remember Me" (cookies)
- Personal Cabinet: activate license keys, view subscription info
- Admin Panel: manage users, create/manage license keys, activity log, online users counter
- Mod download page
- Dark theme with smooth CSS animations (cheat-client style)

## Quick Start

### 1. Install & Run

```bash
npm install
npm start
```

Or on Windows: double-click `start.bat`

### 2. Open

```
http://localhost:3000
```

### 3. Login

| Login | Password | Role |
|-------|----------|------|
| `unluck` | `Logan20241` | Super Admin |

On first run these credentials are created automatically.

## Deploy to VPS

```bash
git clone <your-repo-url>
cd ModerUtillsWebsite
npm install

# Set a custom secret for session encryption
export SESSION_SECRET="your-random-secret-here"

# Run with process manager (recommended)
npm install -g pm2
pm2 start server.js --name moderutills

# Or just run directly
node server.js
```

The server listens on port 3000 by default. Set `PORT` env var to change it.

## Project Structure

```
ModerUtillsWebsite/
├── server.js          # Express backend (API + static files)
├── package.json       # Dependencies
├── start.bat          # Windows launcher
├── start.sh           # Linux launcher
├── .gitignore
├── README.md
├── data/              # JSON databases (auto-created on first run)
│   └── .gitkeep
├── downloads/         # Put mod .jar files here
│   └── .gitkeep
└── public/            # Frontend (static files)
    ├── index.html     # SPA with all pages
    ├── css/
    │   └── style.css  # Dark theme + animations
    └── js/
        └── app.js     # Client-side logic
```

## Adding the Mod for Download

1. Build the mod jar
2. Copy it to `downloads/` folder
3. It will be available for download at the "Download Mod" button

## Setting Up a License Key

1. Login as admin/superadmin
2. Go to Admin panel → Generate Key (set duration)
3. Copy the generated key
4. User logs in to their Cabinet → enters the key → clicks Activate

## Mod Integration

The mod verifies licenses via `/api/verify-key` endpoint.
Configure the API URL in `moderatorassist.json` (mod config):

```json
"licenseApiUrl": "http://localhost:3000/api/verify-key"
```

For production, replace `localhost:3000` with your server URL.

## API Reference

| Method | Endpoint | Auth | Description |
|--------|----------|------|-------------|
| POST | /api/login | No | Login |
| POST | /api/logout | No | Logout |
| GET | /api/me | User | Current user info |
| POST | /api/claim-key | User | Activate license key |
| GET | /api/my-keys | User | List user's keys |
| GET | /api/verify-key | No | Verify key (used by mod) |
| GET | /api/download-mod | No | Download latest mod |
| GET | /api/mod-version | No | Get mod version string |
| GET | /api/admin/users | Admin | List all users |
| POST | /api/admin/create-user | SuperAdmin | Create a user |
| POST | /api/admin/ban-user | Admin | Ban/unban user |
| POST | /api/admin/create-key | Admin | Generate license key |
| GET | /api/admin/keys | Admin | List all license keys |
| POST | /api/admin/toggle-key | Admin | Activate/deactivate key |
| GET | /api/admin/activity | Admin | Activity log |
| GET | /api/admin/online-count | Admin | Online users count |

## Environment Variables

| Variable | Default | Description |
|----------|---------|-------------|
| PORT | 3000 | Server port |
| SESSION_SECRET | (built-in) | Session encryption key |

## License

MIT
