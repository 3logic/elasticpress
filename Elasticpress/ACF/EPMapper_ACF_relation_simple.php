<?php
namespace Elasticpress\ACF;

class EPMapper_ACF_relation_simple extends EPMapper_ACF_base {

	public static function get_document_value($post_ob, $field_spec){
		$raw_value = $post_ob->{$field_spec['name']};

		$text_pieces = array();

		if($field_spec['return_format'] == 'id'){
			$ids = $raw_value;
			if(!is_array($ids) || !sizeof($ids))
				return '';

			$query = new \WP_Query(array(
				'post_type'      	=> $field_spec['post_type'],
				'posts_per_page'	=> -1,
				'post__in'		=> $ids,
				'post_status'		=> 'any'
			));

			foreach($query->posts as $p){
				$text_pieces[] = $p->post_title;
			}

			if(sizeof($text_pieces))
				return $text_pieces;
			else
				return null;
		}
		else {
			// TODO
		}

		return '';
	}
	
}