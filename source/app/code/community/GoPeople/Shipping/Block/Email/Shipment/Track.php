<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

class GoPeople_Shipping_Block_Email_Shipment_Track 
extends Mage_Core_Block_Template
{

    public function getTrackingLink($_item){
        if($_item->getCarrierCode() == GoPeople_Shipping_Model_Carrier::CODE){
            if(Mage::getStoreConfigFlag('carrier/'.GoPeople_Shipping_Model_Carrier::CODE.'/sandbox_mode'))
                return '<a href="https://members-demo.gopeople.com.au/tracking/?code='.$_item->getNumber().'">'.$this->escapeHtml($_item->getNumber()).'</a>';
            return '<a href="https://www.gopeople.com.au/tracking/?code='.$_item->getNumber().'">'.$this->escapeHtml($_item->getNumber()).'</a>';
        }
        return $this->escapeHtml($_item->getNumber());
    }

}
