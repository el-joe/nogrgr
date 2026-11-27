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

Same import as Admin › Catalog › Import (`CatalogImportService`, decisions #61 / #112):
products go only into the **central** catalog; stores select them later (#65). Neozena's
catalog API is public: it works out of the box with the default base URL
`https://neozena.com/api/catalog` — no token, no "Enabled" switch, no saved settings needed.
Admin › Integrations › Neozena only overrides the base URL, adds an optional bearer token
and the daily sync. Every page is a queued `ImportCatalogPage` job (one Bus batch per run),
so **a queue worker must be running**.

```bash
php artisan migrate                                          # once after updating (catalog_import_run_pages)
php artisan queue:restart                                    # workers pick up the new code
php artisan queue:work --queue=default --memory=1024 --timeout=900 --tries=3   # keep running (Supervisor / Horizon in production)

php artisan catalog:import-neozena --dry-run                 # totals, pages, mapping, sample items; writes nothing
php artisan catalog:import-neozena --force                   # queue every page, then watch: progress bar + final counts
php artisan catalog:import-neozena --force --no-wait         # queue and exit (follow it in Admin › Catalog › Import)
php artisan catalog:import-neozena --pages=5 --from-page=11 --force   # page window
php artisan catalog:import-neozena --update-existing --force # refresh prices / stock / images / variants of imported products
php artisan catalog:import-neozena --no-auto-create --force  # skip products of unmapped categories / unknown attributes
php artisan catalog:import-neozena --resume=42               # queue the unfinished pages of run #42 again
```

- Without `--force` it asks for confirmation (non-interactive → not started, exit 1).
- While watching it polls the run row every 2 s; still "queued" after 30 s → hint that no
  worker seems to run. Ctrl+C only stops watching (the run goes on in the queue). Prints
  the run id, pages, created / updated / skipped / failed counts and the first 20 failures.
  Exit code 0 only when the run completed without failures; 1 otherwise; 2 for invalid
  options. With `--no-wait` it exits 0 once queued.
- Missing data is created by default (like the old importer): an unmapped source category
  becomes an active central category (en/ar names, slug, parent chain, image) and is mapped
  (an admin mapping always wins); missing attributes / options (with colour swatches) are
  created; every product comes with all its variants (SKU, USD price, stock, weight,
  thumbnail), updated in place on re-runs (keyed by the Neozena variant id). New products
  are published (visible to stores as "New products"); an update keeps the product's status.
  Stock goes to the default branch; with no branch, "Main warehouse" is created.
- Without `--update-existing` products imported before are skipped; unchanged products
  (same payload checksum) are always skipped, so re-runs never duplicate. Products skipped
  only for an unmapped category are imported by the next run.
- `--resume=<id>` continues a paused, cancelled, failed or interrupted (no progress for
  15 min, e.g. the worker died) run: the pages not finished yet are queued again (a paused
  run whose batch is still queued just runs on). Completed runs are not resumed: start a
  new run instead (`--force`). Failed page jobs can be cleared with `php artisan queue:flush`.
- **Daily sync (optional, off by default):** Admin › Integrations › Neozena › "Daily product
  sync" + "Sync time" registers `catalog:import-neozena --update-existing --force --no-wait`
  daily at that server time (`onOneServer`, `withoutOverlapping`). It needs the usual
  `* * * * * php artisan schedule:run` cron and a queue worker; check with
  `php artisan schedule:list`.
