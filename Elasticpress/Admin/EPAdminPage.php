<?php
namespace Elasticpress\Admin;

define('EP_SETTINGS_GROUP_ID', 'options-ep_settings_group_id');
define('EP_SETTINGS_PAGE_ID', 'options-ep');

class EPAdminPage{
    public static function ep_plugin_menu() {
        add_menu_page( 'Elasticpress Options', 'Elasticpress', 'manage_options', EP_SETTINGS_PAGE_ID, array('\Elasticpress\Admin\EPAdminPage','ep_options') );
    }

    public static function ep_plugin_admin_init(){
        register_setting( EP_SETTINGS_GROUP_ID, EP_CLIENT_OPT_KEY,  array(get_called_class(),'sanitize_servers') );
        register_setting( EP_SETTINGS_GROUP_ID, EP_LANG_OPT_KEY,    array(get_called_class(),'sanitize_lang') );
        //register_setting( EP_SETTINGS_GROUP_ID, EP_INDEX_SITE_ID_OPT_KEY, array(get_called_class(),'sanitize_site_id') );
        
        add_settings_section('es_section', 'Elasticsearch Options',  array(get_called_class(),'print_section_text'), EP_SETTINGS_PAGE_ID);

        add_settings_field('servers', 'Server Addresses', array(get_called_class(),'print_plugin_setting_servers'), EP_SETTINGS_PAGE_ID, 'es_section');
        add_settings_field('lang',    'Language',         array(get_called_class(),'print_plugin_setting_lang'),    EP_SETTINGS_PAGE_ID, 'es_section');
        add_settings_field('site_id', 'Site Index',       array(get_called_class(),'print_plugin_setting_site_id'), EP_SETTINGS_PAGE_ID, 'es_section');
        
        //register_setting( EP_SETTINGS_GROUP_ID, EP_INDEX_VERSION_OPT_KEY, array(get_called_class(),'sanitize_mapping_v') );
        
        add_settings_section('maintenance_section', 'Maintenance',  array(get_called_class(),'print_section_text'), EP_SETTINGS_PAGE_ID);
        
        add_settings_field('mapping_v', 'Mapping Version',            array(get_called_class(),'print_plugin_setting_mapping_v'), EP_SETTINGS_PAGE_ID, 'maintenance_section');
    }

    public static function ep_plugin_admin_scripts($hook) {
        if ( $hook !== 'toplevel_page_'.EP_SETTINGS_PAGE_ID ) {
            return;
        }

        $JS_FOLDER_PATH = plugin_dir_url( __FILE__ ) . '../../js/';
        wp_enqueue_script( 'ep_admin_ko',   $JS_FOLDER_PATH. 'knockout-3.2.0.js' );
        wp_enqueue_script( 'ep_admin_main', $JS_FOLDER_PATH. 'adminscript.js' );
    }

	public static function ep_options() {
        if ( !current_user_can( 'manage_options' ) )  {
            wp_die( __( 'You do not have sufficient permissions to access this page.' ) );
        }
        if(sizeof(get_settings_errors())==1 && isset($_GET['settings-updated'])){
            add_settings_error('global','updated','Settings updated','updated');
            settings_errors('global');
        }
        ?>

<div id="ep_settings_wrapper" >
    <h2><?php echo __('Elasticpress plugin') ?></h2>
    <?php echo __('Options relating to the Elasticpress Plugin') ?>
    <?php $ajax_url = sprintf('%s/ajax/%s/', get_site_url(), EP_AJAX_BASENAME); ?>
    <form id="ep_settings_form" action="options.php" method="post" data-ajax-url="<?php echo $ajax_url; ?>">
        <?php 
            settings_fields(EP_SETTINGS_GROUP_ID); 
            do_settings_sections(EP_SETTINGS_PAGE_ID); 
        ?>
        <input name="Submit" type="submit" value="<?php esc_attr_e('Save Changes'); ?>" />
    </form>
</div>

        <?php
        //require \Elasticpress\EPPlugin::$paths['templates'].'/adminpage.php';
    }

    static function print_section_text($data) {
        switch($data['id']){
            case 'es_section':
                $description = 'Elasticsearch server settings';
                break;
            default:
                $description = null;
        }
        if($description)
            echo "<p>$description</p>";
    }

