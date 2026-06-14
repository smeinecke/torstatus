import './app.css';
import { initCharts } from './charts.js';

function setupThemeToggle() {
  const btn = document.getElementById('theme-toggle');
  const icon = document.getElementById('theme-icon');
  if (!btn) return;

  function updateIcon() {
    if (!icon) return;
    const isDark = document.documentElement.getAttribute('data-theme') === 'dark';
    icon.className = isDark ? 'ti ti-sun' : 'ti ti-moon';
  }

  updateIcon();

  btn.addEventListener('click', () => {
    const html = document.documentElement;
    const current = html.getAttribute('data-theme');
    const next = current === 'dark' ? 'light' : 'dark';
    html.setAttribute('data-theme', next);
    localStorage.setItem('torstatus-theme', next);
    updateIcon();
  });
}

function setupDialogs() {
  document.querySelectorAll('[data-dialog-open]').forEach((button) => {
    button.addEventListener('click', () => {
      const dialog = document.getElementById(button.dataset.dialogOpen);
      if (!dialog) return;
      if (typeof dialog.showModal === 'function') {
        if (!dialog.open) dialog.showModal();
      } else {
        dialog.setAttribute('open', 'open');
      }
    });
  });

  document.querySelectorAll('dialog').forEach((dialog) => {
    dialog.addEventListener('click', (event) => {
      const rect = dialog.getBoundingClientRect();
      const inDialog = rect.top <= event.clientY && event.clientY <= rect.bottom && rect.left <= event.clientX && event.clientX <= rect.right;
      if (!inDialog && event.target === dialog && typeof dialog.close === 'function') dialog.close();
    });
  });
}

function setupAutoSubmit() {
  document.querySelectorAll('[data-auto-submit]').forEach((input) => {
    input.addEventListener('change', () => {
      if (input.form) input.form.submit();
    });
  });
}

function setupAutoOpenDialogs() {
  document.querySelectorAll('dialog[data-auto-open="true"]').forEach((dialog) => {
    if (typeof dialog.showModal === 'function' && !dialog.open) {
      dialog.showModal();
    }
  });
}

setupThemeToggle();
setupDialogs();
setupAutoSubmit();
setupAutoOpenDialogs();
initCharts();
