/**
 * MarketLink — Client-side Utilities
 * Vanilla JS, no external dependencies
 */

document.addEventListener('DOMContentLoaded', function () {
    // Cart badge
    updateCartBadge();

    // Animate stat counters on the landing page
    animateCounters();

    // Lazy-load images
    lazyLoadImages();
});

/* ── Cart Badge ────────────────────────────────────────────────── */
function updateCartBadge(customCount) {
    const badge = document.getElementById('cartCountBadge');
    if (!badge) return;
    let count = 0;
    if (typeof customCount !== 'undefined') {
        count = parseInt(customCount, 10) || 0;
    } else {
        const textVal = parseInt(badge.textContent, 10);
        if (!isNaN(textVal)) {
            count = textVal;
        }
    }
    if (count > 0) {
        badge.textContent = count > 9 ? '9+' : count;
        badge.style.display = 'flex';
    } else {
        badge.style.display = 'none';
    }
}
window.updateCartBadge = updateCartBadge;

/* ── Global Dynamic Toast Notification ─────────────────────────── */
function showToast(message, type = 'success') {
    let wrapper = document.getElementById('flashWrapper');
    if (!wrapper) {
        wrapper = document.createElement('div');
        wrapper.id = 'flashWrapper';
        wrapper.className = 'flash-wrapper';
        document.body.appendChild(wrapper);
    }
    const iconClass = (type === 'success') ? 'bi-check-circle-fill' : ((type === 'error' || type === 'danger') ? 'bi-x-circle-fill' : 'bi-info-circle-fill');
    const toast = document.createElement('div');
    toast.className = `flash-alert ${type === 'danger' ? 'error' : type}`;
    toast.setAttribute('role', 'alert');
    toast.innerHTML = `
        <i class="bi ${iconClass} flash-icon"></i>
        <span>${message}</span>
        <span class="flash-close" onclick="this.closest('.flash-alert').remove()">&#x2715;</span>
    `;
    wrapper.appendChild(toast);
    setTimeout(() => {
        toast.style.transition = 'opacity .4s';
        toast.style.opacity = '0';
        setTimeout(() => toast.remove(), 400);
    }, 4000);
}
window.showToast = showToast;

/* ── Counter Animation ─────────────────────────────────────────── */
function animateCounters() {
    const els = document.querySelectorAll('[data-counter]');
    if (!els.length) return;

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const el = entry.target;
                const target = parseInt(el.dataset.counter, 10);
                let current = 0;
                const step = Math.ceil(target / 40);
                const timer = setInterval(() => {
                    current = Math.min(current + step, target);
                    el.textContent = current + (el.dataset.suffix || '');
                    if (current >= target) clearInterval(timer);
                }, 30);
                observer.unobserve(el);
            }
        });
    }, { threshold: 0.5 });

    els.forEach(el => observer.observe(el));
}

/* ── Lazy Load Images ──────────────────────────────────────────── */
function lazyLoadImages() {
    const imgs = document.querySelectorAll('img[data-src]');
    if (!imgs.length || !('IntersectionObserver' in window)) {
        imgs.forEach(img => { if (img.dataset.src) img.src = img.dataset.src; });
        return;
    }
    const obs = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                const img = entry.target;
                img.src = img.dataset.src;
                img.removeAttribute('data-src');
                obs.unobserve(img);
            }
        });
    }, { rootMargin: '100px' });
    imgs.forEach(img => obs.observe(img));
}

/* ── Form Submit Spinner (POST forms only) ──────────────────────── */
document.addEventListener('submit', function (e) {
    const form = e.target;
    if (!form || (form.method && form.method.toLowerCase() === 'get') || form.hasAttribute('data-no-spin')) {
        return;
    }
    const btn = form.querySelector('[type="submit"]:not([data-no-spin])');
    if (!btn || btn.dataset.spinning) return;
    btn.dataset.spinning = '1';
    const orig = btn.innerHTML;
    btn.innerHTML = '<span style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,.4);border-top-color:#fff;border-radius:50%;animation:spin .6s linear infinite;"></span> &nbsp;Loading…';
    btn.disabled = true;
    // Restore if somehow the page stays (e.g., validation)
    setTimeout(() => {
        btn.innerHTML = orig;
        btn.disabled = false;
        delete btn.dataset.spinning;
    }, 8000);
});

// Spin keyframe (injected once)
(function() {
    if (document.getElementById('ml-spin-style')) return;
    const s = document.createElement('style');
    s.id = 'ml-spin-style';
    s.textContent = '@keyframes spin{to{transform:rotate(360deg)}}';
    document.head.appendChild(s);
})();

/* ── Tooltip Init ──────────────────────────────────────────────── */
document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(el => {
    try { new bootstrap.Tooltip(el, { trigger: 'hover' }); } catch(e) {}
});

/* ── Global Password Show/Hide Toggle ──────────────────────────── */
document.addEventListener('click', function (e) {
    const toggleBtn = e.target.closest('[data-toggle-password]');
    if (!toggleBtn) return;

    e.preventDefault();
    const targetId = toggleBtn.getAttribute('data-toggle-password');
    const input = document.getElementById(targetId);
    if (!input) return;

    const icon = toggleBtn.querySelector('i');
    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.classList.remove('bi-eye');
            icon.classList.add('bi-eye-slash');
        }
        toggleBtn.setAttribute('title', 'Hide password');
    } else {
        input.type = 'password';
        if (icon) {
            icon.classList.remove('bi-eye-slash');
            icon.classList.add('bi-eye');
        }
        toggleBtn.setAttribute('title', 'Show password');
    }
});
