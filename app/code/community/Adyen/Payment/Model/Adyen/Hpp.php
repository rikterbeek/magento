<?php

/**
 * Adyen Payment Module
 *
 * NOTICE OF LICENSE
 *
 * This source file is subject to the Open Software License (OSL 3.0)
 * that is bundled with this package in the file LICENSE.txt.
 * It is also available through the world-wide-web at this URL:
 * http://opensource.org/licenses/osl-3.0.php
 * If you did not receive a copy of the license and are unable to
 * obtain it through the world-wide-web, please send an email
 * to license@magentocommerce.com so we can send you a copy immediately.
 *
 * @category	Adyen
 * @package	Adyen_Payment
 * @copyright	Copyright (c) 2011 Adyen (http://www.adyen.com)
 * @license	http://opensource.org/licenses/osl-3.0.php  Open Software License (OSL 3.0)
 */
/**
 * @category   Payment Gateway
 * @package    Adyen_Payment
 * @author     Adyen
 * @property   Adyen B.V
 * @copyright  Copyright (c) 2014 Adyen BV (http://www.adyen.com)
 */
class Adyen_Payment_Model_Adyen_Hpp extends Adyen_Payment_Model_Adyen_Abstract {
    /**
     * @var DUMMY_EMAIL used when email is empty
     */
    const DUMMY_EMAIL = 'dummy@dummy.com';
    /**
     * @var GUEST_ID , used when order is placed by guests
     */
    const GUEST_ID = 'customer_';

    protected $_canUseInternal = false;
    protected $_code = 'adyen_hpp';
    protected $_formBlockType = 'adyen/form_hpp';
    protected $_infoBlockType = 'adyen/info_hpp';
    protected $_paymentMethod = 'hpp';
    protected $_testModificationUrl = 'https://pal-test.adyen.com/pal/adapter/httppost';
    protected $_liveModificationUrl = 'https://pal-live.adyen.com/pal/adapter/httppost';

    /**
     * @desc Get checkout session namespace
     *
     * @return Mage_Checkout_Model_Session
     */
    public function getCheckout() {
        return Mage::getSingleton('checkout/session');
    }

    public function assignData($data) {
        if (!($data instanceof Varien_Object)) {
            $data = new Varien_Object($data);
        }
        $info = $this->getInfoInstance();
        $hppType = $data->getHppType();
        $info->setCcType($hppType)
             ->setPoNumber($data->getData('hpp_ideal_type')); /* @note misused field */
        $config = Mage::getStoreConfig("payment/adyen_hpp/disable_hpptypes");
        if (empty($hppType) && empty($config)) {
            Mage::throwException(Mage::helper('adyen')->__('Payment Method is complusory in order to process your payment'));
        }
        return $this;
    }

    /**
     * @desc Called just after asssign data
     */
    public function prepareSave() {
        parent::prepareSave();
    }

    /**
     * @desc Get current quote
     *
     * @return Mage_Sales_Model_Quote
     */
    public function getQuote() {
        return $this->getCheckout()->getQuote();
    }

    public function getOrderPlaceRedirectUrl() {
        return Mage::getUrl('adyen/process/redirect');
    }

