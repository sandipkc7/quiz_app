# Production Migration Temporary Folder

This folder contains the database migration script required for production optimization.

### File Included:
- **`production_migration.sql`**: Adds composite performance indexes on `quiz_sessions`, `quiz_answers`, and `questions` for the Question Progression engine.

### How to Apply on Production Server:
You can choose any of the following methods:

**Method 1 (Command Line Migration Runner - Recommended):**
```bash
php migrate.php
```

**Method 2 (MySQL CLI):**
```bash
mysql -u your_db_user -p your_db_name < temp/production_migration.sql
```

**Method 3 (phpMyAdmin / Adminer):**
- Open phpMyAdmin, select your quiz database, go to the **SQL** tab, copy & paste the contents of `temp/production_migration.sql`, and click **Go**.

---
> **Note**: After applying the migration on your production database, you can safely delete this entire `/temp` folder.
