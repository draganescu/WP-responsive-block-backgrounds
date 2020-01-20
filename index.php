<?php
/**
 * @package Responsive Images
 * @version 0.1
 */
/*
Plugin Name: Responsive Images
Plugin URI: http://wordpress.org/plugins/responsive-images/
Description: This is a test plugin for serverside embedded media querries
Author: Andrei Draganescu
Version: 0.1
Author URI: https://andreidraganescu.info
*/
function extract_image_data( $block ) {
	$index = 0;
	foreach ( (array) $block['innerContent'] as $key => $chunk ) {
		if ( is_string( $chunk ) ) {
			$block['innerContent'][ $key ] = _image_data( $chunk );
		} else {
			if ( ! is_null( $chunk ) ) {
				$block['innerBlocks'][ $index++ ] = extract_image_data( $block['innerBlocks'][ $index++ ] );
			}
		}
	}
	return $block;
}

function _image_data( $content ) {
	if ( ! preg_match_all( '/<img [^>]+>/', $content, $matches ) ) {
		return $content;
	}

	$styles          = '<style>%s</style>';
	$style_list      = '';
	$selected_images = array();
	$attachment_ids  = array();

	foreach ( $matches[0] as $image ) {
		if ( false === strpos( $image, ' srcset=' ) && preg_match( '/wp-image-([0-9]+)/i', $image, $class_id ) ) {
			$attachment_id = absint( $class_id[1] );

			if ( $attachment_id ) {
				/*
				 * If exactly the same image tag is used more than once, overwrite it.
				 * All identical tags will be replaced later with 'str_replace()'.
				 */
				$selected_images[ $image ] = $attachment_id;
				// Overwrite the ID when the same image is included more than once.
				$attachment_ids[ $attachment_id ] = true;
			}
		}
	}

	if ( count( $attachment_ids ) > 1 ) {
		/*
		 * Warm the object cache with post and meta information for all found
		 * images to avoid making individual database calls.
		 */
		_prime_post_caches( array_keys( $attachment_ids ), false, true );
	}

	foreach ( $selected_images as $image => $attachment_id ) {
		$image_meta = wp_get_attachment_metadata( $attachment_id );
		$style = '
			@media (min-width: ' . $image_meta['width'] . 'px) {
				.wp-image-' . $attachment_id . ' {
					min-height: ' . $image_meta['height'] . 'px;
					background: url(\'' . wp_get_attachment_url( $attachment_id ) . '\') repeat-x;
				}
		  }
		';
		foreach ( $image_meta['sizes'] as $name => $size ) {
			$style .= '
			@media (max-width: ' . $size['width'] . 'px) {
				.wp-image-' . $attachment_id . ' {
					min-height: ' . $size['height'] . 'px;
					background: url(\'' . wp_get_attachment_image_src( $attachment_id, $name )[0] . '\') repeat-x;
				}
		  }
		';
		}
		$div_image   = '<div class="wp-image-' . $attachment_id . '"><div>';
		$content     = str_replace( $image, $div_image, $content );
		$style_list .= $style;
	}

	$styles = sprintf( $styles, $style_list );

	return $styles . "\n" . $content;
}

add_filter( 'render_block_data', 'extract_image_data' );
