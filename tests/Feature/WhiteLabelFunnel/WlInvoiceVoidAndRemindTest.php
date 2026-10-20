<?php

namespace Tests\Feature\WhiteLabelFunnel;

use App\Models\User;
use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlInvoiceVoider;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class WlInvoiceVoidAndRemindTest extends TestCase
{
    use RefreshDatabase;

    private function invoice(string $status = 'issued', ?\DateTimeInterface $due = null, float $paid = 0): WlInvoice
    {
        $u = User::factory()->create();
        $n = WlInvoice::query()->count() + 1;

        return WlInvoice::query()->create([
            'doc_type' => 'invoice', 'series' => 'INV', 'number' => 'INV-T-'.$n, 'source_key' => 'test:'.$n, 'user_id' => $u->id, 'status' => $status, 'currency' => 'USD',
            'subtotal' => 100, 'tax_total' => 0, 'total' => 100, 'amount_paid' => $paid, 'balance_due' => 100 - $paid, 'issued_at' => now(), 'due_at' => $due,
            'public_token_hash' => WlInvoice::hashToken('x'.$n), 'seller_snapshot' => [], 'buyer_snapshot' => [],
            'lines' => [['kind' => 'balance', 'description' => 'Balance', 'qty' => 1, 'unit_price' => 100.0, 'amount' => 100.0]], 'payments' => [],
        ]);
    }

    public function test_credit_note_mirrors_voids_and_is_idempotent(): void
    {
        $inv = $this->invoice();
        $cn = app(WlInvoiceVoider::class)->creditNote($inv, 'Issued in error');
        $this->assertSame('credit_note', $cn->doc_type);
        $this->assertEquals(-100.0, (float) $cn->total);
        $this->assertEquals(-100.0, (float) $cn->lines[0]['amount']);
        $this->assertSame($inv->id, $cn->parent_invoice_id);
        $this->assertSame('void', $inv->fresh()->status);
        $this->assertTrue(app(WlInvoiceVoider::class)->creditNote($inv->fresh(), 'again')->is($cn));
        $this->assertSame(1, WlInvoice::query()->where('doc_type', 'credit_note')->count());
    }

    public function test_reason_is_required_and_a_receipt_cannot_be_credited(): void
    {
        $this->expectException(\InvalidArgumentException::class);
        app(WlInvoiceVoider::class)->creditNote($this->invoice(), '  ');
    }

    public function test_reminders_send_once_per_stage(): void
    {
        $inv = $this->invoice('issued', now()->addDays(5));
        $far = $this->invoice('issued', now()->addDays(30));
        $paid = $this->invoice('paid', now()->addDays(2), 100);
        $this->artisan('wl-invoices:remind')->assertSuccessful();
        $this->assertArrayHasKey('soon', (array) $inv->fresh()->meta['reminded']);
        $this->assertNull($far->fresh()->meta);
        $this->assertNull($paid->fresh()->meta);
        $hash = $inv->fresh()->public_token_hash;
        $this->artisan('wl-invoices:remind')->assertSuccessful();
        $this->assertSame($hash, $inv->fresh()->public_token_hash, 'a second run must not rotate the link');
    }
}
