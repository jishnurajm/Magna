# Magna CMS — Build Progress

Tracks progress against [docs/build-plan.md](docs/build-plan.md). Every session starts by reading this file; every stage ends by updating it.

> **Project location:** `C:\Users\jishn\Herd\magna-cms` — served by Herd at `magna-cms.test`. (Originally scaffolded as `magna`, renamed 2026-07-02; the folder name is hyphenated because Herd derives the dev domain from it. The product name remains "Magna CMS".) All specs are in-repo under `docs/`.

## Stage checklist

- [x] **Stage 0 — Project scaffold & engineering rig**
- [x] **Stage 1 — Kernel: users, roles, permissions (RBAC)**
- [x] **Stage 2 — Kernel: authentication & API tokens**
- [x] **Stage 3 — Kernel: settings system & audit log**
- [x] **Stage 4 — Plugin system**
- [x] **Stage 5 — Content Engine I: schemas & generated tables**
- [x] **Stage 6 — Content Engine II: entries, drafts, revisions, publishing**
- [x] **Stage 7 — Media**
- [x] **Stage 8 — Delivery REST API**
- [ ] Stage 9 — Management API & webhooks
- [ ] Stage 10 — Admin panel (Filament)
- [ ] Stage 11 — Blocks & the structured block editor
- [ ] Stage 12 — Caching & performance contract
- [ ] Stage 13 — Security hardening pass (gates Phase 1 exit)
- [ ] Stage 14 — First-party plugins: blog, SEO, forms (+ afternoon test)
- [ ] Stage 15 — Localization & scheduled publishing polish
- [ ] Stage 16 — Store Stage 1 (official catalog)
- [ ] Stage 17 — Magna Pages
- [ ] Stage 18 — Default theme "Launch"

## Stage 0 notes (2026-07-02)

- Scaffolded **Laravel 13.8** (framework 13.18) — not 12 as the build plan said; rationale in [ADR-0001](docs/adr/ADR-0001-stack-decisions.md). PHP 8.4.20 locally via Herd.
- Rig: Pest 4.7 (+ laravel plugin), Larastan 3.10 / PHPStan 2.2 at **level 9** on `app/`, `src/`, `database/`; Pint. `composer check` runs pint → phpstan → tests.
- `src/Magna` namespace autoloaded; `MagnaServiceProvider` registered in `bootstrap/providers.php` (empty shell — kernel providers attach here in later stages).
- SQLite for dev/tests (`database/database.sqlite`), Postgres-first policy per ADR. CI: PHP 8.3/8.4 matrix, suite runs on SQLite **and** Postgres 16 service.
- Spec docs copied into `docs/`; project README in place.

## Stage 1 notes (2026-07-02)

- RBAC kernel in `src/Magna/Auth` + `src/Magna/Users`. Key pieces: `PermissionRegistry` (in-code string keys, validated format, no wildcards in registered keys), `PermissionMatcher` (trailing `*` = any remainder, mid `*` = exactly one segment), `Role`/`RolePermission` models, `HasRoles` trait (memoized grants), `Magna\Users\User` (ULID, argon2id via config/hashing.php, `UserStatus` enum).
- Gate integration convention: **abilities containing a dot are permission keys** and resolve exclusively through the registry (unregistered → deny + `Log::warning`); dot-free abilities fall through to policies/closures; super-admin roles bypass everything via `Gate::before`.
- Scaffold `app/Models/User.php` deleted; `config/auth.php` points at `Magna\Users\User`. Users migration rewritten for ULID + status before any release exists (allowed only pre-1.0).
- Core kernel permission keys registered in `AuthServiceProvider` (users/roles/settings/plugins/audit). Seeder: super-admin, admin, editor (`content.*`, `media.*`), viewer (`content.*.view`) — wildcards resolve when content permissions register in Stage 6.
- Tests: 45 passing (83 assertions); argon costs lowered in phpunit.xml for speed. `magna:permissions:list` verified live.

## Off-plan: Web installer (2026-07-02)

