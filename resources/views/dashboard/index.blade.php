@extends('layouts.app')

@section('title', 'Dashboard')

@section('content')
    <div class="dashboard-page" data-dashboard-endpoint="{{ route('dashboard.data') }}" data-poll-interval="5000">
        <header class="page-heading">
            <div class="heading-copy">
                <div class="eyebrow"><span class="eyebrow-mark"></span> PLANT OPERATIONS <span class="eyebrow-divider">/</span> OVERVIEW</div>
                <h1>Production dashboard</h1>
                <p>Monitor output and machine activity across your production lines.</p>
            </div>
            <div class="heading-meta">
                <div class="date-chip">
                    <x-icon name="calendar" />
                    <span>{{ $today }}</span>
                </div>
                <div class="connection-chip {{ $hasRecentData ? 'connection-online' : 'connection-offline' }}" data-freshness-status aria-live="polite">
                    <span class="connection-dot"></span>
                    <span data-freshness-label>{{ $hasRecentData ? 'Data available' : 'No recent data' }}</span>
                </div>
                <div class="refresh-indicator" data-refresh-status="idle" role="status" aria-live="polite">
                    <span class="refresh-spinner" aria-hidden="true"></span>
                    <span data-refresh-label>Dashboard ready</span>
                </div>
            </div>
        </header>

        <section class="kpi-grid" aria-label="Production key performance indicators">
            <article class="kpi-card kpi-production">
                <div class="kpi-topline">
                    <span class="kpi-label">Total Production</span>
                    <span class="kpi-icon kpi-icon-green"><x-icon name="package" /></span>
                </div>
                <div class="kpi-value"><span class="kpi-number" data-kpi-production>{{ $todayProduction }}</span><span class="kpi-unit">units</span></div>
                <div class="kpi-caption">Recorded output today across monitored points</div>
                <div class="kpi-accent"></div>
            </article>

            <article class="kpi-card">
                <div class="kpi-topline">
                    <span class="kpi-label">Active Machines</span>
                    <span class="kpi-icon kpi-icon-blue"><x-icon name="machine" /></span>
                </div>
                <div class="kpi-value"><span data-kpi-active-machines>{{ $activeMachineCount }}</span><span class="kpi-secondary"> / <span data-kpi-machine-count>{{ $machineCount }}</span> machines</span></div>
                <div class="kpi-caption">Machines marked active in the registry</div>
                <div class="kpi-status-line"><span class="mini-status-dot"></span> Machine registry status</div>
            </article>

            <article class="kpi-card kpi-target-card">
                <div class="kpi-topline">
                    <span class="kpi-label">Production Target</span>
                    <span class="kpi-icon kpi-icon-amber"><x-icon name="target" /></span>
                </div>
                <div class="kpi-value kpi-unconfigured">Not configured</div>
                <div class="kpi-caption">Target data is not available yet</div>
                <div class="kpi-status-line"><span class="target-indicator"></span> Awaiting target setup</div>
            </article>

            <article class="kpi-card">
                <div class="kpi-topline">
                    <span class="kpi-label">Target Achievement</span>
                    <span class="kpi-icon kpi-icon-purple"><x-icon name="trend" /></span>
                </div>
                <div class="kpi-value kpi-unconfigured">—</div>
                <div class="kpi-caption">Cannot calculate without a production target</div>
                <div class="achievement-track"><span></span></div>
            </article>
        </section>

        <section class="overview-grid" aria-label="Production overview">
            <article class="panel chart-panel">
                <div class="panel-heading">
                    <div>
                        <div class="panel-title-row"><span class="panel-icon"><x-icon name="activity" /></span><h2>Production overview</h2></div>
                        <p class="panel-subtitle">Hourly output from stored counter readings</p>
                    </div>
                    <span class="range-pill">LAST 12 HOURS</span>
                </div>

                <div class="chart-data-content" data-chart-content @if (! $chartHasData) hidden @endif>
                    <div class="chart-summary">
                        <div><strong data-chart-today-production>{{ $todayProduction }}</strong><span>units today</span></div>
                        <div class="chart-period"><x-icon name="clock" /> <span data-chart-period>{{ $chartStart }} — {{ $chartEnd }}</span></div>
                    </div>
                    <div class="chart-wrap" role="img" aria-label="Hourly production output chart for the last 12 hours">
                        <svg class="production-chart" viewBox="0 0 760 228" preserveAspectRatio="none" aria-hidden="true">
                            <defs>
                                <linearGradient id="chartFill" x1="0" x2="0" y1="0" y2="1">
                                    <stop offset="0%" stop-color="#14866d" stop-opacity=".2" />
                                    <stop offset="100%" stop-color="#14866d" stop-opacity="0" />
                                </linearGradient>
                            </defs>
                            <line x1="60" y1="34" x2="720" y2="34" class="chart-grid-line" />
                            <line x1="60" y1="109" x2="720" y2="109" class="chart-grid-line" />
                            <line x1="60" y1="184" x2="720" y2="184" class="chart-grid-line" />
                            <text x="0" y="38" class="chart-y-label" data-chart-maximum>{{ $chartMaximum }}</text>
                            <text x="0" y="188" class="chart-y-label">0</text>
                            <path d="{{ $chartAreaPath }}" class="chart-area" data-chart-area />
                            <polyline points="{{ $chartPath }}" class="chart-line" data-chart-line />
                            @foreach ($chartCoordinates as $point)
                                <circle cx="{{ $point['x'] }}" cy="{{ $point['y'] }}" r="3.5" class="chart-point" data-chart-point>
                                    <title>{{ $point['value'] }} units</title>
                                </circle>
                            @endforeach
                        </svg>
                        <div class="chart-x-labels" data-chart-labels>
                            @foreach ($chartLabels as $label)
                                <span style="left: {{ $label['position'] }}%">{{ $label['label'] }}</span>
                            @endforeach
                        </div>
                    </div>
                </div>
                <div class="chart-empty" data-chart-empty @if ($chartHasData) hidden @endif>
                    <div class="empty-chart-icon"><x-icon name="activity" /></div>
                    <strong>Waiting for comparable readings</strong>
                    <p>Production trends appear after a machine has at least two counter readings.</p>
                </div>
            </article>

            <article class="panel target-panel">
                <div class="panel-heading">
                    <div>
                        <div class="panel-title-row"><span class="panel-icon target-panel-icon"><x-icon name="target" /></span><h2>Target tracking</h2></div>
                        <p class="panel-subtitle">Production plan progress</p>
                    </div>
                </div>
                <div class="target-empty-visual">
                    <div class="target-ring target-ring-outer"><div class="target-ring target-ring-middle"><div class="target-ring target-ring-inner"><span>—</span></div></div></div>
                </div>
                <div class="target-empty-copy">
                    <strong>No target configured</strong>
                    <p>Set a production target to compare actual output and calculate achievement.</p>
                </div>
                <div class="target-footnote"><x-icon name="radio" /> Target management is not available yet</div>
            </article>
        </section>

        <section class="section-block" id="machine-monitoring">
            <div class="section-heading">
                <div>
                    <div class="section-overline">LIVE FLOOR STATUS</div>
                    <h2>Machine monitoring</h2>
                    <p>Latest readings from the five configured monitoring points.</p>
                </div>
                <div class="section-count"><span></span>{{ $machineCards->count() }} monitoring points</div>
            </div>

            <div class="machine-grid">
                @foreach ($machineCards as $card)
                    <article class="machine-card" data-machine-card data-machine-code="{{ $card['code'] }}">
                        <div class="machine-card-top">
                            <span class="machine-symbol"><x-icon name="machine" /></span>
                            <span class="machine-status {{ $card['statusClass'] }}" data-machine-status><span></span><span data-machine-status-label>{{ $card['statusLabel'] }}</span></span>
                        </div>
                        <div class="machine-name">{{ $card['name'] }}</div>
                        <div class="machine-code">{{ $card['machine']?->code ?? 'MACHINE NOT REGISTERED' }}</div>
                        <div class="machine-counter-block">
                            <span class="machine-counter-label">Latest counter</span>
                            <div class="machine-counter-value" data-machine-counter>
                                @if ($card['reading'])
                                        <span data-machine-counter-value>{{ \App\Services\Production\CounterValue::format($card['reading']->counter_value) }}</span>
                                        <small data-machine-unit>{{ $card['unit'] }}</small>
                                @else
                                        <span class="counter-placeholder" data-machine-counter-value>—</span>
                                        <small data-machine-unit>{{ $card['unit'] }}</small>
                                @endif
                            </div>
                        </div>
                        <div class="machine-card-footer">
                            <x-icon name="clock" />
                            <span data-machine-observed-at>
                                @if ($card['reading'])
                                    {{ $card['reading']->observed_at->format('d M Y, H:i:s') }}
                                @else
                                    Awaiting first reading
                                @endif
                            </span>
                        </div>
                    </article>
                @endforeach
            </div>
        </section>

        <section class="section-block activity-section" id="production-history">
            <div class="section-heading">
                <div>
                    <div class="section-overline">LATEST DATA POINTS</div>
                    <h2>Recent production activity</h2>
                    <p>Most recent counter readings received by the application.</p>
                </div>
                <span class="activity-count"><span data-activity-count>{{ $recentReadings->count() }}</span> recent readings</span>
            </div>

            <div class="panel activity-panel">
                @if ($recentReadings->isNotEmpty())
                    <div class="table-scroll" data-activity-table>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Monitoring point</th>
                                    <th>Counter value</th>
                                    <th>Source</th>
                                    <th>Observed at</th>
                                    <th>Received at</th>
                                </tr>
                            </thead>
                            <tbody data-recent-readings>
                                @foreach ($recentReadings as $reading)
                                    <tr>
                                        <td>
                                            <div class="activity-machine">
                                                <span class="activity-machine-icon"><x-icon name="machine" /></span>
                                                <span><strong>{{ $reading->machine?->name ?? 'Unknown machine' }}</strong><small>{{ $reading->machine?->code ?? '—' }}</small></span>
                                            </div>
                                        </td>
                                        <td class="activity-counter">{{ \App\Services\Production\CounterValue::format($reading->counter_value) }} <small>{{ $reading->machine?->unit }}</small></td>
                                        <td><span class="source-pill">{{ $reading->source }}</span></td>
                                        <td>{{ $reading->observed_at->format('d M Y, H:i:s') }}</td>
                                        <td>{{ $reading->received_at->format('d M Y, H:i:s') }}</td>
                                    </tr>
                                @endforeach
                            </tbody>
                        </table>
                    </div>
                @else
                    <div class="table-scroll" data-activity-table hidden>
                        <table class="activity-table">
                            <thead>
                                <tr>
                                    <th>Monitoring point</th>
                                    <th>Counter value</th>
                                    <th>Source</th>
                                    <th>Observed at</th>
                                    <th>Received at</th>
                                </tr>
                            </thead>
                            <tbody data-recent-readings></tbody>
                        </table>
                    </div>
                @endif
                <div class="activity-empty" data-activity-empty @if ($recentReadings->isNotEmpty()) hidden @endif>
                    <div class="empty-chart-icon"><x-icon name="history" /></div>
                    <strong>No production readings yet</strong>
                    <p>When counter data is recorded, recent activity will appear here.</p>
                </div>
            </div>
        </section>

        <footer class="dashboard-footer">
            <span>Monitoring Alur Produksi V2</span>
            <span>Data freshness threshold: {{ $staleAfterMinutes }} min</span>
        </footer>
    </div>
@endsection
