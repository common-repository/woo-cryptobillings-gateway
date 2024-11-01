<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

/**
 * WC_Cryptobillings_API class.
 *
 * Communicates with Cryptobillings API.
 */
class WC_Cryptobillings_API {

	/**
	 * API Key.
	 * @var string
	 */
	private static $api_key = '';

	/**
	 * Set API Key.
	 * @param string $key
	 */
	public static function set_api_key( $api_key ) {
		self::$api_key = $api_key;
	}

	/**
	 * Get api key.
	 * @return string
	 */
	public static function get_api_key() {
		if ( ! self::$api_key ) {
			$options = get_option( 'woocommerce_cryptobillings_settings' );

			if ( isset(  $options['api_key'] ) ) {
				self::set_api_key( $options['api_key'] );
			}
		}
		return self::$api_key;
	}
	
	/**
	 * Get selected coins
	 * #return array
	 */
	public static function get_allowed_coins( $supported , $options ) {
		
		$allowed_coins = array();
		if(empty( $options )) {
			return $supported;
		}
		$flag = false;
		foreach( $options as $coins ) {
			if( isset( $supported[$coins] ) ) {
				$allowed_coins[$coins] = $supported[$coins];
				if(!$flag)  {
					$allowed_coins[$coins] += array('default'=>true);
					$flag = true;
				}
			}
		}
		return $allowed_coins;
	}		
	
	/**
	 * Create encrypted key
	 * #return string
	 */
	public static function create_encrypt_key( $api_key , $currency, $order_total , $order_id ) {
		$encrypt_params = $api_key.$order_id.$currency.$order_total.$order_id;
		$encrypt_params = sha1($encrypt_params);
		return $encrypt_params;
	}
	
	
	/**
	 * Check encryption
	 * #return bool
	 */
	public static function check_request( $api_key , $currency, $order_total , $order_id,  $key ) {
		$encrypt_key = self::create_encrypt_key( $api_key , $currency, $order_total , $order_id );
		if( $encrypt_key == $key ) {
			return true;
		}
		return false;
	}
	
	

}
