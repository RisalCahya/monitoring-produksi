<?php

namespace Tests\Feature;

use App\Models\CounterReading;
use App\Models\Machine;
use App\Services\Production\ProductionCounterService;
use Carbon\CarbonImmutable;
use DomainException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionCounterServiceTest extends TestCase
{
    use RefreshDatabase;

    public function test_first_reading_is_saved_without_a_production_delta(): void
    {
        $machine = $this->createMachine();

        $result = $this->record($machine, '120');

        $this->assertSame('120', $result->reading->counter_value);
        $this->assertNull($result->previousReading);
        $this->assertNull($result->productionIncrease);
        $this->assertFalse($result->counterDecreased);
        $this->assertDatabaseCount('counter_readings', 1);
    }

    public function test_increased_counter_returns_the_exact_difference(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '9007199254740991', '2026-11-06 08:00:00');

        $result = $this->record($machine, '9007199254740993', '2026-11-06 08:01:00');

        $this->assertSame('2', $result->productionIncrease);
        $this->assertFalse($result->counterDecreased);
        $this->assertSame('9007199254740991', $result->previousReading->counter_value);
    }

    public function test_unchanged_counter_returns_zero_production(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '250', '2026-11-06 08:00:00');

        $result = $this->record($machine, '250', '2026-11-06 08:01:00');

        $this->assertSame('0', $result->productionIncrease);
        $this->assertFalse($result->counterDecreased);
    }

    public function test_decreased_counter_is_saved_but_not_counted_as_production(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '400', '2026-11-06 08:00:00');

        $result = $this->record($machine, '12', '2026-11-06 08:01:00');

        $this->assertSame('12', $result->reading->counter_value);
        $this->assertNull($result->productionIncrease);
        $this->assertTrue($result->counterDecreased);
        $this->assertDatabaseCount('counter_readings', 2);
    }

    public function test_counter_history_is_independent_per_machine(): void
    {
        $firstMachine = $this->createMachine('FILL-1');
        $secondMachine = $this->createMachine('FILL-2');
        $this->record($firstMachine, '500');

        $result = $this->record($secondMachine, '10');

        $this->assertNull($result->previousReading);
        $this->assertNull($result->productionIncrease);
        $this->assertSame($secondMachine->id, $result->reading->machine_id);
    }

    public function test_inactive_machines_cannot_receive_counter_readings(): void
    {
        $machine = $this->createMachine();
        $machine->update(['is_active' => false]);

        $this->expectException(DomainException::class);
        $this->record($machine, '1');
    }

    private function createMachine(string $code = 'FILL-1'): Machine
    {
        return Machine::query()->create([
            'code' => $code,
            'name' => 'Filling Gallon',
            'monitoring_point' => 'Filling Gallon',
            'unit' => 'gallon',
            'is_active' => true,
        ]);
    }

    private function record(
        Machine $machine,
        int|string $counterValue,
        string $observedAt = '2026-11-06 08:00:00',
    ): \App\Services\Production\CounterReadingResult {
        return app(ProductionCounterService::class)->record(
            machine: $machine,
            counterValue: $counterValue,
            observedAt: CarbonImmutable::parse($observedAt),
            receivedAt: CarbonImmutable::parse('2026-11-06 08:02:00'),
            source: 'simulator',
        );
    }
}
