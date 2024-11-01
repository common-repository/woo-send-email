<?php
/**
 * Plugin Name: Woo Send Email
 * Plugin URI: http://ldav.it/shop/
 * Description: Send custom email message to your WooCommerce customers directly from WC Orders Admin Page.
 * Version: 0.3
 * Author: laboratorio d'Avanguardia
 * Author URI: http://ldav.it/
 * Requires at least: 4.4
 * Tested up to: 6.6.2
 * WC requires at least: 3.0.0
 * WC tested up to: 7.7.2
 *
 * Text Domain: woo-send-email
 * Domain Path: /languages
 * License: GPLv2 or later
 * License URI: http://www.opensource.org/licenses/gpl-license.php
*/

if ( ! defined( 'ABSPATH' ) ) exit; // Exit if accessed directly
if ( !class_exists( 'Woo_Send_Email' ) ) :
define('Woo_SETC_DOMAIN', 'ldav_woosetc');
	
class Woo_Send_Email {
	public $plugin_basename;
	public $plugin_url;
	public $plugin_path;
	public $version = '0.3';
	protected static $instance = null;

	public static function instance() {
		if ( is_null( self::$instance ) ) self::$instance = new self();
		return self::$instance;
	}

	public function __construct() {
		$this->plugin_basename = plugin_basename(__FILE__);
		$this->plugin_url = plugin_dir_url($this->plugin_basename);
		$this->plugin_path = trailingslashit(dirname(__FILE__));
		$this->init_hooks();
	}

	public function init() {
		$locale = apply_filters( 'plugin_locale', get_locale(), Woo_SETC_DOMAIN );
		load_plugin_textdomain( Woo_SETC_DOMAIN, FALSE, basename( dirname( __FILE__ ) ) . '/languages/' );
	}

	private function init_hooks() {
		add_action( 'plugins_loaded', array( $this, 'init' ), 0 );
		if ($this->is_wc_active()) {
			add_action( 'restrict_manage_posts', array( $this, 'show_button') , 50  );
			add_action( 'wp_ajax_woosetc_sendmessage', array( $this, 'sendmessage' ) );
			add_action( 'before_woocommerce_init', function() {
				if ( class_exists( \Automattic\WooCommerce\Utilities\FeaturesUtil::class ) ) {
					\Automattic\WooCommerce\Utilities\FeaturesUtil::declare_compatibility( 'custom_order_tables', __FILE__, true );
				}
			} );
		} else {
			add_action( 'admin_notices', array ( $this, 'check_wc' ) );
		}
	}

	public function is_wc_active() {
		$plugins = get_site_option( 'active_sitewide_plugins', array());
		if (in_array('woocommerce/woocommerce.php', get_option( 'active_plugins', array())) || isset($plugins['woocommerce/woocommerce.php'])) {
			return true;
		} else {
			return false;
		}
	}

	public function check_wc( $fields ) {
		$class = "error";
		$message = sprintf( __( 'Woo Send Email requires %sWooCommerce%s to be installed and activated!' , Woo_SETC_DOMAIN ), '<a href="https://wordpress.org/plugins/woocommerce/">', '</a>' );
		echo"<div class=\"$class\"> <p>$message</p></div>";
	}	

