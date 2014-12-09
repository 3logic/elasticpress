<?php
namespace Elasticpress;

class EPPostStatusAccessor {

    public static function get_posts_by_indexing_status($status, $opts = array() ){
        if(!is_array($status)){
            $status = array($status);
        }

        $meta_query = array();
        
        if( sizeof($status) > 1 )
            $meta_query['relation'] = 'OR';

        foreach($status as $s){
            $meta_query[] = array(
                'key' => EPPlugin::STATUS_META_KEY,
                'value' => $s,
                'compare' => '='
            );
        }

        if( in_array(EPPlugin::STATUS_INDEXED, $status) ){
            $meta_query['relation'] = 'OR';
            $meta_query[] = array(
                'key'     => EPPlugin::STATUS_META_KEY,
                'value'   => 'dummy',
                'compare' => 'NOT EXISTS'
            );
        }

        $args = array(
            'post_type' => array_keys(EPMapper::get_known_types()),
            'posts_per_page'  => -1,
            'orderby'         => 'post_date',
            'order'           => 'DESC',
            'post_status'     => 'publish',
            'meta_query'      => $meta_query
        );

        $args = array_merge_recursive($args, $opts);

        $posts = get_posts($args);

        return $posts;
    }

    public static function set_post_indexing_status($post_id, $status){
        //print_r('set:'.$post_id.' '.$status.'<br>');
        update_post_meta($post_id, EPPlugin::STATUS_META_KEY, $status);
        if($status === EPPlugin::STATUS_INDEXING)
            update_post_meta($post_id, EPPlugin::START_TIMESTAMP_META_KEY, time());
    }

    public static function mark_indexed_posts_as_pending($post_ids){
        if(!is_array($post_ids))
            return 0;

        $marked = 0;
        $posts = self::get_posts_by_indexing_status ( EPPlugin::STATUS_INDEXED, array('post__in'=>$post_ids) );

        foreach($posts as $p){
            self::set_post_indexing_status($p->ID, EPPlugin::STATUS_PENDING);
            $marked++;
        }

        return $marked;
    }

    public static function mark_all_posts_as_pending(){
        $marked = 0;
        $posts = self::get_posts_by_indexing_status ( array(EPPlugin::STATUS_INDEXED, EPPlugin::STATUS_INDEXING), array() );

        foreach($posts as $p){
            self::set_post_indexing_status($p->ID, EPPlugin::STATUS_PENDING);
            $marked++;
        }

        return $marked;
    }

    public static function index_pending_posts(){
        $indexed = 0;
        $epclient = EPClient::get_instance();
        do{
            $posts = self::get_posts_by_indexing_status( EPPlugin::STATUS_PENDING, array('posts_per_page'  => 1) );
            if(sizeof($posts)){
                $p = $posts[0];
                self::set_post_indexing_status($p->ID, EPPlugin::STATUS_INDEXING);
                $epclient->add_posts($p);
                self::set_post_indexing_status($p->ID, EPPlugin::STATUS_INDEXED);
                $indexed++;
            }
        } while(sizeof($posts)>0);

        return $indexed;
    }

    public static function mark_stuck_posts_as_pending($timeout_seconds = null){
        if($timeout_seconds === null)
            $timeout_seconds = EPPlugin::INDEXING_TIMEOUT_SECONDS;
        $stuck_threshold = time() - $timeout_seconds;

        $marked = 0;

        $meta_query =  array(
            array(
                'key' => EPPlugin::STATUS_META_KEY,
                'value' => EPPlugin::STATUS_INDEXING,
                'compare' => '='
            ),
            array(
                'key' => EPPlugin::START_TIMESTAMP_META_KEY,
                'value' => $stuck_threshold,
                'compare' => '<',
                'type' => 'NUMERIC'
            )
        );
        
        $args = array(
            'post_type' => array_keys(EPMapper::get_known_types()),
            'posts_per_page'  => -1,
            'orderby'         => 'post_date',
            'order'           => 'DESC',
            'post_status'     => 'publish',
            'meta_query'      => $meta_query
        );

        $posts = get_posts($args);
        foreach($posts as $p){
            self::set_post_indexing_status($p->ID, EPPlugin::STATUS_PENDING);
            $marked++;
        }

        return $marked;
    }
 
}