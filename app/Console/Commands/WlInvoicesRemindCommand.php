<?php

namespace App\Console\Commands;

use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlInvoiceIssuer;
use App\Support\Notifications\Notify;
use Illuminate\Console\Command;

/** Reminds White Label buyers about an unpaid balance invoice (MASTER-ONLY): 7 days before it is due, on the due day, and once overdue. Each stage is sent once per invoice. */
class WlInvoicesRemindCommand extends Command
{
    protected $signature = 'wl-invoices:remind';

    protected $description = 'Send balance invoice reminders (7 days before, due day, overdue)';

    public function handle(WlInvoiceIssuer $issuer): int
    {
        $sent = 0;
        WlInvoice::query()->where('doc_type', WlInvoice::INVOICE)->where('status', 'issued')->where('balance_due', '>', 0)->whereNotNull('due_at')->with('user')->orderBy('id')->limit(500)->get()
            ->each(function (WlInvoice $inv) use ($issuer, &$sent) {
                $days = (int) now()->startOfDay()->diffInDays($inv->due_at->copy()->startOfDay(), false);
                $stage = $days < 0 ? 'overdue' : ($days === 0 ? 'due' : ($days <= 7 ? 'soon' : null));
                if ($stage === null || ! $inv->user || isset(((array) $inv->meta)['reminded'][$stage])) {
                    return;
                }
                $when = match ($stage) {
                    'overdue' => 'overdue since '.$inv->due_at->format('Y-m-d'),
                    'due' => 'due today',
                    default => 'due on '.$inv->due_at->format('Y-m-d'),
                };
                // Each stage is recorded in meta so it is sent once. The old public link is replaced because only its hash is stored.
                $url = route('wl-invoice.show', $issuer->newPublicToken($inv));
                Notify::event($inv->user, 'wl.invoice_reminder', ['number' => $inv->number, 'balance' => number_format((float) $inv->balance_due, 2), 'when' => $when, 'url' => $url], 'wl-remind-'.$inv->id.'-'.$stage);
                $meta = (array) $inv->meta;
                $meta['reminded'][$stage] = now()->toIso8601String();
                $inv->forceFill(['meta' => $meta])->save();
                $sent++;
            });
        $this->info("Checked balance invoices; {$sent} reminder(s) queued.");

        return self::SUCCESS;
    }
}
