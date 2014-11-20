<?php
namespace Elasticpress;

class EPMapper_ACF_relation_simple extends EPMapper_ACF_base {

	public static function get_textual_value($post_ob, $field_spec){
		$raw_value = $post_ob->{$field_spec['name']};
		
		$text_value = "";
		$text_pieces = [];

		if($field_spec['return_format'] == 'id'){
			$ids = $raw_value;

			$query = new \WP_Query(array(
				'post_type'      	=> $field_spec['post_type'],
				'posts_per_page'	=> -1,
				'post__in'		=> $ids,
				'post_status'		=> 'any'
			));

			foreach($query->posts as $p){
				$text_pieces[] = $p->post_title;
			}
		}
		else {
			// TODO
		}
		$text_value = implode(' + ', $text_pieces);
		return $text_value;
	}
	
}