<?php

namespace App\Support\Reviews;

/** MASTER-ONLY review moments: Naara Brain and the NaaraBusiness Suite. Never present in a white-label fork (the class does not exist there, and ReviewMoments guards on that). */
final class MasterReviewMoments
{
    public const MOMENTS = [
        'brain_first_answer' => ['label' => 'Naara Brain', 'title' => 'How is Naara Brain working for you?', 'sub' => 'You got your first answer. Tell us how it went.', 'delay' => 10, 'first_only' => true],
        'business_suite_use' => ['label' => 'Business Suite', 'title' => 'How is the Business Suite helping?', 'sub' => 'Your assistant just helped with a conversation. Tell us how it is going.', 'delay' => 60, 'first_only' => true],
    ];

    public static function register(): void
    {
        if (class_exists(\App\Models\Brain\BrainMessage::class)) {
            \App\Models\Brain\BrainMessage::created(function ($m) {
                if ($m->role === 'assistant' && $m->status === 'done' && $m->user_id) {
                    ReviewMoments::qualify((int) $m->user_id, 'brain_first_answer', $m->id);
                }
            });
        }
        if (class_exists(\App\Models\BusinessBrainRun::class)) {
            \App\Models\BusinessBrainRun::saved(function ($r) {
                if ($r->status === 'done' && $r->user_id && $r->wasChanged('status')) {
                    ReviewMoments::qualify((int) $r->user_id, 'business_suite_use', $r->id);
                }
            });
        }
    }
}
