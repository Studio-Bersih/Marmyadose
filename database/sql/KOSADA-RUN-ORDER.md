# Kosada — SQL run order (2026-08-16 release)

Apply these in phpMyAdmin **in this order**, and **before** deploying the backend code.

> **Never run `php artisan migrate` on this database.** The `migrations` table records only the
> project's original Laravel 9/10-era migrations, so `migrate` would attempt `create_users_table`
> against the existing `users` table and fail.

| # | File | What it does | Deploy blocker? |
|---|---|---|---|
| 1 | `2026_08_16_kosada_add_member_id.sql` | Adds `kosada_kredit.MEMBER_ID` + backfill | **Yes** |
| 2 | `2026_08_16_kosada_add_hidden_from_report.sql` | Adds `kosada_kredit.HIDDEN_FROM_REPORT` | **Yes** |
| 3 | `2026_08_16_kosada_index_no_kredit.sql` | Indexes `NO_KREDIT` on both credit tables | No, but do it |
| 4 | `2026_08_16_kosada_create_kredit_macet.sql` | Creates `kosada_kredit_macet` | **Yes** |
| 5 | `2026_08_16_kosada_create_transfer_harian.sql` | Creates `kosada_transfer_harian` | **Yes** |
| 6 | `2026_08_16_kosada_performance_indexes.sql` | Indexes the list-page filter and sort columns | No, but do it |
| 7 | `2026_08_16_users_add_status.sql` | Adds `users.STATUS` (Aktif/Nonaktif) | **Yes** |

**For an actual cPanel deployment, use `deploy/2026-08-16-kosada/` instead of this folder.** It
carries these same seven files renumbered `01`–`07`, plus a read-only `00_PREFLIGHT.sql` that
reports which of them are already applied, a `99_VERIFY.sql` that proves they all landed, and a
`RUN-ALL.sql` for a server where none of them have been. The runbook is `DEPLOY.md` beside them.
This file remains the explanation of *why* the order is what it is.

## Why the order matters

- **#1 before #4 and #5.** Both new tables carry a `MEMBER_ID` that only makes sense once the
  column exists on `kosada_kredit` and has been backfilled.
- **#1 before the backend deploys.** `Kredit@addKredit` now writes `MEMBER_ID`. If the code ships
  first, **creating a new loan will fail** — the column won't exist.
- **#2 before the backend deploys.** `Report@getReport` filters on `HIDDEN_FROM_REPORT`. Without
  it, *every* report request errors.
- **#3 is performance, not correctness** — but skip it and the Laporan page's new "SEMUA" option
  will be slow enough to time out. See below.
- **#7 before the backend deploys.** `Kosada\Akun` selects and writes `users.STATUS`; without the
  column the account list and every account update fails. It is independent of #1–#6 and may be
  run at any point before the code goes up. Login itself survives without it — `Authenticate@logIn`
  reads `$data->STATUS ?? 'Aktif'` — so a missed #7 breaks only the Akun page, not access.

## What to expect when running #1

Measured against a copy of production (9,174 loans, 2,736 members):

| Outcome | Rows |
|---|---|
| Linked cleanly | 8,532 |
| Ambiguous — name shared by 2+ members, left `NULL` on purpose | 174 |
| No matching member, left `NULL` | 468 |

`NULL` is a **supported state**, not a failure. Those loans fall back to the `NAMA`/`ALAMAT`
already stored on the loan and show `-` for Pekerjaan and WhatsApp. The backfill deliberately
refuses to guess between members who share a name — putting the wrong person's phone number on a
collections sheet is worse than showing a dash.

Run the count query at the end of file #1 and record the number.

## Why #3 matters more than it looks

`NO_KREDIT` is the link between `kosada_kredit` and `kosada_detail_kredit`, and it had **no index
on either table**. Every loan-detail lookup scanned all 44,451 detail rows.

This stayed hidden while the Laporan page forced you to pick a single marketing (~161 loans). The
new "SEMUA" option takes it to ~5,249 loans, which killed the request on PHP's 30-second limit.
The controller's N+1 loop was rewritten as a single grouped query at the same time; with both
changes the same report returns in **~0.9s**.

## Why #6 matters

`kosada_member` had **no index at all** beyond `PRIMARY` and the `KTP` unique key. Sorting the
member list by `CREATED_AT` was a full table scan plus a filesort over all 2,736 rows on every
request (`EXPLAIN`: `type=ALL ... Using filesort`). With the index it reads only the rows on the
requested page: **3.13 ms → 0.30 ms**.

The `(STATUS, CREATED_AT)` composite on `kosada_kredit` turns the pagination `COUNT` into a
covering-index scan — **8.24 ms → 4.59 ms**. Column order is deliberate: `STATUS` first because it
is an equality test, `CREATED_AT` second because it is a range.

Like #3, this is performance only. Nothing breaks without it; the list pages are just slower.

## After deploying the backend

```
php artisan route:clear
php artisan config:clear
```

New routes will 404 until the route cache is cleared.

## Rollback

Files #1–#3, #6 and #7 are additive and safe to leave in place if you roll the code back — the old
code simply ignores the new columns and indexes. Files #4 and #5 create standalone tables that
nothing else references; dropping them affects no existing feature.

#7 is the only file that touches a table shared with UD84. It is additive with a default of
`Aktif`, and UD84's `Authenticate@logIn` calls `Auth::attempt()` without reading `STATUS`, so UD84
is unaffected either way — including on rollback.

Nothing here needs rolling back, and rolling #1 back would throw away the `MEMBER_ID` backfill.
