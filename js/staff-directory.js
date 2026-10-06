
/**
 * =============================================================================
 * HEADSTART STAFF DIRECTORY ENGINE (V3)
 * =============================================================================
 * PURPOSE: Processes JSON data into a Tetris-style bin-packing grid layout.
 * LOGIC: Data taxonomy driven engine for staff directories.
 * DEPENDENCIES: Staff JSON Data, Divi grid
 * =============================================================================
 */
const initDirectory = async () => {
    const grid = document.getElementById('main-staff-grid');
    const modalOverlay = document.getElementById('staff-modal-overlay');
    const modalClose = document.getElementById('staff-modal-close');
    const modalImg = document.getElementById('modal-img');
    const modalName = document.getElementById('modal-name');
    const modalTitle = document.getElementById('modal-title');
    const modalDept = document.getElementById('modal-dept');
    const modalCampus = document.getElementById('modal-campus');
    const modalBlurb = document.getElementById('modal-blurb');

    // 0. FIX STACKING CONTEXT ISSUES
    // Move modal to body so it escapes Divi's z-index trapping, wrapped in our scoped class
    if (modalOverlay && modalOverlay.parentNode) {
        const wrapper = document.createElement('div');
        wrapper.className = 'hs-staff-directory hs-modal-global-wrapper';
        wrapper.style.position = 'fixed'; 
        wrapper.style.zIndex = '2147483647';
        wrapper.style.pointerEvents = 'none';
        wrapper.style.top = '0';
        wrapper.style.left = '0';
        wrapper.appendChild(modalOverlay);
        document.body.appendChild(wrapper);
    }

    // --- Tetris Layout State ---
    const strictOrderDepts = ['Foundation', 'Primary', 'Leadership Team'];
    let lastCardsPerRow = getCardsPerRow();

    function getCardsPerRow() {
        const w = window.innerWidth;
        if (w >= 1600) return 7;
        if (w >= 1400) return 6;
        if (w >= 1200) return 5;
        if (w >= 992) return 4;
        if (w >= 768) return 3;
        if (w >= 480) return 2;
        return 1;
    }

    function tetrisPackGroups(subGroupNames, subGroupsObj, maxCardsPerRow) {
        let remaining = [...subGroupNames];
        let packed = [];
        let currentRowSpace = maxCardsPerRow;
        
        while(remaining.length > 0) {
            let placedAny = false;
            for (let i = 0; i < remaining.length; i++) {
                const sgName = remaining[i];
                const size = subGroupsObj[sgName].length;
                
                // Clusters > maxCardsPerRow take 100% width, so their effective pack size is maxCardsPerRow
                const effectiveSize = Math.min(size, maxCardsPerRow);
                
                // If it fits, or if it's the start of a new line (must place it even if it overflows)
                if (effectiveSize <= currentRowSpace || currentRowSpace === maxCardsPerRow) {
                    packed.push(sgName);
                    remaining.splice(i, 1);
                    
                    currentRowSpace -= effectiveSize;
                    if (currentRowSpace <= 0) {
                        currentRowSpace = maxCardsPerRow;
                    }
                    placedAny = true;
                    break;
                }
            }
            if (!placedAny) currentRowSpace = maxCardsPerRow; // Force new row
        }
        return packed;
    }

    // Debounced Resize Listener
    let resizeTimer;
    window.addEventListener('resize', () => {
        clearTimeout(resizeTimer);
        resizeTimer = setTimeout(() => {
            const currentCols = getCardsPerRow();
            if (currentCols !== lastCardsPerRow) {
                lastCardsPerRow = currentCols;
                renderDirectory(document.querySelector('.hs-search-input') ? document.querySelector('.hs-search-input').value : '');
            }
        }, 150);
    });

    
    // Hide old UI elements
    const hsCampusFilters = document.getElementById('hsCampusFilters');
    if (hsCampusFilters) hsCampusFilters.style.display = 'none';

    // Inject New UI (Premium Toolbar)
    const headerRight = document.getElementById('hs-header-inject');
    if (headerRight) {
        // Build the dropdown HTML dynamically based on the Directory Builder structure
        let depts = [];
        if (window.hsDirectoryStructure && Object.keys(window.hsDirectoryStructure).length > 0) {
            depts = Object.keys(window.hsDirectoryStructure);
        } else {
            depts = [
                'Foundation', 'Primary', 'Secondary', 'PE & Academies',
                'Creative & Performing Arts (CAPA)', 'Languages', 'Student Support',
                'Operations & Administration'
            ];
        }
        
        let dropdownHtml = `<div class="dropdown-item" data-dept="all">View All Departments</div>
                           <div style="height: 1px; background: #e2e8f0; margin: 5px 0;"></div>`;
        depts.forEach(d => {
            dropdownHtml += `<div class="dropdown-item" data-dept="${d}">${d}</div>`;
        });

        headerRight.innerHTML = `
            <div class="hs-header-toolbar">
                <div class="opt-3-tabs">
                    <div class="opt-3-tab-container">
                        <div class="opt-3-tab active" data-campus="all">Leadership Team</div>
                    </div>
                    
                    <div class="opt-3-tab-container">
                        <div class="opt-3-tab" data-campus="Chaofah City">
                            Chaofah City <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="opt-3-dropdown-menu hs-dept-jump" data-target-campus="Chaofah City">
                            ${dropdownHtml}
                        </div>
                    </div>

                    <div class="opt-3-tab-container">
                        <div class="opt-3-tab" data-campus="Cherngtalay">
                            Cherngtalay <i class="fa-solid fa-chevron-down"></i>
                        </div>
                        <div class="opt-3-dropdown-menu hs-dept-jump" data-target-campus="Cherngtalay">
                            ${dropdownHtml}
                        </div>
                    </div>
                </div>
                
                <div class="opt-3-search">
                    <i class="fa-solid fa-search"></i>
                    <input type="text" id="staff-search-new" placeholder="Search the directory..." autocomplete="off">
                    <div id="hs-search-dropdown" class="hs-search-dropdown"></div>
                </div>
            </div>
        `;
    }

    const searchInput = document.getElementById('staff-search-new');
    let staffData = [];
    let currentCampus = 'all';

    try {
        let rawData = window.hsStaffData || [];
        
        // Robust client-side HTML entity decoder
        const decodeHTML = function(html) {
            var txt = document.createElement("textarea");
            txt.innerHTML = html;
            return txt.value;
        };

        // Pre-process all data to fix WordPress smart quote entities (like &#8217;) globally
        rawData.forEach(s => {
            if (s.name) s.name = decodeHTML(s.name).trim();
            if (s.title) s.title = decodeHTML(s.title).trim();
            if (s.reportsTo) s.reportsTo = decodeHTML(s.reportsTo).trim();
        });
        
        staffData = rawData;
    } catch (e) {
        console.error("Failed to load staff data", e);
        if (grid) grid.innerHTML = '<p>Failed to load data.</p>';
        return;
    }

    function createCard(staff) {
        const card = document.createElement('div');
        let cleanName = staff.name || '';

        const hasProfile = staff.blurb && staff.blurb.trim() !== '';
        
        let borderClass = 'hs-card-support';
        if (staff.tier === '1. Executive') borderClass = 'hs-card-exec';
        else if (staff.tier === '2. Management') borderClass = 'hs-card-manager';
        else if (staff.tier === '3. Teaching Staff') borderClass = 'hs-card-teacher';
        
        card.className = `hs-staff-card ${borderClass} ${hasProfile ? 'hs-has-profile' : ''}`;
        
        let hoverHtml = hasProfile ? `
                <div class="hs-hover-overlay">
                    <div class="hs-btn-view">Read Profile</div>
                </div>
        ` : '';

        const campusBadge = (staff.campus) 
            ? `<div style="margin-top: auto;"><span class="hs-card-campus-badge">${staff.campus}</span></div>` 
            : '';

        const emailHtml = staff.email ? `<div class="hs-email-icon" title="Contact"><i class="fa-solid fa-envelope"></i></div>` : '';

        const nameLen = cleanName.length;
        let nameClass = '';
        if (nameLen >= 23) nameClass = 'hs-name-xsm';
        else if (nameLen >= 18) nameClass = 'hs-name-sm';
        const titleClass = (staff.title || '').length > 60 ? 'hs-title-sm' : '';

        card.innerHTML = `
            <div class="hs-staff-img-wrap">
                ${hoverHtml}
                ${emailHtml}
                <img src="${staff.image || 'images/dummy.jpg'}" alt="${staff.name}" loading="lazy">
            </div>
            <div class="hs-staff-body">
                <div class="hs-staff-name-wrap">
                    <h3 class="hs-staff-name ${nameClass}">${cleanName}</h3>
                </div>
                <div class="hs-staff-title-wrap">
                    <div class="hs-badge-title ${titleClass}">${staff.title}</div>
                </div>
                ${campusBadge}
            </div>
        `;

        if (hasProfile) {
            card.addEventListener('click', () => {
                modalImg.src = staff.image || 'images/dummy.jpg';
                modalName.textContent = cleanName;
                modalTitle.textContent = staff.title;
                if (staff.department && staff.department.trim() !== '') {
                    modalDept.textContent = staff.department;
                    modalDept.parentElement.style.display = '';
                } else {
                    modalDept.textContent = '';
                    modalDept.parentElement.style.display = 'none';
                }

                if (staff.campus && staff.campus.trim() !== '') {
                    modalCampus.textContent = staff.campus;
                    modalCampus.parentElement.style.display = '';
                } else {
                    modalCampus.textContent = '';
                    modalCampus.parentElement.style.display = 'none';
                }
                
                modalBlurb.innerHTML = (staff.blurb || '').split('\n\n').map(p => `<p>${p}</p>`).join('');
                modalOverlay.classList.add('active');
            });
        }
        return card;
    }

    function renderDirectory(searchQuery = '') {
        if (!grid) return;
        
        // Prevent scroll-yanking by temporarily freezing the grid's height
        const oldHeight = grid.getBoundingClientRect().height;
        if (oldHeight > 0) {
            grid.style.minHeight = oldHeight + 'px';
        }
        
        grid.innerHTML = '';
        
        // Setup Layout for Leadership vs Campus
        if (currentCampus === 'all') {
            grid.style.display = 'flex';
            grid.style.flexWrap = 'wrap';
            grid.style.gap = '3rem';
            grid.style.justifyContent = 'center';
            grid.style.alignItems = 'flex-start';
            grid.style.padding = '20px 0';
        } else {
            grid.style.display = 'block';
            grid.style.padding = '0';
        }
        
        // 1. FILTERING
        let filtered = staffData.filter(s => {
            // Campus Filter
            if (currentCampus !== 'all') {
                // Drop leadership staff with no department (e.g. global executives), but assign normal staff to "Other" so they don't vanish
                if (!s.department || s.department.trim() === '') {
                    const isLeadership = (s.tier || '').includes('1.') || (s.tier || '').includes('2.') || (s.tier || '').toLowerCase().includes('senior management');
                    if (isLeadership) return false;
                    s.department = 'Other';
                }
                
                if (s.campus !== 'Both Campuses' && s.campus !== currentCampus) {
                    return false;
                }
            }
            // Search Filter
            if (searchQuery) {
                const q = searchQuery.toLowerCase();
                const matchName = (s.name || '').toLowerCase().includes(q);
                const matchTitle = (s.title || '').toLowerCase().includes(q);
                const matchDept = (s.department || '').toLowerCase().includes(q);
                const matchGroup = (s.group || '').toLowerCase().includes(q);
                if (!matchName && !matchTitle && !matchDept && !matchGroup) return false;
            }
            return true;
        });

        // 2. DEPARTMENT GROUPING (Accordions)
        // If Campus === 'all', we ONLY build Leadership Team grids (Executives, Directors, Senior Mgmt)
        // If Campus !== 'all', we build departmental accordions.
        
        const depts = {};
        // Load Directory Structure from Builder
        let structure = window.hsDirectoryStructure || {};
        
        // Fallback if they haven't saved the builder yet
        if (Object.keys(structure).length === 0) {
            structure = {
                'Foundation': [], 'Primary': [], 'Secondary': [], 'PE & Academies': [], 
                'Creative & Performing Arts (CAPA)': [], 'Languages': [], 'Student Support': [], 
                'Operations & Administration': []
            };
        }
        
        // Define accordion order based on the structure keys
        const accordionOrder = Object.keys(structure);
        
        filtered.forEach(s => {
            const tierStr = s.tier || '';
            const isTier123 = tierStr.includes('1.') || tierStr.includes('2.') || tierStr.toLowerCase().includes('senior management');
            
            if (currentCampus === 'all') {
                const isSecretary = tierStr.includes('7.') && (s.title || '').toLowerCase().includes('secretary to');
                if (!isTier123 && !isSecretary) return; // Only Leadership on all tab
                
                // Group by Tier for Leadership page
                let deptKey = 'Senior Management';
                if (tierStr.includes('1.')) deptKey = 'Executives';
                if (tierStr.includes('2.')) deptKey = 'School Directors';
                
                if (isSecretary) {
                    // Find manager's tier so secretary goes to the correct cluster
                    const managerName = (s.reportsTo || '').toLowerCase().trim();
                    const manager = staffData.find(m => (m.name || '').toLowerCase().includes(managerName));
                    if (manager) {
                        const mTier = manager.tier || '';
                        if (mTier.includes('1.')) deptKey = 'Executives';
                        if (mTier.includes('2.')) deptKey = 'School Directors';
                    }
                }
                
                if (!depts[deptKey]) depts[deptKey] = [];
                if (!depts[deptKey].some(existing => existing.name === s.name)) {
                    depts[deptKey].push(s);
                }
                
            } else {
                // Normal Campus Page Accordions
                if (isTier123) {
                    // Execs and Directors don't appear on campus pages unless they specifically have a department assigned
                    if (!s.department || s.department.toLowerCase().includes('leadership team')) {
                        return; 
                    }
                }

                let primaryDepts = s.department ? s.department.split(',').map(d => d.trim()).filter(d => d) : ['Other'];
                
                // Track all departments this person belongs to
                let targetDepts = [...primaryDepts];
                

                targetDepts.forEach(d => {
                    // Normalize Leadership Team cross-pollination
                    if (d === 'Leadership Team') return; // Handled separately or ignored on campus pages if they don't have a specific dept
                    
                    if (!depts[d]) depts[d] = [];
                    // Prevent duplicates if primary and secondary are same
                    if (!depts[d].find(x => x.name === s.name)) {
                        depts[d].push(s);
                    }
                });
            }
        });

        let firstAccordion = true;
        
        // Render order: either Leadership grids or standard Accordion order
        let keysToRender = currentCampus === 'all' ? ['Executives', 'School Directors', 'Senior Management'] : [...accordionOrder];
        
        // Add any unexpected departments to the end
        Object.keys(depts).forEach(k => {
            if (!keysToRender.includes(k)) keysToRender.push(k);
        });

        keysToRender.forEach(deptName => {
            const members = depts[deptName];
            if (!members || members.length === 0) return;

            let contentWrapper;

            if (currentCampus === 'all') {
                // Leadership Page Layout (Uses exact same chips as Campus Page)
                const clusterWrap = document.createElement('div');
                clusterWrap.className = 'hs-cluster';
                
                const chip = document.createElement('div');
                chip.className = 'hs-cluster-chip';
                chip.textContent = deptName;
                
                if (deptName === 'Executives' || deptName === 'School Directors') {
                    chip.style.background = '#fef3c7'; // Amber
                    chip.style.color = '#b45309';
                }
                
                clusterWrap.appendChild(chip);

                const cardsWrap = document.createElement('div');
                cardsWrap.className = 'hs-cluster-cards';
                
                sortAndInjectSecretaries(members).forEach(m => {
                    cardsWrap.appendChild(createCard(m));
                });
                
                clusterWrap.appendChild(cardsWrap);
                grid.appendChild(clusterWrap);
                
            } else {
                // Campus Page Layout (Accordions with Chips)
                const accordion = document.createElement('div');
                // Use Design 4 (High Contrast)
                const safeId = deptName.replace(/[^a-z0-9]/gi, '-').toLowerCase();
                accordion.id = 'acc-' + safeId;
                accordion.className = `accordion-wrapper design-4`;
                
                const header = document.createElement('div');
                header.className = 'acc-header';
                header.innerHTML = `
                    <h2 class="acc-title">${deptName}</h2>
                    <i class="fa-solid fa-chevron-down acc-icon"></i>
                `;
                
                header.addEventListener('click', () => {
                    accordion.classList.toggle('open');
                });

                contentWrapper = document.createElement('div');
                contentWrapper.className = 'accordion-content';
                
                accordion.appendChild(header);
                accordion.appendChild(contentWrapper);
                grid.appendChild(accordion);

                // Group by Display Group (Chip) inside the accordion
                const subGroups = {};
                members.forEach(m => {
                    const sg = m.group || 'General';
                    if (!subGroups[sg]) subGroups[sg] = [];
                    subGroups[sg].push(m);
                });

                // Sort Groups using the Hierarchical Structure for this specific department
                let deptGroupsOrder = structure[deptName] || [];
                
                let sortedGroupNames = Object.keys(subGroups).sort((a, b) => {
                    const indexA = deptGroupsOrder.indexOf(a);
                    const indexB = deptGroupsOrder.indexOf(b);
                    
                    if (indexA !== -1 && indexB !== -1) return indexA - indexB;
                    if (indexA !== -1) return -1;
                    if (indexB !== -1) return 1;
                    
                    return a.localeCompare(b);
                });

                // Tetris Bin-Packing has been removed to respect the exact Master Sequence.
                // Groups will now stack vertically in the precise order specified in the admin menu.

                sortedGroupNames.forEach(sg => {
                    const groupMembers = subGroups[sg];
                    const sortedGroup = sortAndInjectSecretaries(groupMembers);

                    if (sg !== 'General' && sg !== '') {
                        const clusterWrap = document.createElement('div');
                        clusterWrap.className = 'hs-cluster';
                        
                        const chip = document.createElement('div');
                        chip.className = 'hs-cluster-chip';
                        chip.textContent = sg;
                        
                        if (deptGroupsOrder.indexOf(sg) === 0 || sg.toLowerCase().includes('leadership')) {
                            chip.style.background = '#fef3c7'; // Amber
                            chip.style.color = '#b45309';
                        }
                        
                        clusterWrap.appendChild(chip);

                        const cardsWrap = document.createElement('div');
                        cardsWrap.className = 'hs-cluster-cards';
                        sortedGroup.forEach(m => cardsWrap.appendChild(createCard(m)));
                        
                        clusterWrap.appendChild(cardsWrap);
                        contentWrapper.appendChild(clusterWrap);
                    } else {
                        // No chip, just cards
                        const clusterWrap = document.createElement('div');
                        clusterWrap.className = 'hs-cluster hs-cluster-fullrow';
                        const cardsWrap = document.createElement('div');
                        cardsWrap.className = 'hs-cluster-cards';
                        sortedGroup.forEach(m => cardsWrap.appendChild(createCard(m)));
                        clusterWrap.appendChild(cardsWrap);
                        contentWrapper.appendChild(clusterWrap);
                    }
                });
            }

            firstAccordion = false;
        });
        
        // Release the height freeze shortly after the browser paints the new DOM
        setTimeout(() => {
            grid.style.minHeight = '';
        }, 50);
    }

    // Master function for Sorting & Secretary Injection
    function sortAndInjectSecretaries(staffArray) {
        // 1. Sort by Tier, then by Sort Order, then Alphabetical
        let sorted = [...staffArray].sort((a, b) => {
            // Sort by Tier (e.g. "1. Executive" vs "3. Senior Management")
            const tierA = a.tier || '99';
            const tierB = b.tier || '99';
            if (tierA !== tierB) return tierA.localeCompare(tierB);
            
            // Sort by manual Sort Order
            // Use leadershipSort if on the Leadership tab (currentCampus === 'all')
            const sortField = currentCampus === 'all' ? 'leadershipSort' : 'sort';
            const sortA = a[sortField] !== undefined ? parseInt(a[sortField]) : 50;
            const sortB = b[sortField] !== undefined ? parseInt(b[sortField]) : 50;
            if (sortA !== sortB) return sortA - sortB;
            
            // Alphabetical fallback
            return (a.name || '').localeCompare(b.name || '');
        });

        // 2. The Secretary Injection 
        // Find anyone who has a 'Reports To'
        const finalArray = [];
        const secretaries = [];

        // Pull secretaries out of the main flow
        sorted.forEach(s => {
            if (s.reportsTo && s.reportsTo.trim() !== '') {
                secretaries.push(s);
            } else {
                finalArray.push(s);
            }
        });

        // Re-inject secretaries directly after their manager
        secretaries.forEach(sec => {
            const managerName = sec.reportsTo.toLowerCase().trim();
            // Find manager in the final array
            const managerIndex = finalArray.findIndex(m => (m.name || '').toLowerCase().includes(managerName));
            
            if (managerIndex !== -1) {
                // Insert immediately after manager
                finalArray.splice(managerIndex + 1, 0, sec);
            } else {
                // Manager not in this grid, just append to the end of the grid
                finalArray.push(sec);
            }
        });

        return finalArray;
    }

    // Event Listeners
    document.querySelectorAll('.opt-3-tab').forEach(btn => {
        btn.addEventListener('click', (e) => {
            // Prevent click if they are actually clicking the dropdown
            if (e.target.closest('.opt-3-dropdown-menu')) return;
            
            document.querySelectorAll('.opt-3-tab').forEach(b => b.classList.remove('active'));
            
            // The btn might be the <i> tag or the text node if not careful, use closest
            const actualBtn = e.target.closest('.opt-3-tab');
            if(actualBtn) {
                const clickedCampus = actualBtn.getAttribute('data-campus');
                if (currentCampus === clickedCampus) return; // Do not re-render if just opening dropdown
                
                actualBtn.classList.add('active');
                currentCampus = clickedCampus;
                renderDirectory(searchInput ? searchInput.value : '');
                
                // Scroll back to the top of the directory so they don't get lost if the new tab is much shorter
                window.hsTabScrollTimer = setTimeout(() => {
                    const yOffset = -180; 
                    const y = grid.getBoundingClientRect().top + window.pageYOffset + yOffset;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                }, 60);
            }
        });
    });

    // Jump to Department Logic
    document.querySelectorAll('.dropdown-item').forEach(item => {
        item.addEventListener('click', (e) => {
            const targetDept = e.target.getAttribute('data-dept');
            const parentMenu = e.target.closest('.opt-3-dropdown-menu');
            const targetCampus = parentMenu.getAttribute('data-target-campus');

            const tabContainer = e.target.closest('.opt-3-tab-container');
            if (tabContainer) {
                // Permanently lock the menu closed
                tabContainer.classList.add('force-close');
                // The lock will be removed by the global mouseenter/touchstart listeners on the container
            }
            
            // 1. Switch to the correct campus if we aren't already on it
            if (currentCampus !== targetCampus) {
                document.querySelectorAll('.opt-3-tab').forEach(b => b.classList.remove('active'));
                const campusBtn = document.querySelector(`.opt-3-tab[data-campus="${targetCampus}"]`);
                if (campusBtn) campusBtn.classList.add('active');
                
                currentCampus = targetCampus;
                renderDirectory(searchInput ? searchInput.value : '');
            }
            
            // 2. Jump and open the accordion
            if (targetDept !== 'all') {
                const safeId = 'acc-' + targetDept.replace(/[^a-z0-9]/gi, '-').toLowerCase();
                const acc = document.getElementById(safeId);
                if (acc) {
                    // Close others (optional, but good UX)
                    document.querySelectorAll('.accordion-wrapper').forEach(a => a.classList.remove('open'));
                    acc.classList.add('open');
                    
                    // Smooth scroll
                    const yOffset = -220; 
                    const y = acc.getBoundingClientRect().top + window.pageYOffset + yOffset;
                    window.scrollTo({ top: y, behavior: 'smooth' });
                }
            }
        });
    });

    const searchDropdown = document.getElementById('hs-search-dropdown');
    
    if (searchInput) {
        searchInput.addEventListener('input', (e) => {
            const val = e.target.value;
            // Removed renderDirectory(val) from here so it doesn't filter while typing
            
            if (val.trim().length > 0) {
                generateSuggestions(val.trim());
            } else {
                if (searchDropdown) searchDropdown.classList.remove('active');
            }
        });

        // Close dropdown when clicking outside
        document.addEventListener('click', (e) => {
            if (!e.target.closest('.opt-3-search')) {
                if (searchDropdown) searchDropdown.classList.remove('active');
            }
        });
    }

    function generateSuggestions(query) {
        if (!staffData || staffData.length === 0 || !searchDropdown) return;
        
        const q = query.toLowerCase();
        
        // Helper to highlight matching text
        const highlightText = (text) => {
            if (!text) return '';
            const regex = new RegExp(`(${query})`, 'gi');
            return text.replace(regex, '<span class="hs-highlight">$1</span>');
        };

        // Find all matching staff (name or title)
        const matches = staffData.filter(s => {
            const n = (s.name || '').toLowerCase();
            const t = (s.title || '').toLowerCase();
            return n.includes(q) || t.includes(q);
        });

        if (matches.length === 0) {
            searchDropdown.classList.remove('active');
            return;
        }

        // Group by Campus
        const grouped = {
            'Both Campuses': [],
            'Chaofah City': [],
            'Cherngtalay': []
        };

        matches.forEach(s => {
            const campus = (s.campus || 'Unknown').trim();
            if (campus === 'Both' || campus === 'Both Campuses') {
                grouped['Both Campuses'].push(s);
            } else if (campus.includes('Chaofah')) {
                grouped['Chaofah City'].push(s);
            } else if (campus.includes('Cherngtalay')) {
                grouped['Cherngtalay'].push(s);
            } else {
                grouped['Both Campuses'].push(s);
            }
        });

        // Generate HTML
        let html = '';
        const orderedCampuses = ['Both Campuses', 'Chaofah City', 'Cherngtalay'];
        
        orderedCampuses.forEach(campus => {
            if (grouped[campus].length > 0) {
                // Determine if the campus name itself matches the query for highlighting
                const displayCampus = campus.toLowerCase().includes(q) ? highlightText(campus) : campus;
                
                html += `<div class="hs-suggest-category">${displayCampus}</div>`;
                grouped[campus].forEach(staff => {
                    const cleanName = staff.name || '';
                    const displayName = highlightText(cleanName);
                    const displayTitle = highlightText(staff.title);
                    
                    html += `
                        <div class="hs-suggest-item" data-exact-name="${cleanName.replace(/"/g, '&quot;')}">
                            <div class="hs-suggest-name">${displayName}</div>
                            <div class="hs-suggest-title">${displayTitle}</div>
                        </div>
                    `;
                });
            }
        });

        searchDropdown.innerHTML = html;
        searchDropdown.classList.add('active');

        // Add click listeners to items
        const items = searchDropdown.querySelectorAll('.hs-suggest-item');
        items.forEach(item => {
            item.addEventListener('click', () => {
                const exactName = item.getAttribute('data-exact-name');
                searchDropdown.classList.remove('active');
                searchInput.value = ''; // clear search box after selection
                
                // 1. Find the full staff object
                const staffObj = staffData.find(s => {
                    const clean = s.name || '';
                    return clean === exactName;
                });
                
                if (!staffObj) return;

                // 2. Routing Logic
                let targetCampus = 'all'; // Default
                const tier = staffObj.tier || '';
                const camp = (staffObj.campus || '').trim();
                const isManager = tier.includes('1.') || tier.includes('2.') || tier.toLowerCase().includes('senior management') || tier.includes('7.');

                if (isManager) {
                    targetCampus = 'all';
                } else if (camp.includes('Chaofah')) {
                    targetCampus = 'Chaofah City';
                } else if (camp.includes('Cherngtalay')) {
                    targetCampus = 'Cherngtalay';
                } else if (camp === 'Both' || camp === 'Both Campuses') {
                    // Non-manager Both Campuses
                    if (currentCampus === 'Cherngtalay') {
                        targetCampus = 'Cherngtalay';
                    } else {
                        targetCampus = 'Chaofah City';
                    }
                }

                // 3. Switch Tab if necessary
                if (currentCampus !== targetCampus) {
                    const targetTab = document.querySelector(`.opt-3-tab[data-campus="${targetCampus}"]`);
                    if (targetTab) {
                        targetTab.click(); // This triggers renderDirectory synchronously
                    }
                } else {
                    renderDirectory(''); // Reset directory if it was filtered
                }

                // 4. Find Card, Open Accordion, Scroll, Highlight
                // Cancel any pending scroll to the top of the grid from the tab switch
                if (window.hsTabScrollTimer) clearTimeout(window.hsTabScrollTimer);

                setTimeout(() => {
                    const allCards = document.querySelectorAll('.hs-staff-card');
                    let targetCard = null;
                    allCards.forEach(c => {
                        const nameEl = c.querySelector('.hs-staff-name');
                        if (nameEl && nameEl.textContent.trim() === exactName) {
                            targetCard = c;
                        }
                    });

                    if (targetCard) {
                        // Find accordion
                        const acc = targetCard.closest('.accordion-wrapper');
                        if (acc && !acc.classList.contains('open')) {
                            acc.classList.add('open');
                        }
                        
                        // Scroll safely using window.scrollTo to avoid Divi header conflicts
                        const yOffset = -250; // Account for sticky headers
                        const y = targetCard.getBoundingClientRect().top + window.pageYOffset + yOffset;
                        window.scrollTo({ top: y, behavior: 'smooth' });
                        
                        // Highlight
                        targetCard.classList.add('hs-highlight-card');
                        setTimeout(() => {
                            targetCard.classList.remove('hs-highlight-card');
                        }, 5000);
                    }
                }, 100);

            });
        });
    }

    if (modalClose && modalOverlay) {
        modalClose.addEventListener('click', () => {
            modalOverlay.classList.remove('active');
        });
        modalOverlay.addEventListener('click', (e) => {
            if (e.target === modalOverlay) modalOverlay.classList.remove('active');
        });
    }

    // Global listeners to re-enable hover when the user interacts with the tabs again
    document.querySelectorAll('.opt-3-tab-container').forEach(container => {
        container.addEventListener('mouseenter', () => container.classList.remove('force-close'));
        container.addEventListener('touchstart', () => container.classList.remove('force-close'), { passive: true });
    });

// Initial render
    renderDirectory();
};

