# Magna CMS — Build Plan & Stage Prompts

**How to use this file**

> **Project location:** `C:\Users\jishn\Herd\magna-cms` (served by Herd at `magna-cms.test`).
> All spec documents live inside this repo under `docs/` — there is no external specs folder.
> **Stage 0 is complete** — check [PROGRESS.md](../PROGRESS.md) for current status before starting any stage.

1. Run **one stage per focused work session**, in order. Don't combine stages.
2. Work from the project root; each stage's brief below defines its full scope.
3. A stage is done only when its **acceptance criteria** pass — run the tests and check the output.
4. After each stage: review the diff, commit, and update `PROGRESS.md` — that file is how the *next* session knows where things stand.
5. If a session goes sideways, revert to the last commit and re-run the prompt. Never build a stage on top of a broken one.

Stages 0–13 = report Phase 1 (MVP). Stages 14–16 = Phase 2. Stages 17–18 = Phase 3 (Pages + theme).

---

## Stage 0 — Project scaffold & engineering rig ✅ COMPLETE

*(Completed 2026-07-02 — prompt kept for the record; do not re-run. The project was scaffolded on Laravel 13 and the folder later renamed to `magna-cms` so Herd could serve it. See docs/adr/ADR-0001 and PROGRESS.md.)*

```
Create a new Laravel 13 project named "magna-cms" in C:\Users\jishn\Herd\magna-cms and set up the engineering rig for a long-running open-source project.

Context: This is Magna CMS, an API-first headless CMS. The full specifications are the docs/*.md files of this repository (Magna-v2.md, plugin-development-guide.md, theme-development-guide.md, security-spec.md, performance-spec.md, default-theme-spec.md, store-plan.md) — these specs are the contract for everything we build. Read docs/Magna-v2.md fully before doing anything else.

Then:
1. git init, sensible .gitignore, initial commit of the bare scaffold.
2. Install and configure: Pest (testing), PHPStan at the highest level the fresh app passes (target level 9 for our own src later), Laravel Pint, and a composer script "check" that runs pint --test, phpstan, and pest.
3. Configure the app: SQLite for local dev tests, but write config and migrations that are PostgreSQL-first (we deploy on Postgres 16). Use ULIDs for all primary keys on models we create.
4. Create the namespace skeleton under app/: Magna kernel code will live in src/Magna (own composer autoload "Magna\\" => "src/Magna/") — create that structure with a placeholder MagnaServiceProvider registered in bootstrap.
5. Create docs/adr/ with ADR-0001 recording the stack decisions (Laravel 13, Filament 4, Postgres-first, ULIDs, plugins as Composer packages) — pull the rationale from docs/Magna-v2.md.
6. Create PROGRESS.md at the repo root: a stage checklist matching docs/build-plan.md, with Stage 0 marked complete and a "notes for next session" section.
7. GitHub Actions workflow: run composer "check" on push against PHP 8.3 + 8.4, with both SQLite and Postgres service containers.

Acceptance criteria: composer check passes locally; the CI workflow file is valid; PROGRESS.md exists; everything committed with clear messages.
```

---

## Stage 1 — Kernel: users, roles, permissions (RBAC)

```
Read PROGRESS.md, docs/Magna-v2.md (sections: Core Architecture, Users/Roles/Permissions, Security), and docs/security-spec.md. We are building Stage 1 of docs/build-plan.md: the RBAC kernel.

Build in src/Magna/Auth and src/Magna/Users:
1. User model (ULID, email, password argon2id via config, status active/suspended), migration, factory.
2. Roles and permissions: Role model, permission registry. Permissions are STRING KEYS registered in code (e.g. "content.article.publish"), not database rows — the registry is an in-memory service that plugins and core register into at boot; role_permission pivot stores granted keys. Wildcard support: granting "blog.*" matches "blog.posts.create".
3. A PermissionRegistry service with register(), all(), and Gate integration: $user->can('any.registered.key') resolves through roles. Super-admin role bypasses (Gate::before).
4. Seeder: default roles = super-admin, admin, editor, viewer with sensible permission sets.
5. Artisan command magna:permissions:list showing all registered keys and which roles hold them.
6. Full Pest coverage: wildcard matching, gate integration, super-admin bypass, unregistered-key behavior (denies + logs a warning).

Keep everything PHPStan level 9 clean. Update PROGRESS.md (Stage 1 complete + notes). Run composer check and show me the output before finishing. Commit.
```

