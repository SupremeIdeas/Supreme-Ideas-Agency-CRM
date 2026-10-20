<?php

namespace Tests\Feature;

use App\Models\ErrorLog;
use App\Support\ErrorLogger;
use Illuminate\Auth\AuthenticationException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Validation\ValidationException;
use Symfony\Component\HttpKernel\Exception\HttpException;
use Symfony\Component\HttpKernel\Exception\NotFoundHttpException;
use Tests\TestCase;

/**
 * White Label Oversight blueprint Phase A2. The original noise filter
 * skipped every `HttpExceptionInterface` uniformly — but a controlled
 * `abort(500, ...)` deep in application code (a real server bug) is that
 * same interface, so it was silently dropped from the log too, leaving the
 * admin looking at a generic error screen with nothing to root-cause from.
 * Only 4xx (client-driven, genuinely expected) HTTP exceptions are noise;
 * 5xx ones are real.
 */
class ErrorLoggerTest extends TestCase
{
    use RefreshDatabase;

    public function test_a_500_level_http_exception_is_still_captured(): void
    {
        ErrorLogger::capture(new HttpException(500, 'License activation failed unexpectedly.'));

        $this->assertDatabaseHas('error_logs', ['code' => 'HttpException']);
    }

    public function test_a_404_is_skipped_as_noise(): void
    {
        ErrorLogger::capture(new NotFoundHttpException('No such route.'));

        $this->assertSame(0, ErrorLog::count());
    }

    public function test_a_validation_exception_is_skipped_as_noise(): void
    {
        ErrorLogger::capture(ValidationException::withMessages(['field' => 'required']));

        $this->assertSame(0, ErrorLog::count());
    }

    public function test_an_authentication_exception_is_skipped_as_noise(): void
    {
        ErrorLogger::capture(new AuthenticationException());

        $this->assertSame(0, ErrorLog::count());
    }

    public function test_a_generic_exception_is_captured_with_file_and_line(): void
    {
        try {
            throw new \RuntimeException('Unexpected decrypt failure.');
        } catch (\RuntimeException $e) {
            ErrorLogger::capture($e);
        }

        $log = ErrorLog::first();
        $this->assertNotNull($log);
        $this->assertSame('RuntimeException', $log->code);
        $this->assertSame(__FILE__, $log->context['file']);
    }
}
