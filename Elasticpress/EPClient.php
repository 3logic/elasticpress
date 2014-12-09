<?php
namespace Elasticpress;

use \Elastica\Client,
	\Elastica\Request;

class EPClient {
	private $client, $posts_index;
	private static $instances = array();

	const INDEX_PATTERN = 'ep_%s_v%s';
	const INDEX_ALIAS_PATTERN = 'ep_%s';

	static private function set_default_options($opts = array()){
		if(!isset($opts['servers']))
			$opts['servers'] = EPPlugin::$es_servers;
		return $opts;
	}

	private function __construct($opts){
		$opts = self::set_default_options($opts);

		$client_settings = array('servers' =>$opts['servers']);

		$this->client = new Client($client_settings);

		$this->posts_index = $this->client->getIndex($this->get_index_name());
		
		//$opts['drop'] = true; //DEBUG
		$drop_old = ( isset($opts['drop']) && $opts['drop'] );

		$new_index = $this->ensure_index();
		$this->elect_index( $new_index, $drop_old );
	}

	public static function has_instance($opts = array()){
		$opts = self::set_default_options($opts);
		$key = md5(serialize($opts));
		
		return isset(self::$instances[$key]);
	}

	public static function get_instance($opts = array()){
		$opts = self::set_default_options($opts);
		$key = md5(serialize($opts));

		if(!isset(self::$instances[$key]))
			self::$instances[$key] = new self($opts);

		return self::$instances[$key];
	}

	public static function build_settings($filter = null){
		$settings = array();
		
		$settings['analysis'] = array_merge_recursive( isset($settings['analysis'])?$settings['analysis']:array(), self::build_settings_analysis() );

		return $settings;
	}

	protected static function build_settings_analysis(){
		$analysis = array();
		$common_path = EPPlugin::$paths['settings'].'analysis_common.php';

		$analysis = array_merge_recursive($analysis, require($common_path) );
		
		$analyzer_langs = array('it', 'en');
		foreach($analyzer_langs as $l){
			$analysis = array_merge_recursive( $analysis, require(EPPlugin::$paths['settings'].sprintf('analysis_%s.php', $l) ) );
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

	public static function update_index_version($version = null){
		if(!$version)
			$version = ((int)get_option(EP_INDEX_VERSION_OPT_KEY, 1) + 1);
		update_option(EP_INDEX_VERSION_OPT_KEY, $version);
	}

	private static function get_index_alias(){
		return strtolower(sprintf(self::INDEX_ALIAS_PATTERN,  EPPlugin::$current_site));
	}

	public function ensure_index($name = null, $settings = null){
		if(!$this->client)
			return null;

		if(!$name)
			$name = $this->get_index_name();
		if(!$settings)
			$settings = $settings = self::build_settings();

		$index = $this->client->getIndex($name);
		if(!$index->exists()){
			//\TFramework\Utils::debug($settings);
			$index->create($settings);
		}

		return $index;
	}

	public function elect_index($index = null, $drop_old = false){
		if(is_string($index) || is_null($index)){
			$index = $this->ensure_index($index);
		}

		if(is_numeric($index) && ((int)$index===$index) ){
			self::update_index_version($index);
			$index = $this->ensure_index();
		}

		if($this->posts_index){
			try{
				$this->posts_index->removeAlias(self::get_index_alias());
			}
			catch(\Exception $e){}

			if($drop_old){
				try{ 
					$this->posts_index->delete(); 
				}
				catch(\Exception $e){}
			}
		}

		$this->posts_index = $index;
		$this->posts_index->addAlias(self::get_index_alias(), true);
		return $index;
	}

	public function copy_index_docs_to($new_index, $old_index = null, $size = 10){

		if(is_string($new_index))
			$new_index= $this->client->getIndex($new_index);

		if(!$old_index)
			$old_index = $this->posts_index;
		else if(is_string($old_index))
			$old_index= $this->client->getIndex($old_index);

		$query = array(
			'query'=> array(
				"match_all" => array() 
			),
			'size' => $size
		);

		$path = $old_index->getName() . '/_search?scroll=10m&search_type=scan';

		try{
			$response = $this->client->request($path, Request::GET, $query);
			$responseArray = $response->getData();

			do{
				$scroll_id = $responseArray['_scroll_id'];

				$scrollpath = '_search/scroll?scroll=10m';
				$response = $this->client->request($scrollpath, Request::GET, $scroll_id);
				$responseArray = $response->getData();

				$new_docs = [];
				foreach($responseArray['hits']['hits'] as $h){
					$estype = $h['_type'];
					if(!isset($new_docs[$estype]))
						$new_docs[$estype] = array();
					$new_docs[$estype][] = (new \Elastica\Document($h['_id'], $h['_source']) );
				}
				foreach($new_docs as $estype => $docs){
					$type = $new_index->getType($estype);
					$type->addDocuments($new_docs[$estype]);
				}
				
				$new_index->refresh();

			} while ($responseArray['hits']['hits']);

		}
		catch(\Exception $e){
			die($e->getMessage());
		}

	}

	public function reindex($mapperclass){
		$new_index_name = $this->get_next_index_name();
		$new_index = $this->ensure_index($new_index_name);
		$mapperclass::set_index_mappings($new_index, false);
		
		$this->copy_index_docs_to($new_index);

		$this->elect_index($new_index);
		
		self::update_index_version();
		$new_index->addAlias(self::get_index_alias(), true);

		return $new_index_name;
	}

	public function get_posts_index(){
		return $this->posts_index;
	}

	public function add_posts($posts){
		if(!is_array($posts)){
			$post = $posts;
			$posts = array($post);
		}
		
		foreach($posts as $lpost){
			if($lpost->post_status == 'publish'){
				$es_type = $lpost->post_type;
				$type = $this->posts_index->getType($es_type);
				$type->addDocuments(EPMapper::build_document_from_post($lpost));
			}
		}
		$this->posts_index->refresh();
	}

	public function query_posts($query_str, $type = null){
		$field_queries = apply_filters('ep_get_query_fields', EPMapper::get_default_query_fields());
		
		$queries = array();
		$source_excludes = array();

		foreach($field_queries as $f){

			$boost = (isset($f['boost'])) ? (float)$f['boost'] : 1.0;

			if(isset($f['source_exclude']) && $f['source_exclude'] )
				$source_excludes[] = $f['name'];

			$matchname = isset($f['qualified_name'])? $f['qualified_name']: $f['name'];
			$match = array(
				$matchname => array(
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
			'_source'=> array(
		        'exclude'=> $source_excludes
		    ),
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


		try{
			$response = $this->client->request($path, Request::GET, $query);
		}
		catch(\Exception $e){
			$response = null;
		}
		//print_r($response);
		return $response;
	}

	public function remove_post($posts){
		if(!is_array($posts)){
			$post = $posts;
			$posts = array($post);
		}
		foreach($posts as $k => $lpost){
			if(is_numeric($lpost))
				$lpost = get_post($lpost);

			$es_type = EPMapper::get_es_type($lpost);
			$type = $this->posts_index->getType($es_type);
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