---

## Stage 2 — Kernel: authentication & API tokens

```
Read PROGRESS.md, docs/security-spec.md fully, and docs/Magna-v2.md section Authentication. Stage 2 of docs/build-plan.md: authentication.

Build:
1. Session auth for the (future) admin: login, logout, password reset, email verification. Registration EXISTS but is DISABLED by default via a setting/config flag. Brute-force lockout on login (rate limit by user+IP, exponential backoff).
2. Sanctum API tokens with Magna's token model per docs/security-spec.md: tokens have a scope (delivery = read-only content, management = full), an expiry (default 1 year delivery / 30 days management), are shown once on creation, hashed at rest, revocable individually. Middleware "magna.api" that resolves token, enforces scope, and applies per-token rate limits.
3. 2FA (TOTP): enrol/confirm/recovery-codes flows, and a per-role "requires 2FA" flag enforced at login.
4. Security headers middleware per the spec (CSP for admin routes, HSTS, frame-ancestors none, nosniff, referrer policy) and default-deny CORS on management API routes.
5. Session hardening: secure/httponly/samesite from config, session rotation on login and privilege change.
6. Pest coverage for every flow above, including: expired token rejected, delivery token cannot write, lockout triggers, 2FA-required role cannot complete login without TOTP.

PHPStan level 9. Update PROGRESS.md. Run composer check and show output. Commit.
```

---

## Stage 3 — Kernel: settings system & audit log

```
Read PROGRESS.md, docs/Magna-v2.md (Settings, Security sections), docs/security-spec.md section 2. Stage 3 of docs/build-plan.md.

Build:
1. Typed settings: abstract Magna\Settings\Settings class — subclasses declare public typed properties; storage in a settings table (group, key, value JSON), cached (tagged cache, invalidated on save). Support a #[Secret] attribute on properties: encrypted at rest, masked in any export/API output.
2. Core settings classes: GeneralSettings (site name, default locale, registration_enabled), MailSettings, StorageSettings.
3. Append-only audit log per security-spec §2: audit_logs table (ULID, actor id/type, IP, action key, subject, before/after JSON, created_at — NO updated_at, and model prevents updates/deletes at the Eloquent level). A recordAudit() helper + automatic auditing of: login success/failure, role/permission changes, settings changes, token created/revoked.
4. JSON-lines export command magna:audit:export --from --to for SIEM ingestion.
5. Pest coverage: secret encryption round-trip and masking, cache invalidation on save, audit immutability (update attempt throws), each auto-audited event writes a row.

PHPStan level 9. Update PROGRESS.md. composer check + show output. Commit.
```

---

## Stage 4 — Plugin system

