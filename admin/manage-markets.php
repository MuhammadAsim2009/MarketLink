<?php
/**
 * MarketLink - Admin Farmers Market Management (CRUD & Map Geocoding)
 * SaaS Redesign with Sidebar Layout & Interactive Geospatial Overview
 */

$required_role = 'admin';
require_once __DIR__ . '/../includes/auth-check.php';

$page_title = 'Manage Farmers Markets';
$active_nav = 'markets';
$action = $_GET['action'] ?? 'list';
$edit_id = (int)($_GET['id'] ?? 0);
$errors = [];

// Handle POST actions (Create, Update, Delete)
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission.');
        redirect(BASE_URL . 'admin/manage-markets.php');
    }

    $form_action = sanitize_input($_POST['form_action'] ?? '');

    // 1. SAVE MARKET (Add / Edit)
    if ($form_action === 'save_market') {
        $mid = (int)($_POST['market_id'] ?? 0);
        $market_name = sanitize_input($_POST['market_name'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        if (!empty($_POST['operating_days']) && is_array($_POST['operating_days'])) {
            $operating_days = implode(',', array_map('sanitize_input', $_POST['operating_days']));
        } elseif (!empty($_POST['operating_days']) && is_string($_POST['operating_days'])) {
            $operating_days = sanitize_input($_POST['operating_days']);
        } else {
            $operating_days = 'Sat,Sun';
        }
        $timings = sanitize_input($_POST['timings'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : 27.5590;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : 68.2120;

        if (empty($market_name)) {
            $errors['market_name'] = 'Market name is required.';
        }
        if (empty($address)) {
            $errors['address'] = 'Market address is required.';
        }

        if (empty($errors)) {
            try {
                if ($mid > 0) {
                    $stmt = $pdo->prepare("UPDATE markets SET market_name = :name, address = :address, operating_days = :days, timings = :timings, latitude = :lat, longitude = :lng WHERE market_id = :mid");
                    $stmt->execute([
                        ':name' => $market_name,
                        ':address' => $address,
                        ':days' => $operating_days,
                        ':timings' => $timings,
                        ':lat' => $latitude,
                        ':lng' => $longitude,
                        ':mid' => $mid
                    ]);
                    set_flash('success', 'Market "' . $market_name . '" updated successfully.');
                } else {
                    $stmt = $pdo->prepare("INSERT INTO markets (market_name, address, operating_days, timings, latitude, longitude) VALUES (:name, :address, :days, :timings, :lat, :lng)");
                    $stmt->execute([
                        ':name' => $market_name,
                        ':address' => $address,
                        ':days' => $operating_days,
                        ':timings' => $timings,
                        ':lat' => $latitude,
                        ':lng' => $longitude
                    ]);
                    set_flash('success', 'New market plaza "' . $market_name . '" added to the directory.');
                }
                redirect(BASE_URL . 'admin/manage-markets.php');
            } catch (PDOException $e) {
                error_log("Market save error: " . $e->getMessage());
                $errors['general'] = 'Database error while saving market.';
            }
        }
    }

    // 2. DELETE MARKET
    if ($form_action === 'delete_market') {
        $mid = (int)($_POST['market_id'] ?? 0);
        if ($mid > 0) {
            try {
                $del = $pdo->prepare("DELETE FROM markets WHERE market_id = :mid");
                $del->execute([':mid' => $mid]);
                set_flash('success', 'Market removed from system.');
            } catch (PDOException $e) {
                error_log("Delete market error: " . $e->getMessage());
                set_flash('danger', 'Failed to delete market.');
            }
        }
        redirect(BASE_URL . 'admin/manage-markets.php');
    }
}

// Fetch edit market if requested
$edit_market = null;
if ($action === 'edit' && $edit_id > 0) {
    try {
        $stmt = $pdo->prepare("SELECT * FROM markets WHERE market_id = :mid LIMIT 1");
        $stmt->execute([':mid' => $edit_id]);
        $edit_market = $stmt->fetch();
    } catch (PDOException $e) {}
}

// Fetch all markets
$markets = [];
$total_stalls_participating = 0;
try {
    $stmt = $pdo->query("SELECT m.*, 
                        (SELECT COUNT(*) FROM market_farmers WHERE market_id = m.market_id) as stall_count 
                        FROM markets m ORDER BY m.market_name ASC");
    $markets = $stmt->fetchAll();
    foreach ($markets as $m) {
        $total_stalls_participating += (int)$m['stall_count'];
    }
} catch (PDOException $e) {
    error_log("Fetch markets error: " . $e->getMessage());
}

require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header & Action Controls -->
<div class="d-flex flex-wrap justify-content-between align-items-center gap-3 mb-4 pb-2 border-bottom">
    <div>
        <div class="d-flex align-items-center gap-2 mb-1">
            <span class="badge badge-primary px-2 py-1"><i class="bi bi-geo-alt-fill me-1"></i> Locations & Plazas</span>
            <span class="badge badge-neutral px-2 py-1"><?= count($markets) ?> Active Markets</span>
        </div>
        <h1 class="h3 fw-bold mb-1">Farmers Markets Management</h1>
        <p class="text-muted small mb-0">Configure physical farmers market venues, operational timings, and geospatial coordinates</p>
    </div>

    <div class="d-flex align-items-center gap-2">
        <?php if ($action === 'list'): ?>
            <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm">
                <i class="bi bi-plus-circle me-1"></i> Add New Market
            </a>
            <a href="<?= BASE_URL ?>customer/browse-markets.php" target="_blank" class="btn btn-outline-primary btn-sm">
                <i class="bi bi-box-arrow-up-right me-1"></i> Public Directory
            </a>
        <?php else: ?>
            <a href="<?= BASE_URL ?>admin/manage-markets.php" class="btn btn-outline-secondary btn-sm">
                <i class="bi bi-arrow-left me-1"></i> Back to Market List
            </a>
        <?php endif; ?>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger py-2 small mb-3"><?= e($errors['general']) ?></div>
<?php endif; ?>

<?php if ($action === 'add' || $action === 'edit'): ?>
    <!-- Add / Edit Market Form -->
    <div class="card border shadow-xs mb-4">
        <div class="card-header bg-white py-3 border-bottom">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi <?= $action === 'add' ? 'bi-plus-circle text-primary' : 'bi-pencil-square text-accent' ?> me-2"></i>
                <?= $action === 'add' ? 'Create New Farmers Market Venue' : 'Edit Market: ' . e($edit_market['market_name']) ?>
            </h5>
        </div>
        <div class="card-body p-4">
            <form action="<?= BASE_URL ?>admin/manage-markets.php" method="POST" novalidate>
                <?= csrf_field() ?>
                <input type="hidden" name="form_action" value="save_market">
                <input type="hidden" name="market_id" value="<?= $edit_market['market_id'] ?? 0 ?>">

                <div class="row g-4">
                    <div class="col-lg-6">
                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="market_name">Market Plaza Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['market_name']) ? 'is-invalid' : '' ?>" id="market_name" name="market_name" value="<?= e($edit_market['market_name'] ?? '') ?>" required placeholder="e.g. Model Town Community Farmers Market">
                            <?php if (isset($errors['market_name'])): ?><div class="invalid-feedback"><?= e($errors['market_name']) ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="address">Full Venue Address <span class="text-danger">*</span></label>
                            <textarea class="form-control <?= isset($errors['address']) ? 'is-invalid' : '' ?>" id="address" name="address" rows="3" required placeholder="e.g. Central Park Grounds, Block C, Model Town, Lahore"><?= e($edit_market['address'] ?? '') ?></textarea>
                            <?php if (isset($errors['address'])): ?><div class="invalid-feedback"><?= e($errors['address']) ?></div><?php endif; ?>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold d-block">
                                <i class="bi bi-calendar-week text-primary me-1"></i> Operating Days <span class="text-danger">*</span>
                            </label>
                            <div class="d-flex flex-wrap gap-3 p-2 px-3 rounded border bg-light">
                                <?php 
                                $all_days = [
                                    'Mon' => 'Monday',
                                    'Tue' => 'Tuesday',
                                    'Wed' => 'Wednesday',
                                    'Thu' => 'Thursday',
                                    'Fri' => 'Friday',
                                    'Sat' => 'Saturday',
                                    'Sun' => 'Sunday'
                                ];
                                $selected_days = !empty($edit_market['operating_days']) 
                                    ? array_map('trim', explode(',', $edit_market['operating_days'])) 
                                    : ['Sat', 'Sun'];
                                foreach ($all_days as $code => $label): 
                                ?>
                                    <div class="form-check m-0">
                                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="<?= $code ?>" id="day_<?= $code ?>" <?= in_array($code, $selected_days) ? 'checked' : '' ?>>
                                        <label class="form-check-label fw-medium small" for="day_<?= $code ?>" title="<?= $label ?>">
                                            <?= $code ?>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="mb-3">
                            <label class="form-label fw-semibold" for="timings">Market Timings</label>
                            <input type="text" class="form-control" id="timings" name="timings" value="<?= e($edit_market['timings'] ?? '07:00 AM - 02:00 PM') ?>" placeholder="e.g. 07:00 AM - 02:00 PM">
                        </div>
                    </div>

                    <!-- OpenStreetMap Location Picker -->
                    <div class="col-lg-6">
                        <div class="d-flex justify-content-between align-items-center mb-2">
                            <label class="form-label fw-semibold mb-0">
                                <i class="bi bi-pin-map-fill text-primary me-1"></i> Market Pin Location
                            </label>
                            <button type="button" id="btnDetectGPS" class="btn btn-xs btn-outline-primary py-0 px-2" style="font-size:.75rem;">
                                <i class="bi bi-crosshair me-1"></i> Use My Location
                            </button>
                        </div>

                        <!-- Search or paste Google Maps link -->
                        <div class="input-group input-group-sm mb-1">
                            <span class="input-group-text bg-white"><i class="bi bi-search text-muted"></i></span>
                            <input type="text" id="marketAddressSearch" class="form-control" placeholder="Search place, OR paste a Google Maps link…">
                            <button type="button" id="btnSearchAddress" class="btn btn-primary">
                                Locate
                            </button>
                        </div>
                        <p class="text-muted mb-2" style="font-size:0.7rem;"><i class="bi bi-info-circle me-1"></i>Paste a full Google Maps URL (e.g. maps.google.com/place/...), type an address to search, or drag the map pin.</p>

                        <!-- Detected Address Badge (Reverse Geocoding result) -->
                        <div id="detectedAddressBadge" class="d-none mb-2 p-2 rounded-2 border bg-success-subtle d-flex align-items-start gap-2" style="font-size:0.78rem;">
                            <i class="bi bi-geo-alt-fill text-success mt-1 flex-shrink-0"></i>
                            <div>
                                <span class="fw-semibold text-success d-block" style="font-size:0.7rem;">Detected Address</span>
                                <span id="detectedAddressText" class="text-dark"></span>
                                <button type="button" id="btnFillAddress" class="btn btn-xs btn-success mt-1 py-0 px-2" style="font-size:0.7rem;">
                                    <i class="bi bi-arrow-down-circle me-1"></i>Fill into Address Field
                                </button>
                            </div>
                        </div>

                        <div id="adminMarketMap" style="height: 240px; border-radius: var(--radius-sm); border: 1px solid var(--border-color, #dee2e6); margin-bottom: 0.8rem; overflow: hidden; position: relative;"></div>

                        <!-- Advanced: Collapsible raw coordinates -->
                        <div class="mb-1">
                            <a class="text-muted d-flex align-items-center gap-1" data-bs-toggle="collapse" href="#advancedCoordsAdmin" role="button" aria-expanded="false" style="font-size:0.75rem; text-decoration:none;">
                                <i class="bi bi-chevron-right" id="advCoordsChevron" style="transition:transform .2s;"></i>
                                <span>⚙️ Advanced: Fine-tune GPS coordinates</span>
                            </a>
                            <div class="collapse" id="advancedCoordsAdmin">
                                <div class="row g-2 align-items-center mt-1">
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold text-muted mb-1" for="latitude">Latitude</label>
                                        <input type="number" step="0.00000001" class="form-control form-control-sm font-monospace" id="latitude" name="latitude" value="<?= e($edit_market['latitude'] ?? '27.55900000') ?>">
                                    </div>
                                    <div class="col-5">
                                        <label class="form-label small fw-semibold text-muted mb-1" for="longitude">Longitude</label>
                                        <input type="number" step="0.00000001" class="form-control form-control-sm font-monospace" id="longitude" name="longitude" value="<?= e($edit_market['longitude'] ?? '68.21200000') ?>">
                                    </div>
                                    <div class="col-2 pt-4">
                                        <a id="previewMapsLink" href="https://www.google.com/maps/search/?api=1&query=<?= e($edit_market['latitude'] ?? '27.5590') ?>,<?= e($edit_market['longitude'] ?? '68.2120') ?>" target="_blank" rel="noopener noreferrer" class="btn btn-sm btn-outline-secondary w-100 p-1" title="Test in Google Maps">
                                            <i class="bi bi-box-arrow-up-right"></i>
                                        </a>
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- Compact GPS pill always visible -->
                        <div class="d-flex align-items-center gap-2 mt-2">
                            <span class="badge bg-light text-secondary border" style="font-size:0.7rem; font-family:monospace;" id="gpsPillAdmin">
                                <i class="bi bi-crosshair me-1"></i>
                                <span id="gpsPillText"><?= number_format((float)($edit_market['latitude'] ?? 27.5590), 4) ?>, <?= number_format((float)($edit_market['longitude'] ?? 68.2120), 4) ?></span>
                            </span>
                            <span class="text-muted" style="font-size:0.68rem;">← Auto-updates when pin moves</span>
                        </div>
                    </div>
                </div>

                <div class="d-flex justify-content-end gap-2 mt-4 pt-3 border-top">
                    <a href="<?= BASE_URL ?>admin/manage-markets.php" class="btn btn-outline-secondary">Cancel</a>
                    <button type="submit" class="btn btn-primary px-4">
                        <i class="bi bi-check-circle me-1"></i> <?= $action === 'add' ? 'Publish Market' : 'Save Changes' ?>
                    </button>
                </div>
            </form>
        </div>
    </div>

    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const latInput = document.getElementById('latitude');
        const lngInput = document.getElementById('longitude');
        const previewMapsLink = document.getElementById('previewMapsLink');
        const btnDetectGPS = document.getElementById('btnDetectGPS');
        const searchInput = document.getElementById('marketAddressSearch');
        const btnSearch = document.getElementById('btnSearchAddress');
        const detectedBadge = document.getElementById('detectedAddressBadge');
        const detectedText = document.getElementById('detectedAddressText');
        const btnFillAddress = document.getElementById('btnFillAddress');
        const gpsPillText = document.getElementById('gpsPillText');
        const addressField = document.getElementById('address');
        const advCollapseEl = document.getElementById('advancedCoordsAdmin');
        const advChevron = document.getElementById('advCoordsChevron');

        let initialLat = parseFloat(latInput.value) || 27.5590;
        let initialLng = parseFloat(lngInput.value) || 68.2120;
        let lastReverseGeoAddress = '';

        const map = L.map('adminMarketMap', {
            zoomControl: true,
            scrollWheelZoom: true
        }).setView([initialLat, initialLng], 14);
        L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
            attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
            maxZoom: 19
        }).addTo(map);
        // Force tile re-render after layout settles
        setTimeout(function() { map.invalidateSize(); }, 300);

        const marketPinHtml = `
            <div style="background: linear-gradient(135deg, #1e3a8a, #3b82f6); color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 2px solid #ffffff;">
                <i class="bi bi-geo-alt-fill" style="font-size: 16px;"></i>
            </div>
        `;
        const customIcon = L.divIcon({
            className: 'custom-admin-pin',
            html: marketPinHtml,
            iconSize: [32, 32],
            iconAnchor: [16, 32],
            popupAnchor: [0, -32]
        });

        let marker = L.marker([initialLat, initialLng], { draggable: true, icon: customIcon }).addTo(map);

        // ── Feature 6: Reverse Geocoding – fetch address for a coordinate ──
        function reverseGeocode(lat, lng) {
            fetch(`https://nominatim.openstreetmap.org/reverse?format=json&lat=${lat}&lon=${lng}&zoom=17&addressdetails=1`)
                .then(r => r.json())
                .then(data => {
                    if (data && data.display_name) {
                        lastReverseGeoAddress = data.display_name;
                        detectedText.textContent = data.display_name;
                        detectedBadge.classList.remove('d-none');
                    }
                })
                .catch(() => {}); // Silent – map still works without reverse geo
        }

        // ── Core: update lat/lng inputs, pill, and map preview link ──
        function updatePosition(lat, lng, pan = false) {
            latInput.value = parseFloat(lat).toFixed(8);
            lngInput.value = parseFloat(lng).toFixed(8);
            marker.setLatLng([lat, lng]);
            if (pan) map.setView([lat, lng], 15);
            if (previewMapsLink) {
                previewMapsLink.href = `https://www.google.com/maps/search/?api=1&query=${lat},${lng}`;
            }
            if (gpsPillText) {
                gpsPillText.textContent = `${parseFloat(lat).toFixed(4)}, ${parseFloat(lng).toFixed(4)}`;
            }
            // Trigger reverse geocoding after pin placement
            reverseGeocode(lat, lng);
        }

        // ── Fill-address button ──
        if (btnFillAddress && addressField) {
            btnFillAddress.addEventListener('click', function () {
                if (lastReverseGeoAddress) {
                    addressField.value = lastReverseGeoAddress;
                    detectedBadge.classList.add('d-none');
                }
            });
        }

        // ── Chevron animation for Advanced collapse ──
        if (advCollapseEl && advChevron) {
            advCollapseEl.addEventListener('show.bs.collapse', function () {
                advChevron.style.transform = 'rotate(90deg)';
            });
            advCollapseEl.addEventListener('hide.bs.collapse', function () {
                advChevron.style.transform = 'rotate(0deg)';
            });
        }

        marker.on('dragend', function (e) {
            const pos = marker.getLatLng();
            updatePosition(pos.lat, pos.lng);
        });

        map.on('click', function (e) {
            updatePosition(e.latlng.lat, e.latlng.lng);
        });

        latInput.addEventListener('input', function() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) updatePosition(lat, lng, true);
        });

        lngInput.addEventListener('input', function() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) updatePosition(lat, lng, true);
        });

        // ── Feature 4: GPS button ──
        if (btnDetectGPS) {
            btnDetectGPS.addEventListener('click', function() {
                if (!navigator.geolocation) {
                    customAlert({ title: 'GPS Not Supported', message: 'Geolocation is not supported by your browser.', type: 'warning' });
                    return;
                }
                btnDetectGPS.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span> Detecting...';
                navigator.geolocation.getCurrentPosition(
                    function(pos) {
                        btnDetectGPS.innerHTML = '<i class="bi bi-crosshair me-1"></i> Use My Location';
                        updatePosition(pos.coords.latitude, pos.coords.longitude, true);
                    },
                    function(err) {
                        btnDetectGPS.innerHTML = '<i class="bi bi-crosshair me-1"></i> Use My Location';
                        customAlert({ title: 'GPS Error', message: 'Could not detect location: ' + (err.message || 'Permission denied'), type: 'danger' });
                    },
                    { enableHighAccuracy: true, timeout: 8000 }
                );
            });
        }

        // ── Feature 3: Google Maps URL parser + raw coordinate detector ──
        function tryExtractGoogleMapsCoords(input) {
            if (!input) return null;
            const decoded = decodeURIComponent(input);

            // Pattern 1: Exact Place Pin (!3dlat!4dlng or !8m2!3dlat!4dlng) - HIGHEST PRIORITY
            let m = decoded.match(/!3d(-?\d+\.\d+)!4d(-?\d+\.\d+)/);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            // Pattern 2: loc:lat,lng
            m = decoded.match(/loc:(-?\d+\.\d+)[,\s+]+(-?\d+\.\d+)/i);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            // Pattern 3: ?q=lat,lng or &q=lat,lng
            m = decoded.match(/[?&]q=(-?\d+\.\d+)[,\s+]+(-?\d+\.\d+)/);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            // Pattern 4: ?query=lat,lng
            m = decoded.match(/[?&]query=(-?\d+\.\d+)[,\s+]+(-?\d+\.\d+)/);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            // Pattern 5: /place/lat,lng or /place/lat+lng or /place/lat lng
            m = decoded.match(/\/place\/(-?\d+\.\d+)[,\s+]+(-?\d+\.\d+)/);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            // Pattern 6: ll=lat,lng or center=lat,lng or sll=lat,lng
            m = decoded.match(/[?&](?:ll|center|sll)=(-?\d+\.\d+)[,\s+]+(-?\d+\.\d+)/);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            // Pattern 7: Raw coordinates pasted (e.g. "27.5590, 68.2120" or "27.5590 68.2120")
            m = decoded.trim().match(/^(-?\d{1,2}\.\d+)[,\s]+(-?\d{1,3}\.\d+)$/);
            if (m) {
                const lat = parseFloat(m[1]), lng = parseFloat(m[2]);
                if (lat >= -90 && lat <= 90 && lng >= -180 && lng <= 180) {
                    return { lat, lng };
                }
            }

            // Pattern 8: @lat,lng (Camera/Viewport center fallback)
            m = decoded.match(/@(-?\d+\.\d+)[,\s+]+(-?\d+\.\d+)/);
            if (m) return { lat: parseFloat(m[1]), lng: parseFloat(m[2]) };

            return null;
        }

        function executeSearch() {
            const q = searchInput.value.trim();
            if (!q) return;

            // ── Raw coordinates or direct Google Maps URL extraction ──
            const immediateCoords = tryExtractGoogleMapsCoords(q);
            if (immediateCoords) {
                updatePosition(immediateCoords.lat, immediateCoords.lng, true);
                searchInput.value = '';
                return;
            }

            // ── If user pasted a short link (goo.gl / maps.app.goo.gl) ──
            if (/(?:goo\.gl|maps\.app\.goo\.gl|g\.co)/i.test(q)) {
                customAlert({
                    title: 'Use Full Google Maps URL',
                    message: 'Shortened links (goo.gl / maps.app.goo.gl) do not contain GPS coordinates. Please open the link in your browser, copy the full URL from the address bar, and paste it here.',
                    type: 'info'
                });
                return;
            }

            // ── Feature 2: Nominatim address geocoding search (with Pakistan bias) ──
            btnSearch.innerHTML = '<span class="spinner-border spinner-border-sm" role="status"></span>';
            btnSearch.disabled = true;

            fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&countrycodes=pk&limit=1`)
                .then(res => res.json())
                .then(data => {
                    // Fallback to global search if no Pakistan match found
                    if (!data || data.length === 0) {
                        return fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(q)}&limit=1`).then(r => r.json());
                    }
                    return data;
                })
                .then(data => {
                    btnSearch.innerHTML = 'Locate';
                    btnSearch.disabled = false;
                    if (data && data.length > 0) {
                        const resLat = parseFloat(data[0].lat);
                        const resLng = parseFloat(data[0].lon);
                        updatePosition(resLat, resLng, true);
                    } else {
                        customAlert({ title: 'Location Not Found', message: 'No coordinates found for this search. Try searching with a broader city or landmark name.', type: 'warning' });
                    }
                })
                .catch(err => {
                    btnSearch.innerHTML = 'Locate';
                    btnSearch.disabled = false;
                    customAlert({ title: 'Geocoding Service Unavailable', message: 'Error reaching OpenStreetMap geocoding service.', type: 'danger' });
                });
        }

        if (btnSearch) {
            btnSearch.addEventListener('click', executeSearch);
        }
        if (searchInput) {
            searchInput.addEventListener('keydown', function(e) {
                if (e.key === 'Enter') {
                    e.preventDefault();
                    executeSearch();
                }
            });
            // Auto-detect paste of Google Maps URL
            searchInput.addEventListener('paste', function(e) {
                setTimeout(executeSearch, 100);
            });
        }
    });
    </script>