- WordPress-style browser installer at `/install`, built in `src/Magna/Install` (requested outside the stage plan; kernel-only dependencies so it slots after Stage 1).
- Flow: requirements check (required + recommended, incl. Argon2id/HTTPS/Redis) → site name/URL/production toggle → database (PostgreSQL/MySQL/MariaDB/SQLite, connection probed with friendly errors before anything is written, then migrate + seed) → super-admin account → lock.
- **Stateless steps**: each step writes straight to `.env` (`EnvWriter` — updates in place, preserves comments, quotes safely). No session state to corrupt.
- **Bare-server bootstrap**: when uninstalled, sessions are forced to the `file` driver and a missing `APP_KEY` is self-generated and persisted (`InstallServiceProvider::prepareUninstalledRuntime`).
- **Security**: `EnsureNotInstalled` 404s all installer routes once the lock (`storage/app/magna-installed.json`) exists; `RedirectIfNotInstalled` sends all web traffic to the installer until then. Argon2id fallback to bcrypt is written to `.env` if the platform lacks it.
- Zero build/CDN dependencies: pure Blade + embedded CSS (dark UI), tiny vanilla JS for the driver toggle.
- Gotchas learned: middleware pushed to the `web` group from a provider is wiped by the bootstrap middleware sync — append in `bootstrap/app.php` instead. Installer flow tests are exempt from `RefreshDatabase` (they migrate their own connection); **new Feature test directories must be added to the RefreshDatabase line in tests/Pest.php**.
- Tests: `MAGNA_INSTALLED=true` in phpunit.xml makes the suite run "as installed"; installer tests override `magna.installed_override`/lock/env paths to temp dirs.
- The local dev site is intentionally left uninstalled so the installer can be tried at `http://magna-cms.test`.
- Full spec + **known limitations / future-edit checklist** in [docs/installer.md](docs/installer.md) — revisit at Stage 13 (security pass). Post-review hardening applied: env newline-injection stripped, URL restricted to http/https, installer routes throttled, cached config auto-cleared after .env writes.

## Stage 2 notes (2026-07-02)

- Sanctum installed; `MagnaToken` extends `PersonalAccessToken` — **must** declare `protected $table = 'personal_access_tokens'` explicitly (Eloquent would otherwise derive `magna_tokens` from the class name).
- Custom `personal_access_tokens` migration adds `scope` (`delivery`/`management`) and `rate_limit_per_minute` columns.
- `MagnaApiMiddleware` — stateless bearer-token auth: resolves token, checks expiry, enforces scope, applies per-token rate limiting (`RateLimiter`, 60s window), sets `auth()->setUser()` only when `tokenable instanceof Authenticatable`.
- `TwoFactorService` — `pragmarx/google2fa` + `bacon/bacon-qr-code` v3; `getQrCodeSvg()` returns inline SVG. Recovery codes: `bin2hex(random_bytes(5))-bin2hex(random_bytes(5))` format, count from `Config::integer('magna.two_factor.recovery_codes', 8)`.
- `LoginThrottle` — exponential backoff via `Cache` directly (not `RateLimiter`) to support variable decay per hit: `base * 2^(excess_attempts - 1)`, capped at max. Key fingerprint: `sha256(email|ip)`.
- Session auth routes at `auth/*`; API token routes at `api/v1/tokens`. Views registered as `magna::` namespace (flat — no `auth/` subdirectory); controller references use `magna::login` etc.
- Blade views only (no JS framework) for login, forgot-password, reset-password, verify-email, two-factor-challenge.
- `TwoFactorSetupController::disable()` uses `Hash::check()` directly instead of `current_password` validation rule — the rule generates a redirect (not JSON 422) when called on a DELETE request in the Pest test context.
- PHPStan level 9 clean: all `config()` calls replaced with `Config::string()` / `Config::integer()`; `Cache::get()` results guarded with `is_int()` before arithmetic; `Password` broker status constants passed directly (not `__()`).
- Tests: 100 passing (263 assertions) — 6 new test files covering Login, PasswordReset, EmailVerification, TwoFactor, ApiToken, SecurityHeaders.
- Registration is disabled by default (`magna.registration_enabled = false`); wire to the Settings system in Stage 3.
- CORS: default Laravel config left in place; production hardening deferred to Stage 13 (security pass).

## Stage 3 notes (2026-07-03)

