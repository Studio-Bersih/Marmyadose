# UD84 — Session Handoff

**Written:** 2026-08-06, end of morning session
**Read this first when resuming.** It is the state of play, what is half-finished, and the traps that already cost time once.

---

## 1. Where we are, overall

`Instruction.md` lists ten code items across four sub-projects. Status:

| # | Item | Status |
|---|---|---|
| 1 | Cancel Invoice / retur | 🟡 **In progress** — backend done, frontend half-done |
| 2 | QRIS di nota | ✅ Merged |
| 3 | Format tanda tangan | ✅ Merged |
| 4 | Cetak DL + thermal 58mm, dua button | ✅ Merged |
| 5 | Satuan item di nota | ✅ Merged |
| 6 | Perbaikan Transaksi | ⬜ Not started (Stages 2 & 3 below) |
| 7 | Dashboard Sales (omzet & kinerja) | ⬜ Not started — **blocked**, see §6 |
| 8 | Sales melihat harga jual di Pesan Online | ⬜ Not started |
| 9 | Sales pengajuan discount → panel/pesanan | ⬜ Not started |
| 10 | 1 juta = 1 poin + dashboard poin | ⬜ Not started |

Plus one item **not** in `Instruction.md`, requested mid-session and delivered:

| — | Salesperson management CRUD | ✅ Merged |

"Bantu Buat QRIS" is a business service; "Desain Icon Baru" is a design deliverable. Neither is code.

**Nothing has been pushed to any remote.** Both repos have local commits on `main` only.

---

## 2. Repos and branches

| Repo | Path | Branch now | State |
|---|---|---|---|
| Frontend | `D:\Coedes\Production\me` | `ud84-cancel-invoice` | 3 commits ahead of `main`, working tree clean |
| Backend | `D:\Coedes\Production\Marmyadose` | `ud84-cancel-invoice` | 2 commits ahead of `main`, **your unrelated WIP still unstaged** |

Already merged into `main` in both repos (branches deleted):
- `ud84-nota-print` — sub-project 1
- `ud84-sales-crud` — salesperson management

---

## 3. EXACTLY where the current work stopped

Sub-project 2, **Stage 1 (Cancel Invoice)**. Spec: `me/docs/superpowers/specs/2026-08-06-ud84-cancel-invoice-design.md`.

### Done and committed

**Backend** (`Marmyadose`, branch `ud84-cancel-invoice`):
- `c1b13a6` — schema: `ud84_penjualan_rekap.STATUS`, `.POIN`, new `ud84_transaksi_log` table
- `ab39710` — `Transaksi.php` controller (cancel + audit trail read), `postPenjualan` repairs, all nine report read-sites honouring `STATUS`, `config/ud84.php`, **23 tests**

**Frontend** (`me`, branch `ud84-cancel-invoice`):
- `dffce40` — the design spec
- `69690ac` — `dibatalkan` on the `Receipt` type + `TRANSAKSI DIBATALKAN` banner on both nota layouts

**Schema is already applied to the local `dao` database.** Do not re-run those ALTERs.

Tests: `php artisan test` → **54 passed, 1 failed**. The one failure is the pre-existing `ExampleTest::test_the_application_returns_a_successful_response` on `GET /`, which was failing before any of this work started. Do not "fix" it.

`npm run check` → **0 errors, 6 warnings**. That is the baseline; it must not grow.

### NOT done — this is where to pick up

**`me/src/routes/ud84/panel/transaksi/+page.svelte` has not been touched at all.** It needs:

1. A **Tampilkan Dibatalkan** toggle beside the existing A-Z toggle, off by default, sending `TAMPILKAN_BATAL: true` in the `UD84/Daftar-Transaksi/Search` payload. The backend already accepts and honours it.
2. Cancelled rows rendered greyed with a `Dibatalkan` badge, and **no Cetak Ulang link** on them.
3. A **Batalkan** button in the detail drawer that opens a confirmation requiring a reason, then posts to `UD84/Daftar-Transaksi/Batal` with `{ KODE, ALASAN, OPERATOR }`.
   - `OPERATOR` comes from `JSON.parse(localStorage.getItem('Auth')).name`.
   - The response carries `data.GAGAL_RESTOK` (array of product names whose stock could **not** be returned) and `data.CATATAN` (system notes). If `GAGAL_RESTOK` is non-empty, show a toast the operator must dismiss — not a transient one — saying stock for those items needs adjusting via Logistik.
