<?php
/**
 * Plugin Name: ElasticPress
 * Plugin URI: http://www.3logic.it/elasticpress
 * Description: Power wordpress search with Elasticsearch
 * Version: 0.1.0
 * Author: 3logic
 * Author URI: http://www.3logic.it
 * License: GPL2
 */

if(!defined('EP_LANG_OPT_KEY'))
	define('EP_LANG_OPT_KEY', 'ep_lang');
if(!defined('EP_INDEX_VERSION_OPT_KEY'))
	define('EP_INDEX_VERSION_OPT_KEY', 'ep_index_version');

require 'vendor/autoload.php';

use Elasticpress\EPPlugin;

// // Admin hooks
// add_action( 'admin_enqueue_scripts', array('TBroadcast\Admin\Panel', 'enqueue_scripts') );
// add_action( 'add_meta_boxes', array('TBroadcast\Admin\Panel', 'add_meta_box'));

// // Plugin activaction hooks
register_activation_hook( __FILE__, array('Elasticpress\EPPlugin','activate') );
register_deactivation_hook( __FILE__, array('Elasticpress\EPPlugin','deactivate') );

add_action('init', array('Elasticpress\EPPlugin', 'init') );

add_action('wp_loaded', array('Elasticpress\EPPlugin', 'on_wp_loaded') );
