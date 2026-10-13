
/**
 * =============================================================================
 * HEADSTART POSTS SLIDER (GLOBAL INITIALIZATION)
 * =============================================================================
 */
document.addEventListener('DOMContentLoaded', function() {
    function initHeadstartSliders() {
        // Only fetch Swiper on pages that actually have a slider.
        if (!document.querySelector('.hs-shortcode-slider-wrapper')) return;
        if (typeof Swiper === 'undefined') {
            if (!document.getElementById('swiper-js-script')) {
                var script = document.createElement('script');
                script.id = 'swiper-js-script';
                script.src = '/wp-content/mu-plugins/hs-swiper/swiper-bundle.min.js?ver=11.2.10';
                document.head.appendChild(script);
            }
            setTimeout(initHeadstartSliders, 100);
            return;
        }

        var wrappers = document.querySelectorAll('.hs-shortcode-slider-wrapper');
        wrappers.forEach(function(rootContainer) {
            var sliderId = rootContainer.id;
            // BULLETPROOF EVENT DELEGATION FOR NAVIGATION ARROWS
            if (!rootContainer.dataset.hsNavDelegated) {
                rootContainer.dataset.hsNavDelegated = 'true';
                rootContainer.addEventListener('click', function(e) {
                    var nextBtn = e.target.closest('.hs-next, .hs-side-next, [class*="hs-slider-next"], .swiper-button-next');
                    var prevBtn = e.target.closest('.hs-prev, .hs-side-prev, [class*="hs-slider-prev"], .swiper-button-prev');
                    if (nextBtn || prevBtn) {
                        e.preventDefault();
                        var currentContainer = rootContainer.querySelector('.swiper.hsPostSliderInit-' + rootContainer.id);
                        if (currentContainer && currentContainer.hsSwiperInstance) {
                            var sw = currentContainer.hsSwiperInstance;
                            // The user has taken control: stop autoplay so it can never move the slider against them
                            if (sw.autoplay && sw.autoplay.running) {
                                sw.autoplay.stop();
                            }
                            // If a slide is mid-animation (e.g. an autoplay step), queue the press instead of
                            // letting preventInteractionOnTransition swallow it. Max 2 queued presses.
                            sw.hsQueuedPresses = sw.hsQueuedPresses || 0;
                            if (sw.hsQueuedPresses >= 2) return;
                            sw.hsQueuedPresses++;
                            var go = function() {
                                if (sw.destroyed) { sw.hsQueuedPresses = 0; return; }
                                if (sw.animating) { sw.once('transitionEnd', go); return; }
                                sw.hsQueuedPresses--;
                                if (nextBtn) {
                                    sw.slideNext();
                                } else {
                                    sw.slidePrev();
                                }
                            };
                            go();
                        }
                    }
                });
            }
            var container = rootContainer.querySelector('.swiper.hsPostSliderInit-' + sliderId);
            if (!container || container.classList.contains('hs-swiper-loaded')) return;

            var wrapper = container.querySelector('.swiper-wrapper');
            var filterSelect = rootContainer.querySelector('select.hs-slider-grid-filter');
            var display = rootContainer.getAttribute('data-display');

            // CLIENT-SIDE DEPARTMENT CONTEXT ENFORCEMENT
            // Stamps ?hs_dept= onto card links for department-specific sliders.
            // This is cache-proof: even if the server serves stale HTML, JS patches links on the fly.
            var hsDeptSlug = rootContainer.getAttribute('data-dept');
            var hsEnforceDeptLinks = function() {
                if (!hsDeptSlug || filterSelect) return; // Skip if no dept or if this is a filter slider
                rootContainer.querySelectorAll('a.hs-story-card').forEach(function(card) {
                    var href = card.getAttribute('href');
                    if (href && href.indexOf('hs_dept=') === -1) {
                        card.setAttribute('href', href + (href.indexOf('?') !== -1 ? '&' : '?') + 'hs_dept=' + encodeURIComponent(hsDeptSlug));
                    }
                });
            };
            hsEnforceDeptLinks(); // Run on initial load

            // Cards added later (lazy loading, department reload) are stamped at the moment they are used.
            // pointerdown/focusin fire before navigation, so this also covers middle-click / open in new tab.
            if (!rootContainer.dataset.hsDeptStamp) {
                rootContainer.dataset.hsDeptStamp = 'true';
                var hsStampOnUse = function(e) {
                    var card = e.target.closest ? e.target.closest('a.hs-story-card') : null;
                    if (card) hsEnforceDeptLinks();
                };
                rootContainer.addEventListener('pointerdown', hsStampOnUse, true);
                rootContainer.addEventListener('focusin', hsStampOnUse, true);
                rootContainer.addEventListener('click', hsStampOnUse, true);
            }

            // CACHE-PROOF DEPARTMENT CONTEXT RELOAD
            // If the URL has ?hs_dept= but the server-rendered slider doesn't match
            // (because a page cache served stale HTML), force an AJAX reload with the correct department.
            var urlDept = new URLSearchParams(window.location.search).get('hs_dept');
            if (urlDept && !filterSelect && hsDeptSlug !== urlDept) {
                rootContainer.setAttribute('data-dept', urlDept); // lazy loads read this, so they follow the new department
                hsDeptSlug = urlDept; // Update for hsEnforceDeptLinks

                var reloadLoader = container.querySelector('.hs-slider-loader');
                if (reloadLoader) reloadLoader.style.display = 'flex';

                var reloadData = new URLSearchParams();
                reloadData.append('action', 'hs_load_slider_posts');
                reloadData.append('department', urlDept);
                reloadData.append('campus', rootContainer.getAttribute('data-campus') || '');
                reloadData.append('enforce_context', rootContainer.getAttribute('data-enforce-context') || 'false');
                reloadData.append('exclude_recent_days', rootContainer.getAttribute('data-exclude-days') || '0');
                reloadData.append('count', rootContainer.getAttribute('data-count') || '15');
                reloadData.append('seed', rootContainer.getAttribute('data-seed') || '');
                reloadData.append('orderby', rootContainer.getAttribute('data-orderby') || '');
                reloadData.append('exclude_id', rootContainer.getAttribute('data-exclude-id') || '');
                reloadData.append('ajax_cards_only', 'true');
                reloadData.append('display', display);

                var reloadUrl = '/wp-admin/admin-ajax.php';
                if (typeof ajaxurl !== 'undefined') reloadUrl = ajaxurl;

                fetch(reloadUrl, { method: 'POST', body: reloadData })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (!res.success || !wrapper) {
                        if (reloadLoader) reloadLoader.style.display = 'none';
                        return;
                    }
                    // The inline script (posts-slider-shortcode) builds the slider ~0.5s after load.
                    // Wait for that first build, then rebuild it with the correct department's cards.
                    var tries = 0;
                    var swapIn = function() {
                        if (!container.hsSwiperInstance && tries < 50) { tries++; setTimeout(swapIn, 100); return; }
                        if (container.hsSwiperInstance) {
                            container.hsSwiperInstance.destroy(true, true);
                            container.hsSwiperInstance = null;
                        }
                        container.className = (display === 'grid' ? 'hs-post-grid swiper ' : 'swiper ') + 'hsPostSliderInit-' + sliderId;
                        wrapper.className = 'swiper-wrapper';
                        wrapper.removeAttribute('style');
                        wrapper.innerHTML = res.data.html; // includes .hs-ajax-meta, read by the inline init
                        hsEnforceDeptLinks();
                        var initFn = window['init_hs_slider_' + sliderId];
                        if (typeof initFn === 'function') initFn();
                    };
                    swapIn();
                }).catch(function(err) {
                    console.error('Department reload error:', err);
                    if (reloadLoader) reloadLoader.style.display = 'none';
                });
            }
        });
    }
    initHeadstartSliders();

    // Divi Builder preview: sliders are re-rendered by AJAX, so attach arrow handling to the new markup
    if (typeof jQuery !== 'undefined' && typeof jQuery(document).ajaxComplete === 'function') {
        jQuery(document).ajaxComplete(function(event, xhr, settings) {
            if (settings.data && typeof settings.data === 'string' && settings.data.indexOf('et_pb_get_shortcode_preview') !== -1) {
                setTimeout(initHeadstartSliders, 500);
            }
        });
    }
});

