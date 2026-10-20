@props(['hosting' => 'auto'])
@php
    use App\Support\Installer;
    // 'auto' detects the profile from the live queue driver chosen at install.
    $isVps = $hosting === 'vps' || ($hosting === 'auto' && Installer::isVps());
    $cronLine = Installer::cronLine();
    $workerCmd = Installer::queueWorkerCommand();
@endphp

{{-- Cron / scheduler setup (blueprint Section 20). One cron entry drives the
     whole platform: the scheduler AND — on shared/cPanel — the queue drain.
     Shown on the install-done screen and in Admin → Maintenance so the operator
     can copy the exact command for their server at any time. --}}
<div class="rounded-2xl border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface))] p-5 text-left  ">
    <h3 class="flex items-center gap-2 text-base font-semibold text-[rgb(var(--nx-text))] ">
        <x-icon name="refresh" class="h-5 w-5 text-[rgb(var(--nx-teal-ink))]" /> Cron &amp; scheduler — one-time setup
    </h3>
    <p class="mt-1 text-sm text-[rgb(var(--nx-text-2))] ">
        NaaraSim needs <span class="font-medium">one</span> cron entry, running every minute. It powers automatic
        catalogue &amp; price sync, provider health checks, nightly backups{{ $isVps ? '' : ', and draining the payment/order queue' }}.
        Without it, those background jobs don’t run.
    </p>

    {{-- The command block + copy button --}}
    <div x-data="{ copied: false, cmd: @js($cronLine) }" class="mt-4">
        <label class="mb-1 block text-xs font-semibold uppercase tracking-wide text-[rgb(var(--nx-text-2))]">Add this cron job</label>
        <div class="flex items-stretch gap-2">
            <code class="flex-1 overflow-x-auto rounded-lg border border-[rgb(var(--nx-line))] bg-[rgb(var(--nx-surface-2))] px-3 py-2.5 font-mono text-xs text-[rgb(var(--nx-text))]   ">{{ $cronLine }}</code>
            <button type="button"
                    @click="navigator.clipboard.writeText(cmd); copied = true; setTimeout(() => copied = false, 1800)"
                    class="shrink-0 inline-flex items-center gap-1.5 rounded-lg bg-[rgb(var(--nx-cta-b))] px-3 py-2 text-xs font-semibold text-white hover:bg-[rgb(var(--nx-cta-a))]">
                <x-icon name="copy" class="h-3.5 w-3.5" x-show="!copied" />
                <x-icon name="check" class="h-3.5 w-3.5" x-show="copied" x-cloak />
                <span x-text="copied ? 'Copied' : 'Copy'"></span>
            </button>
        </div>
    </div>

    {{-- Per-host instructions --}}
    <div class="mt-4 space-y-3 text-sm text-[rgb(var(--nx-text-2))] ">
        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 ">
            <p class="mb-1 flex items-center gap-1.5 font-semibold text-[rgb(var(--nx-text))] ">
                <x-icon name="settings" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> cPanel / shared hosting
            </p>
            <ol class="ml-4 list-decimal space-y-0.5 text-xs text-[rgb(var(--nx-text-2))] ">
                <li>Open <span class="font-medium">cPanel → Advanced → Cron Jobs</span>.</li>
                <li>Under “Common Settings” pick <span class="font-mono">Once Per Minute (* * * * *)</span>.</li>
                <li>Paste the command part into the Command box:</li>
            </ol>
            <code class="mt-2 block overflow-x-auto rounded-lg bg-[rgb(var(--nx-surface-2))] px-3 py-2 font-mono text-[11px] text-[rgb(var(--nx-text-2))]  ">{{ \App\Support\Installer::cronCommandOnly() }}</code>
            <p class="mt-1.5 text-[11px] text-[rgb(var(--nx-text-2))]">If your host offers multiple PHP versions, replace <span class="font-mono">php</span> with the full path shown in cPanel (e.g. <span class="font-mono">/usr/local/bin/ea-php82</span>). No Redis needed — the queue drains through this same cron.</p>
        </div>

        <div class="rounded-xl border border-[rgb(var(--nx-line))] p-3 ">
            <p class="mb-1 flex items-center gap-1.5 font-semibold text-[rgb(var(--nx-text))] ">
                <x-icon name="package" class="h-4 w-4 text-[rgb(var(--nx-teal-ink))]" /> VPS / dedicated server
            </p>
            <ol class="ml-4 list-decimal space-y-0.5 text-xs text-[rgb(var(--nx-text-2))] ">
                <li>Run <span class="font-mono">crontab -e</span> and add the cron line above.</li>
                <li>Run the queue worker as a daemon (Redis is used for queue/cache/session):</li>
            </ol>
            <code class="mt-2 block overflow-x-auto rounded-lg bg-[rgb(var(--nx-surface-2))] px-3 py-2 font-mono text-[11px] text-[rgb(var(--nx-text-2))]  ">{{ $workerCmd }}</code>
            <p class="mt-1.5 text-[11px] text-[rgb(var(--nx-text-2))]">Keep Horizon alive with <span class="font-mono">supervisor</span> or a <span class="font-mono">systemd</span> service so it restarts on boot/crash. Full steps are in <span class="font-mono">docs/INSTALLATION.md</span>.</p>
        </div>
    </div>
</div>
