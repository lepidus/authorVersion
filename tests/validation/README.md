# OPS 3.5 port validation

Validated on 2026-10-02 against OPS 3.5.0.5, PHP 8.4.21 and MariaDB
in an ephemeral VM. The VM was discarded after the successful run.
External email delivery was replaced by the isolated test mail service.

| Check | Result |
| --- | --- |
| Core PHPUnit integration | 13 tests, 62 assertions passed |
| Cypress workflows and public API authorization | 31 tests across 7 specs passed |
| Isolated JavaScript regressions | 13 tests passed |
| Minimum PHP 8.2.34 | Syntax checks for all 17 PHP files and 55 isolated endpoint checks passed |
| PSR-12 | PHP-CS-Fixer dry run passed |
| Locales | `msgfmt` and key parity passed for en, es and pt_BR |
| Independent review | Sol, medium effort; fixes integrated and affected checks rerun |

The browser suite covers creation, submission, justification, dashboard views,
relations and deletion. The API suite covers owner and other authors, assigned
and unassigned moderators, stale or invalid publication IDs, missing CSRF,
cross-context requests and persistence after denied operations. Native core
authorization is used as the comparison for protected submission access.

Two dedicated author endpoints reuse the native OPS submission and production
policies. Creation works around the core's duplicate version route losing the
author role. Relation updates avoid the core's nested context ID array in the
response mapping. Editors continue to use the native workflow endpoints.

The full integration run used PHP 8.4 and MariaDB. PHP 8.2 was checked for
syntax and isolated endpoint behavior, without a complete OPS installation.
PostgreSQL and the remote CI pipeline were not executed during this validation.
Validation preceded commit and merge request creation. No release, package or
deployment was performed; `version.xml` is unchanged.

## Evidence

The machine-readable gate result is [results.json](results.json), based on
[contract.json](contract.json). The executor target is [ops-3.5.json](ops-3.5.json).

Local runtime artifacts (temporary paths):

- Final integration: `/tmp/authorVersion-validation/20261002-182057-2865694`
- Minimum PHP: `/tmp/authorVersion-validation/20261002-181554-2859514/targets/ops-3.5/php82-syntax-regression.log`

Pinned integration images:

- OPS: `lepidustecnologia/ops@sha256:591229190f11129a0cfa8ec9eb31762857d21f1b0dae4ad3ee398370775de061`
- Database: `mariadb@sha256:628f228f0fd5913a220438693576b29b6fe4dc1fa0a1298c0e98579fae28635f`

## Low-severity security corrections (2026-10-02)

Two findings are addressed on `security/ops-3.5-low`, based on the OPS 3.5
port commit `ff1739478bd627f2937dedfeb44527358f687cfd`:

- SEC-AV-001 / CWE-116: encode justification text at the notification-mail
  boundary, preserving persisted text. The real PKP mail compiler test failed
  with raw attacker markup before the fix, then passed with 14 PHPUnit tests
  and 70 assertions.
- SEC-AV-002 / CWE-841: validate the posted relation lock before native or
  plugin API persistence. Author attempts include clearing, changing, omitting
  or nulling the DOI; readback must retain the original relation and DOI, and
  subsequent version creation must remain denied. Native production policies
  retain editorial access.

The relation regression failed before the fix (native API returned HTTP 200
instead of 400; other 32 Cypress tests passed). With the fix, the four native
author requests return 400 and editorial requests return 200; each request is
followed by persisted-state readback. The final test requires editorial HTTP
200, tightening the initial test after confirming the effective image behavior.

PHP 8.2.34 lint passed for all 18 PHP files, including the new email test;
55 isolated endpoint checks also passed. The full runtime uses PHP 8.4.21 and
MariaDB. Minimum-PHP runtime integration and PostgreSQL remain untested.
An independent Sol medium review found no blocking defect. Remaining test gaps:
all editorial role/assignment combinations, trusted import execution and
literal encoded-entity input in the email test. Imports are excluded by the
API router check and were inspected statically.

Security artifacts (temporary local paths):

- Email failing test: `/tmp/authorVersion-validation/20261002-223701-3044875`
- Email passing suite: `/tmp/authorVersion-validation/20261002-223752-3047214`
- Relation failing integration: `/tmp/authorVersion-validation/20261002-223903-3049983`
- Corrected full integration: `/tmp/authorVersion-validation/20261002-224232-3056844`
- Minimum PHP lint: `/tmp/authorVersion-security-php82.log`

The security MR targets `port/ops-3.5` and depends on the port MR !22.
No release, tag, package or deployment is part of these corrections.
