<?php
/*
Plugin Name: WP Lazy Loaded Images
Plugin URI: https://wordpress.org/plugins/wp-lazy-loaded-images/
Description: A simple plugin to enable lazy-loading for images on WordPress.
Version: 1.4.0
Author: Logan Graham
Author URI: http://twitter.com/LoganPGraham
License: GPL2
*/


class WP_Lazy_Loaded_Images {

	/**
	 * Lazy_Loaded_Images constructor.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			// Front-End scripts
			add_action( 'wp_get_attachment_image_attributes', array( $this, 'modify_image_attributes' ), 10, 3 );

			// Enqueue Scripts / Styles
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
			// Inline
			add_action( 'wp_print_footer_scripts', array( $this, 'add_inline_scripts' ), 10 );

			add_filter( 'the_content', array( $this, 'filter_post_content' ) );
		}
	}

	/**
	 * Filter the content of all posts & pages, automatically replacing images with lazy-loaded equivalents
	 *
	 * @param $content
	 *
	 * @return mixed
	 */
	function filter_post_content( $content ) {

		if ( is_feed() || apply_filters( 'skip_lazy_load', false ) || empty( $content ) ) { // Skip replacement on feeds, or disable via filter
			return $content;
		}

		try {
			$dom                      = new DOMDocument(); // Post Content
			$new_image                = new DOMDocument(); // Replacement image parser
			$dom->strictErrorChecking = false;
			$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ) );

			$images = $dom->getElementsByTagName( 'img' );

			if ( $images->length ) {
				// Iterate through found images - replace src and whatnot
				/* @var $image DOMElement */
				for( $x = 0; $x < $images->length; $x++ ) {
					$image = $images->item($x);

					$supported_attributes = apply_filters( 'lazy_load_image_attributes', array(
						'class',
						'alt',
						'title'
					) );
					$attributes           = array();

					foreach ( $supported_attributes as $attribute ) {
						$attributes[ $attribute ] = $image->getAttribute( $attribute );
					}

					// Match image ID and size for dynamic image
					preg_match( '/wp-image-([0-9]+)/', $attributes['class'], $id ); // Image ID
					preg_match( '/size-([a-z]+)/', $attributes['class'], $size ); // Image Size

					// If they have no-lazy attribute, skip
					if ( ! $image->hasAttribute( 'data-no-lazy' ) ) {
						$parent = $image->parentNode;

						// If attributes are set, try to create image using native WP function, else set to false to try and parse otherwise
						if ( isset( $id[1] ) && isset( $size[1] ) ) {
							$new_image_html = wp_get_attachment_image( $id[1], $size[1], false, $attributes );
						} else {
							$new_image_html = false;
						}

						if ( $new_image_html ) {
							// If result is positive, image was created and will be output / replaced below
							$new_image->loadHTML( $new_image_html );
							$new_node = $dom->importNode( $new_image->getElementsByTagName( 'img' )->item( 0 ) );

							// Fallback
							$noscript = $dom->createElement( 'noscript' );
							$noscript->appendChild( $image->cloneNode() );
							$x++; // Double iterate x since we added an additional image to the domnodelist

							$parent->insertBefore( $new_node, $image );
							$parent->replaceChild( $noscript, $image );

						} elseif ( $image->hasAttribute( 'width' ) && $image->hasAttribute( 'height' ) && $image->hasAttribute( 'src' ) ) {
							// Image was not found (maybe external, or ID wasn't present), so check if has height, width, and src attributes (required for placeholder) to pre-fill and generate
							$classes = ( $image->hasAttribute( 'class' ) ) ? $image->getAttribute( 'class' ) . ' lazy-load' : 'lazy-load';

							// Manually create new image
							$manual_image = $dom->createElement( 'img' );
							$manual_image->setAttribute( 'width', $image->getAttribute( 'width' ) );
							$manual_image->setAttribute( 'height', $image->getAttribute( 'height' ) );
							$manual_image->setAttribute( 'data-lazy', $image->getAttribute( 'src' ) );
							$manual_image->setAttribute( 'src', self::create_placeholder_image( $image->getAttribute( 'width' ), $image->getAttribute( 'height' ) ) );
							$manual_image->setAttribute( 'class', $classes );

							// Fallback
							$noscript = $dom->createElement( 'noscript' );
							$noscript->appendChild( $image->cloneNode() );
							$x++; // Double iterate x since we added an additional image to the domnodelist

							$parent->insertBefore( $manual_image, $image );
							$parent->replaceChild( $noscript, $image );
						}
					}
				}
			}

