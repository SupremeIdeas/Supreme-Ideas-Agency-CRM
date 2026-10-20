<?php

namespace App\Http\Controllers;

use App\Models\NotificationTone;
use Symfony\Component\HttpFoundation\Response;

/** Serves a library tone from OUR origin (the CSP allows media/connect from 'self'; the object store is another origin). */
class ToneController extends Controller
{
    public function __invoke(NotificationTone $tone): Response
    {
        $bytes = $tone->contents();
        abort_if($bytes === null, 404);

        return response($bytes, 200, [
            'Content-Type' => $tone->mime,
            'Content-Length' => (string) strlen($bytes),
            'Cache-Control' => 'private, max-age=86400',
            'ETag' => '"'.md5($tone->id.$tone->file_path).'"',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }
}
