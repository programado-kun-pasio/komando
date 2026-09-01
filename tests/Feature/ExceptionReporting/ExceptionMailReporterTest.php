<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Feature\ExceptionReporting;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Queue;
use Programado\Komando\ExceptionReporting\Exceptions\ExceptionReportDeliveryException;
use Programado\Komando\ExceptionReporting\Jobs\SendExceptionReportMail;
use Programado\Komando\ExceptionReporting\Mails\ExceptionReportMail;
use Programado\Komando\ExceptionReporting\Services\ExceptionMailReporter;
use Programado\Komando\Tests\TestCase;
use RuntimeException;

final class ExceptionMailReporterTest extends TestCase
{
    protected function setUp(): void
    {
        parent::setUp();

        $this->app['env'] = 'production';
        config()->set('cache.default', 'array');
        config()->set('komando.exception_reports.enabled', true);
        config()->set('komando.exception_reports.recipients', ['maintenance@example.com']);
        config()->set('komando.exception_reports.queue', 'alerts');
        Cache::flush();
        Queue::fake();
    }

    public function test_it_queues_a_sanitized_exception_report(): void
    {
        $request = Request::create(
            '/graphql?token=query-secret',
            'POST',
            ['password' => 'payload-secret'],
        );
        $request->headers->set('X-Request-ID', 'request-123');

        app(ExceptionMailReporter::class)->report(
            new RuntimeException('Something failed for record 123'),
            $request,
        );

        Queue::assertPushed(SendExceptionReportMail::class, function (SendExceptionReportMail $job): bool {
            $serializedReport = json_encode($job->report, JSON_THROW_ON_ERROR);

            return $job->queue === 'alerts'
                && $job->report['class'] === 'RuntimeException'
                && $job->report['request']['method'] === 'POST'
                && $job->report['request']['url'] === 'http://localhost/graphql'
                && $job->report['request']['request_id'] === 'request-123'
                && ! str_contains($serializedReport, 'payload-secret')
                && ! str_contains($serializedReport, 'query-secret');
        });
    }

    public function test_it_atomically_throttles_normalized_fingerprints(): void
    {
        $reporter = app(ExceptionMailReporter::class);

        $reporter->report($this->exceptionForRecord(123));
        $reporter->report($this->exceptionForRecord(456));

        Queue::assertPushed(SendExceptionReportMail::class, 1);
    }

    public function test_it_does_not_report_delivery_failures(): void
    {
        app(ExceptionMailReporter::class)->report(
            new ExceptionReportDeliveryException(new RuntimeException('SMTP unavailable')),
        );

        Queue::assertNothingPushed();
    }

    public function test_it_is_disabled_by_default(): void
    {
        config()->set('komando.exception_reports.enabled', false);

        app(ExceptionMailReporter::class)->report(new RuntimeException('Test exception'));

        Queue::assertNothingPushed();
    }

    public function test_it_only_reports_in_configured_environments(): void
    {
        $this->app['env'] = 'testing';

        app(ExceptionMailReporter::class)->report(new RuntimeException('Test exception'));

        Queue::assertNothingPushed();
    }

    public function test_the_delivery_job_wraps_mail_failures_without_reporting_them_again(): void
    {
        Mail::shouldReceive('send')
            ->once()
            ->andThrow(new RuntimeException('SMTP unavailable'));
        Log::shouldReceive('error')->once();

        $this->expectException(ExceptionReportDeliveryException::class);

        (new SendExceptionReportMail([
            'fingerprint' => 'test-fingerprint',
        ]))->handle();
    }

    public function test_the_mail_renders_the_package_view(): void
    {
        $html = (new ExceptionReportMail([
            'class' => 'RuntimeException',
            'message' => 'Something failed',
            'location' => 'app/Services/Example.php:42',
            'stack_trace' => '#0 example',
            'fingerprint' => 'test-fingerprint',
            'occurred_at' => '2026-09-01T12:00:00+00:00',
            'environment' => 'production',
            'request' => [],
        ]))->render();

        $this->assertStringContainsString('app/Services/Example.php:42', $html);
        $this->assertStringContainsString('test-fingerprint', $html);
    }

    public function test_the_mail_wraps_long_exception_content(): void
    {
        $html = (new ExceptionReportMail([
            'class' => 'RuntimeException',
            'message' => 'Something failed',
            'location' => 'app/Services/Example.php:42',
            'stack_trace' => str_repeat('unbreakable-stack-trace-token', 50),
            'fingerprint' => str_repeat('a', 64),
            'occurred_at' => '2026-09-01T12:00:00+00:00',
            'environment' => 'production',
            'request' => [],
        ]))->render();

        $this->assertStringContainsString('white-space: pre-wrap', $html);
        $this->assertStringContainsString('overflow-wrap: anywhere', $html);
        $this->assertStringContainsString('word-break: break-word', $html);
        $this->assertStringContainsString('word-break: break-all', $html);
    }

    private function exceptionForRecord(int $recordId): RuntimeException
    {
        return new RuntimeException("Something failed for record {$recordId}");
    }
}
