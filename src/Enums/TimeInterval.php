<?php

namespace Ahmadnajmdev\Cashier\Dodo\Enums;

enum TimeInterval: string
{
    case Day = 'Day';
    case Week = 'Week';
    case Month = 'Month';
    case Year = 'Year';

    /**
     * Get the number of days a single interval spans, useful for rough estimates.
     */
    public function approximateDays(): int
    {
        return match ($this) {
            self::Day => 1,
            self::Week => 7,
            self::Month => 30,
            self::Year => 365,
        };
    }
}
