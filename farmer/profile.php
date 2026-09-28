<?php
/**
 * MarketLink - Farmer Stall Profile & Location Settings
 */

$required_role = 'farmer';
require_once __DIR__ . '/../includes/auth-check.php';

$farmer_id = get_logged_in_user_id();
$errors = [];

// Handle Profile Updates via POST
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        $errors['general'] = 'Invalid form session. Please try again.';
    } else {
        $contact_name = sanitize_input($_POST['name'] ?? '');
        $phone = sanitize_input($_POST['phone'] ?? '');
        $stall_name = sanitize_input($_POST['stall_name'] ?? '');
        $address = sanitize_input($_POST['address'] ?? '');
        $latitude = !empty($_POST['latitude']) ? (float)$_POST['latitude'] : null;
        $longitude = !empty($_POST['longitude']) ? (float)$_POST['longitude'] : null;
        $operating_days = isset($_POST['operating_days']) && is_array($_POST['operating_days']) ? $_POST['operating_days'] : [];
        $pickup_window_start = sanitize_input($_POST['pickup_window_start'] ?? '08:00');
        $pickup_window_end = sanitize_input($_POST['pickup_window_end'] ?? '14:00');
        $order_cutoff_hours = (int)($_POST['order_cutoff_hours'] ?? 2);
        $selected_markets = isset($_POST['markets']) && is_array($_POST['markets']) ? $_POST['markets'] : [];

        if (empty($stall_name)) {
            $errors['stall_name'] = 'Stall / Farm name is required.';
        }
        if (empty($contact_name)) {
            $errors['name'] = 'Contact person name is required.';
        }

        if (empty($errors)) {
            try {
                $pdo->beginTransaction();

                // 1. Update users table
                $u_stmt = $pdo->prepare("UPDATE users SET name = :name, phone = :phone WHERE user_id = :uid");
                $u_stmt->execute([
                    ':name' => $contact_name,
                    ':phone' => $phone,
                    ':uid' => $farmer_id
                ]);
                $_SESSION['user_name'] = $contact_name;

                // 2. Update farmer_profiles table (using INSERT ON DUPLICATE KEY UPDATE)
                $days_str = implode(',', $operating_days);
                $fp_stmt = $pdo->prepare("INSERT INTO farmer_profiles 
                    (farmer_id, stall_name, address, latitude, longitude, operating_days, pickup_window_start, pickup_window_end, order_cutoff_hours) 
                    VALUES (:fid, :stall_name, :address, :lat, :lng, :days, :p_start, :p_end, :cutoff)
                    ON DUPLICATE KEY UPDATE 
                    stall_name = VALUES(stall_name),
                    address = VALUES(address),
                    latitude = VALUES(latitude),
                    longitude = VALUES(longitude),
                    operating_days = VALUES(operating_days),
                    pickup_window_start = VALUES(pickup_window_start),
                    pickup_window_end = VALUES(pickup_window_end),
                    order_cutoff_hours = VALUES(order_cutoff_hours)");
                $fp_stmt->execute([
                    ':fid' => $farmer_id,
                    ':stall_name' => $stall_name,
                    ':address' => $address,
                    ':lat' => $latitude,
                    ':lng' => $longitude,
                    ':days' => $days_str,
                    ':p_start' => $pickup_window_start,
                    ':p_end' => $pickup_window_end,
                    ':cutoff' => $order_cutoff_hours
                ]);

                // 3. Update market associations in market_farmers
                $del_mf = $pdo->prepare("DELETE FROM market_farmers WHERE farmer_id = :fid");
                $del_mf->execute([':fid' => $farmer_id]);

                if (!empty($selected_markets)) {
                    $ins_mf = $pdo->prepare("INSERT INTO market_farmers (market_id, farmer_id) VALUES (:mid, :fid)");
                    foreach ($selected_markets as $mid) {
                        $mid_int = (int)$mid;
                        if ($mid_int > 0) {
                            $ins_mf->execute([':mid' => $mid_int, ':fid' => $farmer_id]);
                        }
                    }
                }

                $pdo->commit();
                set_flash('success', 'Stall profile and market settings saved successfully.');
                redirect(BASE_URL . 'farmer/profile.php');
            } catch (PDOException $e) {
                $pdo->rollBack();
                error_log("Farmer profile save error: " . $e->getMessage());
                $errors['general'] = 'Failed to update stall profile.';
            }
        }
    }
}