    static function print_plugin_setting_site_id($data) {
        $option = get_option(EP_INDEX_SITE_ID_OPT_KEY);

        $value = is_string($option) ? esc_attr($option) : '';
        echo "<input id='plugin_site_id' name='".esc_attr(EP_INDEX_SITE_ID_OPT_KEY)."' size='40' type='text' value='{$value}' disabled />";
        settings_errors( EP_INDEX_SITE_ID_OPT_KEY );
    }

    static function print_plugin_setting_lang($data) {
        $option = get_option(EP_LANG_OPT_KEY);

        $value = is_string($option) ? esc_attr($option) : '';
        echo "<input id='plugin_lang' name='".esc_attr(EP_LANG_OPT_KEY)."' size='5' type='text' value='{$option}' disabled />";
        settings_errors( EP_LANG_OPT_KEY );
    }

    static function print_plugin_setting_servers($data) {
        $IMG_FOLDER_PATH = plugin_dir_url( __FILE__ ) . '../../assets/img/';

        $options = get_option(EP_CLIENT_OPT_KEY);
        $servers = isset($options['servers']) ? $options['servers'] : array();
        $value = esc_attr(implode(', ', 
            array_map(function($h){
                if(!isset($h['host']))
                    return null;
                $port = isset($h['port']) ? $h['port'] : 9200;  
                return sprintf('%s:%d', $h['host'], $port);
            }, $servers) 
        ));
        echo "<input id='plugin_servers' name='".esc_attr(EP_CLIENT_OPT_KEY)."' size='40' type='textarea' value='{$value}' />"; 
        echo "<input type='button' value='"._('Test')."' data-bind='enable:server_test_permitted,click:do_server_test'></input>";
        echo "&nbsp;<img src='{$IMG_FOLDER_PATH}yes.png' data-bind='visible:server_test_status()==STATUSES.ok'><img src='{$IMG_FOLDER_PATH}no.png' data-bind='visible:server_test_status()==STATUSES.ko'>";
        echo "<p class='description'>host1:port1, host2:port2 ...<p>";
        settings_errors( EP_CLIENT_OPT_KEY );   
    }

    static function print_plugin_setting_mapping_v($data) {
        $IMG_FOLDER_PATH = plugin_dir_url( __FILE__ ) . '../../assets/img/';
        
        $option = get_option(EP_INDEX_VERSION_OPT_KEY);

        $value = is_string($option) ? esc_attr($option) : '';
        echo "<input id='plugin_mapping_v' name='".esc_attr(EP_INDEX_VERSION_OPT_KEY)."' size='5' type='number' value='{$option}' disabled />";
        echo "&nbsp;<img src='{$IMG_FOLDER_PATH}yes.png' data-bind='visible:mapping_test_status()==STATUSES.ok'><img src='{$IMG_FOLDER_PATH}no.png' data-bind='visible:mapping_test_status()==STATUSES.ko'>";
        settings_errors( EP_INDEX_VERSION_OPT_KEY );
    }

    static function sanitize_site_id($site_id_string) {
        if(!trim($site_id_string))
            add_settings_error(EP_INDEX_SITE_ID_OPT_KEY,'not_empty','Site Id can\'t be empty');
        return $site_id_string;
    }

    static function sanitize_servers($servers_string) {
        $sane_servers = array();
        if(!is_string($servers_string))
            return $sane_servers;

        $servers = explode(',', $servers_string);
        foreach($servers as $s){
            $match = array(null, null, 9200);
            preg_match('/^\s*(\d{1,3}\.\d{1,3}\.\d{1,3}\.\d{1,3})(?::(\d{1,5}))?\s*\z/', $s, $match);
            if($match)
                $sane_servers[] = array('host'=>$match[1], 'port'=>($match[2]?$match[2]:9200) );
        }
        if(!sizeof($sane_servers))
            add_settings_error(EP_CLIENT_OPT_KEY,'not_empty','List of servers can\'t be empty');
        return array('servers'=>$sane_servers);
    }

    static function sanitize_site_lang($lang_string) {
        if(!trim($lang_string))
            add_settings_error(EP_LANG_OPT_KEY,'not_empty','Site Id can\'t be empty');
        return $lang_string;
    }

    static function sanitize_mapping_v($mapping_v_string) {
        if(!trim($mapping_v_string)){
            add_settings_error(EP_INDEX_VERSION_OPT_KEY,'not_empty','Mapping version can\'t be empty');
            $mapping_v_string = get_option(EP_INDEX_VERSION_OPT_KEY);
        }
        return $mapping_v_string;
    }
}

