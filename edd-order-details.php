<?php
/*
Plugin Name: Easy Digital Downloads - Order Details
Plugin URI: https://sellcomet.com/downloads/order-details/
Description: Allow your vendors to export comprehensive order information.
Version: 1.0.1
Author: Sell Comet
Author URI: https://sellcomet.com
License: GPL-2.0+
License URI: http://www.opensource.org/licenses/gpl-license.php
Text Domain: edd-order-details
Domain Path: languages
*/


// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists( 'EDD_Order_Details' ) ) {

	final class EDD_Order_Details {

		/**
		 * Holds the instance
		 *
		 * Ensures that only one instance of EDD Order Details exists in memory at any one
		 * time and it also prevents needing to define globals all over the place.
		 *
		 * TL;DR This is a static property property that holds the singleton instance.
		 *
		 * @var object
		 * @static
		 * @since 1.0.0
		 */
		private static $instance;

		/**
		 * Main Instance
		 *
		 * Ensures that only one instance exists in memory at any one
		 * time. Also prevents needing to define globals all over the place.
		 *
		 * @since 1.0.0
		 *
		 */
		public static function get_instance() {
			if ( ! isset( self::$instance ) && ! ( self::$instance instanceof EDD_Order_Details ) ) {
				self::$instance = new EDD_Order_Details;
				self::$instance->setup_globals();
				self::$instance->hooks();
			}

			return self::$instance;
		}

		/**
		 * Constructor Function
		 *
		 * @since 1.0.0
		 * @access private
		 */
		private function __construct() {
			self::$instance = $this;

		}

		/**
		 * Reset the instance of the class
		 *
		 * @since 1.0.0
		 * @access public
		 * @static
		 */
		public static function reset() {
			self::$instance = null;
		}

		/**
		 * Globals
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		private function setup_globals() {

			$this->version    = '1.0.1';

			// paths
			$this->file         = __FILE__;
			$this->basename     = apply_filters( 'edd_order_details_plugin_basenname', plugin_basename( $this->file ) );
			$this->plugin_dir   = apply_filters( 'edd_order_details_plugin_dir_path', plugin_dir_path( $this->file ) );
			$this->plugin_url   = apply_filters( 'edd_order_details_plugin_dir_url', plugin_dir_url( $this->file ) );

		}

		/**
		 * Setup the default hooks and actions
		 *
		 * @since 1.0.0
		 *
		 * @return void
		 */
		private function hooks() {

      // Check if Easy Digital Downloads is installed and active
			if ( ! class_exists( 'EDD_Customer' ) ) {
				add_action( 'admin_notices', array( $this, 'edd_admin_notice' ) );
				return;
			}

      // Check if Frontend Submissions is installed and active
			if ( ! class_exists( 'EDD_Front_End_Submissions' ) ) {
				add_action( 'admin_notices', array( $this, 'fes_admin_notice' ) );
				return;
			}

      // Add our checkIn function
			add_action('wp_loaded', function() {
					$this->checkIn();
			});

			// Load text domain
			add_action( 'after_setup_theme', array( $this, 'load_textdomain' ) );

			if ( is_admin() ) {
				// Register extension settings subsection
				add_filter( 'edd_settings_sections_extensions', array( $this, 'settings_subsection' ), 1, 1 );

				// Add extension settings
				add_filter( 'edd_settings_extensions', array( $this, 'settings' ) );
			}

      // Frontend Submissions integration
			add_action ('fes_after_frontend_orders',  array( $this, 'fes_form_integration' ), 10, 1 );

			// Handle licensing
			if ( class_exists( 'EDD_License' ) ) {
				$license = new EDD_License( __FILE__, 'Order Details', $this->version, 'Sell Comet', null, 'https://sellcomet.com/', 388 );
			}

			do_action( 'edd_order_details_setup_actions' );
		}

		/**
		 * Easy Digital Downloads admin notice
		 *
		 * @since 1.0.0
		 */
		public function edd_admin_notice() {
			echo '<div class="error"><p>' . __( 'EDD Order Details requires Easy Digital Downloads Version 2.7.11 or greater. Please update or install Easy Digital Downloads.', 'edd-order-details' ) . '</p></div>';
		}

    /**
     * Easy Digital Downloads - Frontend Submissions admin notice
     *
     * @since 1.0.0
     */
    public function fes_admin_notice() {
      echo '<div class="error"><p>' . __( 'EDD Order Details requires Frontend Submissions Version 2.5.2 or greater. Please update or install Frontend Submissions.', 'edd-order-details' ) . '</p></div>';
    }


		/**
		 * Loads the plugin language files
		 *
		 * @access public
		 * @since 1.0.0
		 * @return void
		 */
		public function load_textdomain() {
			// Set filter for plugin's languages directory
			$lang_dir = dirname( plugin_basename( $this->file ) ) . '/languages/';
			$lang_dir = apply_filters( 'edd_order_details_languages_directory', $lang_dir );

			// Traditional WordPress plugin locale filter
			$locale        = apply_filters( 'plugin_locale',  get_locale(), 'edd-order-details' );
			$mofile        = sprintf( '%1$s-%2$s.mo', 'edd-order-details', $locale );

			// Setup paths to current locale file
			$mofile_local  = $lang_dir . $mofile;
			$mofile_global = WP_LANG_DIR . '/edd-order-details/' . $mofile;

			if ( file_exists( $mofile_global ) ) {
				// Look in global /wp-content/languages/edd-auto-register folder
				load_textdomain( 'edd-order-details', $mofile_global );
			} elseif ( file_exists( $mofile_local ) ) {
				// Look in local /wp-content/plugins/edd-auto-register/languages/ folder
				load_textdomain( 'edd-order-details', $mofile_local );
			} else {
				// Load the default language files
				load_plugin_textdomain( 'edd-order-details', false, $lang_dir );
			}
		}


    /**
     * Generate Orders File
     * Verify our nonce/user_id and pass the data required to build our export file
     *
     * @since 1.0.0
     */
		public function custom_generate_orders_file( $data ) {

				$user_id = $data['user_id'];

				if ( ! isset( $_POST['edd_order_details_nonce'] ) || ! wp_verify_nonce( $_POST['edd_order_details_nonce'], 'edd_order_details_nonce' ) ) {
						wp_die( __( 'Nonce verification failed', 'edd-order-details' ), __( 'Error', 'edd-order-details' ), array( 'response' => 403 ) );
				}

				if ( ( empty( $user_id ) ) ) {
						return;
				}

				require_once plugin_dir_path(__FILE__) . 'includes/edd-orders-export.php';
				$export          = new EDD_Orders_Export();
				$export->user_id = $user_id;
				$export->year    = $data['year'];
				$export->month   = $data['month'];
				$export->export();
		}


    /**
     * checkIn function
     *
     * @since 1.0.0
     */
		public function checkIn() {

				if ( ! empty( $_POST ) && isset( $_POST['edd_action']) && $_POST['edd_action'] === 'generate_orders_export' ) {

						$data['year']    = filter_var( $_POST['year'], FILTER_VALIDATE_INT ) ? (int) $_POST['year'] : date('Y');
						$data['month']   = filter_var( $_POST['month'], FILTER_VALIDATE_INT ) ? (int) $_POST['month'] : date('m');
						$data['user_id'] = filter_var( $_POST['user_id'], FILTER_VALIDATE_INT ) ? (int) $_POST['user_id'] : get_current_user_id();

						$this->custom_generate_orders_file( $data );
				}

		}


    /**
     * Frontend Submissions integration
     * Adds export form to FES Orders screen
     *
     * @since 1.0.0
     */
		public function fes_form_integration( $orders ) {

		global $orders;

		if ( ! empty( $orders ) && count( $orders ) > 0 && is_array( $orders ) ) {

		$user_id 			= empty ( $user_id ) ? get_current_user_id() : $user_id;
		$first_year   = date( 'Y', strtotime( $orders[0]->post_date ) );
		$years_back   = date( 'Y', current_time( 'timestamp' ) )  - $first_year;
		$url = admin_url('admin-ajax.php');

		?>
		<div id="edd_orders_export">
		    <h3><?php _e('Export Orders', 'edd-order-details'); ?></h3>
		    <form method="post" action="<?php echo $url; ?>">
		        <?php echo EDD()->html->month_dropdown(); ?>
		        <?php echo EDD()->html->year_dropdown( 'year', 0, $years_back, 0 ); ?>
		        <?php wp_nonce_field( 'edd_order_details_nonce', 'edd_order_details_nonce' ); ?>
		        <input type="hidden" name="user_id" value="<?php echo $user_id ?>">
		        <input type="hidden" name="edd_action" value="generate_orders_export"/>
		        <input type="submit" class="edd-submit button" value="<?php _e( 'Download CSV', 'edd-order-details' ); ?>"/>
		    </form>
		  <?php
		  }

		}


		/**
		 * Registers the settings subsection
		 *
		 * @access      public
		 * @since       1.0.0
		 * @param       array $sections The sections
		 * @return      array Sections with new subsection added
		 */
		public function settings_subsection( $sections ) {
			$sections['order_details'] = __( 'Order Details', 'edd-order-details' );
			return $sections;
		}


		/**
		 * Settings
		 *
		 * @since 1.0.0
		 */
		public function settings( $settings ) {

			$edd_order_details_settings = array(
				array(
					'id' => 'edd_order_details_general_settings_header',
					'name' => '<strong>' . __( 'General Settings', 'edd-order-details' ) . '</strong>',
					'type' => 'header',
				),
				array(
					'id' => 'edd_order_details_disable_customer_data',
					'name' => __( 'Disable Customer Data', 'edd-order-details' ),
					'desc' => __( 'Check to remove personally identifiable customer data from the exported CSV file. The customer username is still included for support purposes.', 'edd-order-details' ),
					'type' => 'checkbox',
				),
        array(
          'id' => 'edd_order_details_disable_payment_meta',
          'name' => __( 'Disable Payment Meta', 'edd-order-details' ),
          'desc' => __( 'Check to remove Gateway, Key and Transaction ID metadata from the exported CSV file.', 'edd-order-details' ),
          'type' => 'checkbox',
        ),
        array(
          'id' => 'edd_order_details_disable_tax_data',
          'name' => __( 'Disable Tax Data', 'edd-order-details' ),
          'desc' => __( 'Check to remove Tax Rate and Amount from the exported CSV file.', 'edd-order-details' ),
          'type' => 'checkbox',
        ),
				array(
					'id' => 'edd_order_details_disable_commission_data',
					'name' => __( 'Disable Commission Data', 'edd-order-details' ),
					'desc' => __( 'Check to remove Commission Amount, Rate and Status from the exported CSV file.', 'edd-order-details' ),
					'type' => 'checkbox',
				),
				array(
					'id' => 'edd_order_details_tweaks_header',
					'name' => '<strong>' . __( 'Tweaks', 'edd-order-details' ) . '</strong>',
					'type' => 'header',
				),
				array(
					'id' => 'edd_order_details_enable_ids',
					'name' => __( 'Enable Unique Identifiers', 'edd-order-details' ),
					'desc' => __( 'Check to enable Download, Customer and Commission IDs on the exported CSV file. Useful for debugging and support purposes.', 'edd-order-details' ),
					'type' => 'checkbox',
				),
				array(
					'id' => 'edd_order_details_enable_username',
					'name' => __( 'Enable Username', 'edd-order-details' ),
					'desc' => __( 'Check to enable username on the exported CSV file. Useful for debugging and support purposes.', 'edd-order-details' ),
					'type' => 'checkbox',
				),
        array(
          'id' => 'edd_order_details_enable_service_fee',
          'name' => __( 'Enable Service Fee', 'edd-order-details' ),
          'desc' => __( 'Check to enable an optional “Service Fee” column which calculates the commission amount the store charges its vendors for facilitating the transaction (similar to Envato, Etsy, etc.).', 'edd-order-details' ),
          'type' => 'checkbox',
					'tooltip_title' => __( 'Store Service Fees', 'edd-order-details' ),
					'tooltip_desc'  => sprintf( __( 'The service fee calculation is based on the Easy Digital Downloads - Commissions Calculation Base setting.', 'edd-order-details' ) ),
        ),
			);

			$edd_order_details_settings = apply_filters( 'edd_order_details_settings', $edd_order_details_settings );

			if ( version_compare( EDD_VERSION, 2.5, '>=' ) ) {
				$edd_order_details_settings = array( 'order_details' => $edd_order_details_settings );
			}

			return array_merge( $settings, $edd_order_details_settings );
		}

	}
}


/**
 * Loads a single instance of EDD Order Details
 *
 * This follows the PHP singleton design pattern.
 *
 * Use this function like you would a global variable, except without needing
 * to declare the global.
 *
 * @example <?php $edd_order_details_exporter = edd_order_details_exporter(); ?>
 *
 * @since 1.0.0
 *
 * @see EDD_Order_Details::get_instance()
 *
 * @return object Returns an instance of the EDD_Order_Details class
 */
function edd_order_details_exporter() {
	return EDD_Order_Details::get_instance();
}


/**
 * Loads plugin after all the others have loaded and have registered their hooks and filters
 *
 * @since 1.0.0
 */
add_action( 'plugins_loaded', 'edd_order_details_exporter', apply_filters( 'edd_order_details_exporter_action_priority', 10 ) );
