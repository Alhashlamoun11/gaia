// admin/assets/js/analytics.js
// This script loads Chart.js (via CDN) and renders analytics cards based on data fetched from the API.

(function(){
    // Load Chart.js from CDN if not already present
    function loadChartJs(callback){
        if (window.Chart) { callback(); return; }
        var script = document.createElement('script');
        script.src = 'https://cdn.jsdelivr.net/npm/chart.js@4.4.0/dist/chart.umd.min.js';
        script.onload = callback;
        document.head.appendChild(script);
    }

    function initCard(card){
        var cardId = card.id.replace('-card','');
        var canvas = document.getElementById(cardId + '-canvas');
        var config = card.dataset.chartConfig ? JSON.parse(card.dataset.chartConfig) : null;
        if (!canvas || !config) return;
        new Chart(canvas.getContext('2d'), config);
    }

    function fetchAndRender(section){
        var url = 'admin/api/analytics.php?section=' + encodeURIComponent(section);
        // Add date range if present in UI (optional)
        var start = document.getElementById('date-start')?.value;
        var end   = document.getElementById('date-end')?.value;
        if (start) url += '&start=' + encodeURIComponent(start);
        if (end)   url += '&end=' + encodeURIComponent(end);
        fetch(url).then(r=>r.json()).then(data=>{
            // Dispatch to specific renderer based on section
            if(section==='hotels') renderHotels(data);
            else if(section==='payments') renderPayments(data);
            else if(section==='reservations') renderReservations(data);
            else if(section==='users') renderUsers(data);
        });
    }

    function renderHotels(data){
        var card = document.getElementById('hotels-card');
        if(!card) return;
        var config = {
            type: 'doughnut',
            data: {
                labels: ['Occupancy Rate'],
                datasets: [{
                    data: [data.occupancyRate, 100-data.occupancyRate],
                    backgroundColor: ['#4a90e2','#e0e0e0']
                }]
            },
            options: { responsive: true, plugins: { tooltip: { enabled: true } } }
        };
        card.dataset.chartConfig = JSON.stringify(config);
        // Also show average revenue
        var info = document.createElement('p');
        info.textContent = 'Avg. Rev/Res: $' + data.averageRevenuePerReservation;
        card.appendChild(info);
        initCard(card);
    }

    function renderPayments(data){
        var card = document.getElementById('payments-card');
        if(!card) return;
        var config = {
            type: 'line',
            data: {
                labels: data.trend.map(p=>p.d),
                datasets: [{
                    label: 'Paid',
                    data: data.trend.map(p=>parseFloat(p.paid)),
                    borderColor: '#28a745',
                    fill: false
                },{
                    label: 'Pending',
                    data: data.trend.map(p=>parseFloat(p.pending)),
                    borderColor: '#dc3545',
                    fill: false
                }]
            },
            options: { responsive: true }
        };
        card.dataset.chartConfig = JSON.stringify(config);
        var rateInfo = document.createElement('p');
        rateInfo.textContent = 'Success Rate: ' + data.successRate + '%';
        card.appendChild(rateInfo);
        initCard(card);
    }

    function renderReservations(data){
        var card = document.getElementById('reservations-card');
        if(!card) return;
        var config = {
            type: 'bar',
            data: {
                labels: ['Total','Cancelled'],
                datasets: [{
                    label: 'Bookings',
                    data: [data.totalBookings, Math.round(data.totalBookings * data.cancellationRate/100)],
                    backgroundColor: ['#007bff','#ff6b6b']
                }]
            },
            options: { responsive: true }
        };
        card.dataset.chartConfig = JSON.stringify(config);
        var avgInfo = document.createElement('p');
        avgInfo.textContent = 'Avg. Booking Value: $' + data.averageBookingValue;
        card.appendChild(avgInfo);
        initCard(card);
    }

    function renderUsers(data){
        var card = document.getElementById('users-card');
        if(!card) return;
        var info = document.createElement('p');
        info.textContent = 'New Users: ' + data.newUsers;
        card.appendChild(info);
    }

    // Initialize after DOM ready
    document.addEventListener('DOMContentLoaded', function(){
        loadChartJs(function(){
            // Render each card
            ['hotels','payments','reservations','users'].forEach(fetchAndRender);
        });
    });
})();
