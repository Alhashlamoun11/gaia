/**
 * admin/assets/js/analytics.js
 * High-Performance Chart.js Analytics Engine for GAIA Admin Dashboard
 */

(function () {
    'use strict';

    var activeCharts = {};
    var currentRange = '30d';

    function loadChartJs(callback) {
        if (window.Chart) {
            callback();
            return;
        }
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.2/dist/chart.umd.min.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    function formatCurrency(val) {
        return '$' + Number(val).toLocaleString(undefined, { minimumFractionDigits: 2, maximumFractionDigits: 2 });
    }

    function formatNumber(val) {
        return Number(val).toLocaleString();
    }

    function destroyChart(id) {
        if (activeCharts[id]) {
            activeCharts[id].destroy();
            delete activeCharts[id];
        }
    }

    // -------------------------------------------------------------
    // 1. Hotels Overview Renderer
    // -------------------------------------------------------------
    function fetchAndRenderHotels() {
        var card = document.getElementById('hotels-card');
        if (!card) return;

        var url = '/admin/api/analytics.php?section=hotels&range=' + encodeURIComponent(currentRange);
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                // Update KPI metrics
                var occEl = card.querySelector('.kpi-occupancy');
                var revEl = card.querySelector('.kpi-revenue');
                var avgEl = card.querySelector('.kpi-avg-rate');
                var roomsEl = card.querySelector('.kpi-rooms');

                if (occEl) occEl.textContent = data.occupancyRate + '%';
                if (revEl) revEl.textContent = formatCurrency(data.totalRevenue);
                if (avgEl) avgEl.textContent = formatCurrency(data.avgRevenue);
                if (roomsEl) roomsEl.textContent = formatNumber(data.totalRooms) + ' (' + formatNumber(data.totalUnits) + ' Units)';

                // Render Top Hotels Bar Chart
                var canvas = document.getElementById('hotels-canvas');
                if (!canvas) return;

                destroyChart('hotels');

                var hotelNames = (data.topHotels || []).map(function (h) { return h.name; });
                var hotelBookings = (data.topHotels || []).map(function (h) { return h.bookings_count; });

                if (hotelNames.length === 0) {
                    hotelNames = ['No hotel bookings in this period'];
                    hotelBookings = [0];
                }

                activeCharts['hotels'] = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: hotelNames,
                        datasets: [{
                            label: 'Reservations',
                            data: hotelBookings,
                            backgroundColor: 'rgba(31, 111, 143, 0.85)',
                            borderColor: '#1f6f8f',
                            borderRadius: 6,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        indexAxis: 'y',
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ' ' + ctx.raw + ' bookings';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0 }
                            },
                            y: {
                                grid: { display: false }
                            }
                        }
                    }
                });
            })
            .catch(function (err) {
                console.error('Error fetching hotel analytics:', err);
            });
    }

    // -------------------------------------------------------------
    // 2. Payments Overview Renderer
    // -------------------------------------------------------------
    function fetchAndRenderPayments() {
        var card = document.getElementById('payments-card');
        if (!card) return;

        var url = '/admin/api/analytics.php?section=payments&range=' + encodeURIComponent(currentRange);
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var paidEl = card.querySelector('.kpi-paid');
                var pendEl = card.querySelector('.kpi-pending');
                var rateEl = card.querySelector('.kpi-success-rate');
                var refEl = card.querySelector('.kpi-refunded');

                if (paidEl) paidEl.textContent = formatCurrency(data.paidAmount);
                if (pendEl) pendEl.textContent = formatCurrency(data.pendingAmount);
                if (rateEl) rateEl.textContent = data.successRate + '%';
                if (refEl) refEl.textContent = formatCurrency(data.refundedAmount);

                var canvas = document.getElementById('payments-canvas');
                if (!canvas) return;

                destroyChart('payments');

                activeCharts['payments'] = new Chart(canvas.getContext('2d'), {
                    type: 'line',
                    data: {
                        labels: data.timeline.labels,
                        datasets: [
                            {
                                label: 'Collected Revenue ($)',
                                data: data.timeline.paid,
                                borderColor: '#10b981',
                                backgroundColor: 'rgba(16, 185, 129, 0.12)',
                                fill: true,
                                tension: 0.35,
                                borderWidth: 2,
                                pointRadius: 2
                            },
                            {
                                label: 'Pending ($)',
                                data: data.timeline.pending,
                                borderColor: '#f59e0b',
                                backgroundColor: 'transparent',
                                borderDash: [4, 4],
                                tension: 0.35,
                                borderWidth: 1.5,
                                pointRadius: 1
                            }
                        ]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: {
                                position: 'top',
                                labels: { boxWidth: 12, font: { size: 11 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ' ' + ctx.dataset.label + ': ' + formatCurrency(ctx.raw);
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { maxTicksLimit: 8, font: { size: 11 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: {
                                    callback: function (val) {
                                        return '$' + val;
                                    },
                                    font: { size: 11 }
                                }
                            }
                        }
                    }
                });
            })
            .catch(function (err) {
                console.error('Error fetching payment analytics:', err);
            });
    }

    // -------------------------------------------------------------
    // 3. Reservations Overview Renderer
    // -------------------------------------------------------------
    function fetchAndRenderReservations() {
        var card = document.getElementById('reservations-card');
        if (!card) return;

        var url = '/admin/api/analytics.php?section=reservations&range=' + encodeURIComponent(currentRange);
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var totalEl = card.querySelector('.kpi-total-res');
                var revEl = card.querySelector('.kpi-res-revenue');
                var canEl = card.querySelector('.kpi-cancel-rate');
                var avgEl = card.querySelector('.kpi-avg-booking');

                if (totalEl) totalEl.textContent = formatNumber(data.totalReservations);
                if (revEl) revEl.textContent = formatCurrency(data.totalRevenue);
                if (canEl) canEl.textContent = data.cancellationRate + '% (' + formatNumber(data.cancelledCount) + ')';
                if (avgEl) avgEl.textContent = formatCurrency(data.avgBookingValue);

                var canvas = document.getElementById('reservations-canvas');
                if (!canvas) return;

                destroyChart('reservations');

                // Service labels and values
                var serviceLabels = (data.serviceBreakdown || []).map(function (s) { return s.label; });
                var serviceCounts = (data.serviceBreakdown || []).map(function (s) { return s.count; });
                var serviceColors = ['#1b2a4a', '#1f6f8f', '#0ea5e9', '#10b981', '#f59e0b', '#8b5cf6'];

                if (serviceCounts.reduce(function(a, b){ return a + b; }, 0) === 0) {
                    serviceLabels = ['No bookings in period'];
                    serviceCounts = [1];
                    serviceColors = ['#e2e8f0'];
                }

                activeCharts['reservations'] = new Chart(canvas.getContext('2d'), {
                    type: 'doughnut',
                    data: {
                        labels: serviceLabels,
                        datasets: [{
                            data: serviceCounts,
                            backgroundColor: serviceColors,
                            borderWidth: 2,
                            borderColor: '#ffffff'
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        cutout: '68%',
                        plugins: {
                            legend: {
                                position: 'right',
                                labels: { boxWidth: 12, font: { size: 11 } }
                            },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ' ' + ctx.label + ': ' + ctx.raw + ' bookings';
                                    }
                                }
                            }
                        }
                    }
                });
            })
            .catch(function (err) {
                console.error('Error fetching reservation analytics:', err);
            });
    }

    // -------------------------------------------------------------
    // 4. New Users Overview Renderer
    // -------------------------------------------------------------
    function fetchAndRenderUsers() {
        var card = document.getElementById('users-card');
        if (!card) return;

        var url = '/admin/api/analytics.php?section=users&range=' + encodeURIComponent(currentRange);
        fetch(url)
            .then(function (res) { return res.json(); })
            .then(function (data) {
                var totalEl = card.querySelector('.kpi-total-users');
                var newEl = card.querySelector('.kpi-new-users');
                var activeEl = card.querySelector('.kpi-active-users');
                var inactEl = card.querySelector('.kpi-inactive-users');

                if (totalEl) totalEl.textContent = formatNumber(data.totalUsers);
                if (newEl) newEl.textContent = '+' + formatNumber(data.newUsers);
                if (activeEl) activeEl.textContent = formatNumber(data.activeUsers);
                if (inactEl) inactEl.textContent = formatNumber(data.inactiveUsers);

                var canvas = document.getElementById('users-canvas');
                if (!canvas) return;

                destroyChart('users');

                activeCharts['users'] = new Chart(canvas.getContext('2d'), {
                    type: 'bar',
                    data: {
                        labels: data.timeline.labels,
                        datasets: [{
                            label: 'New Registrations',
                            data: data.timeline.users,
                            backgroundColor: 'rgba(27, 42, 74, 0.85)',
                            borderColor: '#1b2a4a',
                            borderRadius: 4,
                            borderWidth: 1
                        }]
                    },
                    options: {
                        responsive: true,
                        maintainAspectRatio: false,
                        plugins: {
                            legend: { display: false },
                            tooltip: {
                                callbacks: {
                                    label: function (ctx) {
                                        return ' ' + ctx.raw + ' new users';
                                    }
                                }
                            }
                        },
                        scales: {
                            x: {
                                grid: { display: false },
                                ticks: { maxTicksLimit: 8, font: { size: 11 } }
                            },
                            y: {
                                beginAtZero: true,
                                grid: { color: '#f1f5f9' },
                                ticks: { precision: 0, font: { size: 11 } }
                            }
                        }
                    }
                });
            })
            .catch(function (err) {
                console.error('Error fetching user analytics:', err);
            });
    }

    // Refresh all cards
    function refreshAllAnalytics() {
        fetchAndRenderHotels();
        fetchAndRenderPayments();
        fetchAndRenderReservations();
        fetchAndRenderUsers();
    }

    // Export button handler
    function setupExportButtons() {
        document.querySelectorAll('.analytics-export-btn').forEach(function (btn) {
            btn.addEventListener('click', function (e) {
                e.preventDefault();
                var cardId = this.dataset.cardId || 'reservations';
                var type = cardId.replace('-card', '');
                window.location.href = '/admin/api/analytics.php?section=export&type=' + encodeURIComponent(type);
            });
        });
    }

    // Setup date range filter pills
    function setupRangeFilters() {
        var pills = document.querySelectorAll('.analytics-range-pill');
        pills.forEach(function (pill) {
            pill.addEventListener('click', function (e) {
                e.preventDefault();
                pills.forEach(function (p) { p.classList.remove('active'); });
                this.classList.add('active');
                currentRange = this.dataset.range || '30d';
                refreshAllAnalytics();
            });
        });
    }

    // Initialization on DOM Ready
    function init() {
        setupExportButtons();
        setupRangeFilters();
        loadChartJs(function () {
            refreshAllAnalytics();
        });
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }

})();
