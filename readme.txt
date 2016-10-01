=== Plugin Name ===
Contributors: omac
Donate link: https://www.paypal.com/cgi-bin/webscr?cmd=_donations&business=B38QAQ2DENKEE&lc=US&item_name=Logan%20Graham&currency_code=USD&bn=PP%2dDonationsBF%3abtn_donate_SM%2egif%3aNonHosted
Tags: Lazy, Loading, Images, Plugin
Requires at least: 2.8.0
Tested up to: 4.6.1
Stable tag: 1.1
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A simple plugin to enable lazy loading for all images using WordPress image functions.

== Description ==

A simple plugin to enable lazy loading for all images using WordPress image functions, or embedded into posts and pages using the WordPress dashboard.

The plugin works by replacing the original image source with a blank white, which can be changed via filters, image via datauri. Reducing page load speed, and saving bandwidth for both you and visitors.

== Installation ==

This section describes how to install the plugin and get it working.

1. Upload the plugin files to the `/wp-content/plugins/wp-lazy-loaded-images` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress


== Frequently Asked Questions ==

= How can I change the default placeholder color? =

You can use the `placeholder_image_color` filter to supply a custom RGB array to define your own custom color. Like so:

     add_filter('placeholder_image_color', function () {
         return array(0, 0, 0); // Return black placeholder
     });

== Changelog ==

= 1.1 =
* Add support for automatically replacing images embedded inside posts and pages.

= 1.0 =
* Initial Release