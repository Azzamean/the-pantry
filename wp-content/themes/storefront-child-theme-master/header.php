<?php
/**
 * The header for our theme.
 *
 * Displays all of the <head> section and everything up till <div id="content">
 *
 * @package storefront
 */

?><!doctype html>
<html <?php language_attributes(); ?>>
<head>
<meta charset="<?php bloginfo( 'charset' ); ?>">
<meta name="viewport" content="width=device-width, initial-scale=1, maximum-scale=2.0">
<link rel="profile" href="http://gmpg.org/xfn/11">
<link rel="pingback" href="<?php bloginfo( 'pingback_url' ); ?>">

<link rel="stylesheet" href="https://use.typekit.net/ybg6drv.css">

<?php wp_head(); ?>
</head>

<?php if(get_field('big_image')) { $image = get_field('big_image'); ?>
	<style>
		body {
			padding-top: 63px !important;
			-webkit-background-size: cover !important;
			background-size: cover !important;
		}
	</style>	
    <body <?php body_class(); ?> style="background-image: url('<?php echo $image['url']; ?>');">
<?php } else { ?>
    <body <?php body_class(); ?> >
<?php } ?>

<?php wp_body_open(); ?>

<?php do_action( 'storefront_before_site' ); ?>

<div id="page" class="hfeed site">
	<?php do_action( 'storefront_before_header' ); ?>

	<!-- <header id="masthead" class="site-header" role="banner" style="<?php storefront_header_styles(); ?>">


	</header> -->

	<?php
	/**
	 * Functions hooked in to storefront_before_content
	 *
	 * @hooked storefront_header_widget_region - 10
	 * @hooked woocommerce_breadcrumb - 10
	 */
	do_action( 'storefront_before_content' );
	?>

	<div id="content" class="site-content" tabindex="-1">
	<div class="col-full">
    <main id="main" class="body wrapper" role="main">
        <header class="page-header">
            <h1><a href="<?php echo site_url();?>" class="logo">The Pantry</a></h1>
            <h2>A place to <em>cook</em>. And <em>eat</em>. And <em>learn</em>.</h2>
        </header>

		<?php
