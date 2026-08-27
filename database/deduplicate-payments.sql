-- Run this once in phpMyAdmin after backing up the meedo_system database.
-- Keeps one row per stall and covered month, preferring Paid over unpaid rows.
DELETE payment_to_remove
FROM payments AS payment_to_remove
JOIN payments AS payment_to_keep
  ON payment_to_keep.stall_id = payment_to_remove.stall_id
 AND payment_to_keep.month_covered = payment_to_remove.month_covered
 AND payment_to_keep.id <> payment_to_remove.id
 AND (
      (payment_to_keep.status = 'Paid' AND payment_to_remove.status <> 'Paid')
      OR (payment_to_keep.status = payment_to_remove.status AND payment_to_keep.id < payment_to_remove.id)
 );

ALTER TABLE payments
  ADD UNIQUE KEY IF NOT EXISTS uq_payments_stall_month (stall_id, month_covered);