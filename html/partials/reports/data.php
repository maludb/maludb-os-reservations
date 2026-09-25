<?php
/**
 * Reports Data Endpoint — Returns metrics cards and chart data for ApexCharts
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireManager();
$restaurantId = currentRestaurantId();
require_once __DIR__ . '/../../../helpers/services/insight.php';

try {
    // Sets $startDate, $endDate, $totalReservations, $avgPartySize, $noshowRate, $cancelRate,
    // $dayLabels, $dayCounts, $hourLabels, $hourCovers, $sectionLabels, $sectionCounts,
    // $heatmapSeries, $sourceLabels, $sourceCounts, $newGuests, $returningGuests, ...
    extract(svc_insight_report($restaurantId, $_GET));
} catch (ServiceError $e) {
    echo '<div class="alert alert-danger" id="reports-data-error">' . htmlspecialchars($e->getMessage()) . '</div>';
    exit;
}
?>

<!-- Metrics Cards -->
<div class="row g-3 mb-4" id="reports-metrics-row">
    <div class="col-sm-6 col-xl-3" id="reports-metric-volume">
        <div class="card" id="reports-metric-volume-card">
            <div class="card-body text-center" id="reports-metric-volume-body">
                <h3 class="mb-0 text-primary"><?php echo $totalReservations; ?></h3>
                <small class="text-muted">Reservations</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3" id="reports-metric-party">
        <div class="card" id="reports-metric-party-card">
            <div class="card-body text-center" id="reports-metric-party-body">
                <h3 class="mb-0 text-success"><?php echo $avgPartySize; ?></h3>
                <small class="text-muted">Avg Party Size</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3" id="reports-metric-noshow">
        <div class="card" id="reports-metric-noshow-card">
            <div class="card-body text-center" id="reports-metric-noshow-body">
                <h3 class="mb-0 text-danger"><?php echo $noshowRate; ?>%</h3>
                <small class="text-muted">No-Show Rate</small>
            </div>
        </div>
    </div>
    <div class="col-sm-6 col-xl-3" id="reports-metric-cancel">
        <div class="card" id="reports-metric-cancel-card">
            <div class="card-body text-center" id="reports-metric-cancel-body">
                <h3 class="mb-0 text-warning"><?php echo $cancelRate; ?>%</h3>
                <small class="text-muted">Cancellation Rate</small>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 1 -->
<div class="row g-3 mb-3" id="reports-charts-row1">
    <div class="col-lg-8" id="reports-chart-daily-col">
        <div class="card" id="reports-chart-daily-card">
            <div class="card-header" id="reports-chart-daily-header">
                <h6 class="mb-0" id="reports-chart-daily-title">Reservations by Day</h6>
            </div>
            <div class="card-body" id="reports-chart-daily-body">
                <div id="chart-daily"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4" id="reports-chart-source-col">
        <div class="card" id="reports-chart-source-card">
            <div class="card-header" id="reports-chart-source-header">
                <h6 class="mb-0" id="reports-chart-source-title">Booking Source</h6>
            </div>
            <div class="card-body" id="reports-chart-source-body">
                <div id="chart-source"></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 2 -->
<div class="row g-3 mb-3" id="reports-charts-row2">
    <div class="col-lg-6" id="reports-chart-hours-col">
        <div class="card" id="reports-chart-hours-card">
            <div class="card-header" id="reports-chart-hours-header">
                <h6 class="mb-0" id="reports-chart-hours-title">Covers by Hour</h6>
            </div>
            <div class="card-body" id="reports-chart-hours-body">
                <div id="chart-hours"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-6" id="reports-chart-section-col">
        <div class="card" id="reports-chart-section-card">
            <div class="card-header" id="reports-chart-section-header">
                <h6 class="mb-0" id="reports-chart-section-title">Reservations by Section</h6>
            </div>
            <div class="card-body" id="reports-chart-section-body">
                <div id="chart-section"></div>
            </div>
        </div>
    </div>
</div>

<!-- Charts Row 3 -->
<div class="row g-3 mb-3" id="reports-charts-row3">
    <div class="col-lg-8" id="reports-chart-heatmap-col">
        <div class="card" id="reports-chart-heatmap-card">
            <div class="card-header" id="reports-chart-heatmap-header">
                <h6 class="mb-0" id="reports-chart-heatmap-title">Peak Hours Heatmap</h6>
            </div>
            <div class="card-body" id="reports-chart-heatmap-body">
                <div id="chart-heatmap"></div>
            </div>
        </div>
    </div>
    <div class="col-lg-4" id="reports-chart-guests-col">
        <div class="card" id="reports-chart-guests-card">
            <div class="card-header" id="reports-chart-guests-header">
                <h6 class="mb-0" id="reports-chart-guests-title">Guest Frequency</h6>
            </div>
            <div class="card-body" id="reports-chart-guests-body">
                <div id="chart-guests"></div>
            </div>
        </div>
    </div>
</div>

<script>
(function() {
    // 1. Reservations by Day (line)
    new ApexCharts(document.querySelector("#chart-daily"), {
        chart: { type: 'area', height: 300, toolbar: { show: false } },
        series: [{ name: 'Reservations', data: <?php echo json_encode(array_map('intval', $dayCounts)); ?> }],
        xaxis: { categories: <?php echo json_encode($dayLabels); ?>, labels: { rotate: -45, style: { fontSize: '10px' } } },
        colors: ['#3498db'],
        stroke: { curve: 'smooth', width: 2 },
        fill: { type: 'gradient', gradient: { shadeIntensity: 1, opacityFrom: 0.4, opacityTo: 0.1 } },
        dataLabels: { enabled: false },
        tooltip: { x: { format: 'MMM dd' } }
    }).render();

    // 2. Booking Source (donut)
    var sourceLabels = <?php echo json_encode($sourceLabels); ?>;
    var sourceCounts = <?php echo json_encode($sourceCounts); ?>;
    if (sourceCounts.length > 0) {
        new ApexCharts(document.querySelector("#chart-source"), {
            chart: { type: 'donut', height: 300 },
            series: sourceCounts,
            labels: sourceLabels,
            colors: ['#3498db', '#2ecc71', '#e74c3c', '#f39c12', '#9b59b6'],
            legend: { position: 'bottom' }
        }).render();
    } else {
        document.querySelector("#chart-source").innerHTML = '<p class="text-muted text-center py-4">No data</p>';
    }

    // 3. Covers by Hour (bar)
    new ApexCharts(document.querySelector("#chart-hours"), {
        chart: { type: 'bar', height: 300, toolbar: { show: false } },
        series: [{ name: 'Covers', data: <?php echo json_encode($hourCovers); ?> }],
        xaxis: { categories: <?php echo json_encode($hourLabels); ?> },
        colors: ['#2ecc71'],
        plotOptions: { bar: { borderRadius: 4 } },
        dataLabels: { enabled: false }
    }).render();

    // 4. Reservations by Section (horizontal bar)
    var sectionLabels = <?php echo json_encode($sectionLabels); ?>;
    var sectionCounts = <?php echo json_encode(array_map('intval', $sectionCounts)); ?>;
    if (sectionLabels.length > 0) {
        new ApexCharts(document.querySelector("#chart-section"), {
            chart: { type: 'bar', height: 300, toolbar: { show: false } },
            series: [{ name: 'Reservations', data: sectionCounts }],
            xaxis: { categories: sectionLabels },
            plotOptions: { bar: { horizontal: true, borderRadius: 4 } },
            colors: ['#f39c12'],
            dataLabels: { enabled: true }
        }).render();
    } else {
        document.querySelector("#chart-section").innerHTML = '<p class="text-muted text-center py-4">No data</p>';
    }

    // 5. Peak Hours Heatmap
    var heatmapData = <?php echo json_encode($heatmapSeries); ?>;
    if (heatmapData.length > 0) {
        new ApexCharts(document.querySelector("#chart-heatmap"), {
            chart: { type: 'heatmap', height: 300, toolbar: { show: false } },
            series: heatmapData,
            colors: ['#3498db'],
            dataLabels: { enabled: false },
            plotOptions: { heatmap: { shadeIntensity: 0.5, radius: 2 } }
        }).render();
    } else {
        document.querySelector("#chart-heatmap").innerHTML = '<p class="text-muted text-center py-4">No data</p>';
    }

    // 6. Guest Frequency (pie)
    var newG = <?php echo $newGuests; ?>;
    var retG = <?php echo $returningGuests; ?>;
    if (newG + retG > 0) {
        new ApexCharts(document.querySelector("#chart-guests"), {
            chart: { type: 'pie', height: 300 },
            series: [newG, retG],
            labels: ['New Guests', 'Returning Guests'],
            colors: ['#9b59b6', '#1abc9c'],
            legend: { position: 'bottom' }
        }).render();
    } else {
        document.querySelector("#chart-guests").innerHTML = '<p class="text-muted text-center py-4">No data</p>';
    }
})();
</script>
