<?php

namespace App\Services\Production;

use App\Models\CounterReading;
use App\Models\Machine;
use DateTimeInterface;
use DomainException;
use InvalidArgumentException;
use Illuminate\Support\Facades\DB;

class ProductionCounterService
{
    public function record(
        Machine $machine,
        int|string $counterValue,
        DateTimeInterface $observedAt,
        DateTimeInterface $receivedAt,
        string $source,
    ): CounterReadingResult {
        $counterValue = CounterValue::normalize($counterValue);
        $source = trim($source);

        if ($source === '' || strlen($source) > 50) {
            throw new InvalidArgumentException('Source must contain between 1 and 50 characters.');
        }

        if (! $machine->exists || $machine->getKey() === null) {
            throw new InvalidArgumentException('A persisted machine is required.');
        }

        return DB::transaction(function () use ($machine, $counterValue, $observedAt, $receivedAt, $source): CounterReadingResult {
            $lockedMachine = Machine::query()
                ->lockForUpdate()
                ->findOrFail($machine->getKey());

            if (! $lockedMachine->is_active) {
                throw new DomainException('Counter readings cannot be recorded for an inactive machine.');
            }

            $previousReading = $lockedMachine->counterReadings()
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->first();

            $counterDecreased = $previousReading !== null
                && CounterValue::compare($counterValue, $previousReading->counter_value) < 0;

            $productionIncrease = match (true) {
                $previousReading === null, $counterDecreased => null,
                default => CounterValue::subtract($counterValue, $previousReading->counter_value),
            };

            $reading = $lockedMachine->counterReadings()->create([
                'counter_value' => $counterValue,
                'observed_at' => $observedAt,
                'received_at' => $receivedAt,
                'source' => $source,
            ]);

            return new CounterReadingResult(
                reading: $reading,
                previousReading: $previousReading,
                productionIncrease: $productionIncrease,
                counterDecreased: $counterDecreased,
            );
        });
    }
}
