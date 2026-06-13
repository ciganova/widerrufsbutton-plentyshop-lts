/* ==========================================================================
 * Widerrufsbutton JavaScript
 * Client-side validation and order lookup
 * ========================================================================== */

document.addEventListener('DOMContentLoaded', function () {
    initWiderrufForm();
});

function initWiderrufForm() {
    const form = document.getElementById('widerruf-form');
    if (!form) return;

    const orderIdInput = document.getElementById('widerruf-order-id');
    const emailInput = document.getElementById('widerruf-email');
    const lookupResult = document.getElementById('order-lookup-result');

    // --- Client-side validation ---
    form.addEventListener('submit', function (event) {
        let valid = true;
        const nameInput = document.getElementById('widerruf-name');
        const nameError = document.getElementById('widerruf-name-error');
        const orderError = document.getElementById('widerruf-order-error');
        const emailError = document.getElementById('widerruf-email-error');

        // Clear previous errors
        [nameInput, orderIdInput, emailInput].forEach(function (el) {
            if (el) el.classList.remove('is-invalid');
        });
        [nameError, orderError, emailError].forEach(function (el) {
            if (el) el.textContent = '';
        });

        // Validate name
        if (!nameInput || nameInput.value.trim().length < 2) {
            markInvalid(nameInput, nameError, 'Bitte geben Sie Ihren vollständigen Namen an.');
            valid = false;
        }

        // Validate order ID
        if (!orderIdInput || orderIdInput.value.trim() === '') {
            markInvalid(orderIdInput, orderError, 'Bitte geben Sie Ihre Bestellnummer an.');
            valid = false;
        }

        // Validate email
        if (!emailInput || emailInput.value.trim() === '') {
            markInvalid(emailInput, emailError, 'Bitte geben Sie Ihre E-Mail-Adresse an.');
            valid = false;
        } else if (!isValidEmail(emailInput.value.trim())) {
            markInvalid(emailInput, emailError, 'Bitte geben Sie eine gültige E-Mail-Adresse an.');
            valid = false;
        }

        if (!valid) {
            event.preventDefault();
        }
    });

    // --- Live order lookup ---
    let lookupTimeout = null;
    var checkOrder = function () {
        var orderId = orderIdInput.value.trim();
        var email = emailInput ? emailInput.value.trim() : '';

        if (!orderId || orderId.length < 2) {
            if (lookupResult) {
                lookupResult.style.display = 'none';
            }
            return;
        }

        // Only check if we have an email too
        if (!email || !isValidEmail(email)) {
            return;
        }

        var xhr = new XMLHttpRequest();
        xhr.open('GET', '/widerruf/lookup?order_id='
            + encodeURIComponent(orderId)
            + '&email='
            + encodeURIComponent(email));

        xhr.onload = function () {
            if (xhr.status !== 200 || !lookupResult) return;

            try {
                var data = JSON.parse(xhr.responseText);
                lookupResult.style.display = 'block';

                if (data.found) {
                    if (data.within_period) {
                        lookupResult.className = 'mt-1 found';
                        lookupResult.innerHTML = '✓ Bestellung gefunden — Widerruf möglich.';
                    } else {
                        lookupResult.className = 'mt-1 not-found';
                        lookupResult.innerHTML = '⚠ Die Widerrufsfrist für diese Bestellung ist abgelaufen.';
                    }
                } else {
                    lookupResult.className = 'mt-1 not-found';
                    lookupResult.innerHTML = '✗ Keine Bestellung mit diesen Daten gefunden.';
                }
            } catch (e) {
                lookupResult.style.display = 'none';
            }
        };

        xhr.onerror = function () {
            if (lookupResult) {
                lookupResult.style.display = 'none';
            }
        };

        xhr.send();
    };

    if (orderIdInput && emailInput) {
        orderIdInput.addEventListener('keyup', function () {
            clearTimeout(lookupTimeout);
            lookupTimeout = setTimeout(checkOrder, 600);
        });

        emailInput.addEventListener('keyup', function () {
            clearTimeout(lookupTimeout);
            lookupTimeout = setTimeout(checkOrder, 600);
        });
    }

    // --- Confirm button disable-on-submit (prevent double-click) ---
    var confirmBtn = document.getElementById('confirm-btn');
    if (confirmBtn) {
        var confirmForm = document.getElementById('widerruf-confirm-form');
        if (confirmForm) {
            confirmForm.addEventListener('submit', function () {
                confirmBtn.disabled = true;
                confirmBtn.innerHTML =
                    '<span class="spinner-border spinner-border-sm me-2" role="status" aria-hidden="true"></span>'
                    + 'Wird übermittelt...';
            });
        }
    }
}

function markInvalid(element, errorElement, message) {
    if (element) element.classList.add('is-invalid');
    if (errorElement) errorElement.textContent = message;
}

function isValidEmail(email) {
    return /^[^\s@]+@[^\s@]+\.[^\s@]+$/.test(email);
}
