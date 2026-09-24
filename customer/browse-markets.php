<?php
/**
 * MarketLink - Browse Farmers Markets & Map View
 * Accessible to Visitors and Customers
 */

require_once __DIR__ . '/../config/constants.php';
require_once __DIR__ . '/../config/db.php';
require_once __DIR__ . '/../includes/functions.php';

$active_nav = 'markets';
$page_title = 'Find Farmers Markets';

$search_q = sanitize_input($_GET['q'] ?? '');
$filter_day = sanitize_input($_GET['day'] ?? '');
$selected_market_id = (int)($_GET['market_id'] ?? $_GET['market'] ?? 0);

// Query markets with attending farmers count
$sql = "SELECT m.*, 
        (SELECT COUNT(*) FROM market_farmers mf 
         JOIN users u ON mf.farmer_id = u.user_id 
         WHERE mf.market_id = m.market_id AND u.status = 'active') as farmer_count 
        FROM markets m WHERE 1=1";
$params = [];

if (!empty($search_q)) {
    $sql .= " AND (m.market_name LIKE :q1 OR m.address LIKE :q2)";
    $params[':q1'] = '%' . $search_q . '%';
    $params[':q2'] = '%' . $search_q . '%';
}
if (!empty($filter_day)) {
    $sql .= " AND m.operating_days LIKE :day";
    $params[':day'] = '%' . $filter_day . '%';
}
$sql .= " ORDER BY m.market_name ASC";

$markets = [];
try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $markets = $stmt->fetchAll();
} catch (PDOException $e) {
    error_log("Browse markets query error: " . $e->getMessage());
}

