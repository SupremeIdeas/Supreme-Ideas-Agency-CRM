<?php

namespace App\Http\Controllers\N2N;

use App\Http\Controllers\Controller;
use App\Services\N2N\Messaging\MessagingException;
use App\Services\N2N\Messaging\Planner;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/** Calendar export of a Naara Planner event (B13, MASTER-ONLY): members of the chat only; anyone else gets the same 404 as a missing event. */
class EventController extends Controller
{
    public function ics(Request $request, Planner $planner, int $eventId): Response
    {
        try {
            $body = $planner->ics($request->user(), $eventId);
        } catch (MessagingException) {
            abort(404);
        }

        return response($body, 200, [
            'Content-Type' => 'text/calendar; charset=utf-8', 'Content-Disposition' => 'attachment; filename="naara-event-'.$eventId.'.ics"',
            'Cache-Control' => 'private, no-store', 'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
