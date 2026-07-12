# Magna CMS — Claude instructions

## Git rules — MUST follow every time

### Never commit or push plugins to this repo
`plugins-dev/` is listed in `.gitignore` and **must stay there**.
Each plugin (`plugins-dev/magna/docs`, `plugins-dev/magna/marketplace`, …) is its
own independent git repository with its own GitHub remote.

**When staging files for a magna-cms commit:**
- Never `git add plugins-dev/` or any path inside it
- Never `git add -A` / `git add .` — use explicit file paths only
- If `git status` shows plugin files as untracked, ignore them

**When a plugin needs its own commit/push**, work inside that plugin's directory
(e.g. `cd plugins-dev/magna/docs`) and push to its own remote.

### Remote
The magna-cms repo remote is `origin`. Push to `origin main` only when explicitly asked.

---

## Stack

- **Laravel 13**, PHP 8.4, SQLite (dev) / MySQL (prod)
- **Filament 3** admin panel, no `/admin` prefix — panel lives at `/`
- Auth: custom `Magna\Auth\Concerns\HasRoles` (NOT Spatie permissions)
- User model: `Magna\Users\User` (namespace, not `App\Models\User`)
- Plugins: discovered from `plugins-dev/` (dev) and `vendor/` (installed);
  enabled/disabled via the `plugins` table

## Key paths

| What | Where |
|---|---|
| Core source | `src/Magna/` |
| Admin panel | `src/Magna/Admin/` |
| User model | `src/Magna/Users/User.php` |
| Plugin contracts | `src/Magna/Contracts/` |
| Plugin base class | `src/Magna/Plugins/Plugin.php` |
| Dev plugins | `plugins-dev/{vendor}/{package}/` |
| Plugin dev guide | `docs/plugin-development-guide.md` |
