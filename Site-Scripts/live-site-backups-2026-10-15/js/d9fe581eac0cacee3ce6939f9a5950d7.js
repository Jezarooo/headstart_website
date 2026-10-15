
/**
 * ============================================================================
 * HEADSTART WEEKLY UPDATE & POST JS LOGIC (V5)
 * ============================================================================
 * This script handles the interactive elements for both Weekly Updates and 
 * individual WordPress Posts.
 * 
 * 1. DOM Cleanup: Converts erratic `<br>` tags inside Gutenberg lists into 
 *    controlled CSS spacer blocks.
 * 2. Magnific Popup (Lightbox): Initializes the gallery slider on images. 
 *    - Uses a robust initialization function that waits for Divi to load the 
 *      Lightbox library asynchronously.
 *    - Scoped to `.hs-weekly-article` so it functions perfectly on both 
 *      Weekly Updates and single Posts.
 *    - Includes an AJAX MutationObserver to automatically re-bind the lightbox 
 *      when new posts are loaded dynamically (e.g., via archive pagination).
 *    - Mirrors the "Mosaic Gallery" design with embedded titles and captions.
 */

jQuery(document).ready(function($) {
    const wrapperSelector = '.hs-post-content-sandbox';
    const wrapper = document.querySelector(wrapperSelector);

    if (wrapper && !wrapper.dataset.hsWeeklyInit) {
        wrapper.dataset.hsWeeklyInit = '1';

        // 1. Fix erratic <br> spacing inside lists by converting them to spacer blocks
        const listBrs = Array.from(wrapper.querySelectorAll('li br'));
        listBrs.forEach(br => {
            const spacer = document.createElement('div');
            spacer.className = 'hs-br-spacer';
            br.parentNode.replaceChild(spacer, br);
        });

        // (Removed the DOM manipulation for .hs-section-card and .hs-story-divider, 
        //  since they are now hardcoded into the HTML payload)
    }

    /**
     * INITIALIZE MAGNIFIC POPUP (Robust Divi-waiting version)
     * Scoped to .hs-weekly-article so it works on both Weekly Updates and Single Posts
     */
    function initWeeklyLightboxes() {
        // Wait for Divi's Magnific Popup library to load
        if (typeof jQuery === 'undefined' || !jQuery.fn.magnificPopup) {
            setTimeout(initWeeklyLightboxes, 100);
            return;
        }

        var $ = jQuery;
        
        // Dynamically wrap native Divi Image modules so they integrate with our gallery slider
        $('.hs-weekly-article .et_pb_image img').each(function() {
            var $img = $(this);
            // Abort if the image is already a link (e.g., user added a custom URL in Divi or we already wrapped it)
            if ($img.closest('a').length > 0) return;
            
            var fullResUrl = $img.attr('src');
            $img.wrap('<a href="' + fullResUrl + '" class="hs-gallery-link" style="display:inline-block; line-height: 0;"></a>');
        });
        
        // Target the article wrapper to group ALL images in the post/story into one continuous gallery slider
        $('.hs-weekly-article, .weekly-card, .hs-gallery-grid').each(function() {
            if ($(this).hasClass('hs-lightbox-initialized')) return;
            $(this).addClass('hs-lightbox-initialized');

            // Merge gallery items into the Magnific slider so clicking standalone images cycles through the gallery too
            var $galleryItems = $(this).find('.et_pb_gallery_item');
            if ($galleryItems.length > 0) {
                var $pool = $('<div class="hs-master-lightbox-pool" style="display: none;"></div>');
                $galleryItems.each(function() {
                    var $link = $(this).find('.et_pb_gallery_image a');
                    if ($link.length) {
                        $pool.append('<a href="' + $link.attr('href') + '" class="hs-gallery-link"></a>');
                    }
                });
                $(this).append($pool);
            }

            $(this).magnificPopup({
                delegate: 'a.hs-gallery-link',
                type: 'image',
                gallery: { 
                    enabled: true, 
                    navigateByImgClick: true, 
                    preload: [1,2] 
                }, 
                mainClass: 'mfp-fade', 
                closeBtnInside: false,
                closeMarkup: '<div title="Close (Esc)" class="mfp-close hs-modal-close hs-shared-close"><span class="hs-modal-close-text">CLOSE</span><span class="hs-modal-close-icon">&times;</span></div>',
                image: {
                    // Force no titles or captions to appear in the popup
                    titleSrc: function(item) {
                        return ''; 
                    }
                }
            });

            // Prevent default link behavior explicitly just in case
            $(this).find('a.hs-gallery-link').on('click', function(e) {
                e.preventDefault();
            });
        });
    }
    
    // Kick off the initializer
    initWeeklyLightboxes();
    initGlobalReadMore();

    // Export to global hsCore object so AJAX can trigger them directly without a slow MutationObserver
    window.hsCore = window.hsCore || {};
    window.hsCore.initWeeklyLightboxes = initWeeklyLightboxes;
    window.hsCore.initGlobalReadMore = initGlobalReadMore;
    
    function initGlobalReadMore() {
        // Do not execute in the Divi Visual Builder (allows user to edit text freely without collapse logic interfering)
        if (window.hsCore && window.hsCore.isDiviBuilder()) return;

        const containers = document.querySelectorAll('.hs-read-more-container:not(.hs-read-more-initialized)');
        
        containers.forEach(container => {
            container.classList.add('hs-read-more-initialized');
            
            // Defensively remove any duplicate wrapper that might have been cloned by Divi DOM updates
            const nextEl = container.nextElementSibling;
            if (nextEl && nextEl.classList.contains('hs-read-more-btn-wrapper')) {
                nextEl.remove();
            }
            
            // Let CSS apply the max-height first, then check if it's actually overflowing.
            // We give a 10px buffer so we don't collapse if it only overflows by a few pixels.
            if (container.scrollHeight > container.clientHeight + 10) {
                const btnWrapper = document.createElement('div');
                btnWrapper.className = 'hs-read-more-btn-wrapper';
                
                const btn = document.createElement('button');
                btn.className = 'hs-read-more-btn';
                btn.innerHTML = 'Read More <i class="fas fa-chevron-down" style="margin-left: 6px;"></i>';
                
                btnWrapper.appendChild(btn);
                
                // Insert immediately after the container
                container.parentNode.insertBefore(btnWrapper, container.nextSibling);
                
                btn.addEventListener('click', () => {
                    if (container.classList.contains('hs-expanded')) {
                        // Collapse
                        container.classList.remove('hs-expanded');
                        btn.innerHTML = 'Read More <i class="fas fa-chevron-down" style="margin-left: 6px;"></i>';
                        
                        // Smooth scroll back up to the container if we are currently past it
                        const rect = container.getBoundingClientRect();
                        if (rect.top < 0) {
                            const offset = window.pageYOffset + rect.top - 100;
                            window.scrollTo({ top: offset, behavior: 'smooth' });
                        }
                    } else {
                        // Expand
                        container.classList.add('hs-expanded');
                        btn.innerHTML = 'Read Less <i class="fas fa-chevron-up" style="margin-left: 6px;"></i>';
                    }
                });
            } else {
                // Content is short enough that it doesn't need to collapse
                container.classList.add('hs-no-collapse');
            }
        });
    }







});
