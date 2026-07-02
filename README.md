
<h1 align="center">Magna CMS</h1>

<p align="center">
  <strong>The headless CMS that speaks Laravel.</strong><br>
  API-first content platform with a real plugin ecosystem — and a website out of the box when you want one.
</p>

<p align="center">
  <a href="#-status"><img src="https://img.shields.io/badge/status-in%20development-orange" alt="Status"></a>
  <a href="LICENSE"><img src="https://img.shields.io/badge/license-MIT-blue" alt="License: MIT"></a>
  <img src="https://img.shields.io/badge/PHP-8.3%2B-777BB4?logo=php&logoColor=white" alt="PHP 8.3+">
  <img src="https://img.shields.io/badge/Laravel-13-FF2D20?logo=laravel&logoColor=white" alt="Laravel 13">
  <img src="https://img.shields.io/badge/PostgreSQL-first-4169E1?logo=postgresql&logoColor=white" alt="PostgreSQL first">
</p>

---

## 🚧 Status

**Magna is in active development and not yet ready for production.** We are building in public, spec-first: every subsystem is fully specified *before* it is coded, and the specifications live in this repository — [read them](#-documentation), challenge them, help shape them. Watch/star the repo to follow the road to 1.0. The [build plan](docs/build-plan.md) shows exactly where we are.

---

## What is Magna?

Magna is an open-source, **API-first headless CMS built on Laravel**. You model content, editors manage it in a clean admin panel, and any frontend — Next.js, Nuxt, Flutter, a native app, an AI agent — consumes it over a fast REST API.

It follows a **Small Core Architecture** with exactly two things in core:

1. **The Kernel** — authentication, users, RBAC, plugin system, events, settings, audit log, API infrastructure.
2. **The Content Engine** — content types, fields, entries, drafts, revisions, localization, relationships, media, scheduled publishing, and schema-as-code.

Everything else — blog, SEO, forms, e-commerce, AI — is a plugin. And a plugin is just a **Composer package** with a manifest.

> Keep the core small — but the core of a CMS is content.
> Everything that is not kernel or content is a plugin.

## Why Magna is different

The headless space is crowded (Strapi, Directus, Payload, Contentful, Sanity). Here is exactly what Magna does that they don't — no vague superlatives, every claim is specified in this repo and enforced in CI:

### 🐘 1. Laravel-native, PHP-hosting-friendly
Every major open-source headless CMS runs on Node. Magna brings a modern headless platform to the **millions of Laravel/PHP developers** and the vast world of ordinary PHP hosting — Eloquent-native extensibility, Composer-based plugins, deployable where Node frontends can't go. Statamic is Laravel but flat-file and theme-oriented; Twill is an admin package. The Laravel-native, database-backed, headless-first slot is empty. Magna fills it.

### 🔀 2. Hybrid mode — headless first, website optional
Headless purity has a famous cost: install it and you see… an API. Magna's answer is **Magna Pages**, an optional official plugin that turns the *same install* into a rendered website — block editor, themes, live preview — with zero second deployment, no CORS, no token handshake. Pages renders the exact JSON the API serves, so any Pages site can go fully headless later without a migration. Don't want it? Don't install it; the core stays purely headless. No major open-source headless CMS ships a credible "website out of the box" path. ([Frontend strategy](docs/Magna-v2.md))

### ⚡ 3. A performance contract, not performance adjectives
Magna publishes **CI-enforced latency budgets** — e.g. delivery API < 10 ms p99 cached / < 50 ms uncached against a 100k-entry dataset — with the benchmark harness *in this repo* so anyone can reproduce the numbers. Nightly CI fails on >10 % regression; every release publishes its deltas. And **tag-based cache invalidation is a core primitive**: every response carries surrogate keys, so publishing one entry purges exactly the affected responses — in Redis *and* at the edge (Cloudflare/Fastly/Varnish drivers built in). No "clear all cache" button as a way of life. ([Performance spec](docs/performance-spec.md))

### 🔐 4. Security as a process with proof
Target: **OWASP ASVS Level 2, verified by a third-party audit before 1.0**, results published. Hardened defaults (per-role enforceable 2FA, scoped expiring tokens, default-deny CORS, uploads re-encoded to strip payloads), signed releases with per-release SBOMs, and one genuine first: **field-level encryption as a schema attribute** — mark any content field `"encrypted": true` and it's encrypted at rest, no other mainstream open-source CMS offers that as a first-class primitive. We're also honest about limits: PHP cannot sandbox in-process plugins, so the plugin trust model is transparency + review gates + a kill switch — stated plainly instead of papered over. ([Security spec](docs/security-spec.md))

### 📐 5. Schema as code
Content types are versionable files. Build your model in the admin, export it, commit it, and `magna:schema:sync` replays it on staging and production — with a diff preview and destructive-change guards. Content modeling finally works like migrations: reviewable, repeatable, in git. Under the hood there's **no EAV**: each content type gets a real table with real columns and real indexes, generated from its schema.

### 🧩 6. Plugins without the malware economy
Plugins are Composer packages — versioning, dependency resolution, and distribution come from infrastructure the PHP world already trusts, not uploaded ZIP files (the WordPress model that made theme/plugin malware an industry). Every plugin declares its capabilities and permissions in a manifest that's **shown at install time, like phone app permissions**. Extension points are **typed PHP interfaces, semver-guaranteed from 1.0** — we will not break plugin authors inside a major version. ([Plugin guide](docs/plugin-development-guide.md))

### 🎨 7. Themes that can't hurt you
Magna Pages themes are **presentation-only packages** — Blade views, design tokens, templates, demo content. No PHP logic, no migrations, no hooks, enforced by tooling. The highest-volume marketplace category carries near-zero code risk, which is structurally impossible for WordPress themes. Design tokens auto-generate a Theme Options panel; a `pairsWith` field links themes to the plugins they're designed for (install a dating plugin + its theme → working product). ([Theme guide](docs/theme-development-guide.md))

### ✍️ 8. A block editor that respects your time (and ours)
Content is composed from **portable JSON blocks** — rendered as Blade views by Pages, or as your own React/Vue components headlessly. The editor is a structured block list with live preview via signed draft URLs (draft preview for Next.js/Nuxt is first-class, not an afterthought). We deliberately did **not** clone Gutenberg — a canvas editor is a multi-year detour; a great structured editor is 80 % of the value at 5 % of the cost.

## How it compares

| | **Magna** | Strapi | Directus | Payload | WordPress |
|---|---|---|---|---|---|
| Runtime | PHP / Laravel | Node | Node | Node/Next | PHP |
| Headless API | ✅ first-class | ✅ | ✅ | ✅ | ⚠️ bolt-on |
| Website out of the box | ✅ optional plugin | ❌ | ❌ | ⚠️ template | ✅ |
| Plugin distribution | Composer + manifest | npm | npm/ext. | npm | ZIP upload |
| Logic-free safe themes | ✅ enforced | — | — | — | ❌ full PHP |
| Published CI latency budgets | ✅ | ❌ | ❌ | ❌ | ❌ |
| Surrogate-key edge purge in core | ✅ | ❌ | ❌ | ❌ | ❌ |
| Field-level encryption in schema | ✅ | ❌ | ❌ | ❌ | ❌ |
| Schema as code w/ env sync | ✅ | ⚠️ partial | ⚠️ partial | ✅ | ❌ |
| Runs on ordinary PHP hosting | ✅ | ❌ | ❌ | ❌ | ✅ |

*(Comparisons reflect core capabilities at the time of writing; corrections welcome — open an issue.)*

## Architecture at a glance

```
                      ┌────────────────────────────────────────┐
  Next.js / Nuxt ───▶ │  REST Delivery API   (ETags, surrogate │
  Flutter / native ─▶ │  keys, cursor pagination, preview)     │
  AI agents ───────▶  ├────────────────────────────────────────┤
                      │  CONTENT ENGINE                        │
  Filament Admin ──▶  │  types • entries • drafts • revisions  │
                      │  media • localization • blocks         │
                      ├────────────────────────────────────────┤
                      │  KERNEL                                │
                      │  auth • RBAC • plugins • events        │
                      │  settings • audit • webhooks           │
                      └───────────────┬────────────────────────┘
                                      │ typed contracts
                      ┌───────────────┴────────────────────────┐
                      │  PLUGINS (Composer packages)           │
                      │  blog · seo · forms · pages · yours    │
                      └────────────────────────────────────────┘
```

**Stack:** Laravel 12 · PHP 8.3+ · PostgreSQL-first (MySQL/MariaDB/SQLite supported) · Filament 4 admin · Redis · FrankenPHP/Octane reference deployment.

## Quick start *(target DX — lands with the first alpha)*

```bash
composer create-project magna/magna my-site
php artisan magna:install          # DB, admin user, done
php artisan serve                  # admin at /admin, API at /api/v1
```

```bash
# a content API in three commands
php artisan magna:type:make article
php artisan magna:schema:sync
curl http://my-site.test/api/v1/content/article
```

## 📚 Documentation

Everything is specified before it's built — the specs are the contract, and they live here:

| Document | What it covers |
|---|---|
| [Project report](docs/Magna-v2.md) | Vision, architecture, market position, roadmap |
| [Plugin development guide](docs/plugin-development-guide.md) | Build a plugin: manifest, contracts, content types, testing |
| [Theme development guide](docs/theme-development-guide.md) | Build a theme: tokens, block views, pairing |
| [Default theme spec](docs/default-theme-spec.md) | The reference theme + the standard block library |
| [Performance spec](docs/performance-spec.md) | The budgets, the benchmark harness, the caching model |
| [Security spec](docs/security-spec.md) | ASVS target, defaults, supply chain, plugin trust model |
| [Store plan](docs/store-plan.md) | The official plugin/theme store, staged rollout |
| [Build plan](docs/build-plan.md) | Stage-by-stage implementation plan and current progress |

## 🗺️ Roadmap

- **Phase 1 — MVP core** *(in progress)*: kernel, content engine, media, REST API, Filament admin, plugin system, block editor
- **Phase 2 — Ecosystem**: plugin SDK & docs, localization, schema sync, Official Store (first-party catalog), third-party security audit, **1.0**
- **Phase 3 — Depth**: Magna Pages + themes, GraphQL, semantic search (pgvector), AI plugin, realtime
- **Phase 4 — Commercial**: Magna Cloud, store open to third-party publishers
- **Phase 5 — Enterprise**: multi-tenancy, SSO/SAML, compliance

## 🤝 Contributing

The most valuable contribution right now is **review of the specs** — they are the contract for everything that gets built, and changing a spec today is free while changing shipped behavior is not. Open an issue or discussion on any spec document. Code contributions: see `CONTRIBUTING.md` (coming with the first alpha); all PRs run the full gate — Pint, PHPStan level 9, Pest, and the performance suite.

## 🔒 Security

Found a vulnerability? **Do not open a public issue.** See [SECURITY.md](SECURITY.md) for coordinated disclosure. Security process, supported-versions policy, and audit results are documented in the [security spec](docs/security-spec.md).

## 📄 License

Magna CMS is open-source software licensed under the [MIT license](LICENSE). The "Magna" name and logo are trademarks; the [store plan](docs/store-plan.md) describes the commercial layer (official store, cloud) that funds core development — the core itself is MIT forever.

---

<p align="center"><sub>Built in the open. Star the repo to follow the road to 1.0. ⭐</sub></p>