// ==========================================
// [BESPOKE] PARENT FITNESS MODAL LOGIC
// ==========================================
window.currentFitnessCampus = window.currentFitnessCampus || 'chaofah';

window.setFitnessCampus = function(campus) {
    window.currentFitnessCampus = campus;

    // Update Tab Button States (Widget)
    const fitTabChaofah = document.getElementById('hs-fit-tab-chaofah');
    const fitTabCherngtalay = document.getElementById('hs-fit-tab-cherngtalay');
    if (fitTabChaofah) fitTabChaofah.classList.toggle('active', window.currentFitnessCampus === 'chaofah');
    if (fitTabCherngtalay) fitTabCherngtalay.classList.toggle('active', window.currentFitnessCampus === 'cherngtalay');
    
    // Update Tab Button States (Modal)
    const fitModalTabChaofah = document.getElementById('hs-fit-modal-tab-chaofah');
    const fitModalTabCherngtalay = document.getElementById('hs-fit-modal-tab-cherngtalay');
    if (fitModalTabChaofah) fitModalTabChaofah.classList.toggle('active', window.currentFitnessCampus === 'chaofah');
    if (fitModalTabCherngtalay) fitModalTabCherngtalay.classList.toggle('active', window.currentFitnessCampus === 'cherngtalay');

    // Toggle Widget Elements
    const avatarsChaofah = document.getElementById('hs-fitness-avatars-chaofah');
    const avatarsCherngtalay = document.getElementById('hs-fitness-avatars-cherngtalay');
    if (avatarsChaofah) avatarsChaofah.style.display = (window.currentFitnessCampus === 'chaofah') ? 'flex' : 'none';
    if (avatarsCherngtalay) avatarsCherngtalay.style.display = (window.currentFitnessCampus === 'chaofah') ? 'none' : 'flex';
    
    const scheduleChaofah = document.getElementById('hs-fitness-schedule-chaofah');
    const scheduleCherngtalay = document.getElementById('hs-fitness-schedule-cherngtalay');
    if (scheduleChaofah) scheduleChaofah.style.display = (window.currentFitnessCampus === 'chaofah') ? 'block' : 'none';
    if (scheduleCherngtalay) scheduleCherngtalay.style.display = (window.currentFitnessCampus === 'chaofah') ? 'none' : 'block';

    // Toggle Modal Elements
    const heroChaofah = document.getElementById('hs-fitness-hero-chaofah');
    const heroCherngtalay = document.getElementById('hs-fitness-hero-cherngtalay');
    if (heroChaofah) heroChaofah.style.display = (window.currentFitnessCampus === 'chaofah') ? 'block' : 'none';
    if (heroCherngtalay) heroCherngtalay.style.display = (window.currentFitnessCampus === 'chaofah') ? 'none' : 'block';

    const timeChaofah = document.getElementById('hs-fitness-timetable-chaofah');
    const timeCherngtalay = document.getElementById('hs-fitness-timetable-cherngtalay');
    if (timeChaofah) timeChaofah.style.display = (window.currentFitnessCampus === 'chaofah') ? 'block' : 'none';
    if (timeCherngtalay) timeCherngtalay.style.display = (window.currentFitnessCampus === 'chaofah') ? 'none' : 'block';
};

