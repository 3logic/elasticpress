<?php
namespace Elasticpress;

use \Elastica\Client,
	\Elastica\Request;

class EPClient{
	private $client, $posts_index;
	private static $instance;

	const POSTINDEX = 'posts';

	public static function get_instance($opts = []){
		if(!self::$instance)
			self::$instance = new EPClient($opts);

		return self::$instance;
	}

	private function __construct($opts){
		$this->client = new Client();
		$this->posts_index = $this->client->getIndex(self::POSTINDEX);

		if( isset($opts['drop']) && $opts['drop'] )
			$this->posts_index->delete();
		if( !($this->posts_index->exists()) ){
			$this->posts_index->create(array());		
		}
	}

	public function get_posts_index(){
		return $this->posts_index;
	}

	public function add_post($posts){
		if(!is_array($posts)){
			$post = $posts;
			$posts = Array($post);
		}
		
		foreach($posts as $lpost){
			$posttype = $lpost->post_type;
			$type = $this->posts_index->getType($posttype);
			$type->addDocument(EPMapper::map_post($lpost));
		}
		$this->posts_index->refresh();
	}

	public function query_posts($query_str){
		$query = array(
		    'query' => array(
		        'query_string' => array(
		            'query' => $query_str
		        )
		    )
		);

		$path = $this->posts_index->getName() . '/_search';
		$response = $this->client->request($path, Request::GET, $query);

		return $response;
	}

	public function remove_post($posts){
		if(!is_array($posts)){
			$post = $posts;
			$posts = Array($post);
		}
		foreach($posts as $k=>$lpost){
			$posttype = $lpost->post_type;
			$type = $this->posts_index->getType($posttype);
			try{
				$type->deleteById($lpost->ID);
			}
			catch(\Exception $e){
				if($e instanceof \Elastica\Exception\NotFoundException){
					//nothing to do
				}
				else throw $e;
			}
		}
	}

}