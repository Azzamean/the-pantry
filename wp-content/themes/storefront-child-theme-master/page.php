<?php
/**
 * The template for displaying all pages.
 *
 * This is the template that displays all pages by default.
 * Please note that this is the WordPress construct of pages
 * and that other 'pages' on your WordPress site will use a
 * different template.
 *
 * @package storefront
 */

get_header(); ?>
<div id="content" class="site-content" tabindex="-1">
    <main id="main" class="body wrapper" role="main">
		<header class="page-header" style="border-bottom: none;">
		    <h1><a href="<?php echo site_url();?>" class="logo">The Pantry</a></h1>    
		    <h1 class="page-title"><?php the_title();?></h1>
		</header>
    </main>
</div>
	<?php while ( have_posts() ) : the_post(); ?>
        <div id="home-main">
            <article class="page">
                <div class="page-body">
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
<?php
get_footer();
