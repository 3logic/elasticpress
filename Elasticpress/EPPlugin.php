<?php
namespace Elasticpress;

use TFramework\JobManager\JobManager;

class EPPlugin {
    const STATUS_META_KEY = '_ep_indexing_status';
    const STATUS_PENDING  = 'pending';
    const STATUS_INDEXING = 'indexing';
    const STATUS_INDEXED  = 'indexed';

    const TEST_OK = 'ok';
    const TEST_CANT_CONNECT = 'cant_connect';
    const TEST_CANT_SEND_MAPPING = 'cant_send_mapping';

    const START_TIMESTAMP_META_KEY = '_ep_last_indexing';

    const INDEXING_TIMEOUT_SECONDS = 60;

    const JOB_MANAGER_CONTEXT = 'elasticpress';
    
    public static $job_manager;

    public static $es_servers;

    public static $latest_search, $latest_results, $latest_error;

    public static $paths, $current_lang, $current_site, $conf;

    private static $test_state = null;


    public static function register_ajax_actions($ajax_helper){
        $actions = array('index_posts', 'index_all_posts', 'reindex', 'clean_all', 'test', 'query', 'suggest', 'history', 'status');

        foreach($actions as $a){
            $ajax_helper->register_path( $a, array(), array('Elasticpress\EPAjaxController',"ajax_do_$a") );
        }
    }

    public static function index_posts($post_ids = null, $background_job = false){
        $result = array(); 

        $updated = 0;
        if( isset($post_ids) && is_array($post_ids) )
            $updated = EPPostStatusAccessor::mark_indexed_posts_as_pending($post_ids);

        $stuck = EPPostStatusAccessor::mark_stuck_posts_as_pending();

        $result['marked']  = $updated + $stuck;
        $result['stuck']   = $stuck;
        if($background_job){
            if( class_exists('\Elasticpress\Job\PostIndexerJob') ){
                $job = self::$job_manager->create_job( '\Elasticpress\Job\PostIndexerJob', array(
                    'blog_id' => get_current_blog_id()
                ));
                $result['job_id'] = $job->get_id();
            }
        }
        else
            $result['indexed'] = EPPostStatusAccessor::index_pending_posts();

        return $result;
    }

    public static function index_all_posts($background_job = true){
        $result = array(); 

        $updated = EPPostStatusAccessor::mark_all_posts_as_pending();

        $result['marked']  = $updated;
        if($background_job){
            if( class_exists('\Elasticpress\Job\PostIndexerJob') ){
                $job = self::$job_manager->create_job( '\Elasticpress\Job\PostIndexerJob', array(
                    'blog_id' => get_current_blog_id()
                ));
                $result['job_id'] = $job->get_id();
            }
        }
        else
            $result['indexed'] = EPPostStatusAccessor::index_pending_posts();

        return $result;
    }

    public static function perform_ep_query($str, $options = array() ){
        $epclient = EPClient::get_instance();

        $use_as_latest = (!array_key_exists('ignore_as_latest',$options)  || $options['ignore_as_latest']  === false );
        $add_to_history= (!array_key_exists('ignore_as_history',$options) || $options['ignore_as_history'] === false );
        
        $latest_results = null;

        if(!isset($options['size']))
            $options['size'] = EP_POST_QUERY_DEFAULT_SIZE;

        $response = $epclient->query_posts($str, $options);
        if($response){
            $responseArray = $response->getData();
            self::$latest_error = $response->getData();
            $latest_results = $responseArray;
            if($use_as_latest){
                self::$latest_results = $latest_results;
            }
        }
        else{
            self::$latest_results = null;
        }

        if($use_as_latest){
            self::$latest_search = array('string' => $str, 'options' => $options);
        }

        if($add_to_history && $latest_results['hits']['total'] > 0 ){
            self::add_history_doc($str);
        }

        return $latest_results;
    }


    public static function perform_ep_suggestion($str, $options = array() ){
        $epclient = EPClient::get_instance();

        $extra_suggestions = isset($options['extra_types']) && is_array($options['extra_types']) ? $options['extra_types'] : array('history','preset');
        $options['extra_types'] = $extra_suggestions;

        $extra_classes = array();
        foreach ($extra_suggestions as $xs) {
            $extra_class = EPMapper::get_extra($xs);
            $extra_classes[$extra_class::$es_type] = $extra_class;
        }

        $response = $epclient->query_suggestions($str, $options);

        if($response){

            $responseArray = $response->getData();
            $hits = $responseArray['hits']['hits'];

            $results = array();
            foreach($hits as $h){
                $type = $h['_type'];
                $field_name = (isset($extra_classes[$type])) ? $extra_classes[$type]::$main_field : 'post_title';
                $results[] = array(
                    'score' => $h['_score'],
                    'type' => $type,
                    'suggestion' => $h['_source'][$field_name],
                    'highlight' => $h['highlight'][$field_name.'.autocomplete']
                );
            }
        }
        else
            $results = null;

        $results = apply_filters('ep_suggestions', $results);
        return $results;
    }

