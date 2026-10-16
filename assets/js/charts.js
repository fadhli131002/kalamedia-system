/**
 * Kalamedia Cashflow & Financial Visualizations (Chart.js)
 * Modern Fintech Style (Referencing Slide 2: Smooth Area Spline, Dual Gradient Waves, Dashed Grid)
 */

window.cashflowChartInstance = window.cashflowChartInstance || null;

function formatRupiahShort(value) {
  const num = Number(value) || 0;
  if (num === 0) return 'Rp 0';
  if (num >= 1000000000) {
    const val = (num / 1000000000).toFixed(1).replace(/\.0$/, '');
    return 'Rp ' + val + ' M';
  } else if (num >= 1000000) {
    const val = (num / 1000000).toFixed(1).replace(/\.0$/, '');
    return 'Rp ' + val + ' Jt';
  } else if (num >= 1000) {
    const val = (num / 1000).toFixed(0);
    return 'Rp ' + val + ' Rb';
  }
  return 'Rp ' + num.toLocaleString('id-ID');
}

function initCashflowChart(chartData) {
  const canvas = document.getElementById('cashflowChart');
  if (!canvas) return;

  if (typeof Chart === 'undefined') {
    console.warn('Chart.js library is not loaded yet.');
    return;
  }

  // Ensure canvas is properly sized and context is ready
  requestAnimationFrame(function () {
    const targetCanvas = document.getElementById('cashflowChart');
    if (!targetCanvas) return;
    const ctx = targetCanvas.getContext('2d');
    if (!ctx) return;

    // Destroy previous instance if any
    if (window.cashflowChartInstance) {
      window.cashflowChartInstance.destroy();
      window.cashflowChartInstance = null;
    }

    // Modern Gradient Palette based on Slide 2 reference:
    // Cyan/Sky-Blue for Inflow (Revenue)
    const revGradient = ctx.createLinearGradient(0, 0, 0, 260);
    revGradient.addColorStop(0, 'rgba(6, 182, 212, 0.36)');
    revGradient.addColorStop(0.65, 'rgba(6, 182, 212, 0.08)');
    revGradient.addColorStop(1, 'rgba(6, 182, 212, 0.00)');

    // Violet/Purple for Outflow (Expense)
    const expGradient = ctx.createLinearGradient(0, 0, 0, 260);
    expGradient.addColorStop(0, 'rgba(139, 92, 246, 0.30)');
    expGradient.addColorStop(0.65, 'rgba(139, 92, 246, 0.06)');
    expGradient.addColorStop(1, 'rgba(139, 92, 246, 0.00)');

    const revData = (chartData && Array.isArray(chartData.revenue)) ? chartData.revenue : [];
    const expData = (chartData && Array.isArray(chartData.expense)) ? chartData.expense : [];
    const labels = (chartData && Array.isArray(chartData.labels)) ? chartData.labels : [];

    const allValues = [...revData, ...expData].map(v => Number(v) || 0);
    const maxVal = allValues.length ? Math.max(...allValues) : 0;
    const suggestedMax = maxVal > 0 ? Math.ceil(maxVal * 1.25) : 2000000;

    window.cashflowChartInstance = new Chart(ctx, {
      type: 'line',
      data: {
        labels: labels,
        datasets: [
          {
            label: 'Total Revenue (Inflow)',
            data: revData,
            borderColor: '#06B6D4', // Vibrant Cyan / Sky Blue (Slide 2 reference)
            backgroundColor: revGradient,
            borderWidth: 2.8,
            fill: true,
            tension: 0.42, // Smooth Bezier spline
            pointBackgroundColor: '#FFFFFF',
            pointBorderColor: '#06B6D4',
            pointBorderWidth: 2.5,
            pointRadius: 2.5,
            pointHoverRadius: 6,
            pointHoverBackgroundColor: '#06B6D4',
            pointHoverBorderColor: '#FFFFFF',
            pointHoverBorderWidth: 2
          },
          {
            label: 'Total Expense (Outflow)',
            data: expData,
            borderColor: '#8B5CF6', // Vibrant Purple / Violet (Slide 2 reference)
            backgroundColor: expGradient,
            borderWidth: 2.8,
            fill: true,
            tension: 0.42, // Smooth Bezier spline
            pointBackgroundColor: '#FFFFFF',
            pointBorderColor: '#8B5CF6',
            pointBorderWidth: 2,
            pointRadius: 2.5,
            pointHoverRadius: 6,
            pointHoverBackgroundColor: '#8B5CF6',
            pointHoverBorderColor: '#FFFFFF',
            pointHoverBorderWidth: 2
          }
        ]
      },
      options: {
        responsive: true,
        maintainAspectRatio: false,
        animation: {
          duration: 750,
          easing: 'easeOutQuart'
        },
        interaction: {
          mode: 'index',
          intersect: false
        },
        plugins: {
          legend: {
            display: false // Use custom sleek HTML legend in panel header (no duplicate)
          },
          tooltip: {
            backgroundColor: '#FFFFFF',
            titleColor: '#101828',
            bodyColor: '#475467',
            borderColor: '#E4E7EC',
            borderWidth: 1,
            padding: 12,
            boxPadding: 6,
            usePointStyle: true,
            cornerRadius: 10,
            titleFont: {
              family: "'Plus Jakarta Sans', -apple-system, sans-serif",
              size: 12,
              weight: '700'
            },
            bodyFont: {
              family: "'Plus Jakarta Sans', -apple-system, sans-serif",
              size: 12,
              weight: '500'
            },
            callbacks: {
              label: function (context) {
                let label = context.dataset.label || '';
                if (label) label += ': ';
                const val = Number(context.raw) || 0;
                label += 'Rp ' + val.toLocaleString('id-ID');
                return label;
              }
            }
          }
        },
        scales: {
          x: {
            grid: {
              display: false,
              drawBorder: false
            },
            ticks: {
              color: '#94A3B8',
              font: {
                family: "'Plus Jakarta Sans', -apple-system, sans-serif",
                size: 11,
                weight: '600'
              },
              maxRotation: 0,
              minRotation: 0,
              padding: 8
            }
          },
          y: {
            beginAtZero: true,
            suggestedMin: 0,
            suggestedMax: suggestedMax,
            grid: {
              color: 'rgba(226, 232, 240, 0.7)',
              borderDash: [5, 5], // Dotted / dashed line matching Slide 2 reference
              drawBorder: false
            },
            ticks: {
              color: '#94A3B8',
              font: {
                family: "'Plus Jakarta Sans', -apple-system, sans-serif",
                size: 11,
                weight: '600'
              },
              maxTicksLimit: 6,
              padding: 10,
              callback: function (value) {
                return formatRupiahShort(value);
              }
            }
          }
        }
      }
    });
  });
}

