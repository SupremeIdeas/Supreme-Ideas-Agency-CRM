<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * Business Suite Prompt 14 §1.2/§2 — one step in a CallFlow. `config` is a
 * fixed shape per `type` (see the constants below and
 * App\Services\Voice\CallFlowResolver); nodes reference each other by id
 * inside `config` (menu digits / invalid_retry_node_id / overflow_node_id /
 * closed_node_id), never by a parent column.
 *
 *  menu:      {prompt_text, digits:{"1":node_id,...}, invalid_retry_node_id,
 *              timeout_seconds, business_hours_schedule_id?, closed_node_id?}
 *  queue:     {ring_group_id, hold_music_url, max_wait_seconds, overflow_node_id}
 *  forward:   {target_number, fallback_number}
 *  voicemail: {greeting_url, transcribe}
 *  ai_agent:  {overflow_node_id}  (the agent's own settings, and the ring group it hands over to, are per workspace: AiVoiceAgentConfig)
 */
class CallFlowNode extends Model
{
    public const MENU = 'menu';

    public const QUEUE = 'queue';

    public const FORWARD = 'forward';

    public const VOICEMAIL = 'voicemail';

    public const AI_AGENT = 'ai_agent';

    public const TYPES = [self::MENU, self::QUEUE, self::FORWARD, self::VOICEMAIL, self::AI_AGENT];

    protected $fillable = ['call_flow_id', 'type', 'label', 'config'];

    protected function casts(): array
    {
        return ['config' => 'array'];
    }

    protected static function booted(): void
    {
        static::saving(function (CallFlowNode $node) {
            if (! in_array($node->type, self::TYPES, true)) {
                throw new \InvalidArgumentException("Unknown call-flow node type [{$node->type}].");
            }
        });
    }

    public function callFlow(): BelongsTo
    {
        return $this->belongsTo(CallFlow::class);
    }

    /** Read one config key with a default. */
    public function cfg(string $key, mixed $default = null): mixed
    {
        return ((array) $this->config)[$key] ?? $default;
    }

    /**
     * The node ids this node can hand off to — a `menu` through its digit
     * map, retry and closed-hours branch; a `queue` through its overflow.
     * Used to keep the builder from saving a reference to a node that
     * belongs to some other flow.
     *
     * @return array<int,int>
     */
    public function childNodeIds(): array
    {
        $cfg = (array) $this->config;
        $ids = [];

        foreach ((array) ($cfg['digits'] ?? []) as $id) {
            $ids[] = (int) $id;
        }
        foreach (['invalid_retry_node_id', 'closed_node_id', 'overflow_node_id'] as $key) {
            if (! empty($cfg[$key])) {
                $ids[] = (int) $cfg[$key];
            }
        }

        return array_values(array_unique(array_filter($ids)));
    }
}
