# Kosada — manual deployment, 2026-08-16 release

Backend (`Marmyadose`) + database. **Two zips, one ordered pass, roughly 20 minutes.**

| | |
|---|---|
| **Target** | cPanel — `https://fae.deabakery.co.id/api/` |
| **Database** | the `dao` database, via phpMyAdmin |
| **Baseline assumed** | server is level with `origin/main` (`b397b2c`, 7 Aug), i.e. the six UD84 releases are already live |
| **This release** | 11 commits, `feat/kosada-request-update` — Kosada only, no UD84 file is touched |
| **Downtime** | none required, but see *When to run this* below |

> **If the server is NOT level with `main`**, stop. This package assumes it is. Run
> `00_PREFLIGHT.sql` anyway — if it reports UD84 objects behaving unexpectedly, or if
> `routes/api.php` on the server has no `UD84\Poin` import, the baseline is wrong and
> uploading `routes/api.php` from this package will point at controllers that are not there.

---

## The one rule

**Database first, code second.** In that order, never the reverse.

`Kredit@addKredit` writes `MEMBER_ID` and `Report@getReport` filters on `HIDDEN_FROM_REPORT`.
Upload the code before the columns exist and creating a loan fails and *every* report request
errors. Going the other way round is harmless: the new columns and tables sit unused until the
code arrives.

## When to run this

Outside working hours if possible. Not because of downtime — there is none — but because there
is a window between the SQL landing and the code landing where the database is ahead of the
application. Nothing breaks in that window; it is just not a state to leave the co-op sitting in
while staff are entering angsuran.

---

## Contents

```
kosada-database-2026-08-16.zip
├── 00_PREFLIGHT.sql          read-only  — what is already applied?
├── 01_kosada_add_member_id.sql             BLOCKER
├── 02_kosada_add_hidden_from_report.sql    BLOCKER
├── 03_kosada_index_no_kredit.sql           performance
├── 04_kosada_create_kredit_macet.sql       BLOCKER
├── 05_kosada_create_transfer_harian.sql    BLOCKER
├── 06_kosada_performance_indexes.sql       performance
├── 07_users_add_status.sql                 BLOCKER
├── RUN-ALL.sql               01–07 as one paste, for a clean server only
└── 99_VERIFY.sql             read-only  — did it all land?

kosada-backend-2026-08-16.zip     13 files, extracts over public_html/api/
├── app/Http/Controllers/Authenticate.php
├── app/Http/Controllers/Kosada/Akun.php                    NEW
├── app/Http/Controllers/Kosada/Concerns/RequiresAdmin.php  NEW (new folder)
├── app/Http/Controllers/Kosada/Kredit.php
├── app/Http/Controllers/Kosada/Macet.php                   NEW
├── app/Http/Controllers/Kosada/Member.php
├── app/Http/Controllers/Kosada/Report.php
├── app/Http/Controllers/Kosada/Transfer.php                NEW
├── app/Models/Kosada/KreditMacetModel.php                  NEW
├── app/Models/Kosada/KreditModel.php
├── app/Models/Kosada/TransferHarianModel.php               NEW
├── app/Models/User.php
└── routes/api.php
```

Nothing in the backend zip touches `vendor/`, `storage/`, `.env`, `public/`, `config/` or any
`UD84/` file. Extracting it cannot disturb the shop's system.

---

## Step 1 — Back up the database

phpMyAdmin → select the database → **Export** → **Quick** → **Go**. Keep the `.sql` file.

This is the only step with no undo if skipped. File 01 runs an `UPDATE` across every row of
`kosada_kredit`; everything else is additive, but that one writes data.

## Step 2 — Preflight

phpMyAdmin → **SQL** tab → paste all of `00_PREFLIGHT.sql` → **Go**.

Read the `RESULT` column of the first table:

- **Every row MISSING** → clean server. Go to step 3a.
- **Some rows PRESENT** → part of this release was already applied. Go to step 3b.
- **`users.privilege` missing, or not `enum('Administrator','Staff')`** → **stop and report it.**
  The Akun page and every admin guard rest on that column. There is no SQL file for it because it
  should already exist.

Note the `TABLE_ROWS` figures from the last query. On the production copy, `kosada_kredit` held
9,174 rows and file 01 finished in seconds. If production is an order of magnitude larger, expect
file 01 to take proportionally longer — and let it finish.

## Step 3a — Clean server: one paste

phpMyAdmin → **SQL** tab → paste all of `RUN-ALL.sql` → **Go**.

## Step 3b — Partial server: file by file

Run files **01 → 02 → 03 → 04 → 05 → 06 → 07** in that order, skipping the ones preflight
reported as PRESENT. One file per paste, checking each succeeded before the next.

**The order is not arbitrary.** 01 must precede 04 and 05 — both new tables carry a `MEMBER_ID`
that only makes sense once `kosada_kredit` has the column and has been backfilled.

**Errors that are safe to ignore**, and mean only "already applied":

| Error | Meaning |
|---|---|
| `1060 Duplicate column name` | the column is already there |
| `1061 Duplicate key name` | the index is already there |
| `1050 Table already exists` | the table is already there |

Any *other* error: stop, do not upload the backend, and report it.

## Step 4 — Verify the database

phpMyAdmin → **SQL** tab → paste all of `99_VERIFY.sql` → **Go**.

**All 11 rows of the first table must read `OK`.** If one reads `MISSING`, run the file it names
and verify again. Do not continue until all 11 are OK.

