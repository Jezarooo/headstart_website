
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


            try {
                // Swiper instantiation deferred to inline script in posts-slider-shortcode.code-snippets.php
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
        });
    }
    initHeadstartSliders();
});
