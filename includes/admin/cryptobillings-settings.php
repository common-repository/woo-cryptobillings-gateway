<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

return apply_filters( 'wc_cryptobillings_settings',
	array(
		'enabled' => array(
			'title'       => __( 'Enable/Disable', 'woocommerce-cryptobillings-gateway' ),
			'label'       => __( 'Enable Crypto Billings', 'woocommerce-cryptobillings-gateway' ),
			'type'        => 'checkbox',
			'description' => '',
			'default'     => 'yes',
		),
		'title' => array(
			'title'       => __( 'Title', 'woocommerce-cryptobillings-gateway' ),
			'type'        => 'text',
			'description' => __( 'This controls the title which the user sees during checkout.', 'woocommerce-cryptobillings-gateway' ),
			'default'     => __( 'Crypto Billings', 'woocommerce-cryptobillings-gateway' ),
			'desc_tip'    => true,
		),
		'description' => array(
			'title'       => __( 'Description', 'woocommerce-cryptobillings-gateway' ),
			'type'        => 'text',
			'description' => __( 'This controls the description which the user sees during checkout.', 'woocommerce-cryptobillings-gateway' ),
			'default'     => __( 'Pay with crypto via Crypto Billings.', 'woocommerce-cryptobillings-gateway' ),
			'desc_tip'    => true,
		),
		'instructions' => array(
			'title'       => __( 'Instructions', 'woocommerce-cryptobillings-gateway' ),
			'type'        => 'textarea',
			'css'         => 'width: 400px;',
			'description' => __( 'Instructions that will be added to the thank you page and emails.', 'wc-gateway-cryptobillings' ),
			'default'     => '',
			'desc_tip'    => true,
		),
		'coin_list' => array(
			'title'             => __( 'Accepted Coin List', 'woocommerce-cryptobillings-gateway' ),
			'type'              => 'multiselect',
			'class'             => 'wc-enhanced-select',
			'css'               => 'width: 400px;',
			'default'           => '',
			'description'       => __( 'Leave blank to enable all coins.', 'woocommerce-cryptobillings-gateway' ),
			'options'           => $options,
			'desc_tip'          => true,
			'custom_attributes' => array(
				'data-placeholder' => __( 'Select Accepted Coins', 'woocommerce-cryptobillings-gateway' ),
			),
		),
		'api_key' => array(
			'title'       => __( 'API Key', 'woocommerce-cryptobillings-gateway' ),
			'type'        => 'text',
			'description' => __( 'Get your API key from your Crypto Billings account.', 'woocommerce-cryptobillings-gateway' ),
			'default'     => '',
			'desc_tip'    => true,
		)
	)
);
