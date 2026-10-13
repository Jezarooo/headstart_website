
/**
 * =============================================================================
 * HEADSTART EVENTS CALENDAR ENGINE (Refactored)
 * =============================================================================
 * PURPOSE: Advanced event calendar management and UI logic.
 * LOGIC: Encapsulated as a class to support multiple modules on a single page.
 * DEPENDENCIES: DOM Calendar modules
 * =============================================================================
 */

class HeadStartEventsCalendar {
    constructor(container) {
        this.container = container;
        
        // State
        this.currentFilter = 'all';
        this.currentPage = 0;
        this.modalFilter = 'all'; 
        this.modalPage = 0; 
        this.currentEventData = null;
        this.currentActiveCardElement = null;
        this.cards = [];
        
        // Debounce timer for resize
        this.resizeTimer = null;

        this.init();
    }

    init() {
        const overlay = this.container.querySelector('#hs-modal-overlay') || document.querySelector('#hs-modal-overlay');
        
        if (overlay && overlay.parentNode !== document.body) {
            overlay.style.setProperty('position', 'fixed', 'important');
            overlay.style.setProperty('top', '0', 'important');
            overlay.style.setProperty('left', '0', 'important');
            overlay.style.setProperty('width', '100vw', 'important');
            overlay.style.setProperty('height', '100vh', 'important');
            overlay.style.setProperty('z-index', '99999999', 'important');

            this.modalWrapper = document.createElement('div');
            this.modalWrapper.className = 'hs-section-events hs-modal-global-wrapper'; 
            this.modalWrapper.appendChild(overlay);
            document.body.appendChild(this.modalWrapper);
        } else {
            this.modalWrapper = overlay ? overlay.parentNode : null;
        }

        this.cards = Array.from(this.container.querySelectorAll('.hs-card-v1'));
        this.bindEvents();
        this.applyFilterDisplay();
    }