// Fetch current profile data
$user_info = null;
$farmer_profile = null;
$all_markets = [];
$attending_markets = [];

try {
    $u_stmt = $pdo->prepare("SELECT name, email, phone FROM users WHERE user_id = :uid LIMIT 1");
    $u_stmt->execute([':uid' => $farmer_id]);
    $user_info = $u_stmt->fetch();

    $p_stmt = $pdo->prepare("SELECT * FROM farmer_profiles WHERE farmer_id = :fid LIMIT 1");
    $p_stmt->execute([':fid' => $farmer_id]);
    $farmer_profile = $p_stmt->fetch();

    $m_stmt = $pdo->query("SELECT market_id, market_name, address, operating_days FROM markets ORDER BY market_name ASC");
    $all_markets = $m_stmt->fetchAll();

    $mf_stmt = $pdo->prepare("SELECT market_id FROM market_farmers WHERE farmer_id = :fid");
    $mf_stmt->execute([':fid' => $farmer_id]);
    $attending_markets = $mf_stmt->fetchAll(PDO::FETCH_COLUMN);

} catch (PDOException $e) {
    error_log("Fetch profile data error: " . $e->getMessage());
}

$active_nav = 'profile';
$page_title = 'Stall Profile & Schedule Settings';
require_once __DIR__ . '/includes/header.php';
?>

<!-- Page Header -->
<div class="d-flex flex-column flex-md-row justify-content-between align-items-md-center gap-3 mb-4">
    <div>
        <h1 class="h3 mb-1 fw-bold">Stall Settings & Schedule</h1>
        <p class="text-muted small mb-0">Configure your farm branding, operating days, pickup timings, and interactive map pin</p>
    </div>
    <div class="d-flex gap-2">
        <a href="<?= BASE_URL ?>customer/farmer-detail.php?farmer_id=<?= $farmer_id ?>" target="_blank" class="btn btn-outline-secondary btn-sm rounded-3">
            <i class="bi bi-eye me-1"></i> Preview Public Stall
        </a>
    </div>
</div>

<?php if (!empty($errors['general'])): ?>
    <div class="alert alert-danger py-2 small mb-3 border-0 shadow-xs"><?= e($errors['general']) ?></div>
<?php endif; ?>

