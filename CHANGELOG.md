## [1.7.0] - 2026-11-10

- fix(driver): `cursor()` streaming — the streaming path iterated the raw extension envelope (`columns`, `rows`, `rows_affected`, `last_insert_rowid`) instead of yielding rows, so `DB::table(...)->cursor()` never returned a correct row and skipped all cell decoding. It now normalizes the envelope (online stdClass / offline row list), decodes cells exactly like `select()` (double-base64, JSON vectors, resource blobs) and yields `stdClass` rows per Laravel's cursor contract
- fix(driver, data corruption): blob round-trip transport now writes an unambiguous `libsqlblob:` marker; previously, any text cell that happened to be canonical, printable base64 — e.g. the Faker word `bzwobkku` — was silently double-decoded into garbage bytes. This is what made CI leg `8.3, 12` fail deterministically under fixed random-order seeds (`-'bzwobkku' +'¢r'`). The legacy double-base64 heuristic stays for unmarked data but no longer fires on plain alphabetic words
- fix(statement): `fetch()` delegates to the shared decoder instead of its own inline single-base64 pass (which corrupted base64-looking text); `fetchAll()` no longer re-encodes output rows through the input binder `parameterCasting()`
- test: regression test for `cursor()` in `DatabaseQueriesTest`; suite green with the exact CI seed `1794325993` plus a 4-seed sweep; `pint --test` passes; `docker/proof/proof.sh` green with this code (PROOF 42/42, HTTP 8/8, OFFLINE-WRITE 9/9, OFFLINE-SYNC 5/5, `all_ok` everywhere, outage/blind-window and post-sync server reads verified by an independent host-side Hrana client)
- docs: README trimmed to Wiki links (single source of truth for the full manual; no content removed, only duplicated detail moved)
- compat: data written by 1.7.0 uses the `libsqlblob:` marker and needs 1.7.0+ to read; unmarked data written by earlier releases is still decoded via the legacy heuristic

## [1.6.0] - 2026-11-10

- feat(driver, multi-connection): `db.factory` extension registered **by connection name** — every statically configured `driver: libsql` connection is now extended individually, each with its own raw config. Previously the facade extension was registered only for the connection named `libsql` and forced the default `libsql` config onto every connection — any second libsql connection silently shared the default's memory configuration, making remote/replica modes unusable in multi-connection apps. The 6 server-dependent tests (previously always skipped) now run and pass in CI against seeded sqld services
- feat(ci): 7-job test matrix — Laravel 12 on PHP 8.2/8.3/8.4/8.5 and Laravel 13 on PHP 8.3/8.4/8.5 (L13 legs rewrite `composer.json` to require `orchestra/testbench ^11`; Laravel 13 needs PHP 8.3+); two seeded sqld service containers (8081/8082) initialize WAL metadata so embedded replicas can sync
- feat(offline-cloud): Turso Cloud offline-writes flavor now ships a runnable verification — `php artisan proof:offline-cloud` (12 asserts: bootstrap sync → local journaled writes → `sync()` push → plain remote read-back → pushed drop) plus env-gated `TursoCloudOfflineWriteTest` (skips without credentials) and `docker/proof/offline-cloud.sh`
- compat: `LibSQLConnection::select()`, `selectResultSets()` and `cursor()` accept the optional `array $fetchUsing = []` parameter that Laravel 13 added to the parent signature (optional — Laravel 12 compatibility preserved)
- test(replica): embedded-replica tests are isolated per test (unique replica file + WAL/SHM sidecar cleanup) and retry on stale-replica opens ("Can not sync a database without a wal_index")
- fix(fixtures): factory-generated names are now collision-proof (`unique()->regexify`), eliminating the random-order `1 matches expected 2` CI failure
- docs: README corrected — Laravel 12 or 13 only; all unverifiable promises removed (no adapter-in-development, no Laravel Sail section); Requirements section reflects only physically-proven facts
- security(docs): added `SECURITY.md` — supported versions, private reporting via GitHub advisories, coordinated disclosure, extension issues cross-linked to `turso-client-php`
- deps(security): `composer audit` reports **0 advisories** (18 resolved in 1.5.0 cycle are retained); `composer.lock` pinned to PHP 8.2 platform so it installs across the whole matrix

