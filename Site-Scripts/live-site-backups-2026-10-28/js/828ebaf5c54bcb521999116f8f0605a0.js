
/**
 * =============================================================================
 * HEADSTART HYBRID TAB ENGINE V2
 * =============================================================================
 * PURPOSE: Desktop nested sidebar, mobile dropdown, and table auto-labeling.
 * LOGIC: Vanilla JS engine (Divi 5 compatible) managing dropdown states, accordion height transitions, tab panel routing, and dynamic data-label injection for mobile card-stack table views.
 * DEPENDENCIES: Divi DOM
 * =============================================================================
 */

document.addEventListener('DOMContentLoaded', () => {

    /* --- Multilingual Logic --- */
    const langBtns = document.querySelectorAll('.lang-switch-btn');
    if (langBtns.length > 0) {
        langBtns.forEach(btn => {
            btn.addEventListener('click', (e) => {
                e.preventDefault();
                const targetLang = btn.getAttribute('data-lang');
                
                langBtns.forEach(b => {
                    b.classList.remove('active');
                    if (b.getAttribute('data-lang') === targetLang) {
                        b.classList.add('active');
                    }
                });
                
                const allLangNodes = document.querySelectorAll('[data-lang]:not(.lang-switch-btn)');
                
                allLangNodes.forEach(node => {
                    if (node.getAttribute('data-lang') === targetLang) {
                        node.classList.remove('hs-lang-hidden');
                    } else {
                        node.classList.add('hs-lang-hidden');
                    }
                });
            });
        });
    }
    /* -------------------------- */


    const tabContainers = document.querySelectorAll('.hs-hybrid-tabs');
    
    tabContainers.forEach(container => {
        
        /* --- DOM ELEMENTS --- */

        const trigger = container.querySelector('.hs-mobile-dropdown-trigger');
        
        let triggerText = null;
        if (trigger) {
            triggerText = trigger.querySelector('.hs-active-text') || trigger.querySelector('span');
        }

        const navWrapper = container.querySelector('.hs-tab-nav-wrapper');
        const tabList = container.querySelector('.hs-tab-list');
        const navGroups = container.querySelectorAll('.hs-nav-group');
        const tabs = container.querySelectorAll('.hs-tab-btn');
        const panels = container.querySelectorAll('.hs-tab-panel');
        
        /* --- MOBILE DROPDOWN --- */

        /**
         * Handles the mobile dropdown trigger state and automatically recalculates 
         * nested accordion heights if the dropdown is opened.
         */
        if (trigger && tabList) {
            trigger.addEventListener('click', (e) => {
                e.preventDefault();
                trigger.classList.toggle('dropdown-open');
                tabList.classList.toggle('dropdown-open');
                
                let parentRow = container.closest('.et_pb_row');
                let parentSection = container.closest('.et_pb_section');
                
                if (tabList.classList.contains('dropdown-open')) {
                    if (parentRow) parentRow.style.setProperty('z-index', '9999', 'important');
                    if (parentSection) parentSection.style.setProperty('z-index', '9999', 'important');
                    container.style.setProperty('z-index', '9999', 'important');

                    setTimeout(() => {
                        navGroups.forEach(group => {
                            if (group.classList.contains('open')) {
                                const content = group.querySelector('.hs-nav-sub-items');
                                const inner = group.querySelector('.hs-nav-sub-items-inner');
                                if (content && inner) {
                                    content.style.maxHeight = inner.offsetHeight + 'px';
                                }
                            }
                        });
                    }, 50);
                } else {
                    if (parentRow) parentRow.style.removeProperty('z-index');
                    if (parentSection) parentSection.style.removeProperty('z-index');
                    container.style.removeProperty('z-index');
                }
            });
            
            document.addEventListener('click', (e) => {
                if (!container.contains(e.target) && trigger.classList.contains('dropdown-open')) {
                    trigger.classList.remove('dropdown-open');
                    tabList.classList.remove('dropdown-open');
                    
                    let parentRow = container.closest('.et_pb_row');
                    let parentSection = container.closest('.et_pb_section');
                    if (parentRow) parentRow.style.removeProperty('z-index');
                    if (parentSection) parentSection.style.removeProperty('z-index');
                    container.style.removeProperty('z-index');
                }
            });
        }

        /* --- NESTED ACCORDION --- */

        /**
         * Manages the desktop sidebar and mobile dropdown accordion states, ensuring 
         * smooth max-height transitions and mutually exclusive open states.
         */
        navGroups.forEach(group => {
            const groupToggle = group.querySelector('.hs-nav-group-toggle');
            const content = group.querySelector('.hs-nav-sub-items');
            const inner = group.querySelector('.hs-nav-sub-items-inner');

            if (!groupToggle || !content || !inner) return;

            if (group.classList.contains('open')) {
                content.style.maxHeight = inner.offsetHeight + 'px';
            }

            groupToggle.addEventListener('click', (e) => {
                e.preventDefault();
                const isOpen = group.classList.contains('open');

                navGroups.forEach(otherGroup => {
                    if (otherGroup !== group && otherGroup.classList.contains('open')) {
                        otherGroup.classList.remove('open');
                        otherGroup.querySelector('.hs-nav-sub-items').style.maxHeight = null;
                    }
                });

                if (isOpen) {
                    group.classList.remove('open');
                    content.style.maxHeight = null;
                } else {
                    group.classList.add('open');
                    content.style.maxHeight = inner.offsetHeight + 'px';
                }
            });
        });

        window.addEventListener('resize', () => {
            navGroups.forEach(group => {
                if (group.classList.contains('open')) {
                    const content = group.querySelector('.hs-nav-sub-items');
                    const inner = group.querySelector('.hs-nav-sub-items-inner');
                    if(content && inner) {
                        content.style.maxHeight = inner.offsetHeight + 'px';
                    }
                }
            });
        });

        /* --- TAB SWITCHING & ROUTING --- */

        /**
         * Routes click events to the appropriate tab panel, updates the mobile 
         * trigger text, closes the dropdown, and smooth-scrolls to the content.
         */
        tabs.forEach(tab => {
            tab.addEventListener('click', (e) => {
                e.preventDefault();
                
                tabs.forEach(t => t.classList.remove('active'));
                panels.forEach(p => p.classList.remove('active'));
                
                tab.classList.add('active');
                
                const targetId = tab.getAttribute('data-target');
                const targetPanel = container.querySelector('#' + targetId);
                if (targetPanel) {
                    targetPanel.classList.add('active');
                }
                
                if (triggerText && trigger && tabList) {
                    
                    if (triggerText.classList.contains('hs-active-text')) {
                        triggerText.innerHTML = tab.innerHTML;
                    } else {
                        triggerText.innerHTML = tab.innerHTML;
                    }
                    
                    trigger.classList.remove('dropdown-open');
                    tabList.classList.remove('dropdown-open');
                    
                    let parentRow = container.closest('.et_pb_row');
                    let parentSection = container.closest('.et_pb_section');
                    if (parentRow) parentRow.style.removeProperty('z-index');
                    if (parentSection) parentSection.style.removeProperty('z-index');
                    container.style.removeProperty('z-index');
                    
                    if (window.innerWidth <= 768) {
                        // Dynamically calculate the top bar height
                        const topBar = document.querySelector('.mobile-menu-section');
                        const topBarHeight = topBar ? topBar.offsetHeight : 0;
                        
                        // Grab the height of the tab trigger itself so the panel clears it
                        const triggerHeight = trigger.offsetHeight || 0;
                        
                        window.scrollTo({
                            top: container.getBoundingClientRect().top + window.scrollY - (topBarHeight + triggerHeight),
                            behavior: 'smooth'
                        });
                    }
                }
            });
        });

        /* --- MOBILE TABLE FORMATTER --- */

        /**
         * Dynamically reads table headers and assigns them to 'data-label' attributes 
         * on table cells to enable responsive card-stack CSS layouts on mobile views.
         */
        const tables = container.querySelectorAll('.hs-standard-table:not(.hs-table-scroll)');
        tables.forEach(table => {
            const headers = Array.from(table.querySelectorAll('thead th')).map(th => th.textContent.trim());
            const rows = table.querySelectorAll('tbody tr');
            
            rows.forEach(row => {
                const cells = row.querySelectorAll('td');
                cells.forEach((cell, index) => {
                    if (headers[index]) {
                        cell.setAttribute('data-label', headers[index]);
                    }
                });
            });
        });
    });

    /* ==========================================================================
       [GLOBAL COMPONENT: HORIZONTAL SCROLL TABLES]
       ========================================================================== */
    
    /**
     * Initializes horizontal scroll tables (standalone and inside tabs)
     * Adds the '.is-visible' class to '.hs-scroll-arrow-overlay' when 
     * the table is scrollable, and hides it when scrolled to the right end.
     */
    function initScrollableTables() {
        const scrollContainers = document.querySelectorAll('.hs-table-container-relative');
        
        scrollContainers.forEach(container => {
            const scrollWrapper = container.querySelector('.hs-table-wrapper-scroll');
            const overlay = container.querySelector('.hs-scroll-arrow-overlay');
            
            if (!scrollWrapper || !overlay) return;
            
            const checkScroll = () => {
                // Check if scrollable
                const isScrollable = scrollWrapper.scrollWidth > scrollWrapper.clientWidth;
                // Check if scrolled to the end (allow 2px margin for rounding)
                const isAtEnd = Math.abs(scrollWrapper.scrollWidth - scrollWrapper.clientWidth - scrollWrapper.scrollLeft) <= 2;
                
                if (isScrollable && !isAtEnd) {
                    overlay.classList.add('is-visible');
                } else {
                    overlay.classList.remove('is-visible');
                }
            };
            
            // Initial check
            checkScroll();
            
            // Listen for scroll and resize (Debounced for performance)
            const debouncedCheck = HeadStartUtils.debounce(checkScroll, 15);
            scrollWrapper.addEventListener('scroll', debouncedCheck, { passive: true });
            window.addEventListener('resize', debouncedCheck, { passive: true });
        });
    }

    // Initialize after a slight delay to ensure fonts/layout have settled
    setTimeout(initScrollableTables, 100);
});

document.addEventListener("DOMContentLoaded", function() {
    function initAlumniGallery() {
        if (typeof jQuery === "undefined" || !jQuery.fn.magnificPopup) {
            setTimeout(initAlumniGallery, 100);
            return;
        }
        var $ = jQuery;
        $(".hs-tab-panel").each(function() {
            if ($(this).find("a.hs-gallery-link").length > 0) {
                // Unbind any previous magnific popup on these links to prevent conflict
                $(this).find("a.hs-gallery-link").off("click.magnificPopup");
                
                $(this).magnificPopup({
                    delegate: "a.hs-gallery-link",
                    type: "image",
                    gallery: { enabled: true, navigateByImgClick: true },
                    mainClass: "mfp-fade",
                    closeBtnInside: false,
                    closeMarkup: "<div title=\"Close (Esc)\" class=\"mfp-close hs-modal-close hs-shared-close\"><span class=\"hs-modal-close-text\">CLOSE</span><span class=\"hs-modal-close-icon\">&times;</span></div>",
                    image: {
                        titleSrc: function(item) {
                            return ""; 
                        }
                    }
                });
            }
        });
    }
    initAlumniGallery();







});
