document.addEventListener('DOMContentLoaded', () => {
    const loadingEl = document.getElementById('dashboard-loading');
    const contentEl = document.getElementById('dashboard-content');
    const kpiTotalUsers = document.getElementById('kpi-total-users');
    const kpiPendingTxs = document.getElementById('kpi-pending-txs');
    const kpiAvgDaily = document.getElementById('kpi-avg-daily');
    const kpiBusiestMonth = document.getElementById('kpi-busiest-month');
    const ctxDestino = document.getElementById('chart-top-destino');
    const ctxOrigen = document.getElementById('chart-top-origen');
    const tableTopUsers = document.getElementById('table-top-users');

    let chartDestinoInstance = null;
    let chartOrigenInstance = null;

    const renderBarChart = (canvasCtx, chartInstance, chartData, chartLabel) => {
        if (!canvasCtx) return null;

        if (chartInstance) {
            chartInstance.destroy();
        }

        return new Chart(canvasCtx, {
            type: 'bar',
            data: {
                labels: chartData.labels,
                datasets: [{
                    label: chartLabel,
                    data: chartData.data,
                    backgroundColor: [
                        'rgba(0, 86, 179, 0.7)',
                        'rgba(0, 123, 255, 0.7)',
                        'rgba(23, 162, 184, 0.7)',
                        'rgba(40, 167, 69, 0.7)',
                        'rgba(255, 193, 7, 0.7)'
                    ],
                    borderColor: [
                        'rgba(0, 86, 179, 1)',
                        'rgba(0, 123, 255, 1)',
                        'rgba(23, 162, 184, 1)',
                        'rgba(40, 167, 69, 1)',
                        'rgba(255, 193, 7, 1)'
                    ],
                    borderWidth: 1
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: true,
                plugins: {
                    legend: {
                        display: false
                    }
                },
                scales: {
                    y: {
                        beginAtZero: true,
                        ticks: {
                            callback: function(value) {
                                if (value % 1 === 0) {
                                    return value;
                                }
                            }
                        }
                    }
                }
            }
        });
    };

    const loadDashboardData = async () => {
        try {
            const response = await fetch(`../api/?accion=getDashboardStats`);
            if (!response.ok) {
                throw new Error(`Error del servidor.`);
            }
            
            const result = await response.json();
            if (!result.success) {
                throw new Error(result.error || 'No se pudieron cargar las estadísticas.');
            }

            const stats = result.stats;

            if (stats.kpis) {
                kpiTotalUsers.textContent = stats.kpis.totalUsers || 0;
                kpiPendingTxs.textContent = stats.kpis.pendingTransactions || 0;
                kpiAvgDaily.textContent = stats.kpis.averageDaily || 0;
                kpiBusiestMonth.textContent = stats.kpis.busiestMonth || 'N/A';
            }

            if (stats.charts) {
                chartDestinoInstance = renderBarChart(ctxDestino, chartDestinoInstance, stats.charts.topDestino, 'Transacciones a Destino');
                chartOrigenInstance = renderBarChart(ctxOrigen, chartOrigenInstance, stats.charts.topOrigen, 'Transacciones desde Origen');
            }

            if (stats.tables && stats.tables.topUsers && tableTopUsers) {
                tableTopUsers.innerHTML = '';
                if (stats.tables.topUsers.length === 0) {
                    tableTopUsers.innerHTML = '<tr><td colspan="4" class="text-center">No hay datos de usuarios para mostrar.</td></tr>';
                } else {
                    stats.tables.topUsers.forEach(user => {
                        const row = `
                            <tr>
                                <td>${user.UserID}</td>
                                <td>${user.NombreCompleto || ''}</td>
                                <td>${user.Email || ''}</td>
                                <td>${user.TotalTransacciones}</td>
                            </tr>
                        `;
                        tableTopUsers.innerHTML += row;
                    });
                }
            }

            loadingEl.classList.add('d-none');
            contentEl.classList.remove('d-none');

        } catch (error) {
            console.error("Error al cargar el dashboard:", error);
            loadingEl.innerHTML = `<p class="text-center text-danger p-5">${error.message}</p>`;
        }
    };

    loadDashboardData();
});