# Complete Agila backups to your PC

This replaces the Google Drive, daily local and code-change backup automations.
Existing SQL files are preserved. No hosting cron job is required: the Windows task
requests a fresh export from Agila and downloads it every six hours. Each TAR bundle
contains `database.sql` and the private `discount_ids`, `receipts`, and `avatars` folders.

## Enable on Agila

1. Deploy the updated website, including `.htaccess`, the backup helpers,
   `backend/api/pc_backup.php`, `frontend/admin_backups.php`, and both PowerShell scripts.
   The deployment must also remove the retired Google Drive helper/runner and old JS.
2. Keep your working Agila database settings and `BACKUP_MYSQLDUMP` configuration.
   PHP must allow `proc_open`, have mysqldump available and permit the database export.
3. Keep `PRIVATE_STORAGE_DIR` outside every served directory and writable by PHP.
   Set `PRIVATE_STORAGE_DOCUMENT_ROOT` explicitly on nonstandard installations.
4. Remove any old cron jobs for `db_backup.php --check` or `drive_backup.php` from
   Agila's control panel. The former now does nothing; the Drive runner was removed.
5. Open **Admin → Database Backups** on your live website. Enter its HTTPS base URL,
   including the project subfolder if applicable, and download the connection file.
   Example: `https://example.com/hotel`, not the login/admin page URL.

If Google OAuth was previously connected, revoke the old application in your Google
Account, remove its unused environment settings and private `backups/drive.json` file.
Do not delete SQL backups. This implementation never deletes existing Google files.

## Install on Windows

Download the connection file and both Windows scripts using the admin page. Save all
three in the same folder, then run `install_pc_backup.ps1` with PowerShell. Alternatively:

```powershell
powershell.exe -NoProfile -ExecutionPolicy Bypass -File .\install_pc_backup.ps1
```

Optional destination override:

```powershell
.\install_pc_backup.ps1 -PairingFile .\santabeachclub-pc.json -Destination 'D:\Database Backups'
```

The default destination is your Windows Documents folder under **SantaBeachClub Backups**.
New TAR bundles are saved inside its **Complete Backups** subfolder. Older standalone
SQL exports can be kept separately in a **Database Backups** subfolder.
The task is named **SantaBeachClub Agila PC Backups**. It runs every six hours, starts
once immediately, and checks missed runs at sign-in. Failures retry up to three times
at ten-minute intervals. Your PC must be awake, online and signed in as the installing
Windows user. The browser and local XAMPP/MySQL do not need to run.

There are no snapshots of periods when your PC is unavailable; the next run creates
a current snapshot. PC copies are kept until you remove them. Server export copies
are retained for 30 days following successful new exports.

After the first successful download, delete the downloaded `santabeachclub-pc.json`.
It contains a private bearer key. The installer stores that key using Windows DPAPI
for the current user in `%LOCALAPPDATA%\SantaBeachClubBackups\access-key.xml` and
restricts that directory to the user. Do not share the setup file or saved TAR bundles.
Keep the destination on a BitLocker-encrypted Windows drive because the bundles contain
guest identity documents and payment receipts.

## Check results

See `%LOCALAPPDATA%\SantaBeachClubBackups\download.log` for SUCCESS/FAILED messages.
The website reports the last requested server export, not proof that the PC saved it.
Downloads use HTTPS without redirects and verify byte count plus SHA-256 before a
`.partial` file is renamed to `.tar`. Retrying a failed download reuses its request ID
and server export. Generating a new connection file revokes the old PC key, so reinstall
after pairing again. **Revoke PC access** immediately disables the current key.

To run immediately:

```powershell
& "$env:LOCALAPPDATA\SantaBeachClubBackups\download_pc_backup.ps1" -Force
```

A 401 response means the key is invalid/revoked or the host stripped Authorization.
The included Apache rewrite preserves Authorization for PHP. A 503 means the host
could not export or verify a backup; verify PHP/mysqldump access and database settings.
Redirects are rejected: use the website's final HTTPS URL in the connection file.

Website source files and public gallery/room images are not included. Test recovery
into a separate database and private-storage directory before relying on an export.

Local verification: `php tests/pc-backups.php`, `php tests/backups.php`,
`node tests/backups-access.cjs`, and `powershell -File tests/pc-downloader.ps1`.
A live Agila download remains unverified until deployment and PC pairing are complete.
