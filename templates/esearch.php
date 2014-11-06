<?php
/**
 * Template for displaying elastic Search Results pages
 *
 */
get_header(); ?>

		<div id="container">
			<div id="content" role="main">

<?php if ( sizeof($results) ) : ?>
				<h1 class="page-title"><?php printf( __( 'Search Results for: %s', 'elasticpress' ), '<span>' . get_search_query() . '</span>' ); ?></h1>
				<?php foreach($results as $r): ?>
					<div><?php print_r($r) ?></div>
				<?php endforeach; 
				/**
				 * Run the loop for the search to output the results.
				 * If you want to overload this in a child theme then include a file
				 * called loop-search.php and that will be used instead.
				 */
				 //get_template_part( 'loop', 'search' );
				?>
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
<?php get_footer(); ?>