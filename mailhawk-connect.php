<?php

/**
 * MailHawk Connect.
 *
 * This include this class in your plugin and follow the example implementation
 * of how to deply it to display the MailHawk connect button.
 *
 * @todo replace 'my_plugin' with your plugin slug. Ex. 'groundhogg' or 'lifterlms'
 * @todo add your affiliate ID
 */

defined( 'ABSPATH' ) || exit;

/**
 * MailHawk_Connect class.
 */
class MailHawk_Connect {

	/**
	 * @todo replace this with your Affiliate ID
	 */
	const PARTNER_ID = 1;

	/**
	 * Constructor.
	 *
	 * @return void
	 *
	 */
	public function __construct() {
		add_action( 'wp_ajax_my_plugin_mailhawk_remote_install', array( $this, 'ajax_callback_remote_install' ) );
	}


	/**
	 * Ajax callback for installing MailHawk Plugin.
	 *
	 * @hook wp_ajax_my_plugin_mailhawk_remote_install
	 * 
	 * @return void
	 */
	public function ajax_callback_remote_install() {

		$ret = $this->do_remote_install();
		ob_clean();
		wp_send_json( $ret, ! empty( $ret['status'] ) ? $ret['status'] : 200 );

	}

	/**
	 * Remote installation method.
	 *
	 * @return array
	 */
	public function do_remote_install() {

	    $nonce = isset( $_POST[ 'nonce' ] ) ? $_POST[  'nonce' ] : false;

		if ( ! current_user_can( 'install_plugins' ) || ! wp_verify_nonce( $nonce, 'install_mailhawk' ) ) {
			return array(
				'code'    => 'mailhawk_install_unauthorized',
				'message' => __( 'You do not have permission to perform this action.', 'my_plugin' ),
				'status'  => 403,
			);
		}

		$install = $this->install();

		if ( is_wp_error( $install ) ) {
			return array(
				'code'    => $install->get_error_code(),
				'message' => $install->get_error_message(),
				'status'  => 400,
			);
		}

		if ( ! defined( 'MAILHAWK_VERSION' ) ) {
			return array(
				'code'    => 'mailhawk_missing',
				'message' => 'MailHawk not installed.',
				'status'  => 400,
			);
		}

		// You can change this to redirect back to your own plugin...
		$redirect = \MailHawk\get_admin_mailhawk_uri();

		return array(
			'partner_id'   => self::PARTNER_ID,
			'register_url' => esc_url( trailingslashit( MAILHAWK_LICENSE_SERVER_URL ) ),
			'client_state' => esc_url( \MailHawk\Keys::instance()->state() ),
			'redirect_uri' => esc_url( $redirect ),
		);

	}

	/**
	 * Install / Activate MailHawk plugin.
	 *
	 * @return \WP_Error|true
	 */
	private function install() {

		$is_mailhawk_installed = false;

		foreach ( get_plugins() as $path => $details ) {
			if ( false === strpos( $path, '/mailhawk.php' ) ) {
				continue;
			}
			$is_mailhawk_installed = true;
			$activate              = activate_plugin( $path );
			if ( is_wp_error( $activate ) ) {
				return $activate;
			}
			break;
		}

		$install = null;
		if ( ! $is_mailhawk_installed ) {

			include_once ABSPATH . 'wp-admin/includes/plugin-install.php';
			include_once ABSPATH . 'wp-admin/includes/file.php';
			include_once ABSPATH . 'wp-admin/includes/class-wp-upgrader.php';

			// Use the WordPress Plugins API to get the plugin download link.
			$api = plugins_api(
				'plugin_information',
				array(
					'slug' => 'mailhawk',
				)
			);
			if ( is_wp_error( $api ) ) {
				return $api;
			}

			// Use the AJAX upgrader skin to quietly install the plugin.
			$upgrader = new \Plugin_Upgrader( new \WP_Ajax_Upgrader_Skin() );
			$install  = $upgrader->install( $api->download_link );
			if ( is_wp_error( $install ) ) {
				return $install;
			}

			$activate = activate_plugin( $upgrader->plugin_info() );
			if ( is_wp_error( $activate ) ) {
				return $activate;
			}
		}

		// Final check to see if MailHawk is available.
		if ( ! defined( 'MAILHAWK_VERSION' ) ) {
			return new \WP_Error( 'mailhawk_not_found', __( 'MailHawk plugin not found. Please try again.', 'my_plugin' ), $install );
		}

		return true;

	}

	/**
	 * Get the "Connect" Setting field html.
	 */
	public function output_connect_button() {

		if ( function_exists( '\MailHawk\mailhawk_is_connected' ) && \MailHawk\mailhawk_is_connected() ) {

			$ret = array(
				__( 'Your site is connected to MailHawk.', 'my_plugin' ),
			);

			if ( function_exists( '\MailHawk\mailhawk_is_suspended' ) && ! \MailHawk\mailhawk_is_suspended() ) {
				$ret[] = sprintf(
				// Translators: %1$s = Opening anchor tag; %2$s = Closing anchor tag.
					__( '%1$sManage your account%2$s.', 'my_plugin' ),
					'<a href="https://mailhawk.io/account/" target="_blank" rel="noopener noreferrer">',
					'</a>'
				);
			} else {
				$ret[] = sprintf(
				// Translators: %1$s = Opening anchor tag; %2$s = Closing anchor tag.
					'<em>' . __( 'Email sending is currently disabled. %1$sVisit the MailHawk Settings%2$s to enable sending..', 'my_plugin' ) . '</em>',
					'<a href="' . admin_url( '/tools.php?page=mailhawk' ) . '">',
					'</a>'
				);
			}

			echo '<p>' . implode( ' ', $ret ) . '</p>';

			return;

		}

		echo sprintf( '<button type="button" class="button button-primary big-button" id="my_plugin-mailhawk-connect"><span class="dashicons dashicons-email-alt"></span> %s</button>', __( 'Connect MailHawk' ) );
	}