4. The `Transaksi` TS interface needs a `STATUS: "Aktif" | "Dibatalkan"` field; the search endpoint now returns it per row.

Optionally: surface the audit trail via `UD84/Daftar-Transaksi/Riwayat` (`{ KODE }`) in the drawer. The endpoint exists and is tested.

After that, verify in a browser (see §5) and merge both branches to `main`, then delete them.

---

## 4. Traps that already cost time — do not rediscover these

**Route cache.** `bootstrap/cache/routes-v7.php` exists. Any new route 404s until `php artisan route:clear`. The symptom is an empty page with no error. This burned a debugging round already. Same applies on production — it is in the deployment guide as a mandatory step.

**`phraseBox.ts` points at production.** `me/src/library/resources/phraseBox.ts` has `isProduction = true`, so the local frontend talks to `https://fae.deabakery.co.id/api/`. For local testing, flip it to `false` (→ `http://localhost:8000/api/`), and **flip it back before committing or merging**. It is currently `true` and committed as `true` — verify before any push. Shipping `false` breaks the live site.

**`routes/api.php` needs staging surgery.** Your uncommitted E-Money routes (`POS/Report/Delete-EMoney`, `POS/Report/Update-EMoney`) point at methods that exist only in your uncommitted `EMoney.php`. Committing them would break `route:cache` on production. The procedure used twice this session:
1. `git show HEAD:routes/api.php > <scratch>/base.php`
2. Write `base + only your new routes` to `routes/api.php`
3. `git add routes/api.php`
4. Re-insert the E-Money block into the working file so it stays unstaged
5. Verify: `git diff --cached routes/api.php` shows only yours, `git diff routes/api.php` shows only theirs

**Never `git add -A` in `Marmyadose`.** It holds ~14 unrelated modified files plus untracked `app/Models/Kosada/`.

**Never `RefreshDatabase` in a test.** It runs `migrate:fresh` and would drop every `ud84_*` table — none are covered by migrations, so they would not come back. Use `DatabaseTransactions`.

**Never `php artisan migrate`.** The `migrations` table holds only the project's original Laravel 9/10-era rows; `database/migrations/` now has Laravel 11-style `0001_01_01_*` files that are unrecorded, so migrate would try to create `users` (which exists) and fail. Schema ships as `.sql` pasted into phpMyAdmin.

**MySQL `SUM()` returns a string.** `assertSame` against an int fails. Cast in tests.

**CRLF warnings on every `Marmyadose` commit** are expected and harmless.

---

## 5. Local environment

- MySQL at `127.0.0.1:3306`, db `dao`, user `root`, password `root`. `.env` already points at it.
- Backend: `php artisan serve` → `http://localhost:8000`
- Frontend: `npm run dev` → `http://localhost:5173`
- Chrome for headless verification: `C:\Program Files\Google\Chrome\Application\chrome.exe`
  - Screenshot / print-to-PDF need `--virtual-time-budget=15000`. **Except** for toast checks, where 15000 outlasts svelte-sonner's 4-second auto-dismiss and gives a false pass — use `3000` there.
  - Blank ~1.5KB PDFs are an intermittent flake. Re-run; never count one as a pass.
- The UD84 panel nav redirects to login unless `localStorage.Auth` is set. To screenshot a panel page headlessly, create a temporary `src/routes/ud84/dev-seed/+page.svelte` that sets `localStorage.Auth` then `goto`s the target — **and delete it before committing**. That was done and removed once already.

Test data note: a real sale exists locally from earlier verification — `UNIQUE 6a738e24212fb` (product 111, qty 2, CASH 60000 < TOTAL 100000). Useful for exercising Sisa Tagihan.

---

## 6. Decisions already made — do not re-litigate

From the brainstorming rounds:

