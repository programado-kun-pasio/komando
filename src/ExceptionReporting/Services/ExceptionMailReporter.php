<?php declare(strict_types=1);

namespace Programado\Komando\ExceptionReporting\Services;

use Illuminate\Http\Request;
use Illuminate\Routing\Route;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;
use Programado\Komando\ExceptionReporting\Exceptions\ExceptionReportDeliveryException;
use Programado\Komando\ExceptionReporting\Jobs\SendExceptionReportMail;
use Throwable;

final class ExceptionMailReporter
{
    public function report(Throwable $exception, ?Request $request = null): void
    {
        if (! $this->isEnabled()
            || $exception instanceof ExceptionReportDeliveryException) {
            return;
        }

        try {
            $location = $this->applicationLocation($exception);
            $fingerprint = $this->fingerprint($exception, $location);
            $cacheKey = 'komando.exception_mail_reports.'.$fingerprint;
            $throttleMinutes = (int) config('komando.exception_reports.throttle_minutes');

            if (! Cache::add($cacheKey, true, now()->addMinutes($throttleMinutes))) {
                return;
            }

            SendExceptionReportMail::dispatch([
                'class' => class_basename($exception),
                'message' => Str::limit(
                    $exception->getMessage(),
                    (int) config('komando.exception_reports.max_message_length'),
                ),
                'location' => $location['file'].':'.$location['line'],
                'stack_trace' => Str::limit(
                    $exception->getTraceAsString(),
                    (int) config('komando.exception_reports.max_stack_trace_length'),
                ),
                'fingerprint' => $fingerprint,
                'occurred_at' => now()->utc()->toIso8601String(),
                'environment' => app()->environment(),
                'request' => $this->requestContext($request),
            ]);
        } catch (Throwable $reportingException) {
            $this->logReportingFailure($reportingException);
        }
    }

    private function isEnabled(): bool
    {
        return config('komando.exception_reports.enabled', false)
            && in_array(
                app()->environment(),
                config('komando.exception_reports.environments', ['production']),
                true,
            );
    }

    /** @return array{file: string, line: int} */
    private function applicationLocation(Throwable $exception): array
    {
        $basePath = base_path().DIRECTORY_SEPARATOR;
        $frames = collect([
            ['file' => $exception->getFile(), 'line' => $exception->getLine()],
            ...$exception->getTrace(),
        ]);

        $frame = $frames->first(fn (array $frame): bool => isset($frame['file'])
            && str_starts_with($frame['file'], $basePath)
            && ! str_contains($frame['file'], DIRECTORY_SEPARATOR.'vendor'.DIRECTORY_SEPARATOR)
        ) ?? $frames->first();

        $file = (string) ($frame['file'] ?? $exception->getFile());

        return [
            'file' => str_starts_with($file, $basePath) ? Str::after($file, $basePath) : $file,
            'line' => (int) ($frame['line'] ?? $exception->getLine()),
        ];
    }

    /** @param array{file: string, line: int} $location */
    private function fingerprint(Throwable $exception, array $location): string
    {
        $normalizedMessage = preg_replace(
            [
                '/\b[0-9a-f]{8}-[0-9a-f]{4}-[1-8][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}\b/i',
                '/\b\d+\b/',
            ],
            ['{uuid}', '{number}'],
            $exception->getMessage(),
        ) ?? $exception->getMessage();

        return hash('sha256', implode('|', [
            $exception::class,
            $normalizedMessage,
            $location['file'],
            (string) $location['line'],
        ]));
    }

    /** @return array<string, int|string|null> */
    private function requestContext(?Request $request): array
    {
        if ($request === null) {
            return [];
        }

        $route = $request->route();

        return [
            'method' => $request->method(),
            'url' => $request->url(),
            'route' => $route instanceof Route ? $route->getName() : null,
            'user_id' => $request->user()?->getAuthIdentifier(),
            'request_id' => Str::limit((string) $request->header('X-Request-ID'), 255),
        ];
    }

    private function logReportingFailure(Throwable $exception): void
    {
        try {
            Log::error('Exception report could not be queued.', [
                'exception' => $exception,
            ]);
        } catch (Throwable) {
            // The original exception must never be hidden by its reporting path.
        }
    }
}
