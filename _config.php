<?php
/**
 * @author nicolaas [at] www.sunnysideup.co.nz
**/

Director::addRules(50, array(
    SecurePayTechPaymentHosted_Handler::$URLSegment . '/$Action/$ID' => 'SecurePayTechPaymentHosted_Handler',
));

//copy the lines between the START AND END line to your /mysite/_config.php file and choose the right settings
//===================---------------- START payment_NZ_gateways MODULE ----------------===================
//SecurePayTechPayment::set_spt_merchant_id('TESTDIGISPL1');
//SecurePayTechPayment::set_spt_merchant_key('abc');
//SecurePayTechPaymentHosted::set_spt_merchant_id('TESTDIGISPL1');
//SecurePayTechPaymentHosted::set_spt_merchant_key('abc');
//===================---------------- END payment_NZ_gateways MODULE ----------------===================
