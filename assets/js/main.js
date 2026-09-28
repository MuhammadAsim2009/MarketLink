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
function togglePasswordVisibility(targetId, btnEl) {
    if (!targetId) return;
    const input = document.getElementById(targetId);
    if (!input) return;

    const btn = btnEl || document.querySelector(`[data-toggle-password="${targetId}"]`);
    const icon = btn ? btn.querySelector('i') : null;

    if (input.type === 'password') {
        input.type = 'text';
        if (icon) {
            icon.className = 'bi bi-eye-slash';
        }
        if (btn) btn.setAttribute('title', 'Hide password');
    } else {
        input.type = 'password';
        if (icon) {
            icon.className = 'bi bi-eye';
        }
        if (btn) btn.setAttribute('title', 'Show password');
    }
}
window.togglePasswordVisibility = togglePasswordVisibility;

// Event delegation (only triggers if button does NOT have inline onclick)
document.addEventListener('click', function (e) {
    const toggleBtn = e.target.closest('[data-toggle-password]');
    if (!toggleBtn) return;
    if (toggleBtn.hasAttribute('onclick')) return; // Avoid double toggle

    e.preventDefault();
    const targetId = toggleBtn.getAttribute('data-toggle-password');
    togglePasswordVisibility(targetId, toggleBtn);
});

/* ============================================================
   Custom Popup Modal Module (Replaces js alert & confirm)
   ============================================================ */
