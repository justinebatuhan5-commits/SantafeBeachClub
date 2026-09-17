Private receipt storage
=======================

Receipts are written outside the web root. For XAMPP with DocumentRoot
`C:/xampp/htdocs`, the default is `C:/xampp/SantaBeachClub-private/receipts`.
On other hosts set PRIVATE_STORAGE_DIR to an absolute persistent directory outside
every directory or alias served by the web server. Give the PHP service account
read/write access; do not expose this directory through an Apache Alias.

PRIVATE_STORAGE_DOCUMENT_ROOT optionally specifies the actual document root for
CLI jobs. Otherwise CLI assumes the application's parent directory is the web root;
web requests use the server's DOCUMENT_ROOT. Set both variables for nonstandard
deployments so CLI and web requests resolve the same storage.

Before accepting bookings on an existing installation:

1. Configure the directory and PHP account permissions.
2. Run `php backend/scripts/migrate_private_receipts.php` to preview.
3. Run `php backend/scripts/migrate_private_receipts.php --apply` to create storage
   and migrate receipts from storage/receipts and frontend/uploads/receipts.
4. Verify receipt viewing while logged in and denial while logged out.

Migration preserves filenames and database keys, verifies copied file hashes, and
only then removes each original. Conflicting destination files stop migration.
It is safe to rerun after a successful migration. Back up the private directory
alongside the database; existing project-only backups do not include it.

Missing or unsafe private storage fails closed. There is no public-storage fallback.
Historical `uploads/receipts/` and current `storage/receipts/` database keys both
use the authenticated serve_file.php endpoint. Public gallery, room images and
existing avatar storage are outside this private-receipt migration's scope.
