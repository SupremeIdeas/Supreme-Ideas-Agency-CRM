# Admin path + white-label credentials — change guide (2026-09-14)

Owner-facing summary of two related security/access changes shipped the same
day, across all three repos. Kept here (rather than only in `PROGRESS.md`,
which is a build log written for the next Claude Code session) as a plain
record of exactly what changed, where, and why.

---

## 1. Dynamic, database-backed admin login path (NAARA-PROMPT-22)

**Scope:** `SupremeIdeas/NaaraSim` (master) only — merged in PR #79.

The admin panel's URL path is no longer a fixed value baked in at deploy
time. It now resolves from the database first, with the `.env` value as a
fallback:

- **Where it lives now:** Admin → Security → "Admin panel path" (super_admin
  only).
- **How to change it:** enter the new path, type it again to confirm, enter
  your current password, submit. It applies immediately — no redeploy, no
  cache clear, no restart.
- **Safety rails:** the new path can't collide with any other route or
  `public/` folder name; every super_admin gets an email the moment it
  changes; the change is written to the audit log; your own admin session
  stays logged in through the change (it isn't tied to the URL).
- **If you get the path wrong or forget it:** a wrong path is a plain 404 —
  it never redirects anywhere, so there's no way to "discover" the real one
  by guessing. Any super_admin can look it up or reset it via
  `php artisan tinker` (`Setting::setValue('admin_path', '...', 'security')`)
  or by checking the notification email sent at the last change.

**Master's own default changed too:** a fresh install of `NaaraSim` now
defaults to `/adminheavens` instead of `/adminmaster` (`.env.example` and
`config/admin.php`). This is cosmetic only for existing installs — it's just
the starting value a brand-new `.env` gets; anyone already running the
platform keeps whatever `ADMIN_PATH` they already have until they change it.

**White-label forks are unaffected by this default change.** Both
`NaaraSim-WhiteLabel` and `NaaraSim-White-Label-Extented` keep their own
`adminmaster` default — this was a deliberate choice, not an oversight, and
must be preserved the next time code syncs from master into the forks (the
sync script/process should treat `config/admin.php`'s literal default and
`.env.example`'s `ADMIN_PATH` line as fork-owned, not master-owned).

The dynamic-path *feature itself* — the Security-panel UI, the database
lookup, the safety rails above — reaches white-label installs automatically
through the existing white-label update/distribution system once it's
packaged and pushed, the same way every other master feature does. No
separate code change was needed in either fork repo for this piece.

---

## 2. White-label default admin credentials changed

**Scope:** `SupremeIdeas/NaaraSim-WhiteLabel` and
`SupremeIdeas/NaaraSim-White-Label-Extented` only. **Master
(`SupremeIdeas/NaaraSim`) is untouched** — its own default admin account
still uses its original credentials.

| | Old (both forks) | New (both forks) |
|---|---|---|
| Email | `supremeideasz@gmail.com` | `adminmaster1234@gmail.com` |
| Password | `22504108303@AdminMaster` | `123456789@AdminMaster` |

**Where this lives in code:** `database/seeders/DefaultAdminSeeder.php` in
each fork repo — just the two class constants, `EMAIL` and `PASSWORD`.
Every place that shows or checks the default admin login (the installer's
Done screen, the installer flow itself, the test suite) reads from these two
constants, so nothing else needed to change.

**This only affects a brand-new install.** `DefaultAdminSeeder` is
idempotent — it only creates the account if no user with that email exists
yet. An already-running white-label installation keeps whichever admin
account and password it already has; this change only changes what a fresh
`/install` run seeds going forward.

**As always: change this password immediately after first login** on any
new install — both README files and the Done screen say so.

Also updated: both forks' `README.md` now states the new default login
under the **Install** section, so it's visible without opening the seeder
source.

---

## 3. Fix: installer's Done screen sent operators to the wrong login

A follow-up spot-check turned up a real bug in all three repos: the Done
screen's **"Go to admin login"** button linked to `/login` — the ordinary
customer login — instead of the actual admin panel. Harmless while the
admin path was the well-known `adminmaster` default; a genuine dead end
now that the path is database-backed and no longer guessable on master.
The Done screen also never showed the admin path itself, so a fresh
install had no way to find out where the panel lives short of reading
source code.

**Fixed:** the Done screen now shows the admin panel path plainly (e.g.
`/adminheavens`) right alongside the email and password, and the button
links to the real admin login URL. Confirmed with new test assertions in
all three repos' `InstallerTest.php`, and the full suite is green on all
three.

---

## Where to verify each change

- Master PR: `SupremeIdeas/NaaraSim#79` (dynamic admin path) — merged.
- `NaaraSim-WhiteLabel`: `database/seeders/DefaultAdminSeeder.php` +
  `README.md` (credentials).
- `NaaraSim-White-Label-Extented`: same two files, same change.
- `PROGRESS.md` in master has the full technical write-up of the admin-path
  feature (routing bugs found and fixed along the way, the full test list,
  live-verification notes).
