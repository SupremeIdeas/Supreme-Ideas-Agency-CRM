# NAARA PROMPT 22: Skins in the White Label forks, then a license-gated skin allowance (2 for White Label, 5 for Extended)

**For:** Claude Code, working across three repos: `NaaraSim-main` (master), `NaaraSim-WhiteLabel`, `NaaraSim-White-Label-Extented`.
**Depends on:** `NAARA-PROMPT-20-SURFACE-SYSTEM-BLUEPRINT-rev11.md` (the skin engine: tokens, 35 skins, `appearance_presets`, `user_appearance`, `AppearanceResolver`). Read its §13 to §19 and §31 to §36 first. `NAARA-PROMPT-21-SKIN-ACCESS-JOURNEYS-UNLOCK-BLUEPRINT.md` is **not** ported to the forks (see Decision D5).
**Also read before touching anything:** `CLAUDE.md` (especially "WHITE-LABEL SHIPPING DISCIPLINE" and "GITHUB ACTIONS BUDGET") and `docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md`.
**Status of facts:** §2 was read from the three Sept 22 snapshots. Anything marked **VERIFY** must be confirmed in the real committed `HEAD` before you rely on it. Anything you cannot verify goes in the report as **UNVERIFIED**; never fill a gap with an assumption.

---

## 0. How to work

1. One batch at a time. After each batch: update `PROGRESS.md` (DONE list plus the exact next step at the top of NEXT), run `vendor/bin/phpunit` locally, run `npm run build` for any view/CSS change, and browser-check mobile (390px) and desktop (1280px), light and dark.
2. **CI is manual-only until the owner says otherwise.** Do not re-enable `push:`, `pull_request:` or `schedule:` triggers. Put `[skip ci]` in every commit subject. Test locally.
3. **Surgical edits, not rebuilds.** Do not rewrite files you only need to extend. Where a fork file differs from master, merge by hand; never overwrite a fork file with the master copy.
4. **Before porting any file to a fork, run `git show HEAD:<path>` in the target repo** and compare. The Sept 22 snapshots already differ between master and the forks (`layouts/app.blade.php` and `layouts/admin.blade.php` are not identical; the forks have no `help-center.blade.php`). Do not assume parity.
5. Repository rules from `CLAUDE.md` apply: no emoji, SVG sprite icons only (`resources/views/partials/icon-sprite.blade.php`, ids prefixed `i-`), dark mode on every element, loading states and disabled buttons on every state-changing action, admin pages behind `{adminGateway}` with no admin URL in user markup (S25).
6. **Boundary discipline (non-negotiable).** The *authority* for "how many skins a license gets" lives only on master. A fork is a consumer: it receives a number and obeys it. Do not put a tier-to-count map, an admin override, or any decision logic in either fork. The boundary fitness tests in the forks must keep passing, and you will extend them (Batch 7).
7. Report with evidence. Every claim in the final report is backed by a command output, a test name, or a screenshot path.

---

## 1. Decisions already made by the owner (locked)

1. Commit the skins feature into **WhiteLabel** and **White Label Extended** (both forks), test it, and **confirm** it works there before anything else is added.
2. After a license is **activated with its license key**, the fork unlocks a limited number of skins:
   - **White Label (normal tier): 2 skins.** One is the licensee's default skin; the other is the skin their end users can switch to.
   - **White Label Extended: 5 skins.** (Extended V2 is the same tier and gets the same 5.)
3. The licensee **selects which skins** they want (up to their allowance) and picks which one is the default.
4. Every skin that is **not** selected **hides automatically**. Only the selected skins are active and ready for the licensee's end users to use at any time.
5. The default skin counts toward the allowance (2 means default plus one switch skin; 5 means default plus four).

---

## 2. Verified facts (read from the Sept 22 snapshots)

### 2.1 Headline finding: the skins feature is not in any of the three repos

| Check | Master | WhiteLabel | Extended |
|---|---|---|---|
| `AppearanceResolver`, `user_appearance`, `appearance_presets` anywhere in `app/`, `database/`, `resources/`, `routes/`, `config/` | none | none | none |
| `resources/css/nx-skins/` or `data-nx-skin` / `data-skin` in CSS, JS, Blade | none | none | none |
| `resources/views/components/nx/` (Prompt 20 §3 primitives) | none | none | none |
| `config/appearance.php` | none | none | none |
| Migrations | newest is `2026_09_22_100400_create_brand_partner_followers_table.php`, the exact "latest existing migration" Prompt 20 §17.1 was written against | same | same |
| What does exist | the older **15-preset `ThemePreset` system** (`app/Support/ThemePreset.php`, `app/Models/ThemePreset.php`, `Admin/ThemePicker`, `theme_presets` table) | same | same |