if (document.readyState === 'loading') {
    document.addEventListener('DOMContentLoaded', initDirectory);
} else {
    initDirectory();
}

// ---------------------------------------------------------------------------
// STAFF SIDEBAR AVATAR BUBBLE SIZING LOGIC
// ---------------------------------------------------------------------------
document.addEventListener('DOMContentLoaded', function() {
    function hsUpdateAvatars() {
        document.querySelectorAll('.hs-staff-widget').forEach(function(widget) {
            var container = widget.querySelector('.hs-staff-avatars');
            if (!container) return;
            
            var width = container.clientWidth;
            if (width === 0) return;
            
            // Ideal width ~68px, Overlap: 16px.
            // N * 52 + 16 = width => N = (width - 16) / 52
            var n = Math.ceil((width - 16) / 52);
            if (n < 2) n = 2; // At least bubble + 1 avatar
            
            var avatars = container.querySelectorAll('img.hs-staff-avatar');
            var moreBubble = container.querySelector('.hs-staff-more');
            var totalStaff = parseInt(moreBubble.getAttribute('data-total') || '100', 10);
            
            var targetAvatars = n - 1; // 1 space is taken by the +X bubble
            var visibleCount = 0;
            
            avatars.forEach(function(img, idx) {
                if (idx < targetAvatars) {
                    img.style.display = '';
                    visibleCount++;
                } else {
                    img.style.display = 'none';
                }
            });
            
            var remaining = totalStaff - visibleCount;
            if (remaining > 0) {
                moreBubble.textContent = '+' + remaining;
            }
        });
    }
    
    hsUpdateAvatars();
    window.addEventListener('resize', hsUpdateAvatars);
});

