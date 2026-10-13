
/**
 * =============================================================================
 * HEADSTART GALLERY MOSAIC ENGINE & LIGHTBOX
 * =============================================================================
 * PURPOSE: Hijacks native Divi gallery modules to generate high-performance, mathematically randomized "bento-style" mosaic grids.
 * LOGIC: Manages deep-linking, asynchronous image pre-loading, scrollbar-jump prevention, and Magnific Popup integration.
 * DEPENDENCIES: Magnific Popup, Divi Gallery
 * =============================================================================
 */
document.addEventListener('DOMContentLoaded', function() {
    
    // Original Execution Guard exactly as provided
    if (HeadStartUtils.isDiviBuilder()) return;

    /* --- DOM PREPARATION & MODAL STATE --- */
    
    let modalPopup = document.getElementById('school-gallery-popup');
    
    if (!modalPopup) {
        document.body.insertAdjacentHTML('beforeend', '<div id="school-gallery-popup"></div>');
        modalPopup = document.getElementById('school-gallery-popup');
    }
    
    if (!modalPopup.querySelector('.hs-modal-close')) {
        modalPopup.innerHTML = `
            <div class="hs-modal-close hs-shared-close">
                <span class="hs-modal-close-text">CLOSE</span>
                <span class="hs-modal-close-icon">&times;</span>
            </div>
            <div class="hs-mosaic-grid"></div>
        `;
    }

    if (modalPopup.parentNode !== document.body) {
        document.body.appendChild(modalPopup);
    }

    const closeBtn = modalPopup.querySelector('.hs-modal-close');

    const closeModal = () => {
        if(modalPopup) {
            modalPopup.style.transition = 'opacity 0.25s ease';
            modalPopup.style.opacity = '0';
            
            setTimeout(() => {
                modalPopup.style.setProperty('display', 'none', 'important');
                modalPopup.style.transition = '';
                modalPopup.style.opacity = '1'; 
                
                document.body.style.overflow = ''; 
                document.body.style.paddingRight = '';
                const mainHeader = document.getElementById('main-header');
                if (mainHeader) mainHeader.style.paddingRight = '';
            }, 250);
        } else {
            document.body.style.overflow = ''; 
        }
    };

    closeBtn?.addEventListener('click', closeModal);
    
    modalPopup?.addEventListener('click', (e) => {
        const grid = modalPopup.querySelector('.hs-mosaic-grid');
        if (e.target === modalPopup || e.target === grid) closeModal();
    });
    
    document.addEventListener('keydown', (e) => {
        if (e.key === 'Escape' && modalPopup?.style.display === 'block') closeModal();
    });


    /* --- GRID ENGINES & CONFIGURATION --- */
    
    const FIXED_LANDSCAPE_CONFIGS = {
        1: { cols: 1, items: [{ span: '1x1' }] },
        2: { cols: 2, items: [{ span: '1x1' }, { span: '1x1' }] },
        3: { cols: 3, items: [{ span: '2x2' }, { span: '1x1' }, { span: '1x1' }] },
        4: { cols: 2, items: [{ span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        5: { cols: 4, items: [{ span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        6: { cols: 3, items: [{ span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        7: { cols: 4, items: [{ span: '2x2' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        8: { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }] },
        9: { cols: 5, items: [{ span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        10: { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }] },
        11: { cols: 4, items: [{ span: '3x3' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        12: { cols: 4, items: [{ span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '3x3' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }] },
        13: { cols: 5, items: [{ span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '3x3' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        14: { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }] },
        15: { cols: 4, items: [{ span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '3x3' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        16: { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }] },
        17: { cols: 5, items: [{ span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '3x3' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        18: { cols: 5, items: [{ span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '3x3' }, { span: '2x2' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        19: { cols: 6, items: [{ span: '2x2' }, { span: '1x1' }, { span: '3x3' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] },
        20: { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '2x2' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '3x3' }, { span: '2x2' }, { span: '1x1' }, { span: '2x2' }, { span: '1x1' }, { span: '1x1' }, { span: '1x1' }] }
    };

    function generateFlushGrid(L, P, targetCols, isMaster = false) {
        const totalItems = L + P;

        // Area = (L - l - m) + l*9 + m*4 + P*3 = L + 8l + 3m + 3P
        const validCombos = [];
        
        // Only allow 3x3 blocks in larger grids to prevent them from dominating the layout
        const maxL = totalItems > 12 ? Math.floor(L / 8) : 0;
        
        for (let l = 0; l <= maxL; l++) {
            for (let m = 0; m <= L - l; m++) {
                let s = L - l - m;
                if ((s + l * 9 + m * 4 + P * 3) % targetCols === 0) {
                    validCombos.push({ l, m, s });
                }
            }
        }

        // We want a good mix of sizes. Aim for roughly 1 big piece (3x3 or 2x2) per 6-8 total items.
        const targetBigPieces = Math.max(1, Math.floor(totalItems / 7));

        validCombos.sort((a, b) => {
            // Favor having at least one 3x3 if mathematically possible
            const hasL = a.l > 0 ? 0 : 1;
            const hasLb = b.l > 0 ? 0 : 1;
            
            // Try to match the target number of big pieces
            let scoreA = Math.abs((a.l + a.m) - targetBigPieces);
            let scoreB = Math.abs((b.l + b.m) - targetBigPieces);
            
            // MASSIVE PENALTY for 0 heroes (forces it to only pick all-small grids if absolutely no other option exists)
            if (a.l + a.m === 0) scoreA += 100;
            if (b.l + b.m === 0) scoreB += 100;

            if (hasL !== hasLb) return hasL - hasLb;
            return scoreA - scoreB;
        });

        // Try the best combos
        for (const combo of validCombos) {
            const area = combo.s + combo.l * 9 + combo.m * 4 + P * 3;
            const rows = area / targetCols;
            if (!Number.isInteger(rows) || rows < 1) continue;

            // 3x3 blocks are harder to pack, so allow more attempts to find a perfect fit
            for (let attempt = 0; attempt < 2000; attempt++) { 
                const result = attemptGridPlacement(combo.l, combo.m, P, combo.s, rows, targetCols, attempt);
                if (result) return result;
            }
        }

        if (isMaster) {
            // Dense Masonry Fallback for the modal popup: pure variation, CSS Grid Dense packing
            const masonryItems = [];
            for (let i = 0; i < P; i++) masonryItems.push({ span: '1x3', type: 'P' });
            
            let numHeroes = Math.max(0, Math.floor(L / 5));
            for (let i = 0; i < numHeroes; i++) masonryItems.push({ span: '2x2', type: 'HERO_M' });
            for (let i = 0; i < L - numHeroes; i++) masonryItems.push({ span: '1x1', type: 'S' });
            
            for (let i = masonryItems.length - 1; i > 0; i--) {
                const j = Math.floor(Math.random() * (i + 1));
                [masonryItems[i], masonryItems[j]] = [masonryItems[j], masonryItems[i]];
            }
            return { cols: targetCols, items: masonryItems, isFallback: true };
        }
        
        // Hybrid Nuclear Fallback for inline pages: force 1x1 explicit coordinates for flat desktop bottom
        const fallbackRows = Math.ceil(totalItems / targetCols);
        const items = [];
        
        const fallbackSpans = [];
        for (let i = 0; i < P; i++) fallbackSpans.push({ span: '1x3', type: 'P' });
        let numHeroes = Math.max(0, Math.floor(L / 5));
        for (let i = 0; i < numHeroes; i++) fallbackSpans.push({ span: '2x2', type: 'HERO_M' });
        for (let i = 0; i < L - numHeroes; i++) fallbackSpans.push({ span: '1x1', type: 'S' });
        
        for (let i = fallbackSpans.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [fallbackSpans[i], fallbackSpans[j]] = [fallbackSpans[j], fallbackSpans[i]];
        }
        
        let idx = 0;
        for (let r = 0; r < fallbackRows; r++) {
            for (let c = 0; c < targetCols; c++) {
                if (idx < totalItems) {
                    const itemSpan = fallbackSpans[idx];
                    items.push({ 
                        span: itemSpan.span, 
                        type: itemSpan.type, 
                        gridRow: r + 1, 
                        gridCol: c + 1, 
                        w: 1, 
                        h: 1 // FORCE height to 1 so desktop perfectly flush-packs it without stretching
                    });
                    idx++;
                }
            }
        }
        
        return { cols: targetCols, items, isFallback: true };
    }

    /**
     * DIRECT GRID BUILDER FOR PAGINATED GALLERIES (5 COLUMNS)
     * ========================================================
     * Instead of simulating CSS grid placement, this builds the grid
     * as a 2D array. Big pieces (heroes, portraits) are placed at
     * random valid positions, then ALL remaining cells are filled
     * with 1x1 smalls. Gaps are impossible because every cell is filled.
     *
     * Each item gets exact (gridRow, gridCol) coordinates which the
     * renderer applies as inline styles — zero reliance on CSS auto-placement.
     *
     * Spans:
     *   2x2  (hero)  – landscape 16:9, 2 cols × 2 rows (4 cells)
     *   1x1  (small)  – landscape 16:9, 1 col  × 1 row  (1 cell)
     *   1x3  (port)  – portrait  9:16, 1 col  × 3 rows (3 cells)
     */

    function attemptGridPlacement(lCount, mCount, pCount, sCount, rows, cols, attemptIndex) {
        const grid = Array.from({ length: rows }, () => new Array(cols).fill(false));
        const items = [];

        // Collect big pieces and shuffle for variety
        const pieces = [];
        for (let i = 0; i < pCount; i++) pieces.push({ span: '1x3', w: 1, h: 3, type: 'P' });
        for (let i = 0; i < lCount; i++) pieces.push({ span: '3x3', w: 3, h: 3, type: 'HERO_L' });
        for (let i = 0; i < mCount; i++) pieces.push({ span: '2x2', w: 2, h: 2, type: 'HERO_M' });
        
        for (let i = pieces.length - 1; i > 0; i--) {
            const j = Math.floor(Math.random() * (i + 1));
            [pieces[i], pieces[j]] = [pieces[j], pieces[i]];
        }

        // Place each big piece
        let placedCount = 0;
        for (const piece of pieces) {
            const validPos = [];
            for (let r = 0; r <= rows - piece.h; r++) {
                for (let c = 0; c <= cols - piece.w; c++) {
                    let fits = true;
                    for (let dr = 0; dr < piece.h && fits; dr++) {
                        for (let dc = 0; dc < piece.w && fits; dc++) {
                            if (grid[r + dr][c + dc]) fits = false;
                        }
                    }
                    if (fits) validPos.push({ r, c });
                }
            }
            if (validPos.length === 0) return null; // can't fit, try different arrangement

            let pos;
            if (attemptIndex < 100) {
                // Distributed Random Placement: force vertical spread
                const idealRow = (placedCount / pieces.length) * (rows - piece.h);
                validPos.forEach(p => {
                    p.score = Math.abs(p.r - idealRow) + (Math.random() * (rows / 3)); 
                });
                validPos.sort((a, b) => a.score - b.score);
                const poolSize = Math.min(3, validPos.length);
                pos = validPos[Math.floor(Math.random() * poolSize)];
            } else {
                // Pure Random Placement: maximum flexibility
                pos = validPos[Math.floor(Math.random() * validPos.length)];
            }
            
            for (let dr = 0; dr < piece.h; dr++) {
                for (let dc = 0; dc < piece.w; dc++) {
                    grid[pos.r + dr][pos.c + dc] = true;
                }
            }
            items.push({ span: piece.span, type: piece.type, gridRow: pos.r + 1, gridCol: pos.c + 1 });
            placedCount++;
        }

        // Fill ALL remaining cells with 1x1 smalls — no gaps possible
        let smallsPlaced = 0;
        for (let r = 0; r < rows; r++) {
            for (let c = 0; c < cols; c++) {
                if (!grid[r][c]) {
                    items.push({ span: '1x1', type: 'S', gridRow: r + 1, gridCol: c + 1 });
                    smallsPlaced++;
                }
            }
        }

        // Verify: smalls used must match expected count
        if (smallsPlaced !== sCount) return null;

        // Sort items by row then column so DOM order matches visual order naturally
        items.sort((a, b) => {
            if (a.gridRow !== b.gridRow) return a.gridRow - b.gridRow;
            return a.gridCol - b.gridCol;
        });

        return { cols, items };
    }



    /* --- RENDER HELPER --- */

    function renderMosaicGrid(config, targetGridElement) {
        targetGridElement.innerHTML = '';
        targetGridElement.style.setProperty('--cols', config.cols);
        const fragment = document.createDocumentFragment();

        config.items.forEach(mappedItem => {
            const data = mappedItem.data;
            const aTag = document.createElement('a');
            aTag.href = data.highResSrc;
            aTag.className = `hs-span-${mappedItem.span}`; 
            aTag.setAttribute('data-title', data.titleText);
            aTag.setAttribute('data-caption', data.captionText);

            // If the simulator computed explicit positions, apply them.
            // This guarantees the grid matches the simulator exactly,
            // regardless of how the browser's dense packing behaves.
            if (mappedItem.gridRow !== undefined && mappedItem.gridCol !== undefined) {
                const spanMap = { '1x1': [1,1], '2x2': [2,2], '1x3': [1,3], '3x3': [3,3] };
                const [sw, sh] = spanMap[mappedItem.span] || [1,1];
                
                const finalW = mappedItem.w || sw;
                const finalH = mappedItem.h || sh;
                
                aTag.style.setProperty('--hs-grid-row', mappedItem.gridRow + ' / span ' + finalH);
                aTag.style.setProperty('--hs-grid-col', mappedItem.gridCol + ' / span ' + finalW);
            }

            const newImg = document.createElement('img');
            newImg.setAttribute('loading', 'lazy'); 
            newImg.setAttribute('decoding', 'async');
            if (data.srcset) {
                // Real sizes from hs-gallery-image-data.php: the browser picks one per tile width,
                // so a 3x3 tile gets ~1280px and a 1x1 ~480px instead of the full original.
                const tileCols = mappedItem.w || parseInt(mappedItem.span, 10) || 1;
                newImg.sizes = Math.ceil(100 * tileCols / config.cols) + 'vw';
                newImg.srcset = data.srcset;
                newImg.src = data.thumbImg.src;
            } else if (mappedItem.span === '1x1') {
                newImg.src = data.thumbImg.src; 
                const srcset = data.thumbImg.getAttribute('srcset');
                const sizes = data.thumbImg.getAttribute('sizes');
                if (srcset) newImg.setAttribute('srcset', srcset);
                if (sizes) newImg.setAttribute('sizes', sizes);
            } else {
                newImg.src = data.highResSrc; 
            }
            newImg.alt = data.titleText || 'Gallery Image';

            aTag.appendChild(newImg);
            fragment.appendChild(aTag);
        });

        targetGridElement.appendChild(fragment);
    }

    /* --- PRE-COMPILATION ENGINE --- */
    
    async function preCompileGallery(container) {
        if (container.hsGridConfig || container.dataset.hsCompiling) return; 
        container.dataset.hsCompiling = 'true';

        // If the container IS the gallery module, use it. Otherwise, look inside it.
        const nativeGallery = container.classList.contains('et_pb_gallery') ? container : container.querySelector('.et_pb_gallery');
        if (!nativeGallery) return;

        const galleryItems = Array.from(nativeGallery.querySelectorAll('.et_pb_gallery_item'));
        if (galleryItems.length === 0) return;

        const article = container.closest('.hs-weekly-article, .weekly-card, .hs-gallery-grid');
        const standaloneImages = article ? Array.from(article.querySelectorAll('.et_pb_image img')) : [];
        
        const allItems = [
            ...standaloneImages.map(img => ({ type: 'standalone', el: img })),
            ...galleryItems.map(item => ({ type: 'gallery', el: item }))
        ];

        const measuredAllItems = await Promise.all(allItems.map(obj => {
            return new Promise(resolve => {
                let highResSrc, titleText, captionText, thumbImg;

                if (obj.type === 'standalone') {
                    highResSrc = obj.el.getAttribute('src');
                    thumbImg = obj.el; // Use the image itself as the thumbnail
                    titleText = obj.el.getAttribute('alt') || '';
                    captionText = '';
                } else {
                    const link = obj.el.querySelector('.et_pb_gallery_image a');
                    if (!link) return resolve(null);
                    highResSrc = link.getAttribute('href');
                    thumbImg = link.querySelector('img');
                    const titleTag = obj.el.querySelector('.et_pb_gallery_title');
                    const captionTag = obj.el.querySelector('.et_pb_gallery_caption, p');
                    titleText = titleTag ? titleTag.textContent.trim() : '';
                    captionText = captionTag && captionTag !== titleTag ? captionTag.textContent.trim() : '';
                }

                if (!highResSrc) return resolve(null);
                
                const data = {
                    type: obj.type,
                    highResSrc,
                    thumbImg,
                    titleText,
                    captionText,
                    isPortrait: false
                };

                // Gallery images carry their real size (hs-gallery-image-data.php), so there is
                // nothing to download. Only images without it fall back to loading the original.
                if (thumbImg && thumbImg.dataset.hsW) {
                    data.isPortrait = +thumbImg.dataset.hsH > +thumbImg.dataset.hsW;
                    data.srcset = thumbImg.dataset.hsSrcset || '';
                    return resolve(data);
                }

                // Standalone Divi images carry width/height attributes; loading the original
                // here would defeat loading="lazy" and pull every full-size file at once.
                const attrW = thumbImg && +thumbImg.getAttribute('width');
                const attrH = thumbImg && +thumbImg.getAttribute('height');
                if (attrW && attrH) {
                    data.isPortrait = attrH > attrW;
                    return resolve(data);
                }

                const tempImg = new Image();
                tempImg.onload = () => {
                    data.isPortrait = tempImg.naturalHeight > tempImg.naturalWidth;
                    resolve(data);
                };
                tempImg.onerror = () => resolve(data);
                tempImg.src = highResSrc;
            });
        }));

        const validAllItems = measuredAllItems.filter(Boolean);
        const validGalleryItems = validAllItems.filter(d => d.type === 'gallery');

        let landscapes = validAllItems.filter(d => !d.isPortrait);
        let portraits = validAllItems.filter(d => d.isPortrait);

        /* 1. COMPILE MASTER CONFIG (FOR MODAL - 100% IDENTICAL TO ORIGINAL) */
        let masterConfig;
        if (portraits.length === 0 && validAllItems.length <= 20 && FIXED_LANDSCAPE_CONFIGS[validAllItems.length]) {
            masterConfig = JSON.parse(JSON.stringify(FIXED_LANDSCAPE_CONFIGS[validAllItems.length]));
        } else {
            const targetCols = validAllItems.length < 8 ? 4 : 5;
            masterConfig = generateFlushGrid(landscapes.length, portraits.length, targetCols, true);
        }

        let mappedMasterItems = masterConfig.items.map(item => ({ ...item, data: null }));
        const assignSpanMaster = (spanTarget, sourceArray) => {
            mappedMasterItems.forEach(item => {
                if (item.span === spanTarget && sourceArray.length > 0) item.data = sourceArray.shift();
            });
        };
        
        let landCopy = [...landscapes];
        let portCopy = [...portraits];
        assignSpanMaster('3x3', landCopy);
        assignSpanMaster('2x2', landCopy);
        assignSpanMaster('1x3', portCopy);
        assignSpanMaster('1x1', landCopy); 
        assignSpanMaster('1x1', portCopy); 

        container.hsGridConfig = {
            cols: masterConfig.cols,
            items: mappedMasterItems.filter(i => i.data !== null)
        };

        /* 2. COMPILE PAGINATED INLINE OR SUMMARY CONFIG */
        if (container.classList.contains('hs-summary-mosaic')) {
            // Pick up to 13 items from GALLERY ITEMS ONLY, heavily prioritizing Landscapes for the Bento preview
            const summaryLandscapes = validGalleryItems.filter(d => !d.isPortrait);
            const summaryPortraits = validGalleryItems.filter(d => d.isPortrait);
            
            // Prioritize landscapes first, then fill the remainder with portraits if we don't have enough landscapes
            const summaryItems = [...summaryLandscapes, ...summaryPortraits].slice(0, 13);
            
            let summaryConfig;
            
            // Mathematical Masterpiece: Every layout here is 100% flush (zero empty cells).
            // Uses dense packing to gracefully arrange 3x3, 2x2, and 1x1 cells.
            const len = summaryItems.length;
            if (len >= 13) {
                summaryConfig = { cols: 6, items: [{ span: '3x3' }, { span: '2x2' }, ...Array(11).fill({ span: '1x1' })] };
            } else if (len === 12) {
                summaryConfig = { cols: 6, items: [{ span: '2x2' }, { span: '2x2' }, { span: '2x2' }, { span: '2x2' }, ...Array(8).fill({ span: '1x1' })] };
            } else if (len === 11) {
                summaryConfig = { cols: 5, items: [{ span: '2x2' }, { span: '2x2' }, { span: '2x2' }, ...Array(8).fill({ span: '1x1' })] };
            } else if (len === 10) {
                summaryConfig = { cols: 6, items: [{ span: '3x3' }, { span: '2x2' }, { span: '2x2' }, ...Array(7).fill({ span: '1x1' })] };
            } else if (len === 9) {
                summaryConfig = { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, ...Array(7).fill({ span: '1x1' })] };
            } else if (len === 8) {
                summaryConfig = { cols: 4, items: [{ span: '3x3' }, ...Array(7).fill({ span: '1x1' })] };
            } else if (len === 7) {
                summaryConfig = { cols: 6, items: [{ span: '3x3' }, { span: '2x2' }, ...Array(5).fill({ span: '1x1' })] };
            } else if (len === 6) {
                summaryConfig = { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, { span: '2x2' }, ...Array(3).fill({ span: '1x1' })] };
            } else if (len === 5) {
                summaryConfig = { cols: 4, items: [{ span: '2x2' }, ...Array(4).fill({ span: '1x1' })] };
            } else if (len === 4) {
                summaryConfig = { cols: 5, items: [{ span: '3x3' }, { span: '2x2' }, ...Array(2).fill({ span: '1x1' })] };
            } else if (len === 3) {
                summaryConfig = { cols: 3, items: [{ span: '2x2' }, ...Array(2).fill({ span: '1x1' })] };
            } else if (len === 2) {
                summaryConfig = { cols: 2, items: [{ span: '1x1' }, { span: '1x1' }] };
            } else {
                summaryConfig = { cols: 1, items: [{ span: '1x1' }] };
            }
            
            let mappedSummary = summaryConfig.items.map(item => ({ ...item, data: null }));
            const assignSpan = (spanTarget, sourceArray) => {
                mappedSummary.forEach(item => {
                    if (item.span === spanTarget && sourceArray.length > 0) item.data = sourceArray.shift();
                });
            };
            
            let sLand = summaryItems.filter(d => !d.isPortrait);
            let sPort = summaryItems.filter(d => d.isPortrait);
            // By only matching 3x3, 2x2, and 1x1, we FORCE portraits to crop into 16:9 landscape boxes
            assignSpan('3x3', sLand);
            assignSpan('3x3', sPort); // Fallback if no landscapes exist
            assignSpan('2x2', sLand);
            assignSpan('2x2', sPort); // Fallback if no landscapes exist
            assignSpan('1x1', sLand); 
            assignSpan('1x1', sPort);
            
            const wrapper = document.createElement('div');
            // We give it the trigger classes so clicking it automatically opens the master modal
            wrapper.className = 'hs-summary-mosaic-wrapper hs-gallery-trigger hs-gallery-trigger-split';
            
            const grid = document.createElement('div');
            grid.className = 'hs-mosaic-grid';
            renderMosaicGrid({ cols: summaryConfig.cols, items: mappedSummary.filter(i => i.data !== null) }, grid);
            
            // Fix: Append the grid to the wrapper!
            wrapper.appendChild(grid);
            
            if (validGalleryItems.length > 13) {
                const lastCell = grid.lastElementChild;
                if (lastCell) {
                    lastCell.style.position = 'relative';
                    lastCell.classList.add('hs-has-overlay');
                    const overlay = document.createElement('div');
                    overlay.className = 'hs-summary-overlay';
                    overlay.innerHTML = `<span>+${validGalleryItems.length - 13}</span>`;
                    lastCell.appendChild(overlay);
                }
            }
            
            container.appendChild(wrapper);
            container.classList.add('hs-mosaic-loaded');
            
        } else if (container.classList.contains('hs-inline-mosaic')) {
            const isPaginated = container.classList.contains('hs-paginate-mosaic');
            let chunks = [];
            if (isPaginated && validGalleryItems.length > 12) {
                // STEP 1: Interleave portraits and landscapes evenly.
                // This prevents portrait-heavy or landscape-heavy clusters from
                // creating mathematically impossible chunks.
                const landItems = validGalleryItems.filter(d => !d.isPortrait);
                const portItems = validGalleryItems.filter(d => d.isPortrait);
                const interleaved = [];
                let li = 0, pi = 0;
                // Distribute whichever type is less common evenly among the other
                const totalL = landItems.length;
                const totalP = portItems.length;
                for (let i = 0; i < validGalleryItems.length; i++) {
                    // Calculate how many of each type should appear by position i+1
                    const targetL = Math.round((i + 1) * totalL / validGalleryItems.length);
                    const targetP = Math.round((i + 1) * totalP / validGalleryItems.length);
                    // Add whichever type is most behind its target
                    if (li < totalL && (pi >= totalP || li < targetL)) {
                        interleaved.push(landItems[li++]);
                    } else if (pi < totalP) {
                        interleaved.push(portItems[pi++]);
                    }
                }
                
                // STEP 2: Chunk the interleaved array, testing each chunk size
                // with the actual math engine to guarantee it won't fall back.
                let currentIndex = 0;
                while (currentIndex < interleaved.length) {
                    let remaining = interleaved.length - currentIndex;
                    if (remaining <= 18) {
                        chunks.push(interleaved.slice(currentIndex));
                        break;
                    }
                    
                    let bestSize = 14;
                    
                    // Test chunk sizes from preferred to flexible, pick the first
                    // that produces a perfect (non-fallback) bento grid
                    const sizeOptions = [14, 15, 13, 16, 12, 17, 11, 18, 10];
                    for (let size of sizeOptions) {
                        if (currentIndex + size > interleaved.length) continue;
                        // Don't leave a tiny remainder (< 10) for the last chunk
                        let wouldRemain = interleaved.length - currentIndex - size;
                        if (wouldRemain > 0 && wouldRemain < 10) continue;
                        
                        let testChunk = interleaved.slice(currentIndex, currentIndex + size);
                        let lCount = testChunk.filter(d => !d.isPortrait).length;
                        let pCount = testChunk.filter(d => d.isPortrait).length;
                        
                        let testConfig = generateFlushGrid(lCount, pCount, 5);
                        if (!testConfig.isFallback) {
                            bestSize = size;
                            break;
                        }
                    }
                    
                    chunks.push(interleaved.slice(currentIndex, currentIndex + bestSize));
                    currentIndex += bestSize;
                }
            } else {
                chunks.push(validGalleryItems);
            }

            const inlineConfigs = chunks.map((chunk, chunkIndex) => {
                let cLand = chunk.filter(d => !d.isPortrait);
                let cPort = chunk.filter(d => d.isPortrait);
                const isLastChunk = chunkIndex === chunks.length - 1;
                
                let chunkConfig;
                if (cPort.length === 0 && cLand.length <= 20 && FIXED_LANDSCAPE_CONFIGS[cLand.length]) {
                    chunkConfig = JSON.parse(JSON.stringify(FIXED_LANDSCAPE_CONFIGS[cLand.length]));
                } else if (isPaginated) {
                    // Last page: use masonry fallback (varied sizes, possibly ragged edge)
                    // Earlier pages: use nuclear fallback (flat edge) if math engine fails
                    chunkConfig = generateFlushGrid(cLand.length, cPort.length, 5, isLastChunk);
                } else {
                    const targetCols = chunk.length < 8 ? 4 : 5;
                    chunkConfig = generateFlushGrid(cLand.length, cPort.length, targetCols);
                }
                
                let mappedChunk = chunkConfig.items.map(item => ({ ...item, data: null }));
                const assignChunkSpan = (spanTarget, sourceArray) => {
                    mappedChunk.forEach(item => {
                        if (item.span === spanTarget && sourceArray.length > 0) item.data = sourceArray.shift();
                    });
                };
                
                if (isPaginated) {
                    assignChunkSpan('3x3', cLand);
                    assignChunkSpan('2x2', cLand);
                    assignChunkSpan('1x3', cPort);
                    assignChunkSpan('1x1', cLand); 
                    assignChunkSpan('1x1', cPort); // overflow portraits as 1x1 if needed
                } else {
                    assignChunkSpan('3x3', cLand);
                    assignChunkSpan('2x2', cLand);
                    assignChunkSpan('1x3', cPort);
                    assignChunkSpan('1x1', cLand); 
                    assignChunkSpan('1x1', cPort); 
                }

                return { cols: chunkConfig.cols, items: mappedChunk.filter(i => i.data !== null) };
            });

            renderInlineGallery(container, inlineConfigs);
        }
    }

    function renderInlineGallery(container, configs) {
        if (container.querySelector('.hs-inline-mosaic-wrapper')) return;

        let activeIndex = 0;
        // Default inline mosaics to split-theme so the auto-collapse logic can show text if present
        const themeClass = 'hs-split-theme';
        
        const wrapper = document.createElement('div');
        wrapper.className = 'hs-inline-mosaic-wrapper';
        
        const gridContainers = configs.map((config, index) => {
            const grid = document.createElement('div');
            grid.className = 'hs-mosaic-grid';
            if (index === 0) grid.classList.add('hs-active-grid');
            renderMosaicGrid(config, grid);
            wrapper.appendChild(grid);
            return grid;
        });

        // Initialize Magnific Popup globally on the wrapper so users can cycle through ALL images across all pages
        if (window.jQuery && jQuery.fn.magnificPopup) {
            jQuery(wrapper).magnificPopup({
                delegate: 'a',
                type: 'image',
                gallery: { enabled: true, navigateByImgClick: true, preload: [1,2] }, 
                mainClass: `mfp-fade ${themeClass}`, 
                fixedContentPos: true,
                fixedBgPos: true,
                closeBtnInside: false,
                closeMarkup: '<div title="%title%" class="mfp-close hs-modal-close hs-shared-close"><span class="hs-modal-close-text">CLOSE</span><span class="hs-modal-close-icon">&times;</span></div>',
                image: {
                    markup: '<div class="mfp-figure hs-split-preview">'+
                              '<div class="hs-preview-img-wrapper"><div class="mfp-img"></div></div>'+
                              '<div class="hs-preview-text-wrapper">'+
                                 '<div class="mfp-title"></div>'+
                                 '<div class="mfp-counter"></div>'+
                              '</div>'+
                              '<div class="mfp-close-container"></div>'+
                            '</div>',
                    titleSrc: function(item) {
                        let t = item.el.attr('data-title') || '';
                        let c = item.el.attr('data-caption') || '';
                        // data-title/caption hold decoded text; escape before building HTML.
                        const esc = s => s.replace(/[&<>"']/g, ch => '&#' + ch.charCodeAt(0) + ';');
                        t = esc(t); c = esc(c);
                        let html = '';
                        if(t) html += '<div style="color:#003057; font-weight:800; font-size:1.5rem; margin-bottom:10px; line-height:1.2; font-family:\'Montserrat\', sans-serif;">' + t + '</div>';
                        if(c) html += '<div style="font-family:\'Open Sans\', sans-serif; color:#444; font-size:0.95rem; line-height:1.6;">' + c + '</div>';
                        return html;
                    }
                },
                callbacks: {
                    change: function() {
                        let t = this.currItem.el.attr('data-title') || '';
                        let c = this.currItem.el.attr('data-caption') || '';
                        if (!t && !c) {
                            this.wrap[0].classList.add('hs-empty-text');
                        } else {
                            this.wrap[0].classList.remove('hs-empty-text');
                        }
                    }
                }
            });
        }
        
        container.appendChild(wrapper);

        if (configs.length > 1) {
            const controls = document.createElement('div');
            controls.className = 'hs-paginate-controls';
            
            const prevBtn = document.createElement('button');
            prevBtn.className = 'hs-carousel-btn';
            prevBtn.innerHTML = '&#8592;';
            prevBtn.disabled = true;
            
            const dotsWrapper = document.createElement('div');
            dotsWrapper.className = 'hs-paginate-dots';
            const dots = configs.map((_, i) => {
                const dot = document.createElement('div');
                dot.className = `hs-dot ${i === 0 ? 'active' : ''}`;
                dot.addEventListener('click', () => showPage(i));
                dotsWrapper.appendChild(dot);
                return dot;
            });
            
            const nextBtn = document.createElement('button');
            nextBtn.className = 'hs-carousel-btn';
            nextBtn.innerHTML = '&#8594;';
            
            controls.appendChild(prevBtn);
            controls.appendChild(dotsWrapper);
            controls.appendChild(nextBtn);
            container.appendChild(controls);
            
            const showPage = (index) => {
                const prevHeight = wrapper.offsetHeight;
                
                gridContainers[activeIndex].classList.remove('hs-active-grid');
                dots[activeIndex].classList.remove('active');
                
                activeIndex = index;
                
                gridContainers[activeIndex].classList.add('hs-active-grid');
                dots[activeIndex].classList.add('active');
                
                wrapper.style.height = prevHeight + 'px';
                void wrapper.offsetHeight; // force reflow
                wrapper.style.height = gridContainers[activeIndex].offsetHeight + 'px';
                
                prevBtn.disabled = activeIndex === 0;
                nextBtn.disabled = activeIndex === configs.length - 1;
            };
            
            prevBtn.addEventListener('click', () => { if (activeIndex > 0) showPage(activeIndex - 1); });
            nextBtn.addEventListener('click', () => { if (activeIndex < configs.length - 1) showPage(activeIndex + 1); });
            
            // Keep height responsive when window resizes
            const ro = new ResizeObserver(() => {
                if (gridContainers[activeIndex]) {
                    wrapper.style.height = gridContainers[activeIndex].offsetHeight + 'px';
                }
            });
            ro.observe(container);
        }
        
        container.classList.add('hs-mosaic-loaded');
    }

    // Only on-page mosaics need compiling before a click; do it as they near the viewport.
    // Gallery-bar sections compile on click (see handler below). preCompileGallery loads
    // every full-size image to measure it, so the old 1.5s timer cost 20+ downloads per page.
    const hsMosaicObserver = new IntersectionObserver((entries, obs) => {
        entries.forEach(entry => {
            if (!entry.isIntersecting) return;
            obs.unobserve(entry.target);
            preCompileGallery(entry.target);
        });
    }, { rootMargin: '600px 0px' });
    document.querySelectorAll('.hs-gallery-container.hs-summary-mosaic, .hs-gallery-container.hs-inline-mosaic')
        .forEach(c => hsMosaicObserver.observe(c));


    /* --- EVENT ROUTING & MODAL RENDERING (ORIGINAL LOGIC) --- */
    
    document.addEventListener('click', async function(e) {
        
        const trigger = e.target.closest('.hs-gallery-trigger, .hs-gallery-trigger-split, .hs-gallery-trigger-full');
        if (!trigger) return;

        e.preventDefault();
        e.stopPropagation();

        const container = trigger.closest('.hs-gallery-container');
        if (!container) return;

        const isSplitTheme = trigger.classList.contains('hs-gallery-trigger-split');
        const themeClass = isSplitTheme ? 'hs-split-theme' : 'hs-full-theme';

        const mosaicGrid = modalPopup.querySelector('.hs-mosaic-grid');
        if (!mosaicGrid) return;
        
        const scrollbarWidth = window.innerWidth - document.documentElement.clientWidth;
        if (scrollbarWidth > 0) {
            document.body.style.paddingRight = `${scrollbarWidth}px`;
            const mainHeader = document.getElementById('main-header'); 
            if (mainHeader) mainHeader.style.paddingRight = `${scrollbarWidth}px`;
        }

        if(modalPopup) modalPopup.style.setProperty('display', 'block', 'important');
        document.body.style.overflow = 'hidden'; 
        
        mosaicGrid.innerHTML = ''; 

        if (!container.hsGridConfig) {
            await preCompileGallery(container);
        }

        const config = container.hsGridConfig;
        if (!config) return;

        renderMosaicGrid(config, mosaicGrid);

        if (window.jQuery && jQuery.fn.magnificPopup) {
            jQuery(mosaicGrid).magnificPopup({
                delegate: 'a',
                type: 'image',
                gallery: { enabled: true, navigateByImgClick: true, preload: [1,2] }, 
                mainClass: `mfp-fade ${themeClass}`, 
                closeBtnInside: false,
                closeMarkup: '<div title="%title%" class="mfp-close hs-modal-close hs-shared-close"><span class="hs-modal-close-text">CLOSE</span><span class="hs-modal-close-icon">&times;</span></div>',
                image: {
                    markup: '<div class="mfp-figure hs-split-preview">'+
                              '<div class="hs-preview-img-wrapper"><div class="mfp-img"></div></div>'+
                              '<div class="hs-preview-text-wrapper">'+
                                 '<div class="mfp-title"></div>'+
                                 '<div class="mfp-counter"></div>'+
                              '</div>'+
                              '<div class="mfp-close-container"></div>'+
                            '</div>',
                    titleSrc: function(item) {
                        let t = item.el.attr('data-title') || '';
                        let c = item.el.attr('data-caption') || '';
                        // data-title/caption hold decoded text; escape before building HTML.
                        const esc = s => s.replace(/[&<>"']/g, ch => '&#' + ch.charCodeAt(0) + ';');
                        t = esc(t); c = esc(c);
                        let html = '';
                        if(t) html += '<div style="color:#003057; font-weight:800; font-size:1.5rem; margin-bottom:10px; line-height:1.2; font-family:\'Montserrat\', sans-serif;">' + t + '</div>';
                        if(c) html += '<div style="font-family:\'Open Sans\', sans-serif; color:#444; font-size:0.95rem; line-height:1.6;">' + c + '</div>';
                        return html;
                    }
                },
                callbacks: {
                    change: function() {
                        let t = this.currItem.el.attr('data-title') || '';
                        let c = this.currItem.el.attr('data-caption') || '';
                        if (!t && !c) {
                            this.wrap[0].classList.add('hs-empty-text');
                        } else {
                            this.wrap[0].classList.remove('hs-empty-text');
                        }
                    },
                    open: function() { if(closeBtn) { closeBtn.style.opacity = '0'; closeBtn.style.pointerEvents = 'none'; } },
                    close: function() { if(closeBtn) { closeBtn.style.opacity = '1'; closeBtn.style.pointerEvents = 'auto'; } }
                }
            });
        }
    }, true); 








});
