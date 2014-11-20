<?php
namespace Elasticpress;

use \Elastica\Document;

class EPMapper {
	const VALUE_TYPE_TEXT = 'val_type_text';
	const VALUE_TYPE_HTML = 'val_type_html';
	const VALUE_TYPE_INTEGER = 'val_type_integer';

	protected static $helper_classes = array(
		'acf' => 'EPMapper_ACF'
	);

	public static function get_helper($helper_name){
		if(self::$helper_classes[$helper_name]){
			$helper_class = sprintf("\\%s\\%s",__NAMESPACE__,self::$helper_classes[$helper_name]);
			return $helper_class;	
		}
		else
			throw new Exception( sprintf('Unknown plugin "%s"',$helper_name) );
	}

	public static $PROP_TEXT_NOTANALYZED = array( 
		'type' => 'string', 
		'index' => 'not_analyzed'
	);

	public static $PROP_TEXT_CURRENTLANG = array( 
		'type' => 'string', 
		'index' => 'analyzed',
		'analyzer' => 'english'
	);

	public static $PROP_HTMLTEXT_CURRENTLANG = array( 
		'type' => 'string', 
		'index' => 'analyzed',
		'analyzer' => 'english'
	);

	public static $PROP_INTEGER_EXACT = array( 
		'type' => 'long'
	);

	protected static $current_lang_text_property;

	protected static function set_lang(){
		self::$PROP_TEXT_CURRENTLANG['analyzer']     = sprintf('custom_lang_%s', EPPlugin::$current_lang);
		self::$PROP_HTMLTEXT_CURRENTLANG['analyzer'] = sprintf('custom_html_lang_%s', EPPlugin::$current_lang);
	}
	
	public static function get_known_types(){
		$base_types = array('post', 'page');
		$types = apply_filters('ep_get_known_types', $base_types);
		return $types;
	}

	protected static function get_one_type_mapping($typename){
		$common_props = array(
			'post_id'      => self::$PROP_INTEGER_EXACT,
			'post_content' => self::$PROP_HTMLTEXT_CURRENTLANG,
			'post_title'   => self::$PROP_TEXT_CURRENTLANG,
			'post_excerpt' => self::$PROP_HTMLTEXT_CURRENTLANG
		);

		$default_mapping = array(
			'_all' => array( 'enabled' => false ),
			'properties' => $common_props
		);

		return apply_filters('ep_get_'.$typename.'_type_mapping',
			$default_mapping,
			get_called_class()
		);
	}


	protected static function get_type_mappings($type_filter = null){
		$types = self::get_known_types();
		$mappings = array();

		foreach($types as $tn){
			$mappings[$tn] = self::get_one_type_mapping($tn);
		}
		//print_r($mappings);
		return apply_filters('ep_get_type_mappings',
			$mappings, 
			get_called_class()
		);
	}

	public static function init(){
		self::set_lang();
		self::set_index_mappings();
		add_filter('ep_get_query_fields', array(get_called_class(), 'get_default_query_fields'), 50, 1);
	}

	public static function set_index_mappings(){
		$es_types = self::get_known_types();
		$type_mappings = self::get_type_mappings();
		//\TFramework\Utils::debug($type_mappings);
		$client = EPClient::get_instance();

		foreach($es_types as $t){
			$type = $client->get_posts_index()->getType($t);

			$mapping = new \Elastica\Type\Mapping();
			$mapping->setType($type);
			$mapping->setProperties($type_mappings[$t]['properties']);

			try{
				$mapping->send();
			}
			catch(\Exception $e){
				$new_index = $client->ensure_index();
				$client->copy_to_index($mapping);
			}
		}
	}

	public static function build_document_from_post($post_ob){
		$posttype = static::get_es_type($post_ob);

		/*
		* <post_ob_key> => <document_field_key> 
		*/
		$common_fields = array(
			'post_id'      => array('name' => 'post_id',      'type' => self::VALUE_TYPE_INTEGER),
			'post_title'   => array('name' => 'post_title',   'type' => self::VALUE_TYPE_TEXT),
			'post_content' => array('name' => 'post_content', 'type' => self::VALUE_TYPE_HTML),
			'post_excerpt' => array('name' => 'post_excerpt', 'type' => self::VALUE_TYPE_HTML)
		);

		$doc_data = array();
		foreach($common_fields as $pk=>$d){
			$doc_data[$d['name']] = self::get_value($post_ob->{$pk}, $d['type']);
		}
		$doc_data['post_type'] = $posttype;
		$doc_data['post_id'] = $post_ob->ID;

		$doc_data = apply_filters('ep_get_'.$posttype.'_document_data', apply_filters('ep_get_document_data',$doc_data, $post_ob, get_called_class()), $post_ob, get_called_class());
		return new Document($post_ob->ID, $doc_data);
	}

	public static function get_es_type($post_ob){
		return $post_ob->post_type;
	}

	public static function get_default_query_fields($query_fields){
		$default_query_fields = [
			array('name' => 'post_title', 'boost' => 2.0),
			array('name' => 'post_content'),
			array('name' => 'post_excerpt', 'boost' => 1.5)
		];
		return array_merge_recursive($query_fields, $default_query_fields);
	}

	public static function get_value($raw_value, $value_type = null){
		if($value_type == self::VALUE_TYPE_HTML){
			return html_entity_decode( strip_tags($raw_value) );
		}
		return $raw_value;
	} 
}