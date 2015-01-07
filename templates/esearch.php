<?php
/**
 * Template for displaying elastic Search Results pages
 *
 */

if(!function_exists('debug')){
    function debug($var = null){
        $var_to_dump = func_get_args();
        $debug_message = '';
        foreach ($var_to_dump as $var) {
            if($var === null)
                $var = '(null)';
            if($var === false)
                $var = '(bool)false';
            if($var === true)
                $var = '(bool)true';
            if($var === 0)
                $var = '(int)0';
            if($var === '')
                $var = '\'\'';
            if(is_string($var))
                $var = htmlspecialchars($var, ENT_QUOTES);
            $debug_message .= print_r($var ,true). '<br/>'; 
        }
        $backtrace = debug_backtrace();
        $backtrace = $backtrace[0];
        $file_name = $backtrace['file'];// substr($backtrace['file'],strripos($backtrace['file'],'/') + 1);
        printf('<pre class="debug">%s:%d<br />%s</pre>',$file_name,$backtrace['line'],$debug_message);
    }
}

get_header(); 
$results= Elasticpress\EPPlugin::$latest_results;

//DEBUG
//$ep_client = Elasticpress\EPClient::get_instance();
//print_r( $ep_client->analyze(Elasticpress\EPPlugin::$latest_search['string'], array('field'=>'post_title')) );

?>

        <div id="container">
            <div id="content" role="main">

<?php if (false &&  have_posts() ) : ?>

            <header class="page-header">
                <h1 class="page-title"><?php printf( __( 'Loop Search Results for: %s', 'twentythirteen' ), get_search_query() ); ?></h1>
            </header>

            <?php /* The loop */ ?>
            <?php while ( have_posts() ) : the_post(); ?>
                <?php get_template_part( 'content', get_post_format() ); ?>
            <?php endwhile; ?>

            <?php twentythirteen_paging_nav(); ?>

<?php endif; ?>

<?php 
    debug( Elasticpress\EPPlugin::$latest_search);
    if ( sizeof($results) ) : 

            debug($results);
?>
            <header class="page-header">
                <h1 class="page-title"><?php printf( __( 'ElasticSearch Results for: %s', 'elasticpress' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
            </header>

            <?php 
                $lasttype = '';
                foreach($results as $r): 
                if($lasttype !== $r['_type']):
                    $lasttype = $r['_type'];
                    ?>
                <div>
                    <h1><?php print_r($r['_type']) ?></h1>
                </div>
                <?php endif;?>
                <div>
                    <h2><?php print_r($r['_source']['post_title']) ?></h2>
                    <p>Doc. Id: <?php print_r($r['_id']); ?></p>
                    <p>Score: <?php print_r($r['_score']); ?></p>

                    <pre><?php print_r($r['_source']) ?></pre>
                </div>
            <?php endforeach; ?>
<?php else : ?>
                <div id="post-0" class="post no-results not-found">
                    <h2 class="entry-title"><?php _e( 'Nothing Found', 'elasticpress' ); ?></h2>
                    <div class="entry-content">
                        <p><?php _e( 'Sorry, but nothing matched your search criteria. Please try again with some different keywords.', 'elasticpress' ); ?></p>
                        <?php get_search_form(); ?>
                    </div><!-- .entry-content -->
                </div><!-- #post-0 -->
<?php endif; ?>
            </div><!-- #content -->
        </div><!-- #container -->


<?php get_footer(); ?>