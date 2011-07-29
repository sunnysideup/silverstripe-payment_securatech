<?php

/**
 * @author Nicolaas [at] sunnysideup.co.nz
 * Sub-class of Payment that supports SecurePayTech as its payment processor
 *
 * Note: You must have the cURL extension installed in PHP in order to use
 * this class
 * @reference: http://www.securepaytech.com/developers/documentation/SPT-Hosted-Payment-Page.pdf
 *
 **/

/**
 *  Configuration
 *  =============
 *  You need to define the merchant id and key in the _config.php of your
 *  project
 */
class SecurePayTechPayment extends Payment {

	protected static $credit_cards = array(
		'Visa' => 'payment/images/payments/methods/visa.jpg',
		'MasterCard' => 'payment/images/payments/methods/mastercard.jpg',
		//These two are usually not supported
		//'Amex' => 'payment/images/payments/methods/american-express.gif',
		//'Diners' => 'payment/images/payments/methods/dinners-club.jpg',
	);

	protected static $spt_merchant_id;

	static function set_spt_merchant_id ($spt_merchant_id) {self::$spt_merchant_id = $spt_merchant_id;}
	static function get_spt_merchant_id() {if(Director::isDev()) {return "TESTDIGISPL1";}else {return self::$spt_merchant_key;}}

	protected static $spt_merchant_key;

	static function set_spt_merchant_key($spt_merchant_key) {self::$spt_merchant_key = $spt_merchant_key;}