<?php else: ?>

    <!-- Overview Metric Cards -->
    <div class="row g-3 mb-4">
        <div class="col-xl-4 col-sm-6">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle text-primary bg-primary-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-geo-alt fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Total Market Venues</div>
                        <div class="fs-4 fw-bold text-dark"><?= count($markets) ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-6">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle text-success bg-success-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-shop fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Participating Stalls</div>
                        <div class="fs-4 fw-bold text-dark"><?= $total_stalls_participating ?></div>
                    </div>
                </div>
            </div>
        </div>
        <div class="col-xl-4 col-sm-12">
            <div class="card border shadow-xs p-3">
                <div class="d-flex align-items-center gap-3">
                    <div class="p-3 rounded-circle text-warning bg-warning-subtle d-flex align-items-center justify-content-center" style="width: 48px; height: 48px;">
                        <i class="bi bi-calendar-week fs-4"></i>
                    </div>
                    <div>
                        <div class="text-muted small fw-semibold">Weekend Coverage</div>
                        <div class="fs-4 fw-bold text-dark">Active Weekly</div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Interactive Platform Overview Map Card -->
    <div class="card border shadow-xs mb-4">
        <div class="card-header bg-white py-3 border-bottom d-flex justify-content-between align-items-center">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi bi-map-fill text-primary me-2"></i>Platform Geographic Network Map
            </h5>
            <span class="badge badge-primary"><?= count($markets) ?> Venues Pinned</span>
        </div>
        <div class="card-body p-0">
            <div id="allMarketsMap" style="height: 280px; width: 100%;"></div>
        </div>
    </div>

    <!-- Markets Directory Table -->
    <div class="card border shadow-xs">
        <div class="card-header bg-white py-3 border-bottom d-flex flex-wrap justify-content-between align-items-center gap-2">
            <h5 class="card-title mb-0 fs-6 fw-bold">
                <i class="bi bi-buildings text-primary me-2"></i>Registered Markets Directory
            </h5>
            <div class="d-flex align-items-center gap-2">
                <input type="text" id="marketSearch" class="form-control form-control-sm" placeholder="Search markets..." style="max-width: 220px;">
            </div>
        </div>
        <div class="card-body p-0">
            <?php if (!empty($markets)): ?>
                <div class="table-responsive">
                    <table class="table table-hover align-middle mb-0" id="marketsTable">
                        <thead class="table-light small">
                            <tr>
                                <th>Market Name & Details</th>
                                <th>Address & Location</th>
                                <th>Operating Days</th>
                                <th>Timings</th>
                                <th>Stalls</th>
                                <th class="text-end">Actions</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($markets as $m): ?>
                                <tr class="market-row">
                                    <td>
                                        <div class="fw-bold text-dark"><?= e($m['market_name']) ?></div>
                                        <small class="text-muted">Lat: <?= number_format((float)$m['latitude'], 4) ?>, Lng: <?= number_format((float)$m['longitude'], 4) ?></small>
                                    </td>
                                    <td>
                                        <div class="small text-secondary" style="max-width: 260px;">
                                            <i class="bi bi-pin-map text-primary me-1"></i><?= e($m['address']) ?>
                                        </div>
                                    </td>
                                    <td>
                                        <span class="badge badge-primary"><?= e($m['operating_days']) ?></span>
                                    </td>
                                    <td>
                                        <span class="small fw-semibold text-dark"><i class="bi bi-clock me-1 text-muted"></i><?= e($m['timings']) ?></span>
                                    </td>
                                    <td>
                                        <span class="badge bg-light text-dark border"><?= $m['stall_count'] ?> stall<?= $m['stall_count'] == 1 ? '' : 's' ?></span>
                                    </td>
                                    <td class="text-end">
                                        <div class="d-inline-flex gap-1">
                                            <a href="<?= BASE_URL ?>customer/browse-markets.php?market_id=<?= $m['market_id'] ?>" target="_blank" class="btn btn-outline-primary btn-sm py-1 px-2" title="Preview Public Page">
                                                <i class="bi bi-eye"></i>
                                            </a>
                                            <a href="<?= BASE_URL ?>admin/manage-markets.php?action=edit&id=<?= $m['market_id'] ?>" class="btn btn-outline-primary btn-sm py-1 px-2" title="Edit Market">
                                                <i class="bi bi-pencil"></i>
                                            </a>
                                            <form method="POST" action="<?= BASE_URL ?>admin/manage-markets.php" class="d-inline" data-confirm="Permanently delete farmers market venue <?= e($m['market_name']) ?>?" data-confirm-title="Delete Market Venue" data-confirm-type="danger" data-confirm-btn="Yes, Delete">
                                                <?= csrf_field() ?>
                                                <input type="hidden" name="form_action" value="delete_market">
                                                <input type="hidden" name="market_id" value="<?= $m['market_id'] ?>">
                                                <button type="submit" class="btn btn-outline-danger btn-sm py-1 px-2" title="Delete Market">
                                                    <i class="bi bi-trash3"></i>
                                                </button>
                                            </form>
                                        </div>
                                    </td>
                                </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                </div>
            <?php else: ?>
                <div class="text-center py-5 text-muted">
                    <div class="mx-auto rounded-circle d-flex align-items-center justify-content-center mb-3" style="width: 56px; height: 56px; background: var(--surface-2); color: var(--text-3);">
                        <i class="bi bi-geo fs-2"></i>
                    </div>
                    <h6 class="fw-bold mb-1">No Farmers Markets Registered</h6>
                    <p class="small text-muted mb-3">Add your first physical farmers market venue to start hosting farmer stalls.</p>
                    <a href="<?= BASE_URL ?>admin/manage-markets.php?action=add" class="btn btn-primary btn-sm">
                        <i class="bi bi-plus-circle me-1"></i> Add First Market
                    </a>
                </div>
            <?php endif; ?>
        </div>
    </div>

    <!-- Map Leaflet Script for Overview -->
    <script>
    document.addEventListener('DOMContentLoaded', function () {
        const marketsData = <?= json_encode(array_map(function($m) {
            return [
                'id' => (int)$m['market_id'],
                'name' => $m['market_name'],
                'address' => $m['address'],
                'lat' => (float)$m['latitude'],
                'lng' => (float)$m['longitude'],
                'days' => $m['operating_days'],
                'timings' => $m['timings'],
                'stalls' => (int)$m['stall_count']
            ];
        }, $markets)) ?>;

        if (marketsData.length > 0 && document.getElementById('allMarketsMap')) {
            const first = marketsData[0];
            const map = L.map('allMarketsMap').setView([first.lat, first.lng], 11);

            L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
                attribution: '&copy; OpenStreetMap contributors'
            }).addTo(map);

            const marketPinHtml = `
                <div style="background: linear-gradient(135deg, #2E7D4F, #1e3a8a); color: white; width: 34px; height: 34px; border-radius: 50%; display: flex; align-items: center; justify-content: center; box-shadow: 0 4px 10px rgba(0,0,0,0.3); border: 2px solid #ffffff;">
                    <i class="bi bi-buildings-fill" style="font-size: 16px;"></i>
                </div>
            `;
            const customIcon = L.divIcon({
                className: 'custom-overview-pin',
                html: marketPinHtml,
                iconSize: [34, 34],
                iconAnchor: [17, 34],
                popupAnchor: [0, -34]
            });

            const bounds = [];
            marketsData.forEach(function(m) {
                if (m.lat && m.lng) {
                    const marker = L.marker([m.lat, m.lng], { icon: customIcon }).addTo(map);
                    marker.bindPopup(`
                        <div style="font-family: inherit; font-size: 13px; min-width: 200px;">
                            <strong style="color: #2E7D4F; font-size: 14px;">${m.name}</strong><br>
                            <span style="color: #64748b; font-size: 12px;"><i class="bi bi-geo-alt me-1"></i>${m.address}</span>
                            <div style="margin-top: 6px; font-size: 12px; background: #f8fafc; padding: 6px 8px; border-radius: 4px; border: 1px solid #e2e8f0;">
                                <div><strong>Days:</strong> ${m.days}</div>
                                <div><strong>Hours:</strong> ${m.timings}</div>
                                <div><strong>Active Stalls:</strong> ${m.stalls}</div>
                            </div>
                            <div class="d-flex gap-1 mt-2">
                                <a href="https://www.google.com/maps/dir/?api=1&destination=${m.lat},${m.lng}" target="_blank" rel="noopener noreferrer" class="btn btn-xs btn-outline-primary w-100 py-1" style="font-size: 11px; text-decoration: none;">
                                    <i class="bi bi-pin-map-fill text-danger me-1"></i> Get Directions
                                </a>
                                <a href="<?= BASE_URL ?>customer/browse-markets.php?market_id=${m.id}" target="_blank" class="btn btn-xs btn-primary w-100 py-1" style="font-size: 11px; text-decoration: none;">
                                    <i class="bi bi-shop me-1"></i> Stalls
                                </a>
                            </div>
                        </div>
                    `);
                    bounds.push([m.lat, m.lng]);
                }
            });

            if (bounds.length > 1) {
                map.fitBounds(bounds, { padding: [30, 30] });
            }
        }

        // Live Market Search Filter
        const searchInput = document.getElementById('marketSearch');
        if (searchInput) {
            searchInput.addEventListener('keyup', function() {
                const query = this.value.toLowerCase();
                document.querySelectorAll('#marketsTable .market-row').forEach(function(row) {
                    const text = row.textContent.toLowerCase();
                    row.style.display = text.includes(query) ? '' : 'none';
                });
            });
        }
    });
    </script>

<?php endif; ?>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
