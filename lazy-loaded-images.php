<?php
/*
Plugin Name: WP Lazy Loaded Images
Plugin URI: https://wordpress.org/plugins/wp-lazy-loaded-images/
Description: A simple plugin to enable lazy-loading for images on WordPress.
Version: 2.0.5
Author: Logan Graham
Author URI: http://twitter.com/LoganPGraham
License: GPL2
*/

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class WP_Lazy_Loaded_Images {

	private $do_noscript;

	/**
	 * Lazy_Loaded_Images constructor.
	 */
	public function __construct() {
		if ( ! is_admin() ) {
			// Front-End scripts
			add_action( 'wp_get_attachment_image_attributes', array( $this, 'modify_image_attributes' ), 10, 3 );

			// Enqueue Scripts / Styles
			add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_scripts' ) );

			add_filter( 'the_content', array( $this, 'filter_post_content' ) );
			add_filter( 'do_shortcode_tag', array( $this, 'filter_post_content' ) );

			add_action( 'wp', function () {
				$this->do_noscript = apply_filters( 'lazy_load_enable_noscript', false );

				if ( apply_filters( 'lazy_load_enable_fallback', false ) ) {
					$this->do_noscript = true;

					add_action( 'wp_head', array( $this, 'output_nojs_styles' ) );

					// Add body class for 'nojs' if it doesn't exist.
					add_filter( 'body_class', function ( $classes, $class ) {
						if ( ! in_array( 'no-js', $classes ) ) {
							$classes[] = 'no-js';
						}

						return $classes;
					}, 10, 2 );

					// Add script to remove class from body for individuals who have Javascript enabled
					add_action( 'wp_footer', array( $this, 'output_nojs_script' ), 1 );
				}

				if ( $this->do_noscript ) {
					add_filter( 'post_thumbnail_html', array( $this, 'filter_post_thumbnail' ), 10, 5 );
				}
			} );
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
			@$dom->loadHTML( mb_convert_encoding( $content, 'HTML-ENTITIES', 'UTF-8' ), LIBXML_HTML_NODEFDTD );

			$images = $dom->getElementsByTagName( 'img' );

			if ( $images->length ) {
				// Iterate through found images - replace src and whatnot
				/* @var $image DOMElement */
				for( $x = 0; $x < $images->length; $x++ ) {
					$image = $images->item($x);

					// Check to see if image has already been filtered through - prevent duplicates for shortcode & post content blocks
					$data_src = $image->getAttribute( 'data-src' );
					if ( ! empty( $data_src ) ) {
						continue;
					}

					// Automatically load all attributes
					$attributes = array();

					if ( $image->hasAttributes() ) {
						foreach ( $image->attributes as $attr => $value ) {
							$attributes[ $attr ] = $value->value;
						}
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

							if ( $this->do_noscript ) {
								// Fallback
								$noscript = $dom->createElement( 'noscript' );
								$noscript->appendChild( $image->cloneNode() );
								$x ++; // Double iterate x since we added an additional image to the domnodelist

								$parent->insertBefore( $noscript, $image );
							}

							$parent->replaceChild( $new_node, $image );

						} elseif ( isset( $attributes['width'] ) && isset( $attributes['height'] ) && isset( $attributes['src'] ) ) {
							// Image was not found (maybe external, or ID wasn't present), so check if has height, width, and src attributes (required for placeholder) to pre-fill and generate
							$attributes['class'] = ( isset( $attributes['class'] ) ) ? $attributes['class'] . ' lazyload lazy-fallback' : 'lazyload lazy-fallback';

							// Manually create new image
							$manual_image = $dom->createElement( 'img' );

							// Bring all previous attributes
							foreach ( $attributes as $key => $attribute ) {
								$manual_image->setAttribute( $key, $attribute );
							}

							// Overwrite source & data source for lazy loading
							$manual_image->setAttribute( 'data-src', $attributes['src'] );
							$manual_image->setAttribute( 'src', self::create_placeholder_image( $attributes['width'], $attributes['height'] ) );

							if ( $this->do_noscript ) {
								// Fallback
								$noscript = $dom->createElement( 'noscript' );
								$noscript->appendChild( $image->cloneNode() );
								$x ++; // Double iterate x since we added an additional image to the domnodelist

								$parent->insertBefore( $noscript, $image );
							}

							$parent->replaceChild( $manual_image, $image );
						}
					}
				}
			}

			$html = trim( $dom->saveHTML() );

			return substr( $html, 12, - 14 ); // Strip out 'html' and 'body' tags that are automatically appended.
		} catch ( \Exception $e ) {
			if ( apply_filters( 'lazy_load_log_errors', false ) ) { // Set to false by default to prevent excess error logging if used on high traffic site
				error_log( $e->getMessage() );
			}

			return $content;
		}
	}

	/**
     * Append noscript fallback for post thumbnails if enabled
     *
	 * @param $html
	 * @param $post_id
	 * @param $post_thumbnail_id
	 * @param $size
	 * @param string|array $attr
     *
     * @return string $html
	 */
	function filter_post_thumbnail( $html, $post_id, $post_thumbnail_id, $size, $attr ) {
		$default_attr = $lazy_attr = wp_parse_args( $attr );

		// Default attribute modification
		if ( isset( $default_attr['class'] ) ) {
			$default_attr['class'] .= ' lazy-fallback';
		} else {
			$default_attr['class'] = 'lazy-fallback';
		}

		// Lazy / Fallback attributes to prevent processing
		$lazy_attr['data-no-lazy'] = true;

		return sprintf( "%s<noscript>%s</noscript>", wp_get_attachment_image( $post_thumbnail_id, $size, false, $default_attr ), wp_get_attachment_image( $post_thumbnail_id, $size, false, $lazy_attr ) );
	}

	/**
	 * Enqueue styles for use on the front-end.
	 */
	function enqueue_scripts() {
		if ( ! wp_style_is( 'lazysizes' ) ) {
			wp_enqueue_script( 'lazysizes', $this->get_plugin_url( '/includes/js/lazysizes.min.js' ), array(), '4.1.4', true );
		}
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

		if ( strpos( $source['mime'], 'svg', 0 ) === false && ! isset( $attr['data-no-lazy'] ) ) {

			$attr['data-src'] = $attr['src'];
			$source           = wp_get_attachment_image_src( $attachment->ID, $size );
			$attr['src']      = self::create_placeholder_image( $source[1], $source[2] );

			if ( isset( $attr['srcset'] ) ) {
				$attr['data-srcset'] = $attr['srcset'];
				$attr['data-sizes']  = $attr['sizes'];
				$attr['srcset']      = self::create_placeholder_image( $source[1], $source[2] );
			}

			$attr['class'] = ( strpos( $attr['class'], "lazyload" ) === false ) ? $attr['class'] . " lazyload" : $attr['class'];

			unset( $attr['sizes'] );
		}

		return $attr;
	}

	/**
	 * Function to create placeholder image (data-uri) for existing attachment.
	 *
	 * @param int        $width
	 * @param int        $height
	 *
	 * @return string
	 */
	static function create_placeholder_image( $width = 0, $height = 0 ) {
		$image = imagecreate( $width, $height );
		$color = imagecolorallocate( $image, 255, 255, 255 );
		$color = imagecolortransparent( $image, $color );
		imagefill( $image, 0, 0, $color );
		ob_start();
		imagegif( $image );
		$string = ob_get_clean();
		imagedestroy( $image );

		return 'data:image/gif;base64,' . base64_encode( $string );
	}

	/**
	 * Helper function to automatically append 'nojs' styles for those not incorporating in theme
	 */
	function output_nojs_styles(){
		?><style type="text/css">.no-js .lazyload.lazy-fallback {display: none;}</style><?php
	}

	/**
	 * Helper function to automatically append 'nojs' removal for those not incorporating in theme
	 */
	function output_nojs_script() {
		?>
		<script type="text/javascript">
            var body = document.getElementsByTagName('body')[0];
            if (body != undefined) {
                body.setAttribute('class', body.getAttribute('class').replace('no-js', ''));
            }
		</script>
		<?php
	}
}

global $lazy_loaded_images;
$lazy_loaded_images = new WP_Lazy_Loaded_Images();