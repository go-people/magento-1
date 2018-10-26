<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

class GoPeople_Shipping_ShippedController 
extends Mage_Core_Controller_Front_Action
{
    /**
     * Save shipment for order once booked
     */
    public function indexAction()
    {
        $results = array();

        if ($this->getRequest()->isPost() || true) {
            try{
                // Get initial data from request
                $parameters = Mage::helper('core')->jsonDecode($this->getRequest()->getRawBody());
                //$parameters = Mage::helper('core')->jsonDecode('{"guid":"81436a20-3435-5406-d917-1849bada0bc1","shipment":{"trackingCode":"ABC123"}}');
                $orders = Mage::getModel('sales/order')->getCollection()
                                ->addFieldToFilter('shipping_method',['like' => GoPeople_Shipping_Model_Carrier::CODE.'_%'])
                                ->addFieldToFilter('gopeople_cart_id',$parameters['guid']);
                foreach($orders as $_order){
                    $shipment = array();
                    if(isset($parameters['shipment']) && isset($parameters['shipment']['parcels']) && is_array($parameters['shipment']['parcels'])){
                        foreach($parameters['shipment']['parcels'] as $item){
                            foreach($_order->getItems() as $_item){
                                if(!$_item->getIsVirtual() && !$_item->getParentItem() && $item['sku'] == $_item->getSku())
                                    $shipment[$_item->getItemId()] = $item['number'];
                            }
                        }
                    }
                    $track = Mage::getModel('sales/order_shipment_track')
                            ->addData(array(
                                'carrier_code' => GoPeople_Shipping_Model_Carrier::CODE,
                                'title'        => "Go People",
                                'number'       => $parameters['shipment']['trackingCode'],
                            ));

                    $order = Mage::getModel('sales/order')->load($_order->getId());//reload order
                    if ($order->canShip()) {

                        $shipment = Mage::getModel('sales/service_order', $order)->prepareShipment($shipment)->addTrack($track);
                        $shipment->register();
                        $shipment->getOrder()->setCustomerNoteNotify(false);

                        $shipment->getOrder()->setIsInProcess(true);
                        $transactionSave = Mage::getModel('core/resource_transaction')
                                ->addObject($shipment)
                                ->addObject($shipment->getOrder())
                                ->save();

                        $shipment->sendEmail(true,'');

                        $results = ['error'=>false,'message'=>"Shipment ".$shipment->getIncrementId()." has been created."];
                    }
                    break;
                }
                if(empty($results)) throw new Exception("Unable to find order with cart id - ".$parameters['guid']);
            }
            catch(\Throwable $e){
                $results = array('error'=>true,'message'=>$e->getMessage(),'code'=>$e->getCode());
            }
        }
        else $results= array('error'=>true,'message'=>"Method not allowed",'code'=>405);

        $this->getResponse()->clearHeaders()->setHeader('Content-type','application/json',true);
        $this->getResponse()->setBody(json_encode($results));
    }
}