			return $dom->saveHTML();
		} catch ( \Exception $e ) {
			if ( apply_filters( 'lazy_load_log_errors', false ) ) { // Set to false by default to prevent excess error logging if used on high traffic site
				error_log( $e->getMessage() );
			}

			return $content;
		}
	}

	/**
	 * Function to add inline scripts to reduce network calls.
	 */
	function add_inline_scripts() {
		$this->print_inline_script( join( DIRECTORY_SEPARATOR, array(
			__DIR__,
			'assets',
			'js',
			'lazyload-scripts.js'
		) ) );
	}

	/**
	 * Enqueue styles for use on the front-end.
	 */
	function enqueue_scripts() {
		if ( ! wp_style_is( 'jquery-lazyload' ) ) {
			wp_enqueue_script( 'jquery-lazyload', $this->get_plugin_url( '/includes/js/jquery.lazyload.min.js' ), array( 'jquery' ), '1.9.7', true );
		}
	}

	/**
	 * Helper function used to print out the javascript file minified inline. Can be dequeued using hooks if necessary.
	 *
	 * @param $path String File path for use with file_get_contents to return inline javascript
	 */
	function print_inline_script( $path ) {
		echo sprintf( "<script type='text/javascript'>%s</script>", file_get_contents( $path ) );
	}

	/**
	 * Function to generate url to plugin directory.
	 *
	 * @param bool $filename Extended filename to pass to function to return appended to plugin url
	 *
	 * @return string URL to file / plugin directory
	 */
	function get_plugin_url( $filename = false ) {
		return plugins_url( $filename, __FILE__ );
	}

	/**
	 * Function to hook in and modify the default WP attributes for images.
	 *
	 * @param $attr
	 * @param $attachment
	 * @param $size
	 *
	 * @return array Default Attributes
	 */
	function modify_image_attributes( $attr, $attachment, $size ) {
		$source = wp_prepare_attachment_for_js( $attachment->ID );
		if ( strpos( $source['mime'], 'svg', 0 ) === false ) {
			// Allow passing of custom placeholder color on individual image
			$placeholder_color = ( isset( $attr['placeholder_color'] ) ) ? $attr['placeholder_color'] : false;
			unset( $attr['placeholder_color'] );
			
			$attr['data-lazy'] = $attr['src'];
			$source            = wp_get_attachment_image_src( $attachment->ID, $size );
			$attr['src']       = $this->create_placeholder_image( $source[1], $source[2], $placeholder_color );
			$attr['class']     = ( strpos( $attr['class'], "lazy-load" ) === false ) ? $attr['class'] . " lazy-load" : $attr['class'];
			unset( $attr['srcset'], $attr['sizes'] ); // Unset srcset to prevent it from taking priority over lazy-loaded content
            // TODO: Better support / fallback for srcset
		}

		return $attr;
	}

	/**
	 * Function to create placeholder image (data-uri) for existing attachment.
	 *
	 * @param int        $width
	 * @param int        $height
	 * @param bool|array $color Array in RGB format to set the default background color
	 *
	 * @return string
	 */
	static function create_placeholder_image( $width = 0, $height = 0, $color = false ) {
		if ( ! $color ) {
			$color = apply_filters( 'placeholder_image_color', array( 255, 255, 255 ) );
		}
		$image = imagecreate( $width, $height );
		$color = imagecolorallocate( $image, $color[0], $color[1], $color[2] );
		imagefill( $image, 0, 0, $color );
		ob_start();
		imagegif( $image );
		$string = ob_get_clean();
		imagedestroy( $image );

		return 'data:image/png;base64,' . base64_encode( $string );
	}
}

global $lazy_loaded_images;
$lazy_loaded_images = new WP_Lazy_Loaded_Images();