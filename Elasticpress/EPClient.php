<?php
namespace Elasticpress;

use \Elastica\Client,
    \Elastica\Request,
    \Elastica\Document,
    \Elastica\Script,
    \Elastica\Status;

class EPClient {
    private $client, $posts_index;
    private static $instances = array();

    const INDEX_PATTERN = 'ep_%s_v%s';
    const INDEX_ALIAS_PATTERN = 'ep_%s';
    const EXTRA_INDEX_PATTERN = 'ep_%s_extra';

    static private function set_default_options($opts = array()){
        if(!isset($opts['servers']))
            $opts['servers'] = EPPlugin::$es_servers;
        return $opts;
    }

    private function __construct($opts){
        $opts = self::set_default_options($opts);

        $client_settings = array('servers' =>$opts['servers']);
        $this->client = new Client($client_settings);

        $this->posts_index = $new_index = $this->ensure_index();

        $this->extra_index = $this->ensure_extra_index();
        EPMapper::set_extra_index_mappings($this->extra_index);
        
        //$opts['drop'] = true; //DEBUG
        $drop_old = ( isset($opts['drop']) && $opts['drop'] );
        $this->elect_index( $new_index, $drop_old );
    }

    public static function build_opts_hash($opts = array()){
        return md5(serialize($opts));
    }

    public static function has_instance($opts = array()){
        $opts = self::set_default_options($opts);
        $key = self::build_opts_hash($opts);
        
        return isset(self::$instances[$key]);
    }

    public static function get_instance($opts = array(), $force = False){
        $opts = self::set_default_options($opts);
        $key = self::build_opts_hash($opts);

        if(!isset(self::$instances[$key]) || $force)
            self::$instances[$key] = new self($opts);

        return self::$instances[$key];
    }

    public static function build_settings($filter = null){
        $settings = array();
        
        $settings['analysis'] = array_merge_recursive( isset($settings['analysis'])?$settings['analysis']:array(), self::build_settings_analysis() );

        return $settings;
    }

    protected static function build_settings_common_analysis(){
        $analysis = array();
        $common_path = EPPlugin::$paths['settings'].'analysis_common.php';

        $analysis = array_merge_recursive($analysis, require($common_path) );
        
        return $analysis;
    }

    protected static function build_settings_analysis(){
        $analysis = self::build_settings_common_analysis();
        
        $analyzer_langs = array('it', 'en');
        foreach($analyzer_langs as $l){
            $analysis = array_merge_recursive( $analysis, require(EPPlugin::$paths['settings'].sprintf('analysis_%s.php', $l) ) );
        }

        return $analysis;
    }

    public static function get_index_name($version = null){
        if(!$version)
            $version = get_option(EP_INDEX_VERSION_OPT_KEY, 1);
        return strtolower(sprintf(self::INDEX_PATTERN,  EPPlugin::$current_site, $version));
    }


    public function get_next_index_name(){
        $version = (int)get_option(EP_INDEX_VERSION_OPT_KEY, 1);
        return self::get_index_name($version+1);
    }

    public static function update_index_version($version = null){
        if(!$version)
            $version = ((int)get_option(EP_INDEX_VERSION_OPT_KEY, 1) + 1);
        update_option(EP_INDEX_VERSION_OPT_KEY, $version);
    }

    private static function get_index_alias(){
        return strtolower(sprintf(self::INDEX_ALIAS_PATTERN,  EPPlugin::$current_site));
    }

    public static function get_extra_index_name(){
        return strtolower(sprintf(self::EXTRA_INDEX_PATTERN,  EPPlugin::$current_site));
    }

    public function ensure_index($name = null, $settings = null){
        if(!$this->client)
            return null;

        if(!$name)
            $name = self::get_index_name();
        if(!$settings)
            $settings = $settings = self::build_settings();

        $index = $this->client->getIndex($name);
        if(!$index->exists()){
            //\TFramework\Utils::debug($settings);
            $index->create($settings);
        }

        return $index;
    }


