-- UD84 Nota & Print — unit of sale on each sales detail line.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- postPenjualan writes SATUAN and getInvoices reads it; without the column
-- every sale and every nota fails.
--
-- Do NOT run `php artisan migrate` on this database. There is no migrations
-- table, so Laravel's three default migrations would run and collide with the
-- existing `users` table.

ALTER TABLE `ud84_penjualan_detail`
  ADD COLUMN `SATUAN` varchar(20) DEFAULT NULL AFTER `NAMA`;
