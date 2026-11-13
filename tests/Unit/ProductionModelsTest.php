<?php

namespace Tests\Unit;

use App\Models\CounterReading;
use App\Models\Machine;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Tests\TestCase;

class ProductionModelsTest extends TestCase
{
    public function test_machine_has_many_counter_readings(): void
    {
        $machine = new Machine;
        $relation = $machine->counterReadings();

        $this->assertInstanceOf(HasMany::class, $relation);
        $this->assertSame('machine_id', $relation->getForeignKeyName());
        $this->assertSame('machines', $machine->getTable());
        $this->assertTrue($machine->hasCast('is_active', 'boolean'));
    }

    public function test_counter_reading_belongs_to_machine_and_preserves_large_counter_values(): void
    {
        $reading = new CounterReading;
        $relation = $reading->machine();

        $this->assertInstanceOf(BelongsTo::class, $relation);
        $this->assertSame('machine_id', $relation->getForeignKeyName());
        $this->assertSame('counter_readings', $reading->getTable());
        $this->assertSame('string', $reading->getCasts()['counter_value']);
        $reading->counter_value = '18446744073709551615';
        $this->assertSame('18446744073709551615', $reading->counter_value);
        $this->assertTrue($reading->hasCast('observed_at', 'datetime'));
        $this->assertTrue($reading->hasCast('received_at', 'datetime'));
    }
}