(function () {
    let overlayEl = null;
    let dialogEl = null;
    let iconEl = null;
    let titleEl = null;
    let msgEl = null;
    let actionsEl = null;
    let activeResolve = null;

    function createModalDOM() {
        if (document.getElementById('mlCustomModalOverlay')) {
            overlayEl = document.getElementById('mlCustomModalOverlay');
            dialogEl = overlayEl.querySelector('.ml-modal-dialog');
            iconEl = overlayEl.querySelector('.ml-modal-icon-wrapper');
            titleEl = overlayEl.querySelector('.ml-modal-title');
            msgEl = overlayEl.querySelector('.ml-modal-message');
            actionsEl = overlayEl.querySelector('.ml-modal-actions');
            return;
        }

        overlayEl = document.createElement('div');
        overlayEl.id = 'mlCustomModalOverlay';
        overlayEl.className = 'ml-modal-overlay';
        overlayEl.setAttribute('role', 'dialog');
        overlayEl.setAttribute('aria-modal', 'true');

        overlayEl.innerHTML = `
            <div class="ml-modal-dialog">
                <button type="button" class="ml-modal-close-x" aria-label="Close modal">&times;</button>
                <div class="ml-modal-icon-wrapper">
                    <i class="bi bi-question-circle"></i>
                </div>
                <h3 class="ml-modal-title">Confirmation</h3>
                <div class="ml-modal-message">Are you sure you want to proceed?</div>
                <div class="ml-modal-actions"></div>
            </div>
        `;

        document.body.appendChild(overlayEl);

        dialogEl = overlayEl.querySelector('.ml-modal-dialog');
        iconEl = overlayEl.querySelector('.ml-modal-icon-wrapper');
        titleEl = overlayEl.querySelector('.ml-modal-title');
        msgEl = overlayEl.querySelector('.ml-modal-message');
        actionsEl = overlayEl.querySelector('.ml-modal-actions');

        // Close on X click
        overlayEl.querySelector('.ml-modal-close-x').addEventListener('click', () => closeModal(false));

        // Close on backdrop click
        overlayEl.addEventListener('click', (e) => {
            if (e.target === overlayEl) {
                closeModal(false);
            }
        });

        // Keydown listener for ESC / Enter
        document.addEventListener('keydown', (e) => {
            if (!overlayEl || !overlayEl.classList.contains('active')) return;
            if (e.key === 'Escape') {
                e.preventDefault();
                closeModal(false);
            }
        });
    }

    function closeModal(result) {
        if (!overlayEl) return;
        overlayEl.classList.remove('active');
        document.body.style.overflow = '';
        if (typeof activeResolve === 'function') {
            const res = activeResolve;
            activeResolve = null;
            res(result);
        }
    }

    /**
     * Determine visual type and icons automatically from message / action if not explicitly given
     */
    function detectTypeFromText(text) {
        const lower = (text || '').toLowerCase();
        if (lower.includes('delete') || lower.includes('remove') || lower.includes('suspend') || lower.includes('reject') || lower.includes('cancel') || lower.includes('permanently')) {
            return 'danger';
        }
        if (lower.includes('decline') || lower.includes('clear') || lower.includes('restore') || lower.includes('warning')) {
            return 'warning';
        }
        if (lower.includes('approve') || lower.includes('reactivate') || lower.includes('confirm that') || lower.includes('success') || lower.includes('paid')) {
            return 'success';
        }
        return 'primary';
    }

    function getIconClass(type, explicitIcon) {
        if (explicitIcon) return explicitIcon;
        switch (type) {
            case 'danger': return 'bi-trash3-fill';
            case 'warning': return 'bi-exclamation-triangle-fill';
            case 'success': return 'bi-check-circle-fill';
            case 'info': return 'bi-info-circle-fill';
            default: return 'bi-patch-question-fill';
        }
    }

    /**
     * Custom Confirm Modal
     * Usage:
     *   customConfirm('Delete item?').then(ok => { ... })
     *   customConfirm({ title: 'Delete Product', message: 'Are you sure?', type: 'danger', confirmText: 'Yes, Delete' })
     */
    function customConfirm(options, onConfirm, onCancel) {
        createModalDOM();

        let opts = {};
        if (typeof options === 'string') {
            opts.message = options;
        } else if (typeof options === 'object' && options !== null) {
            opts = { ...options };
        }

        const type = opts.type || detectTypeFromText(opts.message || opts.title || '');
        const title = opts.title || (type === 'danger' ? 'Confirm Action' : (type === 'warning' ? 'Warning' : 'Confirmation'));
        const message = opts.message || 'Are you sure you want to proceed with this action?';
        const confirmText = opts.confirmText || (type === 'danger' ? 'Yes, Proceed' : (type === 'success' ? 'Confirm' : 'Yes'));
        const cancelText = opts.cancelText || 'Cancel';
        const iconClass = getIconClass(type, opts.icon);

        // Update DOM
        iconEl.className = `ml-modal-icon-wrapper ${type}`;
        iconEl.innerHTML = `<i class="bi ${iconClass}"></i>`;
        titleEl.textContent = title;
        msgEl.innerHTML = message;

        actionsEl.innerHTML = `
            <button type="button" class="ml-modal-btn ml-modal-btn-cancel">${cancelText}</button>
            <button type="button" class="ml-modal-btn ml-modal-btn-confirm ${type}">${confirmText}</button>
        `;

        const cancelBtn = actionsEl.querySelector('.ml-modal-btn-cancel');
        const confirmBtn = actionsEl.querySelector('.ml-modal-btn-confirm');

        return new Promise((resolve) => {
            activeResolve = (val) => {
                if (val && typeof onConfirm === 'function') onConfirm();
                if (!val && typeof onCancel === 'function') onCancel();
                resolve(val);
            };

            cancelBtn.onclick = () => closeModal(false);
            confirmBtn.onclick = () => closeModal(true);

            overlayEl.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => confirmBtn.focus(), 50);
        });
    }

    /**
     * Custom Alert Modal
     * Usage:
     *   customAlert('Operation completed successfully!')
     *   customAlert({ title: 'Notice', message: '...', type: 'info' })
     */
    function customAlert(options, onDismiss) {
        createModalDOM();

        let opts = {};
        if (typeof options === 'string') {
            opts.message = options;
        } else if (typeof options === 'object' && options !== null) {
            opts = { ...options };
        }

        const type = opts.type || (detectTypeFromText(opts.message || opts.title || '') === 'danger' ? 'danger' : 'info');
        const title = opts.title || (type === 'danger' ? 'Notice' : (type === 'success' ? 'Success' : 'Notice'));
        const message = opts.message || '';
        const confirmText = opts.confirmText || 'Got it';
        const iconClass = opts.icon || (type === 'danger' ? 'bi-exclamation-circle-fill' : (type === 'success' ? 'bi-check-circle-fill' : 'bi-info-circle-fill'));

        iconEl.className = `ml-modal-icon-wrapper ${type}`;
        iconEl.innerHTML = `<i class="bi ${iconClass}"></i>`;
        titleEl.textContent = title;
        msgEl.innerHTML = message;

        actionsEl.innerHTML = `
            <button type="button" class="ml-modal-btn ml-modal-btn-confirm ${type}" style="width: 100%;">${confirmText}</button>
        `;

        const confirmBtn = actionsEl.querySelector('.ml-modal-btn-confirm');

        return new Promise((resolve) => {
            activeResolve = () => {
                if (typeof onDismiss === 'function') onDismiss();
                resolve();
            };

            confirmBtn.onclick = () => closeModal(true);

            overlayEl.classList.add('active');
            document.body.style.overflow = 'hidden';
            setTimeout(() => confirmBtn.focus(), 50);
        });
    }

    // Expose globally
    window.customConfirm = customConfirm;
    window.customAlert = customAlert;
    window.mlModal = {
        confirm: customConfirm,
        alert: customAlert,
        close: closeModal
    };

    // Override browser native alert to use customAlert
    window.alert = function (msg) {
        return customAlert(msg);
    };

    /**
     * Global Event Interceptor for data-confirm attributes and legacy onsubmit confirm()
     */
    document.addEventListener('DOMContentLoaded', function () {
        // Intercept form submissions
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form) return;

            // If this form was already confirmed and programmatically submitted, allow it
            if (form._mlConfirmed) {
                delete form._mlConfirmed;
                return;
            }

            // Check if form has data-confirm attribute
            const confirmMsg = form.getAttribute('data-confirm');
            if (confirmMsg) {
                e.preventDefault();
                e.stopImmediatePropagation();

                const title = form.getAttribute('data-confirm-title') || null;
                const type = form.getAttribute('data-confirm-type') || null;
                const confirmBtnText = form.getAttribute('data-confirm-btn') || null;
                const submitter = e.submitter || null;
                const submitterName = submitter && submitter.name ? submitter.name : null;
                const submitterValue = submitter && submitter.value !== undefined ? submitter.value : null;

                customConfirm({
                    title: title,
                    message: confirmMsg,
                    type: type,
                    confirmText: confirmBtnText
                }).then(confirmed => {
                    if (confirmed) {
                        form._mlConfirmed = true;
                        
                        // If a button with name/value initiated the submit, ensure it's in the form
                        if (submitterName && !form.querySelector(`input[name="${submitterName}"]`)) {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = submitterName;
                            hiddenInput.value = submitterValue;
                            form.appendChild(hiddenInput);
                        }

                        // Submit form cleanly
                        if (typeof form.requestSubmit === 'function' && submitter) {
                            form.requestSubmit(submitter);
                        } else if (typeof form.requestSubmit === 'function') {
                            form.requestSubmit();
                        } else {
                            form.submit();
                        }
                    }
                });
                return;
            }

            // Check for legacy onsubmit="return confirm('...')" inline attribute
            const onsubmitAttr = form.getAttribute('onsubmit');
            if (onsubmitAttr && onsubmitAttr.includes('confirm(')) {
                // Extract the message from confirm('...')
                const match = onsubmitAttr.match(/confirm\s*\(\s*(['"`])(.*?)\1\s*\)/);
                if (match && match[2]) {
                    e.preventDefault();
                    e.stopImmediatePropagation();

                    const rawMsg = match[2];
                    form.removeAttribute('onsubmit'); // Remove legacy to avoid re-triggering

                    customConfirm({
                        message: rawMsg
                    }).then(confirmed => {
                        if (confirmed) {
                            form._mlConfirmed = true;
                            if (typeof form.requestSubmit === 'function') {
                                form.requestSubmit();
                            } else {
                                form.submit();
                            }
                        }
                    });
                }
            }
        }, true); // Capture phase to intercept before inline onsubmit

        // Intercept click on links or buttons with data-confirm
        document.addEventListener('click', function (e) {
            const target = e.target.closest('[data-confirm]');
            if (!target || target.tagName === 'FORM') return;

            // If it's a submit button inside a form, let the form submit listener handle it
            if (target.type === 'submit' && target.form) return;

            // If already confirmed
            if (target._mlConfirmed) {
                delete target._mlConfirmed;
                return;
            }

            e.preventDefault();
            e.stopImmediatePropagation();

            const confirmMsg = target.getAttribute('data-confirm');
            const title = target.getAttribute('data-confirm-title') || null;
            const type = target.getAttribute('data-confirm-type') || null;
            const confirmBtnText = target.getAttribute('data-confirm-btn') || null;

            customConfirm({
                title: title,
                message: confirmMsg,
                type: type,
                confirmText: confirmBtnText
            }).then(confirmed => {
                if (confirmed) {
                    target._mlConfirmed = true;
                    if (target.tagName === 'A' && target.href) {
                        window.location.href = target.href;
                    } else {
                        target.click();
                    }
                }
            });
        }, true);
    });
})();

