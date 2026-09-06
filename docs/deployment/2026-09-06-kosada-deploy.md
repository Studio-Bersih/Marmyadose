# Kosada — manual deployment, 2026-09-06

Backend only. **One file, no SQL, about three minutes.**

| | |
|---|---|
| **Target** | cPanel — `https://fae.deabakery.co.id/api/` |
| **Database** | no change. Nothing to run in phpMyAdmin. |
| **Baseline assumed** | the 2026-09-02 release is live (Transfer Harian has an `Ubah` button) |
| **This release** | Transfer Harian shows the whole day, not just the first 50 rows |
| **Downtime** | none |
| **Frontend** | already on `main`, Vercel deploys it itself — and it is cosmetic here. The fix is entirely in this file. |

---

## What this fixes

Reported from the field:

> "kenapa hanya bisa discroll kebawah sampai nomer 50 saja ya mas? yang 51 ke
> bawah tidak bisa dilihat" — data transferan per hari

`Transfer@getTransferHarian` defaulted to 50 rows per page, but the Transfer
Harian page is a single-day recap sheet: it scrolls, it has no pager, and it
never asked for page 2. So a day past 50 transfers showed 50 lines under a
footer that totalled all of them, and rows 51 onward could not be reached by
any means the user had.

The endpoint now returns the whole day, the way the print sheet always has.

Verified before upload: a seeded 63-transfer day returned 50 rows on the old
code and 63 on this one, confirmed both in the API and in the browser.

---

## Why there is no SQL this time

Nothing about the table changed — this is a query that was cutting itself short.
`kosada_transfer_harian` is untouched, so the usual "database first, code
second" rule has nothing to order here. Upload and you are done.

---

## Contents

```
upload/                                extracts over public_html/api/
└── app/Http/Controllers/Kosada/Transfer.php    modified — getTransferHarian() returns the full day
```

`routes/api.php` is **not** in this release — no route was added or removed, so
there is no route cache to clear either.

Nothing here touches `vendor/`, `storage/`, `.env`, `public/`, `config/`,
`routes/`, or any `UD84/` file. Uploading it cannot disturb the shop's system.

---

## Step 1 — Keep a copy of what is there now

cPanel → File Manager → `public_html/api/`. Download the one file you are about
to replace:

- `app/Http/Controllers/Kosada/Transfer.php`

This is the rollback. It takes twenty seconds and it is the only thing standing
between a bad upload and a broken Transfer Harian page.

## Step 2 — Upload

| Upload this | Over this |
|---|---|
| `upload/app/Http/Controllers/Kosada/Transfer.php` | `public_html/api/app/Http/Controllers/Kosada/Transfer.php` |

Keep the folder structure exactly. Overwrite when asked.

## Step 3 — Clear the config cache (only if you cache)

No route changed, so the route table does not matter this time. If the site runs
with a cached config, clear it anyway so the new file is picked up cleanly.

cPanel → Terminal (or SSH), from `public_html/api/`:

```
php artisan config:clear
```

No Terminal on the plan? There is nothing to do — PHP re-reads the controller on
the next request on its own. If the host runs OPcache and the old behaviour
persists after a few minutes, restart PHP from cPanel → "Setup PHP" or MultiPHP.

## Step 4 — Check it landed

Pick a date that actually has more than 50 transfers, then open in a browser:

```
https://fae.deabakery.co.id/api/Kosada/Transfer-Harian?tanggal=2026-09-01
```

In the JSON, the `data` array should hold as many entries as `meta.total` says.
Before this release the array stopped at 50 while `meta.total` read higher —
that gap **is** the bug, so seeing them agree is the proof.

Then open Transfer Harian on the Kosada site for that same day. Scroll the table
to the bottom: the last row number must equal the count in the footer's
**Total (N data)**, and it must be the real last transfer of the day.

## What "it did not work" looks like

| Symptom | Cause |
|---|---|
| Still stops at 50 | Uploaded to the wrong folder, or OPcache still serving the old file — see Step 3 |
| Row count still under `meta.total`, but not at 50 | Not this release — that day genuinely has that many rows |
| Page shows "Ada masalah pada server" | The file did not upload whole. Re-upload; if it persists, roll back. |
| Ubah / Hapus buttons vanished | Wrong baseline — you have overwritten a **newer** Transfer.php. Roll back and ask before retrying. |

## Rollback

Upload the file you downloaded in Step 1 back over this one. The database is
untouched by this release, so there is nothing else to undo — the page simply
goes back to showing the first 50 rows of a day.
