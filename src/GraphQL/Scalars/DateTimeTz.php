<?php declare(strict_types=1);

namespace Programado\Komando\GraphQL\Scalars;

use Illuminate\Support\Carbon;
use Nuwave\Lighthouse\Schema\Types\Scalars\DateTimeTz as LighthouseDateTimeTz;

final class DateTimeTz extends LighthouseDateTimeTz
{
    protected function parse(string $value): Carbon
    {
        if (preg_match('/\.(\d+)(Z|[+-]\d{2}:\d{2})$/', $value, $matches) === 1) {
            $fraction = str_pad(substr($matches[1], 0, 6), 6, '0');
            $value = substr($value, 0, -strlen($matches[0])).'.'.$fraction.$matches[2];

            return Carbon::createFromFormat('Y-m-d\TH:i:s.uP', $value)->utc();
        }

        return parent::parse($value)->utc();
    }
}
