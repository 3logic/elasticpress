<?php
namespace Elasticpress;

class EPMapper_ACF_base {

	public static function get_textual_value($post_ob, $field_spec){
		$raw_value = $post_ob->{$field_spec['name']};
		$value_type = EPMapper::VALUE_TYPE_TEXT;
		return EPMapper::get_value($raw_value, $value_type);
	}

}