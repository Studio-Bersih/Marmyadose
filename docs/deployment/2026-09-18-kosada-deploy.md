# Kosada — manual deployment, 2026-09-18

**One SQL statement, three backend files, then the frontend.** The order matters.

| | |
|---|---|
| **Target** | cPanel — `https://fae.deabakery.co.id/api/` |
| **Database** | one additive column: `kosada_kredit.URUTAN_ATM` |
| **Baseline assumed** | the 2026-09-06 release is live |
| **Downtime** | none |
| **Branches** | `feat/kosada-macet-urutan-atm` in both `Marmyadose` and `Kosada` |

---

## What this delivers

Requested by the client:

> 1. untuk data macet, sepertinya butuh tambahan satu kolom untuk informasi kapan pinjaman diambil.
> 2. untuk data macet, perlu opsi edit dan menghapus
> 3. untuk data laporan tiap bulan [...] apakah bisa diedit manual supaya bisa mengikuti urutan atm yang ada di buku atm tiap marketing?

1. **Data Macet — Tgl Pinjaman.** A new column, on screen and on the printed sheet,
   showing `kosada_kredit.CREATED_AT` — the same date the Laporan calls Tanggal Pinjaman.
2. **Data Macet — Ubah and Hapus.** Administrator-only, password re-verified on the
   server like Selesai. Ubah edits the reason and the date the loan went bad; every
   nominal stays computed from the installments. Hapus removes the macet row only —
   the loan, its installments and the Dashboard are untouched, and the loan can be
   registered as macet again.
3. **Laporan — No. ATM.** A number field on each row. Staff type the loan's position
   in the marketing's ATM book; the report and its print sheet sort by it. Loans with
   no number follow, newest first — the old order — so nothing changes until someone
   starts numbering. With "Semua Marketing" the report groups by marketing first,
   because every book has its own #1.

The number is stored **per loan**. A member's new loan starts without one.

---

## Step 1 — SQL (phpMyAdmin), BEFORE the code

Run `database/sql/2026_09_18_kosada_add_urutan_atm.sql`:

```sql
ALTER TABLE `kosada_kredit`
  ADD COLUMN `URUTAN_ATM` INT UNSIGNED NULL DEFAULT NULL AFTER `HIDDEN_FROM_REPORT`;
```

If the code goes up first, **every Laporan request fails** — it sorts on this column.

Check: `SHOW COLUMNS FROM kosada_kredit LIKE 'URUTAN_ATM';` returns one row.

## Step 2 — Backend

Download these three from `public_html/api/` first — they are the rollback:

| Upload this | Over this |
|---|---|
| `app/Http/Controllers/Kosada/Macet.php` | `public_html/api/app/Http/Controllers/Kosada/Macet.php` |
| `app/Http/Controllers/Kosada/Report.php` | `public_html/api/app/Http/Controllers/Kosada/Report.php` |
| `routes/api.php` | `public_html/api/routes/api.php` |

Then, from `public_html/api/`:

```
php artisan route:clear
php artisan config:clear
```

Three new routes (`Ubah-Macet`, `Hapus-Macet`, `Urutan-ATM`) 404 until the route cache is cleared.

## Step 3 — Frontend

Merge `feat/kosada-macet-urutan-atm` into `main` in `Kosada` and push. Vercel deploys it.
Do this **after** Step 2: the new buttons call routes that only the new backend has.

## Step 4 — Check it landed

- **Data Macet:** a *Tgl Pinjaman* column with dates. As an Administrator, *Ubah* and
  *Hapus* are enabled; as Staff they are greyed out.
- **Laporan:** pick a month and one marketing. Type `1` in the *No. ATM* of a row near
  the bottom, press Enter, then *Terapkan urutan* — that row moves to the top. *Cetak*
  shows the same order with the number in its own column. Clear the field to undo.

## What "it did not work" looks like

| Symptom | Cause |
|---|---|
| Laporan shows "Ada masalah pada server" | Step 1 was skipped — the column does not exist |
| Ubah / Hapus / No. ATM save says "Ada masalah pada server" | Route cache — run Step 2's `route:clear` |
| Tgl Pinjaman column shows `-` everywhere | Frontend is new, backend is old — Step 2 did not land |

## Rollback

Upload the three downloaded files back and run `route:clear`. The column may stay:
the old code ignores it. Dropping it would throw away every ATM number staff typed.
