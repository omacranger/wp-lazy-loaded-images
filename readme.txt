=== Plugin Name ===
Contributors: omac
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=B38QAQ2DENKEE&lc=US&item_name=Logan%20Graham&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: Lazy, Loading, Images, Plugin
Requires at least: 2.8.0
Tested up to: 4.7.0
Stable tag: 1.3.0
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to enable lazy loading for all images using WordPress image functions.

== Description ==

A simple plugin to enable lazy loading for all images using WordPress image functions, or embedded into posts and pages using the WordPress dashboard.

The plugin works by replacing the original image source with a blank white, which can be changed via filters, image via datauri. Reducing page load speed, and saving bandwidth for both you and visitors. The plugin makes use of the fantastic [Lazy Load Plugin for jQuery](https://github.com/tuupola/jquery_lazyload).

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/wp-lazy-loaded-images` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress
3. That's it! It should automatically replace images used inside of posts / pages (where passed through properly in themes), plugins, and more.


== Frequently Asked Questions ==

= How can I change the default placeholder color? =

You can use the `placeholder_image_color` filter to supply a custom RGB array to define your own custom color globally for all placeholder images. Like so:

     add_filter('placeholder_image_color', function () {
        return array(0, 0, 0); // Return black placeholder for all images
     });

You can also change it on a per-image basis for use in theme development; The below example would give a white fallback for the outputted image only, by passing an RGB array as the value for a custom attribute of `placeholder_color` in the attr argument:

     wp_get_attachment_image( $image_id, $image_size, false, array(
        'placeholder_color' => array(
            255,
            255,
            255
        )
     ) );

The same custom attribute can be passed to a few different functions such as `the_post_thumbnail`, `get_the_post_thumbnail`, `wp_get_attachment_image`, etc. Basically any function that outputs the entire img object through WordPress and allows you to pass an attribute array.

= Fade in images as they load =

By default I've included a class that's added after images are loaded to assist in theme development, and improve aesthetics. You can include the below CSS to animate images in after they are loaded in supported browsers (using opacity transition).

     .lazy-load {
        opacity: 0;
        transition: .4s opacity ease-in-out;
     }

     .lazy-load.loaded {
        opacity: 1;
     }

== Changelog ==

= 1.3.0 =
* Change `the_content` parser to a DOM parser to be a bit more accurate when replacing images, and better support for attributes (alt, title, classes)
* Add support to disable lazy loading on a per-image basis by passing `data-no-lazy` on the image you don't want loading inside the post/page content area
* Added a few filters for developers / users

= 1.2.0 =
* Move script enqueue to footer
* Allow filter for custom placeholder color on a per-image basis

= 1.1 =
* Add support for automatically replacing images embedded inside posts and pages

= 1.0 =
* Initial Release