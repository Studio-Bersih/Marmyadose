# UD84 — Session Handoff

**Written:** 2026-08-06, end of evening session (supersedes all earlier versions)
**Read this first when resuming.** It is the state of play, what is half-finished, and the traps that already cost time once.

---

## 1. Where we are, overall

`Instruction.md` lists ten code items across four sub-projects. Status:

| # | Item | Status |
|---|---|---|
| 1 | Cancel Invoice / retur | ✅ **Stage 1 merged** — see §3 for what "Stage 1" covers |
| — | Perbaikan Pesanan (Stage 2 of the same sub-project) | ✅ **Merged** — see §3b |
| 2 | QRIS di nota | ✅ Merged |
| 3 | Format tanda tangan | ✅ Merged |
| 4 | Cetak DL + thermal 58mm, dua button | ✅ Merged |
| 5 | Satuan item di nota | ✅ Merged |
| 6 | Perbaikan Transaksi | 🟡 Stage 2 done (§3b); **Stage 3 not started** (§7) |
| 7 | Dashboard Sales (omzet & kinerja) | ⬜ Not started — **blocked**, see §6 |
| 8 | Sales melihat harga jual di Pesan Online | ⬜ Not started |
| 9 | Sales pengajuan discount → panel/pesanan | ⬜ Not started |
| 10 | 1 juta = 1 poin + dashboard poin | ⬜ Not started |

Plus one item **not** in `Instruction.md`, requested and delivered:

| — | Salesperson management CRUD | ✅ Merged |

"Bantu Buat QRIS" is a business service; "Desain Icon Baru" is a design deliverable. Neither is code.

**Nothing has been pushed to any remote, and nothing is deployed.** Both repos have local commits on `main` only. Three releases are now written up and waiting, and their order is not optional — see §10.

---

## 2. Repos and branches

| Repo | Path | Branch now | State |
|---|---|---|---|
| Frontend | `D:\Coedes\Production\me` | `main` | working tree clean |
| Backend | `D:\Coedes\Production\Marmyadose` | `main` | working tree clean |

All UD84 branches are merged and deleted: `ud84-nota-print`, `ud84-sales-crud`, `ud84-cancel-invoice`, `ud84-perbaikan-pesanan`.

The owner's unrelated WIP (POS, Kosada, E-Money, DTOs) is **committed on `Marmyadose` main** as of `02e5c6c`. It is no longer sitting unstaged, so `git status` is clean — but it is still unfinished work that must not be deployed except where a release explicitly needs it (see the `EMoney.php` note in the cancel deployment guide).

---

## 3. Sub-project 2, Stage 1 — done

**Cancel Invoice is complete on both sides and merged.**

Spec: `me/docs/superpowers/specs/2026-08-06-ud84-cancel-invoice-design.md`.
Deployment: `me/docs/deployment/2026-08-06-ud84-cancel-invoice-deploy.md`.

Backend (`Marmyadose` main): schema (`STATUS`, `POIN`, `KODE` widening, `ud84_transaksi_log`), `Transaksi.php` (cancel + audit read), `postPenjualan` repairs, all nine report read-sites honouring `STATUS`, `config/ud84.php`, 23 tests.

Frontend (`me` main): the `DIBATALKAN` banner on both nota papers, and the Transaksi page — **Tampilkan Dibatalkan** toggle, greyed cancelled rows with a badge and no Cetak Ulang, a **Batalkan** action in the drawer requiring a reason, the audit trail rendered below it, and a stock-warning toast that must be dismissed. The login page now stores the operator's name in `localStorage.Auth` (it used to store a bare `true`) so cancellations record who did them.

**Verified end-to-end in headless Chrome against the local backend**, driven over CDP rather than screenshot-only: cancel restores stock 16 → 28 with a reversing `ud84_logs` row and the original untouched, money fields unchanged, a blank reason refused, the warning toast still on screen 13 seconds later when the success toast has gone, totals reading Rp 0 with the cancelled row displayed, both nota papers printing the banner, and login storing the operator name.

**Local database was restored afterwards** — the test sale `6a738e24212fb` is `Aktif` again with stock back at 16, and the verification rows are deleted. Nothing cancelled remains in local data.

Tests: `php artisan test` → **54 passed, 1 failed**. That one is the pre-existing `ExampleTest` on `GET /`, failing since before any of this work. Do not "fix" it.
`npm run check` → **0 errors, 6 warnings**. That is the baseline; it must not grow.

---

## 3b. Sub-project 2, Stage 2 — done

**Perbaikan Pesanan is complete on both sides and merged.**

