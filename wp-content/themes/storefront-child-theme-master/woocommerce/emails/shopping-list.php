<?php
/**
 * Shopping List Reminder email
 *
 * This template can be overridden by copying it to yourtheme/woocommerce/emails/customer-completed-order.php.
 *
 * HOWEVER, on occasion WooCommerce will need to update template files and you
 * (the theme developer) will need to copy the new files to your theme to
 * maintain compatibility. We try to do this as little as possible, but it does
 * happen. When this occurs the version of the template file will be bumped and
 * the readme will list any important changes.
 *
 * @see https://docs.woocommerce.com/document/template-structure/
 * @package WooCommerce/Templates/Emails
 * @version 3.7.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/*
 * @hooked WC_Emails::email_header() Output the email header
 */
do_action( 'woocommerce_email_header', $email_heading, $email ); ?>

<p>Thank you for signing up for <?= esc_html( $class_name_friendly ); ?> with <?= esc_html( $instructor ); ?> on <?= esc_html( $class_date ); ?>. This live, online class will start at <?= esc_html( $class_time ); ?> PT and run for about 2 hours.</p>

<p>We'll send out the recipes and a Zoom link a bit closer to class, but we wanted to go ahead and get <a href="<?= esc_html( $list_link ); ?>">the shopping and equipment list</a> to you in case you're limiting your shopping trips. </p>

<?php echo $email_text;?>

<?php

echo '<hr style="margin-bottom: 20px;
    margin-top: 20px;
    border: none;
    border-bottom: 1px solid #ccc;">';

/*
 * @hooked WC_Emails::email_footer() Output the email footer
 */
do_action( 'woocommerce_email_footer', $email );
