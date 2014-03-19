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
class Adyen_Payment_Model_Observer {

    /**
     * @param Mage_Sales_Model_Order_Invoice $observer
     * @desc online capture only
     */
    public function capture(Varien_Event_Observer $observer) {
        $event = $observer->getEvent();
        $eventNameCmp = strcmp($event->getName(), 'sales_order_invoice_pay');
        $onlineCmp = strcmp($event->getInvoice()->getData('requested_capture_case'), 'online');
        if ($onlineCmp === 0 && $eventNameCmp === 0) {
            $order = $event->getInvoice()->getOrder();
            $isAdyen = $this->isPaymentMethodAdyen($order);
            if (!$isAdyen)
                return false;
            $grandTotal = $event->getInvoice()->getGrandTotal();
            $payment = $order->getPayment();
            $pspReference = Mage::getModel('adyen/event')->getOriginalPspReference($order->getIncrementId());
            $order->getPayment()->getMethodInstance()->sendCaptureRequest($payment, $grandTotal, $pspReference);
            return true;
        }
        return false;
    }

    /**
     * @desc online refund only
     * @param Mage_Sales_Model_Order_Creditmemo $observer
     */
    public function refund(Varien_Event_Observer $observer) {
        $event = $observer->getEvent();
        $eventNameCmp = strcmp($event->getName(), 'sales_order_creditmemo_refund');
        $offline = (bool) $event->getCreditmemo()->getData('offline_requested');
        if ($eventNameCmp === 0 && !$offline) {
            $order = $event->getCreditmemo()->getOrder();
            $isAdyen = $this->isPaymentMethodAdyen($order);
            if (!$isAdyen)
                return false;
            $grandTotal = $event->getCreditmemo()->getGrandTotal();
            $payment = $order->getPayment();
            $pspReference = Mage::getModel('adyen/event')->getOriginalPspReference($order->getIncrementId());
            $order->getPayment()->getMethodInstance()->sendRefundRequest($payment, $grandTotal, $pspReference);
            return true;
        }
        return false;
    }

    /**
     * Determine if the payment method is Adyen
     * @param type $order
     * @return boolean
     */
    public function isPaymentMethodAdyen($order) {
        return ( strpos($order->getPayment()->getMethod(), 'adyen') !== false ) ? true : false;
    }

}