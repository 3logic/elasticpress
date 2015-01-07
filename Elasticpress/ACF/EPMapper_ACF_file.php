<?php
namespace Elasticpress\ACF;

use Elasticpress\EPMapper;
use \Elastica\Document;

class EPMapper_ACF_file extends EPMapper_ACF_base {

    public static function get_acf_mapping($field_spec){
        return EPMapper::$PROP_ATTACHMENT_CURRENTLANG;
    }

	public static function get_document_value($post_ob, $field_spec){

        if(isset($field_spec['save_format']) && $field_spec['save_format'] == 'id'){
            $attachment_id = get_field($field_spec['key'], $post_ob);

            if( $attachment_id ){
            $file_name = get_attached_file($attachment_id);
            if(!file_exists($file_name)){
                throw new \Exception("File for attachement $attachment_id does not exist.");
            }

            $data = file_get_contents($file_name);
            $value = base64_encode($data);
            return $value;
        }
            else
                return '';
        }
        else {
            // TODO
        }

        return '';
	}

}