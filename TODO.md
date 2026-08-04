# Fix Plan: Overdue/Unpaid Tracking

## Root Cause
The `payments` table has `payment_date DATE NOT NULL`, but the code inserts `NULL` for pending (unpaid) payments. These INSERTs fail silently, so no Pending/Overdue records are ever created.

## Steps
- [x] 1. Fix `stall-monitoring.php` — add ALTER TABLE to relax `payment_date` to NULL
- [x] 2. Fix `register-tenants.php` — add ALTER TABLE to relax `payment_date` to NULL
- [x] 3. Fix `database/meedo_system.sql` — change `payment_date` to `DEFAULT NULL`
- [x] 4. Fix overdue report queries in `stall-monitoring.php` and `financial-reports.php`
- [x] 5. Verify the fix by loading `stall-monitoring.php`
