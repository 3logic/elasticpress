<?php
namespace Elasticpress;

class EPPlugin{

    public static $latest_search, $latest_results;

    private static function perform_equery($str, $type = null ){
        $epclient = EPClient::get_instance();
        
        //DEBUG
        print_r( $epclient->analyze($str, array('field'=>'post_title')) );

        $response = $epclient->query_posts($str, $type);
        $responseArray = $response->getData();

        self::$latest_search = array('string' => $str, 'type' => $type);
        self::$latest_results = $responseArray['hits']['hits'];
    }

    public static function hooked_save_post( $post_id, $post, $update ) {
        if ( wp_is_post_revision( $post_id ) )
            return;

        $epclient = EPClient::get_instance();
        $epclient->add_post($post);
    }

    // public static function elasticsearch_on_search( $request ) {
    //     $dummy_query = new \WP_Query();  // the query isn't run if we don't pass any query vars
    //     $dummy_query->parse_query( $request );
        
    //     if ( $dummy_query->is_search() ){
    //         $search_str = $request['s'];
    //         self::perform_equery($search_str);
    //     }
    //     return $request;
    // }

    public static function restrict_query_to_esearch_results( $wpquery ) {
        if ( $wpquery->is_search() ){
            $search_str = $wpquery->query['s'];

            $type = (isset($wpquery->query['post_type'])) ? $wpquery->query['post_type'] : null;
            self::perform_equery($search_str,$type);
            
            $ids = [];
            foreach(self::$latest_results as $r){
                $ids[] = $r['_id'];
            }
            if(sizeof($ids)){
                $wpquery->set('post__in',$ids);
                $wpquery->set('orderby','post__in');
                // this actually breaks get_search_query() in templates. Too bad...
                // we fix it with the restore_query_string filter func
                $wpquery->set('s', null); 
                $wpquery->query = array(); 
            }
            else {
                $wpquery->set('post__in',[0]);
            }
        }
    }

    public static function restore_query_string($search_query){
        return self::$latest_search['string'];
    }
    

    // public static function replace_search_template( $template ) {
    //     if ( is_search() ) {
    //         $esearch_path = plugin_dir_path( __FILE__ ).'/../templates/esearch.php';
    //         $new_template = locate_template( array( 'esearch.php' ) );
    //         if ( '' != $new_template ) {
    //             return $new_template ;
    //         }
    //         else {
    //             $template = $esearch_path;
    //         }
    //     }

    //     return $template;
    // }

    /**
     * Hook plugin activation
     */
    public static function activate(){
    }
    /**
     * Hook plugin deactivation
     */
    public static function deactivate(){
    }

    public static function init(){
        add_action( 'save_post', array(get_called_class(),'hooked_save_post'), 10, 3);
        //add_filter( 'request', array(get_called_class(),'elasticsearch_on_search'), 10, 3);
        add_action( 'pre_get_posts', array(get_called_class(),'restrict_query_to_esearch_results'), 99 );
        add_filter( 'get_search_query', array(get_called_class(),'restore_query_string'), 99 );
        //add_filter( 'template_include', array(get_called_class(),'replace_search_template'), 99 );

        //This is called here to construct our singleton with the correct options
        $clientopts = []; //array('drop'=>1);
        EPClient::get_instance($clientopts);

        EPMapper::put_mappings();
    }
}