# Security Policy

## Supported Versions

| Version | Supported |
|---------|-----------|
| 1.x     | ✅ current line — actively maintained |

Only the latest minor release line receives security fixes. If you are pinned
to an older line, please upgrade before reporting — we cannot backport every
fix indefinitely.

## Reporting a Vulnerability

**Please do not open a public issue for security reports.**

Report privately through GitHub's private vulnerability reporting:

https://github.com/tursodatabase/turso-driver-laravel/security/advisories/new

That channel notifies the maintainer(s) directly and keeps the report
confidential until a fix is released.

If you cannot use GitHub's advisory flow, reach out to the maintainer via the
[Turso Discord](https://discord.gg/9eAaMnJtfY) (see the badges at the top of
the README) and ask for a private contact for the report.

Include in your report, as much as you can:

- affected package version(s) and the PHP / extension version
- the connection flavor involved (local / memory / remote / embedded replica /
  offline writes)
- a minimal reproduction (a small test case or a Docker compose snippet is
  preferred — see `docker/proof/` for examples)
- the impact and your assessment of severity

## What to Expect

- Acknowledgement of your report as soon as possible (typically within a few
  business days)
- An assessment of validity and severity
- Coordinated disclosure: a fix is developed privately, a patch release is cut
  (e.g. `1.5.x`), and only then is the report made public in the advisory
- Credit in the advisory (unless you ask otherwise)

## Scope Notes for This Package

- This package bridges Laravel and the native `libsql_php` extension. The
  extension has its own security policy and repository
  ([turso-client-php](https://github.com/tursodatabase/turso-client-php)) —
  issues in the extension should also be reported there or cross-linked.
- SQL injection through the query builder, Eloquent or raw expressions,
  authentication-token handling in `.env`, and encryption-at-rest behavior are
  all in scope.
- Issues in the Turso Cloud service itself belong in the Turso support
  channels, not here — this policy covers the driver package.
