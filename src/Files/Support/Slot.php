<?php declare(strict_types=1);

namespace Programado\Komando\Files\Support;

use BackedEnum;

final class Slot
{
    public static function value(BackedEnum|string $slot): string
    {
        return $slot instanceof BackedEnum
            ? strval($slot->value)
            : $slot;
    }
}
