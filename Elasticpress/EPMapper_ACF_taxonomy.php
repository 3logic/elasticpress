<?php
namespace Elasticpress;

class EPMapper_ACF_taxonomy extends EPMapper_ACF_base {

	public static function get_textual_value($post_ob, $field_spec){
		$raw_value = $post_ob->{$field_spec['name']};

		if(!is_array($raw_value))
			return "";

		$text_value = "";
		$args = array(
		    'hide_empty'        => false, 
		    'fields'            => 'all', 
		); 

		$terms = get_terms($field_spec['taxonomy'], $args);
		$text_pieces = [];
		foreach($terms as $t){
			$term_id = $t->term_id;
			$term_text = $t->name;
			if(in_array($term_id, $raw_value)){
				$text_pieces[] = $term_text;
			}
		}

		$text_value = implode(' + ', $text_pieces);
		return $text_value;
	}
	
}