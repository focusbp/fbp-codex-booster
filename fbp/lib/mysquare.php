<?php

require dirname(__FILE__) . '/../lib_ext/square/autoload.php';

use Square\SquareClient;
use Square\LocationsApi;
use Square\Exceptions\ApiException;
use Square\Http\ApiResponse;
use Square\Models\ListLocationsResponse;
use Square\Environment;
use Square\Models\Money;
use Square\Models\CreatePaymentRequest;
use Square\Models\CreateCustomerRequest;
use Square\Models\UpsertCatalogObjectRequest;
use Square\Models\CatalogObject;
use Square\Models\CatalogObjectType;
use Square\Models\CatalogSubscriptionPlan;
use Square\Models\SubscriptionPhase;
use Square\Models\Address;
use Square\Models\CreateSubscriptionRequest;

class mysquare {
	
	private $testserver;
	private $client;
	private $error;
	
	function __construct($square_access_token,$testserver) {
		$this->testserver = $testserver;
		if ($testserver) {
			$this->client = new SquareClient([
				'accessToken' => $square_access_token,
				'environment' => Environment::SANDBOX,
			]);
		} else {
			$this->client = new SquareClient([
				'accessToken' => $square_access_token,
				'environment' => Environment::PRODUCTION,
			]);
		}
	}

	private function get_error_text($result_body, $fallback) {
		$txt = "";
		if (isset($result_body["errors"]) && is_array($result_body["errors"])) {
			foreach ($result_body["errors"] as $error) {
				if (isset($error["detail"]) && trim((string) $error["detail"]) !== "") {
					$txt .= $error["detail"] . " ";
				} else if (isset($error["code"])) {
					$txt .= $error["code"] . " ";
				}
			}
		}
		$txt = trim($txt);
		if ($txt === "") {
			$txt = $fallback;
		}
		$this->error = $txt;
		return $txt;
	}
		
	function regist_customer($name,$email){
			
		$customers_api = $this->client->getCustomersApi();
		
		$customer = new CreateCustomerRequest();
		$customer->setIdempotencyKey(uniqid());
		$customer->setGivenName($name);
		$customer->setFamilyName("");
//		$customer->setEmailAddress($email);
//		$address_c = new Address();
//		$address_c->setAddressLine1($address);
//		$address_c->setLocality($locality);
//		$address_c->setCountry($country);
		//$customer->setAddress($address_c);

		$result = $customers_api->createCustomer($customer);
		$result_body = json_decode($result->getBody(),true);
		if ($result->isError()) {
			throw new Exception($this->get_error_text($result_body, "Square顧客登録に失敗しました"));
		}

		$customer_id = (string) ($result_body["customer"]["id"] ?? "");
		if ($customer_id === "") {
			throw new Exception($this->get_error_text($result_body, "Square顧客登録に失敗しました"));
		}
		return $customer_id;
	}
		
	function regist_card($customer_id,$nonce){
		if (empty($customer_id)) {
			throw new Exception("Square顧客登録に失敗しました");
		}
		if (empty($nonce)) {
			throw new Exception("Squareカード情報の取得に失敗しました");
		}

		$customers_api = $this->client->getCustomersApi();
		$card = new Square\Models\CreateCustomerCardRequest($nonce);
		$result = $customers_api->createCustomerCard($customer_id,$card);
		$result_body = json_decode($result->getBody(),true);
		if ($result->isError()) {
			throw new Exception($this->get_error_text($result_body, "Squareカード登録に失敗しました"));
		}

		$card_id = (string) ($result_body["card"]["id"] ?? "");
		if($card_id === ""){
			throw new Exception($this->get_error_text($result_body, "Squareカード登録に失敗しました"));
		}
		return $card_id;
	}
	
	function payment($customer_id,$card_id,$price,$currency="JPY"){
		$payments_api = $this->client->getPaymentsApi();

		$money = new Money();
		$money->setAmount($price);
		$money->setCurrency($currency);
		
		if(empty($customer_id)){
			throw new Exception("Invalid Customer ID");
		}
		
		if(empty($card_id)){
			throw new Exception("Invalid Card ID");
		}

		$create_payment_request = new CreatePaymentRequest($card_id, uniqid(), $money);
		$create_payment_request->setCustomerId($customer_id);

		$result = $payments_api->createPayment($create_payment_request);
		if ($result->isError()) {
			$result_body = json_decode($result->getBody(),true);
			$txt = "";
			if(isset($result_body["errors"]) && is_array($result_body["errors"])){
				foreach($result_body["errors"] as $error){
					$txt .= ($error["detail"] ?? "") . " ";
				}
			}
			$txt = trim($txt);
			if($txt === ""){
				$txt = "Unknown Error";
			}
			$this->error = $txt;
			return false;
		}else{
			return true;
		}
	}
	
	function get_error(){
		return $this->error;
	}
	
}
