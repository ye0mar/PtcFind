/**
 * Vanilla JavaScript Frontend Interactions
 * FoundU - Lost & Found Management System
 * Standard browser-compatible JavaScript (No frameworks)
 */

document.addEventListener('DOMContentLoaded', function () {
    // 0. Session Persistence Bridge (Seamless fallback when 3P iframe cookies are blocked)
    const authMode = document.body.getAttribute('data-auth-mode') || '';
    const bodyToken = document.body.getAttribute('data-session-token') || '';
    const currentUrlObj = new URL(window.location.href);
    const urlToken = currentUrlObj.searchParams.get('_st') || '';
    let activeSessionToken = '';

    if (authMode === 'token' || urlToken) {
        activeSessionToken = bodyToken || urlToken || '';
        if (activeSessionToken) {
            try { sessionStorage.setItem('foundu_st', activeSessionToken); } catch (_) {}
            if (!urlToken && window.history && window.history.replaceState) {
                currentUrlObj.searchParams.set('_st', activeSessionToken);
                window.history.replaceState({}, '', currentUrlObj.pathname + currentUrlObj.search + currentUrlObj.hash);
            }
        }
    } else if (!document.body.getAttribute('data-user-id')) {
        try { sessionStorage.removeItem('foundu_st'); } catch (_) {}
    } else {
        try {
            const stored = sessionStorage.getItem('foundu_st') || '';
            if (authMode === 'cookie' && stored) {
                sessionStorage.removeItem('foundu_st');
            }
        } catch (_) {}
    }

    function appendSessionToken(rawUrl) {
        if (!activeSessionToken || !rawUrl) return rawUrl;
        const s = String(rawUrl).trim();
        if (!s || s.startsWith('#') || s.startsWith('javascript:') || s.startsWith('mailto:') || s.startsWith('tel:')) {
            return rawUrl;
        }
        try {
            const u = new URL(s, window.location.origin);
            if (u.origin !== window.location.origin) return rawUrl;
            if (u.pathname.endsWith('/logout.php') || u.pathname === '/logout.php' || u.pathname.endsWith('/login.php')) {
                return rawUrl;
            }
            if (!u.searchParams.has('_st')) {
                u.searchParams.set('_st', activeSessionToken);
            }
            return u.pathname + u.search + u.hash;
        } catch (_) {
            return rawUrl;
        }
    }

    function applySessionTokenToDom(root) {
        if (!activeSessionToken || !root) return;
        root.querySelectorAll('a[href]').forEach(function (link) {
            const raw = link.getAttribute('href');
            const updated = appendSessionToken(raw);
            if (updated !== raw) link.setAttribute('href', updated);
        });
        root.querySelectorAll('form').forEach(function (form) {
            const rawAction = form.getAttribute('action') || window.location.pathname;
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method === 'POST') {
                form.setAttribute('action', appendSessionToken(rawAction));
            } else {
                let hidden = form.querySelector('input[type="hidden"][name="_st"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = '_st';
                    form.appendChild(hidden);
                }
                hidden.value = activeSessionToken;
            }
        });
    }

    if (activeSessionToken) {
        applySessionTokenToDom(document);
        document.addEventListener('click', function (e) {
            const link = e.target.closest('a[href]');
            if (!link) return;
            const raw = link.getAttribute('href') || '';
            if (raw.includes('logout.php')) {
                try { sessionStorage.removeItem('foundu_st'); } catch (_) {}
                return;
            }
            const updated = appendSessionToken(raw);
            if (updated !== raw) {
                link.setAttribute('href', updated);
            }
        }, true);
        document.addEventListener('submit', function (e) {
            const form = e.target;
            if (!form || !form.getAttribute) return;
            const rawAction = form.getAttribute('action') || window.location.pathname;
            const method = (form.getAttribute('method') || 'GET').toUpperCase();
            if (method === 'POST') {
                const updatedAction = appendSessionToken(rawAction);
                if (updatedAction !== rawAction) form.setAttribute('action', updatedAction);
            } else {
                let hidden = form.querySelector('input[type="hidden"][name="_st"]');
                if (!hidden) {
                    hidden = document.createElement('input');
                    hidden.type = 'hidden';
                    hidden.name = '_st';
                    form.appendChild(hidden);
                }
                hidden.value = activeSessionToken;
            }
        }, true);
    }

    // 1. Bootstrap 5 Form Validation
    const forms = document.querySelectorAll('.needs-validation');
    Array.from(forms).forEach(function (form) {
        form.addEventListener('submit', function (event) {
            if (!form.checkValidity()) {
                event.preventDefault();
                event.stopPropagation();
            }
            form.classList.add('was-validated');
        }, false);
    });

    // 2. Auto-dismiss flash messages after 6 seconds
    const alerts = document.querySelectorAll('.alert-dismissible');
    alerts.forEach(function (alert) {
        setTimeout(function () {
            if (typeof bootstrap !== 'undefined' && bootstrap.Alert) {
                const bsAlert = bootstrap.Alert.getOrCreateInstance(alert);
                if (bsAlert) {
                    bsAlert.close();
                }
            }
        }, 6000);
    });

    // 3. Image Upload Validation & Immediate Client-Side Preview
    const MAX_IMAGE_BYTES = 5 * 1024 * 1024;
    const ALLOWED_IMAGE_TYPES = ['image/jpeg', 'image/png', 'image/webp'];
    const ALLOWED_IMAGE_EXTS = ['.jpg', '.jpeg', '.png', '.webp'];

    function formatBytes(bytes) {
        if (bytes < 1024) return bytes + ' B';
        if (bytes < 1024 * 1024) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / (1024 * 1024)).toFixed(2) + ' MB';
    }

    function escapeHtml(str) {
        return String(str || '')
            .replace(/&/g, '&amp;')
            .replace(/</g, '&lt;')
            .replace(/>/g, '&gt;')
            .replace(/"/g, '&quot;')
            .replace(/'/g, '&#39;');
    }

    function restoreExistingPreview(previewBox) {
        if (!previewBox) return;
        const existingSrc = previewBox.getAttribute('data-existing-src') || '';
        const existingName = previewBox.getAttribute('data-existing-name') || 'Current photo';
        if (existingSrc) {
            previewBox.classList.remove('d-none');
            previewBox.innerHTML =
                '<img src="' + escapeHtml(existingSrc) + '" alt="' + escapeHtml(existingName) + '" class="item-edit-preview" data-fallback-kind="edit">' +
                '<div class="min-w-0">' +
                '<div class="fw-semibold text-dark small text-truncate">' + escapeHtml(existingName) + '</div>' +
                '<div class="font-meta text-muted small">Current uploaded photo &middot; Leave empty to keep</div>' +
                '</div>';
        } else {
            previewBox.classList.add('d-none');
            previewBox.innerHTML = '';
        }
    }

    const imageInputs = document.querySelectorAll('input[type="file"][accept*="image"]');
    imageInputs.forEach(function (input) {
        const container = input.closest('.col-12, .col-md-6, .mb-3') || input.parentElement;
        const previewBox = container ? container.querySelector('[data-image-preview-box]') : null;

        input.addEventListener('change', function (e) {
            const oldWarning = container ? container.querySelector('.file-upload-warning') : null;
            if (oldWarning) {
                oldWarning.remove();
            }

            const file = e.target.files && e.target.files[0];
            if (!file) {
                restoreExistingPreview(previewBox);
                return;
            }

            const fileNameLower = (file.name || '').toLowerCase();
            const hasAllowedExt = ALLOWED_IMAGE_EXTS.some(function (ext) {
                return fileNameLower.endsWith(ext);
            });
            const hasAllowedMime = !file.type || ALLOWED_IMAGE_TYPES.includes(file.type.toLowerCase());

            if (!hasAllowedExt || !hasAllowedMime) {
                const warning = document.createElement('div');
                warning.className = 'alert alert-danger py-2 px-3 mt-2 mb-0 small file-upload-warning';
                warning.setAttribute('role', 'alert');
                warning.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> Unsupported file format. Allowed formats: JPG, JPEG, PNG, WEBP.';
                input.insertAdjacentElement('afterend', warning);
                input.value = '';
                restoreExistingPreview(previewBox);
                return;
            }

            if (file.size <= 0 || file.size > MAX_IMAGE_BYTES) {
                const warning = document.createElement('div');
                warning.className = 'alert alert-danger py-2 px-3 mt-2 mb-0 small file-upload-warning';
                warning.setAttribute('role', 'alert');
                warning.innerHTML = '<i class="fa-solid fa-triangle-exclamation me-1"></i> File size exceeds the 5MB limit. Please choose a smaller image.';
                input.insertAdjacentElement('afterend', warning);
                input.value = '';
                restoreExistingPreview(previewBox);
                return;
            }

            if (previewBox) {
                const reader = new FileReader();
                reader.onload = function (loadEvent) {
                    const resultUrl = loadEvent.target && loadEvent.target.result ? String(loadEvent.target.result) : '';
                    if (!resultUrl) return;
                    previewBox.classList.remove('d-none');
                    previewBox.innerHTML =
                        '<img src="' + escapeHtml(resultUrl) + '" alt="Selected image preview" class="item-edit-preview">' +
                        '<div class="min-w-0">' +
                        '<div class="fw-semibold text-dark small text-truncate">' + escapeHtml(file.name) + '</div>' +
                        '<div class="font-meta text-primary small">' + formatBytes(file.size) + ' &middot; Ready to upload</div>' +
                        '</div>';
                };
                reader.readAsDataURL(file);
            }
        });
    });

    // 4. Graceful fallback if a stored image file is missing on disk
    document.addEventListener('error', function (e) {
        const target = e.target;
        if (!target || target.tagName !== 'IMG') return;
        const kind = target.getAttribute('data-fallback-kind');
        if (!kind) return;

        const iconClass = target.getAttribute('data-fallback-icon') || 'fa-regular fa-image';
        const fallback = document.createElement('div');

        if (kind === 'thumb') {
            fallback.className = 'item-thumb-placeholder';
            fallback.innerHTML = '<i class="' + escapeHtml(iconClass.indexOf('fa-') === 0 && iconClass.indexOf(' ') === -1 ? 'fa-solid ' + iconClass : iconClass) + '"></i>';
            target.replaceWith(fallback);
        } else if (kind === 'detail') {
            const text = target.getAttribute('data-fallback-text') || 'Image file unavailable';
            fallback.className = 'bg-light text-muted p-5 text-center';
            fallback.innerHTML =
                '<i class="' + escapeHtml(iconClass.indexOf('fa-') === 0 && iconClass.indexOf(' ') === -1 ? 'fa-solid ' + iconClass : iconClass) + ' fs-1 d-block mb-2 text-secondary"></i>' +
                '<span class="font-meta small">' + escapeHtml(text) + '</span>';
            target.replaceWith(fallback);
        } else if (kind === 'edit') {
            fallback.className = 'item-thumb-placeholder';
            fallback.style.width = '76px';
            fallback.style.height = '76px';
            fallback.innerHTML = '<i class="fa-regular fa-image"></i>';
            target.replaceWith(fallback);
        }
    }, true);

    // 5. Quick key listener for search input focus ('/' key)
    document.addEventListener('keydown', function (e) {
        if (e.key === '/' && document.activeElement.tagName !== 'INPUT' && document.activeElement.tagName !== 'TEXTAREA') {
            const searchInput = document.querySelector('input[name="search"]');
            if (searchInput) {
                e.preventDefault();
                searchInput.focus();
            }
        }
    });

    // 6. Debounced Automatic Live Search (e.g., Activity Logs)
    const liveSearchForms = document.querySelectorAll('[data-live-search]');
    liveSearchForms.forEach(function (form) {
        const input = form.querySelector('input[name="search"]');
        const searchBox = form.querySelector('[data-live-search-box]');
        const clearBtn = form.querySelector('[data-live-search-clear]');
        const targetSelector = form.getAttribute('data-results-target');
        const resultsEl = targetSelector ? document.querySelector(targetSelector) : null;

        if (!input || !resultsEl) return;

        const baseAction = form.getAttribute('action') || window.location.pathname;
        const initialParams = new URLSearchParams(window.location.search);
        const initialPage = Math.max(1, parseInt(initialParams.get('page') || '1', 10) || 1);
        let lastFetchedKey = input.value.trim() + '|' + initialPage;
        let debounceTimer = null;
        let activeController = null;

        function updateClearVisibility() {
            if (!clearBtn) return;
            if (input.value.length > 0) {
                clearBtn.classList.remove('d-none');
            } else {
                clearBtn.classList.add('d-none');
            }
        }

        function setLoading(isLoading) {
            if (searchBox) searchBox.classList.toggle('is-loading', isLoading);
            resultsEl.classList.toggle('is-loading', isLoading);
        }

        function showInlineError(message) {
            const existingErr = resultsEl.querySelector('.live-search-error-banner');
            if (existingErr) existingErr.remove();
            const errDiv = document.createElement('div');
            errDiv.className = 'alert alert-danger m-3 mb-0 d-flex align-items-center justify-content-between gap-2 live-search-error-banner';
            errDiv.setAttribute('role', 'alert');
            errDiv.innerHTML =
                '<div class="small"><i class="fa-solid fa-triangle-exclamation me-2"></i>' + escapeHtml(message) + '</div>' +
                '<button type="button" class="btn btn-sm btn-outline-danger py-1 px-2" data-live-search-retry>Retry</button>';
            resultsEl.prepend(errDiv);
        }

        function executeSearch(rawQuery, page, force) {
            const q = String(rawQuery || '').trim();
            const p = Math.max(1, parseInt(page || '1', 10) || 1);
            const requestKey = q + '|' + p;

            if (!force && requestKey === lastFetchedKey) {
                return;
            }

            if (activeController) {
                activeController.abort();
            }
            const controller = new AbortController();
            activeController = controller;

            setLoading(true);

            const requestUrl = new URL(baseAction, window.location.origin);
            requestUrl.searchParams.set('ajax', '1');
            if (q !== '') {
                requestUrl.searchParams.set('search', q);
            }
            if (p > 1) {
                requestUrl.searchParams.set('page', String(p));
            }
            if (activeSessionToken) {
                requestUrl.searchParams.set('_st', activeSessionToken);
            }

            const fetchHeaders = {
                'X-Requested-With': 'XMLHttpRequest',
                'Accept': 'application/json'
            };
            if (activeSessionToken) {
                fetchHeaders['X-FoundU-Token'] = activeSessionToken;
            }

            fetch(requestUrl.toString(), {
                method: 'GET',
                signal: controller.signal,
                headers: fetchHeaders
            })
                .then(function (response) {
                    if (!response.ok) {
                        throw new Error('Unable to load matching records (HTTP ' + response.status + ').');
                    }
                    const ct = response.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) {
                        throw new Error('Session expired or unexpected response. Please refresh the page.');
                    }
                    return response.json();
                })
                .then(function (data) {
                    if (!data || !data.ok || typeof data.html !== 'string') {
                        throw new Error((data && data.error) || 'Failed to retrieve activity logs.');
                    }

                    resultsEl.innerHTML = data.html;
                    applySessionTokenToDom(resultsEl);
                    lastFetchedKey = q + '|' + (data.page || p);

                    const cleanUrl = new URL(baseAction, window.location.origin);
                    if (q !== '') {
                        cleanUrl.searchParams.set('search', q);
                    }
                    if ((data.page || p) > 1) {
                        cleanUrl.searchParams.set('page', String(data.page || p));
                    }
                    if (activeSessionToken) {
                        cleanUrl.searchParams.set('_st', activeSessionToken);
                    }
                    if (window.history && window.history.replaceState) {
                        window.history.replaceState({}, '', cleanUrl.pathname + cleanUrl.search);
                    }
                })
                .catch(function (err) {
                    if (err && err.name === 'AbortError') return;
                    showInlineError((err && err.message) || 'Network error while searching activity logs.');
                })
                .finally(function () {
                    if (activeController === controller) {
                        activeController = null;
                        setLoading(false);
                    }
                });
        }

        updateClearVisibility();

        input.addEventListener('input', function () {
            updateClearVisibility();
            if (debounceTimer) clearTimeout(debounceTimer);
            debounceTimer = setTimeout(function () {
                executeSearch(input.value, 1, false);
            }, 300);
        });

        form.addEventListener('submit', function (e) {
            e.preventDefault();
            if (debounceTimer) clearTimeout(debounceTimer);
            executeSearch(input.value, 1, false);
        });

        if (clearBtn) {
            clearBtn.addEventListener('click', function () {
                if (debounceTimer) clearTimeout(debounceTimer);
                input.value = '';
                updateClearVisibility();
                input.focus();
                executeSearch('', 1, false);
            });
        }

        resultsEl.addEventListener('click', function (e) {
            const retryBtn = e.target.closest('[data-live-search-retry]');
            if (retryBtn) {
                e.preventDefault();
                executeSearch(input.value, 1, true);
                return;
            }

            const pageLink = e.target.closest('.pagination a.page-link[href]');
            if (!pageLink) return;
            const parentItem = pageLink.closest('.page-item');
            if (parentItem && (parentItem.classList.contains('disabled') || parentItem.classList.contains('active'))) {
                e.preventDefault();
                return;
            }

            e.preventDefault();
            const targetUrl = new URL(pageLink.getAttribute('href'), window.location.origin);
            const targetPage = Math.max(1, parseInt(targetUrl.searchParams.get('page') || '1', 10) || 1);
            executeSearch(input.value, targetPage, false);
        });
    });

    // 7. Bootstrap Tooltips (Top Icon-Only Navbar & Action Controls)
    if (typeof bootstrap !== 'undefined' && bootstrap.Tooltip) {
        const tooltipTriggers = document.querySelectorAll('[data-bs-toggle="tooltip"]');
        tooltipTriggers.forEach(function (el) {
            bootstrap.Tooltip.getOrCreateInstance(el);
        });
    }

    // 8. Logout Confirmation Modal & Generic Action Confirmation Modal
    const logoutModalEl = document.getElementById('logoutConfirmModal');
    const confirmLogoutLink = document.getElementById('confirmLogoutLink');
    if (confirmLogoutLink) {
        confirmLogoutLink.addEventListener('click', function () {
            try { sessionStorage.removeItem('foundu_st'); } catch (_) {}
        });
    }
    document.addEventListener('click', function (e) {
        const logoutBtn = e.target.closest('[data-logout-trigger]');
        if (!logoutBtn) return;
        e.preventDefault();
        if (logoutModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            bootstrap.Modal.getOrCreateInstance(logoutModalEl).show();
        } else {
            try { sessionStorage.removeItem('foundu_st'); } catch (_) {}
            window.location.href = (confirmLogoutLink && confirmLogoutLink.getAttribute('href')) || '/logout.php';
        }
    });

    const confirmActionModalEl = document.getElementById('confirmActionModal');
    const confirmActionMessageEl = document.getElementById('confirmActionModalMessage');
    const confirmActionProceedBtn = document.getElementById('confirmActionProceedBtn');
    let pendingConfirmForm = null;

    if (confirmActionProceedBtn) {
        confirmActionProceedBtn.addEventListener('click', function () {
            const formToSubmit = pendingConfirmForm;
            pendingConfirmForm = null;
            if (confirmActionModalEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
                bootstrap.Modal.getOrCreateInstance(confirmActionModalEl).hide();
            }
            if (formToSubmit) {
                formToSubmit.dataset.confirmedSubmit = '1';
                formToSubmit.submit();
            }
        });
    }

    document.addEventListener('submit', function (e) {
        const form = e.target;
        if (!form || !form.getAttribute) return;
        const msg = form.getAttribute('data-confirm');
        if (!msg || form.dataset.confirmedSubmit === '1') {
            return;
        }
        e.preventDefault();
        pendingConfirmForm = form;
        if (confirmActionModalEl && confirmActionMessageEl && typeof bootstrap !== 'undefined' && bootstrap.Modal) {
            confirmActionMessageEl.textContent = msg;
            bootstrap.Modal.getOrCreateInstance(confirmActionModalEl).show();
        } else {
            form.dataset.confirmedSubmit = '1';
            form.submit();
        }
    });

    // 9. Real-Time Role-Based Notification Polling & Actions
    const notificationsApiUrl = document.body.getAttribute('data-notifications-api');
    const csrfMeta = document.querySelector('meta[name="csrf-token"]');
    const csrfToken = csrfMeta ? csrfMeta.getAttribute('content') || '' : '';

    if (notificationsApiUrl) {
        const navBadgeEl = document.getElementById('navNotificationBadge');
        const toastContainerEl = document.getElementById('notificationToastContainer');
        const notificationsPageEl = document.querySelector('[data-notifications-page]');
        const listContainerEl = document.getElementById('notificationsListContainer');
        const pageUnreadBadgeEl = document.getElementById('pageUnreadBadge');
        const pageUnreadCountEl = document.querySelector('[data-page-unread-count]');
        const markAllBtnEl = document.querySelector('[data-mark-all-read-btn]');

        let knownLatestId = notificationsPageEl
            ? (parseInt(notificationsPageEl.getAttribute('data-latest-id') || '0', 10) || 0)
            : 0;
        let initializedLatestId = !!notificationsPageEl;
        let pollInFlight = false;

        function updateUnreadUI(unreadCount) {
            const count = Math.max(0, parseInt(unreadCount || 0, 10) || 0);
            if (navBadgeEl) {
                if (count > 0) {
                    navBadgeEl.textContent = count > 99 ? '99+' : String(count);
                    navBadgeEl.classList.remove('d-none');
                } else {
                    navBadgeEl.textContent = '0';
                    navBadgeEl.classList.add('d-none');
                }
            }
            if (pageUnreadCountEl) {
                pageUnreadCountEl.textContent = String(count);
            }
            if (pageUnreadBadgeEl) {
                pageUnreadBadgeEl.classList.toggle('status-badge-matched', count > 0);
                pageUnreadBadgeEl.classList.toggle('status-badge-inactive', count === 0);
            }
            if (markAllBtnEl) {
                markAllBtnEl.disabled = count === 0;
                markAllBtnEl.classList.toggle('disabled', count === 0);
            }
        }

        function showNotificationToast(notif) {
            if (!toastContainerEl || !notif) return;
            const toast = document.createElement('div');
            toast.className = 'notification-toast';
            const roleClass = notif.sender_role === 'admin' ? 'role-badge-admin' : 'role-badge-staff';
            const iconClass = notif.related_item_type === 'found_item' ? 'fa-box-open' : 'fa-circle-question';
            toast.innerHTML =
                '<div class="d-flex align-items-start justify-content-between gap-2">' +
                    '<div class="d-flex align-items-start gap-2 min-w-0">' +
                        '<div class="notification-icon-wrap" style="width: 34px; height: 34px; font-size: 0.85rem;">' +
                            '<i class="fa-solid ' + iconClass + '"></i>' +
                        '</div>' +
                        '<div class="min-w-0">' +
                            '<div class="d-flex align-items-center gap-2 mb-1">' +
                                '<strong class="font-display small text-dark">' + escapeHtml(notif.title) + '</strong>' +
                                '<span class="badge ' + roleClass + '">' + escapeHtml(String(notif.sender_role || '').toUpperCase()) + '</span>' +
                            '</div>' +
                            '<div class="small text-secondary mb-2">' + escapeHtml(notif.message) + '</div>' +
                            '<a href="' + escapeHtml(appendSessionToken(notif.open_url)) + '" class="btn btn-soft-primary btn-sm px-2">' +
                                '<i class="fa-solid fa-eye"></i> View ' + escapeHtml(notif.related_item_label) +
                            '</a>' +
                        '</div>' +
                    '</div>' +
                    '<button type="button" class="btn-close btn-sm" aria-label="Close"></button>' +
                '</div>';

            const closeBtn = toast.querySelector('.btn-close');
            if (closeBtn) {
                closeBtn.addEventListener('click', function () {
                    toast.remove();
                });
            }

            toastContainerEl.appendChild(toast);
            setTimeout(function () {
                if (toast.parentNode) {
                    toast.remove();
                }
            }, 6500);
        }

        function renderNotificationsListHtml(notifications) {
            if (!listContainerEl) return;
            if (!notifications || !notifications.length) {
                return;
            }
            const cardsHtml = notifications.map(function (notif) {
                const isRead = !!notif.is_read;
                const isFound = notif.related_item_type === 'found_item';
                const iconClass = isFound ? 'fa-box-open' : 'fa-circle-question';
                const roleClass = notif.sender_role === 'admin' ? 'role-badge-admin' : 'role-badge-staff';
                const roleLabel = String(notif.sender_role || 'staff').toUpperCase();
                return '<div class="notification-card ' + (isRead ? 'is-read' : 'is-unread') + '" data-notification-id="' + Number(notif.id) + '">' +
                    '<div class="d-flex flex-column flex-md-row align-items-md-center justify-content-between gap-3">' +
                        '<div class="d-flex align-items-start gap-3 min-w-0">' +
                            '<div class="notification-icon-wrap"><i class="fa-solid ' + iconClass + '"></i></div>' +
                            '<div class="min-w-0">' +
                                '<div class="d-flex flex-wrap align-items-center gap-2 mb-1">' +
                                    (!isRead ? '<span class="notification-unread-dot" title="Unread" data-unread-dot></span>' : '') +
                                    '<h6 class="font-display fw-semibold text-dark mb-0">' + escapeHtml(notif.title) + '</h6>' +
                                    '<span class="badge ' + roleClass + '">' + escapeHtml(roleLabel) + '</span>' +
                                    '<span class="notification-type-badge"><i class="fa-solid ' + iconClass + '"></i><span>' + escapeHtml(notif.related_item_label) + '</span></span>' +
                                '</div>' +
                                '<p class="small text-dark mb-2">' + escapeHtml(notif.message) + ' <span class="text-muted">&middot; Item: <strong class="text-dark">' + escapeHtml(notif.related_item_name) + '</strong></span></p>' +
                                '<div class="d-flex flex-wrap align-items-center gap-3 small font-meta text-muted">' +
                                    '<span><i class="fa-solid fa-user me-1"></i>' + escapeHtml(notif.sender_name) + '</span>' +
                                    '<span><i class="fa-regular fa-clock me-1"></i>' + escapeHtml(notif.created_at_formatted) + '</span>' +
                                    '<span class="fw-semibold ' + (isRead ? 'text-muted' : 'text-primary') + '" data-read-status-label>' + (isRead ? 'Read' : 'Unread') + '</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                        '<div class="d-flex flex-wrap align-items-center gap-2 flex-shrink-0">' +
                            '<a href="' + escapeHtml(appendSessionToken(notif.open_url)) + '" class="btn btn-soft-primary btn-sm px-3"><i class="fa-solid fa-eye"></i><span>View Item</span></a>' +
                            (!isRead
                                ? '<form method="POST" action="' + escapeHtml(appendSessionToken(window.location.pathname)) + '" class="d-inline" data-mark-read-form>' +
                                    (csrfToken ? '<input type="hidden" name="csrf_token" value="' + escapeHtml(csrfToken) + '">' : '') +
                                    '<input type="hidden" name="action" value="mark_read">' +
                                    '<input type="hidden" name="notification_id" value="' + Number(notif.id) + '">' +
                                    '<button type="submit" class="btn btn-outline-secondary btn-sm px-3" data-mark-read-btn="' + Number(notif.id) + '"><i class="fa-solid fa-check"></i><span>Mark Read</span></button>' +
                                  '</form>'
                                : '') +
                        '</div>' +
                    '</div>' +
                '</div>';
            }).join('');

            listContainerEl.innerHTML = '<div class="notification-list">' + cardsHtml + '</div>';
        }

        function pollNotifications() {
            if (pollInFlight) return;
            if (document.visibilityState === 'hidden') return;
            pollInFlight = true;

            const pollUrl = new URL(notificationsApiUrl, window.location.origin);
            pollUrl.searchParams.set('action', 'poll');
            pollUrl.searchParams.set('last_id', initializedLatestId ? String(knownLatestId) : '0');
            if (notificationsPageEl) {
                pollUrl.searchParams.set('include_list', '1');
            }
            if (activeSessionToken) {
                pollUrl.searchParams.set('_st', activeSessionToken);
            }

            const pollHeaders = {
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest'
            };
            if (activeSessionToken) {
                pollHeaders['X-FoundU-Token'] = activeSessionToken;
            }

            fetch(pollUrl.toString(), {
                method: 'GET',
                headers: pollHeaders
            })
                .then(function (res) {
                    if (!res.ok) return null;
                    const ct = res.headers.get('content-type') || '';
                    if (!ct.includes('application/json')) return null;
                    return res.json();
                })
                .then(function (data) {
                    if (!data || !data.ok) return;
                    updateUnreadUI(data.unreadCount);

                    const latestId = parseInt(data.latestId || 0, 10) || 0;
                    if (!initializedLatestId) {
                        knownLatestId = latestId;
                        initializedLatestId = true;
                    } else if (latestId > knownLatestId) {
                        knownLatestId = latestId;
                        if (Array.isArray(data.newNotifications) && data.newNotifications.length > 0) {
                            data.newNotifications.slice(0, 3).forEach(function (n) {
                                showNotificationToast(n);
                            });
                        }
                        if (notificationsPageEl && Array.isArray(data.notifications)) {
                            renderNotificationsListHtml(data.notifications);
                        }
                    } else if (notificationsPageEl && Array.isArray(data.notifications)) {
                        // Keep read/unread counts synced across tabs without clobbering
                        const existingCards = listContainerEl ? listContainerEl.querySelectorAll('.notification-card').length : 0;
                        if (existingCards !== data.notifications.length) {
                            renderNotificationsListHtml(data.notifications);
                        }
                    }
                })
                .catch(function () {
                    // Fail silently on transient poll errors
                })
                .finally(function () {
                    pollInFlight = false;
                });
        }

        function postNotificationAction(params) {
            const formData = new URLSearchParams();
            if (csrfToken) formData.set('csrf_token', csrfToken);
            Object.keys(params).forEach(function (key) {
                formData.set(key, String(params[key]));
            });

            const postUrl = appendSessionToken(notificationsApiUrl);
            const postHeaders = {
                'Content-Type': 'application/x-www-form-urlencoded; charset=UTF-8',
                'Accept': 'application/json',
                'X-Requested-With': 'XMLHttpRequest',
                'X-CSRF-Token': csrfToken
            };
            if (activeSessionToken) {
                postHeaders['X-FoundU-Token'] = activeSessionToken;
            }

            return fetch(postUrl, {
                method: 'POST',
                headers: postHeaders,
                body: formData.toString()
            }).then(function (res) {
                if (!res.ok) throw new Error('Request failed');
                return res.json();
            });
        }

        if (notificationsPageEl) {
            notificationsPageEl.addEventListener('submit', function (e) {
                const markReadForm = e.target.closest('[data-mark-read-form]');
                if (markReadForm) {
                    e.preventDefault();
                    const idInput = markReadForm.querySelector('input[name="notification_id"]');
                    const notifId = idInput ? parseInt(idInput.value || '0', 10) : 0;
                    if (!notifId) return;

                    postNotificationAction({ action: 'mark_read', notification_id: notifId })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            const card = markReadForm.closest('.notification-card');
                            if (card) {
                                card.classList.remove('is-unread');
                                card.classList.add('is-read');
                                const dot = card.querySelector('[data-unread-dot]');
                                if (dot) dot.remove();
                                const statusLbl = card.querySelector('[data-read-status-label]');
                                if (statusLbl) {
                                    statusLbl.textContent = 'Read';
                                    statusLbl.classList.remove('text-primary');
                                    statusLbl.classList.add('text-muted');
                                }
                                markReadForm.remove();
                            }
                            updateUnreadUI(data.unreadCount);
                        })
                        .catch(function () {
                            markReadForm.submit();
                        });
                    return;
                }

                const markAllForm = e.target.closest('[data-mark-all-read-form]');
                if (markAllForm) {
                    e.preventDefault();
                    postNotificationAction({ action: 'mark_all_read' })
                        .then(function (data) {
                            if (!data || !data.ok) return;
                            document.querySelectorAll('.notification-card.is-unread').forEach(function (card) {
                                card.classList.remove('is-unread');
                                card.classList.add('is-read');
                                const dot = card.querySelector('[data-unread-dot]');
                                if (dot) dot.remove();
                                const statusLbl = card.querySelector('[data-read-status-label]');
                                if (statusLbl) {
                                    statusLbl.textContent = 'Read';
                                    statusLbl.classList.remove('text-primary');
                                    statusLbl.classList.add('text-muted');
                                }
                                const f = card.querySelector('[data-mark-read-form]');
                                if (f) f.remove();
                            });
                            updateUnreadUI(0);
                        })
                        .catch(function () {
                            markAllForm.submit();
                        });
                }
            });
        }

        // Initial baseline sync & lightweight 5s polling
        pollNotifications();
        setInterval(pollNotifications, 5000);
        document.addEventListener('visibilitychange', function () {
            if (document.visibilityState === 'visible') {
                pollNotifications();
            }
        });
    }
});
