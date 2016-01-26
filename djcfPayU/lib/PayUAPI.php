<?php

class PayUAPI {

	const API_VERSION = 'ONE_ZERO';
	const NS = 'http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd';
	
	private $soap_username;
	private $soap_password;
	private $safe_key;
	private $test_mode;
	
	public static $soapClient = null;

	public function __construct() {
		$i = func_num_args();
	
		if ($i < 4 || $i > 4) {
			throw new Exception("Invalid arguments. Use SOAP USERNAME, SOAP PASSWORD, MODE, and SAFE KEY");
		}
	
		if ($i == 4) {
			$this->soap_username = func_get_arg(0);
			$this->soap_password = func_get_arg(1);
			$this->test_mode = func_get_arg(2);
			$this->safe_key = func_get_arg(3);
		}
	}
	
	private function getSoapHeaderXml()
	{
		$headerXml  = '<wsse:Security SOAP-ENV:mustUnderstand="1" xmlns:wsse="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-secext-1.0.xsd">';
		$headerXml .= '<wsse:UsernameToken wsu:Id="UsernameToken-9" xmlns:wsu="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-wssecurity-utility-1.0.xsd">';
		$headerXml .= '<wsse:Username>'.$this->soap_username.'</wsse:Username>';
		$headerXml .= '<wsse:Password Type="http://docs.oasis-open.org/wss/2004/01/oasis-200401-wss-username-token-profile-1.0#PasswordText">'.$this->soap_password.'</wsse:Password>';
		$headerXml .= '</wsse:UsernameToken>';
		$headerXml .= '</wsse:Security>';

		return $headerXml;
	}

	private function getSoapTransaction($reference)
	{
		$data = array();
		$data['Api'] = self::API_VERSION;
		$data['Safekey'] = $this->safe_key;
		$data['AdditionalInformation']['payUReference'] = $reference;

		$result = $this->getSoapSingleton()->getTransaction($data);
		return json_decode(json_encode($result), true);
	}

	private function setSoapTransaction($txn_array)
	{
		$result = $this->getSoapSingleton()->setTransaction($txn_array);
		return json_decode(json_encode($result), true);
	}

	private function getSoapSingleton()
	{
		if(self::$soapClient == null)
		{
			$headerXml = $this->getSoapHeaderXml();
			$baseUrl = $this->getTransactionServer();
			$soapWsdlUrl = $baseUrl.'/service/PayUAPI?wsdl';

			$headerbody = new SoapVar($headerXml, XSD_ANYXML, null, null, null);
			$soapHeader = new SOAPHeader(self::NS, 'Security', $headerbody, true);

			$soap_client = new SoapClient($soapWsdlUrl, array('trace' => 1, 'exception' => 0));
			$soap_client->__setSoapHeaders($soapHeader);
			
			self::$soapClient = $soap_client;
			
			return $soap_client;
		}
		return self::$soapClient;
	}

	private function getTransactionServer()
	{
		if($this->test_mode) {
			$url = 'https://staging.payu.co.za';
		} else {
			$url = 'https://secure.payu.co.za';
		}
		return $url;
	}
	
	public function configure_payment($params)
	{
		$amount = $params['amount'];
		$safe_key = $params['safe_key'];
		$merchant_id = $params['merchant_id'];
		$payment_methods = $params['payment_methods'];
		$currency_code = $params['currency_code'];
		$basket_desc = $params['basket_desc'];
		$txn_type = $params['transaction_type'];
		$secure_3ds = $params['secure_3ds'];
		$budget_payment = $params['budget_payment'];
		$baseUrl = self::getTransactionServer() . '/rpp.do?PayUReference=';
		$payURppUrl = $baseUrl;
		$apiVersion = self::API_VERSION;
		//var_dump($params['return_url'], $params['cancel_url'], $params['notify_url']);
		//exit;
			
		$payload = array();
		$payload['Api'] = $apiVersion;
		$payload['Safekey'] = $safe_key;
		$payload['TransactionType'] = $txn_type;
		$payload['AdditionalInformation']['merchantReference'] = $merchant_id;
	
		if($secure_3ds) {
			$payload['AdditionalInformation']['secure3d'] = true;
		} else {
			$payload['AdditionalInformation']['secure3d'] = false;
		}
	
		if($budget_payment) {
			$payload['AdditionalInformation']['ShowBudget'] = true;
		} else {
			$payload['AdditionalInformation']['ShowBudget'] = false;
		}
		$payload['AdditionalInformation']['notificationUrl'] = $params['notify_url'];
		$payload['AdditionalInformation']['cancelUrl'] = $params['cancel_url'];
		$payload['AdditionalInformation']['returnUrl'] = $params['return_url'];
		$payload['AdditionalInformation']['supportedPaymentMethods'] = $payment_methods;
	
		$payload['Basket']['description'] = $basket_desc;
		$payload['Basket']['amountInCents'] =(int)$amount;
		$payload['Basket']['currencyCode'] = $currency_code;
	
		$payload['Customer']['merchantUserId'] = $params['name'];
		$payload['Customer']['email'] = $params['email'];
		$payload['Customer']['ip'] = $params['ip_addr'];
		$payload['Customer']['firstName'] = '';
		$payload['Customer']['lastName'] = '';
		$payload['Customer']['mobile'] = '';
		$payload['Customer']['regionalId'] = '';
	
		$data = $this->setSoapTransaction($payload);
		$payuReference = isset($data['return']['payUReference']) ? $data['return']['payUReference'] : '';

		$confirmPayment = false;
		if($payuReference != ''){
			$payURppUrl .= $payuReference;
			$confirmPayment = true;
		} 
		
		return array(
			'rpp' => $payURppUrl,
			'canPay' => $confirmPayment,
		);
	}
	
	public function get_payment_info($reference)
	{
		if(isset($reference) && $reference)
			return $this->getSoapTransaction($reference);
		return false;
	}
}
?>