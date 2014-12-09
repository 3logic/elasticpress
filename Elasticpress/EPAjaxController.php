<?php
namespace Elasticpress;

use TFramework\Ajax\AjaxHelper;

class EPAjaxController {

    public static function check_request_for_api_key(){
        return ( isset($_REQUEST['api_key']) && $_REQUEST['api_key'] === EP_API_KEY );
    }

    public static function ajax_do_index_posts(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_test($result);

        if($result['success']){

            if( isset($_REQUEST['post_ids']) && is_array($_REQUEST['post_ids']) )
                $index_result = EPPlugin::index_posts($_REQUEST['post_ids'],true);

            $result = array_merge_recursive($baseresult,$index_result);
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_index_all_posts(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_apikey($result);
        $result = self::ajax_filter_test($result);
        
        if($result['success']){
            $index_result = EPPlugin::index_all_posts();

            $result = array_merge_recursive($baseresult,$index_result);
        }
        AjaxHelper::print_json_result($result);
    }

    public static function ajax_do_reindex(){
        $result = $baseresult = array('success'=>true);
        $result = self::ajax_filter_apikey($result);
        $result = self::ajax_filter_test($result);
        
        if($result['success']){

            if( isset($_REQUEST['index_version']) && is_numeric($_REQUEST['index_version']))
                $index_version = (int)$_REQUEST['index_version'];
            else
                $index_version = null;

            if( class_exists('\Elasticpress\Job\ReindexJob') ){
                EPPlugin::$job_manager->create_job( '\Elasticpress\Job\ReindexJob', array('index_version' => $index_version) );
            }

            $result = $baseresult;
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