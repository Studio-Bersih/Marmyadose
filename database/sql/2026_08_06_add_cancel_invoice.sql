-- UD84 Cancel Invoice — transaction status and audit trail.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- The cancel endpoint writes both of these; every report reads STATUS to
-- exclude cancelled sales from revenue figures.
--
-- Both changes are additive. Existing sales default to 'Aktif', so nothing
-- about current data or current reports changes until a sale is cancelled.
--
-- ud84_transaksi_log records who cancelled what, when and why. CATATAN_SISTEM
-- holds what the system itself did or could not do -- specifically which lines
-- had their stock returned and which were skipped because the product or the
-- unit could not be resolved. That distinction is what makes it an audit trail
-- rather than a receipt.
--
-- Do NOT run `php artisan migrate` on this database. Its migrations table
-- records only the project's original Laravel 9/10-era migrations; the repo's
-- current database/migrations/ holds Laravel 11-style files that are not
-- recorded there, so migrate would attempt create_users_table against the
-- existing `users` table and fail.

ALTER TABLE `ud84_penjualan_rekap`
  ADD COLUMN `STATUS` enum('Aktif','Dibatalkan') NOT NULL DEFAULT 'Aktif' AFTER `UNIQUE`;

-- POIN records how many member points this sale actually granted, so a
-- cancellation reverses exactly what was given rather than recomputing it.
-- Recomputing would be wrong the moment the earning rule changes: a sale made
-- under the old rule would reverse the wrong amount forever after.
--
-- NULL means "granted before this column existed". Cancelling such a sale
-- falls back to recomputing from CASH, and says so in CATATAN_SISTEM.
ALTER TABLE `ud84_penjualan_rekap`
  ADD COLUMN `POIN` smallint(6) DEFAULT NULL AFTER `MEMBER`;

-- KODE stores ud84_master_produk.ID, which is int(11), but the column itself
-- was smallint(6) -- a ceiling of 32767. Product IDs are around 466 today, so
-- this has never been hit, but cancellation now resolves the product by KODE
-- in order to return stock to it. A truncated ID would silently resolve to a
-- DIFFERENT product and credit that one instead, so the types are aligned.
ALTER TABLE `ud84_penjualan_detail`
  MODIFY COLUMN `KODE` int(11) DEFAULT NULL;

CREATE TABLE `ud84_transaksi_log` (
  `ID`               bigint(19) NOT NULL AUTO_INCREMENT,
  `UNIQUE_TRANSAKSI` varchar(50)  DEFAULT NULL,
  `AKSI`             varchar(30)  DEFAULT NULL,
  `OPERATOR`         varchar(100) DEFAULT NULL,
  `ALASAN`           text         DEFAULT NULL,
  `CATATAN_SISTEM`   text         DEFAULT NULL,
  `SEBELUM`          longtext     DEFAULT NULL,
  `SESUDAH`          longtext     DEFAULT NULL,
  `CREATED_AT`       timestamp    NULL DEFAULT current_timestamp(),
  PRIMARY KEY (`ID`),
  KEY `UNIQUE_TRANSAKSI` (`UNIQUE_TRANSAKSI`)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
