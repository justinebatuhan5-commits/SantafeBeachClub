# Database migrations

Run migrations from the project root during deployment, before serving traffic:

```sh
php backend/scripts/migrate.php
```

Migration files live in `backend/database/migrations` and are applied once in filename order. Applied versions are recorded in `schema_migrations`. Web requests never run this command or mutate the schema/catalog.

Migrations must be forward-only and non-destructive. Do not delete or overwrite rooms, bookings, payments, or other operational data in a migration. Put intentional data cleanup in a separately reviewed administrative script with an explicit backup and operator confirmation.