<form action="<?= BASE_URL ?>farmer/profile.php" method="POST" novalidate>
    <?= csrf_field() ?>
    <div class="row g-4">
            <!-- Left Column: Stall Details & Timings -->
            <div class="col-lg-6">
                <!-- Branding & Contact Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-person-badge text-primary me-2"></i>Stall & Contact Information</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label" for="stall_name">Stall / Farm Brand Name <span class="text-danger">*</span></label>
                            <input type="text" class="form-control <?= isset($errors['stall_name']) ? 'is-invalid' : '' ?>" id="stall_name" name="stall_name" value="<?= e($farmer_profile['stall_name'] ?? '') ?>" required placeholder="e.g. Green Valley Organic Farm (Stall #12)">
                            <?php if (isset($errors['stall_name'])): ?><div class="invalid-feedback"><?= e($errors['stall_name']) ?></div><?php endif; ?>
                        </div>

                        <div class="row g-2 mb-3">
                            <div class="col-md-6">
                                <label class="form-label" for="name">Contact Person <span class="text-danger">*</span></label>
                                <input type="text" class="form-control <?= isset($errors['name']) ? 'is-invalid' : '' ?>" id="name" name="name" value="<?= e($user_info['name'] ?? '') ?>" required>
                                <?php if (isset($errors['name'])): ?><div class="invalid-feedback"><?= e($errors['name']) ?></div><?php endif; ?>
                            </div>
                            <div class="col-md-6">
                                <label class="form-label" for="phone">Phone / WhatsApp</label>
                                <input type="tel" class="form-control" id="phone" name="phone" value="<?= e($user_info['phone'] ?? '') ?>" placeholder="03001234567">
                            </div>
                        </div>

                        <div class="mb-0">
                            <label class="form-label" for="address">Stall Location / Farm Address</label>
                            <textarea class="form-control" id="address" name="address" rows="2" placeholder="e.g. Row B, Stall 12, Central Farmers Market"><?= e($farmer_profile['address'] ?? '') ?></textarea>
                        </div>
                    </div>
                </div>

                <!-- Operating Days & Pickup Window Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-clock-history text-primary me-2"></i>Operating Schedule & Pickup Windows</h5>
                    </div>
                    <div class="card-body p-4">
                        <div class="mb-3">
                            <label class="form-label d-block fw-semibold">Operating Days at Market</label>
                            <div class="d-flex flex-wrap gap-3">
                                <?php 
                                $all_days = ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
                                $profile_days = !empty($farmer_profile['operating_days']) ? explode(',', $farmer_profile['operating_days']) : ['Sat', 'Sun'];
                                foreach ($all_days as $d): 
                                ?>
                                    <div class="form-check">
                                        <input class="form-check-input" type="checkbox" name="operating_days[]" value="<?= $d ?>" id="day_<?= $d ?>" <?= in_array($d, $profile_days) ? 'checked' : '' ?>>
                                        <label class="form-check-label small" for="day_<?= $d ?>"><?= $d ?></label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        </div>

                        <div class="row g-3 mb-3">
                            <div class="col-md-4">
                                <label class="form-label" for="pickup_window_start">Pickup Start Time</label>
                                <input type="time" class="form-control" id="pickup_window_start" name="pickup_window_start" value="<?= e($farmer_profile['pickup_window_start'] ?? '08:00') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="pickup_window_end">Pickup End Time</label>
                                <input type="time" class="form-control" id="pickup_window_end" name="pickup_window_end" value="<?= e($farmer_profile['pickup_window_end'] ?? '14:00') ?>">
                            </div>
                            <div class="col-md-4">
                                <label class="form-label" for="order_cutoff_hours" title="Minimum hours required before pickup slot to pack produce">Cutoff Hours</label>
                                <input type="number" min="1" max="72" class="form-control" id="order_cutoff_hours" name="order_cutoff_hours" value="<?= e($farmer_profile['order_cutoff_hours'] ?? 2) ?>">
                            </div>
                        </div>
                    </div>
                </div>

                <!-- Attending Farmers Markets Card -->
                <div class="card shadow-sm border-0 mb-4">
                    <div class="card-header bg-white py-3">
                        <h5 class="card-title mb-0 fs-6"><i class="bi bi-geo-alt text-primary me-2"></i>Select Markets Where You Sell</h5>
                    </div>
                    <div class="card-body p-4">
                        <p class="small text-muted mb-3">Check the markets where your stall will be present so shoppers can discover you on that market's directory page:</p>
                        <?php if (!empty($all_markets)): ?>
                            <div class="d-flex flex-column gap-2">
                                <?php foreach ($all_markets as $m): ?>
                                    <div class="form-check p-2 bg-light rounded border">
                                        <input class="form-check-input ms-1" type="checkbox" name="markets[]" value="<?= $m['market_id'] ?>" id="m_<?= $m['market_id'] ?>" <?= in_array($m['market_id'], $attending_markets) ? 'checked' : '' ?>>
                                        <label class="form-check-label ms-2 small" for="m_<?= $m['market_id'] ?>">
                                            <strong><?= e($m['market_name']) ?></strong> &bull; <span class="text-muted"><?= e($m['address']) ?> (<?= e($m['operating_days']) ?>)</span>
                                        </label>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                        <?php else: ?>
                            <div class="text-muted small">No markets configured yet by administrator.</div>
                        <?php endif; ?>
                    </div>
                </div>
            </div>

            <!-- Right Column: Interactive OpenStreetMap Location Pin Picker -->
            <div class="col-lg-6">
                <div class="card shadow-sm border-0 mb-4 rounded-4 overflow-hidden">
                    <div class="card-header bg-white py-3 px-4 d-flex justify-content-between align-items-center">
                        <div>
                            <h5 class="card-title mb-0 fs-6 fw-bold"><i class="bi bi-pin-map-fill text-primary me-2"></i>Stall Map Pin (OpenStreetMap)</h5>
                            <small class="text-muted">Click, drag pin, or search to set your exact stall pickup point</small>
                        </div>
                        <span class="badge bg-success-subtle text-success small">Interactive Pin</span>
                    </div>
                    <div class="card-body p-4">
                        <!-- Location Search & GPS Controls -->
                        <div class="row g-2 mb-1">
                            <div class="col-sm-7">
                                <div class="input-group input-group-sm">
                                    <span class="input-group-text bg-light"><i class="bi bi-search"></i></span>
                                    <input type="text" id="mapSearchInput" class="form-control" placeholder="Search place, OR paste a Google Maps link…">
                                    <button type="button" id="btnSearchLoc" class="btn btn-primary">Find</button>
                                </div>
                            </div>
                            <div class="col-sm-5">
                                <button type="button" id="btnGpsStall" class="btn btn-outline-primary btn-sm w-100 rounded-3" title="Detect your device coordinates">
                                    <i class="bi bi-crosshair me-1"></i> Use My Location
                                </button>
                            </div>
                        </div>
                        <p class="text-muted mb-2" style="font-size:0.7rem;"><i class="bi bi-info-circle me-1"></i>Paste a Google Maps URL to auto-extract coordinates, or type an address/landmark.</p>

                        <!-- Reverse Geocoding result badge -->
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

                        <div id="stallMap" style="height: 300px; border-radius: var(--radius-sm); border: 1px solid var(--border-color, #dee2e6); margin-bottom: 0.8rem; overflow: hidden; position: relative;"></div>

                        <!-- Advanced: Collapsible raw coordinates -->
                        <div class="mb-2">
                            <a class="text-muted d-flex align-items-center gap-1" data-bs-toggle="collapse" href="#advancedCoordsFarmer" role="button" aria-expanded="false" style="font-size:0.75rem; text-decoration:none;">
                                <i class="bi bi-chevron-right" id="advChevronFarmer" style="transition:transform .2s;"></i>
                                <span>⚙️ Advanced: Fine-tune GPS coordinates</span>
                            </a>
                            <div class="collapse" id="advancedCoordsFarmer">
                                <div class="row g-2 mt-1">
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold" for="latitude">Latitude</label>
                                        <input type="number" step="0.00000001" class="form-control form-control-sm font-monospace" id="latitude" name="latitude" value="<?= e($farmer_profile['latitude'] ?? '27.55900000') ?>">
                                    </div>
                                    <div class="col-md-6">
                                        <label class="form-label small fw-semibold" for="longitude">Longitude</label>
                                        <input type="number" step="0.00000001" class="form-control form-control-sm font-monospace" id="longitude" name="longitude" value="<?= e($farmer_profile['longitude'] ?? '68.21200000') ?>">
                                    </div>
                                </div>
                            </div>
                        </div>

                        <!-- GPS Pill + directions -->
                        <div class="p-2 bg-light rounded-3 border d-flex justify-content-between align-items-center flex-wrap gap-2">
                            <div class="d-flex align-items-center gap-2">
                                <span class="badge bg-white text-secondary border" style="font-size:0.7rem; font-family:monospace;">
                                    <i class="bi bi-crosshair me-1"></i>
                                    <span id="stallGpsPill"><?= number_format((float)($farmer_profile['latitude'] ?? 27.5590), 4) ?>, <?= number_format((float)($farmer_profile['longitude'] ?? 68.2120), 4) ?></span>
                                </span>
                                <span class="text-muted" style="font-size:0.68rem;">← Auto-updates</span>
                            </div>
                            <a id="previewDirectionsLink" href="https://www.google.com/maps/dir/?api=1&destination=<?= $farmer_profile['latitude'] ?? '27.5590' ?>,<?= $farmer_profile['longitude'] ?? '68.2120' ?>" target="_blank" class="btn btn-outline-success btn-sm py-1 px-2 rounded-2" style="font-size: 0.75rem;">
                                <i class="bi bi-sign-turn-right-fill me-1"></i> Test Directions
                            </a>
                        </div>
                    </div>
                </div>

                <div class="card shadow-xs border-0 p-3 bg-white rounded-4">
                    <button type="submit" class="btn btn-primary btn-lg w-100 py-2 rounded-3 shadow-xs fw-bold">
                        <i class="bi bi-check2-circle me-1"></i> Save Stall Configuration
                    </button>
                </div>
            </div>
        </div>
    </form>

