<?php
namespace Elasticpress;

use TFramework\Ajax\AjaxHelper;

class EPAjaxController {

    public static function check_request_for_api_key(){
        return ( isset($_REQUEST['api_key']) && $_REQUEST['api_key'] === EP_API_KEY );
    }

    public static function ajax_do_query(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_test($result);

        if($result['success']){
            $query_result = null;
            if( isset($_REQUEST['s'])){
                $options = array();
                if( isset($_REQUEST['size']))
                    $options['size'] = (int)$_REQUEST['size'];
                if( isset($_REQUEST['sort']))
                    $options['sort'] = (string)$_REQUEST['sort'];
                if( isset($_REQUEST['type']))
                    $options['type'] = (string)$_REQUEST['type'];
                $query_result = EPPlugin::perform_ep_query($_REQUEST['s'], $options);
            }
            $result['result'] = $query_result;
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_suggest(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_test($result);

        if($result['success']){
            $query_result = null;
            if( isset($_REQUEST['s'])){
                $options = array();
                if(isset($_REQUEST['size']))
                    $options['size'] = (int)$_REQUEST['size'];
                if( isset($_REQUEST['type']))
                    $options['type'] = (string)$_REQUEST['type'];
                $query_result = EPPlugin::perform_ep_suggestion($_REQUEST['s'], $options);
            }

            $result['result'] = $query_result;
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_history(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_test($result);

        if($result['success']){
            $query_result = null;
            
            $options = array();
            if( isset($_REQUEST['size']))
                $options['size'] = (int)$_REQUEST['size'];

            $query_result = EPPlugin::perform_ep_history($options);

            $result['result'] = $query_result;
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_status(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_apikey($result);
        $result = self::ajax_filter_test($result);

        if($result['success']){
            $status_result = EPPlugin::perform_ep_status();
            $result['status'] =  $status_result;
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_index_posts(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_test($result);

        if($result['success']){
            $execute_in_background = array_key_exists('foreground', $_GET) && $_GET['foreground'] == 'true' ? false : true ;
            if( isset($_REQUEST['post_ids']) && is_array($_REQUEST['post_ids']) )
                $index_result = EPPlugin::index_posts($_REQUEST['post_ids'], $execute_in_background);

            $result = array_merge_recursive($baseresult,$index_result);
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_index_all_posts(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_apikey($result);
        $result = self::ajax_filter_test($result);
        
        if($result['success']){
            $execute_in_background = array_key_exists('foreground', $_GET) && $_GET['foreground'] == 'true' ? false : true ;
            $index_result = EPPlugin::index_all_posts($execute_in_background);

            $result = array_merge_recursive($baseresult,$index_result);
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_reindex(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_apikey($result);

        $request = $_REQUEST;
        $request['tests'] = array('connection');
        $result = self::ajax_filter_test($result, $request);
        
        if($result['success']){

            if( isset($_REQUEST['from_index']) )
                $from_index = (int)$_REQUEST['from_index'];
            else if( isset($_REQUEST['index_version']) && is_numeric($_REQUEST['index_version']))
                $from_index = (int)$_REQUEST['index_version'];
            else
                $from_index = null;

            if( class_exists('\Elasticpress\Job\ReindexJob') ){
                $job = EPPlugin::$job_manager->create_job( '\Elasticpress\Job\ReindexJob', array(
                    'blog_id' => get_current_blog_id(),
                    'from_index' => $from_index
                ));
                $result = $baseresult;
                $result['job_id'] = $job->get_id();
            }
            else
                $result['success'] = false;
            
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_clean_all(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_apikey($result);
        
        $clean_extra = isset($_REQUEST['clean_extra']) && ($_REQUEST['clean_extra']) && ($_REQUEST['clean_extra']!=='false');
        $epclient = EPClient::get_instance();

        try{
            $epclient->delete_indexes(array('delete_extra' => $clean_extra));
        }
        catch(\Exception $e){
            $result['success'] = false;
        }     

        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_test(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_test($result,$_REQUEST);
        AjaxHelper::print_json_result($result);
    }

    protected static function ajax_filter_apikey($result, $request=null){
        if(!$result['success'])
            return $result;

        if(! self::check_request_for_api_key() ){
            $result['success'] = false;
            $result['message'] = 'Action Needs an API key';

            AjaxHelper::print_json_result($result);
        }

        return $result;
    }

    protected static function ajax_filter_test($result, $request=null){
        if(!$result['success'])
            return $result;
        $servers = (isset($request) && isset($request['servers'])) ? $request['servers'] : null;
        $tests = (isset($request) && isset($request['tests'])) ? $request['tests'] : null;

        $test = EPPlugin::test( $tests, array('connection_servers'=>$servers) );
        //die($test);
        $status_string = _('Unspecified error');
        switch($test){
            case EPPlugin::TEST_OK:
                $status_string = _('Ok');
                break;
            case EPPlugin::TEST_CANT_CONNECT:
                $status_string = _('Can\'t connect to Elasticsearch server');
                break;
            case EPPlugin::TEST_CANT_SEND_MAPPING:
                $status_string = _('Error in mapping submission');
                break;
            default:
        } 

        $result['success'] = ($test === EPPlugin::TEST_OK);
        $result['status']  = $test;
        $result['message'] = $status_string; 
        //$result['servers'] = json_encode($servers); 
        return $result;
    }
}