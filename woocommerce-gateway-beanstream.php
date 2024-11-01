<?php
/*
Plugin Name: WooCommerce - Beanstream Payment
Plugin URI: http://www.wptricksbook.com/
Description: Beanstream Canedian Payment Gateway for WooCommerce. Direct pay by credit card on website without leaving shop. Please sure your shop currency is in CAD(canadian doller).
Version: 1.0
Author: Alok Tiwari
Author URI: http://www.wptricks.com/
License: GNU General Public License v3.0
License URI: http://www.gnu.org/licenses/gpl-3.0.html
*/
add_action('plugins_loaded', 'woocommerce_beanstream_init', 0);
define('beanstream_imgdir', WP_PLUGIN_URL . "/" . plugin_basename(dirname(__FILE__)) . '/assets/img/');
function woocommerce_beanstream_init(){
    if(!class_exists('WC_Payment_Gateway')) return;
    
    if( isset($_GET['msg']) && !empty($_GET['msg']) ){
        add_action('the_content', 'beanstream_showMessage');
    }
    function beanstream_showMessage($content){
            return '<div class="'.htmlentities($_GET['typ   e']).'">'.htmlentities(urldecode($_GET['msg'])).'</div>'.$content;
    }
    
    /**
     * Gateway class
     */
        class WC_beanstream extends WC_Payment_Gateway{
            
                var $avaiable_cards = array(
				'Visa',
				'MasterCard',
				'Discover',
				'American Express'
		);
            
                public function __construct(){
			$this->id 					= 'beanstream';
			$this->method_title 		= 'Bean Stream Payment';
			$this->method_description	= "Redefining Payments, Simplifying Lives";
			$this->has_fields 			= false;
			$this->init_form_fields();
			$this->init_settings();
			if ( $this->settings['showlogo'] == "yes" ) {
				$this->icon 			= beanstream_imgdir . 'logo.gif';
			}			
			$this->title 			= $this->settings['title'];
			$this->redirect_page_id = $this->settings['redirect_page_id'];
			if ( $this->settings['testmode'] == "yes" ) {
				$this->liveurl 			= 'https://www.beanstream.com/api/v1/payments';
				$this->merchant_id 		= "300200722";
				$this->passcode 			= "E8fFffB8c5544D1aAc1b4364679aC319";
				$this->description 		= $this->settings['description'].
										"<br/><br/><u>Test Mode is <strong>ACTIVE</strong>, use following Credit Card details:-</u><br/>".
										"Test Card Name: <strong><em style='#999999;'>any name</em></strong><br/>".
										"Test Card Number: <strong>5100000010001004</strong><br/>".
										"Test Card CVV: <strong>123</strong><br/>".
										"Test Card Expiry: <strong>May 2017</strong><br/>";
			} else {
				$this->liveurl 			= 'https://www.beanstream.com/api/v1/payments';
				$this->merchant_id 		= $this->settings['merchant_id'];
				$this->passcode 		= $this->settings['passcode'];
				$this->description 		= $this->settings['description'];
			}					
			$this->msg['message'] 	= "";
			$this->msg['class'] 	= "";
					
			add_action('init', array(&$this, 'check_beanstream_response'));
			//update for woocommerce >2.0
			add_action( 'woocommerce_api_' . strtolower( get_class( $this ) ), array( $this, 'check_beanstream_response' ) );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.0.0', '>=' ) ) {
				/* 2.0.0 */
				add_action( 'woocommerce_update_options_payment_gateways_' . $this->id, array( &$this, 'process_admin_options' ) );
			} else {
				/* 1.6.6 */
				add_action( 'woocommerce_update_options_payment_gateways', array( &$this, 'process_admin_options' ) );
			}
			
			add_action('woocommerce_receipt_beanstream', array(&$this, 'receipt_page'));
		}
            
        
        function init_form_fields(){
			$this->form_fields = array(
				'enabled' => array(
					'title' 		=> __('Enable/Disable', 'woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Beanstream Payment Module.', 'woocommerce'),
					'default' 		=> 'no',
					'description' 	=> 'Show in the Payment List as a payment option'
				),
      			'title' => array(
					'title' 		=> __('Title:', 'woocommerce'),
					'type'			=> 'text',
					'default' 		=> __('Beanstream Canadian Payment Gateway', 'woocommerce'),
					'description' 	=> __('This controls the title which the user sees during checkout.', 'woocommerce'),
					'desc_tip' 		=> true
				),
      			'description' => array(
					'title' 		=> __('Description:', 'woocommerce'),
					'type' 			=> 'textarea',
					'default' 		=> __('Pay securely by Credit Card through Beanstream Servers Canadian Payment Gateway.', 'woocommerce'),
					'description' 	=> __('This controls the description which the user sees during checkout.', 'woocommerce'),
					'desc_tip' 		=> true
				),
      			'merchant_id' => array(
					'title' 		=> __('Merchant Id', 'woocommerce'),
					'type' 			=> 'text',
					'description' 	=> __('Given to Merchant by Beanstream'),
					'desc_tip' 		=> true
				),
      			'passcode' => array(
					'title' 		=> __('Merchant Passcode', 'woocommerce'),
					'type' 			=> 'text',
					'description' 	=>  __('Given to Merchant by Beanstream', 'woocommerce'),
					'desc_tip' 		=> true
                ),
				'showlogo' => array(
					'title' 		=> __('Show Logo', 'woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Show the "Beanstream" logo in the Payment Method section for the user', 'woocommerce'),
					'default' 		=> 'yes',
					'description' 	=> __('Tick to show "Beanstream" logo'),
					'desc_tip' 		=> true
                ),
      			'testmode' => array(
					'title' 		=> __('TEST Mode', 'woocommerce'),
					'type' 			=> 'checkbox',
					'label' 		=> __('Enable Bean Stream Canadian Payment TEST Transactions.', 'woocommerce'),
					'default' 		=> 'no',
					'description' 	=> __('Tick to run TEST Transaction on the Beanstream canadian payment platform'),
					'desc_tip' 		=> true
                )
			);
		}
                
         /**
         * Admin Panel Options
         * - Options for bits like 'title' and availability on a country-by-country basis
         **/
                public function admin_options(){
			echo '<h3>'.__('BeanStream Payment', 'woocommerce').'</h3>';
			echo '<p>'.__('Redefining Payments, Simplifying Lives! Empowering any business to collect money online within minutes').'</p>';
			echo '<table class="form-table">';
			// Generate the HTML For the settings form.
			$this->generate_settings_html();
			echo '</table>';
		}
         /**
         *  There are no payment fields for techpro, but we want to show the description if set.
         **/
                function payment_fields(){
//			if($this->description) echo wpautop(wptexturize($this->description));
                     $available_cards = $this->avaiable_cards;
                    ?>
                    <?php if ($this->testmode=='yes') : ?><p><?php _e('TEST MODE/SANDBOX ENABLED', 'woocommerce'); ?></p><?php endif; ?>
			<?php if ($this->description) : ?><p><?php echo $this->description; ?></p><?php endif; ?>
			<fieldset>
                                <p class="form-row form-row-first">
					<label for="beanstream_cart_name"><?php echo __("Owner Name", 'woocommerce') ?> <span class="required">*</span></label>
					<input type="text" class="input-text" name="beanstream_card_name" />
				</p>
				<p class="form-row form-row-first">
					<label for="beanstream_cart_number"><?php echo __("Credit Card number", 'woocommerce') ?> <span class="required">*</span></label>
					<input type="text" class="input-text" name="beanstream_card_number" />
				</p>
				<p class="form-row form-row-last">
					<label for="beanstream_cart_type"><?php echo __("Card type", 'woocommerce') ?> <span class="required">*</span></label>
					<select id="beanstream_card_type" name="beanstream_card_type">
						<?php foreach ($available_cards as $card) : ?>
									<option value="<?php echo $card ?>"><?php echo $card; ?></options>
						<?php endforeach; ?>
					</select>
				</p>
				<div class="clear"></div>
				<p class="form-row form-row-first">
					<label for="cc-expire-month"><?php echo __("Expiration date", 'woocommerce') ?> <span class="required">*</span></label>
					<select name="beanstream_card_expiration_month" id="cc-expire-month">
						<option value=""><?php _e('Month', 'woocommerce') ?></option>
						<?php
							$months = array();
							for ($i = 1; $i <= 12; $i++) {
							    $timestamp = mktime(0, 0, 0, $i, 1);
							    $months[date('m', $timestamp)] = date('F', $timestamp);
							}
							foreach ($months as $num => $name) {
					            printf('<option value="%s">%s</option>', $num, $name);
					        }
					        
						?>
					</select>
					<select name="beanstream_card_expiration_year" id="cc-expire-year">
						<option value=""><?php _e('Year', 'woocommerce') ?></option>
						<?php
							$years = array();
							for ($i = date('Y'); $i <= date('Y') + 15; $i++) {
							    printf('<option value="%u">%u</option>', $i, $i);
							}
						?>
					</select>
				</p>
				<p class="form-row form-row-last">
					<label for="beanstream_card_csc"><?php _e("Card security code", 'woocommerce') ?> <span class="required">*</span></label>
					<input type="text" class="input-text" id="beanstream_card_csc" name="beanstream_card_csc" maxlength="4" style="width:45px" />
					<span class="help payjunction_card_csc_description"></span>
				</p>
				<div class="clear"></div>
			</fieldset>
			<script type="text/javascript">
			
				function toggle_csc() {
					var card_type = $("#beanstream_card_type").val();
					var csc = $("#beanstream_card_csc").parent();
			
					if(card_type == "Visa" || card_type == "MasterCard" || card_type == "Discover" || card_type == "American Express" ) {
						csc.fadeIn("fast");
					} else {
						csc.fadeOut("fast");
					}
					
					if(card_type == "Visa" || card_type == "MasterCard" || card_type == "Discover") {
						$('.beanstream_card_csc_description').text("<?php _e('3 digits usually found on the back of the card.', 'woocommerce'); ?>");
					} else if ( cardType == "American Express" ) {
						$('.beanstream_card_csc_description').text("<?php _e('4 digits usually found on the front of the card.', 'woocommerce'); ?>");
					} else {
						$('.beanstream_card_csc_description').text('');
					}
				}
			
				$("#beanstream_card_type").change(function(){
					toggle_csc();
				}).change();
			
			</script>
                        <?php
		}
               
                
                /**
		* Process the payment and return the result
		**/
		function process_payment($order_id){
			global $woocommerce;
			$order = new WC_Order( $order_id );
			
			if ( version_compare( WOOCOMMERCE_VERSION, '2.1.0', '>=' ) ) {
				/* 2.1.0 */
				$checkout_payment_url = $order->get_checkout_payment_url( true );
			} else {
				/* 2.0.0 */
				$checkout_payment_url = get_permalink( get_option ( 'woocommerce_pay_page_id' ) );
			}
                        			
			
			$card_name		= isset($_POST['beanstream_card_name']) ? $_POST['beanstream_card_name'] : '';
			$card_type 		= isset($_POST['beanstream_card_type']) ? $_POST['beanstream_card_type'] : '';
			$card_number 		= isset($_POST['beanstream_card_number']) ? $_POST['beanstream_card_number'] : '';
			$card_csc 		= isset($_POST['beanstream_card_csc']) ? $_POST['beanstream_card_csc'] : '';
			$card_exp_month		= isset($_POST['beanstream_card_expiration_month']) ? $_POST['beanstream_card_expiration_month'] : '';
			$card_exp_year 		= isset($_POST['beanstream_card_expiration_year']) ? $_POST['beanstream_card_expiration_year'] : '';
	
			// Format Expiration Year			
			$expirationYear = substr($card_exp_year, -2);
			
			// Format card number
			$card_number = str_replace(array(' ', '-'), '', $card_number);
                        
                        // Validate plugin settings
			if (!$this->validate_settings()) :
				$cancelNote = __('Order was cancelled due to invalid settings (check your API credentials and make sure your currency is supported).', 'woocommerce');
				$order->add_order_note( $cancelNote );
		
				$woocommerce->add_error(__('Payment was rejected due to configuration error.', 'woocommerce'));
				return false;
			endif;
                        
			// Send request to payjunction
			try {
				$url = $this->liveurl;
				if ($this->testmode == 'yes') :
					$url = $this->testurl;
				endif;
	
				$req = curl_init($url);

                                $merchantId = $this->merchant_id;
                                $passcode = $this->passcode;
                                $auth = base64_encode( $merchantId.":".$passcode );

                                $headers = array(
                                        'Content-Type: application/json',
                                        'Authorization: Passcode '.$auth
                                );
				
                                curl_setopt($req,CURLOPT_POST, true);
                                curl_setopt($req,CURLOPT_HTTPHEADER, $headers);
                                curl_setopt($req,CURLOPT_RETURNTRANSFER, true);
                                curl_setopt($req,CURLOPT_FAILONERROR, true);
                                curl_setopt($req, CURLOPT_SSL_VERIFYPEER, false);
                                
                                $request = array(
                                            'merchant_id' => $merchantId,
                                            'order_number' => $order_id.uniqid(),
                                            'amount' => $order->order_total,
                                            'payment_method' => 'card',
                                            'card' => array(
                                                    'name' => $card_name,
                                                    'number' => $card_number,
                                                    'expiry_month' => $card_exp_month,
                                                    'expiry_year' => $expirationYear,
                                                    'cvd' => $card_csc
                                            )
                                );
                                
                                curl_setopt($req,CURLOPT_POSTFIELDS, json_encode($request));
                                $response = curl_exec($req);
                                
			} catch(Exception $e) {
				$woocommerce->add_error(__('There was a connection error', 'woocommerce') . ': "' . $e->getMessage() . '"');
				return;
			}
                        
                        if (strpos($response,"approved"))
                        {
                                $result = json_decode($response);
                                $order->add_order_note( __('Beanstream payment completed', 'woocommerce') . ' (Transaction ID: ' . $result->id . ')' );
				$order->payment_complete();
                                
                                $woocommerce->cart->empty_cart();
                                
                                // Return thank you page redirect
				return array(
					'result' 	=> 'success',
					'redirect'	=> add_query_arg('key', $order->order_key, add_query_arg('order', $order_id, $this->get_return_url($order)))
				);
                        }
                        else
                        {
                                $result = json_decode($response);
                                
                                $cancelNote = __('Beanstream payment failed', 'woocommerce');
	
				$order->add_order_note( $cancelNote );
				
				$woocommerce->add_error(__('Payment error', 'woocommerce') . ': '.$result->message.'');
                        }
		}
                
                
                /**
	     * Validate the payment form
	     */
		function validate_fields() {
			global $woocommerce;
												
			$billing_country 	= isset($_POST['billing_country']) ? $_POST['billing_country'] : '';
                        $card_name		= isset($_POST['beanstream_card_name']) ? $_POST['beanstream_card_name'] : '';
			$card_type 		= isset($_POST['beanstream_card_type']) ? $_POST['beanstream_card_type'] : '';
			$card_number 		= isset($_POST['beanstream_card_number']) ? $_POST['beanstream_card_number'] : '';
			$card_csc 			= isset($_POST['beanstream_card_csc']) ? $_POST['beanstream_card_csc'] : '';
			$card_exp_month		= isset($_POST['beanstream_card_expiration_month']) ? $_POST['beanstream_card_expiration_month'] : '';
			$card_exp_year 		= isset($_POST['beanstream_card_expiration_year']) ? $_POST['beanstream_card_expiration_year'] : '';
	
	
			// Check card security code
			if(!ctype_digit($card_csc)) {
				$woocommerce->add_error(__('Card security code is invalid (only digits are allowed)', 'woocommerce'));
                                $error = 1;
			}
	
			if((strlen($card_csc) != 3 && in_array($card_type, array('Visa', 'MasterCard', 'Discover'))) || (strlen($card_csc) != 4 && $card_type == 'American Express')) {
				$woocommerce->add_error(__('Card security code is invalid (wrong length)', 'woocommerce'));
                                $error = 1;
			}
	
			// Check card expiration data
			if(!ctype_digit($card_exp_month) || !ctype_digit($card_exp_year) ||
				 $card_exp_month > 12 ||
				 $card_exp_month < 1 ||
				 $card_exp_year < date('Y') ||
				 $card_exp_year > date('Y') + 20
			) {
				$woocommerce->add_error(__('Card expiration date is invalid', 'woocommerce'));
                                $error = 1;
			}
	
			// Check card number
			$card_number = str_replace(array(' ', '-'), '', $card_number);
	
			if(empty($card_number) || !ctype_digit($card_number)) {
				$woocommerce->add_error(__('Card number is invalid', 'woocommerce'));
                                $error = 1;
			}
                        
                        if(empty($card_name))
                        {
                            $woocommerce->add_error(__('Card name is invalid', 'woocommerce'));
                            $error = 1;
                        }
                        
                        if(!isset($error) && $error != 1)
                        {
                            return true;
                        }
                        else 
                        {
                            return false;
                        }
		}
                
                /**
	     * Validate plugin settings
	     */
		function validate_settings() {
			$currency = get_option('woocommerce_currency');
                        
			if (!in_array($currency, array('CAD'))) {
				return false;
			}
	
			return true;
		}
                
		
		}
		/**
		* Add the Gateway to WooCommerce
		**/
		function woocommerce_add_beanstream_gateway($methods) {
			$methods[] = 'WC_beanstream';
			return $methods;
		}

		add_filter('woocommerce_payment_gateways', 'woocommerce_add_beanstream_gateway' );
}
?>