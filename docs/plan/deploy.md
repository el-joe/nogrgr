# Deploy steps

Run from `backend/` on every release, in this order. Every step is idempotent, so a
failed deploy can simply be re-run.

```bash
php artisan down                                 # optional: maintenance mode
composer install --no-dev --optimize-autoloader
npm ci && npm run build

php artisan migrate --force                      # central database
php artisan tenants:migrate --force              # every store database

php artisan permissions:sync                     # PermissionCatalog → central + every store (QA-P5-019)
php artisan localization:sync-static-texts       # new storefront base keys → every store (decision #57)
php artisan finance:backfill-ledger              # ledger postings missing or drifted from ORDER_FORMULAS §3

php artisan optimize                             # config, routes, views, events cache
php artisan queue:restart                        # Horizon: php artisan horizon:terminate
php artisan reverb:restart
php artisan up
```

## Why each sync step exists

- **`permissions:sync`**: provisioning only seeds the permissions that existed when a store
  was created, so new permissions such as `catalog.categories.*` or `finance.subscription.*`
  never reached older stores and their owners got 403 (QA-P5-019). The command creates every
  catalog permission centrally and in each store, gives `super-admin` / `affiliate`
  (central) and `owner` (store) exactly the catalog permissions of their guard, leaves custom
  roles alone and clears the spatie permission cache. Options: `--tenant=<id>` (repeatable),
  `--skip-central`.
- **`localization:sync-static-texts`**: queues the storefront static-text sync per store.
- **`finance:backfill-ledger`**: posts paid orders / settlements / invoices that predate the
  ledger and corrects orders whose ledger postings drifted from the settlement figures (for
  example a refund above the vendor share posted by an earlier release, QA-P5-011).
  `--dry-run` reports without writing.

## Neozena catalog import from the CLI (`catalog:import-neozena`)

Same import as Admin › Catalog › Import (`CatalogImportService`, decision #61): products go
only into the **central** catalog; stores select them later (#65). Credentials come from
Admin › Integrations › Neozena (#69) — the command refuses with a clear message while the
section is disabled or has no API base URL. Source categories must be mapped first
(unmapped → product skipped and logged).

```bash
php artisan catalog:import-neozena --dry-run                 # totals, pages, mapping, sample items; writes nothing
php artisan catalog:import-neozena --force                   # queued batch on "default" (needs a worker)
php artisan catalog:import-neozena --sync --force            # inline in this process with a progress bar
php artisan catalog:import-neozena --sync --pages=5 --from-page=11 --force   # page window
php artisan catalog:import-neozena --sync --update-existing --force          # refresh prices / stock / images of imported products
php artisan catalog:import-neozena --resume=42               # continue run #42
```

- Without `--force` it asks for confirmation (non-interactive → not started, exit 1).
- Prints the run id, pages, created / updated / skipped / failed counts and the first 20
  failures. Exit code 0 only when the run completed without failures; 1 otherwise; 2 for
  invalid options.
- Without `--update-existing` products imported before are skipped; unchanged products
  (same payload checksum) are always skipped, so re-runs never duplicate.
- `--sync` runs are resumable: Ctrl+C / SIGTERM pauses the run after the current page;
  `--resume=<id>` continues a paused, cancelled, failed or interrupted (no progress for
  15 min) inline run from its next page. `--resume` on a paused **queued** run sets it
  running again (its batch continues); queued runs cannot be resumed inline, and inline runs
  cannot be resumed from the admin page.
- **Daily sync (optional, off by default):** Admin › Integrations › Neozena › "Daily product
  sync" + "Sync time" registers `catalog:import-neozena --update-existing --force` (queued)
  daily at that server time (`onOneServer`, `withoutOverlapping`). It needs the usual
  `* * * * * php artisan schedule:run` cron and a queue worker; check with
  `php artisan schedule:list`.
