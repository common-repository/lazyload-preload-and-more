=== Lazyload, Preload, and More! ===
Contributors: aryadhiratara, thinkdigitalway
Tags: lazyload, lazy load, loading eager, core web vitals, pagespeed, performance, preload, web vitals, image, iframe, video, woocommerce,
Requires at least: 5.8
Tested up to: 6.3
Requires PHP: 7.4
Stable tag: 1.0.4
License: GPLv2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

A drop dead simple and lightweight image, iframe, and video optimization plugin to satisfy Google PageSpeed Insights and Core Web Vitals.

== Description ==

A drop dead simple and lightweight image, iframe, and video optimization plugin to satisfy Google PageSpeed Insights, Lighthouse, and overall user experience.

This tiny little plugin (around **14kb** zipped) will automatically

- **lazyload** your below the fold images/iframes/videos,
- **preload** your featured images (should also works well with WooCommerce product featured image),
- **add loading="eager"** to your featured image and all images that have `no-lazy` or `skip-lazy` class.
- **add missing image dimension** to images that doesn't have width and height attribute

## Features

- **Lazy Load**:
 - Images, iframes, and videos.
 - Inline background images.
 - CSS background image (simply put `lazyload` class to the background image container)
- **Preload Featured Images**
Automatically preloading featured image from common page/post (homepage, pages except homepage, single post, and WooCommerce single product pages)
- **Add loading="eager" attribute** to to your featured image and all images that have `no-lazy` or `skip-lazy` class.
- **Add missing image dimension** to images that doesn't have width and height attribute [since 1.0.3]

Should works well with all page builders and theme builders. This plugin also able to lazy loading WooCommerce images and preloading WooCommerce product featured images.

##Disclaimer