```
Read PROGRESS.md and docs/plugin-development-guide.md COMPLETELY — that document is the contract this stage must satisfy; every code sample in it must work when we're done (except content-type and block features, which arrive in Stages 5–6 and 11: stub those registration points with TODO-marked no-ops that we fill in later).

Build in src/Magna/Plugins:
1. magna.json manifest: schema validation (all fields from the guide §4), a Manifest value object, semver compat checking against a core version constant.
2. Plugin discovery: scan vendor packages for magna.json (composer "type": "magna-plugin" via installed.json) plus a plugins-dev/ path-repository convention for local development.
3. Plugin base class per guide §5 (register/boot/enable/disable) and lifecycle: enable = validate manifest → compat check → run the plugin's migrations → call enable() → register permissions from manifest → done; disable reverses registration without touching data. State stored in a plugins table.
4. Typed hook contracts from guide §6: create the Magna\Contracts interfaces RegistersAdminNavigation, RegistersDashboardWidgets, RegistersSettingsPages, RegistersBlocks, ExtendsEntryForm, FiltersApiQuery, RegistersWebhookEvents — with the supporting value objects (NavGroup, NavItem). Core iterates enabled plugins and dispatches to implemented contracts at boot.
5. CLI: magna:plugin:make (scaffolds the guide §3 skeleton into plugins-dev/), magna:plugin:install, magna:plugin:enable, magna:plugin:disable, magna:plugin:uninstall --purge (honors the manifest uninstall section), magna:plugin:list.
6. Test harness per guide §11: Magna\Testing\PluginTestCase that boots the app with a given plugin enabled.
7. Prove it: create plugins-dev/magna/hello-world, a minimal plugin with a permission, a nav registration, an API route, and a passing test using PluginTestCase.
8. Pest coverage: manifest rejection cases, compat refusal, enable/disable lifecycle, uninstall --purge vs without.

PHPStan level 9. Update PROGRESS.md (note explicitly which guide features are stubbed for later stages). composer check + output. Commit.
```

---

## Stage 5 — Content Engine I: schemas & generated tables

```
Read PROGRESS.md, docs/Magna-v2.md sections "Content Engine" and "Database" carefully — the storage strategy there is the heart of this product. Stage 5 of docs/build-plan.md.

Build in src/Magna/Content:
1. Content type schema: JSON files per docs/plugin-development-guide.md §7 (handle, displayName, localizable, draftable, fields[]). A ContentType value object + FieldType classes for the initial set: text, textarea, richtext (portable JSON), markdown, number, boolean, date, datetime, select, media, relation, blocks, json, slug, email, url, color. Each FieldType declares: column type (real column vs JSONB), validation rules, cast.
2. Schema registry: types load from (a) schemas/ directory in the app, (b) enabled plugins' schemas/ dirs (fill the Stage 4 stub), (c) database-defined types (admin-created, stored in a content_types table as JSON — same format).
3. Table generation: each content type gets one physical table magna_entries_{handle} with fixed columns (ulid id, status, locale, published_at, author_id, created/updated_at, plus a JSONB "blocks_data" style column only when needed) and real columns for simple fields per the FieldType mapping. Complex fields (blocks, repeater-ish, multi-select) → JSONB with GIN index (Postgres) / JSON (SQLite fallback for tests).
4. magna:schema:diff — compares every registered schema against the live database and prints a migration plan (add column, add index, new table; flag destructive changes and require --allow-destructive). magna:schema:sync executes the plan inside a transaction where the DB supports transactional DDL.
5. Relation fields: pivot table magna_relations (from_type, from_id, to_type, to_id, field, sort) — one shared pivot, indexed both directions.
6. Pest coverage: table generated correctly per field type, diff detects add/remove/change, sync is idempotent (second run = no-ops), destructive guard works, plugin schemas register on enable.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 6 — Content Engine II: entries, drafts, revisions, publishing

```
Read PROGRESS.md and docs/Magna-v2.md Content Engine section. Stage 6 of docs/build-plan.md.

