<?php declare(strict_types=1);

namespace Programado\Komando\ExceptionReporting\Exceptions;

use Illuminate\Contracts\Debug\ShouldntReport;
use RuntimeException;
use Throwable;

final class ExceptionReportDeliveryException extends RuntimeException implements ShouldntReport
{
    public function __construct(Throwable $previous)
    {
        parent::__construct('The exception report mail could not be delivered.', previous: $previous);
    }
}
