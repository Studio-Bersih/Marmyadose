# Changelog

Marmyadose API. Newest first.

## Unreleased

### Added
- `Transfer@updateTransfer` (`POST /Kosada/Ubah-Transfer`) — corrects the
  JENIS, NOMINAL and KETERANGAN of a transfer line that is already recorded.
  Administrator-only via `RequiresAdmin`, like `deleteTransfer`.

  The nasabah identity fields and `CREATED_AT` are deliberately not writable:
  an edit must not be able to re-point a line at a different person, nor clear
  the late-entry flag on the row it applies to. No schema change — the table
  already carries `UPDATED_AT`.