Spec: `me/docs/superpowers/specs/2026-08-06-ud84-perbaikan-pesanan-design.md`.
Plan: `me/docs/superpowers/plans/2026-08-06-ud84-perbaikan-pesanan.md`.
Deployment: `me/docs/deployment/2026-08-06-ud84-perbaikan-pesanan-deploy.md`.

Panel staff can correct an order that has not been verified — customer, WhatsApp, salesperson, notes, quantities, added and removed products — in one atomic save recording who changed what. A verified order is immune to editing **and** deletion; deleting one records its full snapshot first; verifying one finally reports verification instead of deletion.

Built with subagent-driven development: 8 tasks, each reviewed, plus a whole-branch review. 28 feature tests (`Marmyadose/tests/Feature/UD84/PerbaikanPesananTest.php`), full suite **84 passed / 1 pre-existing `ExampleTest` failure**. `npm run check` 0 errors / 6 warnings. Verified in headless Chrome over CDP: 36 assertions, including proof that one edit is one request.

**The constraint that shaped the implementation:** `ud84_analisa_sales` is a VIEW dating every line by `ud84_pesanan_detail.CREATED_AT`, so lines are reconciled **in place** — the obvious delete-all-and-reinsert would move an edited March order's contribution into the present with nothing reporting an error. If Stage 3 touches this machinery, that property must survive.

**Things review caught that are worth remembering:**
- stored duplicate lines for one product used to collapse in the line map, so removing that product deleted only one row. Now refused outright rather than merged.
- the verified lock is enforced under a `lockForUpdate` **inside** the transaction, not merely before it — a second operator pressing Validasi mid-edit used to be able to slip past.
- `getItems` used to throw on an order whose product was deleted, making it unopenable and therefore unfixable. Such a line now returns `ADA: false` and can only be removed.

---

## 4. Traps that already cost time — do not rediscover these

**Route cache.** `bootstrap/cache/routes-v7.php` exists. Any new route 404s until `php artisan route:clear`. The symptom is an empty page with no error. Same on production — it is in both deployment guides as mandatory.

**Config cache too, now.** `config/ud84.php` is new. A stale `bootstrap/cache/config.php` makes `config('ud84.poin_per_rupiah')` read null, and a cancellation then deducts **zero points silently**. `config:clear` is as mandatory as `route:clear` for the cancel release.

**`phraseBox.ts` points at production.** `me/src/library/resources/phraseBox.ts` has `isProduction = true`. For local testing flip it to `false`, and **flip it back before committing**. It is currently `true`. Note that `sed -i` on it rewrites CRLF to LF and makes git show the file as modified with an empty diff — `git checkout -- <file>` is the clean way back.

**Never `git add -A` in `Marmyadose`.** Even now that the WIP is committed, that habit is what would sweep the next batch in.

**Never `RefreshDatabase` in a test.** It runs `migrate:fresh` and would drop every `ud84_*` table — none are covered by migrations, so they would not come back. Use `DatabaseTransactions`.

**Never `php artisan migrate`.** The `migrations` table holds only the project's original Laravel 9/10-era rows; `database/migrations/` now has Laravel 11-style `0001_01_01_*` files that are unrecorded, so migrate would try to create `users` (which exists) and fail. Schema ships as `.sql` pasted into phpMyAdmin.

**AUTO_INCREMENT is not rolled back by `DatabaseTransactions`.** Counters climb with every test run even though the rows vanish. That is how the `SALES` tinyint ceiling in §8 surfaced, and it is worth remembering before dismissing a test that "used to pass".

**MySQL `SUM()` returns a string.** `assertSame` against an int fails. Cast in tests.

**CRLF warnings on every `Marmyadose` commit** are expected and harmless.

---

## 5. Local environment

- MySQL at `127.0.0.1:3306`, db `dao`, user `root`, password `root`. `.env` already points at it.
- Backend: `php artisan serve` → `http://localhost:8000`
- Frontend: `npm run dev` → `http://localhost:5173`
- Chrome: `C:\Program Files\Google\Chrome\Application\chrome.exe`

**Driving the browser, rather than just photographing it.** Launch Chrome with `--headless=new --remote-debugging-port=9222 --user-data-dir=<scratch>/chrome-profile` and talk to it over CDP from plain Node — Node 24 has a global `WebSocket`, so no puppeteer, no install. A ~90-line driver (open tab, `Runtime.evaluate`, `waitFor`, `clickText`, `captureScreenshot`) is enough to click through a real flow and assert on the DOM. That is how Stage 1 was verified.

