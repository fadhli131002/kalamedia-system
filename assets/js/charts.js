/**
 * Kalamedia Cashflow & Financial Visualizations (Chart.js)
 * Untitled UI Light Theme - High-Contrast Readability & Crisp Grid
 */

let cashflowChartInstance = null;

function formatRupiahShort(value) {
  if (value >= 1000000000) {
    return 'Rp ' + (value / 1000000000).toFixed(1) + ' M';
  } else if (value >= 1000000) {
    return 'Rp ' + (value / 1000000).toFixed(1) + ' Jt';
  } else if (value >= 1000) {
    return 'Rp ' + (value / 1000).toFixed(0) + ' Rb';
  }
  return 'Rp ' + value;
}

function initCashflowChart(chartData) {
  const canvas = document.getElementById('cashflowChart');
  if (!canvas) return;

  const ctx = canvas.getContext('2d');

  // Destroy previous instance if any
  if (cashflowChartInstance) {
    cashflowChartInstance.destroy();
  }

  // Gradients for light theme
  const revGradient = ctx.createLinearGradient(0, 0, 0, 300);
  revGradient.addColorStop(0, 'rgba(16, 185, 129, 0.18)');
  revGradient.addColorStop(1, 'rgba(16, 185, 129, 0.01)');

  const expGradient = ctx.createLinearGradient(0, 0, 0, 300);
  expGradient.addColorStop(0, 'rgba(239, 68, 68, 0.15)');
  expGradient.addColorStop(1, 'rgba(239, 68, 68, 0.01)');

  cashflowChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: 'Total Revenue (Uang Masuk)',
          data: chartData.revenue,
          borderColor: '#10B981',
          backgroundColor: revGradient,
          borderWidth: 2.5,
          fill: true,
          tension: 0.35,
          pointBackgroundColor: '#10B981',
          pointBorderColor: '#FFFFFF',
          pointBorderWidth: 2,
          pointRadius: 4.5,
          pointHoverRadius: 6.5
        },
        {
          label: 'Total Expense (Uang Keluar)',
          data: chartData.expense,
          borderColor: '#EF4444',
          backgroundColor: expGradient,
          borderWidth: 2.5,
          fill: true,
          tension: 0.35,
          pointBackgroundColor: '#EF4444',
          pointBorderColor: '#FFFFFF',
          pointBorderWidth: 2,
          pointRadius: 4.5,
          pointHoverRadius: 6.5
        }
      ]
    },
    options: {
      responsive: true,
      maintainAspectRatio: false,
      interaction: {
        mode: 'index',
        intersect: false
      },
      plugins: {
        legend: {
          position: 'top',
          align: 'end',
          labels: {
            color: '#344054',
            font: { family: 'Plus Jakarta Sans', size: 12, weight: 600 },
            usePointStyle: true,
            boxWidth: 8,
            padding: 16
          }
        },
        tooltip: {
          backgroundColor: '#101828',
          titleColor: '#FFFFFF',
          bodyColor: '#F2F4F7',
          borderColor: '#1D2939',
          borderWidth: 1,
          padding: 12,
          boxPadding: 6,
          usePointStyle: true,
          cornerRadius: 8,
          titleFont: { family: 'Plus Jakarta Sans', size: 12, weight: 700 },
          bodyFont: { family: 'Plus Jakarta Sans', size: 12, weight: 500 },
          callbacks: {
            label: function (context) {
              let label = context.dataset.label || '';
              if (label) label += ': ';
              label += 'Rp ' + Number(context.raw).toLocaleString('id-ID');
              return label;
            }
          }
        }
      },
      scales: {
        x: {
          grid: { color: '#F2F4F7', drawBorder: false },
          ticks: { color: '#475467', font: { family: 'Plus Jakarta Sans', size: 11, weight: 500 } }
        },
        y: {
          grid: { color: '#F2F4F7', drawBorder: false },
          ticks: {
            color: '#475467',
            font: { family: 'Plus Jakarta Sans', size: 11, weight: 500 },
            callback: function (value) {
              return formatRupiahShort(value);
            }
          }
        }
      }
    }
  });
}

// Filter button switch for Owner Dashboard
async function changeTimeRange(range, btnElement) {
  // Update button active state
  document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
  if (btnElement) btnElement.classList.add('active');

  try {
    const res = await fetch(`api/analytics.php?range=${range}`);
    const data = await res.json();
    if (data.success) {
      // Update KPIs
      const kpis = data.kpis.formatted;
      const elRev = document.getElementById('kpi-revenue');
      const elExp = document.getElementById('kpi-expense');
      const elProfit = document.getElementById('kpi-profit');
      const elMargin = document.getElementById('kpi-margin');
      const elRec = document.getElementById('kpi-receivables');

      if (elRev) elRev.innerText = kpis.revenue;
      if (elExp) elExp.innerText = kpis.expense;
      if (elProfit) elProfit.innerText = kpis.net_profit;
      if (elMargin) {
        elMargin.innerText = 'Margin ' + kpis.margin;
        elMargin.className = `badge-margin ${parseFloat(data.kpis.profit_margin) >= 30 ? 'good' : (parseFloat(data.kpis.profit_margin) > 15 ? 'warning' : 'bad')}`;
      }
      if (elRec) elRec.innerText = kpis.receivables;

      // Update Chart
      if (data.chart) {
        initCashflowChart(data.chart);
      }
    }
  } catch (err) {
    console.error('Failed to update analytics', err);
  }
}
