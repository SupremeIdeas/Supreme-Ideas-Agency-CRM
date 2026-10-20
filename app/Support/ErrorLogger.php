<?php

namespace App\Support;

use App\Models\ErrorLog;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpExceptionInterface;
use Throwable;

/**
 * Durable, exportable error capture (blueprint Section 17.5). Writes server
 * errors to the error_logs table (which the admin ErrorLog module lists +
 * exports). Sentry captures the same exceptions independently when a DSN is
 * configured. Expected 4xx HTTP/validation/auth exceptions are skipped as
 * noise — but NOT every `HttpExceptionInterface`: a controlled `abort(500,
 * ...)` deep in application code (e.g. a license decrypt failure) is still
 * that interface, and blindly skipping the whole interface meant a real
 * server-side bug could render the generic "That's on us" error screen with
 * NO corresponding log entry at all — confirmed as the actual root cause of
 * one such gap (White Label Oversight blueprint Phase A2, 2026-09-22).
 */
class ErrorLogger
{
    public static function capture(Throwable $e): void
    {
        if ($e instanceof ValidationException || $e instanceof AuthenticationException) {
            return;
        }
        if ($e instanceof HttpExceptionInterface && $e->getStatusCode() < 500) {
            return;
        }

        try {
            ErrorLog::create([
                'code' => class_basename($e),
                'message' => $e->getMessage() ?: class_basename($e),
                'context' => [
                    'file' => $e->getFile(),
                    'line' => $e->getLine(),
                    'exception' => $e::class,
                ],
                'severity' => 'error',
            ]);
        } catch (Throwable $inner) {
            // Never let error logging throw (e.g. DB unavailable) — that would
            // mask the original failure.
        }
    }
}