## [1.5.0] - 2026-11-10

- feat(offline): offline writes now work through the driver — when `sqldOfflineMode` and `offlineWrites` are set (env `DB_SQLD_OFFLINE_MODE`, `DB_OFFLINE_WRITES`, extension ≥ 1.7.0), statements bind parameters and route through the extension's offline-safe `execute()`/`query()`; DDL (e.g. `CREATE TABLE`) and DML journal locally while the app is offline, and `sync()`/re-open replicates them to the server. The extension refuses `prepare()` in offline-write mode, which previously made every statement fail
- feat(docker): isolated proof-of-usage harness (`docker/proof/`) — Laravel 12 app built fresh, driver installed via `turso-php:install`, assertions through `php artisan` (42) and through a real nginx → php-fpm tier over HTTP (8), plus a real outage window (`docker network disconnect`) proving journaled writes, blind server and `sync()` drain (9 + 5); user-facing guide in `docker/proof/README.md`
- security(deps): resolved all 18 open advisories across 8 dev packages (phpunit 11.5.15→11.5.57, psy/psysh, symfony/http-foundation|kernel|mime|routing|yaml (7.4-pinned for PHP 8.3 CI), zbateson/mail-mime-parser 4.0.1→4.0.2) — `composer audit` is clean
- fix(statement): normalized the extension's two `fetchArray()` shapes (online envelope vs plain row list in offline mode) and the `rows`-key absence on DDL results
- docs(README): professional restructure (Requirements/Installation/Configuration/Usage/Testing, standard OSS order, corrected stale "offline writes unsupported" status note, no emoji)
- docs(README): documented throw-behavior for failed writes + offline-write flavors (Turso Cloud vs sqld)
- fix(tests): skip server-dependent tests without eagerly hitting the DB in hooks (Pest/PHPUnit bump made the old `markTestSkipped()` pattern leak through `beforeEach`/`afterEach`)

## [1.4.0] - 2026-11-10

