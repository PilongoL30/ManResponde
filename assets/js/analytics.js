
document.addEventListener('DOMContentLoaded', () => {
    // Only run if we are on the analytics view
    if (!document.getElementById('reportsByCategoryChart')) return;

    const ctxCategory = document.getElementById('reportsByCategoryChart').getContext('2d');
    const ctxTrend = document.getElementById('reportsTrendChart').getContext('2d');
    const ctxResponse = document.getElementById('responseTimeChart').getContext('2d');

    let categoryChart, trendChart, responseChart;

    // Initialize Charts with default/empty data
    function initCharts() {
        // Pie Chart: Reports by Category
        categoryChart = new Chart(ctxCategory, {
            type: 'doughnut',
            data: {
                labels: [],
                datasets: [{
                    data: [],
                    backgroundColor: [
                        '#ef4444', // Red (Fire)
                        '#3b82f6', // Blue (Flood)
                        '#10b981', // Emerald (Ambulance)
                        '#f59e0b', // Amber (Other)
                        '#6366f1'  // Indigo (Tanod)
                    ],
                    borderWidth: 0
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: {
                    legend: { position: 'right' }
                }
            }
        });

        // Line Chart: Reports Trend
        trendChart = new Chart(ctxTrend, {
            type: 'line',
            data: {
                labels: [],
                datasets: [{
                    label: 'Reports',
                    data: [],
                    borderColor: '#3b82f6',
                    backgroundColor: 'rgba(59, 130, 246, 0.1)',
                    tension: 0.4,
                    fill: true
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });

        // Bar Chart: Response Time
        responseChart = new Chart(ctxResponse, {
            type: 'bar',
            data: {
                labels: ['Fire', 'Flood', 'Ambulance', 'Other', 'Tanod'],
                datasets: [{
                    label: 'Avg Response Time (min)',
                    data: [],
                    backgroundColor: '#10b981',
                    borderRadius: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                scales: {
                    y: { beginAtZero: true }
                }
            }
        });
    }

    // Fetch Analytics Data
    async function loadAnalyticsData(range = 'week') {
        try {
            // Show loading state (optional)
            
            const formData = new FormData();
            formData.append('api_action', 'get_analytics_data');
            formData.append('range', range);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData
            });

            const result = await response.json();
            if (result.success) {
                updateCharts(result.data);
                updateMetrics(result.data);
            } else {
                console.error('Analytics API error:', result.message);
                showToast('Failed to load analytics data', 'error');
            }

        } catch (error) {
            console.error('Error loading analytics:', error);
            showToast('Failed to load analytics data', 'error');
        }
    }

    /*
    function generateSimulatedData(range) {
        // ... (removed simulated data)
    }
    */

    function updateCharts(data) {
        // Update Pie Chart
        categoryChart.data.labels = data.categoryLabels;
        categoryChart.data.datasets[0].data = data.categoryData;
        categoryChart.update();

        // Update Trend Chart
        trendChart.data.labels = data.trendLabels;
        trendChart.data.datasets[0].data = data.trendData;
        trendChart.update();

        // Update Response Chart
        responseChart.data.datasets[0].data = data.responseTimeData;
        responseChart.update();
    }

    function updateMetrics(data) {
        const animate = (id, val, suffix = '') => {
            const el = document.getElementById(id);
            if (el) el.textContent = val + suffix;
        };

        animate('totalReportsCount', data.metrics.totalReports);
        animate('responseRate', data.metrics.responseRate, '%');
        animate('avgResponseTime', data.metrics.avgResponseTime, 'm');
        animate('activeResponders', data.metrics.activeResponders);
    }

    // Event Listeners
    const timeRange = document.getElementById('analyticsTimeRange');
    if (timeRange) {
        timeRange.addEventListener('change', (e) => {
            loadAnalyticsData(e.target.value);
        });
    }

    // Initialize
    initCharts();
    loadAnalyticsData('week');
});