// Toggle dataset visibility on custom legend pill click
window.toggleChartDataset = function (index, pillEl) {
  if (!window.cashflowChartInstance) return;
  const isHidden = window.cashflowChartInstance.isDatasetVisible(index);
  window.cashflowChartInstance.setDatasetVisibility(index, !isHidden);
  window.cashflowChartInstance.update();

  if (pillEl) {
    pillEl.style.opacity = isHidden ? '0.45' : '1';
    pillEl.style.textDecoration = isHidden ? 'line-through' : 'none';
  }
};

// Filter button switch for Owner Dashboard
async function changeTimeRange(range, btnElement) {
  // Update button active state
  document.querySelectorAll('.filter-btn').forEach(btn => btn.classList.remove('active'));
  if (btnElement) btnElement.classList.add('active');

  const baseUrl = window.__KALA_BASE_URL__ || '';
  const url = `${baseUrl}/api/analytics.php?range=${encodeURIComponent(range)}`;

  try {
    const res = await fetch(url);
    const data = await res.json();
    if (data.success) {
      // Update KPIs
      if (data.kpis && data.kpis.formatted) {
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
          const numMargin = parseFloat(data.kpis.profit_margin) || 0;
          elMargin.className = `badge-margin ${numMargin >= 30 ? 'good' : (numMargin > 15 ? 'warning' : 'bad')}`;
        }
        if (elRec) elRec.innerText = kpis.receivables;
      }

      // Update Chart with new time range data
      if (data.chart) {
        initCashflowChart(data.chart);
      }
    }
  } catch (err) {
    console.error('Failed to update analytics', err);
  }
}