- Lazy load feature are using **Lazysizes** library (around **3kb**, gzipped and minified).
- This plugin doesn't add anything to your database since there's no settings and options. Everything will automatically activated after you activate the plugin.
- You can change some default settings using filter.
- This plugin is the simplified version of **[Optimize More! – Images](https://wordpress.org/plugins/optimize-more-images/)**, if you don't want to use filters and need to control the settings with UI, you can try that plugin instead of this one.

##About Lazysizes

Lazysizes is highly performant lazy load library, written by Alexander Farkas in pure JS with no dependencies.

**Taken from lazysize's github description**:
*High performance and SEO friendly lazy loader for images (responsive and normal), iframes and more, that detects any visibility changes triggered through user interaction, CSS or JavaScript without configuration.*

##Filters

Example filter to add extra lazyload exclude list:

    add_filter( 'lpam_extra_exclude_list', function($lpam_extra_exclude_list) {
        return array( 
			'my-logo', 'my-hero-img', 'exclude-lazy'
        );
    } );

Example filter to change lazysizes config (Read the [docs](https://github.com/aFarkas/lazysizes/#js-api---options)):

    add_filter( 'lpam_lazysizes_js_before', function($lpam_lazysizes_js_before) {
        return 'window.lazySizesConfig = {
			expand: 500,
			threshold: 500
		};'
    } );

Example filter to change the image sizes for preload featured image:

    add_filter( 'lpam_preload_featured_images_image_size', function($image_size, $post) {
        if ( is_singular( 'post' ) ) { return 'large'; }
        elseif ( is_singular( 'product' ) ) { return 'full'; }
        else { return $image_size; }
    }, 10, 2 );

[_new_ since **1.0.2**] Example to disable the preload featured image feature in certain page-type:


	add_filter('disable_featured_image_preload', function ($disable) {
	    if ( is_singular( 'post' ) ) {
			return true;
		}
	});

[_new_ since **1.0.3**] Example to disable adding image dimension in certain page-type:

	add_filter('disable_img_dimension', function ($disable) {
	    if ( is_singular( 'post' ) ) {
			return true;
		}
	});

[_new_ since **1.0.3**] Example to disable this plugin completely in certain page using url:

	add_filter('lazy_load_excluded_pages', function ($excludedPages) {
	    $excludedPages[] = '/page-1';
	    $excludedPages[] = '/page-2';
	    return $excludedPages;
	});


[_new_ since **1.0.3**] Example to disable this plugin completely in certain page using page ID:

	add_filter('lazy_load_excluded_page_ids', function ($excludedPageIDs) {
	    $excludedPageIDs[] = 3678; // Exclude page with ID 1
	    $excludedPageIDs[] = 3615; // Exclude page with ID 2
	    return $excludedPageIDs;
	});
	
[_new_ since **1.0.4**] To disable the <noscript> tag:

	add_filter('disable_noscript', '__return_true');
	
&nbsp;
## USEFUL PLUGINS TO OPTIMIZE YOUR SITE'S SPEED:

- **[Optimize More!](https://wordpress.org/plugins/optimize-more/)**: A Do It Yourself WordPress Optimization Plugin that give you the ability to:
 - **Load CSS Asynchronously** - selectively load CSS file(s) asynchronously on selected post/page types.

 - **Delay CSS and JS until User Interaction** - selectively delay CSS/JS load until user interaction on selected post/page types.

 - **Preload Critical CSS, JS, and Font Files** - selectively preload critical CSS/JS/Font file(s) on selected post/page types.

 - **Remove Unused CSS and JS Files** - selectively remove unused CSS/JS file(s) on selected post/page types.

 - **Defer JS** - selectively defer loading JavaScript file(s) on selected post/page types.

 - **Advance Defer JS** - hold JavaScripts load until everything else has been loaded. Adapted from the legendary **varvy's defer js** method _*recommended for defer loading 3rd party scripts like ads, pixels, and trackers_

 - **Load Gutenberg CSS conditionally** 

 - **Remove Passive Listener Warnings**
 - and many more

## Other USEFUL PLUGIN:

- **[Shop Extra](https://wordpress.org/plugins/shop-extra/)** - A lightweight plugin to optimize your WooCommerce & Business site that makes you able to:
 - add Floating WhatsApp Chat Widget (can be use without WooCommerce),
 - add WhatsApp Order Button for WooCommrece,
 - Hide/Disable WooCommerce Elements,
 - WooCommerce Strings Translations,
 - and many more.

- **[Animate on Scroll](https://wordpress.org/plugins/animate-on-scroll/)** - Animate any Elements on scroll using the popular AOS JS library simply by adding class names. This plugin helps you integrate easily with AOS JS library to add any AOS animations to WordPress. Simply add the desired AOS animation to your element class name with "aos-" prefix and the plugin will add the corresponding aos attribute to the element tag.

## Optimize More!

Need to optimize more? Try my **[WordPress Page Speed Optimization's Service](https://thinkdigital.co.id/services/speed-optimization/)**.

== Installation ==

#### From within WordPress

1. Visit `Plugins > Add New`
1. Search for `Lazyload, Preload, and more!`
1. Activate Lazyload, Preload, and more! from your Plugins page

#### Manually

1. Download the plugin using the download link in this WordPress plugins repository
1. Upload `lazyload-preload-and-more` folder to your `/wp-content/plugins/` directory
1. Activate Lazyload, Preload, and more! plugin from your Plugins page


== Frequently Asked Questions ==

= How to enable lazy loading CSS background images?  =

Lazy loading css background images requires some effort from your end. Add an extra `lazyload` class to each container which has css background image in your favorite page editor.

= Preload featured images not working? =

It calls images set as featured image in the native WordPress post/pages, using `get_post_thumbnail_id()` and `wp_get_attachment_image_src()`. Make sure you already adds them.

If you are using Elementor or other Page's builders, simply edit the pages with the native WordPress editor to set the featured image.

= This plugin preload the wrong image size in my post? =

By default, this plugin will be grab the url and preload the `full` image size and `woocommerce_single` for WooCommerce single product pages. You can change that using filter if your theme is uses different `image size`. See example plugin description.

== Screenshots ==


== Changelog ==

= 1.0.4 =

- Better codes to read svg images dimension
- Add fetch priority to images
- New filter to disable <noscript> tag (see plugin's description)

= 1.0.3 =

- Add **missing image dimension** feature. This will automatically add image width and height to images that doesn’t have width and height attribute
- Introduce new filters to customize this plugin configuration (see plugin's description)

= 1.0.2 =

- Bug fix: add js pattern to lazyload video poster images
- Add filter to disable preload featured images

= 1.0.1 =

- Revised readme file

= 1.0.0 =

- Initial release