- fix(encryption): `DB_ENCRYPTION_KEY` actually reaches the libSQL extension — it was always a dead positional argument before extension v1.7.0 positional args are now passed positionally (config `encryptionKey`) so database encryption works from creation
- feat(config): new `sqldOfflineMode` / `offlineWrites` config keys (env `DB_SQLD_OFFLINE_MODE`, `DB_OFFLINE_WRITES`) — sqld offline-write mode and Turso Cloud offline writes, feature-detected across extension generations (1.6.x vs 1.7.0+ constructors)
- fix(ide): vendored IDE stubs refreshed from extension `v1.7.0` (51 methods, new `prepare()`/`fetchSingle()`/CDC/`sync()`/`isOnline()` surface, renamed `src/libsql_php.stubs.php`)
- chore(deps): `darkterminal/turso-php-installer` ^2.2 — metadata-based install detection (fixes false "already installed" reruns), PHP 8.1–8.5 assets, renamed turso-client-php release channel serving extension 1.7.0
- fix(statement): rethrow failed writes as `PDOException` → Laravel `QueryException` (fixes #113, PR #114) [William Isted]
- security(vectors): validate identifiers, vector components and limit in `VectorMacro` (PR #107) [jonathanagustin]
- ci: actions bumps folded (checkout 7, dependabot/fetch-metadata 3.1.0, git-auto-commit-action 7) [dependabot[bot]]

## [1.3.2] - 2026-11-10

- fix(statement): rethrow failed writes (constraints, binding errors) as `PDOException` — Laravel surfaces a `QueryException`, silent drops are over (fixes #113, PR #114) [William Isted]
- security(vectors): validate identifiers, vector components and limit in `VectorMacro` — `DB::raw()` / `vector_top_k()` interpolation accepted request-shaped strings (PR #107) [jonathanagustin]
- chore(deps): require `darkterminal/turso-php-installer` ^2.2 — metadata-based install detection in `turso-php:install`, renamed turso-client-php release channel with extension 1.7.0 (PHP 8.1–8.5) (fixes the rerun false-positive class of #66) [Imam Ali Mustofa]
- ci: actions bumps folded (checkout 7, dependabot/fetch-metadata 3.1.0, git-auto-commit-action 7) [dependabot[bot]]
- deps: automated Composer maintenance updates throughout the cycle (laravel/pint, phpstan suite, laravel-package-tools) [dependabot[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.18 to 2.0.21 [dependabot[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.93.2 to 1.93.3 [dependabot[bot]]
- Merge pull request #108 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.93.2 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.93.1 to 1.93.2 [dependabot[bot]]
- Validate identifiers, vector components and limit in VectorMacro [jonathanagustin]
- Merge pull request #106 from tursodatabase/dependabot/composer/laravel/pint-1.30.4 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.30.0 to 1.30.4 [dependabot[bot]]
- Merge pull request #105 from tursodatabase/dependabot/composer/laravel/pint-1.30.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.29.3 to 1.30.0 [dependabot[bot]]
- Merge pull request #104 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.18 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.16 to 2.0.18 [dependabot[bot]]
- Merge pull request #103 from tursodatabase/dependabot/composer/laravel/pint-1.29.3 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.29.1 to 1.29.3 [dependabot[bot]]
- build(deps): bump actions/checkout from 4 to 7 [dependabot[bot]]
- Merge pull request #101 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.93.1 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.93.0 to 1.93.1 [dependabot[bot]]
- Merge pull request #100 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.9 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.8 to 1.43.9 [dependabot[bot]]
- Merge pull request #99 from tursodatabase/dependabot/composer/laravel/pint-1.29.1 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.29.0 to 1.29.1 [dependabot[bot]]
- Merge pull request #98 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.8 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.7 to 1.43.8 [dependabot[bot]]
- build(deps): bump dependabot/fetch-metadata from 2.5.0 to 3.1.0 [dependabot[bot]]
- Merge pull request #95 from tursodatabase/dependabot/composer/laravel/pint-1.29.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.27.1 to 1.29.0 [dependabot[bot]]
- Merge pull request #94 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.7 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.6 to 1.43.7 [dependabot[bot]]
- Merge pull request #91 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.16 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.13 to 2.0.16 [dependabot[bot]]
- Merge pull request #93 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.6 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.5 to 1.43.6 [dependabot[bot]]
- Merge pull request #92 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.93.0 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.92.7 to 1.93.0 [dependabot[bot]]
- Merge pull request #90 from tursodatabase/dependabot/composer/laravel/pint-1.27.1 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.27.0 to 1.27.1 [dependabot[bot]]
- Merge pull request #89 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.13 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.12 to 2.0.13 [dependabot[bot]]
- Merge pull request #88 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.5 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.3 to 1.43.5 [dependabot[bot]]
- Merge pull request #87 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.12 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.11 to 2.0.12 [dependabot[bot]]
- Merge pull request #84 from tursodatabase/dependabot/composer/laravel/pint-1.27.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.26.0 to 1.27.0 [dependabot[bot]]
- Merge pull request #86 from tursodatabase/dependabot/composer/orchestra/testbench-10.9.0 [github-actions[bot]]
- build(deps-dev): bump orchestra/testbench from 10.8.0 to 10.9.0 [dependabot[bot]]
- Merge pull request #85 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.3 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.2 to 1.43.3 [dependabot[bot]]
- Merge pull request #83 from tursodatabase/dependabot/github_actions/dependabot/fetch-metadata-2.5.0 [github-actions[bot]]
- build(deps): bump dependabot/fetch-metadata from 2.4.0 to 2.5.0 [dependabot[bot]]
- Merge pull request #82 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.11 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.10 to 2.0.11 [dependabot[bot]]
- Merge pull request #81 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.2 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.43.1 to 1.43.2 [dependabot[bot]]
- Merge pull request #80 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.10 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.8 to 2.0.10 [dependabot[bot]]
- Merge pull request #79 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.43.1 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.42.0 to 1.43.1 [dependabot[bot]]
- Merge pull request #78 from tursodatabase/dependabot/composer/laravel/pint-1.26.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.25.1 to 1.26.0 [dependabot[bot]]
- Merge pull request #76 from tursodatabase/dependabot/composer/orchestra/testbench-10.8.0 [github-actions[bot]]
- build(deps-dev): bump orchestra/testbench from 10.6.0 to 10.8.0 [dependabot[bot]]
- Merge pull request #77 from tursodatabase/dependabot/composer/nunomaduro/collision-8.8.3 [github-actions[bot]]
- build(deps-dev): bump nunomaduro/collision from 8.8.2 to 8.8.3 [dependabot[bot]]
- Merge pull request #75 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.42.0 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.41.0 to 1.42.0 [dependabot[bot]]
- Merge pull request #73 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.8 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.7 to 2.0.8 [dependabot[bot]]
- Remove Override attribute from typeVector method [Peter Ade-Ojo]
- Merge pull request #70 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.41.0 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.40.3 to 1.41.0 [dependabot[bot]]
- build(deps): bump stefanzweifel/git-auto-commit-action from 5 to 7 [dependabot[bot]]
- Merge pull request #68 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.40.3 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.40.2 to 1.40.3 [dependabot[bot]]
- Merge pull request #67 from tursodatabase/dependabot/composer/laravel/pint-1.25.1 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.24.0 to 1.25.1 [dependabot[bot]]
- Merge pull request #62 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.7 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.6 to 2.0.7 [dependabot[bot]]
- Merge pull request #64 from tursodatabase/dependabot/composer/orchestra/testbench-10.6.0 [github-actions[bot]]
- build(deps-dev): bump orchestra/testbench from 10.4.0 to 10.6.0 [dependabot[bot]]
- Merge pull request #63 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.92.7 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.92.6 to 1.92.7 [dependabot[bot]]
- Merge pull request #61 from tursodatabase/dependabot/github_actions/aglipanci/laravel-pint-action-2.6 [github-actions[bot]]
- build(deps): bump aglipanci/laravel-pint-action from 2.5 to 2.6 [dependabot[bot]]
- Merge pull request #60 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.92.6 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.92.4 to 1.92.6 [dependabot[bot]]
- Merge pull request #59 from tursodatabase/dependabot/composer/laravel/pint-1.24.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.23.0 to 1.24.0 [dependabot[bot]]
- Merge pull request #58 from tursodatabase/dependabot/composer/laravel/pint-1.23.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.22.1 to 1.23.0 [dependabot[bot]]
- Merge pull request #57 from tursodatabase/dependabot/composer/nunomaduro/collision-8.8.2 [github-actions[bot]]
- build(deps-dev): bump nunomaduro/collision from 8.8.1 to 8.8.2 [dependabot[bot]]
- Merge pull request #55 from tursodatabase/dependabot/composer/nunomaduro/collision-8.8.1 [github-actions[bot]]
- build(deps-dev): bump nunomaduro/collision from 8.8.0 to 8.8.1 [dependabot[bot]]
- Merge pull request #54 from tursodatabase/dependabot/composer/orchestra/testbench-10.4.0 [github-actions[bot]]
- build(deps-dev): bump orchestra/testbench from 10.3.0 to 10.4.0 [dependabot[bot]]
- chore: update CHANGELOG.md for patch version 1.3.1 [Imam Ali Mustofa]

## [1.3.1] - 2025-05-28

- release: patch version 1.3.1 [Imam Ali Mustofa]
- fix(upgrade): schema builder and grammar [Imam Ali Mustofa]
- chore: update CHANGELOG.md for minor version 1.3.0 [Imam Ali Mustofa]

## [1.3.0] - 2025-05-28

- release: minor version 1.3.0 [Imam Ali Mustofa]
- fix: binary encoding for blob [Imam Ali Mustofa]
- build(deps-dev): bump orchestra/testbench from 9.11.0 to 10.3.0 [dependabot[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.40.1 to 1.40.2 [dependabot[bot]]
- Merge pull request #53 from tursodatabase/dependabot/github_actions/dependabot/fetch-metadata-2.4.0 [github-actions[bot]]
- build(deps): bump dependabot/fetch-metadata from 2.3.0 to 2.4.0 [dependabot[bot]]
- Merge pull request #51 from tursodatabase/dependabot/composer/laravel/pint-1.22.1 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.22.0 to 1.22.1 [dependabot[bot]]
- Merge pull request #49 from tursodatabase/dependabot/composer/pestphp/pest-3.8.2 [github-actions[bot]]
- build(deps-dev): bump pestphp/pest from 3.8.1 to 3.8.2 [dependabot[bot]]
- Merge pull request #48 from tursodatabase/dependabot/composer/laravel/pint-1.22.0 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.21.2 to 1.22.0 [dependabot[bot]]
- Merge pull request #46 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.92.4 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.92.0 to 1.92.4 [dependabot[bot]]
- chore: add git ads [Imam Ali Mustofa]
- Merge pull request #45 from tursodatabase/dependabot/composer/nunomaduro/collision-8.8.0 [github-actions[bot]]
- build(deps-dev): bump nunomaduro/collision from 8.7.0 to 8.8.0 [dependabot[bot]]
- Merge pull request #44 from tursodatabase/dependabot/composer/pestphp/pest-3.8.1 [github-actions[bot]]
- build(deps-dev): bump pestphp/pest from 3.8.0 to 3.8.1 [dependabot[bot]]
- Merge pull request #42 from tursodatabase/dependabot/composer/phpstan/phpstan-phpunit-2.0.6 [github-actions[bot]]
- build(deps-dev): bump phpstan/phpstan-phpunit from 2.0.4 to 2.0.6 [dependabot[bot]]
- Merge pull request #40 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.92.0 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.91.1 to 1.92.0 [dependabot[bot]]
- Merge pull request #39 from tursodatabase/dependabot/composer/pestphp/pest-3.8.0 [github-actions[bot]]
- build(deps-dev): bump pestphp/pest from 3.7.4 to 3.8.0 [dependabot[bot]]
- Merge pull request #38 from tursodatabase/dependabot/composer/spatie/laravel-package-tools-1.91.1 [github-actions[bot]]
- build(deps): bump spatie/laravel-package-tools from 1.19.0 to 1.91.1 [dependabot[bot]]
- Merge pull request #37 from tursodatabase/dependabot/composer/laravel/pint-1.21.2 [github-actions[bot]]
- build(deps-dev): bump laravel/pint from 1.21.0 to 1.21.2 [dependabot[bot]]
- Merge pull request #36 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.40.1 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.40.0 to 1.40.1 [dependabot[bot]]
- Merge pull request #35 from tursodatabase/dependabot/composer/nunomaduro/collision-8.7.0 [github-actions[bot]]
- build(deps-dev): bump nunomaduro/collision from 8.6.1 to 8.7.0 [dependabot[bot]]
- Merge pull request #34 from tursodatabase/dependabot/composer/spatie/laravel-ray-1.40.0 [github-actions[bot]]
- build(deps-dev): bump spatie/laravel-ray from 1.39.1 to 1.40.0 [dependabot[bot]]
- Merge pull request #31 from tursodatabase/dependabot/composer/darkterminal/turso-php-installer-2.1.0 [github-actions[bot]]
- build(deps): bump darkterminal/turso-php-installer from 2.0.16 to 2.1.0 [dependabot[bot]]
- chore: update CHANGELOG.md for patch version 1.2.10 [Imam Ali Mustofa]

## [1.2.10] - 2025-02-27

- release: patch version 1.2.10 [Imam Ali Mustofa]
- fix: database path pointing [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.9 [Imam Ali Mustofa]

## [1.2.9] - 2025-02-27

- release: patch version 1.2.9 [Imam Ali Mustofa]
- fix: undefined method getAttribute [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.8 [Imam Ali Mustofa]

## [1.2.8] - 2025-02-27

- release: patch version 1.2.8 [Imam Ali Mustofa]
- fix: make it compatible with laravel 12 [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.7 [Imam Ali Mustofa]

## [1.2.7] - 2025-02-27

- release: patch version 1.2.7 [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.6 [Imam Ali Mustofa]

## [1.2.6] - 2025-02-27

- release: patch version 1.2.6 [Imam Ali Mustofa]
- deps: update all dependencies [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.5 [Imam Ali Mustofa]

## [1.2.5] - 2025-02-27

- release: patch version 1.2.5 [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.4 [Imam Ali Mustofa]

## [1.2.4] - 2025-02-27

- release: patch version 1.2.4 [Imam Ali Mustofa]
- Merge pull request #30 from tursodatabase/dependabot/github_actions/aglipanci/laravel-pint-action-2.5 [github-actions[bot]]
- build(deps): bump aglipanci/laravel-pint-action from 2.4 to 2.5 [dependabot[bot]]
- tests: use different database connection driver [Imam Ali Mustofa]
- Merge pull request #28 from tursodatabase/dependabot/github_actions/dependabot/fetch-metadata-2.3.0 [github-actions[bot]]
- build(deps): bump dependabot/fetch-metadata from 2.2.0 to 2.3.0 [dependabot[bot]]
- Fix styling [darkterminal]
- chore: update CHANGELOG.md for patch version 1.2.3 [Imam Ali Mustofa]

## [1.2.3] - 2025-01-21

- release: patch version 1.2.3 [Imam Ali Mustofa]
- refactor: missing strict types [Imam Ali Mustofa]
- fix: indexes, view, foreignkey, tables in database command related [Imam Ali Mustofa]
- fix: preg_match can be null [Imam Ali Mustofa]
- refactor(feat): add line in artisan db:show command [Imam Ali Mustofa]
- fix(provider): ensure libSQL is only used when set as the default connection [Imam Ali Mustofa]
- build(deps-dev): update phpstan/phpstan-phpunit requirement [dependabot[bot]]
- build(deps): bump actions/checkout from 3 to 4 [dependabot[bot]]
- chore(traits): using new traits [Imam Ali Mustofa]
- Merge pull request #24 from eznix86/chore/rename-concern [Imam Ali Mustofa]
- build(refactor): using parallel tests [Imam Ali Mustofa]
- chore: update CHANGELOG.md for patch version 1.2.2 [Imam Ali Mustofa]
- chore: add parallel testing [Bruno Bernard]
- refactor: rename trait to concern [Bruno Bernard]

## [1.2.2] - 2025-01-17

- release: patch version 1.2.2 [Imam Ali Mustofa]
- feat(refactor): complete turso-php artisan command and refactoring [Imam Ali Mustofa]
- Fix styling [darkterminal]
- fix(tests): fix schema builder test [Imam Ali Mustofa]
- chore(build): add emoji and composer update hook [Imam Ali Mustofa]
- Fix styling [darkterminal]
- chore: update CHANGELOG.md for patch version 1.2.1 [Imam Ali Mustofa]

## [1.2.1] - 2025-01-15

- release: patch version 1.2.1 [Imam Ali Mustofa]
- fix(schema): expected column types schema [Imam Ali Mustofa]
- docs: add tests badge [Imam Ali Mustofa]
- Fix styling [darkterminal]
- chore: update CHANGELOG.md for minor version 1.2.0 [Imam Ali Mustofa]

## [1.2.0] - 2025-01-14

- release: minor version 1.2.0 [Imam Ali Mustofa]
- fix(feature): also add vectory data type tests [Imam Ali Mustofa]
- chore: commit save point [Imam Ali Mustofa]
- docs: update database configuration [Imam Ali Mustofa]
- chore: commit save point [Imam Ali Mustofa]
- chore(tests): complete all tests [Imam Ali Mustofa]
- chore: commit save point [Imam Ali Mustofa]
- docs: update the docs and add community sdk annotation [Imam Ali Mustofa]
- docs: include laravel sail setup guide [Imam Ali Mustofa]
- Fix styling [darkterminal]
- docs: include new turso-php artisan command [Imam Ali Mustofa]
- Fix styling [darkterminal]
- chore: update CHANGELOG.md for patch version 1.1.1 [Imam Ali Mustofa]
- release: patch version 1.1.1 [Imam Ali Mustofa]
- feat(vector): add vector data type support [Imam Ali Mustofa]
- Fix styling [darkterminal]

## [1.1.1] - 2025-01-10

- release: patch version 1.1.1 [Imam Ali Mustofa]
- feat(vector): add vector data type support [Imam Ali Mustofa]
- chore: update CHANGELOG.md for minor version 1.1.0 [Imam Ali Mustofa]

## [1.1.0] - 2025-01-09

- release: minor version 1.1.0 [Imam Ali Mustofa]
- feat: porting turso-php-installer into artisan [Imam Ali Mustofa]
- feat: register turso-php commands [Imam Ali Mustofa]
- refactor: add constructor into connection factory [Imam Ali Mustofa]
- build(composer): add release script [Imam Ali Mustofa]
- build: create release script [Imam Ali Mustofa]

# Changelog

All notable changes to `turso-driver-laravel` will be documented in this file.
