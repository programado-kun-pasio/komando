<?php declare(strict_types=1);

namespace Programado\Komando\ExceptionReporting\Jobs;

use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Programado\Komando\ExceptionReporting\Exceptions\ExceptionReportDeliveryException;
use Programado\Komando\ExceptionReporting\Mails\ExceptionReportMail;
use Throwable;

final class SendExceptionReportMail implements ShouldQueue
{
    use Queueable;

    public int $tries = 3;

    /** @var array<int, int> */
    public array $backoff = [60, 300];

    /** @param array<string, mixed> $report */
    public function __construct(
        public readonly array $report,
    ) {
        $this->onQueue(config('komando.exception_reports.queue'));
    }

    public function handle(): void
    {
        try {
            Mail::send(new ExceptionReportMail($this->report));
        } catch (Throwable $exception) {
            Log::error('Exception report mail delivery failed.', [
                'exception' => $exception,
                'fingerprint' => $this->report['fingerprint'],
            ]);

            throw new ExceptionReportDeliveryException($exception);
        }
    }
}
