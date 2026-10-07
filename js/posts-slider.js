
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
                script.src = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.js';
                document.head.appendChild(script);
                var link = document.createElement('link');
                link.rel = 'stylesheet';
                link.href = 'https://cdn.jsdelivr.net/npm/swiper@11/swiper-bundle.min.css';
                document.head.appendChild(link);
            }
            setTimeout(initHeadstartSliders, 100);
            return;
        }

        var wrappers = document.querySelectorAll('.hs-shortcode-slider-wrapper');
        wrappers.forEach(function(rootContainer) {
            var sliderId = rootContainer.id;
            var container = rootContainer.querySelector('.swiper.hsPostSliderInit-' + sliderId);
            if (!container || container.classList.contains('hs-swiper-loaded')) return;

            var wrapper = container.querySelector('.swiper-wrapper');
            var filterSelect = rootContainer.querySelector('select.hs-slider-grid-filter');
            var display = rootContainer.getAttribute('data-display');
            var paged = 1;

            var slideNodes = container.querySelectorAll('.swiper-slide:not(.swiper-slide-duplicate)');
            var slideCount = slideNodes.length;
            var shouldLoop = slideCount > 3;

            setTimeout(function() { 
                container.classList.add('hs-swiper-loaded');
                var loader = container.querySelector('.hs-slider-loader');
                if (loader) loader.style.display = 'none'; 
            }, 1000);

            var hsLoadMorePosts = function(swiperInstance) {
                if (display !== 'slider' || swiperInstance.hsIsLoading || swiperInstance.hsNoMorePosts) return;
                
                swiperInstance.hsIsLoading = true;
                swiperInstance.hsCurrentPage++;
                
                // INJECT SKELETON CARDS
                var skeletonHTML = '<div class="swiper-slide hs-skeleton-wrapper"><div class="hs-skeleton-card hs-slider-skeleton-card"></div></div>';
                var tempSkeletonDiv = document.createElement('div');
                tempSkeletonDiv.innerHTML = skeletonHTML + skeletonHTML + skeletonHTML;
                var skeletonsArray = Array.prototype.slice.call(tempSkeletonDiv.children);
                swiperInstance.appendSlide(skeletonsArray);
                swiperInstance.update();
                swiperInstance.slideNext();
                
                var data = new URLSearchParams();
                data.append('action', 'hs_load_slider_posts');
                data.append('page', swiperInstance.hsCurrentPage);
                data.append('count', rootContainer.getAttribute('data-count') || '15');
                
                var dept = filterSelect ? filterSelect.value : (rootContainer.getAttribute('data-dept') || '');
                data.append('department', dept);
                data.append('campus', rootContainer.getAttribute('data-campus') || '');
                data.append('enforce_context', rootContainer.getAttribute('data-enforce-context') || 'false');
                data.append('enforce_dept_context', rootContainer.getAttribute('data-enforce-dept-context') || 'false');
                data.append('exclude_recent_days', rootContainer.getAttribute('data-exclude-days') || '0');
                data.append('seed', rootContainer.getAttribute('data-seed') || '');
                data.append('ajax_cards_only', 'true');
                data.append('display', display);
                
                var hs_ajaxurl = '/wp-admin/admin-ajax.php';
                if (typeof ajaxurl !== 'undefined') hs_ajaxurl = ajaxurl;
                
                // ARTIFICIAL DELAY: 500ms delay so the user can visibly see the skeleton loading animation
                setTimeout(function() {
                    fetch(hs_ajaxurl, { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(res) {
                        var currentSkeletons = swiperInstance.wrapperEl.querySelectorAll('.hs-skeleton-wrapper');
                    
                    if (res.success && res.data.html && res.data.html.trim() !== '' && res.data.html.indexOf('No posts found') === -1) {
                        var tempDiv = document.createElement('div');
                        tempDiv.innerHTML = res.data.html;
                        var slidesArray = Array.prototype.slice.call(tempDiv.children);
                        
                        // Bulletproof morph: replace skeletons with the first few real slides
                        // This prevents Swiper from jumping if the user is currently staring at a skeleton on mobile
                        var i = 0;
                        for (; i < currentSkeletons.length; i++) {
                            if (i < slidesArray.length) {
                                currentSkeletons[i].className = slidesArray[i].className;
                                currentSkeletons[i].innerHTML = slidesArray[i].innerHTML;
                            }
                        }
                        
                        // Remove any leftover skeletons (more skeletons than real slides) using Swiper API
                        if (slidesArray.length < currentSkeletons.length) {
                            var leftoverIndices = [];
                            for (var k = 0; k < swiperInstance.slides.length; k++) {
                                if (swiperInstance.slides[k].classList.contains('hs-skeleton-wrapper')) {
                                    leftoverIndices.push(k);
                                }
                            }
                            if (leftoverIndices.length > 0) {
                                swiperInstance.removeSlide(leftoverIndices);
                            }
                        }
                        
                        // Append the rest of the slides natively
                        var remainingSlides = slidesArray.slice(i);
                        if (remainingSlides.length > 0) {
                            swiperInstance.appendSlide(remainingSlides);
                        }
                        
                        swiperInstance.update();
                        hsEnforceDeptLinks();
                        swiperInstance.hsIsLoading = false;
                    } else {
                        // No more posts — slide back to last real card, THEN remove skeletons
                        var skeletonIndices = [];
                        for (var j = 0; j < swiperInstance.slides.length; j++) {
                            if (swiperInstance.slides[j].classList.contains('hs-skeleton-wrapper')) {
                                skeletonIndices.push(j);
                            }
                        }
                        // Slide to the last real card first (so user isn't staring at a skeleton when it vanishes)
                        var lastRealIndex = skeletonIndices.length > 0 ? skeletonIndices[0] - 1 : swiperInstance.slides.length - 1;
                        if (lastRealIndex >= 0) {
                            swiperInstance.slideTo(lastRealIndex, 300);
                        }
                        // After the transition completes, remove the skeletons
                        setTimeout(function() {
                            // Re-collect indices since they may have shifted
                            var freshIndices = [];
                            for (var k = 0; k < swiperInstance.slides.length; k++) {
                                if (swiperInstance.slides[k].classList.contains('hs-skeleton-wrapper')) {
                                    freshIndices.push(k);
                                }
                            }
                            if (freshIndices.length > 0) {
                                swiperInstance.removeSlide(freshIndices);
                            }
                        }, 400);
                        // No more posts! Just stop gracefully.
                        swiperInstance.hsNoMorePosts = true;
                        swiperInstance.hsIsLoading = false;
                        if (swiperInstance.autoplay && swiperInstance.autoplay.running) {
                            swiperInstance.autoplay.stop();
                        }
                    }
                }).catch(function(err) {
                    var skeletonIndices = [];
                    for (var j = 0; j < swiperInstance.slides.length; j++) {
                        if (swiperInstance.slides[j].classList.contains('hs-skeleton-wrapper')) {
                            skeletonIndices.push(j);
                        }
                    }
                    if (skeletonIndices.length > 0) {
                        var lastRealIndex = skeletonIndices[0] - 1;
                        if (lastRealIndex >= 0) {
                            swiperInstance.slideTo(lastRealIndex, 300);
                        }
                        setTimeout(function() {
                            var freshIndices = [];
                            for (var k = 0; k < swiperInstance.slides.length; k++) {
                                if (swiperInstance.slides[k].classList.contains('hs-skeleton-wrapper')) {
                                    freshIndices.push(k);
                                }
                            }
                            if (freshIndices.length > 0) {
                                swiperInstance.removeSlide(freshIndices);
                            }
                        }, 400);
                    }
                    swiperInstance.hsIsLoading = false;
                });
                }, 500); // End setTimeout
            };

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
                            
                            // Reset Swiper's autoplay timer so it doesn't jump immediately after a click
                            if (currentContainer.hsSwiperInstance.autoplay && currentContainer.hsSwiperInstance.autoplay.running) {
                                currentContainer.hsSwiperInstance.autoplay.stop();
                                currentContainer.hsSwiperInstance.autoplay.start();
                            }

                            if (nextBtn) {
                                currentContainer.hsSwiperInstance.slideNext();
                            } else {
                                currentContainer.hsSwiperInstance.slidePrev();
                            }
                        }
                    }
                });
            }

            try {
                container.hsSwiperInstance = new Swiper(container, {
                    on: {
                        init: function () {
                            container.classList.add('hs-swiper-loaded');
                            var loader = container.querySelector('.hs-slider-loader');
                            if (loader) loader.style.display = 'none';
                            this.hsCurrentPage = paged;
                            this.hsIsLoading = false;
                            
                            // Manually manage disabled states on init
                            var swp = this;
                            rootContainer.querySelectorAll('.hs-prev, .hs-side-prev').forEach(function(el) {
                                swp.isBeginning ? el.classList.add('swiper-button-disabled') : el.classList.remove('swiper-button-disabled');
                            });
                            rootContainer.querySelectorAll('.hs-next, .hs-side-next').forEach(function(el) {
                                swp.isEnd ? el.classList.add('swiper-button-disabled') : el.classList.remove('swiper-button-disabled');
                            });
                        },
                        reachEnd: function() {
                            hsLoadMorePosts(this);
                        },
                        slideChange: function() {
                            var swp = this;
                            
                            // Manually manage disabled states
                            rootContainer.querySelectorAll('.hs-prev, .hs-side-prev').forEach(function(el) {
                                swp.isBeginning ? el.classList.add('swiper-button-disabled') : el.classList.remove('swiper-button-disabled');
                            });
                            rootContainer.querySelectorAll('.hs-next, .hs-side-next').forEach(function(el) {
                                swp.isEnd ? el.classList.add('swiper-button-disabled') : el.classList.remove('swiper-button-disabled');
                            });

                            // Trigger background load if within 6 slides of the end to make it seamless
                            if (swp.slides.length - swp.activeIndex <= 6) {
                                hsLoadMorePosts(swp);
                            }
                        }
                    },
                    slidesPerView: 'auto', 
                    spaceBetween: 10,
                    centeredSlides: false,
                    loop: false,
                    autoplay: { delay: 4000, disableOnInteraction: true, pauseOnMouseEnter: true },
                    touchStartPreventDefault: false,
                    pagination: false,
                    breakpoints: {
                        640: { slidesPerView: 'auto', centeredSlides: false },
                        768: { slidesPerView: 'auto', centeredSlides: false, enabled: (display === 'slider') },
                        1024: { slidesPerView: 'auto', centeredSlides: false, enabled: (display === 'slider') },
                        1400: { slidesPerView: 'auto', centeredSlides: false, enabled: (display === 'slider') }
                    }
                });
            } catch (e) { console.error('Swiper instantiation error:', e); }

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

            // CACHE-PROOF DEPARTMENT CONTEXT RELOAD
            // If the URL has ?hs_dept= but the server-rendered slider doesn't match
            // (because a page cache served stale HTML), force an AJAX reload with the correct department.
            var urlParams = new URLSearchParams(window.location.search);
            var urlDept = urlParams.get('hs_dept');
            if (urlDept && !filterSelect && hsDeptSlug !== urlDept) {
                // The URL says "foundation" but the slider was rendered without that filter — reload it
                rootContainer.setAttribute('data-dept', urlDept);
                hsDeptSlug = urlDept; // Update for hsEnforceDeptLinks

                var loader = container.querySelector('.hs-slider-loader');
                if (loader) loader.style.display = 'flex';
                container.classList.remove('hs-swiper-loaded');

                var reloadData = new URLSearchParams();
                reloadData.append('action', 'hs_load_slider_posts');
                reloadData.append('department', urlDept);
                reloadData.append('campus', rootContainer.getAttribute('data-campus') || '');
                reloadData.append('enforce_context', rootContainer.getAttribute('data-enforce-context') || 'false');
                reloadData.append('enforce_dept_context', 'true');
                reloadData.append('exclude_recent_days', rootContainer.getAttribute('data-exclude-days') || '0');
                reloadData.append('count', rootContainer.getAttribute('data-count') || '15');
                reloadData.append('seed', rootContainer.getAttribute('data-seed') || '');
                reloadData.append('ajax_cards_only', 'true');
                reloadData.append('display', display);

                var hs_ajaxurl = '/wp-admin/admin-ajax.php';
                if (typeof ajaxurl !== 'undefined') hs_ajaxurl = ajaxurl;

                fetch(hs_ajaxurl, { method: 'POST', body: reloadData })
                .then(function(res) { return res.json(); })
                .then(function(res) {
                    if (res.success && wrapper) {
                        if (container.hsSwiperInstance) {
                            container.hsSwiperInstance.destroy(true, true);
                            container.hsSwiperInstance = null;
                        }
                        container.className = 'swiper hsPostSliderInit-' + sliderId;
                        wrapper.className = 'swiper-wrapper';
                        wrapper.removeAttribute('style');
                        wrapper.innerHTML = res.data.html;
                        setTimeout(initHeadstartSliders, 50);
                    } else {
                        if (loader) loader.style.display = 'none';
                        container.classList.add('hs-swiper-loaded');
                    }
                }).catch(function(err) {
                    if (loader) loader.style.display = 'none';
                    container.classList.add('hs-swiper-loaded');
                });
            }

            if (filterSelect && !filterSelect.dataset.listenerAttached) {
                filterSelect.dataset.listenerAttached = 'true';
                filterSelect.addEventListener('change', function(e) {
                    var dept = this.value;
                    var campus = rootContainer.getAttribute('data-campus') || '';
                    var enforceContext = rootContainer.getAttribute('data-enforce-context') || 'false';
                    var enforceDeptContext = rootContainer.getAttribute('data-enforce-dept-context') || 'false';
                    var excludeDays = rootContainer.getAttribute('data-exclude-days') || '0';
                    var count = rootContainer.getAttribute('data-count') || '-1';
                    var title = rootContainer.getAttribute('data-title') || '';
                    var seed = rootContainer.getAttribute('data-seed') || '';
                    
                    var loader = container.querySelector('.hs-slider-loader');
                    if (loader) loader.style.display = 'flex';
                    container.classList.remove('hs-swiper-loaded');
                    
                    var data = new URLSearchParams();
                    data.append('action', 'hs_load_slider_posts');
                    data.append('department', dept);
                    data.append('campus', campus);
                    data.append('enforce_context', enforceContext);
                    data.append('enforce_dept_context', enforceDeptContext);
                    data.append('exclude_recent_days', excludeDays);
                    data.append('count', count);
                    data.append('title', title);
                    data.append('seed', seed);
                    data.append('ajax_cards_only', 'true');
                    data.append('display', display);
                    
                    var hs_ajaxurl = '/wp-admin/admin-ajax.php';
                    if (typeof ajaxurl !== 'undefined') hs_ajaxurl = ajaxurl;

                    fetch(hs_ajaxurl, { method: 'POST', body: data })
                    .then(function(res) { return res.json(); })
                    .then(function(res) {
                        if (res.success && wrapper) {
                            if (container.hsSwiperInstance) {
                                container.hsSwiperInstance.destroy(true, true);
                                container.hsSwiperInstance = null;
                            }
                            container.className = 'swiper hsPostSliderInit-' + sliderId;
                            wrapper.className = 'swiper-wrapper';
                            wrapper.removeAttribute('style');
                            wrapper.innerHTML = res.data.html;
                            setTimeout(initHeadstartSliders, 50);
                        } else {
                            if (loader) loader.style.display = 'none';
                            container.classList.add('hs-swiper-loaded');
                        }
                    }).catch(function(err) {
                        if (loader) loader.style.display = 'none';
                        container.classList.add('hs-swiper-loaded');
                    });
                });
            }
        });
    }

    setTimeout(initHeadstartSliders, 50);

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
