<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

class GoPeople_Shipping_Model_Config_Comment_Callback
extends Mage_Core_Model_Config_Data
{
    public function getCommentText(Mage_Core_Model_Config_Element $element, $currentValue)
    {
        $url = Mage::getStoreConfig(Mage_Core_Model_Url::XML_PATH_SECURE_URL).'gopeople/shipped';
        return "Please copy this link into your Go Poeple's Members Area:-<br/><a href='".$url."'>".$url."</a>";
    }

}
