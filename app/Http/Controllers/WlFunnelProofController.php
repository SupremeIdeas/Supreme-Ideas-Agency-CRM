<?php

namespace App\Http\Controllers;

use App\Models\WlFunnelManualPayment;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\Response;

/** Streams one payment proof file to an admin (MASTER-ONLY). Never public; sandboxed so a hostile PDF or image cannot run anything. */
class WlFunnelProofController extends Controller
{
    public function show(Request $request, int $payment, int $i): Response
    {
        abort_unless($request->user()?->hasAnyRole(['super_admin', 'admin']), 404);
        $p = WlFunnelManualPayment::query()->findOrFail($payment);
        $f = ((array) $p->proof)[$i] ?? null;
        abort_if($f === null || ! Storage::disk($f['disk'])->exists($f['key']), 404);

        return Storage::disk($f['disk'])->response($f['key'], null, ['Content-Type' => $f['mime'], 'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff', 'Content-Security-Policy' => "default-src 'none'; img-src 'self' data:; sandbox"], 'inline');
    }
}