Consequence: "commit the skins to WhiteLabel" cannot be done by copying files from master, because master (at this snapshot) does not have them either. The skins exist today only as the Prompt 20 / 21 blueprints and the lab HTML files (`naara-dashboard-system-v9-surface-core13.html`, `naara-dashboard-system-v15-new22-skins-lab.html`). **Batch 0 decides the path** (see §5).

The three zips are Sept 22 snapshots and the owner has kept building since. The real `HEAD` of master may already contain the engine. Check `HEAD`, do not trust the snapshot.

### 2.2 How white-label licensing and entitlement actually work (this shapes the design)

| Fact | Where | Consequence |
|---|---|---|
| Master is the sole authority. A fork is a consumer + leaf puller, never a hub. | `docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md` §1, §3 | The skin allowance (tier to count) is master-only. |
| Tiers: `normal`, `extended` (`WhiteLabelInstance::TIERS`). Plans: `basic`, `medium`, `extended`, `extended_v2` (`WhiteLabelLicensePlan`). Extended and Extended V2 share one tier. Levels: `basic`, `standard`, `full`. | `app/Models/WhiteLabelInstance.php`, `WhiteLabelLicensePlan.php` | Key the allowance off **tier** (not plan, not level). |
| Fork activation: license key goes to `POST {master}/api/v1/white-label/activate`; master (`WhiteLabelLicenseService::activateWithKey`) returns a Sanctum token plus `entitlement: {level, locks[]}`. The token lives in `.env` as `NAARA_UPDATE_API_TOKEN` (never in the DB). The in-app screen is status-only. | `WhiteLabelLicenseController@activate`, boundary doc §4 decision A, `config/updater.php` | "Activation" in this design means the first successful entitlement response. Do not build a paste-key-in-UI flow (Decision D1). |
| Entitlement refresh: `GET /api/v1/white-label/entitlement` returns `FeatureLocks::entitlementFor($instance->entitlement_level)`; fork side `WhiteLabelUpdateClient::refreshEntitlement()` stores it via `FeatureEntitlements::store()`. It runs on every update check-in and from the admin screen `Admin/WhiteLabelUpdater::refreshEntitlement()`. | `WhiteLabelUpdateController@entitlement`, `WhiteLabelUpdateClient.php` | Extend this exact payload and this exact refresh path. Do not invent a second channel. |
| Resilience posture: a failed refresh never wipes or invents state; last-known value is kept. | `refreshEntitlement()` docblock | Same posture for the skin allowance. |
| **Extended is hard-empty on locks.** `FeatureEntitlements::all()` on the Extended fork always returns `[]` ("zero feature locks", blueprint §0.4). | `E/app/Support/FeatureEntitlements.php` | The 5-skin cap on Extended is a **quota**, not a feature lock. It must not touch `FeatureEntitlements::all()`. See Decision D2 for the owner-facing change this is. |
| Extended and normal forks run identical code; only the data master returns differs. | boundary doc §1 | Do not add `if tier === extended` anywhere in a fork. The fork reads one number from master. |
| Fork repos have CI fitness tests that fail if master-only classes reappear. | `tests/Feature/WhiteLabelBoundaryFitnessTest.php` | Add a test that the allowance authority class does not exist in a fork. |
| `Setting::getValue/setValue($key, $value, $group)` is the settings store. | `app/Models/Setting.php`, used by `FeatureEntitlements` | Store fork-side skin state here, group `white_label`. |

### 2.3 What Prompt 20 already gives you (reuse, do not rebuild)

- `appearance_presets` (`kind` skin|accent, `key`, `label`, `enabled`, `is_default`, `sort`) with the invariant "exactly one default per kind" and "cannot disable the current default".
- `AppearanceResolver::for(?User)`: lock on, then platform defaults; else the user's value **if the preset exists and is enabled**, else the platform default, silently, without deleting the user's row (so re-enabling restores their choice).
- `UpdateUserAppearance` action validating with `Rule::in(enabled keys)`, throttled 30/min, rejected when locked.
- Per-user cache keys `appearance:user:{id}`, forgotten on save.
- Admin Appearance page, `appearance.lock_users` setting, `Auditor::log('appearance.admin_updated', ...)`.

