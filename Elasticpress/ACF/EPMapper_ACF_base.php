<?php
namespace Elasticpress\ACF;

use Elasticpress\EPMapper;

class EPMapper_ACF_base {
    public static $mapperclass;
    
    public static function get_acf_mapping($field_spec){
        return EPMapper::$PROP_TEXT_CURRENTLANG;
    }

	public static function get_document_value($post_ob, $field_spec){
		$raw_value = $post_ob->{$field_spec['name']};
		$value_type = EPMapper::VALUE_TYPE_TEXT;
		return EPMapper::get_value($raw_value, $value_type);
	}

}