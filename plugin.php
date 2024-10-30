<?php	
/**
* Plugin Name: Lazyload, Preload, and More!
* Description: A drop dead simple and lightweight image, iframe, and video optimization plugin to satisfy Google PageSpeed Insights and Core Web Vitals.
* Author: Arya Dhiratara
* Author URI: https://dhiratara.me/
* Version: 1.0.4
* License: GPLv2 or later
* License URI: http://www.gnu.org/licenses/gpl-2.0.html
* Text Domain: dh_lp
*/

namespace LazyLoadPreload;

if (!defined('ABSPATH')) exit; // Exit if accessed directly

define('LPAM_NAME', 'Lazyload, Preload, and more!');
define('LPAM_VERSION', '1.0.4');
define("LPAM_VENDOR_URL", plugin_dir_url(__FILE__) . 'vendor/');
define("LPAM_CLASSES_DIR", plugin_dir_path(__FILE__) . 'includes/');


include_once(LPAM_CLASSES_DIR . 'init.php');