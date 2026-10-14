<?php

/**
 * Gallery-Image-Real-Size
 *
 * Adds data-hs-w / data-hs-h / data-hs-srcset (original size) to content images so gallery-mosaic.js lays out portraits correctly. Jeremy, 2026-10-13.
 */
/**
 * Adds each content image's original size to its <img> tag so gallery-mosaic.js can tell
 * portrait from landscape without downloading the full photo.
 *
 * WordPress gives Divi gallery thumbnails width/height of the cropped thumbnail (e.g. 400x284),
 * which is always landscape. This adds:
 *   data-hs-w / data-hs-h  original width / height of the attachment
 *   data-hs-srcset         full srcset, so large pop-up tiles load a suitable size
 *
 * Runs on the front end only, through the core filter wp_content_img_tag (fires for every
 * content image WordPress can match to an attachment). No DB writes.
 */
add_filter( 'wp_content_img_tag', 'hs_gallery_image_real_size', 20, 3 );
function hs_gallery_image_real_size( $image, $context, $attachment_id ) {
    if ( is_admin() || ! $attachment_id || ! class_exists( 'WP_HTML_Tag_Processor' ) ) {
        return $image;
    }

    $meta = wp_get_attachment_metadata( $attachment_id );
    if ( empty( $meta['width'] ) || empty( $meta['height'] ) ) {
        return $image;
    }

    $tags = new WP_HTML_Tag_Processor( $image );
    if ( ! $tags->next_tag( 'img' ) || null !== $tags->get_attribute( 'data-hs-w' ) ) {
        return $image;
    }

    $tags->set_attribute( 'data-hs-w', (string) (int) $meta['width'] );
    $tags->set_attribute( 'data-hs-h', (string) (int) $meta['height'] );

    $srcset = wp_get_attachment_image_srcset( $attachment_id, 'full', $meta );
    if ( $srcset ) {
        $tags->set_attribute( 'data-hs-srcset', $srcset );
    }

    return $tags->get_updated_html();
}
