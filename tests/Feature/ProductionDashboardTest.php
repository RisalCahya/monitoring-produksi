<?php

namespace Tests\Feature;

use App\Models\Machine;
use App\Services\Production\ProductionCounterService;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

class ProductionDashboardTest extends TestCase
{
    use RefreshDatabase;

    protected function tearDown(): void
    {
        CarbonImmutable::setTestNow();

        parent::tearDown();
    }

    public function test_dashboard_shows_monitoring_points_and_empty_states_when_database_is_empty(): void
    {
        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Production dashboard')
            ->assertSee('Filling Gallon')
            ->assertSee('Filling Bottle 1')
            ->assertSee('Filling Bottle 2')
            ->assertSee('Filling Cup 1')
            ->assertSee('Filling Cup 2')
            ->assertSee('No target configured')
            ->assertSee('Waiting for comparable readings')
            ->assertSee('No production readings yet');

        $this->assertDatabaseCount('counter_readings', 0);
    }

    public function test_dashboard_uses_recorded_counter_differences_for_kpis_chart_and_activity(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00'));

        $machine = Machine::query()->create([
            'code' => 'FILL-GALLON',
            'name' => 'Filling Gallon',
            'monitoring_point' => 'Filling Gallon',
            'unit' => 'gallon',
            'is_active' => true,
        ]);

        $this->record($machine, '100', '2026-11-06 09:40:00', '2026-11-06 09:41:00');
        $this->record($machine, '108', '2026-11-06 09:50:00', '2026-11-06 09:59:00');
        $this->record($machine, '113', '2026-11-06 10:00:00', '2026-11-06 10:00:00');

        $response = $this->get(route('dashboard'));

        $response->assertOk()
            ->assertSee('Production dashboard')
            ->assertSee('13')
            ->assertSee('113')
            ->assertSee('Reporting')
            ->assertSee('simulator')
            ->assertSee('No target configured');

        $this->assertDatabaseCount('counter_readings', 3);
    }

    public function test_dashboard_data_endpoint_returns_current_metrics_and_machine_readings_without_writing(): void
    {
        CarbonImmutable::setTestNow(CarbonImmutable::parse('2026-11-06 10:00:00'));

        $machine = Machine::query()->create([
            'code' => 'FILL-GALLON',
            'name' => 'Filling Gallon',
            'monitoring_point' => 'Filling Gallon',
            'unit' => 'gallon',
            'is_active' => true,
        ]);

        $this->record($machine, '100', '2026-11-06 09:40:00', '2026-11-06 09:41:00');
        $this->record($machine, '108', '2026-11-06 09:50:00', '2026-11-06 09:59:00');
        $readingCount = $machine->counterReadings()->count();

        $response = $this->getJson(route('dashboard.data'));

        $response->assertOk()
            ->assertHeader('Cache-Control', 'no-store, private')
            ->assertJsonStructure([
                'updated_at',
                'kpis' => ['today_production', 'active_machine_count', 'machine_count'],
                'has_recent_data',
                'machines' => [
                    ['code', 'name', 'unit', 'counter_value', 'observed_at', 'status', 'status_label'],
                ],
                'chart' => ['has_data', 'values', 'points', 'path', 'area_path', 'labels', 'maximum', 'start', 'end', 'today_production'],
                'recent_readings' => [
                    ['machine_name', 'machine_code', 'unit', 'counter_value', 'source', 'observed_at', 'received_at', 'observed_at_label', 'received_at_label'],
                ],
            ])
            ->assertJsonPath('kpis.today_production', '8')
            ->assertJsonPath('kpis.active_machine_count', 1)
            ->assertJsonPath('kpis.machine_count', 1)
            ->assertJsonPath('has_recent_data', true)
            ->assertJsonPath('machines.0.code', 'FILL-GALLON')
            ->assertJsonPath('machines.0.counter_value', '108')
            ->assertJsonPath('machines.0.status', 'reporting')
            ->assertJsonPath('chart.has_data', true)
            ->assertJsonPath('recent_readings.0.counter_value', '108');

        $this->assertSame($readingCount, $machine->counterReadings()->count());
    }

    public function test_dashboard_data_endpoint_returns_a_valid_empty_snapshot_without_writing(): void
    {
        $response = $this->getJson(route('dashboard.data'));

        $response->assertOk()
            ->assertJsonPath('kpis.today_production', '0')
            ->assertJsonPath('kpis.active_machine_count', 0)
            ->assertJsonPath('has_recent_data', false)
            ->assertJsonPath('chart.has_data', false)
            ->assertJsonPath('recent_readings', []);

        $this->assertDatabaseCount('machines', 0);
        $this->assertDatabaseCount('counter_readings', 0);
    }

    private function record(
        Machine $machine,
        string $counter,
        string $observedAt,
        string $receivedAt,
    ): void {
        app(ProductionCounterService::class)->record(
            machine: $machine,
            counterValue: $counter,
            observedAt: CarbonImmutable::parse($observedAt),
            receivedAt: CarbonImmutable::parse($receivedAt),
            source: 'simulator',
        );
    }
}
