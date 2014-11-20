<?php
namespace Elasticpress;

class EPMapper_ACF {
	public static $mapped_types = array('text', 'taxonomy', 'wysiwyg', 'relation_simple');

	public static function build_acf_mapping($field_spec){
		if(!in_array($field_spec['type'], static::$mapped_types))
			throw new Exception('ACF type "{$field_spec}" can\'t be indexed');

		if($field_spec['type'] == 'wysiwyg')
			return EPMapper::$PROP_HTMLTEXT_CURRENTLANG;
		return EPMapper::$PROP_TEXT_CURRENTLANG;
	}

	public static function get_acf_value($post_ob, $field_spec){
		if(!in_array($field_spec['type'], static::$mapped_types))
			throw new Exception('ACF type "{$field_spec}" can\'t be indexed');
		
		$delegate_class = sprintf('\Elasticpress\EPMapper_ACF_%s', $field_spec['type']);

		if(!class_exists($delegate_class))
			$delegate_class = '\Elasticpress\EPMapper_ACF_base';

		return $delegate_class::get_textual_value($post_ob, $field_spec);	
	}

}