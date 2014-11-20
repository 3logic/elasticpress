<?php
namespace Elasticpress;

use \Elastica\Client,
	\Elastica\Request;

class EPClient {
	private $client, $posts_index;
	private static $instance;

	const INDEX_PATTERN = 'ep_%s_%s';
	const INDEX_ALIAS_PATTERN = 'ep_%s';

	public static function get_instance($opts = []){
		if(!self::$instance)
			self::$instance = new self($opts);

		return self::$instance;
	}

	public static function build_settings($filter = null){
		$settings = array(); //$this->posts_index->getSettings();
		
		$settings['analysis'] = array_merge_recursive( isset($settings['analysis'])?$settings['analysis']:array(), self::build_settings_analysis() );

		return $settings;
	}

	protected static function build_settings_analysis(){
		$analysis = array();
		$analysis = array_merge_recursive( $analysis, require_once (sprintf('%s../settings/analysis_common.php', plugin_dir_path( __FILE__ ) ) ) );
		
		$analyzer_langs = array('it', 'en');
		foreach($analyzer_langs as $l){
			$analysis = array_merge_recursive( $analysis, require_once (sprintf('%s../settings/analysis_%s.php', plugin_dir_path( __FILE__ ), $l) ) );
		}
		return $analysis;
	}

	public function get_index_name($version = null){
		if(!$version)
			$version = get_option(EP_INDEX_VERSION_OPT_KEY, 1);
		return strtolower(sprintf(self::INDEX_PATTERN,  EPPlugin::$current_site, $version));
	}

	public function get_next_index_name(){
		$version = (int)get_option(EP_INDEX_VERSION_OPT_KEY, 1);
		return $this->get_index_name($version+1);
	}

	private static function get_index_alias(){
		return strtolower(sprintf(self::INDEX_ALIAS_PATTERN,  EPPlugin::$current_site));
	}

	private function __construct($opts){
		$this->client = new Client();
		$this->posts_index = $this->client->getIndex($this->get_index_name());
		//$opts['drop'] = true; //DEBUG

		if( isset($opts['drop']) && $opts['drop'] ){
			try{
				$this->posts_index->delete(); 
			}
			catch(\Exception $e){}
			// $this->client->getIndex(self::POSTINDEX_ALIAS)->delete();
		}

		$this->posts_index = $this->ensure_index();
	}

	public function ensure_index($name = null, $alias = null, $settings = null){
		if(!$name)
			$name = $settings = $this->get_index_name();
		if(!$settings)
			$settings = $settings = self::build_settings();
		if(!$alias)
			$alias = self::get_index_alias();

		$index = $this->client->getIndex($name);
		if(!$this->posts_index->exists()){
			//\TFramework\Utils::debug($settings);
			$index->create($settings);
			$index->addAlias($alias);
		}

		return $index;
	}

	public function reindex($size = 10){

		$query = array(
			'query'=> array(
				"match_all" => array() 
			),
			'size' => $size
		);

		$path = $this->posts_index->getName() . '/_search?scroll=10m&search_type=scan';

		try{

			$response = $this->client->request($path, Request::GET, $query);
			$responseArray = $response->getData();

			do{
				$scroll_id = $responseArray['_scroll_id'];

				$scrollpath = '_search/scroll?scroll=10m';
				$response = $this->client->request($scrollpath, Request::GET, $scroll_id);
				$responseArray = $response->getData();
				print_r('<br>');
				print_r('nuovo scroll');
				foreach($responseArray['hits']['hits'] as $h){
					print_r('<br>');
					print_r($h['_source']);
					print_r('<br>');
				}
				
			} while ($responseArray['hits']['hits']);
			

			die('<br>finito');
		}
		catch(\Exception $e){
			die($e->getMessage());
		}

	}



	public function get_posts_index(){
		return $this->posts_index;
	}

	public function add_post($posts){
		if(!is_array($posts)){
			$post = $posts;
			$posts = array($post);
		}
		
		foreach($posts as $lpost){
			if($lpost->post_status == 'publish'){
				$posttype = $lpost->post_type;
				$type = $this->posts_index->getType($posttype);
				$type->addDocument(EPMapper::build_document_from_post($lpost));
			}
		}
		$this->posts_index->refresh();
	}

	public function query_posts($query_str, $type = null){
		$field_queries = apply_filters('ep_get_query_fields',array());  
		
		$queries = array();
		foreach($field_queries as $f){

			$boost = (isset($f['boost'])) ? (float)$f['boost'] : 1.0;

			$match = array(
				$f['name'] => array(
					'query' => $query_str,
					'boost' => $boost,
					'minimum_should_match'=> '40%',
					'lenient' => true
				)
			);

			$queries[] = array('match' => $match);
		}
		
		// $queries = array_slice($queries, 0, 3);

		$query = array(
			'query'=> array(
				'dis_max' => array(
					'queries' => $queries,
					'tie_breaker' => 0.2,
				)
			)
		);

		if(!$type)
			$query['sort'] = array('post_type', '_score');
		else
			$query['sort'] = apply_filters('ep_get_query_sort', array('_score'));
		//die(print_r(json_encode($query),true));

		if(!$type)
			$type = EPMapper::get_known_types();
		
		if(is_array($type))
			$type = implode(',', $type);

		$path = $this->posts_index->getName() . '/' . $type . '/_search';

		$response = $this->client->request($path, Request::GET, $query);
		//print_r($response);
		return $response;
	}

	public function remove_post($posts){
		if(!is_array($posts)){
			$post = $posts;
			$posts = array($post);
		}
		foreach($posts as $k => $lpost){
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

	public function analyze($str, $opts=array()){
		return $this->posts_index->analyze($str,$opts);
	}

}