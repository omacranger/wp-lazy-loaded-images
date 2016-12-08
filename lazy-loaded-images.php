<?php
/*
Plugin Name: WP Lazy Loaded Images
Plugin URI: https://wordpress.org/plugins/wp-lazy-loaded-images/
Description: A plugin to enable lazy-loading on all images using official WordPress functions.
Version: 1.1.1
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

		if ( is_feed() ) { // Skip replacement on feeds
			return $content;
		}

		preg_match_all( "`<img.*?/>`", $content, $matches );

		if ( count( $matches[0] ) ) {
			// Iterate through found images - replace src and whatnot
			foreach ( $matches[0] as $match ) {
				preg_match( '/wp-image-([0-9]+)/', $match, $id );
				preg_match( '/size-([a-z]+)/', $match, $size );
				if ( isset( $id[1] ) && isset( $size[1] ) ) {
					$new_image = wp_get_attachment_image( $id[1], $size[1] );
					$content   = str_replace( $match, $new_image, $content );
				}

			}
		}

		return $content;
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