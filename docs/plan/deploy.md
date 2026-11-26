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
