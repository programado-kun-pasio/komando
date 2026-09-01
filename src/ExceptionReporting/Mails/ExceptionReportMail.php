<?php declare(strict_types=1);

namespace Programado\Komando\ExceptionReporting\Mails;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

final class ExceptionReportMail extends Mailable
{
    use Queueable;
    use SerializesModels;

    /** @param array<string, mixed> $report */
    public function __construct(
        public readonly array $report,
    ) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            from: config('mail.from.address'),
            to: config('komando.exception_reports.recipients'),
            subject: '['.config('app.name').'] Exception report: '.$this->report['class'],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'komando::mails.exception_report',
        );
    }
}
