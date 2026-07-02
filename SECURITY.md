# Security Policy

## Reporting a vulnerability

**Please do not open a public issue for security vulnerabilities.**

Report privately via [GitHub Security Advisories](../../security/advisories/new)
("Report a vulnerability" on the repository's Security tab). You will receive an
acknowledgement within 72 hours.

We follow coordinated disclosure: we ask for up to 90 days to ship a fix before
public disclosure, and we credit reporters in the release notes unless they
prefer otherwise.

## Supported versions

Magna CMS is **pre-release software in active development** — there are no
supported production versions yet. The supported-versions and backport policy
(current + previous minor, security-only) takes effect at 1.0, per
[docs/security-spec.md](docs/security-spec.md).

## Security architecture

The full security specification — hardened defaults, plugin trust model,
supply-chain gates, and the pre-1.0 third-party audit commitment — lives in
[docs/security-spec.md](docs/security-spec.md).
