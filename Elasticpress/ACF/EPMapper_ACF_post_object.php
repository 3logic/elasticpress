<?php
namespace Elasticpress\ACF;

class EPMapper_ACF_post_object extends EPMapper_ACF_base {

	public static function get_document_value($post_ob, $field_spec){
		$raw_value = $post_ob->{$field_spec['name']};

		if($field_spec['return_format'] == 'id'){
			$post_id = $raw_value;
			$post = get_post($post_id);

			if($post)
				return $post->post_title;
			else
				return null;
		}
		else {
			// TODO
		}

		return '';
	}
	
}