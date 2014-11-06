<?php
namespace Elasticpress;

class EPPlugin{

	public static $results;

	public static function hooked_save_post( $post_id, $post, $update ) {
		if ( wp_is_post_revision( $post_id ) )
			return;

		$epclient = EPClient::get_instance();
		$epclient->add_post($post);
	}


	public static function filter_query( $request ) {
	    $dummy_query = new \WP_Query();  // the query isn't run if we don't pass any query vars
	    $dummy_query->parse_query( $request );
		
	    if ( $dummy_query->is_search() ){
	    	$epclient = EPClient::get_instance();
	    	$response = $epclient->query_posts($request['s']);
	    	$responseArray = $response->getData();

	    	self::$results = $responseArray['hits']['hits'];
	    }

	    return $request;
	}
	

	public static function replace_search_template( $template ) {
		if ( is_search() ) {
			$esearch_path = plugin_dir_path( __FILE__ ).'/../templates/esearch.php';
			$new_template = locate_template( array( 'esearch.php' ) );
			if ( '' != $new_template ) {
				return $new_template ;
			}
			else {
				$results = self::$results;
				require($esearch_path);
				$template = '';
			}
		}

		return $template;
	}

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
    	add_filter( 'request', array(get_called_class(),'filter_query'), 10, 3);
		add_filter( 'template_include', array(get_called_class(),'replace_search_template'), 99 );

    	//This is called here to construct our singleton with the correct options
    	$clientopts = []; //Array('drop'=>1);
    	EPClient::get_instance($clientopts);

    	EPMapper::put_mappings();
    }
}