- Settings system in `src/Magna/Settings/`. Abstract `Settings` base: `static::get()` returns a hydrated `static` instance (property defaults used when no DB row exists); `save()` persists all public properties and busts the tagged cache (`magna-settings` tag). `#[Secret]` attribute marks properties encrypted at rest via `Crypt::encryptString(json_encode($value))` and masked to `'[secret]'` in `toArray(maskSecrets: true)`.
- `group()` derives from the class name: `GeneralSettings` → `general`, `MailSettings` → `mail`, `StorageSettings` → `storage`.
- Tagged cache (`Cache::tags(['magna-settings'])`) works with the array driver used in tests. Cache key per group: `magna-settings:{group}`. Invalidated on every `save()`.
- `RegisterController::guardEnabled()` migrated from `config('magna.registration_enabled')` to `GeneralSettings::get()->registration_enabled`. LoginTest flush cache in the registration guard test to prevent cross-test pollution.
- PHPStan issues resolved: `new static()` in abstract class needs `@phpstan-consistent-constructor`; `group()` must be `public` (not `protected`) since `SettingsRepository` calls it from outside the class hierarchy.
- Audit log in `src/Magna/Audit/AuditLog`. Model prevents updates/deletes at the Eloquent level (throws `LogicException`). ULID primary key via `HasUlids`. No `updated_at` column (`const UPDATED_AT = null`). `AuditLog::record()` is the single static factory.
- Auto-audited events: `auth.login.success` / `auth.login.failure` via `RecordLoginSuccess` / `RecordLoginFailure` listeners (subscribed to `Illuminate\Auth\Events\Login` / `Failed`). `roles.assigned` / `roles.removed` from `HasRoles` trait. `settings.changed` from `SettingsRepository::persist()` with before/after diff (secrets masked). `tokens.created` / `tokens.revoked` from `ApiTokenController`.
- `magna:audit:export --from --to` command streams JSON lines to stdout in 500-record chunks.
- Tests: 113 passing (288 assertions) — 2 new test files (SettingsTest 5 tests, AuditLogTest 8 tests).

## Stage 4 notes (2026-07-03)

- Plugin system in `src/Magna/Plugins/`. Core pieces: `Manifest` value object (from magna.json), `ManifestValidator` (all required fields + name format + semver + permission key format), `PluginDiscovery` (scans `vendor/composer/installed.json` for `type: magna-plugin` packages AND `plugins-dev/*/*/magna.json` for dev plugins), `PluginManager` (enable/disable/uninstall/bootEnabledPlugins), `PluginRecord` Eloquent model (`plugins` table, ULID PK).
- Plugin base class `Magna\Plugins\Plugin` — `register()`/`boot()`/`enable()`/`disable()`. Routes in `routes/api.php` are auto-loaded by `PluginManager` and prefixed at `/api/v1/{slug}/` (slug = last segment of vendor/package name).
- Compat checking via `composer/semver` (`Semver::satisfies(VERSION, $constraint)`). Core VERSION: `1.0.0-dev` in `MagnaServiceProvider::VERSION`.
- 7 typed hook contracts in `src/Magna/Contracts/`: `RegistersAdminNavigation` (dispatched at boot, nav stored as `magna.nav.{name}` binding), `RegistersDashboardWidgets`, `RegistersSettingsPages`, `RegistersBlocks`, `ExtendsEntryForm`, `FiltersApiQuery`, `RegistersWebhookEvents`. The last 5 are TODO-marked stubs (wired in Stages 8–11).
- Supporting value objects: `Magna\Admin\Nav\NavGroup` and `NavItem` — fluent builders used by `RegistersAdminNavigation`.
- CLI commands: `magna:plugin:make` (scaffolds skeleton + updates composer.json path repo), `magna:plugin:install`, `magna:plugin:enable`, `magna:plugin:disable`, `magna:plugin:uninstall --purge`, `magna:plugin:list`.
- Test harness: `Magna\Testing\PluginTestCase` (extends TestCase, uses RefreshDatabase) — call `$this->enablePlugin($name)` from Pest `beforeEach()`.
- Proof plugin: `plugins-dev/magna/hello-world` (type `magna-plugin`, installed via path repo as `require-dev`). Permission `hello-world.greet`, nav registration via `RegistersAdminNavigation`, API route at `GET /api/v1/hello-world/greet`.
- `PluginTestCase` / `PluginLifecycleTest` cannot share the same Pest global `extend()` because they need different base classes — lifecycle tests use explicit `uses(TestCase::class, RefreshDatabase::class)`.
- Stubbed for later: content type registration (Stage 5), blocks (Stage 11), entry form extensions (Stage 10), API query filters (Stage 8), webhook events (Stage 9).
- Tests: 141 passing (328 assertions); 28 new tests across PluginLifecycleTest and HelloWorldPluginTest.

## Stage 5 notes (2026-07-03)

