<?php

// Exit if accessed directly
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

if ( ! class_exists('EDD_Export') ) {
    require_once EDD_PLUGIN_DIR . 'includes/admin/reporting/class-export.php';
}

/**
 * Class EDD_Orders_Export
 */
class EDD_Orders_Export extends EDD_Export {

    /**
     * @var string
     */
    public $export_type = 'orders';

    /**
     * @var int
     */
    public $user_id = 0;

    /**
     * @var int
     */
    public $year = 0;

    /**
     * @var int
     */
    public $month = 0;

    /**
     * @return bool
     */
    public function can_export() {
        return true;
    }


    /**
     * Construct the export header.
     *
     * @since 1.0.0
     * @access public
     */
    public function headers() {

        ignore_user_abort( true );

        if ( ! edd_is_func_disabled('set_time_limit') && !ini_get('safe_mode') )
            set_time_limit(0);

        nocache_headers();
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename=edd-export-' . $this->export_type . '-' . $this->year . '-' . $this->month . '.csv');
        header("Expires: 0");

    }


    /**
     * Build the header export array.
     *
     * @since 1.0.0
     * @access public
     *
     * @return array
     */
    public function csv_cols() {

        $cols = array(
            'payment_id' 				=> __('Payment ID', 'edd-order-details'),
            'date_time'					=> __('Date/Time', 'edd-order-details'),
            'fullname' 					=> __('Full Name', 'edd-order-details'),
            'email' 						=> __('Email', 'edd-order-details'),
            'customer_id' 			=> __('Customer ID', 'edd-order-details'),
            'username' 					=> __('Username', 'edd-order-details'),
            'user' 							=> __('User', 'edd-order-details'),
            'street_address_1' 	=> __('Street Address Line 1', 'edd-order-details'),
            'street_address_2' 	=> __('Street Address Line 2', 'edd-order-details'),
            'city' 							=> __('City', 'edd-order-details'),
            'zip' 							=> __('Zip / Postal Code', 'edd-order-details'),
            'country_name' 			=> __('Country Name', 'edd-order-details'),
            'state' 						=> __('State / Province', 'edd-order-details'),
            'product_id' 				=> __('Download ID', 'edd-order-details'),
            'product' 					=> __('Download Title', 'edd-order-details'),
            'status' 						=> __('Payment Status', 'edd-order-details'),
            'discount_code' 		=> __('Discount Code', 'edd-order-details'),
            'item_price' 				=> __('Price', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'item_quantity' 		=> __('Quantity', 'edd-order-details'),
            'discount_amount' 	=> __('Discount', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'subtotal' 					=> __('Subtotal', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'tax_rate' 					=> __('Tax Rate (%)', 'edd-order-details'),
            'tax' 							=> __('Tax', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'total' 						=> __('Total', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'payment_gateway' 	=> __('Payment Gateway', 'edd-order-details'),
            'currency' 					=> __('Currency', 'edd-order-details'),
            'purchase_key' 			=> __('Purchase Key', 'edd-order-details'),
            'transaction_id' 		=> __('Transaction ID', 'edd-order-details'),
            'service_fee' 			=> __('Seller Fee', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'commission_amount' => __('Commission Amount', 'edd-order-details') . ' (' . html_entity_decode(edd_currency_filter('')) . ')',
            'commission_rate' 	=> __('Commission Rate', 'edd-order-details'),
            'commission_status' => __('Commission Status', 'edd-order-details'),
            'commission_id' 		=> __('Commission ID', 'edd-order-details'),
            'order_notes' 			=> __('Order Notes', 'edd-order-details'),
            'site' 							=> __('Site', 'edd-order-details')
        );

        // Get our options
        $disable_customer_data 			= edd_get_option( 'edd_order_details_disable_customer_data', '' );
        $disable_payment_meta 			= edd_get_option( 'edd_order_details_disable_payment_meta', '' );
        $disable_tax_data 					= edd_get_option( 'edd_order_details_disable_tax_data', '' );
        $disable_commission_data 		= edd_get_option( 'edd_order_details_disable_commission_data', '' );
        $enable_unique_identifiers 	= edd_get_option( 'edd_order_details_enable_ids', '' );
        $enable_service_fee 				= edd_get_option( 'edd_order_details_enable_service_fee', '' );
        $enable_username 						= edd_get_option( 'edd_order_details_enable_username', '' );

        // Remove tax related columns if not enabled
        if ( ! edd_use_taxes() ) {
          unset( $cols['tax_rate'] );
          unset( $cols['tax'] );
        }

        // Remove Quantity column if not enabled
        if ( ! edd_item_quantities_enabled() ) {
          unset( $cols['item_quantity'] );
        }

        // Remove EDD Commissions columns if inactive
        if ( ! class_exists( 'EDD_Commission' ) ) {
          unset( $cols['seller_fee'] );
          unset( $cols['commission_amount'] );
          unset( $cols['commission_rate'] );
          unset( $cols['commission_status'] );
        }

        // Remove Order Notes column if inactive
        if( ! function_exists( 'edd_order_notes_view_order_details' ) ) {
          unset( $cols['order_notes'] );
        }

        // Remove identifiable customer data
        if ( $disable_customer_data ) {
          unset( $cols['fullname'] );
          unset( $cols['email'] );
          unset( $cols['street_address_1'] );
          unset( $cols['street_address_2'] );
          unset( $cols['city'] );
          unset( $cols['zip'] );
          unset( $cols['user'] );
        }

        // Remove payment metadata
        if ( $disable_payment_meta ) {
          unset( $cols['payment_gateway'] );
          unset( $cols['purchase_key'] );
          unset( $cols['transaction_id'] );
        }

        // Remove tax data
        if ( $disable_tax_data ) {
          unset( $cols['tax_rate'] );
          unset( $cols['tax'] );
        }

        // Remove commission data
        if ( $disable_commission_data ) {
          unset( $cols['commission_amount'] );
          unset( $cols['commission_rate'] );
          unset( $cols['commission_status'] );
        }

        // Disable service if not required
        if ( ! $enable_service_fee ) {
          unset( $cols['service_fee'] );
        }

        // Disable unique identifiers if not required
        if ( ! $enable_unique_identifiers ) {
          unset( $cols['product_id'] );
          unset( $cols['commission_id'] );
          unset( $cols['customer_id'] );
        }

        // (Optional) Username column
        if ( ! $enable_username ) {
          unset( $cols['username'] );
        }

        return $cols;
    }


    /**
     * Build the export data array.
     *
     * Constructs the export data array including
     * customer information, order/product data, etc.
     *
     * @since 1.0.0
     * @access public
     *
     * @return array The array of matching orders.
     */
    public function get_data() {

        $data = array();
        $args = array(
            'year'			=> ! empty( $this->year ) ? $this->year : date('Y'),
            'monthnum' 	=> ! empty( $this->month ) ? $this->month : date('n')
        );

        $orders = $this->custom_get_all_orders( get_current_user_id(), array() );
        $orders = $this->filter_list( $orders, $args );

        if ( class_exists( 'EDD_Commission' ) ) {
          $calc_base = edd_get_option( 'edd_commissions_calc_base', 'subtotal' );
        }

        if ( $orders ) {

            foreach ( $orders as $order ) {
                $order_info   = get_post_meta( $order->ID );

                $payment_meta = edd_get_payment_meta( $order->ID );

                $user_info    = edd_get_payment_meta_user_info( $order->ID );

                $user_id      = isset( $user_info['id'] ) && $user_info['id'] != -1 ? $user_info['id'] : $user_info['email'];

                $payment = new EDD_Payment( $order->ID );

                $cart_details = $payment_meta['cart_details'];

                foreach ( $cart_details as $cart_detail ) {

                    if ( isset( $cart_detail ) ) {

                        if ( get_post( (int) $cart_detail['id'] )->post_author == get_current_user_id() ) {

                            $variation                = '';
                            $price_id                 = '';

                            $item['payment_id']       = $order->ID;
                            $item['date_time']        = $order->post_date;
                            $item['fullname']         = $payment_meta['user_info']['first_name'] . ' ' . $payment_meta['user_info']['last_name'];
                            $item['email']            = $payment_meta['email'];
                            $item['customer_id']      = $payment->customer_id;
                            $item['username']         = get_user_by('email', $payment_meta['email'])->user_login;

                            if ( is_numeric( $user_id ) ) {
                              $user = get_userdata( $user_id );
                            } else {
                              $user = false;
                            }

                            $item['user']             = $user ? $user->display_name : __( 'guest', 'edd-order-details' );

                            $item['street_address_1'] = isset( $payment_meta['user_info']['address']['line1'] )   ? $payment_meta['user_info']['address']['line1']   : '';
                            $item['street_address_2'] = isset( $payment_meta['user_info']['address']['line2'] )   ? $payment_meta['user_info']['address']['line2']   : '';
                            $item['city']             = isset( $payment_meta['user_info']['address']['city'] )    ? $payment_meta['user_info']['address']['city']    : '';
                            $item['zip']              = isset( $payment_meta['user_info']['address']['zip'] )     ? $payment_meta['user_info']['address']['zip']     : '';

                            $country_code             = isset( $payment_meta['user_info']['address']['country'] ) ? $payment_meta['user_info']['address']['country'] : '';
                            $state_list               = edd_get_shop_states($country_code);
                            $item['country_name']     = edd_get_country_list()[$country_code];
                            $item['state']            = $state_list[$payment_meta['user_info']['address']['state']];

                            if (empty($item['state'])) {
                                $item['state'] = $payment_meta['user_info']['address']['state'];
                            }

                            $download_id = $cart_detail['id'];
                            $download = $cart_detail['item_number'];
                            $item['product_id'] = $download_id;

                            if ( edd_has_variable_prices( $download_id ) && edd_get_cart_item_price_id( $download ) != null) {
                                $price_id        = edd_get_cart_item_price_id( $download );
                                $variation       = edd_get_price_option_name( $download_id, $price_id, $order->ID );
                                $item['product'] = $cart_detail['name'] . ' - ' . $variation;
                            } else {
                                $item['product'] = $cart_detail['name'];
                            }

                            $item['status']          = ( edd_get_payment_status( $order->ID ) === 'publish' ? 'Completed' : edd_get_payment_status( $order->ID ) );
                            $item['discount_code']   = isset( $user_info['discount'] ) && $user_info['discount'] != 'none' ? $user_info['discount'] : __( 'None', 'edd-order-details' );
                            $item['item_price']      = html_entity_decode( edd_format_amount( $cart_detail['item_price'] ) );
                            $item['item_quantity']   = $cart_detail['quantity'];
                            $item['discount_amount'] = ( $cart_detail['discount'] == '0' ? 'None' : $cart_detail['discount'] );
                            $item['subtotal']        = html_entity_decode( edd_format_amount( $cart_detail['subtotal'] ) );
                            $item['tax_rate']        = ( $order_info['_edd_payment_tax_rate'][0] * 100 ) . '%';
                            $item['tax']             = html_entity_decode( edd_format_amount( $cart_detail['tax'] ) );
                            $item['total']           = html_entity_decode( edd_format_amount( $cart_detail['price'] ) );

                            // Payment Meta
                            $item['payment_gateway'] = edd_get_gateway_admin_label( edd_get_payment_meta( $order->ID, '_edd_payment_gateway', true ) );
                            $item['currency']        = $payment_meta['currency'];
                            $item['purchase_key']    = $order_info['_edd_payment_purchase_key'][0];
                            $item['transaction_id']  = edd_get_payment_transaction_id( $order->ID );

                            if ( class_exists( 'EDD_Commission' ) ) {

                              $commission = $this->get_user_commission( get_current_user_id(), $download_id, $order->ID, array(
                                  'year' => $this->year,
                                  'monthnum' => $this->month
                              ), $variation );

                              $commission_info = get_post_meta( $commission->ID, '_edd_commission_info', true );

                              if ( isset($commission_info['amount'] ) && $commission_info['amount'] != 0 ) {

                                // Service Fee (based on Commissions Calc Base)
                                switch ( $calc_base ) {
                            			case 'subtotal':
                                    // Commissions Calc Base - Subtotal
                                    $item['service_fee'] = number_format(round((float) $cart_detail['subtotal'] - $commission_info['amount'], 2), 2);
                            				break;
                            			case 'total_pre_tax':
                                    // Commissions Calc Base - Total without Taxes
                                    $item['service_fee'] = number_format(round((float) $cart_detail['price'] - $cart_detail['tax'] - $commission_info['amount'], 2), 2);
                            				break;
                            			default:
                                    // Commissions Calc Base - Total with Taxes
                                    $item['service_fee'] = number_format(round((float) $cart_detail['price'] - $commission_info['amount'], 2), 2);
                            				break;
                            		}
                              } else {
                                  $item['service_fee'] = number_format( 0, 2 );
                              }

                              // Commission Data
                              $item['commission_amount'] = html_entity_decode( edd_format_amount( $commission_info['amount'] ) );
                              $item['commission_rate']   = ( $commission && isset( $commission_info['rate'] ) ) ? $commission_info['rate'] . '%' : '0';
                              $item['commission_status'] = ( $commission && isset( $commission_info['rate'] ) ) ? eddc_get_commission_status( $commission->ID ) : 'None';
                              $item['commission_id']     = ( $commission && isset( $commission->ID ) ) ? $commission->ID : 'None';

                            }

                            // Order Note Data
                            $order_notes 								 = isset($payment_meta['order_notes']) ? $payment_meta['order_notes'] : 'none';
                            $order_notes_stripped 			 = preg_replace( "/\r|\n/", " ", $order_notes );
                            $item['order_notes'] 				 = $order_notes_stripped;

                            // Site Name
                            $item['site']              = get_bloginfo('name');

                            $data[] = $item;
                        }
                    }


                }
            }
        }

        return $data;
    }


    /**
     * Get vendor orders by user id.
     *
     * Return all of the orders of
     * a particular vendor with specific order
     * status(es).
     *
     * @since 1.0.0
     * @access public
     *
     * @param int     $user_id The user id.
     * @param array   $args  Args to allow overriding of. Currently only number.
     * @return array The array of matching orders.
     */
    public function custom_get_all_orders( $user_id = 0, $args = array() ) {
        $published_products = EDD_FES()->vendors->get_published_products( $user_id );
        if ( ! $published_products ) {
            return array();
        }

        $published_products = wp_list_pluck( $published_products, 'ID' );

        $args = array(
            'download' 	=> $published_products,
            'output' 	 	=> 'edd_payment',
            'mode' 			=> 'all',
            'number' 		=> -1,
            'orderby' 	=> 'post_date',
            'order' 		=> 'DESC'
        );

        $payments = edd_get_payments( $args );

        if ( ! $payments ) {
            return array();
        }

        // nothing fancy with this for now
        return $payments;
    }

    /**
     * Filter orders by year and month.
     *
     * Return the number of orders of
     * a particular vendor with specific order
     * status(es).
     *
     * @since 1.0.0
     * @access private
     *
     * Filter list by year and month
     * @param $list
     * @param $query
     * @return array
     */
    private function filter_list( $list, $query ) {

        if ( ! isset($query['year'], $query['monthnum'] ) )
            return $list;

        $new = array();
        foreach ( $list as $item ) {
            $post_time = strtotime( $item->post_date );
            if ( date('Y', $post_time ) == $query['year'] && date( 'n', $post_time ) == $query['monthnum']) {
                $new[] = $item;
            }
        }

        return $new;
    }

    /**
     * Return correct commission ID for the order item.
     *
     * Return the correct commission id by looping
     * through the cart/order product variations.
     *
     * @since 1.0.0
     * @access private
     *
     * @param int     $user_id The user id.
     * @param int     $download_id The download id.
     * @param int     $download_id The order id.
     * @param array   $args The query arguments.
     * @param array   $variation The product variation name.
     * @return int    $commission The commission id.
     */
    private function get_user_commission( $user_id, $download_id, $order_id, $args, $variation ) {
        $commissions = eddc_get_commissions( array(
            'user_id' 		=> $user_id,
            'number' 			=> -1,
            'query_args' 	=> $args
        ) );

        foreach ( $commissions as $commission ) {
            $commission_payment_id = get_post_meta( $commission->ID, '_edd_commission_payment_id', true );
            if ( (int) $commission_payment_id == (int) $order_id ) {
                $commission_download_id = get_post_meta( $commission->ID, '_download_id', true );
                if ( (int) $commission_download_id == (int) $download_id ) {
                    $commission_download_variation = get_post_meta( $commission->ID, '_edd_commission_download_variation', true );
                    if ( ! empty( $commission_download_variation ) ) {
                        if ( $commission_download_variation == $variation ) {
                            return $commission;
                        }
                    } else {
                        return $commission;
                    }
                }
            }
        }
    }

}
