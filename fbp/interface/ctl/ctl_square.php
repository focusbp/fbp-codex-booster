<?php

interface ctl_square {

	
	function square_show_dialog($amount,$callback_function,$error_message="");

	function square_regist_customer($name, $email, $address, $locality = "Japan", $country = "JP"):?string;
	
	function square_regist_card($square_customer_id):?string;
	
	/**
	 * Processes a Square payment using the provided customer and card information.
	 * 
	 * @param string $square_customer_id The ID of the customer in Square's system.
	 * @param string $card_id The ID of the card to use for the payment.
	 * @param float $price The amount to charge.
	 * @param string $currency The currency to use for the transaction, default is "JPY".
	 * @return mixed The result of the payment transaction from Square.
	 */
	function square_payment($square_customer_id, $card_id, $price, $currency = "JPY"):bool;

	function square_get_payment_result():array;
	
	function square_get_error():?string;
	
	function square_close_dialog();
	
	function get_square_callback_parameter_array();
}
