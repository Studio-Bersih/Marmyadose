# Changelog

Marmyadose API. Newest first.

## Unreleased

### Fixed
- `Transfer@getTransferHarian` (`GET /Kosada/Transfer-Harian`) returned only the
  first 50 rows of a day. It defaulted to `per_page=50`, but the page that calls
  it is a single-day recap sheet with no pager, so a day past 50 transfers showed
  50 lines under a footer that totalled all of them and the rest could not be
  reached at all. The endpoint now returns the whole day, as
  `printTransferHarian` always has; `page`, `per_page` and `last_page` are gone
  from the response meta, which nothing read.

  Reported from the field: "kenapa hanya bisa discroll kebawah sampai nomer 50
  saja ya mas? yang 51 ke bawah tidak bisa dilihat".

### Added
- `Transfer@updateTransfer` (`POST /Kosada/Ubah-Transfer`) — corrects the
  JENIS, NOMINAL and KETERANGAN of a transfer line that is already recorded.
  Administrator-only via `RequiresAdmin`, like `deleteTransfer`.

  The nasabah identity fields and `CREATED_AT` are deliberately not writable:
  an edit must not be able to re-point a line at a different person, nor clear
  the late-entry flag on the row it applies to. No schema change — the table
  already carries `UPDATED_AT`.
