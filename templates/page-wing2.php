<?php
/*
Template Name: Wing 1
Wing 1 page template
*/
?>
<?php get_header(); ?>

	<div id="body">
		
		<div id="main"><div class="inner">
			
			<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>

			<div class="box post">

				<?php edit_post_link(__('Edit This','fauna'), '<span class="edit">', '</span>'); ?>

				<h2 id="post-<?php the_ID(); ?>"><?php the_title(); ?></h2>
				<?php the_content(__('Continue reading this entry','fauna').' &raquo;'); ?>
<?php if (function_exists('ftotw_get_river')) {echo ftotw_get_river(2, ftotw_get_month_from_title(get_the_title()), ftotw_get_year_from_title(get_the_title())); } ?>
<?php if (function_exists('ftotw_get_last_updated_date')) {echo '<p>Last updated on:', ftotw_get_last_updated_date(), '</p>'; } ?>
				<?php wp_link_pages('before=<strong>'.__('Page:','fauna').' &after=</strong>&next_or_number=number&pagelink=%'); ?>
				
				
				<!--
				<?php trackback_rdf(); ?>
				-->
			
				<hr />
				
			</div>

			<?php comments_template(); ?>

			<?php endwhile; else: ?>

				<?php include (TEMPLATEPATH . '/templates/template-notfound.php'); ?>

			<?php endif; ?>
			
			<hr />
	
		</div></div><!--// #main -->
		
		<?php get_sidebar(); ?>
		
	</div><!--// #body -->

<?php get_footer(); ?>