- Content Engine I in `src/Magna/Content/`. Key pieces:
  - **16 FieldType classes** (`text`, `textarea`, `richtext`, `markdown`, `number`, `boolean`, `date`, `datetime`, `select`, `media`, `relation`, `blocks`, `json`, `slug`, `email`, `url`, `color`) — each declares `isJsonColumn()`, `isRelationOnly()`, `addColumn()`, `validationRules()`, `cast()`.
  - **`ContentType` value object** — `fromArray()` accepts `array<mixed, mixed>` (json_decode output). Validates field handles against reserved column names (`id`, `status`, `locale`, etc.).
  - **`FieldTypeRegistry`** — maps type name strings to FieldType class-strings. `make()` returns the FieldType instance.
  - **`SchemaRegistry`** — loads from app `schemas/` dir, enabled plugins' `schemas/` dirs (Stage 4 stub filled), and `content_types` table.
  - **`TableGenerator`** — creates `magna_entries_{handle}` with 7 fixed columns + dynamic field columns. Relation fields use `magna_relations` pivot (no column in entry table). GIN indexes on Postgres only.
  - **`SchemaDiffer`** — compares registered schemas vs live DB. Uses `Schema::getColumnListing()` for column existence and `content_types` table for type change detection (driver-independent).
  - **`SchemaSyncer`** — applies diff plan. Wraps in `DB::transaction()` on Postgres; SQLite skips (no transactional DDL). `DestructiveChangeException` if destructive changes without `--allow-destructive`. Updates `content_types` record after every sync.
  - **`magna:schema:diff`** and **`magna:schema:sync --allow-destructive`** commands.
  - **Migrations**: `content_types` (ULID PK, handle unique, schema JSON) + `magna_relations` (shared pivot, indexed both directions).
- PHPStan issue: `json_decode()` → `array<mixed, mixed>` vs `array<string, mixed>` — resolved by accepting `array<mixed, mixed>` in `fromArray()` and using key-iteration to build the `array<string, mixed>` options map.
- Tests: 31 new tests (21 table-generation per field type, 7 diff/sync/idempotency/guard, 2 plugin schema, 1 relations pivot) across `Feature/Content/`. Same Pest convention as `Feature/Plugins/`: explicit `uses()` per file, no global list entry.
- Tests: 172 passing (385 assertions); PHPStan 0 errors; Pint clean.

## Stage 6 notes (2026-07-03)

- Content Engine II in `src/Magna/Content/`. Key pieces:
  - **`Entry` (final)** — dynamic Eloquent model. `Entry::type('article')` returns `Builder<Entry>` bound to `magna_entries_article`. `makeInstance()` configures the table and schema-derived casts. `newInstance()` propagates them to all hydrated results. Casts: `status → EntryStatus` enum, `published_at → datetime`, plus per-field casts from FieldType::cast().
  - **`EntryStatus` enum** — `draft | scheduled | published | archived`.
  - **`EntryManager` service** — `create()`, `update()`, `publish()`, `unpublish()`, `delete()`, `createDraftOf()`, `restore()`. Single business-logic entry point; validates via `SchemaValidator` before any write.
  - **`SchemaValidator`** — validates entry data against schema FieldType rules. Partial mode (for updates) skips absent fields.
  - **`SlugGenerator`** — `Str::slug()` from source field, uniqueness loop per type+locale.
  - **`Revision` model** — `magna_revisions` table (ULID PK, `entry_type`, `entry_id`, `payload` JSON, `author_id`, `created_at` only). Overrides `save()`/`delete()` to throw `LogicException` (append-only).
  - **Draft-of-published pattern** — `createDraftOf()` copies all field values; `publish($draft)` → `publishDraftOf()` snapshots the published state as a revision, overwrites the published entry, and deletes the draft. The canonical entry ID never changes.
  - **Scheduled publishing** — `publish($entry, $futureCarbon)` → `status = scheduled`. `magna:publish:scheduled` promotes due entries and fires `EntryPublished`.
  - **`magna:revisions:prune --keep=N`** — keeps newest N revisions per entry via grouped delete.
  - **5 events** — `EntryCreated`, `EntryUpdated`, `EntryPublished`, `EntryUnpublished`, `EntryDeleted` (each carries entry + actor ID).
  - **Content permissions** — `SchemaRegistry::onTypeRegistered()` callback auto-registers `content.{handle}.view/create/update/publish/delete` into `PermissionRegistry` whenever any type is registered (including plugin types at boot).
  - **Fixed columns updated** — `draft_of char(26) nullable` added to all entry tables; `SchemaDiffer::FIXED_COLUMNS` and `ContentType::fromArray()` reserved list both updated.
