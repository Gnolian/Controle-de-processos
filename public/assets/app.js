document.querySelectorAll('[data-confirm]').forEach((element) => {
  element.addEventListener('click', (event) => {
    if (!window.confirm(element.getAttribute('data-confirm'))) {
      event.preventDefault();
    }
  });
});

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
  const colors = ['#0f766e', '#2563eb', '#b45309', '#be123c', '#475569', '#16a34a', '#7c3aed'];
  const type = canvas.classList.contains('bar') ? 'bar' : 'doughnut';

  new Chart(canvas, {
    type,
    data: {
      labels,
      datasets: [{
        data: values,
        backgroundColor: colors,
        borderWidth: type === 'doughnut' ? 2 : 0,
        borderColor: '#fff',
      }],
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      plugins: {
        legend: {
          position: type === 'doughnut' ? 'bottom' : 'top',
        },
      },
      scales: type === 'bar' ? {
        y: {
          beginAtZero: true,
          ticks: {
            precision: 0,
          },
        },
      } : {},
    },
  });
});

