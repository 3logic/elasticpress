<?php
namespace Elasticpress\ExtraDoc;

use \Elastica\Document,
    \Elastica\Script,
    \Elasticpress\EPMapper;

abstract class EPExtraDocument{
    public static $mapperclass;

    public static function get_mapping(){
        $mapping = array(
            "_ttl" => array (
                "enabled" => false
            ),
            "properties" => array(
                static::$main_field => array(
                    "type" => "multi_field",
                    "fields" => array(
                        static::$main_field => EPMapper::$PROP_TEXT_NOTANALYZED,
                        "autocomplete" => EPMapper::$PROP_AUTOCOMPLETE
                    )
                ),
                "extra_score" => EPMapper::$PROP_INTEGER_EXACT
            )
        );
        return $mapping;
    }

    public static $es_type;

    public static $main_field;

    public static function build_doc_id($data = array()){
        if(static::$main_field) 
            return md5($data[static::$main_field]);
        return md5(microtime());
    }

    public static function build_document($data = array()){
        if(!isset($data['extra_score']))
            $data['extra_score'] = 1;
        return new Document(static::build_doc_id($data), $data);
    }

    public static function build_script($data = array()){
        return null;
    }
}