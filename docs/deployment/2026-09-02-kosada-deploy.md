# Kosada — manual deployment, 2026-09-02

Backend only. **Two files, no SQL, about five minutes.**

| | |
|---|---|
| **Target** | cPanel — `https://fae.deabakery.co.id/api/` |
| **Database** | no change. Nothing to run in phpMyAdmin. |
| **Baseline assumed** | the 2026-08-16 Kosada release is live (`Transfer.php` exists and Hapus works) |
| **This release** | Transfer Harian revisions: thousand separators, and Ubah on a recorded row |
| **Downtime** | none |
| **Frontend** | nothing to upload — Vercel deploys it from `main` on its own |

---

## Why there is no SQL this time

`kosada_transfer_harian` already has `UPDATED_AT`, which is the only column the
new endpoint writes that the old code did not. The August release created the
table with both timestamps.

So the usual "database first, code second" rule has nothing to order here. Upload
and you are done.

---

## Contents

```
upload/                                extracts over public_html/api/
├── app/Http/Controllers/Kosada/Transfer.php    modified — adds updateTransfer()
└── routes/api.php                              modified — adds one route
```

Nothing here touches `vendor/`, `storage/`, `.env`, `public/`, `config/`, or any
`UD84/` file. Uploading it cannot disturb the shop's system.

---

## Step 1 — Keep a copy of what is there now

cPanel → File Manager → `public_html/api/`. Download the two files you are about
to replace:

- `app/Http/Controllers/Kosada/Transfer.php`
- `routes/api.php`

This is the rollback. It takes thirty seconds and it is the only thing standing
between a bad upload and a broken Transfer Harian page.

## Step 2 — Upload

Upload the two files from `upload/`, each over the file of the same name, keeping
the folder structure exactly:

| Upload this | Over this |
|---|---|
| `upload/app/Http/Controllers/Kosada/Transfer.php` | `public_html/api/app/Http/Controllers/Kosada/Transfer.php` |
| `upload/routes/api.php` | `public_html/api/routes/api.php` |

## Step 3 — Clear the route cache

`routes/api.php` changed, so a cached route table would keep serving the old list
and `/Kosada/Ubah-Transfer` would 404 even though the file is there.

cPanel → Terminal (or SSH), from `public_html/api/`:

```
php artisan route:clear
php artisan config:clear
```

No Terminal on the plan? Delete `bootstrap/cache/routes-v7.php` in File Manager
if it exists. If it does not exist, routes were never cached and there is nothing
to clear.

## Step 4 — Check it landed

In a browser, open:

```
https://fae.deabakery.co.id/api/Kosada/Transfer-Harian?tanggal=2026-09-02
```

That should return JSON as it always has — proof the upload did not break the
existing routes.

Then, on the Kosada site as an **Administrator**, open Transfer Harian on a day
that has rows. Each row should now show **Ubah** beside **Hapus**. Click Ubah,
change the nominal, press Simpan, and enter your password at the confirmation.
The row should update and the total at the bottom should move with it.

Signed in as **Staff**, both Ubah and Hapus must be greyed out.

## What "it did not work" looks like

| Symptom | Cause |
|---|---|
| Ubah button missing entirely | Frontend not deployed yet — check Vercel, not cPanel |
| "Data transfer tidak ditemukan" on a row that plainly exists | `Transfer.php` uploaded to the wrong folder |
| 404 from `/Kosada/Ubah-Transfer` | Step 3 skipped — the route cache is stale |
| "Tindakan ini hanya untuk akun Administrator" for a real admin | Not this release; the account's `privilege` is not `Administrator` |

## Rollback

Upload the two files you downloaded in Step 1 back over these, then run Step 3
again. The database is untouched by this release, so there is nothing else to
undo — any edits made through the new button simply stay as they are, which is
the correct data either way.