The license layer sits **on top of** this: it decides which skin keys are allowed to be `enabled` at all.

---

## 3. Open decisions (flagged, with the default to build)

Do not stop the work for these; build the default and list each as a clearly labelled assumption in the final report so the owner can flip it.

| # | Decision | Default to build | Why / what to flag |
|---|---|---|---|
| D1 | How is the license key entered? | Keep the existing `.env`-token flow. "Activated" = first successful entitlement response. The in-app skin picker appears once an allowance has been received. | A paste-key-in-UI screen would persist a bearer token in the DB, which `config/updater.php` and boundary decision A forbid. If the owner wants it, that is a separate decision that relaxes the `.env`-only rule. Flag it, do not build it. |
| D2 | Extended's own blueprint says "zero feature locks". A 5-skin cap is a limit. | Treat the cap as a **quota in a separate field** (`skins.allowance`), leave `FeatureEntitlements::all()` hard-empty, and amend the boundary doc to say Extended has zero feature locks **and** a skin quota. | This is a deliberate owner change, not a bug. Record it in the doc so nobody later "fixes" it. |
| D3 | What do end users see before activation? | The platform default skin only (1 skin). | The owner said skins unlock "upon activation". Fail closed to one skin, not to all 35. |
| D4 | Can the licensee change their selection later? | Yes, any time, audited. | Selection is their branding choice. If the owner wants a lock or cooldown, it must be a field sent by master, not fork logic. |
| D5 | Does Prompt 21 (Pro locks, trials, goals, mood presets, Naara Pro subscription) go to the forks? | **No.** In a fork every enabled skin is free to every end user. | Prompt 21 hangs off the brand-listing subscription, credits and journeys, which are master business features. |
| D6 | Accents, dials and mode in the forks | All remain available to end users, unchanged by the license. | Only **skins** are capped. Flag in case the owner wants accents capped too. |
| D7 | License suspended, revoked or master unreachable | Keep the last-known allowance and selection (matches current refresh behaviour). A revoked/suspended license (master returns 403/404 on entitlement) does not change skins in this batch; flag it. | Existing behaviour for locks is "never wipe on failure". Do not invent a harsher rule for skins alone. |
| D8 | Does the accessibility skin `clarity` count against the cap? | Yes (follow the owner's wording literally). | Flag it: hiding a high-contrast skin from a licensee's end users can be an accessibility problem. Recommend the owner consider exempting `clarity`. |

---

## 4. Architecture

### 4.1 Authority and consumer split

```
MASTER (authority, master-only)                         FORK (consumer, identical code in W and E)
--------------------------------                        ------------------------------------------
config/white_label_skins.php                            LicensedSkins (support class)
SkinAllowance (support class)                             - allowance(): int          (from stored setting)
  tier -> count (normal 2, extended 5)                    - selected(): list<string>  (licensee's choice)
  + admin override Setting                                - default(): string
  + per-instance override column                          - available(): list<string> (what the resolver uses)
WhiteLabelUpdateController@entitlement                    - validateSelection(), save()
WhiteLabelLicenseController@activate                    WhiteLabelUpdateClient::refreshEntitlement()
   -> payload gains  skins: { allowance: N }  --------->    -> stores allowance via LicensedSkins::storeAllowance()
                                                         AppearanceResolver  -> intersects with available()
                                                         UpdateUserAppearance -> Rule::in(available())
                                                         Admin "Your skins" screen (licensee picks)
```

### 4.2 Payload contract (additive, backward compatible)

Entitlement response and the `entitlement` block of the activate response become:

```json
{
  "level": "basic",
  "locks": ["gift_cards", "esim_voice", "preloader_customization", "brand_hunt"],
  "skins": { "allowance": 2 }
}
```

- `skins.allowance` is an integer, minimum 1. Master computes it from `SkinAllowance::forInstance($instance)`.
- A fork that receives **no `skins` key** (older master) changes nothing: it keeps its last-known allowance, or stays at 1 if it never had one. **Deploy master (Batch 3) before the forks check in.**
- A malformed `skins` value (not an int, below 1) is treated like a malformed lock list: ignored, last-known kept.

### 4.3 Fork-side state (Settings, group `white_label`)

| Key | Type | Meaning |
|---|---|---|
| `white_label.skins.allowance` | int or null | Last allowance received from master. Null means never activated. |
| `white_label.skins.selected` | json list of skin keys | The licensee's chosen skins, **order matters: first = default**. |

Derived rules, implemented only in `LicensedSkins` and nowhere else:

- `allowance()`: stored value, else **1** (not yet activated).
- `selected()`: stored list filtered to catalog keys, de-duplicated, trimmed to `allowance()`. If empty, `[platform default]` (the Prompt 20 default skin key, `surface`).
- `default()`: `selected()[0]`.
- `available()`: `selected()`. This is the **only** list the rest of the app may use.
- On the master, `LicensedSkins` is **inert**: `available()` returns every skin enabled in `appearance_presets`, guarded by `FeatureEntitlements::isMaster()`, exactly like the existing gate. Master users are never limited.

### 4.4 Hiding is enforced on the server, not with CSS

"Hidden" means the skin cannot be chosen, previewed, requested or resolved. Enforce in all of these:

1. `AppearanceResolver::for()`: a user's saved skin that is not in `available()` resolves to `default()`. The user's row is **not** deleted or rewritten (restores automatically if the licensee re-selects it).
2. `UpdateUserAppearance`: `Rule::in(LicensedSkins::available())`.
3. The user Appearance page lists only `available()`. Previews render only those.
4. The admin Appearance page (fork version) shows skin enable/disable as **read-only**, managed by the license screen. Accent presets keep their normal toggles.
5. `appearance_presets.enabled` for skins is **synchronised** from `selected()` on save and on every allowance change (so the existing Prompt 20 logic and `Rule::in(enabled keys)` agree), and `is_default` is synced to `default()`. The resolver still intersects with `available()` as defence in depth, so toggling a DB row by hand cannot unlock a skin.
6. No route, query string or Livewire property may name a skin that is not in `available()` (check any `?skin=` style preview parameter that Prompt 20 introduces).
7. Cache keys: add a `licensed_skins_version` value (bumped on every selection or allowance change) into the appearance cache key so a selection change invalidates every user's resolved appearance at once. Do not use a global key for resolved values.

### 4.5 Re-check and downgrade behaviour

- Allowance **rises** (for example a normal fork upgraded to Extended: 2 to 5): keep the existing selection; the admin screen shows "you can add 3 more". Nothing auto-selects.
- Allowance **falls** (selection longer than allowance): keep the first N in saved order, which always includes the default; write an `Auditor` entry and an admin notification; never delete user rows.
- Allowance known, selection never saved: end users see the platform default only; the admin dashboard shows a "Choose your skins" banner until a selection is saved.

---

## 5. Batches

### Batch 0: Audit and path decision (no feature code)

In **each** repo, against real `HEAD`:

1. `git log --oneline -n 30 -- app/Support/Appearance resources/css/nx-skins resources/views/components/nx config/appearance.php 'database/migrations/*appearance*'` and `git show HEAD:app/Support/Appearance/AppearanceResolver.php`.
2. `git status` and `git stash list` (report uncommitted or stashed skin work; do not touch it).
3. Record in `docs/audits/SKINS-WL-AUDIT.md`: engine present or absent per repo; migration list; whether `appearance_presets` / `user_appearance` exist; the exact layout files that emit `<html>` attributes in each repo; the exact file that renders the end-user Account/Appearance entry; whether a `Setting`-backed `appearance.lock_users` exists.
4. Confirm the license-flow facts in §2.2 against `HEAD` (VERIFY list): `WhiteLabelUpdateController@entitlement`, `WhiteLabelLicenseController@activate`, `WhiteLabelUpdateClient::refreshEntitlement`, `FeatureEntitlements`, `Admin/WhiteLabelUpdater`, and the `Admin/WhiteLabelRegistry` screen that shows an instance's tier.

**Path decision (write it at the top of the audit file):**

- **PATH A: master `HEAD` already contains the skin engine** (Prompt 20 Parts A to C built). Continue to Batch 1.
- **PATH B: master does not contain it.** Build it in master first by executing Prompt 20 as its own batches under its own rules, commit to master, then return here. If you cannot finish Prompt 20 in this session, stop at a clean batch boundary, update `PROGRESS.md` with the exact next step, and report. **Do not build a cut-down parallel skin system in a fork.** Do not start Batches 3 to 7 without a skin engine to gate.

### Batch 1: Port the skin engine to WhiteLabel (consumer parity)

Applies the shipping-discipline test: this is each install's own UI, with no cross-instance or billing content, so it **belongs in both forks**.

Port, merging by hand against the fork's own files (`git show HEAD:` each first):

- Migrations: `appearance_presets`, `user_appearance` (same timestamps as master so migration history matches; do not renumber).
- `config/appearance.php` (the 35-skin catalog with groups), `AppearancePresetSeeder`, registered in the same post-migrate step as master.
- `app/Support/Appearance/*` (`AppearanceResolver`, any token/contrast helpers), `ApplyAppearance` middleware, `UpdateUserAppearance` action and its policy.
- `resources/css/nx-skins/*`, `nx-skins.css`, the `nx/` Blade primitives, the Tailwind token wiring, the layout attribute emission (`data-nx-skin`, `data-nx-accent`, `.dark`), and the inline `mode=system` script.
- The end-user Appearance page, the Account entry to it, and the fork's admin Appearance page.
- **Do not port** anything from Prompt 21, and nothing that references another white-label instance, license issuance, or oversight.
- Respect the fork's own layout differences. Do not copy `layouts/app.blade.php` or `layouts/admin.blade.php` over; add only the skin attributes, the middleware share and the stylesheet import to the fork's own versions.
- Respect the L0 `ThemePreset` system: skins sit above it (Prompt 20 §13). On a non-default theme preset, accent `teal` means "inherit the theme brand colour".

Port to **WhiteLabel first**; verify (Batch 2); only then replicate to Extended (Batch 6).

### Batch 2: Test and CONFIRM the skins feature exists in WhiteLabel (gate)

This batch produces the evidence the owner asked for. Do not proceed to Batch 3 unless every item is green or explicitly reported as a failure.

1. **`php artisan skins:verify`**: a new read-only, production-safe command (build it in this batch, port it to Extended later). It prints a pass/fail table and exits non-zero on any failure:
   - `appearance_presets` and `user_appearance` tables exist and the migrations are recorded.
   - Catalog count equals the count in `config/appearance.php` (35 expected; report the real number).
   - Exactly one default skin and one default accent.
   - Every catalog skin key has a matching selector in the built CSS (read `public/build/manifest.json` and the CSS file; report any missing key by name).
   - `AppearanceResolver` and `ApplyAppearance` are registered; the layouts emit `data-nx-skin`.
   - The Appearance route returns 200 for a seeded test user and lists skins.
2. **PHPUnit** (feature tests; name them in the report): resolver defaults, disabled-preset fallback keeps the row, `UpdateUserAppearance` rejects unknown and disabled keys and respects the lock, per-user cache isolation, guests get defaults, impersonation cannot write, admin cannot disable the default skin.
3. **Browser checks**, mobile 390px and desktop 1280px, light and dark: Appearance page, dashboard and the eSIM/numbers screens in at least `surface`, `glass`, `neo` and one themed skin. Save screenshots under `docs/audits/skins-wl/`.
4. Run the **existing** WhiteLabel suite in full. Report any pre-existing failure separately from new ones; do not "fix" unrelated tests.
5. Write `docs/audits/SKINS-WL-PARITY-REPORT.md`: command outputs, test names with results, screenshot paths, and an **UNVERIFIED** section for anything you could not check. Commit: `feat(skins): port skin engine to WhiteLabel [skip ci]`.

### Batch 3: Master, the allowance authority

Master only. Put the verbatim guard comment from boundary doc §3 above the new class.

1. `config/white_label_skins.php`: `'allowance' => ['normal' => 2, 'extended' => 5]`, `'minimum' => 1`.
2. `App\Support\WhiteLabel\SkinAllowance` (master-only), mirroring how `FeatureLocks` resolves and caches:
   - `forTier(?string $tier): int` (tier defaults and an admin-saved override in a `white_label.skins.allowance_by_tier` Setting; unknown or null tier returns the minimum; values clamped to `[minimum, catalog size]`).
   - `forInstance(WhiteLabelInstance $i): int` (per-instance override wins, else `forTier($i->tier)`).
   - `bust()`.
3. Migration (master only): `white_label_instances.skin_allowance_override` unsigned tinyint nullable. `down()` drops only that column.
4. `WhiteLabelUpdateController@entitlement` and `WhiteLabelLicenseController@activate` return the §4.2 payload. Build it in **one** shared method so both callers cannot drift (same pattern as `FeatureLocks::entitlementFor`).
5. Master admin: on the existing `Admin/WhiteLabelRegistry` screen show each instance's effective allowance and let an admin set the per-instance override; on the existing level/lock admin area, let an admin edit the two tier defaults. Audit every change (`Auditor::log('white_label.skin_allowance_set', ...)`). **VERIFY** these screens' real file and method names before editing.
6. Tests: allowance for normal=2, extended=5, extended_v2=5 (same tier), override wins, unknown tier = minimum, activate and entitlement responses both contain `skins.allowance`, response shape unchanged for `level` and `locks`.

### Batch 4: Fork consumer enforcement (WhiteLabel)

1. `App\Support\Appearance\LicensedSkins` per §4.3, with `storeAllowance(int)`, `validateSelection(array $keys): array{ok,errors}`, `saveSelection(array $keys)` (default is the first key), and a `syncPresets()` that makes `appearance_presets` agree. Guard master as inert via `FeatureEntitlements::isMaster()`.
2. `WhiteLabelUpdateClient::refreshEntitlement()`: after the lock list is stored, read `skins.allowance`; if it is an int >= 1, call `LicensedSkins::storeAllowance()` (which trims an over-long selection per §4.5 and bumps `licensed_skins_version`). Never write on failure. Return the allowance in the result array so the admin screen can display it.
3. Wire `AppearanceResolver`, `UpdateUserAppearance`, the user Appearance page and the fork admin Appearance page per §4.4.
4. Tests: before activation only the platform default is available (1 skin); after a stored allowance of 2 and a saved selection of 2, `available()` is exactly those 2; a saved user skin outside the selection resolves to the default and the row is untouched; `UpdateUserAppearance` rejects a hidden skin; a forged `?skin=` or Livewire property naming a hidden skin is ignored; an allowance drop trims to N and keeps the default; a failed refresh leaves the stored allowance untouched; a payload without `skins` changes nothing; a malformed `skins` value is ignored.

### Batch 5: Fork admin, the licensee's "Your skins" screen

1. `App\Livewire\Admin\SkinSelection` plus view, reached from the existing White Label Updater admin area (**VERIFY** the real nav entry in `Admin/WhiteLabelUpdater` / admin layout). It exists only on forks; on master the route 404s via `FeatureEntitlements::isMaster()`. No admin URL in user markup.
2. Content: an allowance meter ("2 of 2 selected"); the full catalog as a gallery with group filter and search, each tile a scoped live preview (reuse the user Appearance gallery component so previews cannot inherit the active skin); tick to select, disabled once the allowance is reached; a "Default" radio on the selected tiles; a read-only line stating the allowance and that it comes from the license.
3. Save: a confirm sheet stating how many skins will be hidden from end users, then one transaction that validates, writes `white_label.skins.selected`, syncs presets, bumps `licensed_skins_version`, and logs `Auditor::log('skins.selection_updated', ...)`. Disabled button plus loading state while saving. Throttle 10/min.
4. States: not yet activated (explain that skins unlock after the license is activated, with a link to the existing status panel); activated, nothing selected (banner on the admin dashboard until saved); allowance raised ("you can add N more"); allowance lowered (explain the trim).
5. The existing `WhiteLabelUpdater` entitlement panel also displays the current skin allowance.
6. Tests: Livewire tests for select, default change, over-allowance rejection, hidden-skin confirmation, unauthorised user cannot reach it, and that saving updates what a normal end user sees on the Appearance page.

### Batch 6: Replicate to White Label Extended

1. Re-run the Batch 1 port against Extended, hand-merging against Extended's own files (`git show HEAD:` each first). Add `skins:verify` and the Batch 2 evidence, saved under `docs/audits/skins-wle/`.
2. Replicate Batches 4 and 5 **byte-for-byte** with WhiteLabel (these files are expected to be identical; report any diff).
3. **Extended-specific tests:** with a stored allowance of 5, `FeatureEntitlements::all()` is **still `[]`** and `locked('gift_cards')` is false, while `LicensedSkins::allowance()` is 5 and a 6th selection is rejected. This proves the quota did not become a feature lock (Decision D2).

### Batch 7: Boundary doc, fitness tests, final QA

1. Amend `docs/architecture/WHITE-LABEL-LICENSE-BOUNDARY.md` (in all three repos): add the skin allowance to the capability table (authority: master only; consumer: forks), record the §4.2 payload, and record Decision D2 (Extended has zero feature locks and a skin quota).
2. Extend `WhiteLabelBoundaryFitnessTest` in both forks: `App\Support\WhiteLabel\SkinAllowance` must **not** exist; `LicensedSkins` must exist; the fork source must not contain a tier-to-count map (grep `config/white_label_skins.php` absent).
3. Run the QA matrix (§6) end to end on both forks.
4. Final report to the owner (see §8). Update `PROGRESS.md` in all three repos.

---

## 6. QA matrix (run on WhiteLabel and Extended)

| # | Scenario | Expected |
|---|---|---|
| 1 | Fresh fork, never activated | End users see only the platform default skin; admin "Your skins" explains skins unlock after activation |
| 2 | Activate WhiteLabel (normal tier) | Allowance 2 received on first entitlement response; picker allows exactly 2 |
| 3 | Select default + 1 switch skin, save | The other 33 skins disappear from the user Appearance page and the fork admin Appearance page |
| 4 | End user opens Appearance | Sees exactly the 2 skins; switching between them works, persists, and is isolated per account |
| 5 | End user previously saved a now-hidden skin | Resolves to the licensee default; row untouched; re-selecting the skin restores it |
| 6 | Try a hidden skin by crafted request / Livewire payload | Rejected (422 or ignored); nothing persisted |
| 7 | Activate Extended | Allowance 5; selection of 5 allowed, 6th rejected |
| 8 | Extended locks | `FeatureEntitlements::all()` still `[]` |
| 9 | Normal fork upgraded to Extended on master | Next check-in raises allowance 2 to 5; existing 2 stay; "add 3 more" shown |
| 10 | Master lowers allowance below current selection | Fork trims to N keeping the default; audit entry and admin notice |
| 11 | Master unreachable | Last-known allowance and selection kept; nothing wiped, nothing unlocked |
| 12 | Old master (no `skins` key) | Fork keeps last-known / stays at 1 |
| 13 | Licensee sets `appearance.lock_users` | End users cannot switch (documented: the switch skin needs the lock off) |
| 14 | Dark/light, mobile/desktop | All selectable skins render correctly; contrast tests pass |
| 15 | Master platform itself | Unaffected: all enabled skins available, no allowance applied |

---

## 7. Files you may edit (and may not)

**May edit/create:** the Prompt 20 skin-engine files listed in Batch 1; `config/white_label_skins.php`, `App\Support\WhiteLabel\SkinAllowance`, the one master migration, `WhiteLabelUpdateController`, `WhiteLabelLicenseController`, `Admin/WhiteLabelRegistry` (master); `App\Support\Appearance\LicensedSkins`, `WhiteLabelUpdateClient`, `Admin/WhiteLabelUpdater` (view + method), `Admin/SkinSelection` + view, `skins:verify` (forks); the boundary doc and fitness tests; `PROGRESS.md`; `docs/audits/*`.

**May not touch:** `FeatureEntitlements::all()` on Extended (stays hard-empty), the update packaging/verification pipeline (`PackageVerifier`, `UpdateApplier`, `PackageBuilder`), payment, wallet, credits or provider code, any master-only class in a fork (issuer, registry, distributor, plan catalog), `.github/workflows/*` triggers.

---

## 8. Final report (what you hand back)

1. The Batch 0 path decision (A or B) with evidence.
2. For WhiteLabel and Extended: the `skins:verify` output, the full test run summary (new failures vs pre-existing), screenshot paths, and an UNVERIFIED list.
3. A plain statement: "WhiteLabel has the skins feature: yes/no, proven by <evidence>"; same for Extended.
4. The decisions D1 to D8 as built, each marked "default used" so the owner can flip any of them.
5. Deployment order for the owner: master first (Batch 3 payload), then each fork.
6. Honest scope note: enforcement is the same trust model as the existing `FeatureEntitlements` locks. The fork enforces the number locally; a licensee with source access could edit their own copy. This design makes honest use correct and automatic, it is not tamper-proof.

---

## 9. Out of scope

Prompt 21 access layer (Pro, trials, goals, mood presets), a paste-key-in-UI activation screen, per-skin pricing or purchase of extra skins, capping accents or dials, a reselection cooldown, and reporting each licensee's chosen skins back to master. Each can be added later without changing this design (the payload already has room: `skins.*`).
