<?php

namespace App\Http\Controllers\Api\V1\Business;

use App\Models\WebhookSubscription;
use App\Rules\PublicUrl;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/** `webhook:subscribe`: register, list and remove the webhooks of THIS key. The signing secret is returned once, on creation. */
class WebhooksController extends BusinessApiController
{
    public const MAX_PER_CLIENT = 10;

    public function index(Request $request): JsonResponse
    {
        return response()->json(['data' => WebhookSubscription::where('api_client_id', $request->user()->id)->orderBy('id')->get()->map(fn ($s) => $this->shape($s))->values()]);
    }

    public function store(Request $request): JsonResponse
    {
        $data = $request->validate([
            'event_type' => ['required', Rule::in(WebhookSubscription::EVENTS)],
            'target_url' => ['required', 'string', 'max:500', 'url:https', new PublicUrl],
        ]);
        abort_if(WebhookSubscription::where('api_client_id', $request->user()->id)->count() >= self::MAX_PER_CLIENT, 422, 'This key already has the maximum number of webhooks.');

        $secret = 'whsec_'.Str::random(40);
        $sub = WebhookSubscription::create($data + [
            'api_client_id' => $request->user()->id, 'wallet_group_id' => $this->workspace($request)->id, 'secret' => $secret, 'status' => WebhookSubscription::ACTIVE,
        ]);

        return response()->json(['data' => $this->shape($sub) + ['secret' => $secret]], 201);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        WebhookSubscription::where('api_client_id', $request->user()->id)->whereKey($id)->firstOrFail()->delete();

        return response()->json(['data' => ['deleted' => true]]);
    }

    /** @return array<string,mixed> */
    private function shape(WebhookSubscription $s): array
    {
        return ['id' => $s->id, 'event_type' => $s->event_type, 'target_url' => $s->target_url, 'status' => $s->status,
            'last_status_code' => $s->last_status_code, 'last_delivery_at' => optional($s->last_delivery_at)->toIso8601String(), 'consecutive_failures' => $s->consecutive_failures];
    }
}
