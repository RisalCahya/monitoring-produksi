<?php

namespace App\Http\Controllers;

use App\Models\CounterReading;
use App\Models\Machine;
use App\Services\Production\CounterValue;
use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder as QueryBuilder;
use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

class ProductionHistoryController extends Controller
{
    public function __invoke(Request $request): View
    {
        $filters = $request->validate([
            'start_date' => ['nullable', 'date_format:Y-m-d'],
            'end_date' => ['nullable', 'date_format:Y-m-d', 'after_or_equal:start_date'],
            'machine_id' => ['nullable', 'integer', 'exists:machines,id'],
            'source' => ['nullable', 'string', 'max:50'],
        ]);

        $timezone = config('app.timezone');
        $query = CounterReading::query()
            ->with('machine')
            ->select('counter_readings.*')
            ->addSelect(['previous_counter_value' => $this->previousCounterSubquery()]);

        if (! empty($filters['start_date'])) {
            $query->where(
                'counter_readings.observed_at',
                '>=',
                CarbonImmutable::createFromFormat('!Y-m-d', $filters['start_date'], $timezone),
            );
        }

        if (! empty($filters['end_date'])) {
            $endExclusive = CarbonImmutable::createFromFormat('!Y-m-d', $filters['end_date'], $timezone)
                ->addDay();

            $query->where('counter_readings.observed_at', '<', $endExclusive);
        }

        if (! empty($filters['machine_id'])) {
            $query->where('counter_readings.machine_id', $filters['machine_id']);
        }

        if (! empty($filters['source'])) {
            $query->where('counter_readings.source', $filters['source']);
        }

        /** @var LengthAwarePaginator $readings */
        $readings = $query
            ->orderByDesc('counter_readings.observed_at')
            ->orderByDesc('counter_readings.id')
            ->paginate(25)
            ->withQueryString();

        foreach ($readings as $reading) {
            $previousValue = $reading->getAttribute('previous_counter_value');

            $delta = match (true) {
                $previousValue === null => null,
                CounterValue::compare($reading->counter_value, (string) $previousValue) < 0 => null,
                default => CounterValue::subtract($reading->counter_value, (string) $previousValue),
            };

            $reading->setAttribute('production_delta', $delta);
            $reading->setAttribute('delta_status', match (true) {
                $previousValue === null => 'first',
                $delta === null => 'decreased',
                default => 'calculated',
            });
        }

        return view('production-history.index', [
            'readings' => $readings,
            'machines' => Machine::query()->orderBy('name')->get(['id', 'code', 'name']),
            'sources' => CounterReading::query()->distinct()->orderBy('source')->pluck('source'),
            'filters' => $filters,
        ]);
    }

    private function previousCounterSubquery(): QueryBuilder
    {
        return DB::table('counter_readings as previous_readings')
            ->select('previous_readings.counter_value')
            ->whereColumn('previous_readings.machine_id', 'counter_readings.machine_id')
            ->where(function (QueryBuilder $query): void {
                $query->whereColumn('previous_readings.observed_at', '<', 'counter_readings.observed_at')
                    ->orWhere(function (QueryBuilder $query): void {
                        $query->whereColumn('previous_readings.observed_at', 'counter_readings.observed_at')
                            ->whereColumn('previous_readings.id', '<', 'counter_readings.id');
                    });
            })
            ->orderByDesc('previous_readings.observed_at')
            ->orderByDesc('previous_readings.id')
            ->limit(1);
    }
}
