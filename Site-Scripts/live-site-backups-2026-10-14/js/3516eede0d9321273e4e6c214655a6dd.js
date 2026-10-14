
/**
 * =============================================================================
 * HEADSTART ATTRIBUTES SIDEBAR ENGINE
 * =============================================================================
 * PURPOSE: Manages the vertical positioning of the "Who We Are" left-hand sidebar.
 * LOGIC: Prevents visual collision with the main navigation header at the top of
 * the page. The CSS natively offsets it 30px lower. Toggles the 'is-scrolled' 
 * class past 20px of scroll to glide the sidebar into absolute vertical center.
 * DEPENDENCIES: None
 * =============================================================================
 */
document.addEventListener("DOMContentLoaded", function() {
    
    const sidebar = document.querySelector('.hs-attribute-sidebar');
    
    if (!sidebar) return;

    /**
     * Evaluates scroll depth using IntersectionObserver for optimal performance.
     */
    let sentinel = document.getElementById('hs-sidebar-sentinel');
    if (!sentinel) {
        sentinel = document.createElement('div');
        sentinel.id = 'hs-sidebar-sentinel';
        sentinel.style.position = 'absolute';
        sentinel.style.top = '20px';
        sentinel.style.left = '0';
        sentinel.style.width = '1px';
        sentinel.style.height = '1px';
        sentinel.style.pointerEvents = 'none';
        sentinel.style.visibility = 'hidden';
        document.body.appendChild(sentinel);
    }

    const observer = new IntersectionObserver((entries) => {
        entries.forEach(entry => {
            if (entry.isIntersecting) {
                sidebar.classList.remove('is-scrolled');
            } else {
                sidebar.classList.add('is-scrolled');
            }
        });
    });

    observer.observe(sentinel);







});
