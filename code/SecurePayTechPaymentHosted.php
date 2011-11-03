<?php

/**
 * Securatech Hosted Payment
 *
 * @author your name here nicolaas [at] sunny side up .co .nz
 */

class SecurePayTechPaymentHosted extends Payment {

	protected static $credit_cards = array(
		'Visa' => 'payment/images/payments/methods/visa.jpg',
		'MasterCard' => 'payment/images/payments/methods/mastercard.jpg'
	);

	protected static $spt_merchant_id;
		static function set_spt_merchant_id ($spt_merchant_id) {self::$spt_merchant_id = $spt_merchant_id;}
		static function get_spt_merchant_id() {if(! Director::isLive()) {return 'TESTDIGISPL1';} else {return self::$spt_merchant_id;}}

	protected static $spt_merchant_key;
		static function set_spt_merchant_key($spt_merchant_key) {self::$spt_merchant_key = $spt_merchant_key;}

	protected static $merchant_url = 'https://merchant.securepaytech.com/paymentpage/index.php';
		static function set_merchant_url ($s) {self::$merchant_url = $s;}
		static function get_merchant_url() {return self::$merchant_url;}


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
		', 'paymark_verify');
		$paymentsList.='
			<img height="30" src="payment_securatech/images/paymark_small.png" alt="Paymark Certified" onclick="paymark_verify (' . "'" . self::get_spt_merchant_id() . "'" . ')" class="last" />
			</div>';
		$fieldSet = new FieldSet();
		$fieldSet->push(new LiteralField('SPTInfo', $paymentsList));
		return $fieldSet;
	}

	function getPaymentFormRequirements() {
		return array();
	}

	function processPayment($data, $form) {
		$page = new Page();
		$page->Title = "Make payment";
		$page->Form = $this->processPaymentForm($data);
		$page->Logo = '<img src="payment_securatech/images/paymark_small.png" "payment gateway powered by Securatech" />';
		$controller = new Page_Controller($page);
		$form = $controller->renderWith("PaymentProcessingPage");
		return new Payment_Processing($form);
	}

	function processPaymentForm($data) {
		$order = $this->Order();
		$url = self::get_merchant_url();
		$amount = $order->TotalOutstanding();
		$merchant = self::get_spt_merchant_id();
		$successURL = Director::absoluteBaseURL() . SecurePayTechPaymentHosted_Handler::success_link($this);
		$cancelURL = Director::absoluteBaseURL() . SecurePayTechPaymentHosted_Handler::cancel_link($this);
		
		Requirements::javascript(THIRDPARTY_DIR . '/jquery/jquery.js');
		return <<<HTML
			<form action="$url" method="post" id="PaymentProcessForm">
				<h2>Now forwarding you to Payment Processor...</h2>
				<input type="hidden" name="amount" value="$amount"/>
				<input type="hidden" name="merchantID" value="$merchant"/>
				<input type="hidden" name="returnURL" value="$successURL"/>
				<input type="hidden" name="cancelURL" value="$cancelURL"/>
				<input type="hidden" name="orderReference" value="$order->ID"/>
				<input type="hidden" name="enableCsc"/>
				<input id="sub" type="submit" value="Pay by Credit Card" />
			</form>
			<script type="text/javascript">
				jQuery(document).ready(function() {
					jQuery('#PaymentProcessForm').submit();
				});
			</script>
HTML;
	}
}

class SecurePayTechPaymentHosted_Handler extends Controller {
	
	protected $payment = null;
	
	static $URLSegment = 'paytech';
	
	static $allowed_actions = array(
		'success',
		'cancel'
	);
	
	static function success_link(SecurePayTechPaymentHosted $payment) {
		return self::$URLSegment . "/success/$payment->ID";
	}
	
	static function cancel_link(SecurePayTechPaymentHosted $payment) {
		return self::$URLSegment . "/cancel/$payment->ID";
	}
	
	function init() {
		$id = intval($this->request("ID"));
		$this->payment = DataObject::get_by_id('SecurePayTechPaymentHosted', $id);
	}


	function success() {
		
	}

	function cancel() {
		
	}
}