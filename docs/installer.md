# Magna Web Installer

**Status: implemented** (off-plan feature, 2026-07-02) · Code: `src/Magna/Install/` · Tests: `tests/Feature/Install/`, `tests/Unit/Install/`

Browser-based, WordPress-style installation: unzip Magna onto a server, open the site, and every web request lands on `/install` until installation completes.

## Flow

1. **Requirements** — required gates (PHP 8.3+, extensions, writable `storage/`, `bootstrap/cache/`, `.env`) block progress with plain-language help; recommended items (Argon2id, intl, GD, zip, HTTPS, Redis) inform only.
2. **Site** — name, URL (http/https only), production toggle (sets `APP_ENV`/`APP_DEBUG`).
3. **Database** — PostgreSQL / MySQL / MariaDB / SQLite driver cards with **host, port, database name, username, password** fields (SQLite shows a file-path field instead). The connection is probed *before* anything is written; PDO failures map to human explanations (bad credentials vs. missing database vs. unreachable host). On success: `.env` written, config cache cleared if present, migrations + role seeder run through the verified connection.
4. **Admin account** — name, email, password (min 12, confirmed); user gets the super-admin role.
5. **Lock** — `storage/app/magna-installed.json` is written; every installer route 404s permanently.

## Security properties (implemented)

- Installer self-destructs after install (`EnsureNotInstalled` → 404).
- All installer routes rate-limited (`throttle:30,1`) so the DB connection probe can't be used for internal port scanning at speed.
- `.env` writer strips newlines from values (env-entry injection) and escapes/quotes safely.
- Site URL restricted to http/https schemes.
- CSRF active on all steps; sessions forced to the `file` driver pre-install; missing `APP_KEY` self-generated.
- Argon2id→bcrypt fallback written explicitly when the platform lacks Argon2.
- Stateless steps: no session state to corrupt; each step persists directly to `.env`.

## Known limitations & future edits (TODO — revisit at Stage 13 security pass)

- [ ] **Pre-install takeover window.** Until installation finishes, whoever reaches the URL first owns the site — identical to WordPress's model, but we can do better: generate a one-time **install token** written to `storage/` at first boot and require it on step 1 (the legitimate owner has filesystem access; a drive-by visitor doesn't). This is the highest-value hardening item.
- [ ] **SQLite path is user-supplied and unrestricted.** Restrict to `database/` (or at minimum refuse paths inside `public/`, where the DB file would be downloadable) and require a `.sqlite` extension.
- [ ] **DB error oracle.** The friendly error messages distinguish "refused" from "auth failed" — combined with the rate limit this is low-risk, but consider a uniform message for non-local hosts.
- [ ] **Raw error leak.** The default branch of `friendlyDatabaseError()` echoes the PDO message; truncate and strip file paths.
- [ ] **`/install/complete` reachable forever** (it only shows the site name/API URL). Gate it behind a short-lived post-install flag so it 404s afterward like the rest.
- [ ] **Password strength**: consider `Password::uncompromised()` (Have-I-Been-Pwned check) as an opt-in when the server has outbound network access.
- [ ] **CLI parity**: `php artisan magna:install` for scripted/headless provisioning (already promised in the README quick start).
- [ ] **Accessibility audit** of installer views (semantics are correct; needs a real screen-reader pass).
- [ ] **Localization** of installer strings once the i18n stage (15) lands.
- [ ] **Resume UX**: detect an already-migrated database on re-entry and say so instead of silently re-running idempotent steps.
- [ ] **Distribution artifact**: the "unzip" story ultimately needs a release ZIP with `vendor/` bundled and `.env.example` pre-staged — belongs to the release pipeline (Phase 2).

Anything checked off here must land with a regression test.
