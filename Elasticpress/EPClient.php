<?php
namespace Elasticpress;

use \Elastica\Client,
    \Elastica\Request;

class EPClient{
    private $client, $posts_index;
    private static $instance;

    const POSTINDEX = 'posts_1';
    const POSTINDEX_ALIAS = 'posts';

    public static function get_instance($opts = []){
        if(!self::$instance)
            self::$instance = new self($opts);

        return self::$instance;
    }

    public static function build_settings($lang='it'){
        $included = require_once (sprintf('%s../settings/index_settings_%s.php', plugin_dir_path( __FILE__ ), $lang) );

        $settings = array(); //$this->posts_index->getSettings();
        $settings['analysis'] = array( 'filter'=>array(), 'analyzer'=>array() );
        $settings['analysis']['filter'] = array_merge( $settings['analysis']['filter'], $included['filter']);
        $settings['analysis']['analyzer'] = $included['analyzer'];
        return $settings;
    }

    private function __construct($opts){
        $this->client = new Client();
        $this->posts_index = $this->client->getIndex(self::POSTINDEX);

        //$opts['drop'] = true; //DEBUG

        if( isset($opts['drop']) && $opts['drop'] ){
            try{
                $this->posts_index->delete(); 
            }
            catch(\Exception $e){}
            // $this->client->getIndex(self::POSTINDEX_ALIAS)->delete();
        }
        if( !($this->posts_index->exists()) ){
            $index_opts = self::build_settings();

            $this->posts_index->create($index_opts);
            $this->posts_index->addAlias(self::POSTINDEX_ALIAS);
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
            $posttype = $lpost->post_type;
            $type = $this->posts_index->getType($posttype);
            $type->addDocument(EPMapper::map_post($lpost));
        }
        $this->posts_index->refresh();
    }

    public function query_posts($query_str, $type = null){
        $query = array(
            'query' => array(
                'query_string' => array(
                    'query' => $query_str
                )
            )
        );

        if($type){
            $path = $this->posts_index->getName() . '/' . $type . '/_search';
        }
        else
            $path = $this->posts_index->getName() . '/_search';

        $response = $this->client->request($path, Request::GET, $query);
        return $response;
    }

    public function remove_post($posts){
        if(!is_array($posts)){
            $post = $posts;
            $posts = array($post);
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

    public function analyze($str, $opts=array()){
        return $this->posts_index->analyze($str,$opts);
    }

}