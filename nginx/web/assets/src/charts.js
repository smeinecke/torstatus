import { Chart, registerables } from 'chart.js';
import 'chartjs-adapter-date-fns';

Chart.register(...registerables);

function getThemeColors() {
  const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
  return {
    text: isDark ? '#e5e7eb' : '#1f2937',
    grid: isDark ? '#374151' : '#e5e7eb',
    background: isDark ? 'rgba(96, 165, 250, 0.5)' : 'rgba(59, 130, 246, 0.5)',
    border: isDark ? '#60a5fa' : '#3b82f6',
  };
}

function renderBarChart(canvas, json) {
  const colors = getThemeColors();
  return new Chart(canvas, {
    type: 'bar',
    data: {
      labels: json.labels,
      datasets: [{
        label: json.legend || '',
        data: json.data,
        backgroundColor: colors.background,
        borderColor: colors.border,
        borderWidth: 1,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: json.title,
          color: colors.text,
        },
        legend: {
          display: !!json.legend,
          labels: { color: colors.text },
        },
      },
      scales: {
        x: {
          ticks: { color: colors.text },
          grid: { color: colors.grid },
        },
        y: {
          ticks: { color: colors.text },
          grid: { color: colors.grid },
          beginAtZero: true,
        },
      },
    },
  });
}

function renderLineChart(canvas, json) {
  const colors = getThemeColors();
  return new Chart(canvas, {
    type: 'line',
    data: {
      datasets: [{
        label: json.title,
        data: json.labels.map((x, i) => ({ x, y: json.data[i] })),
        backgroundColor: colors.background,
        borderColor: colors.border,
        borderWidth: 1,
        fill: true,
        tension: 0.1,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        title: {
          display: true,
          text: json.title,
          color: colors.text,
        },
        legend: { display: false },
      },
      scales: {
        x: {
          type: 'time',
          time: {
            displayFormats: {
              minute: 'HH:mm',
              hour: 'HH:mm',
              day: 'MMM d',
            },
          },
          ticks: { color: colors.text, maxRotation: 90 },
          grid: { color: colors.grid },
        },
        y: {
          ticks: { color: colors.text },
          grid: { color: colors.grid },
          beginAtZero: true,
        },
      },
    },
  });
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

    fetch(endpoint)
      .then((res) => {
        if (!res.ok) throw new Error(`HTTP ${res.status}`);
        return res.json();
      })
      .then((json) => {
        if (type === 'line') {
          renderLineChart(canvas, json);
        } else {
          renderBarChart(canvas, json);
        }
      })
      .catch((err) => {
        console.error('Chart load failed:', err);
        canvas.style.display = 'none';
      });
  });
}
