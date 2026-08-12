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
use Square\Models\RefundPaymentRequest;

class mysquare {
	
	private $testserver;
	private $client;
	private $error;
	private $payment_result = [];
	private $refund_result = [];
	
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
		$this->payment_result = [];
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
		$result_body = json_decode($result->getBody(),true);
		if ($result->isError()) {
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
			$payment = $result_body["payment"] ?? [];
			if (!is_array($payment) || empty($payment["id"])) {
				throw new Exception("Square決済結果を確認できませんでした");
			}
			$this->payment_result = $payment;
			return $payment;
		}
	}

	function get_payment_result(): array {
		return $this->payment_result;
	}

	function refund_payment($payment_id, $amount, $idempotency_key, $currency = "JPY", $reason = "") {
		$this->refund_result = [];
		$payment_id = trim((string) $payment_id);
		$idempotency_key = trim((string) $idempotency_key);
		$currency = trim((string) $currency);
		if ($payment_id === "") {
			throw new Exception("Square Payment IDを確認できませんでした");
		}
		if ((int) $amount <= 0) {
			throw new Exception("返金額を確認できませんでした");
		}
		if ($idempotency_key === "" || strlen($idempotency_key) > 45) {
			throw new Exception("返金処理キーを確認できませんでした");
		}
		if ($currency === "") {
			$currency = "JPY";
		}

		$money = new Money();
		$money->setAmount((int) $amount);
		$money->setCurrency($currency);
		$request = new RefundPaymentRequest($idempotency_key, $money, $payment_id);
		if (trim((string) $reason) !== "") {
			$request->setReason(trim((string) $reason));
		}
		$result = $this->client->getRefundsApi()->refundPayment($request);
		$result_body = json_decode($result->getBody(), true);
		if ($result->isError()) {
			$this->get_error_text(is_array($result_body) ? $result_body : [], "Square返金処理に失敗しました");
			return false;
		}
		$refund = is_array($result_body) ? ($result_body["refund"] ?? []) : [];
		if (!is_array($refund) || trim((string) ($refund["id"] ?? "")) === "") {
			throw new Exception("Square返金結果を確認できませんでした");
		}
		$this->refund_result = $refund;
		return $refund;
	}

	function retrieve_refund($refund_id) {
		$this->refund_result = [];
		$refund_id = trim((string) $refund_id);
		if ($refund_id === "") {
			throw new Exception("Square Refund IDを確認できませんでした");
		}
		$result = $this->client->getRefundsApi()->getPaymentRefund($refund_id);
		$result_body = json_decode($result->getBody(), true);
		if ($result->isError()) {
			$this->get_error_text(is_array($result_body) ? $result_body : [], "Square返金状態の取得に失敗しました");
			return false;
		}
		$refund = is_array($result_body) ? ($result_body["refund"] ?? []) : [];
		if (!is_array($refund) || trim((string) ($refund["id"] ?? "")) === "") {
			throw new Exception("Square返金状態を確認できませんでした");
		}
		$this->refund_result = $refund;
		return $refund;
	}

	function get_refund_result(): array {
		return $this->refund_result;
	}
	
	function get_error(){
		return $this->error;
	}
	
}
