<?php

namespace App\Http\Controllers\MerchantWidget;

use App\Http\Controllers\Controller;
use App\Models\MerchantWidgetKey;
use App\Services\MerchantWidgets\MerchantWidgetPluginPackager;
use App\Support\MerchantWidgetSettings;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

/** Signed-in merchants download the WordPress loader plugin, optionally pre-filled with one of THEIR OWN public keys (MASTER-ONLY). */
class PluginDownloadController extends Controller
{
    public function __invoke(Request $request, MerchantWidgetPluginPackager $packager): BinaryFileResponse
    {
        $merchant = $request->user()?->merchantAccount;
        abort_unless(MerchantWidgetSettings::enabled() && $merchant && $merchant->isActive(), 404);

        abort_unless(app(\App\Services\MerchantWidgets\MerchantWidgetAccessService::class)->hasAccess($merchant), 403, 'Unlock Widgets to download the plugin.');

        $public = null;
        if ($request->filled('key')) {
            $public = MerchantWidgetKey::query()->where('merchant_id', $merchant->id)->where('status', MerchantWidgetKey::ACTIVE)
                ->whereKey((int) $request->query('key'))->value('public_key');
            abort_if($public === null, 404);
        }

        return response()->download($packager->build($public), 'naara-widgets-'.MerchantWidgetPluginPackager::version().'.zip', ['Content-Type' => 'application/zip'])->deleteFileAfterSend(true);
    }
}
