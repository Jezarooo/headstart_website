
/**
 * =============================================================================
 * HEADSTART FORM TOGGLE ENGINE (BOOKING VS. CONTACT)
 * =============================================================================
 * PURPOSE: Manages animated UI swapping between the "Book a Tour" and "Have a Question" forms.
 * LOGIC: Intercepts link clicks to swap active/hidden CSS classes, dynamically updates 
 * ARIA attributes for accessibility, and auto-focuses the first input of the active form.
 * DEPENDENCIES: Form DOM elements
 * =============================================================================
 */
document.addEventListener('DOMContentLoaded', function() {
    
    /* --- DOM ELEMENTS --- */
    
    const btnToEmail = document.getElementById('switch-to-email');
    const btnToBooking = document.getElementById('switch-to-booking');
    const wrapBooking = document.getElementById('hs-booking-form-wrapper');
    const wrapEmail = document.getElementById('hs-email-form-wrapper');

    /* --- UTILITIES --- */
    
    /**
     * Locates and focuses the first text, email, or telephone input 
     * within a given form wrapper after a brief 100ms animation delay.
     */
    function focusFirstInput(formWrapper) {
        const firstInput = formWrapper.querySelector('input[type="text"], input[type="email"], input[type="tel"]');
        if (firstInput) {
            setTimeout(() => { firstInput.focus(); }, 100); 
        }
    }

    /* --- EVENT HANDLERS --- */

    if (btnToEmail && btnToBooking) {
        
        btnToEmail.addEventListener('click', function(e) {
            e.preventDefault();
            
            wrapBooking.classList.remove('hs-form-active');
            wrapBooking.classList.add('hs-form-hidden');
            wrapBooking.setAttribute('aria-hidden', 'true');
            
            wrapEmail.classList.remove('hs-form-hidden');
            wrapEmail.classList.add('hs-form-active');
            wrapEmail.setAttribute('aria-hidden', 'false');
            
            focusFirstInput(wrapEmail);
        });

        btnToBooking.addEventListener('click', function(e) {
            e.preventDefault();
            
            wrapEmail.classList.remove('hs-form-active');
            wrapEmail.classList.add('hs-form-hidden');
            wrapEmail.setAttribute('aria-hidden', 'true');
            
            wrapBooking.classList.remove('hs-form-hidden');
            wrapBooking.classList.add('hs-form-active');
            wrapBooking.setAttribute('aria-hidden', 'false');
            
            focusFirstInput(wrapBooking);
        });
    }







});
