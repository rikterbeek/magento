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
class Adyen_Payment_Model_Adyen_Data_PaymentRequest extends Adyen_Payment_Model_Adyen_Data_Abstract {

    public $additionalAmount;
    public $amount;
    public $bankAccount;
    public $browserInfo;
    public $card;
    public $dccQuote;
    public $deliveryAddress;
    public $deliveryDate;
    public $elv;
    public $fraudOffset;
    public $merchantAccount;
    public $mpiData;
    public $orderReference;
    public $recurring;
    public $selectedBrand;
    public $selectedRecurringDetailReference;
    public $sesionId;
    public $shopperEmail;
    public $shopperIP;
    public $shopperInteraction;
    public $shopperReference;
    public $shopperStatement;
    public $additionalData;

	// added for boleto
	public $shopperName;
	public $socialSecurityNumber;

    public function __construct() {
    	$this->browserInfo = new Adyen_Payment_Model_Adyen_Data_BrowserInfo();
        $this->card = new Adyen_Payment_Model_Adyen_Data_Card();
        $this->amount = new Adyen_Payment_Model_Adyen_Data_Amount();
        $this->elv = new Adyen_Payment_Model_Adyen_Data_Elv();
        $this->additionalData = new Adyen_Payment_Model_Adyen_Data_AdditionalData();
        $this->shopperName = new Adyen_Payment_Model_Adyen_Data_ShopperName(); // for boleto
    }

    public function create(Varien_Object $payment, $amount, $order, $paymentMethod = null, $merchantAccount = null) {
        $incrementId = $order->getIncrementId();
        $orderCurrencyCode = $order->getOrderCurrencyCode();
        $customerId = $order->getCustomerId();

        $this->reference = $incrementId;
        $this->merchantAccount = $merchantAccount;
        $this->amount->currency = $orderCurrencyCode;
        $this->amount->value = $this->_formatAmount($amount);

        $this->sesionId = $order->getQuoteId();
        //shopper data
        $customerEmail = $order->getCustomerEmail();
        $this->shopperEmail = $customerEmail;
        $this->shopperIP = $order->getRemoteIp();
        $this->shopperReference = $customerId;
        
        
        /**
         * Browser info
         * @var unknown_type
         */
        $this->browserInfo->acceptHeader = $_SERVER['HTTP_ACCEPT'];
        $this->browserInfo->userAgent = $_SERVER['HTTP_USER_AGENT'];

        switch ($paymentMethod) {
            case "elv":
                $elv = unserialize($payment->getPoNumber());
                $this->card = null;
                $this->shopperName = null;
                $this->elv->accountHolderName = $elv['account_owner'];
                $this->elv->bankAccountNumber = $elv['account_number'];
                $this->elv->bankLocation = $elv['bank_location'];
                $this->elv->bankLocationId = $elv['bank_location'];
                $this->elv->bankName = $elv['bank_name'];
                break;
            case "cc":
            	$this->shopperName = null;
            	$this->elv = null;
            	
				if (Mage::getModel('adyen/adyen_cc')->isCseEnabled()) {
					$this->card = null;
					$kv = new Adyen_Payment_Model_Adyen_Data_AdditionalDataKVPair();
					$kv->key = new SoapVar("card.encrypted.json", XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
					$kv->value = new SoapVar($payment->getAdditionalInformation("encrypted_data"), XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
					$this->additionalData->entry = $kv;
				}
				else {
					$this->card->cvc = $payment->getCcCid();
					$this->card->expiryMonth = $payment->getCcExpMonth();
					$this->card->expiryYear = $payment->getCcExpYear();
					$this->card->holderName = $payment->getCcOwner();
					$this->card->number = $payment->getCcNumber();
				}
                
                // installments
                if(Mage::helper('adyen/installments')->isInstallmentsEnabled()){
                    $kv = new Adyen_Payment_Model_Adyen_Data_AdditionalDataKVPair();
                    $kv->key = new SoapVar("installments", XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
                    $kv->value = new SoapVar($payment->getPoNumber(), XSD_STRING, "string", "http://www.w3.org/2001/XMLSchema");
                    $this->additionalData->entry = $kv;
                }
                break;
            case "boleto":
            	$boleto = unserialize($payment->getPoNumber());
            	$this->card = null;
            	$this->elv = null;
            	$this->socialSecurityNumber = $boleto['social_security_number'];
            	$this->selectedBrand = $boleto['selected_brand'];
            	$this->shopperName->firstName = $boleto['firstname'];
            	$this->shopperName->lastName = $boleto['lastname'];
            	$this->deliveryDate = $boleto['delivery_date'];
            	break;
        }
		
        return $this;
    }

}
