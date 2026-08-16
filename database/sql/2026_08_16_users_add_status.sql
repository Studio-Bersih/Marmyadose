-- Shared `users` table — add an activation flag.
--
-- Apply this in phpMyAdmin BEFORE deploying the matching backend code.
-- Kosada's account management reads and writes STATUS; without the column the
-- account list and every account update fails.
--
-- Why a flag instead of deleting the row: `users.id` is what identifies who did
-- what. Deleting a departed staff member's row destroys that record, and the
-- co-op needs to be able to say who had access and when. Deactivating removes
-- their ability to log in while leaving the account visible in the register.
--
--
-- IMPORTANT — this table is SHARED with UD84.
--
-- The column is additive and defaults to 'Aktif', so every existing row stays
-- exactly as it is. UD84's Authenticate@logIn calls Auth::attempt() and does not
-- look at STATUS, so UD84 is completely unaffected by this change. Only Kosada's
-- own login and account endpoints check it.
--
-- The privilege column already exists as enum('Administrator','Staff') and is
-- what Kosada uses for its two roles, so no change is needed there. `groups`
-- already scopes rows to an app ('Kosada' / 'UD84').
--
-- Do NOT run `php artisan migrate` on this database. See the note in
-- 2026_08_16_kosada_add_member_id.sql.

ALTER TABLE `users`
  ADD COLUMN `STATUS` ENUM('Aktif','Nonaktif') NOT NULL DEFAULT 'Aktif' AFTER `privilege`;

-- Sanity check after running (every existing account should read 'Aktif'):
--
--   SELECT id, name, email, privilege, `groups`, STATUS FROM users;
