<?php

namespace App\Http\Controllers;

use App\Models\WlInvoice;
use App\Services\WhiteLabel\Funnel\WlInvoiceIssuer;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Public invoice page by unguessable token, owner and admin downloads (MASTER-ONLY). Tokens are stored hashed; pages are noindex and rate-limited. */
class WlInvoiceController extends Controller
{
    public function show(string $token)
    {
        $inv = $this->byToken($token);

        return response()->view('wl-invoice.show', ['inv' => $inv, 'token' => $token])->header('X-Robots-Tag', 'noindex, nofollow')->header('Cache-Control', 'private, no-store');
    }

    public function pdfByToken(string $token, WlInvoiceIssuer $issuer): Response
    {
        return $this->pdf($this->byToken($token), $issuer);
    }

    /** The buyer's proforma for the payment they have started: issued once, then the same document every time. */
    public function proforma(Request $request, \App\Services\WhiteLabel\Funnel\WlFunnel $funnel, \App\Services\WhiteLabel\Funnel\WlFunnelManualPayments $payments, WlInvoiceIssuer $issuer): Response
    {
        abort_unless(\App\Services\WhiteLabel\Funnel\WlFunnel::enabled(), 404);
        $inst = $funnel->forUser($request->user()) ?? abort(404);
        $pay = $payments->current($inst) ?? abort(404);

        return $this->pdf($issuer->proformaForPayment($pay), $issuer);
    }

    /** The signed-in buyer (or an admin) downloads their own document. */
    public function pdfOwn(Request $request, int $id, WlInvoiceIssuer $issuer): Response
    {
        $inv = WlInvoice::query()->findOrFail($id);
        abort_unless((int) $inv->user_id === (int) $request->user()->id || $request->user()->hasAnyRole(['super_admin', 'admin']), 404);

        return $this->pdf($inv, $issuer);
    }

    /** Admin: a sample PDF built from the current settings and logos with made-up data (nothing is stored or numbered). */
    public function preview(Request $request, WlInvoiceIssuer $issuer): Response
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'admin']), 404);
        $seller = \App\Services\WhiteLabel\Funnel\WlInvoiceSettings::seller();
        $seller['logos'] = array_values(array_filter([\App\Services\WhiteLabel\Funnel\WlInvoiceLogos::dataUri('family') ?? \App\Services\WhiteLabel\Funnel\WlInvoiceLogos::dataUri('product'), \App\Services\WhiteLabel\Funnel\WlInvoiceLogos::dataUri('agency')]));
        $inv = new WlInvoice(['doc_type' => 'invoice', 'number' => \App\Services\WhiteLabel\Funnel\WlInvoiceNumbering::render(\App\Services\WhiteLabel\Funnel\WlInvoiceSettings::format('INV'), (int) now()->format('Y'), 0), 'status' => 'issued', 'issued_at' => now(), 'due_at' => now()->addDays(14), 'seller_snapshot' => $seller,
            'buyer_snapshot' => ['name' => 'Sample Buyer Ltd', 'address' => "1 Example Street\nLagos", 'country' => 'NG', 'tax_id' => '', 'email' => 'buyer@example.com', 'brand' => 'Sample Brand', 'funnel_code' => 'BS-SAMPLE'],
            'lines' => [['description' => 'Sample licence (NOT A REAL DOCUMENT)', 'qty' => 1, 'unit_price' => 1000, 'amount' => 1000]], 'subtotal' => 1000, 'tax_total' => 0, 'total' => 1000, 'amount_paid' => 0, 'balance_due' => 1000, 'payments' => [], 'terms' => $seller['terms'], 'notes' => $seller['notes']]);

        return $this->pdf($inv, $issuer);
    }

    private function byToken(string $token): WlInvoice
    {
        abort_unless(preg_match('/^[A-Za-z0-9]{48}$/', $token) === 1, 404);

        return WlInvoice::query()->where('public_token_hash', WlInvoice::hashToken($token))->firstOrFail();
    }

    private function pdf(WlInvoice $inv, WlInvoiceIssuer $issuer): Response
    {
        return response($issuer->pdf($inv), 200, ['Content-Type' => 'application/pdf', 'Content-Disposition' => 'inline; filename="'.preg_replace('/[^A-Za-z0-9._-]/', '_', $inv->number).'.pdf"', 'X-Robots-Tag' => 'noindex', 'Cache-Control' => 'private, no-store']);
    }
}
