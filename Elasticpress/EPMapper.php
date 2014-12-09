<?php
namespace Elasticpress;

use \Elastica\Document;

class EPMapper {
	const VALUE_TYPE_TEXT = 'val_type_text';
	const VALUE_TYPE_HTML = 'val_type_html';
	const VALUE_TYPE_INTEGER = 'val_type_integer';

	public static $did_init = false;

	protected static $helper_classes = array(
		'acf' => 'EPMapperHelper_ACF'
	);

	public static function get_helper($helper_name){
		if(self::$helper_classes[$helper_name]){
			$helper_class = sprintf("\\%s\\Helper\\%s",__NAMESPACE__,self::$helper_classes[$helper_name]);
			$helper_class::$mapperclass = get_called_class();
			return $helper_class;	
		}
		else
			throw new Exception( sprintf('Unknown plugin "%s"',$helper_name) );
	}

	public static function get_client(){
		return EPClient::get_instance();
	}

	public static $PROP_TEXT_NOTANALYZED = array( 
		'type' => 'string', 
		'analyzer' => 'custom_folded'
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

	public static $PROP_ATTACHMENT_CURRENTLANG = array( 
		'type' => 'attachment', 
		'index' => 'analyzed',
		'analyzer' => 'english'
	);

	protected static $current_lang_text_property;

	protected static function set_lang(){
		self::$PROP_TEXT_CURRENTLANG['analyzer']       = sprintf('custom_lang_%s', EPPlugin::$current_lang);
		self::$PROP_HTMLTEXT_CURRENTLANG['analyzer']   = sprintf('custom_html_lang_%s', EPPlugin::$current_lang);
		self::$PROP_ATTACHMENT_CURRENTLANG['analyzer'] = sprintf('custom_lang_%s', EPPlugin::$current_lang);
	}
	
	/**
	 * Returns the registered post types, along with corresponding elasticsearch types
	 * 
	 * @return [array] Array of known types, in the form: array('post_type'=>'es_type', ...)
	 */
	public static function get_known_types(){
		$base_types = array('post'=>'post', 'page'=>'page');
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
		$plugin_conf = EPPlugin::$conf;
		self::set_lang();
		self::set_index_mappings(null, $plugin_conf['do_reindex_on_map_fail']);
		self::$did_init = true;
	}

	public static function set_index_mappings($index = null, $can_reindex = false){
		$es_types = self::get_known_types();
		$type_mappings = self::get_type_mappings();
		//\TFramework\Utils::debug($type_mappings);
		$client = EPClient::get_instance();
		if(!$index)
			$index = $client->get_posts_index();

		$must_reindex = false;
		$mapping_exceptions = array();
		
		foreach($es_types as $t){
			$type = $index->getType($t);

			$mapping = new \Elastica\Type\Mapping();
			$mapping->setType($type);
			$mapping->setProperties($type_mappings[$t]['properties']);

			try{
				$mapping->send();
			}
			catch(\Exception $e){
				$mapping_exceptions[] = $e;
				$must_reindex = true;
			}
		}

		if($must_reindex){
			if($can_reindex)
				$client->reindex(get_called_class());
			else
				throw new \Exception('Could not reindex');
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

		$post_doc_data = array();
		foreach($common_fields as $pk=>$d){
			$post_doc_data[$d['name']] = self::get_value($post_ob->{$pk}, $d['type']);
		}
		$post_doc_data['post_type'] = $posttype;
		$post_doc_data['post_id'] = $post_ob->ID;

		$post_doc_data = apply_filters('ep_get_'.$posttype.'_document_data', apply_filters('ep_get_document_data',$post_doc_data, $post_ob, get_called_class()), $post_ob, get_called_class());
		
		$doc = new Document($post_ob->ID, $post_doc_data);
		//$attached_docs = apply_filters('ep_get_'.$posttype.'_attached_docs', array(), $post_ob, get_called_class());

		return array($doc);
	}

	public static function get_es_type($post_ob){
		return $post_ob->post_type;
	}

	public static function get_default_query_fields($template = null){
		if(!$template || !is_array($template)){
			$template = [
				array('name' => 'post_title', 'boost' => 2.0),
				array('name' => 'post_content'),
				array('name' => 'post_excerpt', 'boost' => 1.5)
			];
		}
		
		$es_types = self::get_known_types();

		foreach($es_types as $t){
			foreach($template as $ft){
				$ft['qualified_name'] = $t . '.' . $ft['name'];
				$default_query_fields[] = $ft;
			}
		}
		return $default_query_fields;
	}

	public static function get_value($raw_value, $value_type = null){
		if($value_type == self::VALUE_TYPE_HTML){
			return html_entity_decode( strip_tags($raw_value) );
		}
		return $raw_value;
	} 
}