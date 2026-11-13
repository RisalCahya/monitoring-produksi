<?php

namespace Tests\Feature;

use App\Models\CounterReading;
use App\Models\Machine;
use App\Services\Production\ProductionCounterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionHistoryTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();
        config(['app.timezone' => 'UTC']);

        parent::tearDown();
    }

    public function test_production_history_page_opens_with_an_empty_state(): void
    {
        $this->get(route('production-history.index'))
            ->assertOk()
            ->assertSee('Production history')
            ->assertSee('No production history found')
            ->assertSee('Showing 0–0 of 0');
    }

    public function test_history_displays_counter_details_and_calculated_delta(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '100', '2026-11-06 09:00:00', 'simulator');
        $this->record($machine, '125', '2026-11-06 09:05:00', 'sanco');

        $this->get(route('production-history.index'))
            ->assertOk()
            ->assertSee('Filling Gallon')
            ->assertSee('FILL-1')
            ->assertSee('125')
            ->assertSee('gallon')
            ->assertSee('sanco')
            ->assertSee('25')
            ->assertSee('06 Nov 2026, 09:05:00')
            ->assertSee('First reading');
    }

    public function test_date_filter_includes_the_selected_days_in_the_application_timezone(): void
    {
        config(['app.timezone' => 'Asia/Jakarta']);

        $machine = $this->createMachine();
        $this->record($machine, '100', '2026-11-05 23:55:00');
        $this->record($machine, '120', '2026-11-06 00:15:00');
        $this->record($machine, '130', '2026-11-07 00:10:00');

        $this->get(route('production-history.index', [
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-06',
        ]))
            ->assertOk()
            ->assertSee('120')
            ->assertSee('20')
            ->assertDontSee('130');
    }

    public function test_machine_filter_only_displays_readings_for_the_selected_machine(): void
    {
        $firstMachine = $this->createMachine('FILL-1', 'Filling Gallon');
        $secondMachine = $this->createMachine('FILL-2', 'Filling Bottle 1');
        $this->record($firstMachine, '100');
        $this->record($secondMachine, '200');

        $this->get(route('production-history.index', ['machine_id' => $firstMachine->id]))
            ->assertOk()
            ->assertSee('Filling Gallon')
            ->assertSee('100')
            ->assertDontSee('200');
    }

    public function test_source_filter_only_displays_readings_from_the_selected_source(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '100', '2026-11-06 09:00:00', 'simulator');
        $this->record($machine, '110', '2026-11-06 09:05:00', 'sanco');

        $this->get(route('production-history.index', ['source' => 'sanco']))
            ->assertOk()
            ->assertSee('110')
            ->assertSee('sanco')
            ->assertDontSee('100');
    }

    public function test_pagination_limits_history_to_twenty_five_rows(): void
    {
        $machine = $this->createMachine();
        $this->createReadings($machine, 27);

        $response = $this->get(route('production-history.index'));

        $response->assertOk()
            ->assertSee('Showing 1–25 of 27')
            ->assertSee('page=2');

        $this->assertSame(25, substr_count($response->getContent(), 'activity-machine-icon'));

        $this->get(route('production-history.index', ['page' => 2]))
            ->assertOk()
            ->assertSee('Showing 26–27 of 27')
            ->assertSee('02');
    }

    public function test_active_filters_are_preserved_in_pagination_links(): void
    {
        $machine = $this->createMachine();
        $this->createReadings($machine, 26, 'simulator');

        $this->get(route('production-history.index', [
            'start_date' => '2026-11-06',
            'end_date' => '2026-11-06',
            'machine_id' => $machine->id,
            'source' => 'simulator',
            'page' => 2,
        ]))
            ->assertOk()
            ->assertSee('start_date=2026-11-06')
            ->assertSee('end_date=2026-11-06')
            ->assertSee('machine_id='.$machine->id)
            ->assertSee('source=simulator')
            ->assertSee('page=1');
    }

    public function test_empty_state_is_shown_when_filters_match_no_readings(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '100', '2026-11-06 09:00:00', 'simulator');

        $this->get(route('production-history.index', ['source' => 'sanco']))
            ->assertOk()
            ->assertSee('No production history found')
            ->assertSee('Clear filters');
    }

    public function test_counter_decrease_is_not_shown_as_negative_production(): void
    {
        $machine = $this->createMachine();
        $this->record($machine, '100', '2026-11-06 09:00:00');
        $this->record($machine, '12', '2026-11-06 09:05:00');

        $this->get(route('production-history.index'))
            ->assertOk()
            ->assertSee('Counter decreased')
            ->assertDontSee('-88');
    }

    private function createMachine(string $code = 'FILL-1', string $name = 'Filling Gallon'): Machine
    {
        return Machine::query()->create([
            'code' => $code,
            'name' => $name,
            'monitoring_point' => $name,
            'unit' => 'gallon',
            'is_active' => true,
        ]);
    }

    private function record(
        Machine $machine,
        string $counterValue,
        string $observedAt = '2026-11-06 09:00:00',
        string $source = 'simulator',
    ): CounterReading {
        $observed = CarbonImmutable::parse($observedAt, config('app.timezone'));

        return app(ProductionCounterService::class)->record(
            machine: $machine,
            counterValue: $counterValue,
            observedAt: $observed,
            receivedAt: $observed->addSeconds(1),
            source: $source,
        )->reading;
    }

    private function createReadings(Machine $machine, int $count, string $source = 'simulator'): void
    {
        for ($index = 1; $index <= $count; $index++) {
            $minute = str_pad((string) $index, 2, '0', STR_PAD_LEFT);
            $counter = str_pad((string) $index, 2, '0', STR_PAD_LEFT);

            CounterReading::query()->create([
                'machine_id' => $machine->id,
                'counter_value' => $counter,
                'observed_at' => "2026-11-06 09:{$minute}:00",
                'received_at' => "2026-11-06 09:{$minute}:01",
                'source' => $source,
            ]);
        }
    }
}