    public static function perform_ep_history($options = array() ){
        $epclient = EPClient::get_instance();
 
        $response = $epclient->query_history($options);
        if($response){
            $responseArray = $response->getData();
            $hits = $responseArray['hits']['hits'];

            $results = array();
            foreach($hits as $h){
                $field_name = 'query_string';
                $results[] = array(
                    'count' => $h['_source']['extra_score'],
                    'suggestion' => $h['_source'][$field_name]
                );
            }
        }
        else
            $results = null;

        $results = apply_filters('ep_history', $results);

        return $results;
    }

    public static function perform_ep_status($options = array() ){
        $epclient = EPClient::get_instance();

        //Document count
        $response = $epclient->count_posts($options);
        if($response){
            $response_array = $response->getData();
            $count = $response_array['hits']['total'];
        }
        else{
            $count = null;
        }

        //Extra count
        $options = array('search_type' => 'count');
        $response = $epclient->query_extra($options);
        if($response){
            $response_array = $response->getData();
            $extra_count = $response_array['hits']['total'];
            $buckets = $response_array['aggregations']['aggregate_by_type']['buckets'];
            $history_count = $buckets['history']['doc_count'];
            $preset_count = $buckets['preset']['doc_count'];
        }
        else{
            $extra_count = $history_count = $preset_count = null;
        }

        return array('document_count'=>$count, 'extra_count'=>$extra_count, 'history_count'=>$history_count, 'preset_count'=>$preset_count);
    }

