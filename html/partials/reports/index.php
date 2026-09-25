<?php
/**
 * Reports Page — Date range selector, metrics cards, charts via ApexCharts
 */
require_once __DIR__ . '/../../../helpers/auth.php';
require_once __DIR__ . '/../../../helpers/db.php';

requireManager();
$restaurantId = currentRestaurantId();
$pdo = db();

$startDate = $_GET['start_date'] ?? date('Y-m-d', strtotime('-30 days'));
$endDate = $_GET['end_date'] ?? date('Y-m-d');
?>

<div id="reports-main">
    <div class="d-flex justify-content-between align-items-center mb-4" id="reports-header">
        <h4 class="mb-0" id="reports-title">Reports</h4>
    </div>

    <!-- Date Range -->
    <div class="card mb-3" id="reports-filter-card">
        <div class="card-body" id="reports-filter-body">
            <div class="row g-3 align-items-end" id="reports-filter-row">
                <div class="col-md-3" id="reports-start-group">
                    <label for="reports-start" class="form-label">From</label>
                    <input type="date" class="form-control" id="reports-start" name="start_date"
                           value="<?php echo htmlspecialchars($startDate); ?>">
                </div>
                <div class="col-md-3" id="reports-end-group">
                    <label for="reports-end" class="form-label">To</label>
                    <input type="date" class="form-control" id="reports-end" name="end_date"
                           value="<?php echo htmlspecialchars($endDate); ?>">
                </div>
                <div class="col-md-3" id="reports-apply-group">
                    <button class="btn btn-primary" id="reports-apply-btn"
                            hx-get="/partials/reports/index.php"
                            hx-include="#reports-start, #reports-end"
                            hx-target="#page-content">
                        Apply
                    </button>
                </div>
            </div>
        </div>
    </div>

    <!-- Metrics and Charts loaded via HTMX -->
    <div id="reports-data"
         hx-get="/partials/reports/data.php?start_date=<?php echo urlencode($startDate); ?>&end_date=<?php echo urlencode($endDate); ?>"
         hx-trigger="load"
         hx-swap="innerHTML">
        <div class="text-center py-5" id="reports-loading">
            <div class="spinner-border text-primary" role="status"></div>
            <p class="text-muted mt-2">Loading reports...</p>
        </div>
    </div>
</div>
