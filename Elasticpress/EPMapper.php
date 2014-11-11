<?php
namespace Elasticpress;

use \Elastica\Document;

class EPMapper{
    const BASE_TYPE_NAME = 'post';

    private static $ITA_TEXT_PROPERTY = array( 
        'type' => 'string', 
        'analyzer' => 'custom_italian'
    );
    
    private static function get_mappings(){
        $common_props = array(
            'post_content' => self::$ITA_TEXT_PROPERTY,
            'post_title'   => self::$ITA_TEXT_PROPERTY,
            'post_excerpt' => self::$ITA_TEXT_PROPERTY
        );

        $mappings = array(
            'post'=>array(
                'properties' => $common_props
            )
        );

        return $mappings;
    }

    public static function put_mappings(){
        $mappings = self::get_mappings();

        $client = EPClient::get_instance();
        $type = $client->get_posts_index()->getType(self::BASE_TYPE_NAME);

        $mapping = new \Elastica\Type\Mapping();
        $mapping->setType($type);
        $mapping->setProperties($mappings[self::BASE_TYPE_NAME]['properties']);
        
        try{
            $mapping->send();
        }
        catch(\Exception $e){
            // TODO: handle mismatch / can't merge
        }

        //TODO: dynamic per-type mappings
    }

    public static function map_post($post_ob){
        $posttype = static::get_es_type($post_ob);

        /*
        * <post_ob_key> => <document_field_key> 
        */
        $common_fields = array(
            'post_content' => 'post_content',
            'post_title' => 'post_title',
            'post_excerpt' => 'post_excerpt',
            'post_type' => 'post_type'
        );

        $post_data = array();
        foreach($common_fields as $k=>$v){
            $post_data[$v] = $post_ob->{$k};
        }

        return new Document($post_ob->ID, $post_data);
    }

    public static function get_es_type($post_ob){
        return $post_ob->post_type;
    }
}