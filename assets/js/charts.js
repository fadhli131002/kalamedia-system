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

  // Gradients for Bankio finance theme
  const revGradient = ctx.createLinearGradient(0, 0, 0, 300);
  revGradient.addColorStop(0, 'rgba(93, 118, 245, 0.22)');
  revGradient.addColorStop(1, 'rgba(93, 118, 245, 0.00)');

  const expGradient = ctx.createLinearGradient(0, 0, 0, 300);
  expGradient.addColorStop(0, 'rgba(137, 50, 224, 0.18)');
  expGradient.addColorStop(1, 'rgba(137, 50, 224, 0.00)');

  cashflowChartInstance = new Chart(ctx, {
    type: 'line',
    data: {
      labels: chartData.labels,
      datasets: [
        {
          label: 'Total Revenue (Uang Masuk)',
          data: chartData.revenue,
          borderColor: '#5D76F5',
          backgroundColor: revGradient,
          borderWidth: 2.5,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#5D76F5',
          pointBorderColor: '#FFFFFF',
          pointBorderWidth: 2,
          pointRadius: 4.5,
          pointHoverRadius: 6.5
        },
        {
          label: 'Total Expense (Uang Keluar)',
          data: chartData.expense,
          borderColor: '#8932E0',
          backgroundColor: expGradient,
          borderWidth: 2.5,
          fill: true,
          tension: 0.4,
          pointBackgroundColor: '#8932E0',
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
            color: '#6E7485',
            font: { family: "'SF Pro Rounded', 'Plus Jakarta Sans', sans-serif", size: 12, weight: 600 },
            usePointStyle: true,
            boxWidth: 8,
            padding: 16
          }
        },
        tooltip: {
          backgroundColor: '#FFFFFF',
          titleColor: '#000000',
          bodyColor: '#6E7485',
          borderColor: '#ECEEF5',
          borderWidth: 1,
          padding: 12,
          boxPadding: 6,
          usePointStyle: true,
          cornerRadius: 12,
          titleFont: { family: "'SF Pro Rounded', 'Plus Jakarta Sans', sans-serif", size: 12, weight: 700 },
          bodyFont: { family: "'SF Pro Rounded', 'Plus Jakarta Sans', sans-serif", size: 12, weight: 500 },
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
          grid: { color: '#F8F9FC', drawBorder: false },
          ticks: {
            color: '#949AB1',
            font: { family: "'SF Pro Rounded', 'Plus Jakarta Sans', sans-serif", size: 10, weight: 600 },
            maxRotation: 0,
            minRotation: 0,
            autoSkip: true,
            maxTicksLimit: 6
          }
        },
        y: {
          grid: { color: '#ECEEF5', drawBorder: false },
          ticks: {
            color: '#949AB1',
            font: { family: "'SF Pro Rounded', 'Plus Jakarta Sans', sans-serif", size: 11, weight: 600 },
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
