<?php

namespace Tests\Feature;

use App\Models\CounterReading;
use App\Models\Machine;
use App\Services\Production\CounterReadingResult;
use App\Services\Production\ProductionCounterService;
use DateTimeInterface;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;
use Tests\TestCase;

class ProductionCounterSimulatorTest extends TestCase
{
    use RefreshDatabase;

    public function test_simulator_creates_each_configured_machine_only_once(): void
    {
        $this->artisan('production:simulate', ['--once' => true])->assertSuccessful();
        $this->artisan('production:simulate', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('machines', 5);
        $this->assertSame(
            ['FILL-BOTTLE-1', 'FILL-BOTTLE-2', 'FILL-CUP-1', 'FILL-CUP-2', 'FILL-GALLON'],
            Machine::query()->orderBy('code')->pluck('code')->all(),
        );
    }

    public function test_simulator_records_readings_through_the_counter_service_only(): void
    {
        $service = Mockery::mock(ProductionCounterService::class);
        $service->shouldReceive('record')
            ->times(5)
            ->withArgs(function (
                Machine $machine,
                int|string $counterValue,
                DateTimeInterface $observedAt,
                DateTimeInterface $receivedAt,
                string $source,
            ): bool {
                return $machine->exists
                    && (string) $counterValue === '0'
                    && $observedAt == $receivedAt
                    && $source === 'simulator';
            })
            ->andReturn(new CounterReadingResult(
                reading: new CounterReading,
                previousReading: null,
                productionIncrease: null,
                counterDecreased: false,
            ));

        $this->app->instance(ProductionCounterService::class, $service);

        $this->artisan('production:simulate', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('machines', 5);
        $this->assertDatabaseCount('counter_readings', 0);
    }

    public function test_simulator_saves_counter_readings_using_the_real_service(): void
    {
        $this->artisan('production:simulate', ['--once' => true])->assertSuccessful();

        $this->assertDatabaseCount('counter_readings', 5);
        $this->assertDatabaseHas('counter_readings', [
            'counter_value' => '0',
            'source' => 'simulator',
        ]);
    }

    public function test_simulator_is_blocked_outside_local_and_testing_environments(): void
    {
        $this->app['env'] = 'production';

        $this->artisan('production:simulate', ['--once' => true])
            ->expectsOutput('The production counter simulator can only run in local or testing environments.')
            ->assertExitCode(1);

        $this->assertDatabaseCount('machines', 0);
        $this->assertDatabaseCount('counter_readings', 0);
    }
}
