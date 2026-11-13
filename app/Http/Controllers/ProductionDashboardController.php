<?php

namespace App\Http\Controllers;

use App\Models\CounterReading;
use App\Models\Machine;
use App\Services\Production\CounterValue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class ProductionDashboardController extends Controller
{
    public function __invoke(): View
    {
        return view('dashboard.index', $this->dashboardData());
    }

    public function data(): JsonResponse
    {
        $dashboard = $this->dashboardData();

        return response()->json([
            'updated_at' => CarbonImmutable::now()->toIso8601String(),
            'kpis' => [
                'today_production' => $dashboard['todayProduction'],
                'active_machine_count' => $dashboard['activeMachineCount'],
                'machine_count' => $dashboard['machineCount'],
            ],
            'has_recent_data' => $dashboard['hasRecentData'],
            'machines' => $dashboard['machineCards']->map(fn (array $card): array => [
                'code' => $card['code'],
                'name' => $card['name'],
                'unit' => $card['unit'],
                'counter_value' => $card['reading']
                    ? CounterValue::format($card['reading']->counter_value)
                    : null,
                'observed_at' => $card['reading']?->observed_at->toIso8601String(),
                'status' => $card['status'],
                'status_label' => $card['statusLabel'],
            ])->values(),
            'chart' => [
                'has_data' => $dashboard['chartHasData'],
                'values' => collect($dashboard['chartValues'])
                    ->map(fn (string $value): string => CounterValue::format($value))
                    ->values(),
                'points' => $dashboard['chartCoordinates'],
                'path' => $dashboard['chartPath'],
                'area_path' => $dashboard['chartAreaPath'],
                'labels' => $dashboard['chartLabels']->values(),
                'maximum' => $dashboard['chartMaximum'],
                'start' => $dashboard['chartStart'],
                'end' => $dashboard['chartEnd'],
                'today_production' => $dashboard['todayProduction'],
            ],
            'recent_readings' => $dashboard['recentReadings']->map(fn (CounterReading $reading): array => [
                'machine_name' => $reading->machine?->name ?? 'Unknown machine',
                'machine_code' => $reading->machine?->code ?? '—',
                'unit' => $reading->machine?->unit,
                'counter_value' => CounterValue::format($reading->counter_value),
                'source' => $reading->source,
                'observed_at' => $reading->observed_at->toIso8601String(),
                'received_at' => $reading->received_at->toIso8601String(),
                'observed_at_label' => $reading->observed_at->format('d M Y, H:i:s'),
                'received_at_label' => $reading->received_at->format('d M Y, H:i:s'),
            ])->values(),
        ])->header('Cache-Control', 'no-store, private');
    }

    private function dashboardData(): array
    {
        $now = CarbonImmutable::now();
        $todayStart = $now->startOfDay();
        $chartStart = $now->startOfHour()->subHours(11);
        $rangeStart = $chartStart->lessThan($todayStart) ? $chartStart : $todayStart;
        $staleAfterMinutes = max(1, (int) config('production.dashboard.stale_after_minutes', 5));
        $staleThreshold = $now->subMinutes($staleAfterMinutes);

        $machines = Machine::query()
            ->with(['counterReadings' => function (HasMany $query): void {
                $query->orderByDesc('observed_at')->orderByDesc('id')->limit(1);
            }])
            ->orderBy('name')
            ->get();
        $machinesByCode = $machines->keyBy('code');

        $knownPoints = collect(config('production.simulator.machines', []));
        $machineCards = $knownPoints
            ->map(fn (array $point): array => $this->machineCard(
                machine: $machinesByCode->get($point['code']),
                code: $point['code'],
                name: $point['name'],
                unit: $point['unit'],
                staleThreshold: $staleThreshold,
            ));

        $machineCards = $machineCards->concat(
            $machines
                ->whereNotIn('code', $knownPoints->pluck('code'))
                ->map(fn (Machine $machine): array => $this->machineCard(
                    machine: $machine,
                    code: $machine->code,
                    name: $machine->name,
                    unit: $machine->unit,
                    staleThreshold: $staleThreshold,
                ))
        );

        $todayProduction = '0';
        $chartHasData = false;
        $hourKeys = [];
        $chartValues = [];

        for ($hour = 0; $hour < 12; $hour++) {
            $key = $chartStart->addHours($hour)->format('Y-m-d H:00:00');
            $hourKeys[] = $key;
            $chartValues[$key] = '0';
        }

        $previousValues = [];

        foreach ($machines as $machine) {
            $previousValues[$machine->id] = $machine->counterReadings()
                ->where('observed_at', '<', $rangeStart)
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->value('counter_value');
        }

        $readings = CounterReading::query()
            ->where('observed_at', '>=', $rangeStart)
            ->where('observed_at', '<=', $now)
            ->orderBy('machine_id')
            ->orderBy('observed_at')
            ->orderBy('id')
            ->get(['id', 'machine_id', 'counter_value', 'observed_at']);

        foreach ($readings as $reading) {
            $machineId = $reading->machine_id;
            $previousValue = $previousValues[$machineId] ?? null;
            $observedAt = $reading->observed_at;

            if ($previousValue !== null) {
                $chartHasData = $chartHasData || $observedAt->greaterThanOrEqualTo($chartStart);

                if (CounterValue::compare($reading->counter_value, $previousValue) >= 0) {
                    $increase = CounterValue::subtract($reading->counter_value, $previousValue);

                    if ($observedAt->greaterThanOrEqualTo($todayStart)) {
                        $todayProduction = CounterValue::add($todayProduction, $increase);
                    }

                    if ($observedAt->greaterThanOrEqualTo($chartStart)) {
                        $hourKey = $observedAt->startOfHour()->format('Y-m-d H:00:00');
                        $chartValues[$hourKey] = CounterValue::add($chartValues[$hourKey] ?? '0', $increase);
                    }
                }
            }

            $previousValues[$machineId] = $reading->counter_value;
        }

        $chartMaximum = '0';

        foreach ($chartValues as $value) {
            if (CounterValue::compare($value, $chartMaximum) > 0) {
                $chartMaximum = $value;
            }
        }

        $chartCoordinates = [];
        $chartMaximumFloat = (float) $chartMaximum;

        foreach (array_values($chartValues) as $index => $value) {
            $ratio = $chartMaximumFloat > 0 ? (float) $value / $chartMaximumFloat : 0;
            $x = 60 + (660 * $index / 11);
            $y = 184 - (150 * $ratio);
            $chartCoordinates[] = [
                'x' => round($x, 2),
                'y' => round($y, 2),
                'value' => CounterValue::format($value),
            ];
        }

        $chartPath = implode(' ', array_map(
            fn (array $point): string => $point['x'].','.$point['y'],
            $chartCoordinates,
        ));
        $chartAreaPath = 'M 60 184 L '.$chartPath.' L 720 184 Z';

        $chartLabels = collect($hourKeys)
            ->filter(fn (string $key, int $index): bool => in_array($index, [0, 3, 6, 9, 11], true))
            ->map(fn (string $key, int $index): array => [
                'label' => CarbonImmutable::parse($key)->format('H:i'),
                'position' => round(($index / 11) * 100, 2),
            ])
            ->values();

        $latestReceivedAt = CounterReading::query()->max('received_at');
        $hasRecentData = $latestReceivedAt !== null
            && CarbonImmutable::parse($latestReceivedAt)->greaterThanOrEqualTo($staleThreshold);

        return [
            'today' => $now->format('l, d F Y'),
            'todayProduction' => CounterValue::format($todayProduction),
            'activeMachineCount' => $machines->where('is_active', true)->count(),
            'machineCount' => $machines->count(),
            'machineCards' => $machineCards,
            'chartHasData' => $chartHasData,
            'chartValues' => $chartValues,
            'chartCoordinates' => $chartCoordinates,
            'chartPath' => $chartPath,
            'chartAreaPath' => $chartAreaPath,
            'chartLabels' => $chartLabels,
            'chartMaximum' => CounterValue::format($chartMaximum),
            'chartStart' => $chartStart->format('H:i'),
            'chartEnd' => $now->format('H:i'),
            'hasRecentData' => $hasRecentData,
            'staleAfterMinutes' => $staleAfterMinutes,
            'recentReadings' => CounterReading::query()
                ->with('machine')
                ->orderByDesc('observed_at')
                ->orderByDesc('id')
                ->limit(8)
                ->get(),
        ];
    }

    /**
     * @return array{machine: ?Machine, code: string, name: string, unit: string, reading: ?CounterReading, status: string, statusLabel: string, statusClass: string}
     */
    private function machineCard(
        ?Machine $machine,
        string $code,
        string $name,
        string $unit,
        CarbonImmutable $staleThreshold,
    ): array {
        $reading = $machine?->counterReadings->first();

        [$status, $statusLabel, $statusClass] = match (true) {
            $machine === null => ['not-configured', 'Not configured', 'status-neutral'],
            ! $machine->is_active => ['inactive', 'Inactive', 'status-muted'],
            $reading === null => ['waiting', 'Waiting for data', 'status-waiting'],
            $reading->received_at->lessThan($staleThreshold) => ['stale', 'Data stale', 'status-waiting'],
            default => ['reporting', 'Reporting', 'status-online'],
        };

        return compact('machine', 'code', 'name', 'unit', 'reading', 'status', 'statusLabel', 'statusClass');
    }
}
