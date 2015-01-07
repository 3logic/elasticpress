<?php
/**
 * Plugin Name: ElasticPress
 * Plugin URI: http://www.3logic.it/elasticpress
 * Description: Power wordpress search with Elasticsearch
 * Version: 99.1.0
 * Author: 3logic
 * Author URI: http://www.3logic.it
 * License: GPL2
 */

if(!defined('EP_CLIENT_OPT_KEY'))
    define('EP_CLIENT_OPT_KEY', 'ep_client_options');
if(!defined('EP_LANG_OPT_KEY'))
	define('EP_LANG_OPT_KEY', 'ep_lang');
if(!defined('EP_INDEX_SITE_ID_OPT_KEY'))
	define('EP_INDEX_SITE_ID_OPT_KEY', 'ep_index_site_id');
if(!defined('EP_INDEX_VERSION_OPT_KEY'))
	define('EP_INDEX_VERSION_OPT_KEY', 'ep_index_version');
if(!defined('EP_CONF_OPT_KEY'))
    define('EP_CONF_OPT_KEY', 'ep_plugin_configuration');

if(!defined('EP_AJAX_BASENAME'))
    define('EP_AJAX_BASENAME', 'lawyers_search');

if(!defined('EP_API_KEY'))
    define('EP_API_KEY', 'O8Fq3rYX3Lsf8kgxFinipGcRV6uzHBzhtYz9mQd8Rknrzt8tme1a9bvaOuqmKJ');

if(!defined('EP_TRANSLATION_KEY'))
    define('EP_TRANSLATION_KEY', 'elasticpress');

if(!defined('EP_POST_QUERY_DEFAULT_SIZE'))
    define('EP_POST_QUERY_DEFAULT_SIZE', 100);

require 'vendor/autoload.php';

// AJAX
use Elasticpress\EPPlugin,
    TFramework\Ajax\AjaxHelper;

$ajax_helper = new AjaxHelper(EP_AJAX_BASENAME,__FILE__);
EPPlugin::register_ajax_actions($ajax_helper);
$ajax_helper->enable();

// Admin hooks
add_action('admin_menu',            array('\Elasticpress\Admin\EPAdminPage', 'ep_plugin_menu'          ) );
add_action('admin_init',            array('\Elasticpress\Admin\EPAdminPage', 'ep_plugin_admin_init'    ) );
add_action('admin_enqueue_scripts', array('\Elasticpress\Admin\EPAdminPage', 'ep_plugin_admin_scripts' ) );

// // Plugin activaction hooks
register_activation_hook( __FILE__, array('Elasticpress\EPPlugin','activate') );
register_deactivation_hook( __FILE__, array('Elasticpress\EPPlugin','deactivate') );

add_action('init', array('Elasticpress\EPPlugin', 'init') );

add_action('wp_loaded', array('Elasticpress\EPPlugin', 'on_wp_loaded') );