    bindEvents() {
        // --- Filter Dropdowns ---
        const mainFilter = this.container.querySelector('.hs-mobile-filter');
        if (mainFilter) {
            mainFilter.addEventListener('change', (e) => this.filterEvents(e.target.value));
        }

        if (this.modalWrapper) {
            const modalFilter = this.modalWrapper.querySelector('.hs-modal-mobile-filter');
            if (modalFilter) {
                modalFilter.addEventListener('change', (e) => this.filterEvents(e.target.value));
            }
        }

        // --- Filter Pill Buttons ---
        const filterBtns = this.container.querySelectorAll('.hs-filter-btn');
        filterBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                this.filterEvents(btn.getAttribute('data-filter'));
            });
        });

        // --- Pagination (Dashboard) ---
        const prevBtn = this.container.querySelector('.hs-carousel-prev');
        if (prevBtn) prevBtn.addEventListener('click', () => this.changePage(-1));
        
        const nextBtn = this.container.querySelector('.hs-carousel-next');
        if (nextBtn) nextBtn.addEventListener('click', () => this.changePage(1));

        // --- Calendar Export Dropdowns ---
        const btnGlobalCal = this.container.querySelector('#btn-global-cal');
        if (btnGlobalCal) {
            btnGlobalCal.addEventListener('click', (e) => this.toggleGlobalCalDropdown(e));
        }
        
        const globalDropdownBtns = this.container.querySelectorAll('#global-cal-dropdown button');
        globalDropdownBtns.forEach(btn => {
            btn.addEventListener('click', () => {
                let platform = 'apple';
                if(btn.innerHTML.includes('google')) platform = 'google';
                if(btn.innerHTML.includes('windows')) platform = 'outlook';
                this.subscribeCalendar(platform);
            });
        });

        if (this.modalWrapper) {
            const btnAddCalEvent = this.modalWrapper.querySelector('#btn-add-cal-event');
            if (btnAddCalEvent) {
                btnAddCalEvent.addEventListener('click', (e) => this.toggleEventCalDropdown(e));
            }

            const eventDropdownBtns = this.modalWrapper.querySelectorAll('#cal-dropdown-event button');
            eventDropdownBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    let platform = 'apple';
                    if(btn.innerHTML.includes('google')) platform = 'google';
                    if(btn.innerHTML.includes('windows')) platform = 'outlook';
                    this.addToCalendar(platform);
                });
            });
            
            // Modal View All / Close
            const btnViewAll = this.container.querySelector('.hs-btn-view-all');
            if (btnViewAll) btnViewAll.addEventListener('click', () => this.openViewAllModal());

            const closeBtns = this.modalWrapper.querySelectorAll('.hs-shared-close');
            closeBtns.forEach(btn => {
                btn.addEventListener('click', () => this.closeModal());
            });

            // Modal Prev/Next Navigation
            const navPrev = this.modalWrapper.querySelector('.hs-modal-nav-prev');
            if (navPrev) navPrev.addEventListener('click', () => this.navigateSingleModal(-1));

            const navNext = this.modalWrapper.querySelector('.hs-modal-nav-next');
            if (navNext) navNext.addEventListener('click', () => this.navigateSingleModal(1));

            // Overlay click to close
            const overlay = this.modalWrapper.querySelector('#hs-modal-overlay');
            if (overlay) {
                overlay.addEventListener('click', (e) => {
                    if (e.target === overlay) this.closeModal();
                });
            }
        }

        // --- Event Cards ---
        const cards = this.container.querySelectorAll('.hs-card-v1');
        cards.forEach(card => {
            card.addEventListener('click', (e) => {
                if (e.target.closest('.hs-card-register-btn') || e.target.closest('.hs-meta-register-btn')) return;
                this.openSingleModalFromNode(card);
            });
        });

        // --- Window Level Events (scoping safely) ---
        window.addEventListener('click', (e) => {
            if (!e.target.closest('.hs-cal-dropdown-container')) {
                this.container.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
                if (this.modalWrapper) {
                    this.modalWrapper.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
                }
            }
        });

        // Resize with debounce
        window.addEventListener('resize', () => {
            clearTimeout(this.resizeTimer);
            this.resizeTimer = setTimeout(() => {
                this.applyFilterDisplay();
                if(this.modalWrapper) {
                    const modalList = this.modalWrapper.querySelector('#modal-list-view');
                    if (modalList && window.getComputedStyle(modalList).display !== 'none') {
                        this.applyModalFilterDisplay();
                    }
                }
            }, 150); // 150ms debounce
        });
    }

    /* --- DATE FORMATTING HELPERS --- */
    parseEventDate(isoStr) {
        if (!isoStr) return { month: '', day: '' };
        const parts = isoStr.split('T')[0].split('-');
        if (parts.length !== 3) return { month: '', day: '' };
        const d = new Date(parts[0], parts[1] - 1, parts[2]);
        if (isNaN(d.getTime())) return { month: '', day: '' };
        const month = d.toLocaleString('en-US', { month: 'short' }).toUpperCase();
        const day = String(d.getDate()).padStart(2, '0');
        return { month, day };
    }

    generateBadgeHTML(startDateStr, endDateStr) {
        const start = this.parseEventDate(startDateStr);
        const badge1 = `<div class="hs-date-badge-v1"><div class="hs-db-month">${start.month}</div><div class="hs-db-day">${start.day}</div></div>`;
        
        if (endDateStr) {
            const end = this.parseEventDate(endDateStr);
            return `<div class="hs-multi-date-wrapper">${badge1}<div class="hs-date-separator">-</div><div class="hs-date-badge-v1"><div class="hs-db-month">${end.month}</div><div class="hs-db-day">${end.day}</div></div></div>`;
        }
        return badge1;
    }

    generateMobileDateText(startDateStr, endDateStr, cat) {
        const start = this.parseEventDate(startDateStr);
        let text = `${start.month} ${start.day}`;
        if (endDateStr) {
            const end = this.parseEventDate(endDateStr);
            if (start.month === end.month) {
                text += ` - ${end.day}`;
            } else {
                text += ` - ${end.month} ${end.day}`;
            }
        }
        return `${text} &bull; ${cat.toUpperCase()}`;
    }

    /* --- PAGINATION CONTROLS --- */
    changePage(dir) {
        this.currentPage += dir;
        this.applyFilterDisplay();
    }

    setPage(page) {
        this.currentPage = page;
        this.applyFilterDisplay();
    }

    changeModalPage(dir) {
        this.modalPage += dir;
        this.applyModalFilterDisplay();
    }

    setModalPage(page) {
        this.modalPage = page;
        this.applyModalFilterDisplay();
    }

    /* --- FILTERING ENGINE --- */
    filterEvents(category) {
        this.currentFilter = category;
        this.currentPage = 0; 
        
        const filterBtns = this.container.querySelectorAll('.hs-filter-btn');
        filterBtns.forEach(btn => {
            btn.classList.remove('active');
            if(btn.getAttribute('data-filter') === category) btn.classList.add('active');
        });

        const selects = this.container.querySelectorAll('.hs-mobile-filter');
        selects.forEach(select => {
            if(select) select.value = category;
        });

        if (this.modalWrapper) {
            const modalSelects = this.modalWrapper.querySelectorAll('.hs-modal-mobile-filter');
            modalSelects.forEach(select => {
                if(select) select.value = category;
            });
        }

        const cards = this.container.querySelectorAll('.hs-card-v1');
        cards.forEach(card => {
            if (category === 'all' || card.getAttribute('data-category') === category) {
                card.setAttribute('data-matches', 'true');
            } else {
                card.setAttribute('data-matches', 'false');
            }
        });

        this.applyFilterDisplay();

        if (this.modalWrapper) {
            const modalList = this.modalWrapper.querySelector('#modal-list-view');
            if (modalList && window.getComputedStyle(modalList).display !== 'none') {
                this.populateViewAllList();
            }
        }
    }

    /* --- LAYOUT & RESPONSIVE DASHBOARD PAGINATION --- */
    applyFilterDisplay() {
        const isMobile = window.innerWidth <= 767;
        const isTablet = window.innerWidth > 767 && window.innerWidth <= 980;
        const isDesktop = window.innerWidth > 980 && window.innerWidth <= 1130;
        const cards = this.container.querySelectorAll('.hs-card-v1');
        let matchedCards = [];

        cards.forEach(card => {
            if (card.getAttribute('data-matches') === 'true') {
                card.classList.remove('filtered-out');
                matchedCards.push(card);
            } else {
                card.classList.add('filtered-out');
                card.classList.remove('paged-hidden');
            }
        });

        let cardsPerPage = 5; 
        if (isMobile) cardsPerPage = 1;
        else if (isTablet) cardsPerPage = 4;
        else if (isDesktop) cardsPerPage = 4;
        
        const totalPages = Math.ceil(matchedCards.length / cardsPerPage);
        
        if (this.currentPage >= totalPages) this.currentPage = Math.max(0, totalPages - 1);
        if (this.currentPage < 0) this.currentPage = 0;

        const startIndex = this.currentPage * cardsPerPage;
        const endIndex = startIndex + cardsPerPage;

        matchedCards.forEach((card, index) => {
            if (index >= startIndex && index < endIndex) {
                card.classList.remove('paged-hidden');
            } else {
                card.classList.add('paged-hidden');
            }
        });

        const controls = this.container.querySelector('#carousel-controls');
        if (controls) {
            const carouselDivider = this.container.querySelector('#carousel-divider');
            const carouselDivider2 = this.container.querySelector('#carousel-divider-2');
            if (totalPages > 1) {
                controls.style.display = 'flex';
                if(carouselDivider) carouselDivider.style.display = 'block';
                if(carouselDivider2) carouselDivider2.style.display = 'block';
                const dotsContainer = controls.querySelector('#carousel-dots');
                if (dotsContainer) {
                    dotsContainer.innerHTML = '';
                    let maxDots = 5;
                    let startDot = Math.max(0, this.currentPage - Math.floor(maxDots / 2));
                    let endDot = startDot + maxDots - 1;
                    if (endDot >= totalPages) {
                        endDot = totalPages - 1;
                        startDot = Math.max(0, endDot - maxDots + 1);
                    }
                    for(let i = startDot; i <= endDot; i++) {
                        const dot = document.createElement('span');
                        dot.className = 'hs-dot' + (i === this.currentPage ? ' active' : '');
                        dot.addEventListener('click', () => this.setPage(i));
                        dotsContainer.appendChild(dot);
                    }
                }
                const prevBtn = controls.querySelector('.hs-carousel-prev');
                const nextBtn = controls.querySelector('.hs-carousel-next');
                if(prevBtn) prevBtn.disabled = this.currentPage === 0;
                if(nextBtn) nextBtn.disabled = this.currentPage === totalPages - 1;
            } else {
                controls.style.display = 'none';
                if(carouselDivider) carouselDivider.style.display = 'none';
                if(carouselDivider2) carouselDivider2.style.display = 'none';
            }
        }

        const track = this.container.querySelector('.hs-events-track');
        if (track) {
            track.classList.remove('hs-meta-stacked');
            void track.offsetHeight; 
            
            let needsStacking = false;
            const visibleCards = matchedCards.filter((card, index) => index >= startIndex && index < endIndex);
            
            for (const card of visibleCards) {
                const meta = card.querySelector('.hs-card-meta');
                if (meta && meta.offsetHeight > 25) { 
                    needsStacking = true;
                    break;
                }
            }
            
            if (needsStacking) {
                track.classList.add('hs-meta-stacked');
            }
        }
    }

    /* --- MODAL PAGINATION ENGINE --- */
    applyModalFilterDisplay() {
        if (!this.modalWrapper) return;
        const isMobile = window.innerWidth <= 767;
        const strips = this.modalWrapper.querySelectorAll('#dynamic-list-body .hs-list-strip');
        
        let stripsPerPage;

        if (isMobile) {
            stripsPerPage = 2;
        } else {
            const availableHeight = (window.innerHeight * 0.95) - 250; 
            stripsPerPage = Math.floor(availableHeight / 122);
            if (stripsPerPage < 2) stripsPerPage = 2;
            if (stripsPerPage > 7) stripsPerPage = 7;
        }
        
        const totalPages = Math.ceil(strips.length / stripsPerPage);
        
        if (this.modalPage >= totalPages) this.modalPage = Math.max(0, totalPages - 1);
        if (this.modalPage < 0) this.modalPage = 0;

        const startIndex = this.modalPage * stripsPerPage;
        const endIndex = startIndex + stripsPerPage;

        strips.forEach((strip, index) => {
            if (index >= startIndex && index < endIndex) {
                strip.style.display = '';
            } else {
                strip.style.display = 'none';
            }
        });

        let controls = this.modalWrapper.querySelector('#modal-carousel-controls');
        if (!controls) {
            controls = document.createElement('div');
            controls.id = 'modal-carousel-controls';
            controls.className = 'hs-carousel-controls-wrapper'; 
            controls.style.marginTop = '5px';
            controls.innerHTML = `
                <button class="hs-carousel-btn hs-modal-prev"><i class="fa-solid fa-arrow-left"></i></button>
                <div class="hs-carousel-dots hs-modal-dots"></div>
                <button class="hs-carousel-btn hs-modal-next"><i class="fa-solid fa-arrow-right"></i></button>
            `;
            const listBody = this.modalWrapper.querySelector('#dynamic-list-body');
            if(listBody) listBody.appendChild(controls);

            controls.querySelector('.hs-modal-prev').addEventListener('click', () => this.changeModalPage(-1));
            controls.querySelector('.hs-modal-next').addEventListener('click', () => this.changeModalPage(1));
        }

        if (totalPages > 1) {
            controls.style.display = 'flex';
            const carouselDivider = this.modalWrapper.querySelector('#carousel-divider');
            if(carouselDivider) carouselDivider.style.display = 'block';
            
            const dotsContainer = controls.querySelector('.hs-modal-dots');
            if (dotsContainer) {
                dotsContainer.innerHTML = '';
                let maxDots = 5;
                let startDot = Math.max(0, this.modalPage - Math.floor(maxDots / 2));
                let endDot = startDot + maxDots - 1;
                if (endDot >= totalPages) {
                    endDot = totalPages - 1;
                    startDot = Math.max(0, endDot - maxDots + 1);
                }
                for(let i = startDot; i <= endDot; i++) {
                    const dot = document.createElement('span');
                    dot.className = 'hs-dot' + (i === this.modalPage ? ' active' : '');
                    dot.addEventListener('click', () => this.setModalPage(i));
                    dotsContainer.appendChild(dot);
                }
            }
            controls.querySelector('.hs-modal-prev').disabled = this.modalPage === 0;
            controls.querySelector('.hs-modal-next').disabled = this.modalPage === totalPages - 1;
        } else {
            controls.style.display = 'none';
            const carouselDivider = this.modalWrapper.querySelector('#carousel-divider');
            if(carouselDivider) carouselDivider.style.display = 'none';
        }
    }

    /* --- MODAL GENERATION & DATA INJECTION --- */
    openSingleModalFromNode(cardElement) {
        if (!this.modalWrapper) return;
        this.currentActiveCardElement = cardElement;
        const modalList = this.modalWrapper.querySelector('#modal-list-view');
        const modalSingle = this.modalWrapper.querySelector('#modal-single-view');
        const overlay = this.modalWrapper.querySelector('#hs-modal-overlay');

        this.currentEventData = {
            title: cardElement.getAttribute('data-title'),
            date: cardElement.getAttribute('data-date'),
            isoDate: cardElement.getAttribute('data-iso-date'),
            endDate: cardElement.getAttribute('data-end-date'),
            isoEndDate: cardElement.getAttribute('data-iso-end-date'),
            time: cardElement.getAttribute('data-time'),
            rawStartTime: cardElement.getAttribute('data-raw-start-time'),
            rawEndTime: cardElement.getAttribute('data-raw-end-time'),
            allDay: cardElement.getAttribute('data-all-day') === 'true',
            loc: cardElement.getAttribute('data-loc'),
            blurb: cardElement.getAttribute('data-blurb'),
            registerLink: cardElement.getAttribute('data-register-link')
        };

        const modalImg = this.modalWrapper.querySelector('#modal-img');
        if(modalImg) modalImg.src = cardElement.getAttribute('data-img');
        
        const modalCat = this.modalWrapper.querySelector('#modal-cat');
        const cat = cardElement.getAttribute('data-cat');
        const catColor = cardElement.getAttribute('data-catcolor');
        if(modalCat) {
            modalCat.textContent = cat;
            modalCat.style.color = catColor;
        }

        const mTitle = this.modalWrapper.querySelector('#modal-title');
        if(mTitle) mTitle.textContent = this.currentEventData.title;
        
        const mDate = this.modalWrapper.querySelector('#modal-date');
        if(mDate) mDate.textContent = this.currentEventData.date;
        
        const mTime = this.modalWrapper.querySelector('#modal-time');
        if(mTime) mTime.textContent = this.currentEventData.time;
        
        const mLoc = this.modalWrapper.querySelector('#modal-loc');
        if(mLoc) mLoc.textContent = this.currentEventData.loc;
        
        const mBlurb = this.modalWrapper.querySelector('#modal-blurb');
        if(mBlurb) mBlurb.textContent = this.currentEventData.blurb;
        
        const dateContainer = this.modalWrapper.querySelector('#modal-date-container');
        if (dateContainer) {
            dateContainer.innerHTML = this.generateBadgeHTML(this.currentEventData.isoDate || this.currentEventData.date, this.currentEventData.isoEndDate || this.currentEventData.endDate);
        }

        const registerBtn = this.modalWrapper.querySelector('#modal-register-btn');
        if (registerBtn) {
            if (this.currentEventData.registerLink) {
                registerBtn.href = this.currentEventData.registerLink;
                registerBtn.style.display = 'inline-flex';
            } else {
                registerBtn.style.display = 'none';
            }
        }
        
        const modalMobileDate = this.modalWrapper.querySelector('#modal-mobile-date');
        if (modalMobileDate) {
            modalMobileDate.innerHTML = this.generateMobileDateText(this.currentEventData.isoDate || this.currentEventData.date, this.currentEventData.isoEndDate || this.currentEventData.endDate, cat);
        }

        if (modalList) modalList.style.display = 'none';
        if (modalSingle) modalSingle.style.display = 'flex';
        if (overlay) overlay.classList.add('active');
    }

    navigateSingleModal(dir) {
        if (!this.currentActiveCardElement) return;
        
        const allCards = Array.from(this.container.querySelectorAll('.hs-events-track .hs-card-v1'));
        const matchedCards = this.currentFilter === 'all' 
            ? allCards 
            : allCards.filter(card => card.getAttribute('data-category') === this.currentFilter);
            
        let idx = matchedCards.indexOf(this.currentActiveCardElement);
        if (idx === -1) return;
        
        idx += dir;
        if (idx < 0) idx = matchedCards.length - 1;
        if (idx >= matchedCards.length) idx = 0;
        
        this.openSingleModalFromNode(matchedCards[idx]);
    }

    populateViewAllList() {
        if (!this.modalWrapper) return;
        const listBody = this.modalWrapper.querySelector('#dynamic-list-body');
        if(!listBody) return;
        listBody.innerHTML = ''; 
        
        const cards = Array.from(this.container.querySelectorAll('.hs-card-v1')); 
        
        cards.sort((a, b) => {
            const dateA = a.getAttribute('data-iso-date') || a.getAttribute('data-date');
            const dateB = b.getAttribute('data-iso-date') || b.getAttribute('data-date');
            return dateB.localeCompare(dateA);
        });
        
        cards.forEach(card => {
            if(card.getAttribute('data-matches') !== 'true') return;

            const title = card.getAttribute('data-title');
            const img = card.getAttribute('data-img');
            const cat = card.getAttribute('data-cat');
            const time = card.getAttribute('data-time');
            const loc = card.getAttribute('data-loc');
            const dateStr = card.getAttribute('data-date');
            const endDateStr = card.getAttribute('data-end-date');
            
            const catColor = card.getAttribute('data-catcolor');
            const badgeHTML = this.generateBadgeHTML(card.getAttribute('data-iso-date') || dateStr, card.getAttribute('data-iso-end-date') || endDateStr);
            const mobileDateText = this.generateMobileDateText(card.getAttribute('data-iso-date') || dateStr, card.getAttribute('data-iso-end-date') || endDateStr, cat);

            const strip = document.createElement('div');
            strip.className = 'hs-list-strip';
            strip.addEventListener('click', () => this.openSingleModalFromNode(card));

            strip.innerHTML = `
                <div class="hs-list-img-wrap">
                    <div class="hs-category-chip" style="color:${catColor}">${cat}</div>
                    <img src="${img}" alt="${title}">
                    ${badgeHTML}
                </div>
                <div class="hs-list-content">
                    <div class="hs-list-mobile-date">${mobileDateText}</div>
                    <h3>${title}</h3>
                    <div class="hs-list-meta">
                        <span><i class="fa-regular fa-clock"></i> ${time}</span>
                        <span><i class="fa-solid fa-location-dot"></i> ${loc}</span>
                    </div>
                </div>
                <div class="hs-list-action">
                    <button class="hs-btn-side">Details <i class="fa-solid fa-chevron-right"></i></button>
                </div>
            `;
            listBody.appendChild(strip);
        });

        this.applyModalFilterDisplay(); 
    }

    openViewAllModal() {
        this.modalPage = 0; 
        this.populateViewAllList();
        
        if (!this.modalWrapper) return;
        const modalSingle = this.modalWrapper.querySelector('#modal-single-view');
        const modalList = this.modalWrapper.querySelector('#modal-list-view');
        const overlay = this.modalWrapper.querySelector('#hs-modal-overlay');

        if(modalSingle) modalSingle.style.display = 'none';
        if(modalList) modalList.style.display = 'flex';
        if(overlay) overlay.classList.add('active');
    }

    closeModal() {
        if (!this.modalWrapper) return;
        const overlay = this.modalWrapper.querySelector('#hs-modal-overlay');
        if(overlay) overlay.classList.remove('active');
        
        this.container.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
        this.modalWrapper.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
    }

    /* --- ICS EXPORT & DROPDOWNS --- */
    toggleEventCalDropdown(e) {
        e.stopPropagation();
        const drop = this.modalWrapper.querySelector('#cal-dropdown-event');
        const btn = this.modalWrapper.querySelector('#btn-add-cal-event');
        if(drop) drop.classList.toggle('active');
        if(btn) btn.classList.toggle('active');
        
        const gDrop = this.container.querySelector('#global-cal-dropdown');
        const gBtn = this.container.querySelector('#btn-global-cal');
        if(gDrop) gDrop.classList.remove('active');
        if(gBtn) gBtn.classList.remove('active');
    }

    toggleGlobalCalDropdown(e) {
        e.stopPropagation();
        const gDrop = this.container.querySelector('#global-cal-dropdown');
        const gBtn = this.container.querySelector('#btn-global-cal');
        if(gDrop) gDrop.classList.toggle('active');
        if(gBtn) gBtn.classList.toggle('active');
        
        if (this.modalWrapper) {
            const drop = this.modalWrapper.querySelector('#cal-dropdown-event');
            const btn = this.modalWrapper.querySelector('#btn-add-cal-event');
            if(drop) drop.classList.remove('active');
            if(btn) btn.classList.remove('active');
        }
    }

    subscribeCalendar(platform) {
        // Construct the global feed URLs based on the new feed-ics.php endpoint
        const siteUrl = window.location.origin;
        const campusFilter = this.container.getAttribute('data-feed-campus') || '';
        const campusParam = (campusFilter && campusFilter !== 'both') ? `&campus=${campusFilter}` : '';
        
        const feedUrl = `${siteUrl}/?feed=hs_events_calendar${campusParam}`;
        const webcalUrl = `webcal://${window.location.host}/?feed=hs_events_calendar${campusParam}`;
        
        if (platform === 'google') {
            const googleUrl = `https://calendar.google.com/calendar/r?cid=${encodeURIComponent(webcalUrl)}`;
            window.open(googleUrl, '_blank');
        } else {
            // Apple and Outlook native app handling
            window.location.href = webcalUrl;
        }

        this.container.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
        if (this.modalWrapper) {
            this.modalWrapper.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
        }
    }

    formatICSDateRaw(isoDate, rawTime) {
        if (!isoDate) return null;
        let dateStr = isoDate.replace(/-/g, ''); // Convert YYYY-MM-DD to YYYYMMDD
        if (rawTime) {
            // rawTime is HHMMSS (e.g. 090000)
            return `${dateStr}T${rawTime}`;
        }
        return dateStr;
    }

    addToCalendar(platform) {
        if (!this.currentEventData || !this.currentEventData.title) return;
        
        // Grab the raw data injected by PHP
        const isoStart = this.currentEventData.isoDate;
        const isoEnd = this.currentEventData.isoEndDate || isoStart;
        const rawStart = this.currentEventData.rawStartTime; 
        const rawEnd = this.currentEventData.rawEndTime;
        const isAllDay = this.currentEventData.allDay;

        let dtStart = this.formatICSDateRaw(isoStart, isAllDay ? null : rawStart);
        let dtEnd = this.formatICSDateRaw(isoEnd, isAllDay ? null : rawEnd);

        // Fallbacks if data is missing
        if (!dtStart) return;
        if (!dtEnd && isAllDay) dtEnd = dtStart;
        
        // Google requires a specific Z or floating time format. 
        // For Google template link, we can just pass the string.
        if (platform === 'google') {
            // For all-day events, Google expects YYYYMMDD/YYYYMMDD (but the end date is exclusive, so we should technically add a day, but Google usually handles it).
            let googleDates = `${dtStart}/${dtEnd}`;
            let url = `https://calendar.google.com/calendar/render?action=TEMPLATE&text=${encodeURIComponent(this.currentEventData.title)}&dates=${googleDates}&details=${encodeURIComponent(this.currentEventData.blurb)}&location=${encodeURIComponent(this.currentEventData.loc)}`;
            window.open(url, '_blank');
        } else {
            // ICS Blob generation for Apple/Outlook
            const icsContent = `BEGIN:VCALENDAR\r\nVERSION:2.0\r\nPRODID:-//HeadStart//Events//EN\r\nBEGIN:VEVENT\r\n` +
                (isAllDay ? `DTSTART;VALUE=DATE:${dtStart}\r\nDTEND;VALUE=DATE:${dtEnd}\r\n` : `DTSTART;TZID=Asia/Bangkok:${dtStart}\r\nDTEND;TZID=Asia/Bangkok:${dtEnd}\r\n`) +
                `SUMMARY:${this.currentEventData.title}\r\nDESCRIPTION:${this.currentEventData.blurb.replace(/\n/g, '\\n')}\r\nLOCATION:${this.currentEventData.loc}\r\nEND:VEVENT\r\nEND:VCALENDAR`;
            
            const blob = new Blob([icsContent], { type: 'text/calendar;charset=utf-8' });
            const link = document.createElement('a');
            link.href = window.URL.createObjectURL(blob);
            link.setAttribute('download', `${this.currentEventData.title.replace(/[^a-z0-9]/gi, '_').toLowerCase()}.ics`);
            document.body.appendChild(link);
            link.click();
            document.body.removeChild(link);
        }
        
        this.container.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
        if (this.modalWrapper) {
            this.modalWrapper.querySelectorAll('.hs-cal-dropdown, .hs-btn-add-cal').forEach(el => el.classList.remove('active'));
        }
    }
}

/* --- INIT FUNCTION --- */
function initAllCalendars() {
    // Find all calendars on the page and instantiate them
    const calendars = document.querySelectorAll('.hs-section-events-calendar');
    calendars.forEach(cal => {
        // Only initialize if not already initialized
        if (!cal.hsCalendarInstance) {
            cal.hsCalendarInstance = new HeadStartEventsCalendar(cal);
        }
    });

    // Initialize Sidebar Interactions globally (since they don't use the calendar class wrapper)
    const sidebarItems = document.querySelectorAll('.hs-events-sidebar-item');
    sidebarItems.forEach(item => {
        item.addEventListener('click', () => {
            item.classList.toggle('is-expanded'); // Toggle allows mobile users to close it
        });
        item.addEventListener('mouseleave', () => {
            item.classList.remove('is-expanded');
        });
    });
}

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initAllCalendars);
} else {
    initAllCalendars();
}
