@extends('layouts.app')

@section('title', 'Production History')

@section('content')
    <div class="history-page">
        <header class="page-heading">
            <div class="heading-copy">
                <div class="eyebrow"><span class="eyebrow-mark"></span> PLANT OPERATIONS <span class="eyebrow-divider">/</span> RECORDS</div>
                <h1>Production history</h1>
                <p>Review stored counter readings and production changes by machine.</p>
            </div>
            <div class="history-heading-meta">
                <div class="history-record-count">
                    <x-icon name="history" />
                    <span>{{ number_format($readings->total()) }} records</span>
                </div>
            </div>
        </header>

        <section class="panel history-filter-panel" aria-label="Filter production history">
            <div class="history-filter-heading">
                <div class="panel-title-row">
                    <span class="panel-icon"><x-icon name="settings" /></span>
                    <h2>Filter records</h2>
                </div>
                <p>Choose a date range, machine, or data source.</p>
            </div>

            <form method="GET" action="{{ route('production-history.index') }}" class="history-filter-form">
                <div class="history-filter-field">
                    <label for="start_date">Start date</label>
                    <input id="start_date" name="start_date" type="date" value="{{ old('start_date', $filters['start_date'] ?? '') }}">
                </div>
                <div class="history-filter-field">
                    <label for="end_date">End date</label>
                    <input id="end_date" name="end_date" type="date" value="{{ old('end_date', $filters['end_date'] ?? '') }}">
                </div>
                <div class="history-filter-field">
                    <label for="machine_id">Machine</label>
                    <select id="machine_id" name="machine_id">
                        <option value="">All machines</option>
                        @foreach ($machines as $machine)
                            <option value="{{ $machine->id }}" @selected((string) old('machine_id', $filters['machine_id'] ?? '') === (string) $machine->id)>
                                {{ $machine->name }} · {{ $machine->code }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="history-filter-field">
                    <label for="source">Source</label>
                    <select id="source" name="source">
                        <option value="">All sources</option>
                        @foreach ($sources as $source)
                            <option value="{{ $source }}" @selected(old('source', $filters['source'] ?? '') === $source)>
                                {{ ucfirst($source) }}
                            </option>
                        @endforeach
                    </select>
                </div>
                <div class="history-filter-actions">
                    <button class="history-apply-button" type="submit"><x-icon name="check" /> Apply filters</button>
                    <a class="history-reset-button" href="{{ route('production-history.index') }}">Reset</a>
                </div>
            </form>

            @if ($errors->any())
                <div class="history-validation-message" role="alert">
                    @foreach ($errors->all() as $error)
                        <span>{{ $error }}</span>
                    @endforeach
                </div>
            @endif
        </section>

        <section class="section-block history-results-section">
            <div class="section-heading">
                <div>
                    <div class="section-overline">COUNTER AUDIT TRAIL</div>
                    <h2>Counter readings</h2>
                    <p>Delta compares each reading with the previous reading for the same machine.</p>
                </div>
                <span class="activity-count">
                    Showing {{ $readings->firstItem() ?? 0 }}–{{ $readings->lastItem() ?? 0 }} of {{ number_format($readings->total()) }}
                </span>
            </div>

            <div class="panel activity-panel history-table-panel">
                @if ($readings->isNotEmpty())
                    <div class="table-scroll">
                        <table class="activity-table history-table">
                            <thead>
                                <tr>
                                    <th>Machine</th>
                                    <th>Counter value</th>
                                    <th>Production delta</th>
                                    <th>Source</th>
                                    <th>Observed at</th>
                                    <th>Received at</th>
                                </tr>
                            </thead>
                            <tbody>
                                @foreach ($readings as $reading)
                                    <tr>
                                        <td>
                                            <div class="activity-machine">
                                                <span class="activity-machine-icon"><x-icon name="machine" /></span>
                                                <span>
                                                    <strong>{{ $reading->machine?->name ?? 'Unknown machine' }}</strong>
                                                    <small>{{ $reading->machine?->code ?? '—' }}</small>
                                                </span>
                                            </div>
                                        </td>
                                        <td class="activity-counter">
                                            {{ \App\Services\Production\CounterValue::format($reading->counter_value) }}
                                            <small>{{ $reading->machine?->unit }}</small>
                                        </td>
                                        <td>
                                            @if ($reading->delta_status === 'calculated')
                                                <span class="delta-value">{{ \App\Services\Production\CounterValue::format($reading->production_delta) }} <small>{{ $reading->machine?->unit }}</small></span>
                                            @elseif ($reading->delta_status === 'decreased')
                                                <span class="delta-unavailable">Counter decreased</span>
                                            @else
                                                <span class="delta-unavailable">First reading</span>
                                            @endif
                                        </td>
                                        <td><span class="source-pill">{{ $reading->source }}</span></td>
                                        <td>{{ $reading->observed_at->setTimezone(config('app.timezone'))->format('d M Y, H:i:s') }}</td>
                                        <td>{{ $reading->received_at->setTimezone(config('app.timezone'))->format('d M Y, H:i:s') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                    <div class="history-pagination">
                        {{ $readings->links() }}
                    </div>
                @else
                    <div class="activity-empty history-empty">
                        <div class="empty-chart-icon"><x-icon name="history" /></div>
                        <strong>No production history found</strong>
                        <p>There are no counter readings for the selected filters. Try another date range or reset the filters.</p>
                        <a href="{{ route('production-history.index') }}">Clear filters</a>
                    </div>
                @endif
            </div>
        </section>

        <footer class="dashboard-footer">
            <span>Production readings are shown in {{ config('app.timezone') }}.</span>
            <span>Counter decreases are recorded without a negative production delta.</span>
        </footer>
    </div>
@endsection
