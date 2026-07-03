# Magna CMS — Security Specification

**Status: Specification v0.1 (pre-implementation).**
Principle: security is a **process with proof**, not a feature list. Baseline target: **OWASP ASVS Level 2** verified before 1.0, with the verification results published. What PHP cannot guarantee (true plugin sandboxing) is stated honestly, not papered over.

---

## 1. Secure defaults (the install is hardened, not hardenable)

- Argon2id password hashing; 2FA (TOTP) available to all users, **enforceable per role** (e.g., require for admins); session hardening (secure/httponly/samesite, rotation on privilege change).
- API tokens are scoped (read-only delivery vs. management), expiring by default, shown once, hashed at rest, revocable per token; per-token rate limits.
- **Default-deny CORS** on management APIs; delivery CORS explicit per site.
- Security headers out of the box: strict CSP on the admin panel, HSTS, `frame-ancestors 'none'`, referrer policy, `X-Content-Type-Options`.
- Uploads: allowlist by content-sniffed type (not extension), images re-encoded on ingest (strips embedded payloads + EXIF), SVGs sanitized, uploads served from a separate origin/disk with no execute permissions.
- All preview/media signed URLs are expiring and scope-bound.
- Registration disabled by default; brute-force lockouts on all auth endpoints.

## 2. Data protection

- Settings marked `secret` are encrypted at rest and never leave via export/API.
- **Field-level encryption as a schema attribute** (`"encrypted": true` on any content field) — searchable-blind, key via app key or KMS. No mainstream open-source CMS offers this as a first-class schema feature; it is Magna's headline data-protection differentiator (dating app example: private bios, contact details).
- Append-only audit log in core: auth events, permission/role changes, plugin enable/disable, settings changes, destructive content ops — with actor, IP, and before/after where applicable. Exportable to SIEM (JSON lines / syslog).
- GDPR tooling in core: per-user data export and erasure hooks that plugins must implement (`HandlesPersonalData` contract) — erasure cascades through plugins or the request is flagged incomplete.

## 3. Supply chain & release integrity

- `composer audit` + Dependabot in CI; builds fail on known-vulnerable dependencies.
- Static analysis gates: PHPStan level 9 on core; Psalm taint analysis on the API and auth paths.
- **Signed releases + SBOM (CycloneDX) published per release**; reproducible dist builds where tooling allows (Sigstore provenance when PHP ecosystem support matures).
- Supported-versions matrix published from 1.0: security backports for the current and previous minor, and an LTS designation from 2.0.

## 4. Plugin & theme security (honest model)

PHP cannot sandbox in-process plugins — anyone claiming otherwise is lying, and Magna won't. The model is **transparency + gates + response**, layered:

1. **Declared capabilities**: `magna.json` permissions/provides shown at install (already specified). Store static analysis rejects undeclared behavior it can detect (eval, raw core-table writes, undeclared outbound HTTP).
2. **Themes are categorically safe**: no PHP execution (enforced), so the highest-volume marketplace category carries near-zero code risk — structurally better than WordPress, where themes are a primary infection vector.
3. **Response**: store kill switch flags malicious/vulnerable versions; admin dashboards surface advisories for installed packages (an installed-package vulnerability feed — core phones the store's advisory API, opt-out).

**Context for the "PHP sandbox" criticism:** in-process plugins are equally unsandboxed in every major CMS on every runtime — Strapi and Payload load npm plugins into the same Node process with the same full-compromise blast radius; no mainstream CMS on any runtime ships true plugin isolation. PHP's shared-nothing model is actually *more* resilient to plugin memory leaks than a long-lived Node process (a leak dies with the request under PHP-FPM). The genuine exposure is worker mode, addressed below.

**Committed mitigations (roadmap):**

4. **Worker recycling is a documented requirement** of the Octane/FrankenPHP reference deployment (`--max-requests` + memory-threshold restarts): a leaking plugin costs one worker restart, never the site. Lands with the Phase-1 deploy guide.
5. **"Remote apps" plugin tier (Phase 4+, with the open store):** a second integration tier for untrusted third parties — apps that run as separate services and integrate only via the API, webhooks, and declared UI extension points. Isolated by construction (the Shopify app model). In-process Composer plugins remain the powerful, reviewed tier; remote apps become the safe default for unreviewed third-party integrations. No open-source CMS offers this today.

## 5. Process & proof

- `SECURITY.md`, `security.txt`, and a disclosure policy from the first public release; 90-day coordinated disclosure; CVE assignment via GitHub Security Advisories.
- **Third-party penetration test + ASVS L2 assessment before 1.0** (budget line item, not aspiration); summary published. Annual re-audit; bug bounty when funding allows (Stage 2+).
- Security regression tests: every fixed vulnerability gets a permanent test.
- Threat model document maintained per release (STRIDE over the four trust boundaries: public API, admin, plugins, store).
- Phase 5 alignment: the audit log, RBAC, SSO/SAML, and SIEM export land earlier *because* they're specified here — enterprise security is not retrofitted.

## 6. The honest claim

"Industry-first" in security means, concretely: field-level encryption as a schema primitive, logic-free themes as a category, published ASVS verification, and per-release SBOMs — a combination no open-source CMS ships today. Magna claims exactly that list and nothing vaguer.
