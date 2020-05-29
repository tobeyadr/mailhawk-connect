<?php

add_action( 'admin_init', 'my_plugin_init' );

/**
 * Your plugin initialization...
 *
 * Call the MailHawk_Connect::instance() to add the required ajax hooks
 */
function my_plugin_init(){

	if ( ! class_exists( 'MailHawk_Connect' ) ){
		include __DIR__ . '/mailhawk-connect.php';
	}

	// Initialize
	if ( is_admin() ){
		MailHawk_Connect::instance();
	}

}

add_action( 'my_plugin_admin_page', 'my_plugin_output_mailhawk_connect_ui' );

/**
 * Your plugins admin page or settings screen...
 */
function my_plugin_output_mailhawk_connect_ui(){

	?>
    <!-- My plugins UI -->
	<?php

	// Output the connect UI
	MailHawk_Connect::instance()->connect_ui();

	?>
	<!-- More UI -->
	<?php

}

