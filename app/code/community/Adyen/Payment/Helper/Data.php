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
class Adyen_Payment_Helper_Data extends Mage_Payment_Helper_Data {

    public function getCcTypes() {
        $_types = Mage::getConfig()->getNode('default/adyen/payment/cctypes')->asArray();
        uasort($_types, array('Mage_Payment_Model_Config', 'compareCcTypes'));
        $types = array();
        foreach ($_types as $data) {
            $types[$data['code']] = $data['name'];
        }
        return $types;
    }

    public function getBoletoTypes() {
    	$_types = Mage::getConfig()->getNode('default/adyen/payment/boletotypes')->asArray();
    	$types = array();
    	foreach ($_types as $data) {
    		$types[$data['code']] = $data['name'];
    	}
    	return $types;
    }
    
    public function getOpenInvoiceTypes() {
    	$_types = Mage::getConfig()->getNode('default/adyen/payment/openinvoicetypes')->asArray();
    	$types = array();
    	foreach ($_types as $data) {
    		$types[$data['code']] = $data['name'];
    	}
    	return $types;
    }
    
    public function getRecurringTypes() {
    	$_types = Mage::getConfig()->getNode('default/adyen/payment/recurringtypes')->asArray();
    	$types = array();
    	foreach ($_types as $data) {
    		$types[$data['code']] = $data['name'];
    	}
    	return $types;
    }
    
    public function getExtensionVersion() { 
    	return (string) Mage::getConfig()->getNode()->modules->Adyen_Payment->version; 
    }
}
