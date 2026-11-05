
/**
 * =============================================================================
 * NOTICEBOARD ENGINE (Campus Toggle + Local Preload)
 * =============================================================================
 * PURPOSE: Logic and execution for Noticeboard AJAX, Modals, and Campus Toggle
 * DEPENDENCIES: WordPress core, Divi
 * =============================================================================
 */
document.addEventListener('DOMContentLoaded', function() {
    const isDiviBuilder = window.location.href.includes('et_fb=1') || window.location.href.includes('et_pb_preview=true') || window.top !== window.self;
    if (isDiviBuilder) return;

    const wrapperEl = document.getElementById('hs-noticeboard-wrapper');
    if (!wrapperEl) return;

    let currentCampus = wrapperEl.getAttribute('data-campus') || 'all';

    function formatCampusName(slug) {
        if (slug === 'chaofah') return "Chaofah";
        if (slug === 'cherngtalay') return "Cherngtalay";
        if (slug === 'all') return "All Campuses";
        return slug.toUpperCase();
    }

    function updateChipName() {
        const chip = document.getElementById('hs-campus-chip');
        if (chip) {
            chip.innerText = formatCampusName(currentCampus);
        }
    }
    
    updateChipName();

    window.hsToggleCampus = function() {
        if (currentCampus === 'chaofah') {
            currentCampus = 'cherngtalay';
        } else if (currentCampus === 'cherngtalay') {
            currentCampus = 'chaofah';
        } else {
            currentCampus = 'chaofah'; // default if all or unknown
        }
        
        wrapperEl.setAttribute('data-campus', currentCampus);
        updateChipName();
        fetchNotices();
    }

    const modalEl = document.getElementById('hs-notice-modal');
    if (modalEl && modalEl.parentNode !== document.body) {
        document.body.appendChild(modalEl);
    }

    let currentPage = 1;
    const itemsPerPage = 3;


    function renderPinnedNotice() {
        const container = document.getElementById('hs-pinned-notice-container');
        if (!container) return;
        
        const pinned = window.hsNoticeData['pinned'];
        if (!pinned) {
            container.innerHTML = '';
            return;
        }
        
        container.innerHTML = `
            <div data-notice-modal="pinned" class="hs-pinned-card hs-noticeboard-trigger" onclick="window.openNoticeModal('pinned')">
                <div class="hs-pinned-card-glow"></div>
                <div class="hs-pinned-tag-wrapper">
                    <span class="hs-pinned-tag">
                        <i class="fas fa-thumbtack"></i>Pinned Notice
                    </span>
                </div>
                
                <h4 class="hs-pinned-heading">
                    ${pinned.title}
                </h4>
                
                <div class="hs-pinned-read-more">
                    Read More <i class="fas fa-arrow-right"></i>
                </div>
            </div>
        `;
    }

    function renderNoticeList() {
        const listContainer = document.getElementById('hs-older-notices-list');
        if (!listContainer) return;

        if(!window.hsStandardNoticeIDs || window.hsStandardNoticeIDs.length === 0) {
            listContainer.innerHTML = '<li style="padding: 1.5rem; text-align: center; color: #666; font-family: sans-serif; list-style: none;">No older notices found for this campus.</li>';
            renderPaginationControls();
            return;
        }
        
        const start = (currentPage - 1) * itemsPerPage;
        const end = start + itemsPerPage;
        const currentIDs = window.hsStandardNoticeIDs.slice(start, end);
        
        listContainer.innerHTML = '';
        
        currentIDs.forEach(id => {
            const notice = window.hsNoticeData[id];
            const importantTag = notice.isImportant ? '<span class="hs-important-tag"><i class="fas fa-exclamation-circle"></i>Important</span>' : '';

            const listItemHTML = `
                <li data-notice-modal="${id}" class="hs-older-notice-item hs-noticeboard-trigger" onclick="window.openNoticeModal('${id}')">
                    <div class="hs-older-notice-border">
                        ${importantTag ? `<div class="hs-important-tag-wrapper">${importantTag}</div>` : ''}
                        <h4 class="hs-older-notice-title">${notice.title}</h4>
                        <div class="hs-older-notice-read-more">
                            Read More <i class="fas fa-arrow-right"></i>
                        </div>
                    </div>
                </li>
            `;
            listContainer.innerHTML += listItemHTML;
        });

        renderPaginationControls();
    }

    function renderPaginationControls() {
        const pagContainer = document.getElementById('hs-notice-pagination');
        if (!pagContainer) return;
        
        if (!window.hsStandardNoticeIDs || window.hsStandardNoticeIDs.length === 0) {
            pagContainer.innerHTML = '';
            pagContainer.classList.add('hidden');
            pagContainer.style.display = 'none';
            return;
        }
        
        const totalPages = Math.ceil(window.hsStandardNoticeIDs.length / itemsPerPage);
        if (totalPages <= 1) {
            pagContainer.innerHTML = '';
            pagContainer.classList.add('hidden');
            pagContainer.style.display = 'none';
            return;
        }
        
        pagContainer.classList.remove('hidden');
        pagContainer.style.display = 'flex';
        
        let html = '';
        const prevDisabled = currentPage === 1 ? 'disabled' : '';
        const prevClass = currentPage === 1 ? 'hs-pagination-btn nav disabled' : 'hs-pagination-btn nav';
        
        html += `<button onclick="window.changeNoticePage(${currentPage - 1})" ${prevDisabled} class="${prevClass}">
                    <i class="fas fa-chevron-left"></i>
                 </button>`;
        
        for (let i = 1; i <= totalPages; i++) {
            if (i === currentPage) {
                html += `<button class="hs-pagination-btn active">${i}</button>`;
            } else {
                html += `<button onclick="window.changeNoticePage(${i})" class="hs-pagination-btn inactive">${i}</button>`;
            }
        }
        
        const nextDisabled = currentPage === totalPages ? 'disabled' : '';
        const nextClass = currentPage === totalPages ? 'hs-pagination-btn nav disabled' : 'hs-pagination-btn nav';
            
        html += `<button onclick="window.changeNoticePage(${currentPage + 1})" ${nextDisabled} class="${nextClass}">
                    <i class="fas fa-chevron-right"></i>
                 </button>`;
                 
        pagContainer.innerHTML = html;
    }

    window.changeNoticePage = function(pageNumber) {
        const totalPages = Math.ceil(window.hsStandardNoticeIDs.length / itemsPerPage);
        if (pageNumber >= 1 && pageNumber <= totalPages) {
            currentPage = pageNumber;
            renderNoticeList();
        }
    }

    window.openNoticeModal = function(id) {
        const notice = window.hsNoticeData[id];
        if(!notice) return;

        const hsModal = document.getElementById('hs-notice-modal');
        const hsModalContainer = document.getElementById('hs-modal-container');
        const imgContainer = document.getElementById('hs-modal-img-container');
        const img = document.getElementById('hs-modal-img');
        
        if(!hsModal || !hsModalContainer) return;

        document.getElementById('hs-modal-title').innerHTML = notice.title;
        document.getElementById('hs-modal-body').innerHTML = notice.body;
        
        if (notice.image) {
            imgContainer.classList.remove('hidden');
            imgContainer.style.display = 'block';
            img.src = notice.image;
        } else {
            imgContainer.classList.add('hidden');
            imgContainer.style.display = 'none';
        }
        
        hsModal.style.display = 'flex';
        hsModal.classList.remove('hidden');
        
        setTimeout(() => {
            hsModalContainer.classList.add('is-open');
        }, 10);
        
        document.body.style.overflow = 'hidden'; 
    }

    window.closeNoticeModal = function() {
        const hsModal = document.getElementById('hs-notice-modal');
        const hsModalContainer = document.getElementById('hs-modal-container');
        if(!hsModal || !hsModalContainer) return;

        hsModalContainer.classList.remove('is-open');
        
        setTimeout(() => {
            hsModal.style.display = 'none';
            hsModal.classList.add('hidden');
            document.body.style.overflow = ''; 
        }, 300);
    }

    document.addEventListener('keydown', (e) => {
        const hsModal = document.getElementById('hs-notice-modal');
        if (e.key === 'Escape' && hsModal && !hsModal.classList.contains('hidden')) {
            window.closeNoticeModal();
        }
    });

    function fetchNotices() {
        const loadingEl = document.getElementById('hs-noticeboard-loading');
        const listEl = document.getElementById('hs-older-notices-list');
        const pagContainer = document.getElementById('hs-notice-pagination');
        const pinnedContainer = document.getElementById('hs-pinned-notice-container');
        
        if (loadingEl) loadingEl.style.display = 'block';
        if (listEl) {
            listEl.style.display = 'none';
            listEl.innerHTML = '';
        }
        if (pagContainer) pagContainer.style.display = 'none';
        if (pinnedContainer) pinnedContainer.innerHTML = '';
        
        // Use preloaded JSON payload from PHP instead of AJAX
        if (window.hsNoticeDataPreload && window.hsNoticeDataPreload[currentCampus]) {
            const payload = window.hsNoticeDataPreload[currentCampus];
            window.hsNoticeData = payload.noticeData;
            window.hsStandardNoticeIDs = payload.standardIDs;
            
            if (loadingEl) loadingEl.style.display = 'none';
            if (listEl) {
                listEl.style.display = 'block';
                listEl.classList.remove('hidden');
            }
            
            currentPage = 1;
            renderPinnedNotice();
            renderNoticeList();
        } else {
            console.error('Noticeboard Preload Data Missing!');
            if (loadingEl) loadingEl.innerHTML = 'Error loading notices.';
        }
    }

    fetchNotices();

});
