<?php
/**
 * Template for displaying elastic Search Results pages
 *
 */
get_header(); 
$results= Elasticpress\EPPlugin::$latest_results;
?>

        <div id="container">
            <div id="content" role="main">

<?php if ( have_posts() ) : ?>

            <header class="page-header">
                <h1 class="page-title"><?php printf( __( 'Loop Search Results for: %s', 'twentythirteen' ), get_search_query() ); ?></h1>
            </header>

            <?php /* The loop */ ?>
            <?php while ( have_posts() ) : the_post(); ?>
                <?php get_template_part( 'content', get_post_format() ); ?>
            <?php endwhile; ?>

            <?php twentythirteen_paging_nav(); ?>

<?php endif; ?>

<?php if ( sizeof($results) ) : ?>
            <header class="page-header">
                <h1 class="page-title"><?php printf( __( 'ElasticSearch Results for: %s', 'elasticpress' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
            </header>

            <?php foreach($results as $r): ?>
                <?php print_r($r) ?>
                <div>
                    <h1><?php print_r($r['_source']['post_title']) ?></h1>
                    <p><?php print_r($r['_source']['post_content']) ?></p>
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

<?php get_sidebar(); ?>
<?php //get_footer(); ?>