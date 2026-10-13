
/**
 * =============================================================================
 * HEADSTART ECA DIRECTORY ENGINE
 * =============================================================================
 * PURPOSE: Manages the ECA (Extra-Curricular Activities) table with search, 
 * category filtering, age filtering, and pagination.
 * LOGIC: Parses DOM rows, removes duplicates, and renders paginated results.
 * Contains null-safe checks (try-catch) to prevent errors when data is missing
 * from the live Divi modules.
 * DEPENDENCIES: DOM table elements
 * =============================================================================
 */
// Wrapped in an IIFE so variables don't leak out and conflict with WordPress
(function() {
    function init(wrapper) {
        const tableBody = wrapper.querySelector('#tableBody');
        if (!tableBody) return;

        // Parse data straight from the HTML DOM
        let allEcas = [];
        const rows = Array.from(tableBody.querySelectorAll('.hs-eca-row'));
        
        // Debugging output to see if rows are found:
        console.log("ECA Directory Engine: Found " + rows.length + " rows in the DOM at initialization.");
        
        rows.forEach((tr, index) => {
            try {
                const name = tr.querySelector('.col-name')?.textContent.trim() || 'Unknown Activity';
                
                // Fallback for missing category tags
                let category = 'Club';
                const tagEl = tr.querySelector('.col-category .tag');
                if (tagEl) {
                    category = tagEl.textContent.trim();
                } else if (tr.querySelector('.col-category')) {
                    category = tr.querySelector('.col-category').textContent.trim() || 'Club';
                }

                const ages = tr.querySelector('.col-ages')?.textContent.trim() || 'All Ages';
                const description = tr.querySelector('.desc-clamped')?.textContent.trim() || '';
                
                allEcas.push({ name, category, ages, description, originalId: index });
            } catch (e) {
                console.warn('ECA Directory: Skipping malformed row at index', index, e);
            }
        });

        // Remove duplicates
        const seen = new Set();
        allEcas = allEcas.filter(item => {
            const duplicate = seen.has(item.name);
            seen.add(item.name);
            return !duplicate;
        });

        let filteredEcas = [];
        let currentPage = 1;
        let itemsPerPage = window.innerWidth <= 768 ? 3 : 4;

        window.addEventListener('resize', () => {
            const newItemsPerPage = window.innerWidth <= 768 ? 3 : 4;
            if (newItemsPerPage !== itemsPerPage) {
                itemsPerPage = newItemsPerPage;
                currentPage = 1; 
                if (allEcas.length > 0) renderTable();
            }
        });

        let sortColumn = 'name';
        let sortDirection = 'asc';

        const searchInput = wrapper.querySelector('#search');
        const categoryFilter = wrapper.querySelector('#categoryFilter');
        const ageFilter = wrapper.querySelector('#ageFilter');
        const paginationContainer = wrapper.querySelector('#paginationContainer');

        function getTagClass(category) {
            if(category === 'Fee Paid') return 'tag-Fee';
            if(category === 'VAPP') return 'tag-VAPP';
            if(category === 'Sport') return 'tag-Sport';
            return 'tag-Club';
        }

        function matchesAge(ecaAges, selectedAge) {
            if (selectedAge === 'All') return true;
            const text = ecaAges.toLowerCase();
            const val = selectedAge.toLowerCase();
            
            if (val === 'early years' && (text.includes('early year') || text.includes('preschool'))) return true;
            if (text.includes(val)) return true;

            const num = parseInt(selectedAge);
            if (!isNaN(num)) {
                const matches = text.match(/(\d+)\s*(to|&|-|and)\s*(\d+)/g);
                if (matches) {
                    for (const m of matches) {
                        const nums = m.match(/\d+/g).map(Number);
                        if (nums.length === 2 && num >= Math.min(...nums) && num <= Math.max(...nums)) {
                            return true;
                        }
                    }
                }
                const exactMatch = new RegExp(`\\b${num}\\b`);
                if (exactMatch.test(text)) return true;
                if (num >= 1 && num <= 6 && text.includes('primary')) return true;
                if (num >= 7 && num <= 13 && text.includes('secondary')) return true;
            }

            return false;
        }

        function sortData(data) {
            return data.sort((a, b) => {
                let valA = a[sortColumn] ? a[sortColumn].toString().toLowerCase() : '';
                let valB = b[sortColumn] ? b[sortColumn].toString().toLowerCase() : '';

                if (valA < valB) return sortDirection === 'asc' ? -1 : 1;
                if (valA > valB) return sortDirection === 'asc' ? 1 : -1;
                return 0;
            });
        }

        function updateSortIcons() {
            ['name', 'category', 'ages', 'description'].forEach(col => {
                const el = wrapper.querySelector(`#sort-${col}`);
                if (el) {
                    if (col === sortColumn) {
                        if(sortDirection === 'asc') {
                            el.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m18 15-6-6-6 6"/></svg>';
                        } else {
                            el.innerHTML = '<svg width="12" height="12" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="3" stroke-linecap="round" stroke-linejoin="round"><path d="m6 9 6 6 6-6"/></svg>';
                        }
                    } else {
                        el.innerHTML = '';
                    }
                }
            });
        }

        // Attach to window so HTML inline onclick handlers can reach it
        window.handleSort = function(col) {
            if (sortColumn === col) {
                sortDirection = sortDirection === 'asc' ? 'desc' : 'asc';
            } else {
                sortColumn = col;
                sortDirection = 'asc';
            }
            currentPage = 1;
            renderTable();
        }

        window.toggleReadMore = function(index, btn) {
            const descDiv = wrapper.querySelector(`#desc-${index}`);
            if (descDiv && descDiv.classList.contains('desc-clamped')) {
                descDiv.classList.remove('desc-clamped');
                btn.textContent = 'Read less';
            } else if (descDiv) {
                descDiv.classList.add('desc-clamped');
                btn.textContent = 'Read more';
            }
        }

        function renderPagination(totalPages) {
            if (!paginationContainer) return;

            if (totalPages <= 1) {
                paginationContainer.innerHTML = '';
                return;
            }

            let html = '';
            if (currentPage > 1) {
                html += `<button class="page-btn" data-goto-page="${currentPage - 1}">Prev</button>`;
            }

            for (let i = 1; i <= totalPages; i++) {
                if (totalPages > 7) {
                    if (i === 1 || i === totalPages || (i >= currentPage - 1 && i <= currentPage + 1)) {
                        html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-goto-page="${i}">${i}</button>`;
                    } else if (i === currentPage - 2 || i === currentPage + 2) {
                        html += `<span class="page-dots">...</span>`;
                    }
                } else {
                    html += `<button class="page-btn ${i === currentPage ? 'active' : ''}" data-goto-page="${i}">${i}</button>`;
                }
            }

            if (currentPage < totalPages) {
                html += `<button class="page-btn" data-goto-page="${currentPage + 1}">Next</button>`;
            }

            paginationContainer.innerHTML = html;
        }

        window.goToPage = function(page) {
            currentPage = page;
            renderTable();
            const top = wrapper.getBoundingClientRect().top + window.scrollY - 100;
            window.scrollTo({ top: top, behavior: 'smooth' });
        }

        function renderTable() {
            if (!searchInput || !categoryFilter || !ageFilter || !tableBody) return;

            const term = searchInput.value.toLowerCase();
            const cat = categoryFilter.value;
            const age = ageFilter.value;

            filteredEcas = allEcas.filter(eca => {
                const matchSearch = eca.name.toLowerCase().includes(term) || eca.description.toLowerCase().includes(term);
                const matchCat = (cat === 'All' || eca.category === cat);
                const matchAge = matchesAge(eca.ages, age);
                return matchSearch && matchCat && matchAge;
            });

            filteredEcas = sortData(filteredEcas);
            updateSortIcons();

            const totalPages = Math.ceil(filteredEcas.length / itemsPerPage);
            if (currentPage > totalPages) currentPage = totalPages || 1;

            const startIdx = (currentPage - 1) * itemsPerPage;
            const endIdx = startIdx + itemsPerPage;
            const paginated = filteredEcas.slice(startIdx, endIdx);

            if (paginated.length === 0) {
                tableBody.innerHTML = `<tr><td colspan="4" class="no-results" style="text-align: center; padding: 2rem;">No activities found matching your criteria.</td></tr>`;
                if (paginationContainer) paginationContainer.innerHTML = '';
                return;
            }

            tableBody.innerHTML = paginated.map((eca) => {
                let descHTML = `
                    <div class="desc-wrapper">
                        <div id="desc-${eca.originalId}" class="desc-clamped">${eca.description}</div>
                        <button id="btn-${eca.originalId}" class="read-more-btn" style="display: none;" data-readmore-id="${eca.originalId}">Read more</button>
                    </div>
                `;

                return `
                <tr>
                    <td class="col-name">${eca.name}</td>
                    <td class="col-category">
                        <span class="tag ${getTagClass(eca.category)}">${eca.category}</span>
                    </td>
                    <td class="col-ages">${eca.ages}</td>
                    <td class="col-desc">${descHTML}</td>
                </tr>
                `;
            }).join('');

            // After rendering, check for overflow to selectively show the button
            requestAnimationFrame(() => {
                paginated.forEach((eca) => {
                    const descDiv = wrapper.querySelector(`#desc-${eca.originalId}`);
                    const btn = wrapper.querySelector(`#btn-${eca.originalId}`);
                    if (descDiv && btn) {
                        if (descDiv.scrollHeight > descDiv.clientHeight) {
                            btn.style.display = 'inline-block';
                        } else {
                            descDiv.classList.remove('desc-clamped');
                        }
                    }
                });
            });

            renderPagination(totalPages);
        }

        if (searchInput) searchInput.addEventListener('input', () => { currentPage = 1; renderTable(); });
        if (categoryFilter) categoryFilter.addEventListener('change', () => { currentPage = 1; renderTable(); });
        if (ageFilter) ageFilter.addEventListener('change', () => { currentPage = 1; renderTable(); });

        if (paginationContainer) {
            paginationContainer.addEventListener('click', (e) => {
                const btn = e.target.closest('.page-btn');
                if (!btn) return;
                const page = parseInt(btn.getAttribute('data-goto-page'));
                if (page && page !== currentPage) {
                    window.goToPage(page);
                }
            });
        }

        if (tableBody) {
            tableBody.addEventListener('click', (e) => {
                const btn = e.target.closest('.read-more-btn');
                if (!btn) return;
                const id = btn.getAttribute('data-readmore-id');
                if (id) {
                    window.toggleReadMore(id, btn);
                }
            });
        }

        // Initial Render 
        renderTable();
    }

    // DIVI/WORDPRESS SAFETY FALLBACK
    // Wait for the HTML container to exist in the DOM before running the script
    let attempts = 0;
    function checkDOM() {
        const wrapper = document.getElementById('hs-eca-directory');
        const tableBody = wrapper ? wrapper.querySelector('#tableBody') : null;
        
        // Wait not just for wrapper, but also until the tableBody has at least one row, 
        // to handle async rendering in Divi.
        if (wrapper && tableBody && tableBody.querySelectorAll('.hs-eca-row').length > 0) {
            init(wrapper);
        } else if (attempts < 50) { // Try for up to 5 seconds
            attempts++;
            setTimeout(checkDOM, 100);
        } else {
            console.warn("ECA Directory script: Wrapper found but no rows were present after 5 seconds, or wrapper not found.");
            // Try init anyway just in case the table is legitimately empty
            if (wrapper) init(wrapper);
        }
    }
    
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', checkDOM);
    } else {
        checkDOM();
    }

})();
