
/**
 * =============================================================================
 * HEADSTART MEGA MENU ENGINE V16.9
 * =============================================================================
 * PURPOSE: Advanced interaction logic for the HeadStart Global Mega Menus.
 * LOGIC: Handles Asymmetric UI Sync, Cross-Platform Isolation, hover suspension, desktop snap-close.
 * DEPENDENCIES: DOM menus, Header structure
 * =============================================================================
 */

(function() {
    
    function runMegaMenuEngine() {
        if (!document.body) { 
            setTimeout(runMegaMenuEngine, 50); 
            return; 
        }

        console.log("🚀 HeadStart Mega Menu Engine V16.9 Started");

        /* --- STATE & CONFIG --- */
        
        const isBuilder = HeadStartUtils.isDiviBuilder();
        let closeTimeout;

        const hoverMap = {
            '.trigger-community':    { target: 'mega-community', anchor: '.hs-utility-bar' },
            '.trigger-resources':    { target: 'mega-resources', anchor: '.hs-utility-bar' },
            '.trigger-fb':           { target: 'mega-social-fb', anchor: '.hs-utility-bar' },
            '.trigger-insta':        { target: 'mega-social-insta', anchor: '.hs-utility-bar' },
            '.trigger-student':      { target: 'mega-student', anchor: '.hs-primary-nav' },
            '.trigger-fb-footer':    { target: 'mega-footer-fb', anchor: 'none' },
            '.trigger-insta-footer': { target: 'mega-footer-insta', anchor: 'none' }
        };


        /* --- UTILITIES --- */


        const handleMegaScroll = HeadStartUtils.debounce(() => {
            if (window.innerWidth < 981) return; 
            
            const openMegas = document.querySelectorAll('.hs-show-mega');
            if (openMegas.length > 0) {
                openMegas.forEach(menu => {
                    menu.style.setProperty('transition', 'none', 'important');
                });
                
                closeAllMegas();
                
                setTimeout(() => {
                    openMegas.forEach(menu => {
                        menu.style.transition = '';
                    });
                }, 50);
            }
        }, 10);

        /**
         * Closes all active mega menus and resets CTA button states.
         */
        function closeAllMegas() {
            document.querySelectorAll('.hs-show-mega').forEach(el => el.classList.remove('hs-show-mega'));
            document.querySelectorAll('.cta-column, .hs-secondary-cta-button, .hs-cta-open').forEach(el => el.classList.remove('hs-cta-open'));
            window.removeEventListener('scroll', handleMegaScroll, { passive: true });
        }

        /**
         * Tags an element as initialized to prevent duplicate processing.
         */
        function setupMegaSection(element) {
            if (!element.classList.contains('hs-mega-initialized')) {
                element.classList.add('hs-mega-initialized');
            }
        }

        /**
         * Ensures columns containing local popups have the required parent class.
         */
        function setupLocalContainers() {
            document.querySelectorAll('.mega-local-popup').forEach(popup => {
                const parentCol = popup.closest('.et_pb_column');
                if (parentCol && !parentCol.classList.contains('hs-mega-parent-column')) {
                    parentCol.classList.add('hs-mega-parent-column');
                }
            });
        }




        /* --- LAYOUT & ALIGNMENT --- */

        /**
         * Vertically aligns a mega menu directly below its anchor element.
         */
        function alignMenuVertical(menuElement, anchorSelector) {
            if (!menuElement || anchorSelector === 'none') return;
            const anchor = document.querySelector(anchorSelector);
            if (anchor) {
                const rect = anchor.getBoundingClientRect();
                menuElement.style.setProperty('position', 'fixed', 'important');
                menuElement.style.setProperty('top', rect.bottom + 'px', 'important');
            }
        }

        /**
         * Positions the contact drawer relative to the utility bar and right edge.
         */
        function alignContactDrawer(drawer) {
            if (!drawer) return;
            window.requestAnimationFrame(() => {
                const btnAnchor = document.querySelector('.cta-column');
                const headerAnchor = document.querySelector('.hs-utility-bar');
                
                if (btnAnchor && headerAnchor) {
                    const btnRect = btnAnchor.getBoundingClientRect();
                    const gapFromRight = window.innerWidth - btnRect.right;
                    
                    const userOffset = drawer.getAttribute('data-hs-offset');
                    const manualOffset = userOffset ? parseInt(userOffset, 10) : 30; 
                    
                    let finalGap = gapFromRight - manualOffset; 
                    if (finalGap < 0) finalGap = 0; 
                    
                    const headerRect = headerAnchor.getBoundingClientRect();
                    
                    drawer.style.setProperty('position', 'fixed', 'important');
                    drawer.style.setProperty('right', finalGap + 'px', 'important');
                    drawer.style.setProperty('left', 'auto', 'important');
                    drawer.style.setProperty('top', headerRect.bottom + 'px', 'important');
                    
                    const userWidth = drawer.getAttribute('data-hs-width');
                    if (userWidth) { 
                        drawer.style.setProperty('max-width', userWidth + 'px', 'important'); 
                    }
                }
            });
        }


        /* --- EVENT HANDLERS --- */

        /**
         * Binds hover, click, scroll, and resize interactions for desktop views.
         */
        function initInteractions() {
            if (isBuilder) return;

            const contactSelectors = ['.cta-column', '.hs-secondary-cta-button'];
            
            document.querySelectorAll(contactSelectors.join(', ')).forEach(btn => {
                btn.classList.add('hs-contact-toggle');
            });

            // Hover Events
            document.addEventListener('mouseover', (e) => {
                if (window.innerWidth < 981) return; 

                const isContactFormOpen = document.querySelector('.mega-contact-form.hs-show-mega');
                if (isContactFormOpen) return;

                let isTrigger = false;
                let isInsideMenu = e.target.closest('.hs-show-mega');

                // 1. Check for triggers
                for (const [selector, config] of Object.entries(hoverMap)) {
                    const trigger = e.target.closest(selector);
                    if (trigger) {
                        isTrigger = true;
                        
                        // CLEAR TIMEOUT: User is on a trigger, cancel any pending close
                        clearTimeout(closeTimeout);
                        closeTimeout = null;
                        
                        const targetMenu = document.querySelector(`.${config.target}`);
                        if (targetMenu && !targetMenu.classList.contains('hs-show-mega')) {
                            closeAllMegas();
                            alignMenuVertical(targetMenu, config.anchor); 
                            targetMenu.classList.add('hs-show-mega');
                            window.addEventListener('scroll', handleMegaScroll, { passive: true });
                        }
                        break;
                    }
                }

                // 2. CLEAR TIMEOUT: User safely reached the menu
                if (isInsideMenu) {
                    clearTimeout(closeTimeout);
                    closeTimeout = null;
                }

                // 3. START TIMEOUT: User is in dead space
                if (!isTrigger && !isInsideMenu) {
                    // CRITICAL FIX: Only set a timeout if one isn't already running
                    if (!closeTimeout) {
                        closeTimeout = setTimeout(() => { 
                            closeAllMegas(); 
                            closeTimeout = null; // Reset state after closing
                        }, 350); // Bumped from 200ms to 350ms for a better grace period
                    }
                }
            });

            // Click Events
            document.addEventListener('click', (e) => {
                if (window.innerWidth < 981) return; 

                const contactBtn = e.target.closest('.hs-contact-toggle, .cta-column, .hs-secondary-cta-button');
                
                if (contactBtn) {
                    e.preventDefault(); 
                    e.stopPropagation();

                    const targetForm = document.querySelector('.mega-contact-form');
                    if (!targetForm) return;

                    const isOpen = targetForm.classList.contains('hs-show-mega');

                    if (isOpen) {
                        closeAllMegas();
                    } else {
                        closeAllMegas();
                        alignContactDrawer(targetForm); 
                        targetForm.classList.add('hs-show-mega'); 
                        window.addEventListener('scroll', handleMegaScroll, { passive: true }); 
                        
                        if (contactBtn.classList.contains('hs-secondary-cta-button')) {
                            document.querySelectorAll('.cta-column, .hs-secondary-cta-button').forEach(btn => {
                                btn.classList.add('hs-cta-open');
                            });
                        } else {
                            document.querySelectorAll('.cta-column').forEach(btn => {
                                btn.classList.add('hs-cta-open');
                            });
                        }
                    }
                    return;
                }

                if (!e.target.closest('.hs-show-mega') && !e.target.closest('[class*="mega-"]')) {
                    closeAllMegas();
                }
            });


            
            // Resize Events
            window.addEventListener('resize', HeadStartUtils.debounce(() => {
                if (window.innerWidth < 981) return;
                const openDrawer = document.querySelector('.mega-contact-form.hs-show-mega');
                if (openDrawer) alignContactDrawer(openDrawer);
            }, 100));
        }

        /* --- INITIALIZATION --- */

        if (!isBuilder) {
            document.querySelectorAll('[class*="mega-"]').forEach(setupMegaSection);
            setupLocalContainers();
            initInteractions();

            const observer = new MutationObserver((mutations) => {
                mutations.forEach((mutation) => {
                    if (mutation.addedNodes.length) {
                        mutation.addedNodes.forEach(node => {
                            if (node.nodeType === 1) {
                                if (node.matches && node.matches('[class*="mega-"]')) setupMegaSection(node);
                                if (node.querySelector && node.querySelector('.mega-local-popup')) setupLocalContainers();
                            }
                        });
                    }
                });
            });
            observer.observe(document.body, { childList: true, subtree: true });
        }
    }

    runMegaMenuEngine();
})();
