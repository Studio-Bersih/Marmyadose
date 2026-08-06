-- UD84 -- discount requests on an order line.
--
-- A salesperson taking an order on /ud84 can now see each product's selling
-- price, and write a free-text request against any line: "5%", "samakan harga
-- bulan lalu", "tolong dibantu bu". Whoever works the order in the panel reads
-- it and decides; nothing here prices anything.
--
-- Free text rather than an amount because the admin retypes the real figure
-- into Retail regardless, so a structured number buys nothing and cannot hold
-- half of what a salesperson actually asks for.
--
-- Nullable, so every existing line simply has no request. NULL means "none";
-- the endpoint stores NULL rather than '' so there is only one such value.
--
-- Do NOT run `php artisan migrate` on this database. Its migrations table
-- records only the project's original Laravel 9/10-era migrations; the repo's
-- current database/migrations/ holds Laravel 11-style files that are not
-- recorded there, so migrate would attempt create_users_table against the
-- existing `users` table and fail.

ALTER TABLE `ud84_pesanan_detail`
  ADD COLUMN `DISKON` varchar(100) DEFAULT NULL AFTER `JUMLAH`;
