<?php

namespace App\Services\Production;

use InvalidArgumentException;

final class CounterValue
{
    private const MAX_UNSIGNED_BIGINT = '18446744073709551615';

    public static function normalize(int|string $counterValue): string
    {
        $counterValue = (string) $counterValue;

        if (! preg_match('/\A\d+\z/', $counterValue)) {
            throw new InvalidArgumentException('Counter value must be a non-negative integer.');
        }

        $counterValue = ltrim($counterValue, '0') ?: '0';

        if (self::compare($counterValue, self::MAX_UNSIGNED_BIGINT) > 0) {
            throw new InvalidArgumentException('Counter value exceeds the unsigned BIGINT limit.');
        }

        return $counterValue;
    }

    public static function compare(string $left, string $right): int
    {
        $left = ltrim($left, '0') ?: '0';
        $right = ltrim($right, '0') ?: '0';

        return strlen($left) <=> strlen($right) ?: strcmp($left, $right);
    }

    public static function subtract(string $minuend, string $subtrahend): string
    {
        if (self::compare($minuend, $subtrahend) < 0) {
            throw new InvalidArgumentException('The counter difference cannot be negative.');
        }

        $difference = '';
        $borrow = 0;
        $rightIndex = strlen($subtrahend) - 1;

        for ($leftIndex = strlen($minuend) - 1; $leftIndex >= 0; $leftIndex--, $rightIndex--) {
            $digit = (int) $minuend[$leftIndex] - $borrow;
            $digit -= $rightIndex >= 0 ? (int) $subtrahend[$rightIndex] : 0;

            if ($digit < 0) {
                $digit += 10;
                $borrow = 1;
            } else {
                $borrow = 0;
            }

            $difference = $digit.$difference;
        }

        return ltrim($difference, '0') ?: '0';
    }

    public static function add(string $left, string $right): string
    {
        $sum = '';
        $carry = 0;
        $leftIndex = strlen($left) - 1;
        $rightIndex = strlen($right) - 1;

        while ($leftIndex >= 0 || $rightIndex >= 0 || $carry > 0) {
            $digit = $carry;
            $digit += $leftIndex >= 0 ? (int) $left[$leftIndex--] : 0;
            $digit += $rightIndex >= 0 ? (int) $right[$rightIndex--] : 0;
            $sum = ($digit % 10).$sum;
            $carry = intdiv($digit, 10);
        }

        return ltrim($sum, '0') ?: '0';
    }

    public static function format(string $counterValue): string
    {
        return preg_replace('/\B(?=(\d{3})+(?!\d))/', ',', $counterValue) ?? $counterValue;
    }
}