// If a specific market is selected, fetch its attending farmers
$market_details = null;
$attending_farmers = [];
if ($selected_market_id > 0) {
    try {
        $m_stmt = $pdo->prepare("SELECT * FROM markets WHERE market_id = :mid LIMIT 1");
        $m_stmt->execute([':mid' => $selected_market_id]);
        $market_details = $m_stmt->fetch();

        if ($market_details) {
            $f_stmt = $pdo->prepare("SELECT u.user_id, u.name, u.phone, fp.stall_name, fp.address, fp.operating_days, 
                                     fp.pickup_window_start, fp.pickup_window_end, fp.latitude, fp.longitude,
                                     (SELECT COUNT(*) FROM products WHERE farmer_id = u.user_id AND is_sold_out = 0) as product_count,
                                     (SELECT COALESCE(AVG(rating), 5) FROM reviews WHERE farmer_id = u.user_id) as avg_rating 
                                     FROM market_farmers mf 
                                     JOIN users u ON mf.farmer_id = u.user_id 
                                     LEFT JOIN farmer_profiles fp ON u.user_id = fp.farmer_id 
                                     WHERE mf.market_id = :mid AND u.status = 'active'");
            $f_stmt->execute([':mid' => $selected_market_id]);
            $attending_farmers = $f_stmt->fetchAll();
        }
    } catch (PDOException $e) {
        error_log("Fetch market farmers error: " . $e->getMessage());
    }
}

// Prepare GeoJSON/JavaScript dataset for Leaflet Map
$map_markers = [];
foreach ($markets as $m) {
    if (!empty($m['latitude']) && !empty($m['longitude'])) {
        $map_markers[] = [
            'id' => (int)$m['market_id'],
            'title' => $m['market_name'],
            'address' => $m['address'],
            'days' => $m['operating_days'],
            'timings' => $m['timings'],
            'farmers' => (int)$m['farmer_count'],
            'lat' => (float)$m['latitude'],
            'lng' => (float)$m['longitude'],
            'url' => BASE_URL . 'customer/browse-markets.php?market_id=' . $m['market_id']
        ];
    }
}

require_once __DIR__ . '/../includes/header.php';
?>

<div class="container py-4">
    <div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
        <div>
            <h1 class="h3 mb-1"><i class="bi bi-geo-alt-fill text-primary me-2"></i>Find Local Farmers Markets</h1>
            <p class="text-muted small mb-0">Discover weekend market plazas, operating timings, and attending farm stalls</p>
        </div>
        <div>
            <a href="<?= BASE_URL ?>customer/browse-products.php" class="btn btn-primary btn-sm">
                <i class="bi bi-basket me-1"></i> Browse All Harvest
            </a>
        </div>
    </div>

    <!-- Search and Day Filters -->
    <div class="card shadow-sm border-0 mb-4">
        <div class="card-body p-3">
            <form action="<?= BASE_URL ?>customer/browse-markets.php" method="GET" class="row g-2 align-items-center">
                <div class="col-md-6">
                    <div class="input-group">
                        <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                        <input type="text" name="q" class="form-control" placeholder="Search markets by name or location..." value="<?= e($search_q) ?>">
                    </div>
                </div>
                <div class="col-md-4">
                    <select name="day" class="form-select" onchange="this.form.submit()">
                        <option value="">All Operating Days</option>
                        <option value="Sat" <?= $filter_day === 'Sat' ? 'selected' : '' ?>>Saturday Markets</option>
                        <option value="Sun" <?= $filter_day === 'Sun' ? 'selected' : '' ?>>Sunday Markets</option>
                        <option value="Fri" <?= $filter_day === 'Fri' ? 'selected' : '' ?>>Friday Markets</option>
                    </select>
                </div>
                <div class="col-md-2 d-flex gap-2">
                    <button type="submit" class="btn btn-primary btn-sm flex-grow-1">Filter</button>
                    <?php if (!empty($search_q) || !empty($filter_day) || $selected_market_id > 0): ?>
                        <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-outline-secondary btn-sm">Reset</a>
                    <?php endif; ?>
                </div>
            </form>
        </div>
    </div>

    <!-- Interactive Map View -->
    <div class="card shadow-sm border-0 mb-4 overflow-hidden">
        <div class="card-header bg-white py-3 d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6"><i class="bi bi-map text-primary me-2"></i>Interactive OpenStreetMap Market Directory</h5>
            <span class="badge bg-success-subtle text-success"><i class="bi bi-pin-map me-1"></i> <?= count($map_markers) ?> Markets Mapped</span>
        </div>
        <div class="card-body p-0">
            <div id="marketsMap" style="height: 380px; width: 100%;"></div>
        </div>
    </div>

    <!-- Selected Market Detail or Markets Grid -->
    <?php if ($market_details): ?>
        <!-- Specific Market Farmers Showcase -->
        <div class="card shadow-sm border-0 mb-4">
            <div class="card-header bg-primary text-white py-3 d-flex justify-content-between align-items-center">
                <div>
                    <h5 class="card-title mb-0 fs-6"><i class="bi bi-shop me-2"></i><?= e($market_details['market_name']) ?> — Attending Stalls</h5>
                    <small class="text-white-50"><i class="bi bi-geo-alt me-1"></i><?= e($market_details['address']) ?> &bull; <?= e($market_details['operating_days']) ?> (<?= e($market_details['timings']) ?>)</small>
                </div>
                <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-light btn-sm">
                    <i class="bi bi-x-lg me-1"></i> View All Markets
                </a>
            </div>
            <div class="card-body p-4">
                <?php if (!empty($attending_farmers)): ?>
                    <div class="row g-3">
                        <?php foreach ($attending_farmers as $f): ?>
                            <div class="col-md-6 col-lg-4">
                                <div class="card h-100 border p-3 card-hover bg-white">
                                    <div class="d-flex align-items-start gap-3 mb-2">
                                        <div class="p-3 rounded-circle bg-primary-subtle text-primary fs-4">
                                            <i class="bi bi-shop"></i>
                                        </div>
                                        <div>
                                            <h6 class="fw-bold mb-1"><?= e($f['stall_name'] ?: $f['name']) ?></h6>
                                            <div class="small text-muted mb-1"><i class="bi bi-person me-1"></i><?= e($f['name']) ?></div>
                                            <div class="text-warning small">
                                                <i class="bi bi-star-fill"></i> <?= number_format($f['avg_rating'], 1) ?>
                                                <span class="text-muted ms-1">(<?= $f['product_count'] ?> products live)</span>
                                            </div>
                                        </div>
                                    </div>
                                    <div class="small text-muted mb-3 border-top pt-2">
                                        <div><i class="bi bi-calendar2-check text-primary me-1"></i> Days: <strong><?= e($f['operating_days'] ?: 'Weekend') ?></strong></div>
                                        <div><i class="bi bi-clock text-primary me-1"></i> Pickup: <strong><?= e($f['pickup_window_start'] ?? '08:00') ?> - <?= e($f['pickup_window_end'] ?? '14:00') ?></strong></div>
                                    </div>
                                    <div class="mt-auto d-flex gap-2">
                                        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $f['user_id'] ?>" class="btn btn-outline-primary btn-sm flex-grow-1">
                                            <i class="bi bi-shop me-1"></i> Stall Profile
                                        </a>
                                        <a href="<?= BASE_URL ?>customer/browse-products.php?farmer_id=<?= $f['user_id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                                            <i class="bi bi-basket me-1"></i> View Stock
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="text-center py-4 text-muted">
                        <i class="bi bi-shop-window fs-1 text-secondary-subtle d-block mb-2"></i>
                        <h6>No farmer stalls registered for this market yet</h6>
                        <p class="small text-muted mb-0">Check back soon as local growers update their weekend attendance.</p>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    <?php endif; ?>

    <!-- Markets Directory Grid -->
    <div class="row g-4">
        <div class="col-12">
            <h5 class="fw-bold mb-3"><i class="bi bi-grid-fill text-primary me-2"></i>All Registered Markets (<?= count($markets) ?>)</h5>
        </div>
        <?php if (!empty($markets)): ?>
            <?php foreach ($markets as $m): ?>
                <div class="col-md-6 col-lg-4">
                    <div class="card h-100 shadow-sm border-0 card-hover bg-white overflow-hidden">
                        <div class="card-body p-4 d-flex flex-column">
                            <div class="d-flex justify-content-between align-items-start mb-2">
                                <span class="badge bg-primary-subtle text-primary fw-semibold px-2 py-1">
                                    <i class="bi bi-calendar3 me-1"></i> <?= e($m['operating_days']) ?>
                                </span>
                                <span class="badge bg-light text-dark border">
                                    <i class="bi bi-shop me-1 text-primary"></i> <?= $m['farmer_count'] ?> Stalls
                                </span>
                            </div>
                            <h5 class="fw-bold mb-2"><?= e($m['market_name']) ?></h5>
                            <p class="small text-muted mb-3 flex-grow-1">
                                <i class="bi bi-geo-alt-fill text-danger me-1"></i> <?= e($m['address']) ?>
                            </p>
                            <div class="p-2 bg-light rounded small text-muted mb-3">
                                <i class="bi bi-clock me-1 text-primary"></i> <strong>Timings:</strong> <?= e($m['timings']) ?>
                            </div>
                            <div class="d-flex gap-2">
                                <a href="<?= BASE_URL ?>customer/browse-markets.php?market_id=<?= $m['market_id'] ?>" class="btn btn-primary btn-sm flex-grow-1">
                                    <i class="bi bi-shop me-1"></i> View Attending Farmers
                                </a>
                                <button type="button" class="btn btn-outline-secondary btn-sm" onclick="focusMap(<?= $m['latitude'] ?? 31.5204 ?>, <?= $m['longitude'] ?? 74.3587 ?>, '<?= e(addslashes($m['market_name'])) ?>')">
                                    <i class="bi bi-pin-map"></i>
                                </button>
                            </div>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        <?php else: ?>
            <div class="col-12 text-center py-5 text-muted">
                <i class="bi bi-geo-alt fs-1 text-secondary-subtle d-block mb-2"></i>
                <h6>No farmers markets matched your search</h6>
                <a href="<?= BASE_URL ?>customer/browse-markets.php" class="btn btn-primary btn-sm mt-2">Clear Filters</a>
            </div>
        <?php endif; ?>
    </div>
</div>

<!-- Leaflet Map Setup -->
<script>
let map;
const markersData = <?= json_encode($map_markers) ?>;

document.addEventListener('DOMContentLoaded', function () {
    const defaultCenter = [31.5204, 74.3587];
    map = L.map('marketsMap').setView(defaultCenter, 12);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    const bounds = [];

    markersData.forEach(function (item) {
        const marker = L.marker([item.lat, item.lng]).addTo(map);
        bounds.push([item.lat, item.lng]);

        const popupContent = `
            <div style="font-family:'Inter',sans-serif; min-width:180px;">
                <h6 style="margin-bottom:4px; font-weight:700; color:#3D8B47;">${item.title}</h6>
                <p style="font-size:12px; margin-bottom:6px; color:#6E6A62;">${item.address}</p>
                <div style="font-size:11px; margin-bottom:8px;">
                    <strong>Days:</strong> ${item.days}<br>
                    <strong>Hours:</strong> ${item.timings}<br>
                    <strong>Farmers:</strong> ${item.farmers} active stalls
                </div>
                <a href="${item.url}" class="btn btn-sm btn-primary" style="font-size:11px; padding:3px 8px; color:#fff; text-decoration:none; display:inline-block; border-radius:4px;">
                    Explore Stalls
                </a>
            </div>
        `;
        marker.bindPopup(popupContent);
    });

    if (bounds.length > 0) {
        map.fitBounds(bounds, { padding: [40, 40] });
    }
});

function focusMap(lat, lng, name) {
    if (map && lat && lng) {
        map.setView([lat, lng], 15);
        window.scrollTo({ top: document.getElementById('marketsMap').offsetTop - 100, behavior: 'smooth' });
    }
}
</script>

<?php require_once __DIR__ . '/../includes/footer.php'; ?>
