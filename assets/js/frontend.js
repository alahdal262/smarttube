(function() {
    'use strict';

    // ── Detect mobile ──
    var isMobile = /Android|iPhone|iPad|iPod|webOS|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);

    // ── Lightbox (desktop) ──
    var lightbox = document.createElement('div');
    lightbox.className = 'stube-lightbox';
    lightbox.innerHTML = '<div class="stube-lightbox-inner">' +
        '<button class="stube-lightbox-close" aria-label="Close">&times;</button>' +
        '<div class="stube-lightbox-video"><iframe id="stube-player" src="" allowfullscreen allow="autoplay; encrypted-media; picture-in-picture"></iframe></div>' +
        '</div>';
    document.body.appendChild(lightbox);

    function openLightbox(videoId) {
        var iframe = document.getElementById('stube-player');
        iframe.src = 'https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0&playsinline=1';
        lightbox.classList.add('active');
        document.body.style.overflow = 'hidden';
    }

    function closeLightbox() {
        lightbox.classList.remove('active');
        document.getElementById('stube-player').src = '';
        document.body.style.overflow = '';
    }

    // ── PiP Popup (mobile) ──
    var pipContainer = null;
    var pipDragData = {};

    function openPiP(videoId) {
        closePiP(); // close any existing
        pipContainer = document.createElement('div');
        pipContainer.className = 'stube-pip';
        pipContainer.innerHTML =
            '<div class="stube-pip-header">' +
                '<span class="stube-pip-drag-handle">&#9776;</span>' +
                '<button class="stube-pip-close" aria-label="Close">&times;</button>' +
            '</div>' +
            '<div class="stube-pip-video">' +
                '<iframe src="https://www.youtube.com/embed/' + videoId + '?autoplay=1&rel=0&playsinline=1" ' +
                'allowfullscreen allow="autoplay; encrypted-media; picture-in-picture" frameborder="0"></iframe>' +
            '</div>';
        document.body.appendChild(pipContainer);

        // Close button
        pipContainer.querySelector('.stube-pip-close').addEventListener('click', closePiP);

        // Draggable on touch
        var header = pipContainer.querySelector('.stube-pip-header');
        header.addEventListener('touchstart', pipTouchStart, { passive: false });
        header.addEventListener('touchmove', pipTouchMove, { passive: false });
        header.addEventListener('touchend', pipTouchEnd);

        // Also draggable on mouse (for tablet)
        header.addEventListener('mousedown', pipMouseDown);
    }

    function closePiP() {
        if (pipContainer) {
            pipContainer.remove();
            pipContainer = null;
        }
    }

    // Touch drag
    function pipTouchStart(e) {
        var touch = e.touches[0];
        var rect = pipContainer.getBoundingClientRect();
        pipDragData = { startX: touch.clientX - rect.left, startY: touch.clientY - rect.top, dragging: true };
    }
    function pipTouchMove(e) {
        if (!pipDragData.dragging) return;
        e.preventDefault();
        var touch = e.touches[0];
        var x = touch.clientX - pipDragData.startX;
        var y = touch.clientY - pipDragData.startY;
        // Clamp within viewport
        x = Math.max(0, Math.min(window.innerWidth - pipContainer.offsetWidth, x));
        y = Math.max(0, Math.min(window.innerHeight - pipContainer.offsetHeight, y));
        pipContainer.style.left = x + 'px';
        pipContainer.style.top = y + 'px';
        pipContainer.style.right = 'auto';
        pipContainer.style.bottom = 'auto';
    }
    function pipTouchEnd() { pipDragData.dragging = false; }

    // Mouse drag
    function pipMouseDown(e) {
        var rect = pipContainer.getBoundingClientRect();
        pipDragData = { startX: e.clientX - rect.left, startY: e.clientY - rect.top, dragging: true };
        function onMove(ev) {
            if (!pipDragData.dragging) return;
            var x = ev.clientX - pipDragData.startX;
            var y = ev.clientY - pipDragData.startY;
            x = Math.max(0, Math.min(window.innerWidth - pipContainer.offsetWidth, x));
            y = Math.max(0, Math.min(window.innerHeight - pipContainer.offsetHeight, y));
            pipContainer.style.left = x + 'px';
            pipContainer.style.top = y + 'px';
            pipContainer.style.right = 'auto';
            pipContainer.style.bottom = 'auto';
        }
        function onUp() {
            pipDragData.dragging = false;
            document.removeEventListener('mousemove', onMove);
            document.removeEventListener('mouseup', onUp);
        }
        document.addEventListener('mousemove', onMove);
        document.addEventListener('mouseup', onUp);
    }

    // ── Video click handler ──
    document.addEventListener('click', function(e) {
        var link = e.target.closest('.stube-thumb-link');
        if (!link) return;

        var mode = link.getAttribute('data-play-mode');
        var videoId = link.getAttribute('data-video-id');

        if (!videoId) return;

        // Always prevent default for lightbox and inline modes
        if (mode === 'lightbox' || mode === 'inline') {
            e.preventDefault();

            if (isMobile) {
                // Mobile: open PiP popup
                openPiP(videoId);
            } else {
                // Desktop: open lightbox
                openLightbox(videoId);
            }
        }
        // mode === 'newtab' → let the normal <a> navigation happen
    });

    // Close lightbox
    lightbox.querySelector('.stube-lightbox-close').addEventListener('click', closeLightbox);
    lightbox.addEventListener('click', function(e) { if (e.target === lightbox) closeLightbox(); });
    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') {
            closeLightbox();
            closePiP();
        }
    });

    // ── Tab Switching ──
    document.addEventListener('click', function(e) {
        var tab = e.target.closest('.stube-tab');
        if (!tab) return;

        var wrap = tab.closest('.stube-tabs-wrap');
        if (!wrap) return;

        var tabName = tab.getAttribute('data-tab');
        var playlistId = tab.getAttribute('data-playlist');

        // Activate tab button
        wrap.querySelectorAll('.stube-tab').forEach(function(t) { t.classList.remove('active'); });
        tab.classList.add('active');

        // Show corresponding panel
        wrap.querySelectorAll('.stube-tab-panel').forEach(function(p) { p.style.display = 'none'; });
        var panel = wrap.querySelector('.stube-tab-panel[data-panel="' + tabName + '"]');
        if (panel) {
            panel.style.display = 'block';

            // If panel has loading placeholder, fetch videos via AJAX
            var loading = panel.querySelector('.stube-tab-loading');
            if (loading && playlistId && playlistId !== 'week') {
                loading.textContent = 'Loading...';

                var formData = new FormData();
                formData.append('action', 'stube_load_tab');
                formData.append('playlist_id', playlistId);

                fetch(getAjaxUrl(), {
                    method: 'POST',
                    body: formData
                })
                .then(function(r) { return r.text(); })
                .then(function(html) {
                    panel.innerHTML = html;
                })
                .catch(function() {
                    loading.textContent = 'Error loading videos.';
                });
            }
        }
    });

    function getAjaxUrl() {
        if (window.stubeData && window.stubeData.ajaxUrl) return window.stubeData.ajaxUrl;
        if (window.stubeAdmin && window.stubeAdmin.ajaxUrl) return window.stubeAdmin.ajaxUrl;
        return window.location.origin + '/wp-admin/admin-ajax.php';
    }

})();