    /**
     * @desc prepare params array to send it to gateway page via POST
     * @return array
     */
    public function getFormFields() {
        $this->_initOrder();
        $order = $this->_order;
        $realOrderId = $order->getRealOrderId();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $skinCode = trim($this->_getConfigData('skinCode', 'adyen_hpp'));
        $amount = $this->_formatAmount($order->getGrandTotal(),(($orderCurrencyCode=='IDR')?0:2));
        $merchantAccount = trim($this->_getConfigData('merchantAccount'));
        $customerEmail = $order->getCustomerEmail();
        $sesionId = $order->getQuoteId();
        $shopperEmail = (!empty($customerEmail)) ? $customerEmail : self::DUMMY_EMAIL;
        $customerId = $order->getCustomerId();
        $shopperIP = $order->getRemoteIp();
        $browserInfo = $_SERVER['HTTP_USER_AGENT'];
        $shopperLocale = trim($this->_getConfigData('shopperlocale'));
        $shopperLocale = (!empty($shopperLocale)) ? $shopperLocale : Mage::app()->getLocale()->getLocaleCode();
        $countryCode = trim($this->_getConfigData('countryCode'));
        $countryCode = (!empty($countryCode)) ? $countryCode : false;

        $adyFields = array();
        $deliveryDays = (int) $this->_getConfigData('delivery_days', 'adyen_hpp');
        $deliveryDays = (!empty($deliveryDays)) ? $deliveryDays : 55;
        $adyFields['merchantAccount'] = $merchantAccount;
        $adyFields['merchantReference'] = $realOrderId;
        $adyFields['paymentAmount'] = $amount;
        $adyFields['currencyCode'] = $orderCurrencyCode;
        $adyFields['shipBeforeDate'] = date("Y-m-d", mktime(date("H"), date("i"), date("s"), date("m"), date("j") + $deliveryDays, date("Y")));
        $adyFields['skinCode'] = $skinCode;
        $adyFields['shopperLocale'] = $shopperLocale;
        $adyFields['countryCode'] = $countryCode;
        $adyFields['sesionId'] = $sesionId;
        $adyFields['shopperIP'] = $shopperIP;
        $adyFields['browserInfo'] = $browserInfo;

        //order data
        $items = $order->getAllItems();
        $shipmentAmount = number_format($order->getShippingAmount() + $order->getShippingTaxAmount(), 2, ',', ' ');
        $prodDetails = Mage::helper('adyen')->__('Shipment cost: %s %s <br />', $shipmentAmount, $orderCurrencyCode);
        $prodDetails .= Mage::helper('adyen')->__('Order rows: <br />');
        foreach ($items as $item) {
            if ($item->getParentItem()) {
                continue;
            }
            $name = $item->getName();
            $qtyOrdered = $this->_formatAmount($item->getQtyOrdered(), '0');
            $rowTotal = number_format($item->getRowTotalInclTax(), 2, ',', ' ');
            $prodDetails .= Mage::helper('adyen')->__('%s ( Qty: %s ) (Price: %s %s ) <br />', $name, $qtyOrdered, $rowTotal, $orderCurrencyCode);
        }
        $adyFields['orderData'] = base64_encode(gzencode($prodDetails)); //depreacated by Adyen
        $adyFields['sessionValidity'] = date(DATE_ATOM, mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y")));
        $adyFields['shopperEmail'] = $customerEmail;

        // recurring    	
        $recurringType = trim($this->_getConfigData('recurringtypes', 'adyen_abstract'));
        $adyFields['recurringContract'] = $recurringType;
        $adyFields['shopperReference'] = (!empty($customerId)) ? $customerId : self::GUEST_ID . $realOrderId;

        //blocked methods
        $adyFields['blockedMethods'] = "";
        //the data that needs to be signed is a concatenated string of the form data 
        $sign = $adyFields['paymentAmount'] .
                $adyFields['currencyCode'] .
                $adyFields['shipBeforeDate'] .
                $adyFields['merchantReference'] .
                $adyFields['skinCode'] .
                $adyFields['merchantAccount'] .
                $adyFields['sessionValidity'] .
                $adyFields['shopperEmail'] .
                $adyFields['shopperReference'] .
                $adyFields['recurringContract'] .
                $adyFields['blockedMethods'];
        
        //Generate HMAC encrypted merchant signature
        $secretWord = $this->_getSecretWord();
        $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
        $adyFields['merchantSig'] = base64_encode(pack('H*', $signMac));
        
		// get extra fields
       	$adyFields = Mage::getModel('adyen/adyen_openinvoice')->getOptionalFormFields($adyFields,$this->_order);

        //IDEAL
        if (strpos($this->getInfoInstance()->getCcType(),"ideal") !== false) {
            $bankData = $this->getInfoInstance()->getPoNumber();
            if (!empty($bankData)) {        
                $id = explode(DS, $bankData);
                $adyFields['skipSelection'] = 'true';
                $adyFields['brandCode'] = $this->getInfoInstance()->getCcType();
                $adyFields['idealIssuerId'] = $id['0'];        
            }
            
        }
        
        // pos over hpp
        $terminalcode = 'redirect';
        $adyFields['pos.serial_number'] = $terminalcode;
        // calculate signatature pos
        $strsign = "merchantSig:pos.serial_number|" . $adyFields['merchantSig'] . ":" . $terminalcode;
        $signPOS = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $strsign);
        $adyFields['pos.sig'] = base64_encode(pack('H*', $signPOS));;
        
        Mage::log($adyFields, self::DEBUG_LEVEL, 'http-request.log',true);
        
        return $adyFields;
    }

    protected function _getSecretWord($options = null) {
        switch ($this->getConfigDataDemoMode()) {
            case true:        
                $secretWord = trim($this->_getConfigData('secret_wordt', 'adyen_hpp'));
                break;
            default:               
                $secretWord = trim($this->_getConfigData('secret_wordp', 'adyen_hpp'));
                break;
        }
        return $secretWord;
    }

