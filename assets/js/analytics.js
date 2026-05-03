
document.addEventListener('DOMContentLoaded', () => {
    // Only run if we are on the analytics view
    if (!document.getElementById('reportsByCategoryChart')) return;

    const ctxCategory = document.getElementById('reportsByCategoryChart').getContext('2d');
    const ctxTrend = document.getElementById('reportsTrendChart').getContext('2d');
    const ctxResponse = document.getElementById('responseTimeChart').getContext('2d');

    let categoryChart, trendChart, responseChart;
    
    // Client-side cache for instant range switching
    const analyticsCache = {};
    let currentAbortController = null;

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

    // Show subtle loading indicator on charts
    function showLoadingState() {
        const containers = document.querySelectorAll('.chart-container, [data-chart-loading]');
        containers.forEach(c => c.style.opacity = '0.5');
    }

    function hideLoadingState() {
        const containers = document.querySelectorAll('.chart-container, [data-chart-loading]');
        containers.forEach(c => c.style.opacity = '1');
    }

    // Event Listeners
    const timeRange = document.getElementById('analyticsTimeRange');
    const customRangeDiv = document.getElementById('customDateRange');
    const startDateInput = document.getElementById('analyticsStartDate');
    const endDateInput = document.getElementById('analyticsEndDate');

    if (timeRange) {
        timeRange.addEventListener('change', (e) => {
            const range = e.target.value;
            if (range === 'custom') {
                customRangeDiv.classList.remove('hidden');
                loadAnalyticsData('custom');
            } else {
                customRangeDiv.classList.add('hidden');
                loadAnalyticsData(range);
            }
        });
    }

    const onDateChange = () => {
        if (timeRange.value === 'custom') {
            loadAnalyticsData('custom');
        }
    };

    if (startDateInput) startDateInput.addEventListener('change', onDateChange);
    if (endDateInput) endDateInput.addEventListener('change', onDateChange);

    // Fetch Analytics Data (Upgraded to handle custom ranges)
    async function loadAnalyticsData(range = 'week') {
        const startDate = startDateInput ? startDateInput.value : '';
        const endDate = endDateInput ? endDateInput.value : '';
        
        // Cache key for custom range depends on the dates
        const cacheKey = range === 'custom' ? `custom_${startDate}_${endDate}` : range;

        // Check client-side cache first (instant)
        if (analyticsCache[cacheKey]) {
            const cached = analyticsCache[cacheKey];
            updateCharts(cached.data);
            updateMetrics(cached.data);
            return;
        }

        // Cancel any in-flight request
        if (currentAbortController) currentAbortController.abort();
        currentAbortController = new AbortController();

        // Check if we have preloaded data (only for default 'week')
        if (range === 'week' && window.__preloadedAnalytics) {
            const preloaded = window.__preloadedAnalytics;
            analyticsCache['week'] = preloaded;
            updateCharts(preloaded.data);
            updateMetrics(preloaded.data);
            delete window.__preloadedAnalytics;
            return;
        }

        showLoadingState();

        try {
            const formData = new FormData();
            formData.append('api_action', 'get_analytics_data');
            formData.append('range', range);
            if (range === 'custom') {
                formData.append('start_date', startDate);
                formData.append('end_date', endDate);
            }
            formData.append('force_refresh', 'false');
            
            const csrfToken = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
            if (csrfToken) formData.append('_csrf_token', csrfToken);

            const response = await fetch(window.location.href, {
                method: 'POST',
                body: formData,
                signal: currentAbortController.signal
            });

            const result = await response.json();
            if (result.success) {
                analyticsCache[cacheKey] = result;
                updateCharts(result.data);
                updateMetrics(result.data);
            } else {
                console.error('Analytics API error:', result.message);
            }
        } catch (error) {
            if (error.name !== 'AbortError') console.error('Error loading analytics:', error);
        } finally {
            hideLoadingState();
        }
    }

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
        if (Array.isArray(data.responseTimeLabels) && data.responseTimeLabels.length > 0) {
            responseChart.data.labels = data.responseTimeLabels;
        }
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
        animate('activeRespondersCount', data.metrics.activeResponders);

        animate('totalReportsTrend', data.metrics.totalReportsTrend, '%');
        animate('responseRateTrend', data.metrics.responseRateTrend, '%');
        animate('responseTimeTrend', data.metrics.responseTimeTrend, '%');
    }

    // Initialize
    initCharts();
    const initialRange = (timeRange && timeRange.value) ? timeRange.value : 'week';
    loadAnalyticsData(initialRange);

    // Pre-fetch other common ranges in the background (after initial load completes)
    setTimeout(() => {
        const ranges = ['day', 'week', 'month', 'year', 'all'];
        const otherRanges = ranges.filter(r => r !== initialRange);
        let delay = 0;
        otherRanges.forEach(r => {
            setTimeout(() => {
                if (!analyticsCache[r]) {
                    // Silent background prefetch
                    const fd = new FormData();
                    fd.append('api_action', 'get_analytics_data');
                    fd.append('range', r);
                    fd.append('force_refresh', 'false');
                    const csrf = document.querySelector('meta[name="csrf-token"]')?.getAttribute('content');
                    if (csrf) fd.append('_csrf_token', csrf);
                    fetch(window.location.href, { method: 'POST', body: fd })
                        .then(res => res.json())
                        .then(result => {
                            if (result.success) {
                                analyticsCache[r] = result;
                                console.log('[Analytics] Pre-cached range:', r);
                            }
                        })
                        .catch(() => {}); // Silent fail
                }
            }, delay);
            delay += 800; // Stagger requests to avoid overwhelming server
        });
    }, 2000);
});
