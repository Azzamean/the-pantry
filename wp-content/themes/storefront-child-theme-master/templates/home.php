<?php
/**
 * The template for displaying the homepage.
 *
 * This page template will display any functions hooked into the `homepage` action.
 * By default this includes a variety of product displays and the page content itself. To change the order or toggle these components
 * use the Homepage Control plugin.
 * https://wordpress.org/plugins/homepage-control/
 *
 * Template name: New Homepage
 *
 * @package storefront
 */

get_header(); ?>
	<?php while ( have_posts() ) : the_post(); ?>
	
	<div id="content" class="site-content" tabindex="-1">
		<div class="col-full">
	    <main id="main" class="body wrapper" role="main">
		    <a id="home-pane" href="#home-main">
				<h1>The Pantry</h1>
			</a>
	        <header class="page-header">
	            <h1><a href="<?php echo site_url();?>" class="logo">The Pantry</a></h1>
	            
				<?php if(get_field('sub_head')) {?><h1><?php the_field('sub_head');?></h1><?php } ?>
	
	        </header>
	
        <div id="home-main">
            <article class="page">
                <div class="page-body columns">
                    <?php the_content();?>
                </div>
                <ul class="homeLinks">
	                
	                <?php
					
					// Check rows exists.
					if( have_rows('links') ):
					
					    // Loop through rows.
					    while( have_rows('links') ) : the_row();
					?>
					    <li><a href="<?php the_sub_field('link_url'); ?>"><?php the_sub_field('link_text'); ?></a></li>
					<?php
					    // End loop.
					    endwhile;
					
					// No value.
					else :
					    // Do something...
					endif;
	                ?>
	                
                </ul>
            </article>
        </div>
	<?php endwhile; // End of the loop. ?>
    </main>
<script>
	jQuery(function ($) {
		$(document).ready(function(){
			$("#home-pane").click(function(e){
				e.preventDefault();
				$('body').addClass('hidehome');
			});
		});
	});
</script>	
<?php
get_footer();
