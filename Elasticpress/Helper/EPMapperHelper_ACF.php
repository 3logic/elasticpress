<?php
namespace Elasticpress\Helper;

class EPMapperHelper_ACF {
	public static $mapperclass;

	public static $mapped_acf_types = array('text', 'textarea', 'taxonomy', 'wysiwyg', 'relation_simple', 'relationship', 'file');


	public static function get_acf_class($field_spec){
		if(!in_array($field_spec['type'], static::$mapped_acf_types))
			throw new \Exception('ACF type "{$field_spec}" can\'t be indexed');
		
		$delegate_class = sprintf('\Elasticpress\ACF\EPMapper_ACF_%s', $field_spec['type']);

		if(!class_exists($delegate_class))
			$delegate_class = '\Elasticpress\ACF\EPMapper_ACF_base';	

		$delegate_class::$mapperclass = self::$mapperclass;

		return $delegate_class;
	}

	public static function build_acf_mapping($field_spec){
		$delegate_class = static::get_acf_class($field_spec);

		return $delegate_class::get_acf_mapping($field_spec);	
	}

	public static function get_acf_value($post_ob, $field_spec){	
		$delegate_class = static::get_acf_class($field_spec);
		$value = $delegate_class::get_document_value($post_ob, $field_spec);
		if(is_null($value))
			$value = '';
		return $value;	
	}

}