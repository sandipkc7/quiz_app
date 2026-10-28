# Production Migration Temporary Folder

This folder contains the database migration script required for the **Password Reset & SMTP Email** system.

### File Included:
- **`production_migration.sql`**: Creates the `password_resets` table with foreign key constraints, indexes, utf8mb4 charset, and updates the `schema_migrations` tracking table.

### How to Apply on Production Server:
You can choose any of the following methods:

**Method 1 (Command Line Migration Runner — Recommended):**
```bash
php migrate.php
```

**Method 2 (MySQL CLI):**
```bash
mysql -u your_db_user -p your_db_name < temp/production_migration.sql
```

**Method 3 (phpMyAdmin / Adminer / cPanel):**
- Open phpMyAdmin, select your quiz database, go to the **SQL** tab, copy & paste the contents of `temp/production_migration.sql`, and click **Go**.

---
> **Note**: After applying the migration on your production database, you can safely delete this entire `/temp` folder.
