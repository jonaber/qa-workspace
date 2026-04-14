const actionButtons = document.querySelectorAll('[data-api-endpoint]');
const statusPanel = document.getElementById('cwv-status');
const chartCanvas = document.getElementById('performance-chart');
const chartStatus = document.getElementById('chart-status');

let performanceChart = null;

function renderStatus(message, isError = false) {
    statusPanel.textContent = message;
    statusPanel.classList.toggle('is-error', isError);
}

async function runAction(button) {
    const endpoint = button.dataset.apiEndpoint;
    if (!endpoint) {
        return;
    }

    const originalText = button.textContent;
    button.disabled = true;
    button.textContent = 'Running...';
    renderStatus('Processing request...');

    try {
        const response = await fetch(endpoint, {
            method: 'GET'
        });

        const rawResponse = await response.text();
        let result;

        try {
            result = JSON.parse(rawResponse);
        } catch (parseError) {
            const snippet = rawResponse.slice(0, 180).replace(/\s+/g, ' ').trim();
            throw new Error(`Server returned non-JSON response: ${snippet || 'empty response'}`);
        }

        if (!response.ok || !result.success) {
            throw new Error(result.error || 'Request failed');
        }

        const details = [];
        if (typeof result.monitorCount === 'number') {
            details.push(`monitor IDs: ${result.monitorCount}`);
        }
        if (typeof result.monitorCalls === 'number') {
            details.push(`monitor calls: ${result.monitorCalls}`);
        }
        if (typeof result.runIdCount === 'number') {
            details.push(`run IDs: ${result.runIdCount}`);
        }
        if (typeof result.runCalls === 'number') {
            details.push(`run calls: ${result.runCalls}`);
        }
        if (typeof result.fetched === 'number') {
            details.push(`fetched: ${result.fetched}`);
        }
        if (typeof result.inserted === 'number') {
            details.push(`inserted: ${result.inserted}`);
        }
        if (typeof result.pruned === 'number') {
            details.push(`pruned: ${result.pruned}`);
        }
        if (typeof result.runValueCount === 'number') {
            details.push(`run values: ${result.runValueCount}`);
        }
        if (typeof result.successfulCalls === 'number') {
            details.push(`successful calls: ${result.successfulCalls}`);
        }
        if (typeof result.durationMs === 'number') {
            details.push(`time: ${(result.durationMs / 1000).toFixed(2)}s`);
        }

        const suffix = details.length > 0 ? ` (${details.join(', ')})` : '';
        renderStatus(`${result.message || 'Completed successfully.'}${suffix}`);

        if (endpoint.includes('fetch-run-values')) {
            showLastFetchTimestamp();
            loadPerformanceChart();
        }
    } catch (error) {
        renderStatus(error.message || 'Failed to process request.', true);
    } finally {
        button.disabled = false;
        button.textContent = originalText;
    }
}

actionButtons.forEach((button) => {
    button.addEventListener('click', () => {
        runAction(button);
    });
});

async function loadPerformanceChart() {
    if (!chartCanvas || typeof Chart === 'undefined') {
        return;
    }

    chartStatus.textContent = 'Loading chart data...';
    chartStatus.classList.remove('is-error');

    try {
        const [chartResponse, settingsResponse] = await Promise.all([
            fetch('api/chart-performance.php'),
            fetch('api/settings.php')
        ]);

        const result = await chartResponse.json();
        const settingsResult = await settingsResponse.json();

        const thresholdAmber = Number(settingsResult.scoreAmber) || 50;
        const thresholdRed = Number(settingsResult.scoreRed) || 25;

        if (!chartResponse.ok || !result.success) {
            throw new Error(result.error || 'Failed to load chart data');
        }

        if (!result.labels || result.labels.length === 0) {
            chartStatus.textContent = 'No run value data available yet. Complete step 3 first.';
            return;
        }

        const labels = result.labels.map((url) =>
            url.replace(/^https?:\/\/(www\.)?/, '').replace(/\/$/, '')
        );

        const backgroundColors = result.values.map((score) => {
            if (score > thresholdAmber) return 'rgba(22, 163, 74, 0.75)';
            if (score > thresholdRed) return 'rgba(234, 179, 8, 0.75)';
            return 'rgba(220, 38, 38, 0.75)';
        });

        const borderColors = backgroundColors.map((c) => c.replace('0.75', '1'));

        if (performanceChart) {
            performanceChart.destroy();
        }

        performanceChart = new Chart(chartCanvas, {
            type: 'bar',
            data: {
                labels,
                datasets: [{
                    label: 'Avg. Performance Score',
                    data: result.values,
                    backgroundColor: backgroundColors,
                    borderColor: borderColors,
                    borderWidth: 1,
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        callbacks: {
                            title: (items) => result.labels[items[0].dataIndex],
                            afterLabel: (item) => {
                                const lines = [];
                                const region = result.regions?.[item.dataIndex];
                                if (region) {
                                    lines.push(`Region: ${region}`);
                                }
                                lines.push(`Runs averaged: ${result.runCounts[item.dataIndex]}`);
                                return lines.join('\n');
                            }
                        }
                    }
                },
                scales: {
                    y: {
                        min: 0,
                        max: 100,
                        title: { display: true, text: 'Performance Score' },
                        ticks: { stepSize: 10 }
                    },
                    x: {
                        ticks: {
                            maxRotation: 45,
                            minRotation: 30,
                            font: { size: 11 }
                        }
                    }
                }
            }
        });

        chartStatus.textContent = `${result.total} URL${result.total !== 1 ? 's' : ''} displayed`;
    } catch (error) {
        chartStatus.textContent = error.message || 'Failed to load chart.';
        chartStatus.classList.add('is-error');
    }
}

async function showLastFetchTimestamp() {
    const el = document.getElementById('last-fetch-timestamp');
    if (!el) {
        return;
    }
    try {
        const response = await fetch('api/settings.php');
        const data = await response.json();
        const ts = data.lastFetchRunValuesAt || '';
        if (ts) {
            const formatted = new Date(ts).toLocaleString();
            el.textContent = `Last run values fetch: ${formatted}`;
        }
    } catch (_) {
        // non-critical, ignore
    }
}

showLastFetchTimestamp();
loadPerformanceChart();
