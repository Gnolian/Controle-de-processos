document.querySelectorAll('[data-confirm]').forEach((element) => {
  element.addEventListener('click', (event) => {
    if (!window.confirm(element.getAttribute('data-confirm'))) {
      event.preventDefault();
    }
  });
});

document.querySelectorAll('.clickable-row[data-href]').forEach((row) => {
  row.style.cursor = 'pointer';
  row.addEventListener('click', (event) => {
    if (event.target.closest('a, button, input, select, textarea, label')) {
      return;
    }
    window.location.href = row.dataset.href;
  });
});

const auditFilters = document.querySelector('#audit-filters');
if (auditFilters) {
  auditFilters.querySelectorAll('select:not([multiple])').forEach((select) => {
    select.addEventListener('change', () => {
      auditFilters.requestSubmit();
    });
  });
}

document.querySelectorAll('.filter-select').forEach((filterSelect) => {
  filterSelect.addEventListener('toggle', () => {
    if (!filterSelect.open) return;
    document.querySelectorAll('.filter-select[open]').forEach((other) => {
      if (other !== filterSelect) {
        other.open = false;
      }
    });
  });
});

document.addEventListener('click', (event) => {
  if (event.target.closest('.filter-select')) return;
  document.querySelectorAll('.filter-select[open]').forEach((filterSelect) => {
    filterSelect.open = false;
  });
});

const syncAuditPanels = () => {
  const leftPanel = document.querySelector('.audit-table-panel');
  const rightPanel = document.querySelector('.audit-point-panel');
  const rightBody = document.querySelector('.audit-point-cards');

  if (!leftPanel || !rightPanel || !rightBody) return;

  rightPanel.style.height = '';
  rightBody.style.height = '';
  rightBody.style.maxHeight = '';

  if (window.innerWidth < 992) {
    return;
  }

  const leftHeight = leftPanel.getBoundingClientRect().height;
  const rightHeader = rightPanel.querySelector('.card-head');
  const panelStyle = window.getComputedStyle(rightPanel);
  const paddingTop = parseFloat(panelStyle.paddingTop || '0');
  const paddingBottom = parseFloat(panelStyle.paddingBottom || '0');
  const headerHeight = rightHeader ? rightHeader.getBoundingClientRect().height : 0;
  const availableBodyHeight = Math.max(140, leftHeight - headerHeight - paddingTop - paddingBottom);

  rightPanel.style.height = `${leftHeight}px`;
  rightBody.style.height = `${availableBodyHeight}px`;
};

requestAnimationFrame(syncAuditPanels);
window.addEventListener('load', syncAuditPanels);
window.addEventListener('resize', syncAuditPanels);

const renderChartLegend = (canvas, rows, colors) => {
  if (!rows.length || !canvas.parentElement) return;

  let legend = canvas.parentElement.querySelector('.chart-legend');
  if (!legend) {
    legend = document.createElement('div');
    legend.className = 'chart-legend';
    canvas.insertAdjacentElement('afterend', legend);
  }

  legend.innerHTML = rows.map((row, index) => `
    <button type="button" class="chart-legend-item ${row.url || row.link ? 'is-clickable' : ''}" data-url="${row.url || row.link || ''}">
      <span class="chart-legend-swatch" style="background:${colors[index % colors.length]}"></span>
      <span class="chart-legend-label">${row.label}</span>
      <strong>${Number(row.total)}</strong>
    </button>
  `).join('');

  legend.querySelectorAll('[data-url]').forEach((item) => {
    item.addEventListener('click', () => {
      const url = item.dataset.url;
      if (url) {
        window.location.href = url;
      }
    });
  });
};