- PHPStan: `Entry` is `final` (resolves `new static()` unsafe usage + `Builder<static>` return type mismatch). `mixed`-to-string casts replaced with `is_string()` guards.
- Tests: 18 new tests in `Feature/Content/EntryTest.php` covering full workflow, scheduling, restore, revision pruning, events, auto-slug uniqueness, non-draftable types, Gate permission enforcement.
- Tests: 190 passing (435 assertions); PHPStan 0 errors; Pint clean.

## Stage 7 notes (2026-07-03)

- Media module in `src/Magna/Media/`. Key pieces:
  - **`Media` model** — `magna_media` table (ULID PK, `SoftDeletes`). Stores disk, path, original filename, MIME type, dimensions, alt/title, metadata JSON. Soft-delete is the delete policy (see ADR below).
  - **`MediaFolder` model** — `magna_media_folders` table (ULID PK, self-referential parent FK). Simple adjacency-list tree.
  - **`MediaConversion` model** — `magna_media_conversions` table (ULID PK, unique on media_id+preset+format). Append-like: no `updated_at`. Stores per-format (webp/avif) conversion results.
  - **`ConversionPreset` / `ConversionPresetRegistry`** — named presets (`thumb` 150×150, `card` 600×400, `hero` 1920×1080) + plugin-registerable. Presets registered in `MediaServiceProvider::register()`.
  - **`MediaIngestor`** — the security-critical upload pipeline (spec §1 rules, non-negotiable):
    1. Content-sniff MIME via `finfo_open(FILEINFO_MIME_TYPE)` — extension **never** trusted.
    2. Allowlist: `image/jpeg`, `image/png`, `image/gif`, `image/webp`, `image/avif`, `image/svg+xml`, `application/pdf`. Anything else → `MimeTypeNotAllowedException`.
    3. Per-type size guard (20 MB images, 2 MB SVG, 50 MB PDF).
    4. **Raster images** re-encoded via Intervention/Image 3.x GD driver — creating a new image from raw pixels guarantees EXIF, embedded scripts, and ICC payloads are stripped.
    5. **SVG** sanitized via `enshrined/svg-sanitize` 0.22 — removes `<script>`, event attributes, `javascript:` hrefs, `<foreignObject>`.
    6. **PDF and others** — stored as-is (separate disk, no execute permissions).
    7. Media record created with a pre-allocated ULID (so storage path and DB record share the same ID).
    8. Queued `ProcessMediaConversionJob` dispatched per registered preset (raster images only).
  - **`ProcessMediaConversionJob`** — reads original from storage → temp file → Intervention/Image resize → WebP output; AVIF is best-effort (silently skipped if GD lacks libavif). Creates/updates `MediaConversion` records.
  - **`MediaUrlResolver`** — `publicUrl(Media, ?preset)`: serves WebP conversion when available, falls back to original. `signedUrl(Media, ?preset, ?expiresAt)`: S3/R2/GCS → SDK `temporaryUrl()`; local/public → `URL::temporarySignedRoute('magna.media.serve', ...)`. `srcset(Media)`: builds `w`-descriptor string from all WebP conversions.
  - **`MediaViewObject`** — read-only view of a Media record with `url()`, `signedUrl()`, `srcset()` accessors. Created via `MediaViewObject::fromModel(Media, MediaUrlResolver)`. Consumed by Stage 8 Delivery API.
  - **`magna:media:reconvert --preset --id`** — re-queues conversion jobs for existing media; accepts optional preset/ID filters.
  - **Signed-URL delivery route** — `GET /_media/{media}` (named `magna.media.serve`), validated by `middleware('signed')`; serves the original file for now. Stage 8 adds preset resolution and auth.
- **Delete policy ADR**: Media uses `SoftDeletes`. On delete, the record gets `deleted_at` but is not hard-removed. Entries continue to reference the ULID; `MediaUrlResolver` can return null for trashed media; the admin UI (Stage 10) can warn. Hard delete is a future explicit action. This approach avoids scanning all entry tables at delete time (which would require the full entry API from Stage 8).
- **Packages added**: `intervention/image ^3.0`, `enshrined/svg-sanitize ^0.22` (to `require`).
- **Tests** (13 new in `Feature/Media/MediaIngestTest.php`):
  - PHP payload disguised as `.jpg` → `MimeTypeNotAllowedException` ✓
  - EXIF stripped: APP1 segment present in input, absent after re-encode ✓
  - Valid JPEG ingest: Media record created, dimensions correct, file stored ✓
  - SVG `<script>` tag sanitized, `<rect>` preserved ✓
  - Conversion jobs dispatched (Queue::fake × 3 presets) ✓
  - No conversion jobs for SVG ✓
  - Conversion job produces WebP `MediaConversion` record with correct dimensions ✓
  - Signed URL contains `signature=` and `expires=` ✓
  - Expired signed URL fails validation ✓
  - Soft delete: `withTrashed()` finds, normal scope excludes ✓
  - `MediaViewObject::fromModel` exposes metadata and delegates URL ✓
  - `magna:media:reconvert` queues 6 jobs (2 images × 3 presets, SVG excluded) ✓
