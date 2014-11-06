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

$loader = require 'vendor/autoload.php';
$loader->add('Elasticpress',plugin_dir_path( __FILE__ ));

use Elasticpress\EPPlugin;

// // Admin hooks
// add_action( 'admin_enqueue_scripts', array('TBroadcast\Admin\Panel', 'enqueue_scripts') );
// add_action( 'add_meta_boxes', array('TBroadcast\Admin\Panel', 'add_meta_box'));

// // Plugin activaction hooks
register_activation_hook( __FILE__, array('Elasticpress\EPPlugin','activate') );
register_deactivation_hook( __FILE__, array('Elasticpress\EPPlugin','deactivate') );

Elasticpress\EPPlugin::init();