document.querySelectorAll('canvas[data-chart]').forEach((canvas) => {
  if (!window.Chart) return;

  let rows = [];
  try {
    rows = JSON.parse(canvas.dataset.chart || '[]');
  } catch (error) {
    rows = [];
  }

  const labels = rows.map((row) => row.label);
  const values = rows.map((row) => Number(row.total));
  const colors = ['#0f766e', '#2563eb', '#b45309', '#be123c', '#475569', '#16a34a', '#7c3aed', '#d97706'];
  const chartMode = canvas.dataset.chartMode || (canvas.classList.contains('bar') ? 'bar' : 'doughnut');
  const type = chartMode === 'horizontal-bar' ? 'bar' : chartMode;
  const links = rows.map((row) => row.url || row.link || null);
  const hideLegend = canvas.dataset.chartLegend === 'none';

  new Chart(canvas, {
    type,
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: rows.map((_, index) => colors[index % colors.length]),
        borderWidth: type === 'doughnut' ? 2 : 0,
        borderColor: '#fff',
        borderRadius: type === 'bar' ? 8 : 0,
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          display: false,
        },
        tooltip: {
          callbacks: {
            label(context) {
              const parsed = typeof context.parsed === 'object'
                ? (chartMode === 'horizontal-bar'
                  ? (context.parsed?.x ?? context.raw ?? 0)
                  : (context.parsed?.y ?? context.raw ?? 0))
                : context.parsed;
              return `${context.label}: ${parsed}`;
            },
          },
        },
      },
      scales: type === 'bar' ? {
        x: {
          beginAtZero: true,
          ticks: {
            display: chartMode === 'horizontal-bar',
            precision: 0,
          },
          grid: {
            display: false,
          },
        },
        y: {
          ticks: {
            precision: 0,
            callback(value, index) {
              if (chartMode === 'horizontal-bar') {
                const label = labels[index] || '';
                return label.length > 28 ? `${label.slice(0, 28)}...` : label;
              }
              return value;
            },
          },
        },
      } : {},
      indexAxis: chartMode === 'horizontal-bar' ? 'y' : 'x',
      onClick: (_, elements, chart) => {
        if (!elements.length) return;
        const url = links[elements[0].index];
        if (url) {
          window.location.href = url;
        }
      },
    },
  });

  if (!hideLegend) {
    renderChartLegend(canvas, rows, colors);
  }
});

const itemsBody = document.querySelector('#audit-items-body');
const itemTemplate = document.querySelector('#audit-item-template');
const addItemButton = document.querySelector('#add-audit-item');

const reindexAuditItems = () => {
  if (!itemsBody) return;

  const rowGroups = [];
  const rows = Array.from(itemsBody.querySelectorAll('tr'));
  for (let i = 0; i < rows.length; i += 2) {
    if (rows[i]) {
      rowGroups.push([rows[i], rows[i + 1] || null]);
    }
  }

  rowGroups.forEach((group, index) => {
    group.forEach((row) => {
      if (!row) return;
      row.querySelectorAll('[name]').forEach((field) => {
        field.name = field.name.replace(/items\[\d+\]/, `items[${index}]`);
      });
    });
  });
};

if (addItemButton && itemTemplate && itemsBody) {
  addItemButton.addEventListener('click', () => {
    const fragment = itemTemplate.content.cloneNode(true);
    const nextIndex = Math.floor(itemsBody.querySelectorAll('.audit-item-row').length);

    fragment.querySelectorAll('[data-name]').forEach((field) => {
      const fieldName = field.dataset.name;
      field.setAttribute('name', `items[${nextIndex}][${fieldName}]`);
    });

    itemsBody.appendChild(fragment);
    reindexAuditItems();
  });

  itemsBody.addEventListener('click', (event) => {
    const button = event.target.closest('.remove-audit-item');
    if (!button) return;

    const row = button.closest('tr');
    const detail = row && row.nextElementSibling && row.nextElementSibling.classList.contains('audit-item-row-detail')
      ? row.nextElementSibling
      : null;

    row?.remove();
    detail?.remove();
    reindexAuditItems();
  });
}

const timelineModal = document.querySelector('#timelineAuditModal');
if (timelineModal) {
  const modal = window.bootstrap ? new window.bootstrap.Modal(timelineModal) : null;

  document.querySelectorAll('[data-timeline-entry]').forEach((button) => {
    button.addEventListener('click', () => {
      let payload = null;
      try {
        payload = JSON.parse(button.dataset.timelineEntry || '{}');
      } catch (error) {
        payload = null;
      }

      if (!payload) return;

      const setText = (selector, value) => {
        const element = timelineModal.querySelector(selector);
        if (element) element.textContent = value || '-';
      };

      setText('[data-timeline-title]', payload.audit_code);
      setText('[data-timeline-id]', payload.audit_code);
      setText('[data-timeline-nup]', payload.audit_nup);
      setText('[data-timeline-body]', payload.requesting_body);
      setText('[data-timeline-theme]', payload.theme);
      setText('[data-timeline-phase]', payload.audit_phase);
      setText('[data-timeline-owner]', payload.current_owner);
      const deadlineLabel = payload.deadline_is_current
        ? new Date().toLocaleDateString('pt-BR')
        : payload.deadline_label;
      setText('[data-timeline-deadline]', `${deadlineLabel}${payload.flag_estimated ? ' (estimado)' : ''}`);
      setText('[data-timeline-summary]', payload.control_summary);

      const link = timelineModal.querySelector('[data-timeline-link]');
      if (link) {
        link.href = `audit_detail.php?id=${payload.id}`;
      }

      modal?.show();
    });
  });
}