    /**
     * @desc Get url of Adyen payment
     * @return string 
     * @todo add brandCode here
     */
    public function getFormUrl() {
        $brandCode = $this->getInfoInstance()->getCcType();
        $paymentRoutine = $this->_getConfigData('payment_routines', 'adyen_hpp');
        $isConfigDemoMode = $this->getConfigDataDemoMode();
        switch ($isConfigDemoMode) {
            case true:
                if ($paymentRoutine == 'single' && empty($brandCode)) {
                    $url = 'https://test.adyen.com/hpp/pay.shtml';
                } else {
                    $url = (empty($brandCode)) ? 
                            'https://test.adyen.com/hpp/select.shtml' : 
                            "https://test.adyen.com/hpp/details.shtml?brandCode=$brandCode";
                }
                break;
            default:
                if ($paymentRoutine == 'single' && empty($brandCode)) {
                    $url = 'https://live.adyen.com/hpp/pay.shtml';
                } else {
                    $url = (empty($brandCode)) ? 
                            'https://live.adyen.com/hpp/select.shtml' : 
                            "https://live.adyen.com/hpp/details.shtml?brandCode=$brandCode";
                }
                break;
        }
        
        //IDEAL
        $idealBankUrl = false;
        $bankData = $this->getInfoInstance()->getPoNumber();
        if ($brandCode == 'ideal' && !empty($bankData)) {
            $idealBankUrl = ($isConfigDemoMode == true) ? 
                            'https://test.adyen.com/hpp/redirectIdeal.shtml' :
                            'https://live.adyen.com/hpp/redirectIdeal.shtml';
        }        
        
        
        return (!empty($idealBankUrl)) ? $idealBankUrl : $url;
    }

    public function getFormName() {
		return "Adyen HPP";
    }

    /**
     * Return redirect block type
     *
     * @return string
     */
    public function getRedirectBlockType() {
        return $this->_redirectBlockType;
    }

    public function isInitializeNeeded() {
        return true;
    }

    public function initialize($paymentAction, $stateObject) {
        $state = Mage_Sales_Model_Order::STATE_NEW;
        $stateObject->setState($state);
        $stateObject->setStatus($this->_getConfigData('order_status'));
    }

    public function getConfigPaymentAction() {
        return true;
    }


    public function getAvailableHPPTypes() {
    	
        $orderCurrencyCode = Mage::helper('checkout/cart')->getQuote()->getQuoteCurrencyCode();
        $skinCode = trim($this->_getConfigData('skinCode', 'adyen_hpp'));
        $merchantAccount = trim($this->_getConfigData('merchantAccount'));
        $amount = $this->_formatAmount(Mage::helper('checkout/cart')->getQuote()->getGrandTotal(),(($orderCurrencyCode=='IDR')?0:2));
        $sessionValidity = date(DATE_ATOM, mktime(date("H") + 1, date("i"), date("s"), date("m"), date("j"), date("Y")));
        $countryCode = trim($this->_getConfigData('countryCode'));
        $countryCode = (!empty($countryCode)) ? $countryCode : false;
        
        $adyFields = array(
        		"paymentAmount" => $amount,
        		"currencyCode" => $orderCurrencyCode,
        		"merchantReference" => "Get Payment methods",
        		"skinCode" => $skinCode,
        		"merchantAccount" => $merchantAccount,
        		"sessionValidity" => $sessionValidity,
        		"countryCode" => $countryCode,
        		"merchantSig" => "",
        );
        
        $sign = $adyFields['paymentAmount'] .
		        $adyFields['currencyCode'] .
		        $adyFields['merchantReference'] .
		        $adyFields['skinCode'] .
		        $adyFields['merchantAccount'] .
		        $adyFields['sessionValidity'];
        
        //Generate HMAC encrypted merchant signature
        $secretWord = $this->_getSecretWord();
        $signMac = Zend_Crypt_Hmac::compute($secretWord, 'sha1', $sign);
        $adyFields['merchantSig'] = base64_encode(pack('H*', $signMac));

        $ch = curl_init();
        
        $isConfigDemoMode = $this->getConfigDataDemoMode();
        if ($isConfigDemoMode)
	        curl_setopt($ch, CURLOPT_URL, "https://test.adyen.com/hpp/directory.shtml");
        else 
        	curl_setopt($ch, CURLOPT_URL, "https://live.adyen.com/hpp/directory.shtml");
        	
       	curl_setopt($ch, CURLOPT_HEADER, false);
        curl_setopt($ch, CURLOPT_POST,count($adyFields));
        curl_setopt($ch, CURLOPT_POSTFIELDS,http_build_query($adyFields));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, TRUE); // do not print results if you do curl_exec
        
        $results = curl_exec($ch);
        
        
        if($results === false)
        	echo "Error: " . curl_error($ch);
        else{
        	/**
        	 * The $result contains a JSON array containing
        	 * the available payment methods for the merchant account.
        	 */
        	$results_json = json_decode($results);

        	if($results_json == null) {
        		// no valid json so show the error
        		echo $results;
        	}
        	
        	$payment_methods = $results_json->paymentMethods;
        	
        	$result_array = array();
        	foreach($payment_methods as $payment_method) {
				$result_array[$payment_method->brandCode]['name'] = $payment_method->name;
				
				if(isset($payment_method->issuers)) {
					// for ideal go through the issuers
					if(count($payment_method->issuers) > 0)
					{
						foreach($payment_method->issuers as $issuer) {
							$result_array[$payment_method->brandCode]['issuers'][$issuer->issuerId] = $issuer->name;
						}
					}
					ksort($result_array[$payment_method->brandCode]['issuers']); // sort on key
				}
        	}
        }
        return $result_array;
    }

}