    public static function hooked_save_post( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) )
            return;
        
        self::index_posts(array($post_id), true);

        // $epclient = EPClient::get_instance();
        // $epclient->add_posts($post);
    }

    public static function hooked_delete_post( $post_id ) {
        if ( wp_is_post_revision( $post_id ) )
            return;
        
        $epclient = EPClient::get_instance();
        $epclient->remove_post($post_id);
    }

    // public static function elasticsearch_on_search( $request ) {
    //     $dummy_query = new \WP_Query();  // the query isn't run if we don't pass any query vars
    //     $dummy_query->parse_query( $request );
        
    //     if ( $dummy_query->is_search() ){
    //         $search_str = $request['s'];
    //         self::perform_ep_query($search_str);
    //     }
    //     return $request;
    // }

    public static function restrict_query_to_esearch_results( $wpquery ) {
        if ( $wpquery->is_search() ){
            $search_str = $wpquery->query['s'];

            $options  = $_REQUEST;
            $options['type'] = (isset($wpquery->query['post_type'])) ? $wpquery->query['post_type'] : null;

            $options = apply_filters('ep_search_options', $options);

            self::perform_ep_query($search_str,$options);
            
            $ids = array();
            if(is_array(self::$latest_results) && array_key_exists('hits', self::$latest_results) && !empty(self::$latest_results['hits']['hits'])){
                foreach(self::$latest_results['hits']['hits'] as $r){
                    $ids[] = $r['_id'];
                }
            }
            if(sizeof($ids)){
                $wpquery->set('post__in',$ids);
                $wpquery->set('orderby','post__in');
                // this actually breaks get_search_query() in templates. Too bad...
                // we fix it with the restore_query_string filter func
                $wpquery->set('s', null); 
                //$wpquery->query = array();
            }
            else {
                $wpquery->set('post__in',[0]);
            }
        }
    }


    public static function add_history_doc($search_str){
        $epclient = EPClient::get_instance();

        $analyzer = EPMapper::$PROP_TEXT_NOTANALYZED['analyzer'];

        $analysis = $epclient->analyze($search_str,array('analyzer'=>$analyzer));
        $tokens = array_map(function($v){return isset($v['token'])?$v['token']:null;}, $analysis);
        asort($tokens);

        $tokens_hash = implode(' ', $tokens);

        $query_doc = array('timestamp' => time(), 'query_string' => $search_str, 'tokens' => $tokens_hash);
        $epclient->add_extra_doc('history', $query_doc);
    }

    public static function add_preset_doc($text){
        $epclient = EPClient::get_instance();

        $preset_doc = array('preset_text' => $text);
        $epclient->add_extra_doc('preset', $preset_doc);
    }

    public static function clean_preset_docs(){
        $epclient = EPClient::get_instance();
        
        return $epclient->clean_extra_docs('preset');
    }


    public static function restore_query_string($search_query){
        return self::$latest_search['string'];
    }
    

    public static function replace_search_template( $template ) {
        if ( is_search() ) {
            $esearch_path = plugin_dir_path( __FILE__ ).'/../templates/esearch.php';
            $new_template = locate_template( array( 'esearch.php' ) );
            if ( '' != $new_template ) {
                return $new_template ;
            }
            else {
                $template = $esearch_path;
            }
        }

        return $template;
    }

    /**
     * Hook plugin activation
     */
    public static function activate(){
        $job_manager = JobManager::get_instance(self::JOB_MANAGER_CONTEXT);
        $job_manager->activate();
    }
    /**
     * Hook plugin deactivation
     */
    public static function deactivate(){
        $job_manager = JobManager::get_instance(self::JOB_MANAGER_CONTEXT);
        $job_manager->deactivate();
    }

    public static function init(){
        self::init_paths();        
        
        // retrieve servers from options, if set
        // else, set it to a reasonable default
        $client_options = get_option(EP_CLIENT_OPT_KEY);
        $must_update_client_opts = false;
        if(!$client_options || !is_array($client_options))
            $client_options = array();
        if(!isset($client_options['servers']) || !is_array($client_options['servers']) || sizeof($client_options['servers'])==0){
            $client_options['servers'] = array(
                array('host'=>'127.0.0.1', 'port'=>9200)
            );
            $must_update_client_opts = true;
        }
        if($must_update_client_opts)
            update_option(EP_CLIENT_OPT_KEY, $client_options);
        self::$es_servers = $client_options['servers'];

        // retrieve language from options, if set
        // else, set it to a reasonable default
        $lang = get_option(EP_LANG_OPT_KEY);
        if(!$lang){
            $lang = substr(get_locale(),0,2);
            if(!$lang)
                $lang = 'en';
            update_option(EP_LANG_OPT_KEY, $lang);
        }
        self::$current_lang = $lang;

        // retrieve site id from options, if set
        // else, set it to a reasonable default
        $current_site = get_option(EP_INDEX_SITE_ID_OPT_KEY);
        if(!$current_site || !is_string($current_site)){
            $current_site = str_replace(
                array(' ','://',':','/','.'), 
                '_', 
                get_bloginfo('url').'_'.get_current_blog_id()
            );
            update_option(EP_INDEX_SITE_ID_OPT_KEY, $current_site);
        }
        self::$current_site = $current_site;

        // retrieve plugin behvaiour configuration from options, if set
        // else, set it to reasonable defaults
        $conf = self::set_defaults(get_option(EP_CONF_OPT_KEY));
        if(isset($conf['changed']) && $conf['changed']){
            $conf['changed'] = false;
            update_option(EP_CONF_OPT_KEY, $conf);
        }
        self::$conf = $conf;

        self::$job_manager = JobManager::get_instance(self::JOB_MANAGER_CONTEXT);

    }

    protected static function init_paths(){
        self::$paths = array(
            'settings' => sprintf('%s../settings/', plugin_dir_path( __FILE__ ) ),
            'templates' => sprintf('%s../templates/', plugin_dir_path( __FILE__ ) )
        );
    }

    public static function set_defaults($conf){

        if(!$conf || !is_array($conf)){
            $conf = array();
            $conf['changed'] = true;
        }

        if(!isset($conf['do_reindex_on_map_fail'])){
            $conf['do_reindex_on_map_fail'] = false;
            $conf['changed'] = true;
        }

        return $conf;
    }

    public static function on_wp_loaded(){
        //This must be done here so that the custom post type have registered themselves as known types
        self::init_late();

        if(self::test() !== self::TEST_OK)
            return;

        $called_class = get_called_class();
        add_action( 'save_post', array($called_class,'hooked_save_post'), 10, 3);
        add_action( 'delete_post', array($called_class,'hooked_delete_post'), 10, 1);
        //add_filter( 'request', array($called_class,'elasticsearch_on_search'), 10, 3);
        add_action( 'pre_get_posts', array($called_class,'restrict_query_to_esearch_results'), 99 );
        add_filter( 'get_search_query', array($called_class,'restore_query_string'), 99 );
        //add_filter( 'template_include', array($called_class,'replace_search_template'), 99 );
    }

    public static function init_late(){
        $clientopts = array(); //array('drop'=>1);
        try{
            //This is called here to construct our singleton with the correct options
            $epclient = EPClient::get_instance($clientopts,true);
        } catch(\Exception $e){
            return;
        }

        try{
            EPMapper::init();
        } catch(\Exception $e){
            return;
        }
    }


    public static function test( $tests = null, $options = array() ){
        if(!$tests)
            $tests = array('connection', 'mapping');

        $client_opts = array(
            'servers'=>(isset($options['connection_servers'])?$options['connection_servers']:null) 
        );

        if( in_array('connection', $tests) ){
            try{
                EPClient::get_instance($client_opts);
            } catch (\Exception $e){
                return self::TEST_CANT_CONNECT;
            }
        }
        if( in_array('mapping', $tests) && !EPMapper::$did_init )
            return self::TEST_CANT_SEND_MAPPING;

        return self::TEST_OK;
    }
    
}