window.openFitnessModal = function() {
    const modal = document.getElementById('hs-fitness-modal');
    const container = document.getElementById('hs-fitness-modal-container');
    if(!modal || !container) return;
    
    // Move modal to body to escape Divi's z-index trapping
    if (modal.parentNode !== document.body) {
        document.body.appendChild(modal);
    }
    
    modal.style.display = 'flex';
    void modal.offsetWidth;
    
    setTimeout(() => {
        container.classList.add('is-active');
    }, 10);
};

window.closeFitnessModal = function() {
    const modal = document.getElementById('hs-fitness-modal');
    const container = document.getElementById('hs-fitness-modal-container');
    if(!modal || !container) return;
    
    container.classList.remove('is-active');
    
    setTimeout(() => {
        modal.style.display = 'none';
    }, 300);
};

// ==========================================
// EVENTS CALENDAR SIDEBAR CAMPUS TOGGLE
// ==========================================
window.hsToggleEventsCampus = function() {
    const wrapper = document.getElementById('hs-events-sidebar-wrapper');
    const chip = document.getElementById('hs-events-campus-chip');
    if (!wrapper || !chip) return;
    
    let currentCampus = wrapper.getAttribute('data-active-campus');
    
    if (currentCampus === 'chaofah') {
        currentCampus = 'cherngtalay';
    } else if (currentCampus === 'cherngtalay') {
        currentCampus = 'chaofah';
    } else {
        currentCampus = 'chaofah';
    }
    
    wrapper.setAttribute('data-active-campus', currentCampus);
    chip.innerText = currentCampus.toUpperCase();
};