Then **write down the `UNLINKED` figure** from the second table. On the production copy it was
**642 of 9,174 (7.0% unlinked)**. Those loans show `-` for Pekerjaan and No WhatsApp on the Data
Macet page. That is a supported state, not a fault — the backfill refuses to guess between two
members who share a name, because putting the wrong person's phone number on a collections sheet
is worse than showing a dash. If `UNLINKED` is close to `TOTAL_LOANS`, the `UPDATE` in file 01
did not run; re-run just that statement.

---

## Step 5 — Upload the backend

1. cPanel → **File Manager** → navigate to the Laravel root for the API (the folder containing
   `app/`, `routes/`, `artisan`).
2. **Upload** `kosada-backend-2026-08-16.zip` into that folder.
3. Right-click the zip → **Extract** → extract into that same folder.
4. When asked, **overwrite** existing files.
5. Delete the zip afterwards.

The zip's paths are relative (`app/…`, `routes/…`), so extracting in the Laravel root drops each
file exactly where it belongs and creates the new `app/Http/Controllers/Kosada/Concerns/` folder.

**Check one thing before moving on:** `app/Http/Controllers/Kosada/Concerns/RequiresAdmin.php`
exists. That folder is new. If the extract flattened it, the three controllers that use the trait
will fatal on every request.

## Step 6 — Clear the caches (mandatory)

**New routes 404 until the route cache is cleared.** The symptom is a page that loads and shows
nothing, with no error anywhere — this has cost time before.

**If you have Terminal / SSH:**

```
php artisan route:clear
php artisan config:clear
```

**If you do not** (cPanel Terminal is often disabled), delete these two files in File Manager —
Laravel rebuilds them on the next request:

```
bootstrap/cache/routes-v7.php
bootstrap/cache/config.php
```

Deleting them is safe. They are generated caches, not source.

## Step 7 — Smoke test

Open each in a browser. **Every one must return JSON, not a 404 page.**

| URL | Expect |
|---|---|
| `…/api/Kosada/Data-Macet` | `{"data":[],"meta":{…}}` — empty `data`, `total` 0 |
| `…/api/Kosada/Transfer-Harian?tanggal=2026-08-16` | `{"data":[],"meta":{…}}` |
| `…/api/Kosada/Akun` | JSON list of Kosada accounts, each with `ROLE` and `STATUS` |
| `…/api/Kosada/Laporan-Tersembunyi` | `[]` — a bare empty array |

`Transfer-Harian` needs the `tanggal` parameter; without it it answers `422` with
`"Tanggal wajib diisi"`, which is still a working route. The other three take no parameters.

**A 404 on any of them means step 6 did not take.** Go back and clear the cache again.

`…/api/Kosada/Akun` is also the check that file 07 landed — it selects `users.STATUS`, so it
errors if the column is missing.

Then, from the Kosada frontend, in this order:

1. **Log in.** If login fails, that is the highest-priority failure — check `users.groups` reads
   `Kosada` on the account (query 3 of `99_VERIFY.sql`).
2. **Open Laporan** and run a report. This exercises `HIDDEN_FROM_REPORT`. Try the **SEMUA**
   marketing option — that is the query file 03's index exists for. It should return in about a
   second; if it hangs for 30 seconds and dies, file 03 did not apply.
3. **Open the Dashboard**, open a loan's detail modal, and check the **TOTAL** column adds up
   (Nominal + Kasbon per row).
4. **Create one test loan** through Tambah Kredit. This is the `MEMBER_ID` write path — the thing
   that breaks loudest if file 01 was skipped. Delete the test loan afterwards.
5. **Open Data Macet and Transfer Harian.** Both should render empty, not error.

---

## Rollback

**Code:** restore the 13 files from the previous deploy and clear the caches again. Keep a copy
of the old files before step 5 if you want this to be quick — File Manager can zip the `app/` and
`routes/` folders in place first.

**Database:** leave it. Files 01, 02, 03, 06 and 07 are additive — the old code ignores the new
columns and indexes entirely, and `users.STATUS` defaults to `Aktif` so UD84 and the old Kosada
login behave exactly as before. Files 04 and 05 create standalone tables that nothing else
references. **There is no need to roll the schema back, and rolling it back would cost the
`MEMBER_ID` backfill.**

The one thing that is not free to redo is file 01's `UPDATE`. That is what step 1's backup is for.

---

## Not in this package

- **The frontend.** `Kosada/` deploys separately to Vercel from `main`. Until it does, the new
  pages (Data Macet, Transfer Harian, Akun) have no UI — the endpoints are live but nothing calls
  them. That is fine as an intermediate state; the backend changes are backward-compatible with
  the frontend currently deployed.
- **`database/sql/` and `database/migrations/`.** Deliberately excluded from the backend zip —
  they are repo documentation, not runtime code, and the server has no use for them.

## Two standing warnings

- **Never run `php artisan migrate` on this database.** The `migrations` table records only the
  project's original Laravel 9/10-era rows, so `migrate` would attempt `create_users_table`
  against the existing `users` table and fail. All schema ships as `.sql` pasted into phpMyAdmin.
- **`users` is shared with UD84.** Only file 07 touches it, additively. UD84's login calls
  `Auth::attempt()` and never reads `STATUS`, so the shop's system is unaffected — but this is the
  one file in the release that can reach outside Kosada, and it is worth knowing that when reading
  the diff.

## Known, tracked, not fixed here

No route in `routes/api.php` carries auth middleware — the API is readable by anyone holding the
URL. This release *adds* server-side administrator checks to the destructive operations (account
management, transfer deletion, closing a macet case), each re-verifying an admin password, but the
read endpoints stay open. That is pre-existing and app-wide; the proposal for fixing it properly
is in `Kosada-Auth-Proposal.md`. Nothing in this deployment makes it worse.
