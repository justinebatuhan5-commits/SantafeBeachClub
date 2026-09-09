/**
 * Santa Beach Club — Frontend Security Library (security.js)
 * Implements 8 Pillars of Frontend Security:
 * 1. Input Validation
 * 2. Input Sanitization
 * 3. XSS Protection
 * 4. CSRF Token Usage & Automatic Attachment
 * 5. Secure Forms & Anti-Double Submission
 * 6. File Upload Validation
 * 7. File Type Restrictions
 * 8. File Size Restrictions
 */

(function (window, document) {
    'use strict';

    const Security = {
        config: {
            defaultMaxFileSizeMB: 5,
            allowedImageTypes: ['image/jpeg', 'image/png', 'image/webp'],
            allowedImageExtensions: ['jpg', 'jpeg', 'png', 'webp'],
            patterns: {
                email: /^[a-zA-Z0-9._%+-]+@[a-zA-Z0-9.-]+\.[a-zA-Z]{2,}$/,
                phone: /^(\+?63|0)?9\d{8,10}$/, // Philippine mobile: 09xxxxxxxxx, +639xxxxxxxxx, 9xxxxxxxxx (9-12 digits)
                name: /^[a-zA-ZÀ-ÿ\s'-]{2,60}$/,
                alphanumeric: /^[a-zA-Z0-9_ -]+$/,
                price: /^\d+(\.\d{1,2})?$/,
                positiveInteger: /^[1-9]\d*$/
            }
        },

        // --- 1. INPUT VALIDATION ---
        validateField: function (input) {
            const value = (input.value || '').trim();
            const type = input.getAttribute('type');
            const patternType = input.getAttribute('data-validate');
            const label = input.getAttribute('data-label');
            let isValid = true;
            let errorMessage = '';

            // Show "X is required." if field has a data-label and is empty
            if (label && value === '') {
                isValid = false;
                errorMessage = label + ' is required.';
            } else if (value !== '') {
                // Email validation
                if (type === 'email' || patternType === 'email') {
                    if (!this.config.patterns.email.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid email address (e.g. name@example.com).';
                    }
                }
                // Phone validation
                else if (type === 'tel' || patternType === 'phone') {
                    const cleanPhone = value.replace(/[\s-]/g, '');
                    if (!this.config.patterns.phone.test(cleanPhone)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid mobile number (e.g. 09171234567 or +639171234567).';
                    }
                }
                // Name validation
                else if (patternType === 'name') {
                    if (!this.config.patterns.name.test(value)) {
                        isValid = false;
                        errorMessage = 'Please enter a valid name (letters, spaces, hyphens only).';
                    }
                }
                // Numeric / Amount validation
                else if (patternType === 'price' || type === 'number') {
                    if (input.min && parseFloat(value) < parseFloat(input.min)) {
                        isValid = false;
                        errorMessage = `Value must be at least ${input.min}.`;
                    }
                    if (input.max && parseFloat(value) > parseFloat(input.max)) {
                        isValid = false;
                        errorMessage = `Value cannot exceed ${input.max}.`;
                    }
                }
                // Custom regex pattern attribute
                else if (input.pattern) {
                    const regex = new RegExp(input.pattern);
                    if (!regex.test(value)) {
                        isValid = false;
                        errorMessage = input.title || 'Invalid format.';
                    }
                }
            }

            this.toggleFieldError(input, isValid, errorMessage);
            return isValid;
        },



        toggleFieldError: function (input, isValid, message) {
            // The direct flex-row wrapper of the input (never put error INSIDE this)
            const inputBox = input.closest('.input-box') || input.closest('.bk-input-combo');
            const combo    = input.closest('.bk-input-combo');

            // Walk up to find a block-level container that is NOT the flex row itself
            const container = input.closest('.input-block')
                           || input.closest('.bk-form-group')
                           || input.closest('.form-group')
                           || (inputBox ? inputBox.parentNode : input.parentNode);

            // Find an existing error element scoped to this container (not inside input-box)
            let errorEl = container.querySelector(':scope > .security-field-error');

            if (!isValid) {
                input.classList.add('is-invalid');
                input.classList.remove('is-valid');
                if (combo) combo.classList.add('is-invalid');

                if (!errorEl) {
                    errorEl = document.createElement('div');
                    errorEl.className = 'security-field-error';
                    // Always insert AFTER the flex row (.input-box), never inside it
                    if (inputBox && inputBox.parentNode === container) {
                        inputBox.insertAdjacentElement('afterend', errorEl);
                    } else {
                        container.appendChild(errorEl);
                    }
                }
                errorEl.innerHTML = `
                    <svg class="error-icon" width="14" height="14" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">
                        <circle cx="12" cy="12" r="10"></circle>
                        <line x1="12" y1="8" x2="12" y2="12"></line>
                        <line x1="12" y1="16" x2="12.01" y2="16"></line>
                    </svg>
                    <span>${Security.escapeHTML(message)}</span>
                `;
                errorEl.style.display = 'flex';
            } else {
                input.classList.remove('is-invalid');
                if (combo && !combo.querySelector('.is-invalid')) {
                    combo.classList.remove('is-invalid');
                }
                if (input.value.trim() !== '') {
                    input.classList.add('is-valid');
                }
                if (errorEl) {
                    errorEl.style.display = 'none';
                }
            }
        },


        // --- 2. INPUT SANITIZATION ---
        sanitizeString: function (str) {
            if (typeof str !== 'string') return str;
            return str
                .replace(/<script\b[^<]*(?:(?!<\/script>)<[^<]*)*<\/script>/gi, '') // Strip script tags
                .replace(/[<>]/g, '') // Strip brackets
                .trim();
        },

        sanitizeFormInputs: function (form) {
            const textInputs = form.querySelectorAll('input[type="text"], input[type="search"], textarea');
            textInputs.forEach(input => {
                // Remove null bytes and dangerous control characters
                input.value = input.value.replace(/[\x00-\x08\x0B\x0C\x0E-\x1F\x7F]/g, '');
            });
        },

        // --- 3. XSS PROTECTION ---
        escapeHTML: function (str) {
            if (str === null || str === undefined) return '';
            const map = {
                '&': '&amp;',
                '<': '&lt;',
                '>': '&gt;',
                '"': '&quot;',
                "'": '&#039;',
                '`': '&#x60;'
            };
            return String(str).replace(/[&<>"'`]/g, function (m) { return map[m]; });
        },

        safeText: function (element, text) {
            if (element) {
                element.textContent = text;
            }
        },

        // --- 4. CSRF TOKEN MANAGEMENT ---
        getCSRFToken: function () {
            const metaTag = document.querySelector('meta[name="csrf-token"]');
            if (metaTag) {
                return metaTag.getAttribute('content');
            }
            const hiddenInput = document.querySelector('input[name="csrf_token"]');
            if (hiddenInput) {
                return hiddenInput.value;
            }
            return '';
        },

        injectCSRFIntoForms: function () {
            const token = this.getCSRFToken();
            if (!token) return;

            document.querySelectorAll('form').forEach(form => {
                const method = (form.getAttribute('method') || 'GET').toUpperCase();
                if (method === 'POST' && !form.querySelector('input[name="csrf_token"]')) {
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'csrf_token';
                    input.value = token;
                    form.appendChild(input);
                }
            });
        },

        setupAjaxCSRF: function () {
            const self = this;
            const token = this.getCSRFToken();
            if (!token) return;

            // Intercept window.fetch
            if (window.fetch) {
                const originalFetch = window.fetch;
                window.fetch = function (url, options = {}) {
                    options = options || {};
                    options.headers = options.headers || {};

                    const method = (options.method || 'GET').toUpperCase();
                    if (['POST', 'PUT', 'DELETE', 'PATCH'].includes(method)) {
                        if (options.headers instanceof Headers) {
                            if (!options.headers.has('X-CSRF-Token')) {
                                options.headers.set('X-CSRF-Token', token);
                            }
                        } else if (typeof options.headers === 'object') {
                            options.headers['X-CSRF-Token'] = token;
                        }
                    }
                    return originalFetch.call(this, url, options);
                };
            }

            // Intercept XMLHttpRequest
            if (window.XMLHttpRequest) {
                const originalOpen = XMLHttpRequest.prototype.open;
                const originalSend = XMLHttpRequest.prototype.send;

                XMLHttpRequest.prototype.open = function (method, url) {
                    this._method = method;
                    return originalOpen.apply(this, arguments);
                };

                XMLHttpRequest.prototype.send = function (data) {
                    if (this._method && ['POST', 'PUT', 'DELETE', 'PATCH'].includes(this._method.toUpperCase())) {
                        try {
                            this.setRequestHeader('X-CSRF-Token', token);
                        } catch (e) {
                            // Header setting might fail if state not OPENED
                        }
                    }
                    return originalSend.apply(this, arguments);
                };
            }
        },

        // --- 5. SECURE FORMS & ANTI-DOUBLE SUBMIT ---
        secureForm: function (form) {
            const self = this;

            // Real-time input validation on blur / input
            form.querySelectorAll('input, select, textarea').forEach(input => {
                input.addEventListener('blur', function () {
                    self.validateField(this);
                });
                input.addEventListener('input', function () {
                    if (this.classList.contains('is-invalid')) {
                        self.validateField(this);
                    }
                });
            });

            form.addEventListener('submit', function (e) {
                self.sanitizeFormInputs(form);

                // Validate all fields
                let isFormValid = true;
                const inputs = form.querySelectorAll('input:not([type="hidden"]), select, textarea');
                inputs.forEach(input => {
                    if (!self.validateField(input)) {
                        isFormValid = false;
                    }
                });

                // Validate file inputs
                const fileInputs = form.querySelectorAll('input[type="file"]');
                fileInputs.forEach(fileInput => {
                    if (!self.validateFileInput(fileInput)) {
                        isFormValid = false;
                    }
                });

                if (!isFormValid) {
                    e.preventDefault();
                    e.stopPropagation();
                    self.showNotification('Please correct the errors in the form before submitting.', 'error');
                    return false;
                }

                // Prevent double submission safely for standard HTTP submissions
                // Skip completely if another handler (e.g. custom AJAX fetch/xhr) has called e.preventDefault()
                // or if the form is flagged for custom AJAX handling
                setTimeout(() => {
                    // Check on next tick to ensure any other submit listener that calls preventDefault() has run
                    if (e.defaultPrevented) {
                        return;
                    }
                    const submitBtn = e.submitter || form.querySelector('button[type="submit"], input[type="submit"]');
                    if (submitBtn && !submitBtn.disabled) {
                        if (submitBtn.name) {
                            const hiddenInput = document.createElement('input');
                            hiddenInput.type = 'hidden';
                            hiddenInput.name = submitBtn.name;
                            hiddenInput.value = submitBtn.value || '1';
                            form.appendChild(hiddenInput);
                        }
                        
                        submitBtn.disabled = true;
                        submitBtn.dataset.originalHtml = submitBtn.innerHTML;
                        if (submitBtn.tagName === 'BUTTON') {
                            submitBtn.innerHTML = '<span class="spinner" style="display:inline-block;width:14px;height:14px;border:2px solid rgba(255,255,255,0.3);border-radius:50%;border-top-color:#fff;animation:secSpin 0.8s linear infinite;margin-right:6px;vertical-align:middle;"></span> Processing...';
                        }
                    }
                }, 0);
            });
        },


        // --- 6, 7 & 8. FILE UPLOAD, TYPE & SIZE VALIDATION ---
        validateFileInput: function (input) {
            if (!input.files || input.files.length === 0) {
                if (input.hasAttribute('required')) {
                    this.toggleFieldError(input, false, 'Please select a file to upload.');
                    return false;
                }
                return true;
            }

            const maxMB = parseFloat(input.getAttribute('data-max-size')) || this.config.defaultMaxFileSizeMB;
            const maxBytes = maxMB * 1024 * 1024;
            const accept = input.getAttribute('accept');

            let allowedExtensions = this.config.allowedImageExtensions;
            let allowedMimes = this.config.allowedImageTypes;

            if (accept) {
                allowedExtensions = accept.split(',')
                    .map(item => item.trim().replace('.', '').toLowerCase())
                    .filter(item => !item.includes('/'));
                allowedMimes = accept.split(',')
                    .map(item => item.trim().toLowerCase())
                    .filter(item => item.includes('/'));
            }

            for (let i = 0; i < input.files.length; i++) {
                const file = input.files[i];
                const extension = file.name.split('.').pop().toLowerCase();

                // 8. Size Check
                if (file.size > maxBytes) {
                    const msg = `File "${file.name}" exceeds the maximum allowed size of ${maxMB}MB (${(file.size / (1024 * 1024)).toFixed(2)}MB).`;
                    this.toggleFieldError(input, false, msg);
                    this.showNotification(msg, 'error');
                    input.value = ''; // Reset input
                    return false;
                }

                // 7. Type Check
                let typeValid = false;
                if (allowedMimes.length > 0) {
                    for (const m of allowedMimes) {
                        if (m === 'image/*' && (file.type.startsWith('image/') || this.config.allowedImageExtensions.includes(extension))) {
                            typeValid = true;
                            break;
                        } else if (file.type && file.type.toLowerCase() === m) {
                            typeValid = true;
                            break;
                        }
                    }
                }
                if (!typeValid && allowedExtensions.length > 0 && allowedExtensions.includes(extension)) {
                    typeValid = true;
                }
                if (!typeValid && allowedMimes.length === 0 && allowedExtensions.length === 0) {
                    if (this.config.allowedImageExtensions.includes(extension) || this.config.allowedImageTypes.includes(file.type.toLowerCase())) {
                        typeValid = true;
                    }
                }

                if (!typeValid && (allowedMimes.length > 0 || allowedExtensions.length > 0)) {
                    const displayExts = allowedExtensions.length > 0 ? allowedExtensions.join(', ') : 'jpg, jpeg, png, webp';
                    const msg = `File "${file.name}" has an invalid type. Allowed extensions: ${displayExts}.`;
                    this.toggleFieldError(input, false, msg);
                    this.showNotification(msg, 'error');
                    input.value = ''; // Reset input
                    return false;
                }
            }

            this.toggleFieldError(input, true, '');
            return true;
        },

        setupFileInputListeners: function () {
            const self = this;
            document.querySelectorAll('input[type="file"]').forEach(fileInput => {
                fileInput.addEventListener('change', function () {
                    self.validateFileInput(this);
                });
            });
        },

        // --- Notification Toast ---
        showNotification: function (message, type = 'info') {
            let container = document.getElementById('security-toast-container');
            if (!container) {
                container = document.createElement('div');
                container.id = 'security-toast-container';
                container.style.cssText = 'position:fixed;top:20px;right:20px;z-index:99999;display:flex;flex-direction:column;gap:10px;pointer-events:none;';
                document.body.appendChild(container);
            }

            const toast = document.createElement('div');
            const bgColor = type === 'error' ? '#dc2626' : (type === 'success' ? '#16a34a' : '#2563eb');
            toast.style.cssText = `background:${bgColor};color:#fff;padding:12px 18px;border-radius:8px;box-shadow:0 4px 14px rgba(0,0,0,0.25);font-size:0.875rem;max-width:360px;font-family:system-ui,-apple-system,sans-serif;pointer-events:auto;animation:secFadeIn 0.3s ease-out;display:flex;align-items:center;gap:10px;`;

            toast.innerHTML = `
                <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">
                    ${type === 'error' ? '<circle cx="12" cy="12" r="10"/><line x1="12" y1="8" x2="12" y2="12"/><line x1="12" y1="16" x2="12.01" y2="16"/>' : '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>'}
                </svg>
                <span>${this.escapeHTML(message)}</span>
            `;

            container.appendChild(toast);
            setTimeout(() => {
                toast.style.opacity = '0';
                toast.style.transform = 'translateY(-10px)';
                toast.style.transition = 'all 0.3s ease';
                setTimeout(() => toast.remove(), 300);
            }, 4500);
        },

        // --- Initialize ---
        init: function () {
            // Add CSS animation for spinners and toast
            if (!document.getElementById('security-styles')) {
                const style = document.createElement('style');
                style.id = 'security-styles';
                style.textContent = `
                    @keyframes secSpin { to { transform: rotate(360deg); } }
                    @keyframes secFadeIn { from { opacity: 0; transform: translateY(-4px); } to { opacity: 1; transform: translateY(0); } }
                    .is-invalid { border-color: #ef4444 !important; box-shadow: 0 0 0 2.5px rgba(239, 68, 68, 0.2) !important; }
                    .is-valid { border-color: #16a34a !important; }
                    .security-field-error {
                        display: flex !important;
                        align-items: center;
                        gap: 6px;
                        margin-top: 7px;
                        padding: 0;
                        font-size: 12px;
                        font-weight: 500;
                        color: #F87171;
                        line-height: 1.4;
                        animation: secFadeIn 0.22s ease;
                        clear: both;
                        width: 100%;
                    }
                    .security-field-error[style*="display: none"] {
                        display: none !important;
                    }
                    .security-field-error .error-icon {
                        display: inline-block;
                        flex-shrink: 0;
                        color: #EF4444;
                        margin-top: 1px;
                    }
                `;
                document.head.appendChild(style);
            }

            this.injectCSRFIntoForms();
            this.setupAjaxCSRF();
            this.setupFileInputListeners();

            document.querySelectorAll('form').forEach(form => {
                this.secureForm(form);
            });
        }
    };

    // Auto-init on DOMContentLoaded
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', () => Security.init());
    } else {
        Security.init();
    }

    window.Security = Security;

})(window, document);
