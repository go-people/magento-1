<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

class GoPeople_Shipping_Model_Observer
{
    public function onSalesOrderPaymentPay(Varien_Event_Observer $observer)
    {
        $invoice = $observer->getInvoice();
        $order = $invoice->getOrder();
        $carrier = Mage::getModel('gopeople_shipping/carrier');
        $shipping = $order->getShippingAddress();
        $method = $order->getShippingMethod();
        $code_l = strlen(GoPeople_Shipping_Model_Carrier::CODE);
        if(substr($method,0,$code_l) == GoPeople_Shipping_Model_Carrier::CODE){

            $parcels = [];
            foreach($order->getAllItems() as $item){
                if (0 < $item->getQtyToShip()) $parcels[] = [
                                                        'type'      => "custom",
                                                        'productId' => $item->getProductId(),
                                                        'sku'       => $item->getSku(),
                                                        'name'      => $item->getName(),
                                                        'number'    => $item->getQtyToShip(),
                                                        'width'     => 0, 'height'=>0, 'length'=>0,
                                                        'weight'    => $carrier->getWeightInKG($order->getStoreId(),$item->getWeight())
                                                ];
            }
            $params = [
                'source'       => "magento1",
                'orderId'      => $order->getId(),
                'orderNumber'  => $order->getIncrementId(),
                'addressFrom'  => $carrier->getShippingOrigin($order->getStoreId()),
                'addressTo'    => [
                    'unit'          => isset($shipping->getStreet()[1]) ? $shipping->getStreet()[1] : '',
                    'address1'      => isset($shipping->getStreet()[0]) ? $shipping->getStreet()[0] : '',
                    'suburb'        => $shipping->getCity(),
                    'state'         => $shipping->getRegion(),
                    'postcode'      => $shipping->getPostcode(),
                    'contactName'   => trim($shipping->getPrefix().' '.$shipping->getFirstname().' '.$shipping->getLastname()),
                    'contactNumber' => $shipping->getTelephone(),
                    'sendUpdateSMS' => true,
                    'contactEmail'  => $shipping->getEmail(),
                    'isCommercial'  => false,
                    'companyName'   => $shipping->getCompany()
                ],
                'parcels'      => $parcels,
                'shippingName' => $order->getShippingDescription(),
            ];

            $curl = new Varien_Http_Adapter_Curl();
            $curl->setConfig(array(
               'maxredirects' => 5,
               'timeout'      => 30,
               'header'       => false,
            ))->setOptions(array(
               CURLOPT_USERAGENT => 'Magento 1',
            ))->write(Zend_Http_Client::POST, $carrier->getEndPoint().'shoppingcart', '1.1', $carrier->getHttpHeaders($order->getStoreId()), Mage::helper('core')->jsonEncode($params));
            $data = Mage::helper('core')->jsonDecode($curl->read());
            $curl->close();

            if(isset($data['result']) && is_array($data['result'])){
                if(isset($data['result']['guid'])) $order->setGopeopleCartId($data['result']['guid']);//update from response once available
                else $order->setGopeopleCartId('none');//prevent repeated export
                $order->save();
            }
        }
    }
}
