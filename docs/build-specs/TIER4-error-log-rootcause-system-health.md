# NaaraSim — Error Log Root-Cause Fixes + Advanced Real-Time System Health Hero
### Grounded in three days of real production error logs, cross-referenced against the actual codebase. Phase A fixes what's actually broken; Phase B builds the sophisticated live monitoring experience requested. Master repo. For Claude Sonnet + Graphify.

---

## Phase A — Fix the three confirmed root causes

### A1. Unconfigured providers are attempted anyway, every cycle, forever
**Confirmed**: `GigsService`, `AiraloService`, `QuibityService`,
`ZenditService`, `OneGlobalService`, and `MontyMobileService` all read
their credentials straight from `config('services.<provider>.*')` and fire
their HTTP call with zero check for whether those values are actually
present. The live `.env` has these six left blank (real credentials never
entered, presumably because these providers aren't actually onboarded
yet). The scheduled `esim:sync` job attempts every one of them anyway,
every single run, guaranteeing a permanent, predictable failure that
produces zero diagnostic value and — per the earlier oversight-hardening
work in this engagement — likely also spams whatever admin-alert channel
already exists on top of it.

**Fix**:
1. Add a real `isConfigured(): bool` check to each provider service
   (or a shared trait/base class, since this exact check — "are my
   required config keys non-empty" — is identical logic across all
   providers and shouldn't be duplicated six times).
2. Have the sync job (`SyncEsimCatalogueJob` and whatever scheduled
   command drives it) skip any provider that isn't configured, logging it
   once at `info`/`debug` level ("provider X skipped: not configured") —
   not as an `error`-severity log entry, since an intentionally-unset
   provider is not a system failure and shouldn't be indistinguishable
   from one in the error log or any alerting this feeds.
3. This same `isConfigured()` check is also exactly what Phase B's health
   UI needs to correctly show "not yet set up" as a distinct, neutral
   state rather than red — build it once, use it in both places.
4. Separately: confirm with the business which of these six providers are
   actually intended to go live soon versus which were added to
   `config/services.php` speculatively and don't need real credentials
   yet — that's a product decision, not something to guess at in code.

### A2. "The MAC is invalid" — tenant/session isolation, likely a continuation of the earlier finding
**Confirmed**: this error means Laravel attempted to decrypt a
cookie/session value that was encrypted under a different `APP_KEY` than
the one currently configured. It's occurring on both `rehav.online`
(master) and `farm.rehav.online` (the White Label subdomain), including
specifically on `white-label-registry.blade.php` — the exact file
identified earlier in this engagement as wrongly duplicated onto White
Label. This is very likely connected to the tenant-isolation issue already
flagged as urgent: either the two apps still don't have fully separated
`APP_KEY` values, or an `APP_KEY` was rotated (possibly as part of already
attempting that earlier fix) without invalidating the sessions that were
encrypted under the old key.

**Fix**:
1. **Re-verify, don't re-assume**: directly confirm on the live servers,
   today, whether `rehav.online` and `farm.rehav.online` currently have
   distinct `APP_KEY` values — if the earlier urgent blueprint's
   remediation was only partially applied, this is the proof it needs
   finishing.
2. If an `APP_KEY` was changed recently on either app, **proactively
   invalidate existing sessions** for that app (truncate the sessions
   table if using the database driver, clear the session storage path if
   using file, or flush the relevant keys if using Redis) — a rotated key
   with no session invalidation guarantees every existing visitor hits
   this exact error until their old cookie naturally expires.
3. **Add resilience regardless of root cause**: wrap session/cookie
   decryption failures so a `DecryptException`/MAC-invalid failure clears
   only the malformed cookie and starts a fresh session for that one
   visitor, rather than surfacing a hard error page. This doesn't fix the
   underlying isolation problem — A2 steps 1–2 do that — but it stops a
   user from being shown a broken page while the underlying cause is
   confirmed and resolved, and it's good defensive practice independent of
   this specific incident.
4. Verification: confirm zero new MAC-invalid errors occur over a
   sustained test period on both domains, in genuinely separate browser
   sessions, after steps 1–2 are applied.

### A3. Livewire `$search` error — a stale deployment, not a code bug
**Confirmed**: the current `Catalogue.php` already declares `public
string $search = ''` and uses it correctly. This error can only occur if
the version of the code actually running in production predates this
property being added. **There is nothing to fix in the source for this
one** — the fix is confirming the live server is actually running the
current build, and treating this as a live signal that a prior deploy
didn't fully land or was partially rolled back.

**Action**:
1. Confirm the live deployment's actual running version/commit against
   what's in the current codebase — if they don't match, redeploy the
   current build properly.
2. This is a direct, concrete argument for why the Updater/changelog work
   elsewhere in this engagement matters: a system that clearly tracks
   "what version is actually live, right now" would have made this
   instantly diagnosable instead of requiring a source-code comparison to
   figure out. Note this connection explicitly in the final report rather
   than treating A3 as an isolated one-off.

---

## Phase B — Advanced, real-time System Health hero

### The actual ask, restated precisely
Not a bigger status page — a monitoring experience that shows **live,
ongoing proof** that background work is happening, with named jobs,
real timestamps, and honest three-state health (working / not configured
/ actually broken) instead of a binary red-or-green that can't
distinguish "this hasn't been set up yet" from "this is failing."

### B1. A real heartbeat table — the foundation everything else is built on
Today, only the NCI recompute job has an explicit "last ran at" check
(`26 * 3600` second overdue window). Every other scheduled job's health is
either invisible or inferred indirectly. Fix this at the root:
1. Add a `job_heartbeats` table: `job_name`, `started_at`, `finished_at`,
   `duration_ms`, `outcome` (`success`/`failed`/`skipped`), `detail` (a
   short human-readable note — "3 providers synced, 6 skipped (not
   configured)", not a raw stack trace).
2. Add a small trait or base-command behavior that every scheduled
   command in `routes/console.php` writes to on start and completion —
   retrofitted onto every existing scheduled job (`providers:health-check`,
   `esim:sync`, `esim:sync-usage`, `numbers:catalogue-sync`, the NCI
   recompute, the backup job, and everything else already scheduled), not
   just new ones going forward.
3. This table is the single source of truth both the admin UI (B2) and
   any future automated alerting reads from — don't build a second,
   separate "is this job healthy" mechanism alongside it.

### B2. The hero UI itself
1. Replace the current static status indicators with a **live-updating
   feed** (Livewire polling every few seconds is sufficient — this
   codebase has no real-time broadcasting infrastructure yet, and this
   doesn't need it badly enough to justify building that just for this)
   showing the most recent `job_heartbeats` entries as they land — a
   genuinely scrolling, timestamped list of real executions, not a
   snapshot.
2. Per scheduled job, a card showing: what it does in plain language (not
   just its command name), when it last ran, how long it took, and its
   outcome — turning the page into a narrated map of what's actually
   protecting the platform, not just a light board.
3. **Three states, not two**: green (configured and healthy), amber/grey
   (not yet configured — using A1's `isConfigured()` check, so an
   intentionally-unset provider never shows as an alarming red), and red
   (configured but genuinely failing). This directly fixes the
   "everything running fine but the page screams red" complaint — once a
   provider correctly reports "not configured" instead of erroring, and
   once the overall scheduler-health check is reading real heartbeat data
   instead of a single hardcoded 26-hour window meant for one specific
   job, the page's verdict will actually match reality.
4. Audit every existing "overdue" calculation on the current
   `SystemHealth.php` (not just the NCI one already found) against this
   new heartbeat data, specifically looking for any indicator whose
   threshold or logic doesn't match how often that job is actually
   scheduled to run — the NCI check's `26 * 3600` window is correct for a
   daily job; if any other indicator on the page reuses that same
   constant for a job that runs hourly, that mismatch alone could produce
   exactly the false "needs immediate attention" the report describes.
5. A "proof" element per card — the actual `detail` text from the most
   recent heartbeat (e.g., "47 eSIM plans synced, 6 providers skipped —
   not configured" or "Horizon: 12 jobs processed in the last minute") —
   so an admin isn't just told "healthy," they can see the literal recent
   work product as evidence.

### B3. Verification
1. Confirm every currently-scheduled command now writes a heartbeat on
   run.
2. Confirm the six unconfigured providers now show as the neutral
   "not configured" state, not red, and produce zero new `error`-severity
   log entries on the next scheduled sync.
3. Confirm the hero UI's live feed genuinely updates within a few seconds
   of a real job completing (trigger one manually and watch it appear),
   and confirm at least one previously-mismatched overdue threshold (if
   found in step B2.4) now correctly reflects that job's real schedule.

---

## Constraints

- A1's fix must not silently hide a provider that *should* be configured
  but was accidentally left blank — "not configured" is a legitimate,
  visible state on the health page (per B2.3), not a way to suppress the
  problem from view entirely.
- A2 is a re-verification and completion of already-issued urgent work,
  not a new investigation from scratch — check what was actually applied
  from the earlier tenant-isolation blueprint before assuming nothing was
  done.
- B1's heartbeat table is the single source of truth for every job's
  health going forward — no job-specific health check should be computed
  a second, different way once this lands.

## Deliverable

For Phase A: confirmation each of the three root causes is resolved,
specifically including a clean scheduled-job run producing zero
`error`-severity entries for the six unconfigured providers. For Phase B:
a screenshot of the upgraded System Health hero showing the live feed,
the three-state health cards, and at least one real heartbeat's detail
text as visible proof-of-execution — plus an explicit list of every
overdue-threshold mismatch found and corrected during B2.4's audit.
