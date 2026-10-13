
/**
 * =============================================================================
 * HEADSTART WEEKLY UPDATE ENGINE V2 (AJAX + DELEGATION)
 * =============================================================================
 * PURPOSE: Handles weekly updates via AJAX and event delegation.
 * LOGIC: Optimized for 10-year scale for large data fetching.
 * DEPENDENCIES: AJAX endpoints
 * =============================================================================
 */

(function() {
    function runWeeklyUpdateEngine() {
        const container = document.querySelector('.hs-weekly-update-v2');
        if (!container) return; // Not on this page

        // DOM Elements
        const cardsContainer = container.querySelector('.hs-update-cards-container');
        const controlsWrapper = container.querySelector('.hs-carousel-controls-wrapper');
        const prevBtn = container.querySelector('.hs-carousel-prev');
        const nextBtn = container.querySelector('.hs-carousel-next');
        const dotsContainer = container.querySelector('.hs-pagination-dots');
        const jumpFirstBtn = container.querySelector('.hs-jump-first');
        const yearFilter = container.querySelector('#hs-year-filter');

        // State
        const campus = container.getAttribute('data-campus') || 'all';
        let currentDisplayPage = 0;
        let cardsPerPage = 4;
        const chunkSize = 20; // Must match posts_per_page in PHP
        let totalPosts = 0;
        let totalDisplayPages = 0;
        let currentFilterYear = 'all';
        
        // Cache structure: filterCache['all'] = { cards: [], nextChunkToFetch: 1, fullyLoaded: false }
        let filterCache = {}; 
        let isFetching = false;
        
        // Configuration from localized script
        // Securely load config via Base64 payload instead of global variable
        let config = {};
        const configNode = document.getElementById('hs-weekly-update-config-v2');
        if (configNode && configNode.dataset.config) {
            try {
                config = JSON.parse(atob(configNode.dataset.config));
            } catch(e) {
                console.error("Failed to parse Weekly Update config:", e);
            }
        }

        function getPagesForFilter(year) {
            const cache = filterCache[year];
            if (!cache || !cache.cards) return [];
            
            const pages = [];
            let currentPageCards = [];
            let currentWeight = 0;
            const maxWeight = (window.innerWidth <= 767) ? 1 : 4;
            
            for (const card of cache.cards) {
                const cardWeight = (card.is_legacy || window.innerWidth <= 767) ? 1 : 2;
                if (currentWeight + cardWeight > maxWeight) {
                    if (currentPageCards.length > 0) pages.push(currentPageCards);
                    currentPageCards = [card];
                    currentWeight = cardWeight;
                } else {
                    currentPageCards.push(card);
                    currentWeight += cardWeight;
                }
            }
            if (currentPageCards.length > 0) pages.push(currentPageCards);
            return pages;
        }

        // Adjust cards per page based on responsive breakpoints
        function updateCardsPerPage() {
            if (totalPosts > 0) {
                const pages = getPagesForFilter(currentFilterYear);
                const cache = filterCache[currentFilterYear];
                if (cache && cache.fullyLoaded) {
                    totalDisplayPages = pages.length;
                } else {
                    const estPer = (window.innerWidth <= 767) ? 1 : 3;
                    totalDisplayPages = Math.max(pages.length, Math.ceil(totalPosts / estPer));
                }
                
                if (currentDisplayPage >= totalDisplayPages && totalDisplayPages > 0) {
                    currentDisplayPage = Math.max(0, totalDisplayPages - 1);
                }
            }
        }
        
        // Listen for resize to recalculate pagination
        let resizeTimer;
        let lastWidth = window.innerWidth;
        window.addEventListener('resize', () => {
            if (window.innerWidth === lastWidth) return;
            lastWidth = window.innerWidth;
            clearTimeout(resizeTimer);
            resizeTimer = setTimeout(() => {
                if (totalPosts > 0) {
                    updateCardsPerPage();
                    renderCurrentDisplayPage(true);
                }
            }, 100);
        });
        
        updateCardsPerPage();

        // -----------------------------------------------------------
        // FETCH & CACHE LOGIC
        // -----------------------------------------------------------
        
        async function fetchNextChunkForFilter(year) {
            let cache = filterCache[year];
            if (!cache) {
                cache = filterCache[year] = { cards: [], nextChunkToFetch: 1, fullyLoaded: false };
            }
            
            if (cache.fullyLoaded) return true;

            const formData = new URLSearchParams();
            formData.append('action', 'hs_load_weekly_updates');
            formData.append('nonce', config.nonce);
            formData.append('campus', campus);
            formData.append('year', year);
            formData.append('page', cache.nextChunkToFetch);

            try {
                const response = await fetch(config.ajaxUrl, {
                    method: 'POST',
                    body: formData,
                    headers: { 'Content-Type': 'application/x-www-form-urlencoded' }
                });
                const json = await response.json();
                
                if (json.success) {
                    const data = json.data;
                    cache.cards = cache.cards.concat(data.cards);
                    totalPosts = data.total_posts;
                    cache.nextChunkToFetch++;
                    
                    if (cache.cards.length >= totalPosts || data.cards.length < chunkSize) {
                        cache.fullyLoaded = true;
                    }
                    return true;
                }
                return false;
            } catch (error) {
                console.error("Weekly Update Fetch Error:", error);
                return false;
            }
        }

        // -----------------------------------------------------------
        // RENDER LOGIC
        // -----------------------------------------------------------
        
        // Builds the HTML string for a single card
        function buildCardHTML(card) {
            // Strip dates like ", 26 June 2026" or " - 26th June 2026" from the end of the title
            const cleanTitle = card.title.replace(/[,|-]\s*\d{1,2}(?:st|nd|rd|th)?\s+[A-Za-z]+\s+\d{4}\s*$/i, '').trim();

            if (card.is_legacy) {
                return `
                <article class="weekly-card is-legacy" data-year="${card.acad_year}">
                    <div class="weekly-card-img-wrapper">
                        <img src="${card.img_url}" class="weekly-card-img" alt="${cleanTitle}">
                        <div class="top-overlay"></div>
                        <div class="standard-overlay"></div>
                        <div class="legacy-meta-wrapper">
                            <div class="legacy-meta">
                                <span class="legacy-badge"><i class="fa-solid fa-box-archive"></i> Archive</span>
                                <span class="legacy-meta-text">${card.legacy_date} <span class="hs-hide-mobile">&bull;</span><br class="hs-show-mobile-only"> Academic Year ${card.acad_year}</span>
                            </div>
                        </div>
                    </div>
                    <div class="weekly-card-content">
                        <div class="hs-legacy-inner-flex">
                            <div class="hs-legacy-flex-1">
                                <h3 class="legacy-title">
                                    <a href="${card.link}" class="hs-card-link">${cleanTitle}</a>
                                </h3>
                            </div>
                            <span class="hs-card-arrow hs-hide-mobile"><i class="fa-solid fa-arrow-right"></i></span>
                        </div>
                    </div>
                </article>`;
            } else {
                let tagsHtml = '';
                if (card.tags && card.tags.length > 0) {
                    const maxTags = 3;
                    const visibleTags = card.tags.slice(0, maxTags);
                    const hiddenTags = card.tags.slice(maxTags);
                    
                    let chips = visibleTags.map(t => `<span class="hs-chip"><i class="fa-solid fa-check"></i>${t}</span>`).join('');
                    if (hiddenTags.length > 0) {
                        chips += `<span class="hs-chip hs-chip-more hs-show-more-tags" title="Show more tags" style="cursor: pointer; background: var(--hs-gold, #f0b323); color: #003057; font-weight: bold;">+${hiddenTags.length}</span>`;
                        // Render hidden tags
                        chips += hiddenTags.map(t => `<span class="hs-chip hs-hidden-tag" style="display:none;"><i class="fa-solid fa-check"></i>${t}</span>`).join('');
                        chips += `<span class="hs-chip hs-chip-more hs-show-less-tags hs-hidden-tag" title="Show fewer tags" style="cursor: pointer; background: var(--hs-gold, #f0b323); color: #003057; font-weight: bold; display: none;">-</span>`;
                    }
                    tagsHtml = `<div class="hs-chip-container" style="display: flex; flex-wrap: wrap; gap: 6px;">${chips}</div>`;
                }
                
                const weekBadge = card.week_num ? `<div class="hs-card-issue-badge">Week ${card.week_num}</div>` : '';
                let draftBadge = '';
                if (card.status === 'draft' || card.status === 'private') {
                    draftBadge = `<div class="hs-card-issue-badge" style="background-color: #dc3545; color: white; right: 15px; left: auto;">DRAFT</div>`;
                }
                const dateString = card.week_num ? `Week ${card.week_num} - ${card.display_date}` : card.display_date;
                const cleanExcerpt = card.excerpt.replace(/\s*\[(?:&hellip;|\.\.\.)\]\s*$/, '').replace(/\s*(?:&hellip;|\.\.\.)\s*$/, '');

                return `
                <article class="weekly-card" data-year="${card.acad_year}">
                    <div class="weekly-card-img-wrapper">
                        <img src="${card.img_url}" class="weekly-card-img" alt="${cleanTitle}">
                        <div class="standard-overlay"></div>
                        ${weekBadge}
                        ${draftBadge}
                        <div class="hs-card-img-content" style="position: absolute; bottom: 0; left: 0; width: 100%; padding: 1.25rem; z-index: 10;">
                            <h3 class="legacy-title" style="margin-bottom: 0;">
                                <a href="${card.link}" class="hs-card-link" style="color: #fff;">${cleanTitle}</a>
                            </h3>
                        </div>
                    </div>
                    <div class="weekly-card-content">
                        <div class="hs-card-header">
                            <div class="hs-card-header-text">
                                <div class="hs-card-meta-row">
                                    <div class="hs-card-meta-year">Academic Year ${card.acad_year}</div>
                                    <div class="hs-card-meta-date">${dateString}</div>
                                </div>
                            </div>
                            <div class="hs-card-cta-desktop hs-hide-mobile">
                                <a href="${card.link}" class="hs-btn-sm" style="position: relative; z-index: 10;">Read Update <i class="fa-solid fa-chevron-right"></i></a>
                            </div>
                        </div>
                        <div class="hs-blurb-wrapper">
                            <div class="hs-blurb">${cleanExcerpt}</div>
                        </div>
                        <div class="hs-card-footer" style="display: flex; justify-content: space-between; align-items: flex-end; gap: 16px;">
                            <div class="hs-card-footer-left" style="flex: 1; min-width: 0;">
                                <div class="hs-card-footer-title">In this issue:</div>
                                <div class="hs-card-footer-chips">
                                    ${tagsHtml}
                                </div>
                            </div>
                            <div class="hs-card-footer-right" style="display: flex; flex-direction: column; align-items: flex-end; gap: 8px; flex-shrink: 0; min-height: 28px; justify-content: flex-end;">
                                <button class="hs-expand-blurb" style="display: none;"><i class="fa-solid fa-plus"></i></button>
                                <button class="hs-collapse-btn hs-collapse-desktop hs-hide-mobile" title="Collapse view"><i class="fa-solid fa-chevron-up"></i></button>
                            </div>
                        </div>
                    </div>
                    <button class="hs-collapse-btn hs-collapse-mobile hs-show-mobile-only" title="Collapse view"><i class="fa-solid fa-chevron-up"></i></button>
                </article>`;
            }
        }

        async function ensureDisplayPageLoaded(displayPageIdx) {
            let cache = filterCache[currentFilterYear];
            if (!cache) {
                cache = filterCache[currentFilterYear] = { cards: [], nextChunkToFetch: 1, fullyLoaded: false };
            }
            
            let pages = getPagesForFilter(currentFilterYear);
            const maxWeight = (window.innerWidth <= 767) ? 1 : 4;
            
            function getPageWeight(pageArray) {
                return pageArray ? pageArray.reduce((s, c) => s + ((c.is_legacy || window.innerWidth <= 767) ? 1 : 2), 0) : 0;
            }
            
            // Loop fetching if the requested page is missing or incomplete, AND we have more to fetch
            while ((!pages[displayPageIdx] || getPageWeight(pages[displayPageIdx]) < maxWeight) && !cache.fullyLoaded) {
                isFetching = true;
                
                // Show skeletons while fetching
                let skeletons = '';
                for(let i=0; i < (window.innerWidth <= 767 ? 1 : 4); i++) {
                    skeletons += `
                    <article class="weekly-card is-legacy hs-skeleton-card" style="background: rgba(0, 48, 87, 0.05); border: 1px solid #e0e0e0;">
                        <div class="weekly-card-img-wrapper" style="display: flex; align-items: center; justify-content: center; position: absolute; inset: 0;">
                            <i class="fa-solid fa-spinner fa-spin" style="font-size: 2rem; color: var(--hs-blue, #0077c8); opacity: 0.5;"></i>
                        </div>
                        <div class="weekly-card-content" style="opacity: 0.6; z-index: 1;">
                            <div class="hs-legacy-inner-flex">
                                <div class="hs-legacy-flex-1">
                                    <div style="height: 14px; background: #e0e0e0; width: 40%; margin-bottom: 12px; border-radius: 4px;"></div>
                                    <div style="height: 24px; background: #e0e0e0; width: 70%; border-radius: 4px;"></div>
                                </div>
                            </div>
                        </div>
                    </article>`;
                }
                cardsContainer.innerHTML = skeletons;
                
                const success = await fetchNextChunkForFilter(currentFilterYear);
                isFetching = false;
                if (!success) {
                    cardsContainer.innerHTML = '<p class="hs-no-posts">Failed to load updates. Please try again.</p>';
                    return null;
                }
                pages = getPagesForFilter(currentFilterYear);
            }
            
            updateCardsPerPage();
            return pages;
        }

        async function renderCurrentDisplayPage(preventScroll = false) {
            if (isFetching) return;
            
            const pages = await ensureDisplayPageLoaded(currentDisplayPage);
            if (!pages) return; // Error occurred

            const cardsToDisplay = pages[currentDisplayPage] || [];

            if (cardsToDisplay.length === 0 && totalPosts === 0) {
                cardsContainer.innerHTML = '<p class="hs-no-posts">No weekly updates found.</p>';
                controlsWrapper.style.display = 'none';
                return;
            }

            // Record scroll target BEFORE mutating DOM to avoid layout shift issues
            let scrollTarget = null;
            if (!preventScroll && (currentDisplayPage > 0 || currentFilterYear !== 'all')) {
                const rect = container.getBoundingClientRect();
                scrollTarget = window.pageYOffset + rect.top - 100;
            }

            // Lock container height temporarily to prevent violent scroll jumps during DOM swap
            const prevHeight = cardsContainer.offsetHeight;
            if (prevHeight > 0) {
                cardsContainer.style.minHeight = prevHeight + 'px';
            }

            // Build HTML efficiently in memory
            let htmlString = '';
            cardsToDisplay.forEach(card => {
                htmlString += buildCardHTML(card);
            });

            // Write to DOM exactly once (prevents thrashing)
            cardsContainer.innerHTML = htmlString;
            
            // Check for blurbs that need 'Read more' button
            const domCards = cardsContainer.querySelectorAll('.weekly-card:not(.is-legacy)');
            domCards.forEach(card => {
                const blurb = card.querySelector('.hs-blurb');
                const wrapper = card.querySelector('.hs-blurb-wrapper');
                const btn = card.querySelector('.hs-expand-blurb');
                if (blurb && btn && wrapper) {
                    // Show if blurb line-clamp overflows (mobile) OR wrapper box overflows (desktop flex hidden)
                    if (blurb.scrollHeight > blurb.clientHeight || wrapper.scrollHeight > wrapper.clientHeight) {
                        btn.style.display = 'inline-flex';
                        card.classList.add('is-overflowing');
                    }
                }
            });

            controlsWrapper.style.display = 'flex';
            updateControls();
            
            // Scroll to top of container smoothly if needed
            if (scrollTarget !== null) {
                window.scrollTo({ top: scrollTarget, behavior: 'smooth' });
            }

            // Release the height lock after rendering
            setTimeout(() => {
                cardsContainer.style.minHeight = '';
                // Re-bind DOM logic safely without global MutationObserver
                if (window.hsCore && typeof window.hsCore.initWeeklyLightboxes === 'function') {
                    window.hsCore.initWeeklyLightboxes();
                }
                if (window.hsCore && typeof window.hsCore.initGlobalReadMore === 'function') {
                    window.hsCore.initGlobalReadMore();
                }
            }, 100);
        }

        // -----------------------------------------------------------
        // PAGINATION CONTROLS
        // -----------------------------------------------------------
        function updateControls() {
            const pages = getPagesForFilter(currentFilterYear);
            const cache = filterCache[currentFilterYear];
            
            prevBtn.disabled = (currentDisplayPage === 0);
            
            // Next button is only definitively disabled if we are on the last rendered page AND no more cards exist
            const isLastKnownPage = (currentDisplayPage >= pages.length - 1);
            if (isLastKnownPage && cache && cache.fullyLoaded) {
                nextBtn.disabled = true;
            } else {
                nextBtn.disabled = false;
            }
            
            // Render Dynamic 5-Dot Visual Indicator (No Numbers)
            const numDots = Math.min(5, totalDisplayPages);
            let activeDotIndex = 0;
            
            if (totalDisplayPages <= 5) {
                activeDotIndex = currentDisplayPage;
            } else {
                if (currentDisplayPage === 0) {
                    activeDotIndex = 0;
                } else if (currentDisplayPage === 1) {
                    activeDotIndex = 1;
                } else if (currentDisplayPage === totalDisplayPages - 2) {
                    activeDotIndex = 3;
                } else if (currentDisplayPage === totalDisplayPages - 1) {
                    activeDotIndex = 4;
                } else {
                    activeDotIndex = 2; // Stay in middle for all intermediate pages
                }
            }

            let dotsHtml = '';
            for (let i = 0; i < numDots; i++) {
                const isActive = (i === activeDotIndex) ? 'active' : '';
                // Dots are strictly visual indicators now (no numbers), so we don't attach data-page jump attributes
                dotsHtml += `<span class="hs-dot ${isActive}"></span>`;
            }
            dotsContainer.innerHTML = dotsHtml;
        }

        // -----------------------------------------------------------
        // EVENT DELEGATION
        // -----------------------------------------------------------
        
        // Handle clicks on cards safely without attaching listeners in loops
        cardsContainer.addEventListener('click', (e) => {
            const moreTagsBtn = e.target.closest('.hs-show-more-tags');
            if (moreTagsBtn) {
                const container = moreTagsBtn.closest('.hs-chip-container');
                if (container) {
                    container.querySelectorAll('.hs-hidden-tag').forEach(tag => tag.style.display = 'inline-flex');
                    moreTagsBtn.style.display = 'none';
                }
                return;
            }

            const lessTagsBtn = e.target.closest('.hs-show-less-tags');
            if (lessTagsBtn) {
                const container = lessTagsBtn.closest('.hs-chip-container');
                if (container) {
                    container.querySelectorAll('.hs-hidden-tag').forEach(tag => tag.style.display = 'none');
                    const moreBtn = container.querySelector('.hs-show-more-tags');
                    if (moreBtn) moreBtn.style.display = 'inline-flex';
                }
                return;
            }

            const expandBtn = e.target.closest('.hs-expand-blurb');
            if (expandBtn) {
                const card = expandBtn.closest('.weekly-card');
                if (card) {
                    card.classList.add('expanded');
                    expandBtn.style.display = 'none';
                }
                return;
            }
            
            // Collapse
            const collapseBtn = e.target.closest('.hs-collapse-btn');
            if (collapseBtn) {
                const card = collapseBtn.closest('.weekly-card');
                if (card) {
                    card.classList.remove('expanded');
                    const exp = card.querySelector('.hs-expand-blurb');
                    if (exp) exp.style.display = 'inline-block';
                    
                    // Smooth scroll back to top of the card
                    const yOffset = -220; 
                    const y = card.getBoundingClientRect().top + window.pageYOffset + yOffset;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                }
                return;
            }
        });

        // Pagination Clicks
        prevBtn.addEventListener('click', () => {
            if (currentDisplayPage > 0 && !isFetching) {
                currentDisplayPage--;
                renderCurrentDisplayPage();
            }
        });

        nextBtn.addEventListener('click', () => {
            if (currentDisplayPage < totalDisplayPages - 1 && !isFetching) {
                currentDisplayPage++;
                renderCurrentDisplayPage();
            }
        });

        // Dots are strictly visual now (no numbers), so clicking them does nothing.
        // (Use Next/Prev buttons to navigate)

        jumpFirstBtn.addEventListener('click', () => {
            if (currentFilterYear !== 'all' || currentDisplayPage !== 0) {
                currentFilterYear = 'all';
                currentDisplayPage = 0;
                if (yearFilter) yearFilter.value = 'all';
                renderCurrentDisplayPage();
            }
        });

        // Year Filter with basic Debounce
        let filterTimeout;
        if (yearFilter) {
            yearFilter.addEventListener('change', (e) => {
                clearTimeout(filterTimeout);
                filterTimeout = setTimeout(() => {
                    currentFilterYear = e.target.value;
                    currentDisplayPage = 0; // Reset to page 1 of new filter
                    renderCurrentDisplayPage();
                }, 300); // 300ms debounce
            });
        }

        // INITIALIZE
        renderCurrentDisplayPage();
    }

    // Run when DOM is ready
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', runWeeklyUpdateEngine);
    } else {
        runWeeklyUpdateEngine();
    }
})();