- Tests: **203 passing (472 assertions)**; PHPStan 0 errors; Pint clean.

## Stage 8 notes (2026-07-03)

- Delivery REST API in `src/Magna/Delivery/`. Key pieces:
  - **`GET /api/v1/content/{type}`** — list endpoint: published-only by default, `?fields=`, `?with=`, `?filter[field][op]=value` (safe operator allowlist), `?sort=`, `?per_page=`, `?cursor=`. Response envelope: `{ data, meta: { next_cursor, has_more, per_page }, included }`.
  - **`GET /api/v1/content/{type}/{idOrSlug}`** — single endpoint: ULID or slug lookup. `?preview=1&preview_token=` admits draft entries when token valid.
  - **`POST /api/v1/content/{type}/{id}/preview-token`** — management scope only; mints entry-scoped HMAC-signed preview tokens. Uses `Carbon::now()->getTimestamp()` (not `time()`) so test time travel works.
  - **`GET /api/v1/openapi.json`** — management scope only; generated spec from all registered types.
  - **`DeliveryQueryBuilder`** — `?filter[field][op]=value` with safe operator allowlist (`eq/neq/lt/lte/gt/gte/like/in/nin`). Validates column against schema + base set. PHPStan proves `OP_MAP` offset always exists after `in`/`nin` branches.
  - **`CursorPaginator`** — ULID-keyset, newest-first default. URL-safe base64 encoding (`+/` → `-_`, no padding). Cursor validation uses `/^[0-9A-Z]{26}$/i` (case-insensitive) + `strtolower()` normalisation — `HasUlids` stores lowercase ULIDs; cursor regex must accept both cases.
  - **`RelationLoader`** — single pivot query + one entry query per distinct relation type; ≤4 total queries (1 entries + 1 pivot + 1 relations + 1 media, regardless of entry count).
  - **`EntryTransformer`** — base fields always present; column fields filtered by `?fields=`; relation fields only when populated via `?with=`. Media resolved from `mediaCache` (preloaded). Uses `isJsonColumn()` (public) NOT `boolOption('multiple')` (protected) to detect multi-media fields.
  - **`ETagService`** — SHA-256 hash of serialized response JSON. `check()` and `store()` must use identical tag set `['magna.delivery', 'magna.delivery.type.{handle}']` (tagged cache requires exact match for reads). `invalidateType()` flushes by single tag (asymmetric flush works).
  - **`SurrogateKeyCollector`** — builds `type:{handle} entry:{ulid} media:{ulid}` header for CDN tag-based invalidation.
  - **`OpenApiGenerator`** — walks `SchemaRegistry`, produces OpenAPI 3.1 paths + schema components per type.
  - **`DeliveryServiceProvider`** — registers singletons + binds controllers; boots routes under `api` middleware + `api/v1` prefix.
- **Key bugs fixed post-build**:
  - `strtoupper($id)` in single controller → changed to `strtolower($id)` (`HasUlids` stores lowercase).
  - Cursor regex `/^[0-9A-Z]{26}$/` → `/^[0-9A-Z]{26}$/i` + `strtolower($decoded)` for lowercase ULID cursor values.
  - `ETagService::check()` used `Cache::tags(['magna.delivery'])` but `store()` used two tags → `check()` now requires `typeHandle` and uses matching two-tag set.
  - `magna_relations` test insert missing `id` ULID + timestamps → added.
  - `PreviewTokenService` used `time()` (ignores test time travel) → changed to `Carbon::now()->getTimestamp()`.
  - Query count test counted token auth overhead (find token + load user + last_used_at update = 3 queries) against a ≤4 budget → now filters to `magna_*` table queries only.
- Tests: **240 passing (580 assertions)**; PHPStan 0 errors; Pint clean.

## Notes for next session (Stage 9)

- Stage 9 — Management API & webhooks: CRUD endpoints for entries/media/schemas, webhook delivery, event subscriptions.
