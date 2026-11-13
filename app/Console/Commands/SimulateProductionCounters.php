<?php

namespace App\Console\Commands;

use App\Models\Machine;
use App\Services\Production\CounterValue;
use App\Services\Production\ProductionCounterService;
use Carbon\CarbonImmutable;
use Illuminate\Console\Command;

class SimulateProductionCounters extends Command
{
    protected $signature = 'production:simulate
        {--once : Record one reading for each configured machine and exit}
        {--interval= : Override the configured interval in seconds}';

    protected $description = 'Simulate production counter readings for local development';

    public function handle(ProductionCounterService $counterService): int
    {
        if (! $this->laravel->environment(['local', 'testing'])) {
            $this->error('The production counter simulator can only run in local or testing environments.');

            return self::FAILURE;
        }

        $interval = $this->resolveInterval();

        if ($interval === null) {
            return self::FAILURE;
        }

        $machines = $this->ensureMachines();

        if ($machines === null) {
            return self::FAILURE;
        }

        do {
            foreach ($machines as [$machine, $definition]) {
                $this->recordReading($counterService, $machine, $definition);
            }

            if ($this->option('once')) {
                return self::SUCCESS;
            }

            sleep($interval);
        } while (true);
    }

    private function resolveInterval(): ?int
    {
        $configuredInterval = $this->option('interval') ?? config('production.simulator.interval_seconds');
        $interval = filter_var($configuredInterval, FILTER_VALIDATE_INT);

        if ($interval === false || $interval < 1) {
            $this->error('The simulator interval must be a positive whole number of seconds.');

            return null;
        }

        return $interval;
    }

    /**
     * @return array<array{0: Machine, 1: array<string, string>}>|null
     */
    private function ensureMachines(): ?array
    {
        $definitions = config('production.simulator.machines');

        if (! is_array($definitions) || $definitions === []) {
            $this->error('No production simulator machines are configured.');

            return null;
        }

        $machines = [];

        foreach ($definitions as $definition) {
            if (! is_array($definition)
                || ! isset($definition['code'], $definition['name'], $definition['monitoring_point'], $definition['unit'], $definition['initial_counter'], $definition['increment'])) {
                $this->error('A production simulator machine definition is incomplete.');

                return null;
            }

            $machine = Machine::query()->firstOrCreate(
                ['code' => $definition['code']],
                [
                    'name' => $definition['name'],
                    'monitoring_point' => $definition['monitoring_point'],
                    'unit' => $definition['unit'],
                    'is_active' => true,
                ],
            );

            $machines[] = [$machine, $definition];
        }

        return $machines;
    }

    /**
     * @param  array{code: string, initial_counter: string, increment: string}  $definition
     */
    private function recordReading(
        ProductionCounterService $counterService,
        Machine $machine,
        array $definition,
    ): void {
        $latestCounter = $machine->counterReadings()
            ->orderByDesc('observed_at')
            ->orderByDesc('id')
            ->value('counter_value');

        $counterValue = $latestCounter === null
            ? CounterValue::normalize($definition['initial_counter'])
            : CounterValue::add(
                CounterValue::normalize((string) $latestCounter),
                CounterValue::normalize($definition['increment']),
            );

        $observedAt = CarbonImmutable::now();
        $result = $counterService->record(
            machine: $machine,
            counterValue: $counterValue,
            observedAt: $observedAt,
            receivedAt: $observedAt,
            source: 'simulator',
        );

        $status = $result->counterDecreased
            ? 'counter decreased; production increase not inferred'
            : ($result->productionIncrease === null
                ? 'baseline recorded'
                : 'increase '.$result->productionIncrease.' '.$machine->unit);

        $this->line("{$machine->code}: counter {$counterValue} ({$status})");
    }
}
