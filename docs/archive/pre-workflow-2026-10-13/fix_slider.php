<?php
$file = 'Site-Scripts/php/posts-slider-shortcode.code-snippets.php';
$content = file_get_contents($file);

// 1. Fix Swiper config: slidesPerGroup, loop logic, initialSlide
$replacement = <<<CODE
                    // Determine safe looping threshold
                    var totalPosts = <?php echo intval(\$query->found_posts); ?>;
                    var enableLoop = (totalPosts >= 6 && totalPosts <= 20); 
                    // If massive feed, rely on infinite scroll instead of looping
                    var isLooping = enableLoop;
                    
                    try {
                        container.hsSwiperInstance = new Swiper('.hsPostSliderInit-%%HS_SLIDER_ID_PLACEHOLDER%%', {
                            on: {
                                init: function () {
                                    console.log('Swiper INIT event fired!');
                                    container.classList.add('hs-swiper-loaded');
                                    var loader = container.querySelector('.hs-slider-loader');
                                    if (loader) loader.style.display = 'none';
                                    this.hsCurrentPage = <?php echo \$paged; ?>;
                                    this.hsIsLoading = false;
                                },
                                reachEnd: function() {
                                    // INFINITE SCROLL LOGIC
                                    <?php if (\$display === 'slider') : ?>
                                    if (this.hsIsLoading || enableLoop) return; // Do not AJAX if we are in strict loop mode
                                    this.hsIsLoading = true;
CODE;

$content = preg_replace('/try \{\s+container\.hsSwiperInstance = new Swiper\(\'\.hsPostSliderInit-%%HS_SLIDER_ID_PLACEHOLDER%%\'\, \{\s+on: \{\s+init: function \(\) \{\s+console\.log\(\'Swiper INIT event fired!\'\);\s+container\.classList\.add\(\'hs-swiper-loaded\'\);\s+var loader = container\.querySelector\(\'\.hs-slider-loader\'\);\s+if \(loader\) loader\.style\.display = \'none\';\s+this\.hsCurrentPage = <\?php echo \$paged; \?>;\s+this\.hsIsLoading = false;\s+\},\s+reachEnd: function\(\) \{\s+\/\/ INFINITE SCROLL LOGIC\s+<\?php if \(\$display === \'slider\'\) : \?>\s+if \(this\.hsIsLoading\) return;\s+this\.hsIsLoading = true;/', $replacement, $content);

$replacement2 = <<<CODE
                        slidesPerView: 'auto', 
                        slidesPerGroup: 1,
                        spaceBetween: 10,
                        centeredSlides: !isLooping, // Center the first slide explicitly if not looping
                        initialSlide: isLooping ? 0 : 1, // On mobile, start on 2nd slide so 1st peaks in from left
                        loop: isLooping, 
                        rewind: !isLooping, // If massive feed, allow snapping back to start
                        autoplay: isLooping ? { delay: 3500, disableOnInteraction: true, pauseOnMouseEnter: true } : false,
                        pagination: false,
                    navigation: {
                        nextEl: '.hs-slider-next-%%HS_SLIDER_ID_PLACEHOLDER%%, #%%HS_SLIDER_ID_PLACEHOLDER%% .swiper-button-next',
                        prevEl: '.hs-slider-prev-%%HS_SLIDER_ID_PLACEHOLDER%%, #%%HS_SLIDER_ID_PLACEHOLDER%% .swiper-button-prev',
                    },
                    breakpoints: {
                        640: { slidesPerView: 'auto', slidesPerGroup: 1, centeredSlides: false, initialSlide: 0 },
                        768: { 
                            slidesPerView: 'auto', 
                            slidesPerGroup: 1,
                            centeredSlides: false,
                            initialSlide: 0,
                            enabled: <?php echo (\$display === 'slider') ? 'true' : 'false'; ?> 
                        },
                        1024: { 
                            slidesPerView: 'auto', 
                            slidesPerGroup: 1,
                            centeredSlides: false,
                            initialSlide: 0,
                            enabled: <?php echo (\$display === 'slider') ? 'true' : 'false'; ?> 
                        },
                        1400: { 
                            slidesPerView: 'auto', 
                            slidesPerGroup: 1,
                            centeredSlides: false,
                            initialSlide: 0,
                            enabled: <?php echo (\$display === 'slider') ? 'true' : 'false'; ?> 
                        }
                    }
CODE;

$content = preg_replace('/slidesPerView: \'auto\', \s+spaceBetween: 10,\s+centeredSlides: shouldLoop,\s+loop: false, \/\/ Turn off loop for Infinite Scroll to work correctly\s+autoplay: shouldLoop \? \{ delay: 3500, disableOnInteraction: true, pauseOnMouseEnter: true \} : false,\s+pagination: false,\s+navigation: \{\s+nextEl: \'\.hs-slider-next-%%HS_SLIDER_ID_PLACEHOLDER%%, #%%HS_SLIDER_ID_PLACEHOLDER%% \.swiper-button-next\',\s+prevEl: \'\.hs-slider-prev-%%HS_SLIDER_ID_PLACEHOLDER%%, #%%HS_SLIDER_ID_PLACEHOLDER%% \.swiper-button-prev\',\s+\},\s+breakpoints: \{\s+640: \{ slidesPerView: \'auto\', centeredSlides: false \},\s+768: \{ \s+slidesPerView: \'auto\', \s+centeredSlides: false,\s+enabled: <\?php echo \(\$display === \'slider\'\) \? \'true\' : \'false\'; \?> \s+\},\s+1024: \{ \s+slidesPerView: \'auto\', \s+centeredSlides: false,\s+enabled: <\?php echo \(\$display === \'slider\'\) \? \'true\' : \'false\'; \?> \s+\},\s+1400: \{ \s+slidesPerView: \'auto\', \s+centeredSlides: false,\s+enabled: <\?php echo \(\$display === \'slider\'\) \? \'true\' : \'false\'; \?> \s+\}\s+\}/', $replacement2, $content);


file_put_contents($file, $content);
echo "PHP fixed.\n";