Build on Stage 5:
1. Entry: a dynamic Eloquent model resolved per content type (Entry::type('article') returns a builder bound to that type's table), with attribute casting driven by the schema's FieldTypes. Creating/updating validates against schema rules.
2. Status workflow: draft → published → archived, plus scheduled publishing (published_at in future + a due scheduler job that flips status and fires events). "draftable: false" types skip draft state.
3. Revisions: every save of a published entry snapshots the full field payload to magna_revisions (type, entry id, payload JSON, author, created_at). magna:revisions:prune keeps the newest N per entry (setting, default 50). Restore = new revision, never mutation of history.
4. Draft-of-published: editing a published entry creates/updates a draft copy (single pending draft per entry) that replaces the published version on publish — the API must be able to serve published while a draft exists.
5. Events per docs: EntryCreated, EntryUpdated, EntryPublished, EntryUnpublished, EntryDeleted — all carrying the entry and actor.
6. Content permissions auto-generated per type: content.{handle}.view/create/update/publish/delete registered into the PermissionRegistry when a type registers.
7. Auto slugs (slug field type populates from a designated source field, unique per type+locale).
8. Pest coverage: full workflow (draft→publish→edit-as-draft→republish→revision count), scheduled publish fires, restore works, permissions enforced via Gate, validation failures per field type.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 7 — Media

```
Read PROGRESS.md, docs/Magna-v2.md (Content Engine — media is core) and docs/security-spec.md §1 upload rules — the upload pipeline is security-critical and the spec's rules are non-negotiable. Stage 7 of docs/build-plan.md.

Build in src/Magna/Media:
1. Media model + folders (tree), disk abstraction over Laravel filesystems (local + S3/R2 config ready).
2. Ingest pipeline per security spec: content-sniffed type allowlist (never trust extension), images re-encoded on ingest (strips embedded payloads + EXIF, configurable EXIF retention), SVG sanitization, max size per type, quarantine-reject with clear errors.
3. Conversions: named presets (thumb, card, hero + per-plugin registerable), generated as WebP AND AVIF, queued (never inline), responsive srcset variant sets. Original never served directly when a conversion exists for the context.
4. Signed, expiring URLs for private disks; public disk gets immutable cache-friendly hashed paths.
5. Media field type (Stage 5 registered it — implement resolution now): entries reference media by ULID; API/view models resolve to URL objects with conversion accessors.
6. magna:media:reconvert command for preset changes.
7. Pest coverage: a fake malicious file (php payload with .jpg extension) is rejected; EXIF stripped; conversions queue and produce variants; signed URL expires; deleting media used by an entry is blocked (or nulls with warning — pick per report and document in an ADR).

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 8 — Delivery REST API

```
Read PROGRESS.md, docs/Magna-v2.md API section, and docs/performance-spec.md FULLY — this stage carries the performance contract. Stage 8 of docs/build-plan.md.

Build:
1. /api/v1/content/{type} (list) and /api/v1/content/{type}/{idOrSlug} (single), delivery-scope token required, published-only by default. Features: field selection (?fields=), relation population (?with=, bounded depth), filtering (?filter[field][op]=value with a safe operator allowlist), sorting, CURSOR pagination as default (offset available but discouraged in docs).
2. Response envelope: data / meta (cursor) / included relations. Consistent, documented JSON shape; richtext served as portable JSON.
3. Performance contract: ETag on every response (contents hash) with conditional 304s costing zero content queries; Cache-Control with stale-while-revalidate; SURROGATE KEYS header on every response (entry:{ulid}, type:{handle}, media:{ulid} for every media resolved) per performance-spec §2.
4. Query discipline: per-endpoint query-count assertions in tests (list ≤ 4 queries regardless of ?with), Model::preventLazyLoading in the suite, relation population uses the shared pivot efficiently.
5. Preview: ?preview=1 with a signed preview token (separate short-lived token type minted per entry) serves the pending draft — the mechanism from the report's differentiators.
6. OpenAPI: generate an openapi.json from registered content types + these endpoints, served at /api/v1/openapi.json (management token) — auto-regenerated when schemas change.
7. Rate limiting per token from Stage 2 applies; 429 responses include Retry-After.
8. Pest coverage: every feature above, plus: draft invisible without preview token, preview token for entry A cannot read entry B, filter operator injection attempts rejected, 304 flow, surrogate keys correct including media keys.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 9 — Management API & webhooks

```
Read PROGRESS.md and docs/Magna-v2.md API section. Stage 9 of docs/build-plan.md.

Build:
1. Management API /api/v1/manage/...: CRUD for entries (draft/publish/unpublish/restore revision), media (upload via multipart + folder ops), content types (create/update DB-defined schemas — this triggers schema sync), settings (secrets masked), users/roles (guarded by RBAC). Management scope token + per-permission enforcement on every route.
2. Webhooks: webhook subscriptions (URL, secret, event allowlist) manageable via API + settings. Events: entry.published/updated/unpublished/deleted, media.created/deleted, plus plugin-registered events via the RegistersWebhookEvents contract (fill that Stage 4 stub). Delivery: queued, HMAC-SHA256 signature header, timestamp, 5 retries with exponential backoff, dead-letter status visible via API. Target <1s dispatch after publish per performance spec.
3. Audit every management mutation (Stage 3 helper).
4. Extend OpenAPI generation to cover management routes.
5. Pest coverage: permission matrix per route (viewer/editor/admin), webhook signature verifiable, retry + dead-letter behavior, content-type creation via API generates the table.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 10 — Admin panel (Filament)

```
Read PROGRESS.md, docs/Magna-v2.md Admin Panel section, docs/plugin-development-guide.md §6. Stage 10 of docs/build-plan.md.

Build:
1. Install Filament 4 as the Magna admin panel at /admin, wired to Stage 2 session auth (2FA respected), Magna branding, dark mode.
2. Content: a dynamic Filament resource per registered content type — form generated from the schema's FieldTypes (each FieldType maps to a Filament form component; blocks field gets a placeholder builder for Stage 11), table with status/locale/updated columns, filters, and actions: save draft, publish, unpublish, schedule, view revisions (diff view + restore).
3. Content type builder UI: create/edit DB-defined content types (fields, types, validation, localizable) — writes the same JSON schema format; "Apply changes" runs schema diff with a confirmation screen showing the plan (destructive changes clearly flagged).
4. Media library: folder tree, grid, upload (drag-drop), detail pane (conversions, usage), picker component used by the media FieldType.
5. RBAC UI: users, roles, permission matrix (grouped by namespace prefix). Settings pages for Stage 3 settings classes. Audit log viewer (read-only, filterable).
6. Plugin admin integration: fill the Stage 4 contract dispatch — RegistersAdminNavigation renders plugin nav groups, RegistersSettingsPages adds pages, RegistersDashboardWidgets populates the dashboard. Verify with the hello-world plugin.
7. Global search (entries by title across types), dashboard (entry counts, recent activity from audit log).
8. Tests: Filament/Livewire tests for the critical paths — create type via builder, create+publish entry, permission-restricted user sees restricted UI.

PHPStan level 9 (pragmatic baseline acceptable for Filament resource classes — document any level exceptions in phpstan config with a comment). Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 11 — Blocks & the structured block editor

```
Read PROGRESS.md, docs/Magna-v2.md Frontend Strategy section (blocks model), docs/plugin-development-guide.md §9, and docs/default-theme-spec.md §2 (the standard block list). Stage 11 of docs/build-plan.md.

Build:
1. Block definitions: block.json format per plugin guide §9 (handle, fields, optionsFrom), discoverable from core, app, and plugins (fill the RegistersBlocks stub). Block field data stored as portable JSON: [{ "block": "hero", "data": {...} }, ...] with schema validation per block on save.
2. The structured block editor in Filament (this is the WordPress-moment feature — make it feel GOOD): vertical block list on the entry form — add block (searchable picker with block icons), drag to reorder, collapse/expand, duplicate, delete, per-block form generated from its block.json fields. No canvas editing — structured list per the report.
3. Live preview: split-view "Preview" mode on the entry editor — an iframe hitting a signed preview URL (Stage 8 preview tokens) that re-renders on save/autosave. The preview endpoint returns the API JSON rendered through a minimal debug renderer for now (real theme rendering arrives with Pages in Stage 17) — structure it so Pages can take over that URL.
4. Implement the STANDARD BLOCK LIBRARY definitions (schemas only, no styled views yet) from default-theme-spec §2: hero, text, image, gallery, cta, features, testimonials, logos, faq, pricing, team, stats, video, form, entries, divider, spacer. The "entries" block's query logic (latest N of type X) lives in its definition class.
5. Pest coverage: block data validates per block schema, unknown block handles rejected on save but TOLERATED on read (forward compatibility), plugin-registered blocks appear in the picker, editor reorder round-trips.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 12 — Caching & performance contract

```
Read PROGRESS.md and docs/performance-spec.md COMPLETELY. Stage 12 of docs/build-plan.md — this stage turns the performance spec into enforced reality.

Build:
1. Tagged response cache for the delivery API keyed by full request signature, tagged with the surrogate keys the response carries (Stage 8 already computes them). Redis tagged store; array store for tests.
2. Invalidation: entry/media/type events purge exactly their tags. Edge drivers: a PurgesEdgeCache contract with Cloudflare, Fastly, and Varnish (BAN) implementations + null driver; purges queued, batched, with failure retry.
3. Stampede protection: lock-based revalidation (Cache::lock) — one rebuilder, others serve the stale copy within a grace window.
4. The benchmark harness per performance-spec §1: a magna:bench:seed command (100k entries across 20 types, 50k media rows — media as DB rows with fake disk objects) and k6 scenario scripts in benchmarks/ hitting each budget line from the spec table. A GitHub Actions nightly workflow that runs seed + k6 against FrankenPHP and fails on >10% regression vs a committed baseline JSON; document how to update the baseline.
5. Wire preventLazyLoading + the per-endpoint query budget assertions as a dedicated test group that CI runs on Postgres.
6. FrankenPHP: octane config, a docker-compose.benchmark.yml for the reference environment, and a CI smoke job booting the app under Octane to catch worker-mode leaks (static state, singletons holding request data).
7. Pest coverage: publish purges the right tags and ONLY those, stampede lock behavior, edge driver called with correct keys, null driver default.

Run the benchmark locally once and record first numbers in PROGRESS.md (they're baseline data, not yet budget-passing — note gaps honestly). PHPStan level 9. composer check + output. Commit.
```

---

## Stage 13 — Security hardening pass (gates Phase 1 exit)

```
Read PROGRESS.md and docs/security-spec.md line by line. Stage 13 of docs/build-plan.md: implement every §1–§3 item not yet built, then verify the whole spec.

Build/verify:
1. Field-level encryption: "encrypted": true on any schema field — encrypted cast at rest, decrypted in API/admin for authorized readers, excluded from search/filters (document why), key from app key with a documented KMS extension point. Migration guard: flipping encrypted on existing data requires magna:schema:encrypt --type=x --field=y (re-writes rows, queued in chunks).
2. GDPR contracts per spec §2: HandlesPersonalData contract, magna:privacy:export {user} aggregating core + plugin data to a JSON archive, magna:privacy:erase {user} cascading and reporting incomplete if any enabled plugin lacks the contract.
3. Supply chain CI per spec §3: composer audit job (fails on advisories), Dependabot config, Psalm taint analysis on src/Magna/Http + Auth paths, verify PHPStan level 9 holds everywhere.
4. SECURITY.md, .well-known/security.txt route, and docs/threat-model.md (STRIDE over the four trust boundaries per spec §5 — write the actual analysis for what exists today).
5. Audit checklist: go through security-spec §1 item by item and produce docs/security-checklist.md marking each item implemented/tested/na with test references. Anything unimplemented becomes a TODO with a stage assignment — nothing silently skipped.
6. Add security regression tests: at minimum — CORS default-deny verified, headers present on every response class, upload payload rejection (re-verify Stage 7), token scope escalation attempts, mass-assignment probes on management API, encrypted field never appears in logs/audit before/after payloads.

Update PROGRESS.md with the checklist summary. composer check + output. Commit.

This completes Phase 1. The MVP exit test from docs/Magna-v2.md now applies: model content, edit it, consume it from a Next.js starter in one afternoon — Stage 14 builds the proof.
```

---

## Stage 14 — First-party plugins: blog, SEO, forms (+ the afternoon test)

```
Read PROGRESS.md, docs/plugin-development-guide.md, and docs/store-plan.md Stage 1 catalog. Stage 14 of docs/build-plan.md: build the three launch plugins AS REAL PLUGINS in plugins-dev/ — they are the proof the plugin API works. Rule: if any of these needs a core change, make the core change generic (a contract or extension point), never a special case. Log every core change forced this way in PROGRESS.md — that list is plugin-API feedback.

1. magna/blog: post + category content types (schemas), author relation to users, RSS route, "entries" block preset config, admin nav via contracts, demo seeder. Tests via PluginTestCase.
2. magna/seo: per-entry SEO fields via ExtendsEntryForm (title, description, og image, noindex), sitemap.xml route (published entries, honoring noindex), meta payload included in delivery API responses under "seo". Tests.
3. magna/forms: form definitions (fields, validation, spam honeypot + rate limit), public submission endpoint (no auth, heavy rate limit, CORS-listed origins), submissions stored + viewable in admin, email notification via queued mail, the "form" block wired to render/submit. Data handling: implements HandlesPersonalData. Tests.
4. THE AFTERNOON TEST: create examples/next-starter — a minimal Next.js app (App Router) consuming the delivery API: blog index + post page with ISR, revalidated by a webhook route, draft preview via preview tokens. A README walking through the full flow (create type → publish → see it on the frontend) in under an hour of steps. This starter is the MVP exit proof from docs/Magna-v2.md.

Update PROGRESS.md: Phase 1 complete, plus the core-changes-forced list. composer check + all plugin tests + output. Commit.
```

---

## Stage 15 — Localization & scheduled publishing polish (Phase 2 begins)

```
Read PROGRESS.md and docs/Magna-v2.md Content Engine section (localization). Stage 15 of docs/build-plan.md.

Build:
1. Localization: locales configured in GeneralSettings (default + enabled list, fallback chain). "localizable: true" types store one row per locale (locale column already exists) sharing a ULID entry identity; per-field localizable flags (non-localized fields sync across locales on save).
2. Admin: locale switcher on the entry editor, translation status indicators on the list, "create translation from default locale" action.
3. API: ?locale= on delivery endpoints with fallback-chain resolution, locale in surrogate keys, hreflang data in the seo payload (coordinate with the SEO plugin's payload).
4. Scheduled publishing polish: unpublish_at support, admin scheduling UI with timezone clarity, upcoming-schedule dashboard widget, schedule visible in list view.
5. Schema sync across environments: magna:schema:export (all DB-defined types to schemas/ files) so schema-as-code round-trips: dev DB-defined → export → commit → prod magna:schema:sync. Document the workflow in docs/.
6. Pest coverage: fallback chain, non-localized field sync, locale-scoped slugs unique per locale, unpublish_at fires, export/sync round-trip is lossless.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 16 — Store Stage 1 (the official catalog)

```
Read PROGRESS.md and docs/store-plan.md fully. Stage 16 of docs/build-plan.md: the Official Store, Stage 1 scope ONLY (first-party catalog, no accounts/payments/ratings).

Build two things:
1. The store service: a SEPARATE minimal Laravel app in ../magna-store (its own repo eventually): a listings table (fields per store-plan data model), seeded with the first-party catalog, a public JSON API (/api/listings, /api/listings/{package}) including compat matrix and pairsWith, and simple public pages (browse, search, category, detail with screenshots). Static-friendly: aggressive full-page caching. No auth needed for Stage 1.
2. The client side in Magna core: an admin "Store" section that browses the store API (configurable base URL), shows compat against the running core version, and an Install action that executes the composer require + enable flow server-side with a confirmation screen showing the manifest's permissions/provides (the phone-app-permissions moment from the specs) and a progress log. Handle composer failure gracefully (report, no partial enable). Setting to disable the store entirely (enterprises want this).
3. The advisory feed hook from security-spec §4: core periodically checks the store's /api/advisories for installed packages and surfaces warnings on the dashboard; opt-out setting. (Store side: an advisories table + endpoint, empty for now.)
4. Tests both sides: store API shape, compat display logic, install flow with a fake composer runner, permissions confirmation is unskippable, advisory warning renders.

Update PROGRESS.md. composer check on both apps + output. Commit both.
```

---

## Stage 17 — Magna Pages (Phase 3 begins)

```
Read PROGRESS.md, docs/Magna-v2.md Frontend Strategy section, docs/theme-development-guide.md COMPLETELY — Pages must implement every mechanism that guide promises to theme developers. Stage 17 of docs/build-plan.md.

Build magna/pages as a plugin in plugins-dev/:
1. A "page" content type (title, slug, template, blocks) + site structure: page tree (parents/children), home designation, nav menu builder in admin.
2. The renderer: catch-all route (lowest priority, after all core/plugin routes) resolving page by path → template → blocks. Blade rendering with the theme resolution chain from the theme guide: theme view → block's default view. Restricted Blade context for theme files per the guide's no-logic rule (no raw PHP blocks; document exactly what's blocked and enforce in magna:theme:check).
3. Theme system per the guide: theme.json loading, tokens.json → CSS custom properties injection + auto-generated Theme Options admin panel (token overrides stored per site, surviving theme updates), template discovery, demo content import (drafts only, one-click removal), active-theme setting.
4. Default views: an unstyled-but-decent default view for every standard block from Stage 11 (these are the fallbacks the theme guide promises).
5. Full-page cache with surrogate keys per performance-spec §2: the renderer records every entry/media consumed per page; publish purges exactly the affected pages. TTFB budget from the spec applies.
6. Live preview takeover: the Stage 11 preview iframe now renders through Pages when it's enabled (real theme rendering in the editor split view).
7. CLI: magna:theme:make, magna:theme:check (manifest validation, declared views exist, fixture-render every block view with empty-optional-fields data, no-PHP-logic scan).
8. Tests: path resolution incl. nesting, fallback view chain, token CSS output, cache purge precision (edit one entry → only its pages purged), theme-check catches a view with raw PHP.

PHPStan level 9. Update PROGRESS.md. composer check + output. Commit.
```

---

## Stage 18 — The default theme "Launch"

```
Read PROGRESS.md and docs/default-theme-spec.md line by line — it IS the requirements document, including the acceptance criteria in §6 which are release blockers. Stage 18 of docs/build-plan.md.

Build magna/theme-launch in plugins-dev/ (composer type magna-theme):
1. Everything default-theme-spec specifies: tokens.json with the §4 defaults (dark mode counterparts included), layout + header/footer/nav partials (mobile nav CSS-only), the five templates (§3), and a styled Blade view for ALL 17 standard blocks (§2) with the specified variants (hero 3 layouts, faq via native <details>, video click-to-load facade, gallery without JS lightbox).
2. Tailwind 4 mapped to the token CSS variables; compiled CSS committed to assets/ (≤50KB gz); total JS ≤10KB gz (nav toggle + video facade only). Logical CSS properties throughout (RTL-cheap per spec §8).
3. Demo content per §5: the fictional company site exercising every block, imported as drafts.
4. Quality gates from §6, verified honestly: run Lighthouse against the demo home (report the actual scores in PROGRESS.md — if below 95/100/100, list what's blocking), axe-core accessibility scan in tests, magna:theme:check passes, every block view renders with all optional fields empty (fixture test), JSON-LD on the article template, self-hosted font files.
5. This theme is the reference implementation: code style exemplary, comments only where the theme guide needs illustrating.

Update PROGRESS.md with the measured Lighthouse/size numbers. composer check + output. Commit.

After this stage: install Magna → enable Pages → activate Launch → import demo = a real website in under a minute. That's the demo that sells the project.
```

---

## After Stage 18

Remaining Phase 3+ work (GraphQL plugin, semantic search, AI plugin, realtime, editorial workflow, store Stage 2) should get prompts written **the same way, at that time** — informed by what the first 18 stages taught. Don't pre-write prompts for work whose shape depends on feedback.

## Ground rules that apply to every stage (the prompts assume these)

- Never weaken a failing test to pass a stage; fix the code or flag the spec conflict in PROGRESS.md.
- Any deviation from the docs/ specs requires an ADR in docs/adr/ — the specs change consciously or not at all.
- Commit messages: `stage-N: <what>`, one stage may be several commits.
- If a session ends mid-stage, update PROGRESS.md with the exact remaining work first — the next session resumes from it.