	public function show_button(){
		$current_user = wp_get_current_user();
		$user_email = $current_user->user_email;
		global $typenow;
		if ( 'shop_order' != $typenow || !current_user_can( 'manage_woocommerce' ) ) {return;}	
		$args = array(
		'ajax_url' => admin_url( 'admin-ajax.php' ),
		'msg_email_sent_to' => __('Email message sent to:', Woo_SETC_DOMAIN),
		'msg_email_completed' => __('Sending completed.', Woo_SETC_DOMAIN),
		'msg_error_no_selection' => __('No order selected.', Woo_SETC_DOMAIN),
		'msg_error_no_subject' => __('Email subject is requested.', Woo_SETC_DOMAIN),
		'msg_error_no_message' => __('Email message is requested.', Woo_SETC_DOMAIN)
		);
		wp_register_script( 'ldav_woosetc', plugin_dir_url( __FILE__ ) . 'inc/sendemail.js' );
		wp_localize_script( 'ldav_woosetc', 'woosetc_ajax_object', $args);
		wp_enqueue_script( 'ldav_woosetc' );

		$email_heading = "";
		$custom_message = "";
		$test = 0;

		if(isset($_COOKIE["woo_send_cookie"])) {
			$data = json_decode(stripslashes($_COOKIE['woo_send_cookie']), true);
			$email_heading = $data["subject"];
			$custom_message = stripslashes($data["custom_message"]);
			$test = $data["test"];
		}

	?>
<input type="button" id="woo_send_email_openpanel" class="button" value="<?php _e('Send a message', Woo_SETC_DOMAIN); ?>">
<div id="woo_send_email_panel_wrap" style="display:none; clear:both; padding-top: 12px;">
<div style="position:absolute; z-index:2"><input type="text" id="woo_send_email_subject" placeholder="<?php _e('Subject', Woo_SETC_DOMAIN); ?>" size="60" value="<?php echo $email_heading ?>" style="height:32px"></div>
<?php wp_editor($custom_message, $editor_id = 'woo_send_email_custom_content', array("media_buttons" => false, "drag_drop_upload" => false, "textarea_name" => "")) ?>
<p>
	<label><input type="checkbox" id="woo_send_email_test_option" <?php echo $test ? " checked" : "" ?>> <?php echo sprintf(__("Send a message to <strong>%s</strong> for testing", Woo_SETC_DOMAIN), $user_email); ?></label></p>
<p><input type="button" id="woo_send_email_button" class="button" value="<?php _e('Send a message', Woo_SETC_DOMAIN); ?>"> <span id="woo_send_email_alert"></span></p>
<input type="hidden" id="woo_send_email_wpnonce" value="<?php echo wp_create_nonce( 'user_email_'.$user_email ); ?>" />
</div>
<?php
	}

	public function sendmessage() {
		global $woocommerce;
		if ( !class_exists( 'WC_Email', false ) ) {
			$mailer = $woocommerce->mailer();
		}

		setcookie('woo_send_cookie', json_encode($_POST));

		$current_user = wp_get_current_user();
		if ( 0 == $current_user->ID ) {
			_doing_it_wrong( __FUNCTION__, __( 'User not found.' ), '0.1' );
			wp_die( __( 'Validation error.' ), 403 );
		}
		$user_email = $current_user->user_email;
		$ok = check_ajax_referer( 'user_email_'.$user_email, 'nonce', false );
		if ( !$ok ) {
			_doing_it_wrong( __FUNCTION__, __( 'Nonce not validated.' ), '0.1' );
			wp_die( __( 'Validation error.' ), 500 );
		}

		if(!current_user_can( 'manage_woocommerce' )) {
			_doing_it_wrong( __FUNCTION__, __( 'Is Not enough.' ), '0.1' );
			wp_die( __( 'Validation error.' ), 500 );
		}

		$email_heading = stripslashes(sanitize_text_field($_POST["subject"]));
		$custom_message = stripslashes(wp_kses_post($_POST["custom_message"]));
		$test = isset($_POST["test"]) && $_POST["test"] === "true" ? 1 : 0;
		if($test === 1) {
			$email = $user_email;
		} else {
			$order_id = intval($_POST["order_id"]);
			$order = wc_get_order($order_id);
			$email = $order->get_billing_email();
		}

		ob_start();
		do_action( 'woocommerce_email_header', $email_heading, $email );
		echo $custom_message;
		do_action( 'woocommerce_email_footer', $email );
		$body = ob_get_clean();
		
		$mail = new WC_Email();
		$mail->send($email, $email_heading, $body, "", "");
		echo $email;

		wp_die();
	}
	
}
endif;

$Woo_Send_Email_To_Customer = new Woo_Send_Email();

?>