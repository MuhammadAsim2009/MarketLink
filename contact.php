<?php
/**
 * MarketLink - Contact Us Page with OpenStreetMap
 */

require_once __DIR__ . '/config/constants.php';
require_once __DIR__ . '/config/db.php';
require_once __DIR__ . '/includes/functions.php';

$active_nav = 'contact';
$page_title = 'Contact Us';
$submitted = false;

if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    if (!verify_csrf_token($_POST['csrf_token'] ?? '')) {
        set_flash('danger', 'Invalid session submission.');
    } else {
        $name = sanitize_input($_POST['name'] ?? '');
        $email = sanitize_input($_POST['email'] ?? '');
        $subject = sanitize_input($_POST['subject'] ?? '');
        $message = sanitize_input($_POST['message'] ?? '');

        if (empty($name) || empty($email) || empty($message)) {
            set_flash('danger', 'Please complete all required fields.');
        } else {
            set_flash('success', "Thank you {$name}! Your message regarding '{$subject}' has been received. Our team will get back to you shortly.");
            $submitted = true;
        }
    }
}

require_once __DIR__ . '/includes/header.php';
?>

<div class="container py-5">
    <div class="max-w-700 mx-auto text-center mb-5">
        <h1 class="h2 fw-bold mb-2"><i class="bi bi-chat-dots-fill text-primary me-2"></i>Get In Touch With MarketLink</h1>
        <p class="text-muted mb-0">Have a question about weekend market locations, stall registrations, or pre-orders? We are here to help!</p>
    </div>

    <div class="row g-5">
        <!-- Contact Form Column -->
        <div class="col-lg-6">
            <div class="card shadow-sm border-0 p-4 bg-white">
                <h5 class="fw-bold mb-3"><i class="bi bi-envelope text-primary me-2"></i>Send an Inquiry</h5>

                <form action="<?= BASE_URL ?>contact.php" method="POST" novalidate>
                    <?= csrf_field() ?>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="name">Your Name <span class="text-danger">*</span></label>
                        <input type="text" class="form-control" id="name" name="name" required placeholder="e.g. John Doe">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="email">Email Address <span class="text-danger">*</span></label>
                        <input type="email" class="form-control" id="email" name="email" required placeholder="name@example.com">
                    </div>

                    <div class="mb-3">
                        <label class="form-label small fw-semibold" for="subject">Subject</label>
                        <input type="text" class="form-control" id="subject" name="subject" placeholder="e.g. Stall Registration Inquiry">
                    </div>

                    <div class="mb-4">
                        <label class="form-label small fw-semibold" for="message">Message <span class="text-danger">*</span></label>
                        <textarea class="form-control" id="message" name="message" rows="4" required placeholder="Write your message here..."></textarea>
                    </div>

                    <button type="submit" class="btn btn-primary w-100 py-2">
                        <i class="bi bi-send me-1"></i> Send Message
                    </button>
                </form>
            </div>
        </div>

        <!-- Contact Info & Leaflet Map Column -->
        <div class="col-lg-6">
            <div class="d-flex flex-column gap-4">
                <div class="card shadow-sm border-0 p-4 bg-white">
                    <h5 class="fw-bold mb-3"><i class="bi bi-info-circle text-primary me-2"></i>Platform Information</h5>
                    <div class="d-flex flex-column gap-2 small text-muted">
                        <div><i class="bi bi-geo-alt-fill text-danger me-2"></i> <strong>Headquarters:</strong> MarketLink Community Hub, Heritage Plaza</div>
                        <div><i class="bi bi-envelope-fill text-primary me-2"></i> <strong>Email:</strong> support@marketlink.test</div>
                        <div><i class="bi bi-telephone-fill text-success me-2"></i> <strong>Helpline:</strong> +92 (300) 123-4567</div>
                        <div><i class="bi bi-clock-fill text-warning me-2"></i> <strong>Support Hours:</strong> Monday – Saturday (08:00 AM – 06:00 PM)</div>
                    </div>
                </div>

                <!-- Interactive Headquarter / Central Market Map -->
                <div class="card shadow-sm border-0 overflow-hidden">
                    <div class="card-header bg-white py-2">
                        <span class="small fw-semibold text-muted"><i class="bi bi-map me-1"></i> Central Market & Support Hub Location</span>
                    </div>
                    <div class="card-body p-0">
                        <div id="contactMap" style="height: 220px; width: 100%;"></div>
                    </div>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- Leaflet Map Script -->
<script>
document.addEventListener('DOMContentLoaded', function () {
    const lat = 31.5204;
    const lng = 74.3587;
    const map = L.map('contactMap').setView([lat, lng], 14);

    L.tileLayer('https://{s}.tile.openstreetmap.org/{z}/{x}/{y}.png', {
        attribution: '&copy; <a href="https://www.openstreetmap.org/copyright">OpenStreetMap</a> contributors'
    }).addTo(map);

    L.marker([lat, lng]).addTo(map)
        .bindPopup('<strong>MarketLink Support Hub</strong><br>Central Farmers Market Plaza')
        .openPopup();
});
</script>

<?php require_once __DIR__ . '/includes/footer.php'; ?>
