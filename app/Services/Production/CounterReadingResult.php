<?php

namespace App\Services\Production;

use App\Models\CounterReading;

final readonly class CounterReadingResult
{
    public function __construct(
        public CounterReading $reading,
        public ?CounterReading $previousReading,
        public ?string $productionIncrease,
        public bool $counterDecreased,
    ) {}
}