- **Cancel = whole invoice only.** Per-item returns are out of scope for all stages; they need a refund/credit model that does not exist. The Logistik → Retur flow already adjusts stock, just unlinked from invoices.
- **Cancel reverses stock and points**, writes a reversing `ud84_logs` row rather than deleting the original, and does **not** touch `CASH`/`DP`/`TOTAL`/`POTONGAN`.
- **Cancelled sales are hidden from lists** unless a "show cancelled" filter is ticked, and **always excluded from every revenue total**, even when shown.
- **No access gate**, but every cancellation records operator, time and reason.
- **Perbaikan Transaksi = full item editing** (owner chose this over the safer options, knowingly).
- **Item-level editing is NOT offered on legacy transactions.** Established from real data: 21 of 57 detail lines reference a product that no longer exists, and 56 of 57 have no `SATUAN`, so the stock multiplier would be a guess against `JUMLAH_PER_ITEM` values commonly of 10. Those transactions get header-only correction plus cancel.
- **Sub-project 3's sales dashboard is blocked**: `ud84_penjualan_rekap` has no salesperson column, so completed sales cannot be attributed to a person. Only `ud84_pesanan_rekap` links to sales, which is why `ud84_analisa_sales` measures verified *orders at list price*, not revenue. Fixing it needs a schema change plus a way to attribute at checkout.

---

## 7. Remaining stages of sub-project 2

**Stage 2 — Perbaikan Pesanan** (unverified orders). Edit customer, WhatsApp, sales, notes, items and quantities on `ud84_pesanan_rekap`/`_detail` while `VALID` is null. Orders touch neither stock nor money, so this is the low-risk one. Not specced yet.

**Stage 3 — Perbaikan Transaksi** (completed sales, full item editing). Header plus add/remove/change lines, with stock re-adjustment, reversing logs and point recomputation. Reuses Stage 1's audit table and stock machinery. Gated to transactions where every line resolves. Not specced yet.

---

## 8. Deferred minors, carried forward

Logged from reviews, none blocking:

- The response still embeds the raw `rekap` row beside `ringkasan`. Layouts must read `ringkasan.*` — `rekap.KEMBALIAN` is wrong whenever DP was used, and `rekap.TOTAL` is net of potongan. A `@deprecated` note on the `Rekap` type would help.
- `PRINT_SAFETY = 1.02` in the nota container was measured on only two short receipts. **Test a 5+ item thermal receipt on the real printer.**
- No physical printer has ever been tested — geometry is verified in Chrome only.
- `UD84Navigation.svelte` hardcodes `activeMenu = 'Transaksi'`, so every panel page highlights "Transaksi". Pre-existing.
- No `try/catch` around `localStorage` access in the nota container; `selectPaper()` runs first in both print handlers, so a throw would kill printing.
- Print CSS enumerates `[data-theme="portfolio"]` and `[data-theme="portfolio-dark"]`; a third theme would silently not be covered.
- `window.open`'s return is unchecked, so a popup blocker would silently no-op "Cetak Nota".
- Percentage discounts that are not whole rupiah can make a printed line differ by a rupiah or two, because the discount is rounded before storage. Fix belongs at the POS (`Math.round(doDiscount)`).

---

## 9. Deployment

`me/docs/deployment/2026-08-06-ud84-nota-print-deploy.md` covers sub-project 1 + the sales CRUD as one manual deployment: both `ALTER TABLE`s, the five backend files, the `git archive` command that builds the zip from the committed branch so your WIP cannot leak, mandatory `route:clear`, and verification steps.

**It does not yet cover cancel invoice.** When Stage 1 finishes, add:
- the `2026_08_06_add_cancel_invoice.sql` statements (STATUS, POIN, KODE widening)
- `app/Http/Controllers/UD84/Transaksi.php` (new)
- `config/ud84.php` (new — needs `config:clear` on deploy)
- the updated `Report.php`, `Penjualan.php`, `routes/api.php`

---

## 10. Suggested first move next session

```bash
cd "D:/Coedes/Production/me"        && git log --oneline -5 && git status --short
cd "D:/Coedes/Production/Marmyadose" && git log --oneline -5 && git status --short
cd "D:/Coedes/Production/Marmyadose" && php artisan test 2>&1 | tail -4
```

Expect: both repos on `ud84-cancel-invoice`, `me` clean, `Marmyadose` showing only your WIP, and 54 passed / 1 pre-existing failure.

Then pick up §3 — the Transaksi page is the only thing left in Stage 1.