Two things that matter with this approach:
- Open the tab on `about:blank` and **navigate afterwards**; `localStorage` on the tab `/json/new?url=` opens throws `SecurityError`.
- Svelte's `bind:value` ignores a plain `el.value = x`. Follow it with `el.dispatchEvent(new Event('input', { bubbles: true }))`.

This replaces the old dev-seed page trick — set `localStorage.Auth` over CDP instead, and there is no temporary route to remember to delete.

If you do fall back to `--screenshot`/`--print-to-pdf`: use `--virtual-time-budget=15000`, except for toast checks where 15000 outlasts svelte-sonner's 4-second auto-dismiss and gives a false pass. Blank ~1.5KB PDFs are an intermittent flake; re-run, never count one as a pass.

Test data: a real sale exists locally — `UNIQUE 6a738e24212fb` (product 111, qty 2 Set, CASH 60000 < TOTAL 100000). Useful for Sisa Tagihan, and for cancelling.

---

## 6. Decisions already made — do not re-litigate

- **Cancel = whole invoice only.** Per-item returns are out of scope for all stages; they need a refund/credit model that does not exist. The Logistik → Retur flow already adjusts stock, just unlinked from invoices.
- **Cancel reverses stock and points**, writes a reversing `ud84_logs` row rather than deleting the original, and does **not** touch `CASH`/`DP`/`TOTAL`/`POTONGAN`.
- **Cancelled sales are hidden from lists** unless a "show cancelled" filter is ticked, and **always excluded from every revenue total**, even when shown.
- **No access gate**, but every cancellation records operator, time and reason.
- **Perbaikan Transaksi = full item editing** (owner chose this over the safer options, knowingly).
- **Item-level editing is NOT offered on legacy transactions.** 21 of 57 detail lines reference a product that no longer exists, and 56 of 57 have no `SATUAN`, so the stock multiplier would be a guess against `JUMLAH_PER_ITEM` values commonly of 10. Those transactions get header-only correction plus cancel.
- **Sub-project 3's sales dashboard is blocked**: `ud84_penjualan_rekap` has no salesperson column, so completed sales cannot be attributed to a person. Only `ud84_pesanan_rekap` links to sales, which is why `ud84_analisa_sales` measures verified *orders at list price*, not revenue. Fixing it needs a schema change plus a way to attribute at checkout.

---

## 7. Remaining stages of sub-project 2

**Stage 2 — Perbaikan Pesanan.** ✅ Done and merged — §3b.

**Stage 3 — Perbaikan Transaksi** (completed sales, full item editing). Header plus add/remove/change lines, with stock re-adjustment, reversing logs and point recomputation. Reuses Stage 1's audit table and stock machinery, and Stage 2's editor shape. Gated to transactions where every line resolves. Not specced yet.

Stage 3 inherits four things Stage 2's reviews flagged as *mattering more for transactions than for orders*, all recorded in §9:
- audit snapshots store `KODE_ITEM` with no product name, so a line the edit did not touch cannot be named later if the product is deleted. Low-stakes for an order; not for a sale.
- `ringkasPerubahan` does not trim `CATATAN`, so a stored `NULL` note becomes `''` without the change list saying so. Stage 3 reuses that method.
- `db.ts` retries a failed POST twice. Stage 2's endpoints are idempotent by accident — the no-op guard absorbs a duplicate save. Anything in Stage 3 that is **not** idempotent needs that thought through first.
- `removeItem` already accepts an `ALASAN` the UI never sends. Stage 3 will want it.

---

## 8. The SALES widening, riding along with the cancel release

**`ud84_pesanan_rekap.SALES` is `tinyint` and holds `ud84_sales.ID`, an `int` auto_increment.** A ceiling of 127 on a value that only ever climbs — every salesperson ever created consumes one permanently, and deleting a salesperson does not give it back. Two exist today, so nothing is broken now; the Sales management page is what makes the 128th a matter of time. Strict mode is on, so the overflow errors rather than clamping, and every order placed with that salesperson from the public Pesan Online page would fail.

The statement is at `Marmyadose/database/sql/2026_08_06_widen_pesanan_sales.sql` and **already applied locally** (which is what brought the test suite back to 54 passed). On the owner's call it now ships as the **fifth SQL statement of the cancel-invoice release**, labelled unrelated in three places so nobody has to work out later which statement belonged to which problem. No code change accompanies it, in either direction.

---

## 9. Deferred minors, carried forward

None blocking. From Stage 2's reviews, triaged as safe to defer by the final whole-branch review:

