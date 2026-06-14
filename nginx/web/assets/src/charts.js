import { Chart, registerables } from 'chart.js';

Chart.register(...registerables);

const chartInstances = new WeakMap();

function cssVar(name, fallback) {
  const value = getComputedStyle(document.documentElement).getPropertyValue(name).trim();
  return value || fallback;
}

function isDarkTheme() {
  const explicitTheme = document.documentElement.getAttribute('data-theme');
  if (explicitTheme === 'dark') return true;
  if (explicitTheme === 'light') return false;

  try {
    return window.matchMedia('(prefers-color-scheme: dark)').matches;
  } catch (error) {
    return false;
  }
}

function getThemeColors() {
  return {
    text: cssVar('--ts-text', isDarkTheme() ? '#e5e7eb' : '#1f2937'),
    muted: cssVar('--ts-muted', isDarkTheme() ? '#94a3b8' : '#64748b'),
    grid: cssVar('--ts-table-border', isDarkTheme() ? '#334155' : '#e2e8f0'),
    background: isDarkTheme() ? 'rgba(167, 139, 250, 0.28)' : 'rgba(109, 40, 217, 0.24)',
    border: cssVar('--ts-purple', isDarkTheme() ? '#a78bfa' : '#6d28d9'),
  };
}

function graphContainer(canvas) {
  return canvas.closest('.ts-chart-canvas-wrap') || canvas.parentElement || canvas;
}

function setChartState(canvas, state, message = '') {
  const container = graphContainer(canvas);
  container.dataset.chartState = state;

  let messageEl = container.querySelector(':scope > .ts-chart-message');
  if (!message) {
    if (messageEl) messageEl.remove();
    return;
  }

  if (!messageEl) {
    messageEl = document.createElement('p');
    messageEl.className = 'ts-chart-message';
    container.appendChild(messageEl);
  }
  messageEl.textContent = message;
}

function compactNumber(value) {
  const number = Number(value);
  if (!Number.isFinite(number)) return value;

  return new Intl.NumberFormat(undefined, {
    notation: Math.abs(number) >= 10000 ? 'compact' : 'standard',
    maximumFractionDigits: Math.abs(number) >= 10000 ? 1 : 0,
  }).format(number);
}

function formatDateLabel(value) {
  if (typeof value !== 'string') return String(value ?? '');
  const parsed = Date.parse(value);
  if (Number.isNaN(parsed)) return value;

  return new Intl.DateTimeFormat(undefined, {
    month: 'short',
    day: 'numeric',
    hour: '2-digit',
    minute: '2-digit',
    timeZone: 'UTC',
  }).format(new Date(parsed));
}

function labelForTick(value, labels) {
  const raw = labels[value] ?? value;
  const dateLabel = formatDateLabel(raw);
  return dateLabel.length > 18 ? `${dateLabel.slice(0, 17)}…` : dateLabel;
}

function normalizeGraphData(json) {
  const rawLabels = Array.isArray(json.labels) ? json.labels : [];
  const rawData = Array.isArray(json.data) ? json.data : [];
  const length = Math.min(rawLabels.length, rawData.length);
  const labels = [];
  const data = [];

  for (let i = 0; i < length; i += 1) {
    const value = Number(rawData[i]);
    if (!Number.isFinite(value)) continue;
    labels.push(String(rawLabels[i] ?? ''));
    data.push(value);
  }

  return {
    labels,
    data,
    title: typeof json.title === 'string' ? json.title : '',
    legend: typeof json.legend === 'string' ? json.legend : '',
  };
}

function destroyExistingChart(canvas) {
  const existing = chartInstances.get(canvas);
  if (existing) {
    existing.destroy();
    chartInstances.delete(canvas);
  }
}

function shouldUseHorizontalBars(canvas, graph) {
  if (canvas.dataset.chartLayout === 'vertical') return false;
  return canvas.dataset.chartLayout === 'horizontal' || graph.labels.length > 24;
}

function setCanvasHeight(canvas, graph, type) {
  const baseHeight = canvas.closest('.ts-graph-card-wide') ? 350 : 280;
  let height = baseHeight;

  if (type === 'bar') {
    const labelCount = Math.max(1, graph.labels.length);
    const horizontal = shouldUseHorizontalBars(canvas, graph);
    if (horizontal) {
      height = Math.min(900, Math.max(baseHeight, labelCount * 24));
    } else if (labelCount > 18) {
      height = Math.min(640, Math.max(baseHeight, labelCount * 18));
    }
  }

  canvas.style.height = `${height}px`;
  const wrapper = canvas.closest('.ts-chart-canvas-wrap');
  if (wrapper) wrapper.style.minHeight = `${height}px`;
}

function sharedOptions(canvas, graph, type) {
  const colors = getThemeColors();
  const valueLabel = canvas.dataset.chartValueLabel || graph.legend || (type === 'line' ? 'Value' : 'Count');

  return {
    responsive: true,
    maintainAspectRatio: false,
    interaction: {
      intersect: false,
      mode: 'index',
    },
    plugins: {
      title: {
        display: false,
      },
      legend: {
        display: !!graph.legend,
        labels: {
          color: colors.text,
          boxWidth: 12,
          boxHeight: 12,
          usePointStyle: true,
        },
      },
      tooltip: {
        callbacks: {
          title(items) {
            const label = items[0]?.label ?? '';
            return type === 'line' ? formatDateLabel(label) : label;
          },
          label(item) {
            return `${valueLabel}: ${compactNumber(item.parsed.y ?? item.parsed.x ?? item.raw)}`;
          },
        },
      },
    },
  };
}

