<?php

namespace App\Support;

use Brick\Math\BigDecimal;
use Brick\Math\BigInteger;
use Brick\Math\RoundingMode;
use Illuminate\Validation\ValidationException;

final class Amounts
{
    public static function parse(mixed $value, int $scale = 2): int
    {
        if (! is_scalar($value) || ! preg_match('/^\d{1,9}(\.\d{1,'.$scale.'})?$/D', (string) $value)) {
            throw ValidationException::withMessages(['amount' => 'Enter a positive decimal with at most '.$scale.' decimal places (no commas).']);
        }

        return BigDecimal::of((string) $value)->multipliedBy(10 ** $scale)->toBigInteger()->toInt();
    }

    public static function ratio(int $value, int $numerator, int $denominator): int
    {
        $result = BigInteger::of($value)->multipliedBy($numerator)->dividedBy($denominator, RoundingMode::HalfUp);
        if ($result->abs()->isGreaterThan('9000000000000000')) {
            throw ValidationException::withMessages(['amount' => 'This transaction exceeds the supported amount. Split it into smaller transactions.']);
        }

        return $result->toInt();
    }

    /** @param int<0, 6> $scale */
    public static function display(int|string $value, int $scale = 2): string
    {
        return (string) BigDecimal::of($value)->dividedBy(10 ** $scale, $scale, RoundingMode::HalfUp);
    }

    public static function quantity(int|string $value): string
    {
        return rtrim(rtrim(self::display($value, 6), '0'), '.');
    }
}
