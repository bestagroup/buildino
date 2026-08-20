# Buildino Runtime Manifest Recovery

This snapshot originally contained the application tree but omitted the root runtime/build manifests required to install dependencies and execute Laravel reproducibly.

Recovered root files:

- `composer.json`
- `artisan`
- `phpunit.xml`
- `.env.example`
- `.gitignore`
- `package.json`
- `vite.config.js`

The dependency manifest intentionally preserves Laravel 12, Sanctum, Yajra DataTables 12, Morilog Jalali and L5-Swagger because these packages are already referenced by the canonical source tree.

## Safe bootstrap

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan optimize:clear
npm install
npm run build
php artisan test --filter=RuntimeManifestIntegrityTest
php artisan test
```

Do not use `migrate:fresh` on an environment that may contain persistent data.