function barScales(canvas, graph, horizontal) {
  const colors = getThemeColors();
  const categoryTicks = {
    color: colors.text,
    autoSkip: horizontal ? false : graph.labels.length > 16,
    maxRotation: horizontal ? 0 : graph.labels.length > 16 ? 60 : 0,
  };
  const valueTicks = {
    color: colors.text,
    callback: compactNumber,
  };
  const grid = { color: colors.grid, drawBorder: false };

  if (horizontal) {
    return {
      x: { ticks: valueTicks, grid, beginAtZero: true },
      y: { ticks: categoryTicks, grid: { display: false, drawBorder: false } },
    };
  }

  return {
    x: { ticks: categoryTicks, grid },
    y: { ticks: valueTicks, grid, beginAtZero: true },
  };
}

function renderBarChart(canvas, graph) {
  const colors = getThemeColors();
  const horizontal = shouldUseHorizontalBars(canvas, graph);
  setCanvasHeight(canvas, graph, 'bar');
  destroyExistingChart(canvas);

  const chart = new Chart(canvas, {
    type: 'bar',
    data: {
      labels: graph.labels,
      datasets: [{
        label: graph.legend || canvas.dataset.chartValueLabel || 'Count',
        data: graph.data,
        backgroundColor: colors.background,
        borderColor: colors.border,
        borderWidth: 1,
        borderRadius: 6,
        maxBarThickness: horizontal ? 20 : 34,
      }],
    },
    options: {
      ...sharedOptions(canvas, graph, 'bar'),
      indexAxis: horizontal ? 'y' : 'x',
      scales: barScales(canvas, graph, horizontal),
    },
  });

  chartInstances.set(canvas, chart);
  return chart;
}

function renderLineChart(canvas, graph) {
  const colors = getThemeColors();
  setCanvasHeight(canvas, graph, 'line');
  destroyExistingChart(canvas);

  const chart = new Chart(canvas, {
    type: 'line',
    data: {
      labels: graph.labels,
      datasets: [{
        label: graph.legend || canvas.dataset.chartValueLabel || graph.title || 'Value',
        data: graph.data,
        backgroundColor: colors.background,
        borderColor: colors.border,
        borderWidth: 2,
        fill: true,
        pointRadius: 0,
        pointHitRadius: 10,
        tension: 0.25,
      }],
    },
    options: {
      ...sharedOptions(canvas, graph, 'line'),
      scales: {
        x: {
          ticks: {
            color: colors.text,
            autoSkip: true,
            maxTicksLimit: 8,
            maxRotation: 0,
            callback(value) {
              return labelForTick(value, graph.labels);
            },
          },
          grid: { color: colors.grid, drawBorder: false },
        },
        y: {
          ticks: {
            color: colors.text,
            callback: compactNumber,
          },
          grid: { color: colors.grid, drawBorder: false },
          beginAtZero: true,
        },
      },
    },
  });

  chartInstances.set(canvas, chart);
  return chart;
}

function renderChart(canvas, json, type) {
  const graph = normalizeGraphData(json);
  if (graph.data.length === 0) {
    canvas.hidden = true;
    setChartState(canvas, 'empty', canvas.dataset.chartEmptyMessage || 'No chart data is available.');
    return;
  }

  canvas.hidden = false;
  setChartState(canvas, 'ready');
  if (type === 'line') {
    renderLineChart(canvas, graph);
  } else {
    renderBarChart(canvas, graph);
  }
}

export function initCharts() {
  document.querySelectorAll('[data-chart-endpoint]').forEach((el) => {
    const endpoint = el.dataset.chartEndpoint;
    const type = el.dataset.chartType || 'bar';
    if (!endpoint) return;

    const canvas = el.tagName === 'CANVAS' ? el : document.createElement('canvas');
    if (el.tagName !== 'CANVAS') {
      el.appendChild(canvas);
    }

    setChartState(canvas, 'loading', 'Loading chart…');

    fetch(endpoint, { credentials: 'same-origin', headers: { Accept: 'application/json' } })
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then((json) => renderChart(canvas, json, type))
      .catch((err) => {
        console.error('Chart load failed:', err);
        canvas.hidden = true;
        setChartState(canvas, 'error', canvas.dataset.chartErrorMessage || 'Chart could not be loaded.');
      });
  });
}

window.addEventListener('torstatus:themechange', () => {
  document.querySelectorAll('[data-chart-endpoint]').forEach((canvas) => {
    const chart = chartInstances.get(canvas);
    if (!chart) return;

    const colors = getThemeColors();
    chart.data.datasets.forEach((dataset) => {
      dataset.backgroundColor = colors.background;
      dataset.borderColor = colors.border;
    });
    Object.values(chart.options.scales || {}).forEach((scale) => {
      if (scale.ticks) scale.ticks.color = colors.text;
      if (scale.grid) scale.grid.color = colors.grid;
    });
    if (chart.options.plugins?.legend?.labels) chart.options.plugins.legend.labels.color = colors.text;
    chart.update('none');
  });
});
