-- UD84 Nota & Print — unit of sale on each sales detail line.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- postPenjualan writes SATUAN and getInvoices reads it; without the column
-- every sale and every nota fails.
--
-- Do NOT run `php artisan migrate` on this database. Its migrations table
-- records only the project's original Laravel 9/10-era migrations; the repo's
-- current database/migrations/ holds Laravel 11-style files that are not
-- recorded there, so migrate would attempt create_users_table against the
-- existing `users` table and fail.

ALTER TABLE `ud84_penjualan_detail`
  ADD COLUMN `SATUAN` varchar(20) DEFAULT NULL AFTER `NAMA`;