	function getPaymentFormFields() {
		$site_currency = Payment::site_currency();
		$paymentsList = '<div id="SecurePayTechCardsAvailable">';
		$count = 0;
		foreach(self::$credit_cards as $name => $image) {
			$count++;
			$class = '';
			if($count == 1) {
				$class = "first";
			}
			if($count % 2) {
				$class .= " even";
			}
			else {
				$class .= " odd";
			}
			$paymentsList .= '<img src="' . $image . '" alt="' . $name . '" class="SecurePayTechCardImage'.$count.'" />';
		}
		Requirements::customScript('
			function paymark_verify(merchant) {
				window.open ("http://www.paymark.co.nz/dart/darthttp.dll?etsl&tn=verify&merchantid=" + merchant, "verify", "scrollbars=yes, width=400, height=400");
			}
		');
		$paymentsList.='<img height="50" src="payment_NZ_gateways/images/paymark.png" alt="Paymark Certified" onclick="paymark_verify (' . "'" . self::get_spt_merchant_id() . "'" . ')" class="last" /></div>';
		$fieldSet = new FieldSet();
		if(Director::isDev()) {
			$fieldSet->push(
				new DropdownField(
					$name = "SecurePayTechTestAmountValue",
					$title = "Test",
					$source = $this->testCodesInCents(),
					$value = "",
					$form = null,
					$emptyString = " --- select test - if any ---"
				)
			);
			$fieldSet->push(
				new DropdownField(
					$name = "SecurePayTechCardsToUse",
					$title = "Card",
					$source = $this->cardsToUse(),
					$value = "",
					$form = null,
					$emptyString = " --- choose card - if any ---"
				)
			);
			$fieldSet->push(
				new LiteralField(
					$name = "SecurePayTechCardsExplained",
					$title = '<p id="SecurePayTechCardsExplained" class="middleColumn"><i>NB: The option to select a test type and test card is ONLY availabe in a development environment.</i></p>'
				)
			);
			Requirements::javascript("payment_NZ_gateways/javascript/SecurePayTechPayment.js");
		}
		$fieldSet->push(new TextField('SecurePayTechCardHolderName', 'Card Holder Name:'));
		$fieldSet->push(new TextField('SecurePayTechCreditCardNumber', 'Credit Card Number:'));
		$fieldSet->push(new NumericField('SecurePayTechCardExpiry', 'Credit Card Expiry (MMYY):', '', 4));
		$fieldSet->push(new LiteralField('SPTInfo', $paymentsList));
		return $fieldSet;
	}

	/**
	 * Returns the required fields to add to the order form, when using this
	 * payment method.
	 */
	function getPaymentFormRequirements() {
		return array (
			"js" => "
				require('SecurePayTechCardHolderName');
				require('SecurePayTechCreditCardNumber');
				require('SecurePayTechCardExpiry');
			",
			"php" => '
				$this->requireField("SecurePayTechCardHolderName", $data);
				$this->requireField("SecurePayTechCreditCardNumber", $data);
				$this->requireField("SecurePayTechCardExpiry", $data);
			'
		);
	}

	/**
	 * Process payment using HTTPS POST
	 */
	function processPayment($data, $form) {
		$data = Convert::raw2sql($data);
		$realPayment = $this->Amount;
		if(Director::isDev()) {
			if(isset($data["SecurePayTechTestAmountValue"])) {
				if($data["SecurePayTechTestAmountValue"] !== "") {
					if($data["SecurePayTechTestAmountValue"] == 0) {
						$numberString = "99";
					}
					else {
						$numberString = "0.".$data["SecurePayTechTestAmountValue"];
					}
					$nicelyFormatted = number_format($numberString,2);
					$this->Amount = floatval($nicelyFormatted);
				}
			}
			if(isset($data["SecurePayTechCardsToUse"])) {
				if($data["SecurePayTechCardsToUse"] !== "") {
					$cardArray = explode(",", $this->getCardData($data["SecurePayTechCardsToUse"]));
					$data['SecurePayTechCreditCardNumber'] = trim($cardArray[1]);
					$data['SecurePayTechCardExpiry'] = trim($cardArray[2]).trim($cardArray[3]);
				}
			}
		}
		$orderRef = $this->ID;
		$cardNo = $data['SecurePayTechCreditCardNumber'];
		$cardExp = $data['SecurePayTechCardExpiry'];
		$cardHolder = $data['SecurePayTechCardHolderName'];
		$cardType = 0;
		$amt = $this->Amount;
		$currency = $this->Currency;

		$postvars = array(
			'OrderReference' => $orderRef,
			'CardNumber' => $cardNo,
			'CardExpiry' => $cardExp,
			'CardHolderName' => $cardHolder,
			'CardType' => $cardType,
			'MerchantID' => self::get_spt_merchant_id(),
			'MerchantKey' => self::$spt_merchant_key,
			'Amount' => $amt,
			'Currency' => $currency
		);
		$this->Amount = $realPayment;
		$response = $this->http_post('https','tx.securepaytech.com',8443,'/web/HttpPostPurchase', $postvars);
		if(!$response) {
			$this->Status = 'Failure';
			$this->Message = "Communication Failure";
			if(Director::isDev()) {
				$this->Message .= " (".curl_error($ch).")";
			}
			$this->write();
			$result = new Payment_Failure();
			return $result;
		}
		$responses = explode (',', $response);
		//var_dump ($responses);
		if(!isset($responses[0])) {
			$responses[0] = 0;
		}
		$ok = false;
		if($responses[0] == 1) {
			$ok = true;
		}
		if ($ok) {
			$this->Status = 'Success';
			$result = new Payment_Success();
		}
		else {
			$this->Status = 'Failure';
			$this->Message = $this->getResponseMessage($responses [0]);
			$result = new Payment_Failure();
		}
		$this->write();
		return $result;
	}

	/* $vars is an associative array containing the post variables */
	function http_post($method,$server, $port, $url, $vars) {
		$postdata = "";
		foreach($vars as $key => $value) {
			$postdata .= urlencode($key) . "=" . urlencode($value) . "&";
		}
		$postdata = substr($postdata,0,-1);
		$content_length = strlen($postdata);
		$headers = "POST $url HTTP/1.1\r\n".
			"Accept: */*\r\n".
			"Accept-Language: en-nz\r\n".
			"Content-Type: application/x-www-form-urlencoded\r\n".
			"Host: $server\r\n".
			"Connection: Keep-Alive\r\n".
			"Cache-Control: no-cache\r\n".
			"Content-Length: $content_length\r\n\r\n";
		$ch = curl_init();
		curl_setopt ($ch, CURLOPT_URL, $method . '://' . $server .":". $port . $url);
		curl_setopt ($ch, CURLOPT_FOLLOWLOCATION, 1);
		curl_setopt ($ch, CURLOPT_RETURNTRANSFER, 1);
		curl_setopt ($ch, CURLOPT_POST, 1);
		curl_setopt ($ch, CURLOPT_POSTFIELDS, $postdata);
		$ret = curl_exec($ch);
		curl_close($ch);
		return $ret;
	}

	protected function responseCodes() {
		$array = array(
			0 => "Could not reach server",
			1 => 'Transaction Approved',
			2 => 'Insufficient funds',
			3 => 'Card expired',
			4 => 'Card declined',
			5 => 'Server error occurred',
			6 => 'Communication error',
			7 => 'Unsupported transaction type',
			8 => 'Bad or malformed request',
			9 => 'Invalid card number'
		);
		return $array;
	}

	protected function getResponseMessage($number) {
		$array = $this->responseCodes();
		if(isset($array[$number])) {
			return $array[$number];
		}
		else {
			return "unknown error";
		}
	}

	protected function testCodesInCents() {
		$array = array(
			00 => "Transaction OK",
			10 => "Insufficient Funds",
			54 => "Card Expired",
			57 => "Unsupported Transaction Type",
			75 => "Card Declined",
			91 => "Communications Error"
		);
		return $array;
	}

	protected function cardsToUse() {
		return array(
			0 => "Visa, 4987654321098769, 05, 13",
			1 => "MasterCard, 5123456789012346, 05, 13",
			2 => "Amex, 345678901234564, 05, 13",
			3 => "Dinersclub, 30123456789019, 05, 13"
		);
	}

	protected function getCardData($cardNumber) {
		$array = $this->cardsToUse();
		return $array[$cardNumber];
	}


}