	/**
	 * Output some quick and dirty inline CSS.
	 *
	 * @return void
	 */
	public function output_css() {
		?>
        <style type="text/css">
            #my_plugin-mailhawk-connect {
                font-size: 16px;
                height: auto;
                margin: 0 0 6px;
                padding: 8px 14px;
            }

            #my_plugin-mailhawk-connect .fa {
                margin-right: 4px;
            }

            #my_plugin-mailhawk-connect .dashicons {
                /*vertical-align: middle;*/
                font-size: 26px;
                margin-right: 10px;
            }
        </style>
		<?php
	}

	/**
	 * Settings UI output.
	 */
	public function connect_ui() {

		$this->output_css();

		?>
        <table class="form-table">
            <tbody>
            <tr>
                <th><?php _e( 'A better way to send email.', 'my_plugin' ); ?></th>
                <td><?php $this->output_connect_button(); ?>

					<?php if ( ! function_exists( '\MailHawk\mailhawk_is_connected' ) || ! \MailHawk\mailhawk_is_connected() ) : ?>
                        <p class="description"><?php _e( 'Never worry about sending email again! <a href="https://mailhawk.io/" target="_blank">MailHawk</a> takes care of everything for you for starting at <b>$14.97/month!</b>', 'my_plugin' ); ?></p>
					<?php endif; ?>
                </td>
            </tr>
            </tbody>
        </table>
		<?php

		$this->output_js();
	}

	/**
	 * Output some quick and dirty inline JS.
	 *
	 * @return void
	 */
	public function output_js() {
		?>
        <script>
            var btn = document.getElementById("my_plugin-mailhawk-connect");
            btn.addEventListener("click", function (e) {
                e.preventDefault();
                my_plugin_mailhawk_remote_install();
            });

            /**
             * Perform AJAX request to install MailHawk plugin.
             *
             * @since 3.36.1
             *
             * @return void
             */
            function my_plugin_mailhawk_remote_install() {
                var data = {
                    "action": "my_plugin_mailhawk_remote_install",
                    "nonce": '<?php echo wp_create_nonce( 'install_mailhawk' ); ?>'
                };

                jQuery.post(ajaxurl, data, function (res) {

                    my_plugin_mailhawk_register_client(res.register_url, res.client_state, res.redirect_uri, res.partner_id);
                }).fail(function (jqxhr) {
                    if (jqxhr.responseJSON && jqxhr.responseJSON.message) {
                        alert("Error: " + jqxhr.responseJSON.message);
                        console.log(jqxhr);
                    }
                });
            }

            /**
             * Register client with MailHawk.
             *
             * @since 3.36.1
             *
             * @param {string} register_url Registration URL.
             * @param {string} client_state string state for oauth.
             * @param {string} redirect_uri Client redirect URL.
             * @param {int} partner_id MailHawk partner ID.
             * @return {void}
             */
            function my_plugin_mailhawk_register_client(register_url, client_state, redirect_uri, partner_id) {

                var form = document.createElement("form");
                form.setAttribute("method", "POST");
                form.setAttribute("action", register_url);

                function my_plugin_mailhawk_append_form_input(name, value) {
                    var input = document.createElement("input");
                    input.setAttribute("type", "hidden");
                    input.setAttribute("name", name);
                    input.setAttribute("value", value);
                    form.appendChild(input);
                }

                my_plugin_mailhawk_append_form_input("mailhawk_plugin_signup", "yes");
                my_plugin_mailhawk_append_form_input("state", client_state);
                my_plugin_mailhawk_append_form_input("redirect_uri", redirect_uri);
                my_plugin_mailhawk_append_form_input("partner_id", partner_id);

                document.body.appendChild(form);
                form.submit();

            }
        </script>
		<?php

	}

	/**
	 * @var MailHawk_Connect;
	 */
	public static $instance;

	/**
	 * Instance.
	 *
	 * Ensures only one instance of the plugin class is loaded or can be loaded.
	 *
	 * @return MailHawk_Connect An instance of the class.
	 * @since 1.0.0
	 * @access public
	 * @static
	 *
	 */
	public static function instance() {
		if ( is_null( self::$instance ) ) {
			self::$instance = new self();
		}

		return self::$instance;
	}

	/**
	 * Clone.
	 *
	 * Disable class cloning and throw an error on object clone.
	 *
	 * The whole idea of the singleton design pattern is that there is a single
	 * object. Therefore, we don't want the object to be cloned.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __clone() {
		// Cloning instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Something went wrong.', 'my_plugin' ), '1.0' );
	}

	/**
	 * Wakeup.
	 *
	 * Disable unserializing of the class.
	 *
	 * @access public
	 * @since 1.0.0
	 */
	public function __wakeup() {
		// Unserializing instances of the class is forbidden.
		_doing_it_wrong( __FUNCTION__, esc_html__( 'Something went wrong.', 'my_plugin' ), '1.0' );
	}

}
