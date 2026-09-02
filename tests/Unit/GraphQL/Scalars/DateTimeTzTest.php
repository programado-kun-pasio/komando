<?php declare(strict_types=1);

namespace Programado\Komando\Tests\Unit\GraphQL\Scalars;

use GraphQL\Error\Error;
use PHPUnit\Framework\Attributes\DataProvider;
use PHPUnit\Framework\TestCase;
use Programado\Komando\GraphQL\Scalars\DateTimeTz;

final class DateTimeTzTest extends TestCase
{
    /**
     * @return array<string, array{string, string}>
     */
    public static function dateTimes(): array
    {
        return [
            'winter offset' => ['2026-01-15T10:30:16+01:00', '2026-01-15T09:30:16.000000+00:00'],
            'summer offset' => ['2026-07-14T10:30:16+02:00', '2026-07-14T08:30:16.000000+00:00'],
            'microseconds' => ['2026-07-14T10:30:16.2946Z', '2026-07-14T10:30:16.294600+00:00'],
            'Temporal nanoseconds' => ['2026-07-14T10:30:16.294600098Z', '2026-07-14T10:30:16.294600+00:00'],
        ];
    }

    #[DataProvider('dateTimes')]
    public function test_it_normalizes_date_times_to_utc(string $value, string $expected): void
    {
        $dateTime = (new DateTimeTz)->parseValue($value);

        $this->assertSame($expected, $dateTime->format('Y-m-d\TH:i:s.uP'));
    }

    public function test_it_rejects_invalid_date_times(): void
    {
        $this->expectException(Error::class);

        (new DateTimeTz)->parseValue('not-a-date');
    }
}