    public function ensure_extra_index(){
        if(!$this->client)
            return null;

        $index = $this->client->getIndex(self::get_extra_index_name());
        if(!$index->exists()){
            $extra_settings = array('analysis' => self::build_settings_common_analysis() );
            $index->create($extra_settings);
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

    public function delete_indexes( $options = array() ){
        if(!$this->client)
            return null;

        $delete_extra = isset($options['delete_extra']) && $options['delete_extra'];

        $status = new Status($this->client);
        $indexes = $status->getIndexNames();
        $pattern = self::get_index_name('%d');

        $filtered_indexes = array_filter($indexes, function($i) use($pattern,$indexes) {
            $match = sscanf($i,$pattern);
            return( isset($match[0]) );
        });

        foreach($filtered_indexes as $in){
            $index = $this->client->getIndex($in);
            if($index)
                $index->delete();
        }

        if($delete_extra){
            $extra = $this->get_extra_index();
            $extra->delete();
        }

        return $filtered_indexes;
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

                $new_docs = array();
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

    public function get_extra_index(){
        return $this->extra_index;
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

    public function add_extra_doc($extra_type, $data){
        $extra_class = EPMapper::get_extra($extra_type);
        $es_type = $extra_class::$es_type;
        $type = $this->extra_index->getType($es_type);

        $doc_id = $extra_class::build_doc_id($data);
        $document = $extra_class::build_document($data);

        $script_spec = $extra_class::build_script($data);

        if($script_spec){
            try{
                $script = new Script($script_spec);
                $script->setUpsert($document);
                $script->setId($doc_id);
                $type->updateDocument($script);
            }
            catch(\Exception $e){
                //Don't care
            }
        }
        else{
            $document->setDocAsUpsert(true);
            $type->updateDocument($document);
        }
      
        $this->extra_index->refresh();
    }

    public function clean_extra_docs($extra_type){
        $extra_class = EPMapper::get_extra($extra_type);
        $es_type = $extra_class::$es_type;
        $extra_index = self::get_extra_index();

        $type = $extra_index->getType($es_type);
        $docs = $this->find_extra_docs($extra_type);

        foreach($docs as $d){
            try{
                $type->deleteById($d['_id']);
            }
            catch(\Exception $e){
                if($e instanceof \Elastica\Exception\NotFoundException){
                }
                else throw $e;
            }
        }

        $extra_index->refresh();
    }
    
    public function find_extra_docs($extra_type, $options = array()){
        $extra_class = EPMapper::get_extra($extra_type);

        $es_type = $extra_class::$es_type;

        $size = isset($options['size']) && is_numeric($options['size']) ? (int)$options['size'] : null;
        
        $path = self::get_extra_index_name() . '/' . $es_type . '/_search';

        $query = array();
        if($size)
            $query['size'] = $size;

        try{
            $response = $this->client->request($path, Request::GET, $query);
        }
        catch(\Exception $e){
            if($e instanceof \Elastica\Exception\PartialShardFailureException){
                $response = $e->getResponse();
            }
            else
                $response = null;
        }

        if($response){
            $response_array = $response->getData();
            return $response_array['hits']['hits'];
        }
        return array();
    }

    public function query_posts($query_str, $options = array()){
        $field_queries = apply_filters('ep_get_query_fields', EPMapper::get_default_query_fields(), '\Elasticpress\EPMapper');
        
        $type = isset($options['type']) ? $options['type'] : null;
        $sort = isset($options['sort']) ? $options['sort'] : null;
        $size = isset($options['size']) && is_numeric($options['size']) ? (int)$options['size'] : null;

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
                    'lenient' => true
                )
            );

            if($boost!=1)
                $match[$matchname]['boost'] = $boost;

            $queries[] = array('match' => $match);
        }

        $query = array(
            '_source'=> array(
                'exclude'=> $source_excludes
            ),
            'query'=> array(
                'dis_max' => array(
                    'queries' => $queries,
                    'tie_breaker' => 0.2
                )
            )
        );

        if($sort)
            $query['sort'] = $sort;
        else if(!$type)
            $query['sort'] = array('post_type', '_score');
        else{
            $query['sort'] = apply_filters('ep_get_query_sort', array('_score'));
            if(is_string($type) || (is_array($type) && sizeof($type) == 1)){
                $singletype = is_string($type) ? $type : $type[0];
                $query['sort'] = apply_filters("ep_get_{$singletype}_query_sort", $query['sort']);
            }
        }

        if($size)
            $query['size'] = $size;
        
        $entities = apply_filters('ep_query',  array('query' => $query, 'options' => $options));
        $query = $entities['query'];
        $options = $entities['options'];

        if(!$type)
            $type = EPMapper::get_known_types();
        
        if(is_array($type))
            $type = implode(',', $type);

        $path = self::get_index_alias() . '/' . $type . '/_search';
        if(isset($options['search_type'])){
            $path = add_query_arg( array('search_type' => $options['search_type']), $path );
        }
        $path = apply_filters('ep_query_path',  $path);
        // debug($path, $query);

        try{
            $response = $this->client->request($path, Request::GET, $query);
        }
        catch(\Exception $e){
            if($e instanceof \Elastica\Exception\PartialShardFailureException){
                $response = $e->getResponse();
            }
            else{
                //debug($e->getMessage());
                $response = null;
            }
        }
        return $response;
    }

    public function count_posts($options = array()){
        $type = EPMapper::get_known_types();
        $type = implode(',', $type);

        $path = self::get_index_alias() . '/' . $type . '/_search';

        $query = array(
            '_source'=> false
        );
        try{
            $response = $this->client->request($path, Request::GET, $query);
        }
        catch(\Exception $e){
            if($e instanceof \Elastica\Exception\PartialShardFailureException){
                $response = $e->getResponse();
            }
            else{
                $response = null;
            }
        }
      
        return $response;
    }

    public function query_history($options = array()){
        $type = ExtraDoc\EPExtra_History::$es_type;

        $size = isset($options['size']) && is_numeric($options['size']) ? (int)$options['size'] : null;
        
        $path = self::get_extra_index_name() . '/' . $type . '/_search';

        $query = array(
            'sort' => array(
                'count' => array('order' => 'desc')
            )
        );

        if($size)
            $query['size'] = $size;

        try{
            $response = $this->client->request($path, Request::GET, $query);
        }
        catch(\Exception $e){
            if($e instanceof \Elastica\Exception\PartialShardFailureException){
                $response = $e->getResponse();
            }
            else
                $response = null;
        }
        return $response;
    }

    public function query_extra($options = array()){

        $size = isset($options['size']) && is_numeric($options['size']) ? (int)$options['size'] : null;
        $count_only = isset($options['search_type']) && $options['search_type']==='count';
        
        $path = self::get_extra_index_name() . '/_search';
        if($count_only)
            $path.='?search_type=count';

        $query = array(
            "aggs" => array(
                "aggregate_by_type" => array(
                    "filters" => array(
                        "filters" => array(
                            "history" => array(
                                "type" => array("value" => ExtraDoc\EPExtra_History::$es_type) 
                            ),
                            "preset" => array(
                                "type" => array("value" => ExtraDoc\EPExtra_Preset::$es_type) 
                            )
                        )
                    )
                )
            )
        );
       
        if($size)
            $query['size'] = $size;

        try{
            $response = $this->client->request($path, Request::GET, $query);
        }
        catch(\Exception $e){
            if($e instanceof \Elastica\Exception\PartialShardFailureException){
                $response = $e->getResponse();
            }
            else
                $response = null;
        }

        return $response;
    }



    public function query_suggestions($query_str, $options = array()){
        $type = isset($options['type']) ? $options['type'] : null;
        $size = isset($options['size']) && is_numeric($options['size']) ? (int)$options['size'] : 10;   
        $extra_suggestions = isset($options['extra_types']) && is_array($options['extra_types']) ? $options['extra_types'] : array();

        $fuzziness_field = 2;
        $fuzziness_autocomplete = 1;
        $prefix_length = 1;

        $query = array(
            '_source'=> array(
                'include'=> [ 'post_title', 'query_string' ]
            ),
            'query'=> array(
                'bool' => array(
                    'should' => [
                        array(
                            'match' => array(
                                'post_title' => array(
                                    'query' => $query_str,
                                    'fuzziness' => $fuzziness_field,
                                    'prefix_length' => $prefix_length
                                )
                            )
                        ),
                        array(
                            'match' => array(
                                'post_title.autocomplete' => array(
                                    'query' => $query_str,
                                    'fuzziness' => $fuzziness_autocomplete,
                                    'prefix_length' => $prefix_length
                                )
                            )
                        )
                    ]
                )
            ),
            'highlight' => array(
                "pre_tags" => array('<strong class="tt-highlight">'),
                "post_tags" => array("</strong>"),
                "fields" => array(
                    "post_title.autocomplete" => array(
                        "force_source" => false
                    )
                )
            ),
            'size' => $size
        );

        $extra_es_types = array();

        foreach($extra_suggestions as $extra_type){
            $extra_class = EPMapper::get_extra($extra_type);
            $extra_es_types[] = $extra_class::$es_type;

            $query['query']['bool']['should'][] = array(
                'filtered'=>array( 
                    'query'=>array(
                        'function_score' => array(
                            'query'=>array(
                                'match' => array(
                                    $extra_class::$main_field.'.autocomplete' => array(
                                        'query' => $query_str,
                                        'fuzziness' => $fuzziness_autocomplete,
                                        'prefix_length' => $prefix_length
                                    )
                                )
                            ),
                            'field_value_factor' => array(
                                'field' => 'extra_score',
                                'modifier' => 'log1p'
                            )
                        )
                    ),
                    'filter' => array(
                        'exists' => array(
                            'field' => 'extra_score'
                        )
                    )
                )
            );

            $query['highlight']['fields'][$extra_class::$main_field.'.autocomplete'] = array( "force_source" => false );
        }

        // $query = array(
        //     'query'=> array(
        //         'match' => array(
        //             'post_title' => array(
        //                 'query' => $query_str,
        //             )
        //         )
        //     ),
        //     'suggest'=> array(
        //         "text"=> $query_str,
        //         'simple_phrase' => array(
        //             'phrase' => array(
        //                 'field' => 'post_title.autocomplete',
        //                 'size' => 5,
        //                 "real_word_error_likelihood" => 0.95,
        //                 "max_errors" => 0.5,
        //                 "gram_size" => 2,
        //                 "highlight"=> array(
        //                   "pre_tag"=> "<em>",
        //                   "post_tag"=> "</em>"
        //                 )
        //             )
        //         )
        //     )
        // );

        if(!$type)
            $type = EPMapper::get_known_types();
        
        if(is_array($type))
            $type = implode(',', $type);

        //Add extra doc types
        $type.= ','.implode(',',$extra_es_types);

        $path = self::get_index_alias(). ',' . self::get_extra_index_name() . '/' . $type . '/_search';
        if(isset($options['search_type'])){
            $path = add_query_arg( array('search_type' => $options['search_type']), $path );
        }

        try{
            $response = $this->client->request($path, Request::GET, $query);
        }
        catch(\Exception $e){
            if($e instanceof \Elastica\Exception\PartialShardFailureException){
                $response = $e->getResponse();
            }
            else
                $response = null;
        }
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