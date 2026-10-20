<?php

namespace App\Services\N2N;

use App\Jobs\N2N\LinkContactsJob;
use App\Models\Contact;
use App\Services\N2N\Identity\PhoneNormalizer;

/** Keeps contacts.e164 in step with the number a user typed, and re-links when it changes (N2N, MASTER-ONLY). */
class ContactObserver
{
    public function saving(Contact $contact): void
    {
        if ($contact->isDirty('phone_number') || $contact->e164 === null && $contact->phone_number !== null) {
            $region = $contact->user?->country_code;
            $contact->e164 = PhoneNormalizer::normalise((string) $contact->phone_number, $region)['e164'] ?? null;
            if ($contact->isDirty('phone_number')) {
                $contact->linked_user_id = null;
                $contact->linked_at = null;
            }
        }
    }

    public function saved(Contact $contact): void
    {
        if ($contact->wasChanged('e164') && $contact->e164 !== null) {
            LinkContactsJob::dispatch($contact->user_id);
        }
    }
}
