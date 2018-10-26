<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

class GoPeople_Shipping_Model_Cron
{
    /**
     * @param Varien_Event_Observer/Aoe_Scheduler_Model_Schedule  $observer
     * @return string $message
     */
    public function synchroniseOrders($observer)
    {
        $orders = Mage::getModel('sales/order')->getCollection()
                       ->addFieldToFilter('shipping_method',['like' => GoPeople_Shipping_Model_Carrier::CODE.'_%'])
                       ->addFieldToFilter('gopeople_cart_id',['null' => true])
                       ->addFieldToFilter('state',Mage_Sales_Model_Order::STATE_PROCESSING)
                       ->addFieldToFilter('created_at',['to' => date('Y-m-d H:i:s', Mage::getModel('core/date')->gmtTimestamp()-(5*60))]);//test whether local or UTC time
        foreach($orders as $_order){
            foreach($_order->getInvoiceCollection() as $invoice){
                $parameters = new Varien_Event_Observer();
                Mage::getModel('gopeople_shipping/observer')->onSalesOrderPaymentPay($parameters->setInvoice($invoice));
                break;//only the first one is enough
            }
        }
    }
}
