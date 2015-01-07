<?php
namespace Elasticpress\ExtraDoc;

use \Elastica\Document,
    \Elasticpress\EPMapper;

if(!defined('EP_HISTORY_TTL'))
    define('EP_HISTORY_TTL', '30d');

class EPExtra_History extends EPExtraDocument{

    public static function get_mapping(){
        $parent_mapping = parent::get_mapping();

        $mapping = array_merge_recursive($parent_mapping, array(
            "_ttl" => array (
                "enabled" => true, 
                "default" => EP_HISTORY_TTL 
            ),
            "properties" => array(
                "tokens" => EPMapper::$PROP_TEXT_NOTANALYZED,
                "count" => EPMapper::$PROP_INTEGER_EXACT
            )
        ));
        return $mapping;
    }

    public static $es_type = 'ep_history';

    public static $main_field = 'query_string';

    public static function build_doc_id($data = array()){
        return md5($data['tokens']);
    }

    public static function build_document($data = array()){
        $data['count'] = 1;
        $data['extra_score'] = 1;
        return parent::build_document($data);
    }

    public static function build_script($data = array()){
        $script = 'ctx._source.count +=1;';
        $script.= 'ctx._source.extra_score +=1;';
        return $script;
    }
}