- **`db.ts` retries a failed POST twice**, so a save that commits but loses its response is re-sent. Stage 2 fails safe — the second attempt hits "Tidak ada perubahan untuk disimpan." — but the operator is told an error after a success. House-wide, shared with Stage 1.
- **`getPesanan` returns 400 and `getItems`' catch returns 500**, against the house always-200 rule. Pre-existing, owned by no stage.
- **No route in `api.php` carries auth middleware.** House-wide, pre-existing, and the largest of these.
- **The Pesanan list has no `onMount`**, so it opens reading "Tidak ada data" until the operator presses search. Pre-existing on `main`; documented in the runbook so it is not mistaken for a broken deploy.
- **`postPesanan` has no server-side dedupe**, so it remains the upstream source of the duplicate-line condition Stage 2 now refuses to edit around.
- A deleted (not deactivated) salesperson, a decimal quantity, and a stale drawer after a failed refetch — all handled or harmless; see the plan's ledger for the full list.

Carried from earlier work:

- **A cancelled nota still prints the QRIS block and Sisa Tagihan** under the DIBATALKAN banner — it says "void" and then asks to be paid. Worth suppressing both on a cancelled receipt.
- The detail response still embeds the raw `rekap` row beside `ringkasan`. Layouts must read `ringkasan.*` — `rekap.KEMBALIAN` is wrong whenever DP was used, and `rekap.TOTAL` is net of potongan. A `@deprecated` note on the `Rekap` type would help.
- `PRINT_SAFETY = 1.02` in the nota container was measured on only two short receipts. **Test a 5+ item thermal receipt on the real printer.**
- No physical printer has ever been tested — geometry is verified in Chrome only.
- `UD84Navigation.svelte` hardcodes `activeMenu = 'Transaksi'`, so every panel page highlights "Transaksi", and greets a hardcoded "Richie" while the real operator name now sits in `localStorage.Auth`. Both pre-existing, both now trivially fixable.
- `UD84/Daftar-Transaksi` (the GET list endpoint) does not return `STATUS` and ignores the show-cancelled filter. Nothing calls it any more — the Transaksi page uses `Search` throughout — but it is a trap for the next caller.
- The login page checks `status === "Unauthorized"`, which `db()` can never return: a 401 makes `fetchWithRetry` throw and the helper reports `status: "error"`. Wrong credentials therefore fall through to the success path. Pre-existing, worth fixing when login is next touched.
- No `try/catch` around `localStorage` in the nota container; `selectPaper()` runs first in both print handlers, so a throw would kill printing. (The Transaksi page's own access is guarded.)
- Print CSS enumerates `[data-theme="portfolio"]` and `[data-theme="portfolio-dark"]`; a third theme would silently not be covered.
- `window.open`'s return is unchecked, so a popup blocker would silently no-op "Cetak Nota".
- Percentage discounts that are not whole rupiah can make a printed line differ by a rupiah or two, because the discount is rounded before storage. Fix belongs at the POS (`Math.round(doDiscount)`).

---

## 10. Deployment

Three runbooks, in this order:

1. `me/docs/deployment/2026-08-06-ud84-nota-print-deploy.md` — sub-project 1 + sales CRUD.
2. `me/docs/deployment/2026-08-06-ud84-cancel-invoice-deploy.md` — cancel invoice (plus the `SALES` widening riding along).
3. `me/docs/deployment/2026-08-06-ud84-perbaikan-pesanan-deploy.md` — perbaikan pesanan. **No SQL at all**, but it needs `ud84_transaksi_log` *and* `UD84/Transaksi.php`, both of which ship with release 2 — two independent reasons it cannot go first.

**The order matters.** Release 2 edits `Report.php` and `Penjualan.php` again; uploading release 1's copies afterwards would quietly roll it back. Each guide says so at the top.

---

## 11. Suggested first move next session

```bash
cd "D:/Coedes/Production/me"        && git log --oneline -3 && git status --short
cd "D:/Coedes/Production/Marmyadose" && git log --oneline -3 && git status --short
cd "D:/Coedes/Production/Marmyadose" && php artisan test 2>&1 | tail -4
```

Expect both repos on `main` and clean, and **84 passed / 1 pre-existing failure**.

Then pick the next piece of work. **Stage 3 (perbaikan transaksi) is the natural continuation** — it is what `Instruction.md` literally asks for, and Stages 1 and 2 have now built everything it needs: the audit table, the stock-reversal machinery, the editor shape, and the in-place reconciliation pattern. Read §7's four inherited concerns before speccing it. Items 8, 9 and 10 are untouched; item 7 stays blocked until someone decides how a completed sale gets attributed to a salesperson.
