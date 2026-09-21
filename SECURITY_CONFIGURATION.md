# Database and payment configuration

Set `DB_HOST`, `DB_PORT`, `DB_NAME`, `DB_USER`, and `DB_PASS` in the hosting environment or the ignored root `.env` file before deploying. Agila uses the same variables as other hosts. Local XAMPP auto-detection remains available when `DB_HOST` is empty. A failed explicitly configured database connection no longer falls back to another database.

Set `PAYMONGO_SECRET_KEY`, `PAYMONGO_PUBLIC_KEY`, and `PAYMONGO_WEBHOOK_SECRET` in that environment. Checkout fails when its secret key is missing. Never place production secrets in `.env.example` or tracked PHP files.

## Required credential rotation during deployment

The previously committed database password must be replaced at the hosting provider; removing it from source does not revoke it. During a maintenance window, change the database user's password in the hosting control panel, update `DB_PASS` in the deployed environment, restart PHP workers as needed, and verify application and backup database access. Confirm the old password no longer authenticates. Update any other consumers of the same account.

The removed embedded PayMongo API key must also be replaced through the PayMongo dashboard. Update the deployed environment and revoke the exposed key. Source changes alone do not rotate either credential, and repository history still contains the old values.

## TLS trust

Both PayMongo checkout requests require certificate trust and hostname verification. If PHP reports a certificate error, configure `curl.cainfo` in the active `php.ini` with an up-to-date trusted CA bundle and restart PHP/Apache. Do not disable verification to work around a missing CA bundle.

Keep `.env` inaccessible over HTTP. The included Apache rules deny access; deployments using other web servers need equivalent protection.
