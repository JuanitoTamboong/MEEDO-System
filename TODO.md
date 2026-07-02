# TODO - MEEDO-System “Pro-code” upgrade (Track A)

- [ ] Step 1: Refactor authentication

  - [x] Add auth helper (`includes/auth.php`)
  - [x] Update `login.php` to use prepared statements
  - [x] Implement password hashing verification (`password_verify`)
  - [x] Add minimal session guards (ensure redirect if unauthenticated)




- [ ] Step 2: Harden database access
  - [ ] Add helper include (e.g., `includes/db.php` or query helpers)
  - [ ] Remove inline SQL concatenation for user inputs in pages we touch

- [ ] Step 3: Fix/optimize critical pages
  - [ ] `manage-stalls.php`: convert SQL that uses `$_POST`/`$_GET` to prepared statements
  - [ ] `stall-monitoring.php`: ensure payment generation/overdue update queries are consistent and safe
  - [ ] `financial-reports.php`: replace month/year filtering with prepared statements

- [ ] Step 4: Code cleanliness
  - [ ] Reduce logic duplication where safe (small helper functions)
  - [ ] Standardize flash messages/alerts

- [ ] Step 5: Testing checklist
  - [ ] Login flow works for both roles
  - [ ] Add/delete section/stall works
  - [ ] Payment generation and overdue updates behave correctly



