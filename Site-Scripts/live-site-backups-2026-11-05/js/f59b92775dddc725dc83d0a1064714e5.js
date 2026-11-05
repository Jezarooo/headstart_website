
/**
 * =============================================================================
 * HEADSTART MOBILE NAVIGATION ENGINE
 * =============================================================================
 * PURPOSE: Handles all mobile-specific navigation logic, form teleportation, drawer states, and submenu toggles.
 * LOGIC: Attaches event listeners for mobile interactions and DOM node movement.
 * DEPENDENCIES: Mobile Menu DOM
 * =============================================================================
 */
document.addEventListener('DOMContentLoaded', function() {
        const root = document.getElementById('hs-mobile-nav-root');
        
        // MOUNT THE MENU
        if (root && !HeadStartUtils.isDiviBuilder()) {
            document.body.appendChild(root);
        }

        // LANGUAGE SWITCHER LAYOUT LOGIC moved to hsToggleDrawer for accurate width calculation

        // TELEPORT BOTH FORMS (Booking and Contact)
        let teleportAttempts = 0;
        const teleport = setInterval(() => {
            teleportAttempts++;
            if (teleportAttempts > 20) {
                clearInterval(teleport);
                return;
            }
            const bSource = document.getElementById('hs-booking-source');
            const cSource = document.getElementById('hs-contact-source');
            const bWrap = document.getElementById('hs-mobile-booking-wrap');
            const cWrap = document.getElementById('hs-mobile-contact-wrap');
            const loading = document.getElementById('hs-form-loading');

            if (bSource && bWrap && cSource && cWrap) {
                // Move forms to their wrappers safely
                bWrap.appendChild(bSource);
                bSource.style.display = 'block';
                cWrap.appendChild(cSource);
                cSource.style.display = 'block';
                
                // Hide loading spinner
                if(loading) loading.style.display = 'none';
                
                // Format Booking Checkbox Labels (New Line for Age)
                const labels = bWrap.querySelectorAll('.jez-cta-curriculum-checkboxes .ff-el-form-check-label span');
                labels.forEach(label => {
                    if(label.innerHTML.includes('(')) {
                        label.innerHTML = label.innerHTML.replace(' (', '<br><span style="font-size: 0.85em; opacity: 0.8;">(') + '</span>';
                        label.style.lineHeight = '1.3';
                        label.style.display = 'block';
                    }
                });

                clearInterval(teleport);
            }
        }, 500);

        // --- WIRE UP EXTERNAL .mobile-hamburger BUTTONS ---
        document.addEventListener('click', function(e) {
            const trigger = e.target.closest('.mobile-hamburger');
            if (trigger) {
                e.preventDefault();
                e.stopPropagation(); 
                
                if(!trigger.querySelector('.hs-custom-close-icon')) {
                    const xIcon = document.createElement('div');
                    xIcon.className = 'hs-custom-close-icon';
                    xIcon.innerHTML = `<svg style="width:28px; height:28px; fill:#003057;" viewBox="0 0 24 24"><path d="M19 6.41L17.59 5 12 10.59 6.41 5 5 6.41 10.59 12 5 17.59 6.41 19 12 13.41 17.59 19 19 17.59 13.41 12z"/></svg>`;
                    const inner = trigger.querySelector('.et_pb_module_inner') || trigger;
                    inner.style.position = 'relative'; 
                    inner.appendChild(xIcon);
                }

                hsToggleDrawer(trigger); 
            }
        });

        // --- CATCH SECONDARY FOOTER CTA ON MOBILE ---
        document.addEventListener('click', function(e) {
            const secBtn = e.target.closest('.hs-secondary-cta-button');
            // Check if it's the secondary button AND we are on mobile
            if (secBtn && window.innerWidth < 980) {
                e.preventDefault();
                e.stopPropagation();
                // Because Desktop script aborts on mobile, we explicitly open the mobile booking form here
                hsToggleFormSlide('booking');
            }
        });
    });

    function hsToggleDrawer(clickedTrigger) {
        const mask = document.getElementById('hs-drawer-mask');
        const content = document.getElementById('hs-drawer-content');
        const body = document.body;
        
        let headerHeight = 80; 
        
        if(clickedTrigger) {
            const section = clickedTrigger.closest('.et_pb_section');
            if(section) {
                headerHeight = section.getBoundingClientRect().bottom;
                if(headerHeight < 0) headerHeight = 0;
            }
        } else {
            const trigger = document.querySelector('.mobile-hamburger');
            if(trigger) {
                const section = trigger.closest('.et_pb_section');
                if(section) headerHeight = section.getBoundingClientRect().bottom;
            }
        }

        mask.style.top = headerHeight + 'px';
        mask.style.height = `calc(100vh - ${headerHeight}px)`;

        mask.classList.toggle('active');
        content.classList.toggle('active');
        const isOpen = content.classList.contains('active');

        document.querySelectorAll('.mobile-hamburger').forEach(trigger => {
            if(isOpen) {
                trigger.classList.add('hs-is-open');
            } else {
                trigger.classList.remove('hs-is-open');
            }
        });

        if (isOpen) {
            body.style.overflow = 'hidden';

            // --- ACCURATE LANGUAGE SWITCHER LAYOUT LOGIC ---
            // Now that the menu is visible in the DOM, we can check actual rendered width
            const contactBtn = document.getElementById('hs-book-tour-btn');
            const langWrap = document.getElementById('hs-language-switcher-wrap');
            if (langWrap && contactBtn) {
                // Temporarily ensure it's displayed so we can measure it
                langWrap.style.display = 'flex';
                
                // If the language plugin rendered hidden elements (e.g. display:none) or nothing,
                // the inner children will have 0 offsetWidth.
                let hasVisibleWidth = false;
                const children = langWrap.querySelectorAll('*');
                children.forEach(child => {
                    if (child.offsetWidth > 2) hasVisibleWidth = true;
                });
                
                // Fallback text check (if the shortcode just outputs text without wrapping elements)
                const hasText = langWrap.innerText.trim().length > 0 && !langWrap.innerText.includes('[language_switcher]');

                if (!hasVisibleWidth && !hasText) {
                    langWrap.style.display = 'none';
                    contactBtn.classList.add('w-full', 'justify-center');
                } else {
                    langWrap.style.display = ''; // let CSS handle it normally
                    contactBtn.classList.remove('w-full', 'justify-center');
                }
            }
        } else {
            const formPanel = document.getElementById('hs-form-panel');
            if (formPanel) formPanel.classList.remove('active');
            body.style.overflow = '';
        }
    }

    // Slide Router
    function hsToggleFormSlide(type) {
        const panel = document.getElementById('hs-form-panel');
        const title = document.getElementById('hs-form-panel-title');
        const bWrap = document.getElementById('hs-mobile-booking-wrap');
        const cWrap = document.getElementById('hs-mobile-contact-wrap');

        if (type === 'booking') {
            title.innerText = "Book a Tour";
            if(bWrap) bWrap.style.display = 'block';
            if(cWrap) cWrap.style.display = 'none';
            panel.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else if (type === 'contact') {
            title.innerText = "Contact Us";
            if(bWrap) bWrap.style.display = 'none';
            if(cWrap) cWrap.style.display = 'block';
            panel.classList.add('active');
            document.body.style.overflow = 'hidden';
        } else {
            panel.classList.remove('active');
            const content = document.getElementById('hs-drawer-content');
            if(!content || !content.classList.contains('active')) {
                document.body.style.overflow = '';
            }
        }
    }

    function hsToggleSubmenu(id) {
        const el = document.getElementById(id);
        const icon = document.getElementById('hs-icon-' + id);
        if(!el) return;
        el.classList.toggle('open');
        if (el.classList.contains('open')) {
            if(icon) {
                if(id === 'resources') {
                    icon.innerHTML = '<path d="M19 13H5v-2h14v2z"/>';
                } else {
                    icon.style.transform = 'rotate(180deg)';
                }
            }
        } else {
            if(icon) {
                if(id === 'resources') {
                    icon.innerHTML = '<path d="M19 13h-6v6h-2v-6H5v-2h6V5h2v6h6v2z"/>';
                } else {
                    icon.style.transform = 'rotate(0deg)';
                }
            }
        }
    }

    function hsToggleSocial(id) {
        document.querySelectorAll('.hs-social-popup').forEach(el => {
            if(el.id !== id) el.classList.remove('active');
        });
        document.getElementById(id).classList.toggle('active');
    }

    window.addEventListener('click', function(e) {
        if (!e.target.closest('.hs-social-wrapper')) {
            document.querySelectorAll('.hs-social-popup').forEach(el => el.classList.remove('active'));
        }
    });

