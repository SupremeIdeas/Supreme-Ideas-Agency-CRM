<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Support\MediaStorage;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;

/**
 * Inline image upload for the Email Broadcast rich-text composer (content/
 * billing/hot-menu blueprint Phase C). Trix uploads a dropped/pasted image via
 * a plain fetch, outside Livewire's wire model, so this is a small standalone
 * endpoint: store the file the same way every other admin media upload does
 * (Wasabi when configured, else local public disk) and hand back its URL for
 * Trix to embed.
 */
class EmailInlineImageController extends Controller
{
    public function store(Request $request): JsonResponse
    {
        abort_unless(Auth::user()?->hasAnyRole(['super_admin', 'admin']), 403);

        $request->validate(['image' => MediaStorage::uploadRules()]);

        return response()->json([
            'url' => MediaStorage::storePublic($request->file('image'), 'email-broadcast'),
        ]);
    }
}
