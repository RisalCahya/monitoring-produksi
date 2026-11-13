import './bootstrap';

const dashboard = document.querySelector('[data-dashboard-endpoint]');

if (dashboard) {
    const endpoint = dashboard.dataset.dashboardEndpoint;
    const pollInterval = Number(dashboard.dataset.pollInterval) || 5000;
    const refreshStatus = dashboard.querySelector('[data-refresh-status]');
    const refreshLabel = dashboard.querySelector('[data-refresh-label]');
    const freshnessStatus = dashboard.querySelector('[data-freshness-status]');
    const freshnessLabel = dashboard.querySelector('[data-freshness-label]');
    const numberFormatter = new Intl.NumberFormat();
    const svgNamespace = 'http://www.w3.org/2000/svg';
    let timerId;
    let activeRequest;

    const setRefreshStatus = (status, label) => {
        refreshStatus.dataset.refreshStatus = status;
        refreshLabel.textContent = label;
    };

    const makeActivityCell = (text, className = '') => {
        const cell = document.createElement('td');
        cell.textContent = text;
        if (className) cell.className = className;
        return cell;
    };

    const makeIcon = (className, paths) => {
        const wrapper = document.createElement('span');
        wrapper.className = className;
        wrapper.innerHTML = '<svg class="icon" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="1.8" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><rect x="4" y="4" width="16" height="16" rx="2"/><path d="M9 8v8M15 8v8M4 9H2M4 15H2M22 9h-2M22 15h-2"/></svg>';
        return wrapper;
    };

    const updateMachineCards = (machines) => {
        for (const machine of machines) {
            const card = [...dashboard.querySelectorAll('[data-machine-card]')]
                .find((item) => item.dataset.machineCode === machine.code);
            if (!card) continue;

            const status = card.querySelector('[data-machine-status]');
            status.className = `machine-status ${machine.status === 'reporting' ? 'status-online' : machine.status === 'inactive' ? 'status-muted' : machine.status === 'not-configured' ? 'status-neutral' : 'status-waiting'}`;
            status.querySelector('[data-machine-status-label]').textContent = machine.status_label;

            const value = card.querySelector('[data-machine-counter-value]');
            value.textContent = machine.counter_value ?? '—';
            value.classList.toggle('counter-placeholder', machine.counter_value === null);
            card.querySelector('[data-machine-unit]').textContent = machine.unit;
            card.querySelector('[data-machine-observed-at]').textContent = machine.observed_at
                ? new Intl.DateTimeFormat(undefined, {
                    day: '2-digit', month: 'short', year: 'numeric',
                    hour: '2-digit', minute: '2-digit', second: '2-digit',
                    hourCycle: 'h23',
                }).format(new Date(machine.observed_at))
                : 'Awaiting first reading';
        }
    };

    const updateChart = (chart) => {
        const content = dashboard.querySelector('[data-chart-content]');
        const empty = dashboard.querySelector('[data-chart-empty]');
        content.hidden = !chart.has_data;
        empty.hidden = chart.has_data;
        if (!chart.has_data) return;

        dashboard.querySelector('[data-chart-today-production]').textContent = chart.today_production;
        dashboard.querySelector('[data-chart-period]').textContent = `${chart.start} — ${chart.end}`;
        dashboard.querySelector('[data-chart-maximum]').textContent = chart.maximum;
        dashboard.querySelector('[data-chart-line]').setAttribute('points', chart.path);
        dashboard.querySelector('[data-chart-area]').setAttribute('d', chart.area_path);

        const svg = dashboard.querySelector('.production-chart');
        svg.querySelectorAll('[data-chart-point]').forEach((point) => point.remove());
        for (const point of chart.points) {
            const circle = document.createElementNS(svgNamespace, 'circle');
            circle.setAttribute('cx', point.x);
            circle.setAttribute('cy', point.y);
            circle.setAttribute('r', '3.5');
            circle.setAttribute('class', 'chart-point');
            circle.dataset.chartPoint = '';
            const title = document.createElementNS(svgNamespace, 'title');
            title.textContent = `${point.value} units`;
            circle.append(title);
            svg.append(circle);
        }

        const labels = dashboard.querySelector('[data-chart-labels]');
        labels.replaceChildren(...chart.labels.map((item) => {
            const label = document.createElement('span');
            label.style.left = `${item.position}%`;
            label.textContent = item.label;
            return label;
        }));
    };

    const updateRecentReadings = (readings) => {
        const table = dashboard.querySelector('[data-activity-table]');
        const empty = dashboard.querySelector('[data-activity-empty]');
        const body = dashboard.querySelector('[data-recent-readings]');
        body.replaceChildren(...readings.map((reading) => {
            const row = document.createElement('tr');
            const machineCell = document.createElement('td');
            const machine = document.createElement('div');
            machine.className = 'activity-machine';
            machine.append(makeIcon('activity-machine-icon'));

            const details = document.createElement('span');
            const name = document.createElement('strong');
            name.textContent = reading.machine_name;
            const code = document.createElement('small');
            code.textContent = reading.machine_code;
            details.append(name, code);
            machine.append(details);
            machineCell.append(machine);

            const counter = document.createElement('td');
            counter.className = 'activity-counter';
            counter.append(document.createTextNode(`${reading.counter_value} `));
            const unit = document.createElement('small');
            unit.textContent = reading.unit ?? '';
            counter.append(unit);

            const sourceCell = document.createElement('td');
            const source = document.createElement('span');
            source.className = 'source-pill';
            source.textContent = reading.source;
            sourceCell.append(source);

            row.append(
                machineCell,
                counter,
                sourceCell,
                makeActivityCell(reading.observed_at_label),
                makeActivityCell(reading.received_at_label),
            );
            return row;
        }));

        table.hidden = readings.length === 0;
        empty.hidden = readings.length !== 0;
        dashboard.querySelector('[data-activity-count]').textContent = readings.length;
    };

    const isDashboardResponse = (data) => (
        data
        && data.kpis
        && typeof data.kpis.today_production === 'string'
        && Number.isInteger(data.kpis.active_machine_count)
        && Number.isInteger(data.kpis.machine_count)
        && typeof data.has_recent_data === 'boolean'
        && Array.isArray(data.machines)
        && data.machines.every((machine) => (
            typeof machine.code === 'string'
            && typeof machine.name === 'string'
            && typeof machine.unit === 'string'
            && (machine.counter_value === null || typeof machine.counter_value === 'string')
            && (machine.observed_at === null || typeof machine.observed_at === 'string')
            && typeof machine.status === 'string'
            && typeof machine.status_label === 'string'
        ))
        && data.chart
        && typeof data.chart.has_data === 'boolean'
        && Array.isArray(data.chart.points)
        && Array.isArray(data.chart.labels)
        && typeof data.chart.path === 'string'
        && typeof data.chart.area_path === 'string'
        && typeof data.chart.today_production === 'string'
        && Array.isArray(data.recent_readings)
        && data.recent_readings.every((reading) => (
            typeof reading.machine_name === 'string'
            && typeof reading.machine_code === 'string'
            && typeof reading.counter_value === 'string'
            && typeof reading.source === 'string'
            && typeof reading.observed_at_label === 'string'
            && typeof reading.received_at_label === 'string'
        ))
    );

    const updateDashboard = (data) => {
        if (!isDashboardResponse(data)) {
            throw new Error('Invalid dashboard response');
        }

        dashboard.querySelector('[data-kpi-production]').textContent = data.kpis.today_production;
        dashboard.querySelector('[data-kpi-active-machines]').textContent = data.kpis.active_machine_count;
        dashboard.querySelector('[data-kpi-machine-count]').textContent = data.kpis.machine_count;

        freshnessStatus.classList.toggle('connection-online', data.has_recent_data);
        freshnessStatus.classList.toggle('connection-offline', !data.has_recent_data);
        freshnessLabel.textContent = data.has_recent_data ? 'Data available' : 'No recent data';

        updateMachineCards(data.machines);
        updateChart(data.chart);
        updateRecentReadings(data.recent_readings);
    };

    const poll = async () => {
        if (document.visibilityState !== 'visible') return;

        setRefreshStatus('loading', 'Updating dashboard…');
        const requestController = new AbortController();
        activeRequest = requestController;

        try {
            const response = await fetch(endpoint, {
                method: 'GET',
                headers: {
                    Accept: 'application/json',
                    'X-Requested-With': 'XMLHttpRequest',
                },
                cache: 'no-store',
                credentials: 'same-origin',
                signal: requestController.signal,
            });

            if (!response.ok) throw new Error(`Dashboard request failed (${response.status})`);
            const data = await response.json();
            if (document.visibilityState !== 'visible') return;

            updateDashboard(data);
            setRefreshStatus('success', `Updated ${new Intl.DateTimeFormat(undefined, {
                hour: '2-digit', minute: '2-digit', second: '2-digit', hourCycle: 'h23',
            }).format(new Date(data.updated_at))}`);
        } catch (error) {
            if (error.name !== 'AbortError') setRefreshStatus('error', 'Update failed · retrying');
        } finally {
            if (activeRequest === requestController) activeRequest = undefined;
            if (document.visibilityState === 'visible') {
                timerId = window.setTimeout(() => {
                    timerId = undefined;
                    void poll();
                }, pollInterval);
            }
        }
    };

    document.addEventListener('visibilitychange', () => {
        if (document.visibilityState === 'hidden') {
            window.clearTimeout(timerId);
            timerId = undefined;
            activeRequest?.abort();
            return;
        }

        if (timerId === undefined) void poll();
    });

    void poll();
}