<!-- Leaflet Map Initialization Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const latInput = document.getElementById('latitude');
    const lngInput = document.getElementById('longitude');
    const dirLink = document.getElementById('previewDirectionsLink');
    const detectedBadge = document.getElementById('detectedAddressBadge');
    const detectedText = document.getElementById('detectedAddressText');
    const btnFillAddress = document.getElementById('btnFillAddress');
    const stallGpsPill = document.getElementById('stallGpsPill');
    const addressField = document.getElementById('address');
    const advCollapseEl = document.getElementById('advancedCoordsFarmer');
    const advChevron = document.getElementById('advChevronFarmer');

    const defaultLat = parseFloat(latInput.value) || 27.5590;
    const defaultLng = parseFloat(lngInput.value) || 68.2120;
    let lastReverseGeoAddress = '';

    const map = L.map('stallMap', {
        zoomControl: true,
        scrollWheelZoom: true
    }).setView([defaultLat, defaultLng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors',
        maxZoom: 19
    }).addTo(map);
    // Force full tile render after page layout settles
    setTimeout(function() { map.invalidateSize(); }, 300);

    const stallIcon = L.divIcon({
        className: 'custom-stall-pin',
        html: `<div style="background-color:#2E7D4F; width:36px; height:36px; border-radius:50%; display:flex; align-items:center; justify-content:center; color:#fff; border:2px solid #fff; box-shadow:0 3px 8px rgba(0,0,0,0.3); font-size:17px;">
                 <i class="bi bi-shop"></i>
               </div>`,
        iconSize: [36, 36],
        iconAnchor: [18, 18],
        popupAnchor: [0, -18]
    });

    let marker = L.marker([defaultLat, defaultLng], { draggable: true, icon: stallIcon }).addTo(map)
        .bindPopup('<strong>📍 Stall Pickup Point</strong><br>Drag or click map to reposition.')
        .openPopup();

    // ── Feature 6: Reverse Geocoding ──
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
            .catch(() => {}); // Silent – map works without reverse geo
    }

    // ── Core: update coords, pill, and directions link ──
    function updateCoords(lat, lng) {
        latInput.value = lat.toFixed(8);
        lngInput.value = lng.toFixed(8);
        if (dirLink) {
            dirLink.href = `https://www.google.com/maps/dir/?api=1&destination=${lat.toFixed(8)},${lng.toFixed(8)}`;
        }
        if (stallGpsPill) {
            stallGpsPill.textContent = `${lat.toFixed(4)}, ${lng.toFixed(4)}`;
        }
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

    // ── Feature 5: Chevron animation for Advanced collapse ──
    if (advCollapseEl && advChevron) {
        advCollapseEl.addEventListener('show.bs.collapse', function () {
            advChevron.style.transform = 'rotate(90deg)';
        });
        advCollapseEl.addEventListener('hide.bs.collapse', function () {
            advChevron.style.transform = 'rotate(0deg)';
        });
    }

    marker.on('dragend', function (e) {
        const position = marker.getLatLng();
        updateCoords(position.lat, position.lng);
    });

    map.on('click', function (e) {
        marker.setLatLng(e.latlng);
        updateCoords(e.latlng.lat, e.latlng.lng);
    });

    // Manual input sync
    [latInput, lngInput].forEach(function(inp) {
        inp.addEventListener('change', function() {
            const lat = parseFloat(latInput.value);
            const lng = parseFloat(lngInput.value);
            if (!isNaN(lat) && !isNaN(lng)) {
                marker.setLatLng([lat, lng]);
                map.setView([lat, lng], 14);
                updateCoords(lat, lng);
            }
        });
    });

    // ── Feature 4: GPS Geolocation Handler ──
    const btnGps = document.getElementById('btnGpsStall');
    if (btnGps) {
        btnGps.addEventListener('click', function() {
            if (!navigator.geolocation) {
                customAlert({ title: 'GPS Not Supported', message: 'Geolocation is not supported by your browser.', type: 'warning' });
                return;
            }
            btnGps.innerHTML = '<span class="spinner-border spinner-border-sm me-1"></span> Detecting...';
            btnGps.disabled = true;

            navigator.geolocation.getCurrentPosition(
                function(pos) {
                    const lat = pos.coords.latitude;
                    const lng = pos.coords.longitude;
                    btnGps.innerHTML = '<i class="bi bi-check2-circle text-success me-1"></i> GPS Detected';
                    btnGps.disabled = false;
                    marker.setLatLng([lat, lng]);
                    map.setView([lat, lng], 15);
                    updateCoords(lat, lng);
                },
                function(err) {
                    btnGps.innerHTML = '<i class="bi bi-crosshair me-1"></i> Use My Location';
                    btnGps.disabled = false;
                    customAlert({ title: 'GPS Detection Failed', message: 'Could not detect your current coordinates: ' + (err.message || 'Permission denied'), type: 'danger' });
                },
                { enableHighAccuracy: true, timeout: 10000 }
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

        // Pattern 7: Raw coordinates pasted (e.g. "27.5590, 68.2120")
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

    // ── Feature 2: Address geocoding search & Full Google Maps URL locator ──
    const searchInput = document.getElementById('mapSearchInput');
    const btnSearch = document.getElementById('btnSearchLoc');

    function executeSearch() {
        const query = (searchInput.value || '').trim();
        if (!query) return;

        // ── Raw coordinates or direct Google Maps URL extraction ──
        const immediateCoords = tryExtractGoogleMapsCoords(query);
        if (immediateCoords) {
            marker.setLatLng([immediateCoords.lat, immediateCoords.lng]);
            map.setView([immediateCoords.lat, immediateCoords.lng], 15);
            updateCoords(immediateCoords.lat, immediateCoords.lng);
            searchInput.value = '';
            return;
        }

        // ── If user pasted a short link (goo.gl / maps.app.goo.gl) ──
        if (/(?:goo\.gl|maps\.app\.goo\.gl|g\.co)/i.test(query)) {
            customAlert({
                title: 'Use Full Google Maps URL',
                message: 'Shortened links (goo.gl / maps.app.goo.gl) do not contain GPS coordinates. Please open the link in your browser, copy the full URL from the address bar, and paste it here.',
                type: 'info'
            });
            return;
        }

        btnSearch.innerHTML = '<span class="spinner-border spinner-border-sm"></span>';
        btnSearch.disabled = true;

        fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&countrycodes=pk&limit=1`)
            .then(res => res.json())
            .then(data => {
                if (!data || data.length === 0) {
                    return fetch(`https://nominatim.openstreetmap.org/search?format=json&q=${encodeURIComponent(query)}&limit=1`).then(r => r.json());
                }
                return data;
            })
            .then(data => {
                btnSearch.innerHTML = 'Find';
                btnSearch.disabled = false;
                if (data && data.length > 0) {
                    const lat = parseFloat(data[0].lat);
                    const lng = parseFloat(data[0].lon);
                    marker.setLatLng([lat, lng]);
                    map.setView([lat, lng], 15);
                    updateCoords(lat, lng);
                    marker.bindPopup(`<strong>${data[0].display_name}</strong>`).openPopup();
                } else {
                    customAlert({ title: 'Location Not Found', message: 'No location results found for "' + query + '". Please try another search term or drag the map pin manually.', type: 'warning' });
                }
            })
            .catch(err => {
                btnSearch.innerHTML = 'Find';
                btnSearch.disabled = false;
                customAlert({ title: 'Geocoding Service Error', message: 'Unable to reach the geocoding service. You can position your stall pin by clicking or dragging directly on the map.', type: 'danger' });
            });
    }

    if (btnSearch && searchInput) {
        btnSearch.addEventListener('click', executeSearch);
        searchInput.addEventListener('keydown', function(e) {
            if (e.key === 'Enter') {
                e.preventDefault();
                executeSearch();
            }
        });
        // Auto-trigger on paste (for Google Maps URLs)
        searchInput.addEventListener('paste', function(e) {
            setTimeout(executeSearch, 100);
        });
    }
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
