<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

class GoPeople_Shipping_Model_Carrier
extends Mage_Shipping_Model_Carrier_Abstract
implements Mage_Shipping_Model_Carrier_Interface
{

    /**
     * Code of the carrier
     *
     * @var string
     */
    const CODE = 'gopeople';

    /**
     * Code of the carrier
     *
     * @var string
     */
    protected $_code = self::CODE;

   /**
     * Default gateway url
     *
     * @var string
     */
    protected $_defaultGatewayUrl = 'https://api.gopeople.com.au/';

   /**
     * Default sandbox url
     *
     * @var string
     */
    protected $_defaultSandboxUrl = 'http://api-demo.gopeople.com.au/';

    /**
     * Flag for check carriers for activity
     *
     * @var string
     */
    protected $_activeFlag = 'active';

    /**
     * Set flag for check carriers for activity
     *
     * @param string $code
     * @return Mage_Usa_Model_Shipping_Carrier_Abstract
     */
    public function setActiveFlag($code = 'active')
    {
        $this->_activeFlag = $code;
        return $this;
    }

    /**
     * Get Go People End Point
     *
     * @return array
     */
    public function getEndPoint()
    {
        return ((bool)$this->getConfigData('sandbox_mode') ? $this->_defaultSandboxUrl : $this->_defaultGatewayUrl);
    }

    /**
     * Check if carrier has shipping tracking option available
     * All Mage_Usa carriers have shipping tracking option available
     *
     * @return boolean
     */
    public function isTrackingAvailable()
    {
        return true;
    }

    /**
     * Check if carrier has shipping label option available
     *
     * @return boolean
     */
    public function isShippingLabelsAvailable()
    {
        return false;
    }

    /**
     * Determine whether zip-code is required for the country of destination
     *
     * @param string|null $countryId
     * @return bool
     */
    public function isZipCodeRequired($countryId = null)
    {
        if ($countryId != null) {
            return !Mage::helper('directory')->isZipCodeOptional($countryId);
        }
        return true;
    }

    /**
     * Get allowed shipping methods
     *
     * @return array
     */
    public function getAllowedMethods()
    {
        return array('fasterdelivery' => $this->getConfigData('name'));
    }

    /**
     * Get Shipping origin
     *
     * @return array
     */
    public function getShippingOrigin($storeId){
        $region = Mage::getStoreConfig(Mage_Shipping_Model_Config::XML_PATH_ORIGIN_REGION_ID,$storeId);
        if(0 < (int)$region) $region = Mage::getModel('directory/region')->load($region)->getName();

        return [
            'unit'          => Mage::getStoreConfig('shipping/origin/street_line2',$storeId),
            'address1'      => Mage::getStoreConfig('shipping/origin/street_line1',$storeId),
            'suburb'        => Mage::getStoreConfig(Mage_Shipping_Model_Config::XML_PATH_ORIGIN_CITY,$storeId),
            'postcode'      => Mage::getStoreConfig(Mage_Shipping_Model_Config::XML_PATH_ORIGIN_POSTCODE,$storeId),
            'state'         => $region,
            'country'       => Mage::getStoreConfig(Mage_Shipping_Model_Config::XML_PATH_ORIGIN_COUNTRY_ID,$storeId), 
            'contactName'   => Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME,$storeId),
            'contactNumber' => Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_STORE_PHONE,$storeId),
            'sendUpdateSMS' => false,
            'contactEmail'  => Mage::getStoreConfig('trans_email/ident_general/email',$storeId),
            'isCommercial'  => true,
            'companyName'   => Mage::getStoreConfig(Mage_Core_Model_Store::XML_PATH_STORE_STORE_NAME,$storeId),
        ];
    }

    /**
     * Get Http Headers
     *
     * @return \Zend\Http\Headers
     */
     public function getHttpHeaders($storeId){
        return array(
           'Authorization: bearer ' . Mage::getStoreConfig('carriers/'.GoPeople_Shipping_Model_Carrier::CODE.'/rest_token',$storeId),
           'Accept: application/json',
           'Content-Type: application/json'
        );
    }

    /**
     * Collect and get rates
     *
     * @param Mage_Shipping_Model_Rate_Request $request
     * @return Mage_Shipping_Model_Rate_Result|bool|null
     */
    public function collectRates(Mage_Shipping_Model_Rate_Request $request)
    {

        if (!$this->getConfigFlag($this->_activeFlag)) {
            return false;
        }

        $quote = null;
        $parcels = [];
        foreach($request->getAllItems() as $item){
            if(!isset($quote)) $quote = $item->getQuote();
            $parcels[] = [
                'type'   => "custom",
                'number' => $item->getQty(),
                'width' => 0, 'height' => 0, 'length' => 0,
                'weight' => $this->getWeightInKG($request->getStoreId(),$item->getProduct()->getWeight()),
            ];
        }
        if(!isset($quote)) return $this->getErrorMessage();
        $billing = $quote->getBillingAddress();
        $region = Mage::getStoreConfig(Mage_Shipping_Model_Config::XML_PATH_ORIGIN_REGION_ID,$request->getStoreId());
        if(0 < (int)$region) $region = Mage::getModel('directory/region')->load($region)->getName();

        $streets = explode("\n",$request->getStreet());
        $params = [
            'addressFrom' => $this->getShippingOrigin($request->getStoreId()),
            'addressTo'   => [
                'unit'          => isset($streets[1]) ? $streets[1] : '',
                'address1'      => isset($streets[0]) ? $streets[0] : '',
                'suburb'        => $request->getDestCity(),
                'state'         => $request->getDestRegionCode(),
                'postcode'      => $request->getDestPostcode(),
                'contactName'   => trim($billing->getPrefix().' '.$billing->getFirstname().' '.$billing->getLastname()),
                'contactNumber' => $billing->getTelephone(),
                'sendUpdateSMS' => true,
                'contactEmail'  => $quote->getCustomerEmail(),
                'isCommercial'  => false,
                'companyName'   => $billing->getCompany()
            ],
            'parcels'     => $parcels,
            'pickUpAfter' => '',
            'dropOffBy'   => '',
            'onDemand'    => true,
            'setRun'      => true
        ];

        try{
            $curl = new Varien_Http_Adapter_Curl();
            $curl->setConfig(array(
               'maxredirects' => 5,
               'timeout'      => 30,
               'header'       => false,
            ))->setOptions(array(
               CURLOPT_USERAGENT => 'Magento 1',
            ))->write(Zend_Http_Client::POST, $this->getEndPoint().'quote', '1.1', $this->getHttpHeaders($request->getStoreId()), Mage::helper('core')->jsonEncode($params));
            $data = Mage::helper('core')->jsonDecode($curl->read());
            $curl->close();

            if(isset($data['errorCode']) && 0 < (int)$data['errorCode']){
                $errorMsg = isset($data['message']) && !empty($data['message']) ? $data['message'] : $this->getConfigData('specificerrmsg');
                return Mage::getModel('shipping/rate_result_error')
                    ->setCarrier($this->_code)
                    ->setCarrierTitle($this->getConfigData('title'))
                    ->setErrorMessage(__($errorMsg ? $errorMsg : 'Sorry, but we can\'t deliver to the destination with this shipping module.'));
            }
            if(isset($data['result']) && is_array($data['result']) && 0 < count($data['result'])){
                $services = explode(',',$this->getConfigData('services'));
                $result = Mage::getModel('shipping/rate_result');
                if(in_array('on_demand', $services) && isset($data['result']['onDemandPriceList']) && is_array($data['result']['onDemandPriceList'])){
                    foreach ($data['result']['onDemandPriceList'] as $onDemand) {
                        $result->append(Mage::getModel('shipping/rate_result_method')
                                ->setCarrier($this->_code)
                                ->setCarrierTitle($this->getConfigData('title'))
                                ->setMethod($this->_slugify($onDemand['serviceName']))
                                ->setMethodTitle(ucwords($onDemand['serviceName']))
                                ->setCost($onDemand['amount'])
                                ->setPrice($onDemand['amount']));
                    }
                }
                if(in_array('set_run', $services) && isset($data['result']['setRunPriceList']) && is_array($data['result']['setRunPriceList'])){
                    foreach ($data['result']['setRunPriceList'] as $setRun) {
                        $result->append(Mage::getModel('shipping/rate_result_method')
                                ->setCarrier($this->_code)
                                ->setCarrierTitle($this->getConfigData('title'))
                                ->setMethod($this->_slugify($setRun['serviceName']))
                                ->setMethodTitle(ucwords($setRun['serviceName']))
                                ->setCost($setRun['amount'])
                                ->setPrice($setRun['amount']));
                    }
                }
                if(in_array('shift', $services) && isset($data['result']['shiftList']) && is_array($data['result']['setRunPriceList'])){
                    foreach ($data['result']['shiftList'] as $shift) {
                        $result->append(Mage::getModel('shipping/rate_result_method')
                                ->setCarrier($this->_code)
                                ->setCarrierTitle($this->getConfigData('title'))
                                ->setMethod($this->_slugify($shift['serviceName']))
                                ->setMethodTitle(ucwords($shift['serviceName']))
                                ->setCost($shift['amount'])
                                ->setPrice($shift['amount']));
                    }
                }
                if(!empty($result->getAllRates())) return $result;
            }
        }
        catch(Throwable $e){
            return Mage::getModel('shipping/rate_result_error')
                  ->setCarrier($this->_code)
                  ->setCarrierTitle($this->getConfigData('title'))
                  ->setErrorMessage($e->getMessage());
        }
        return false;
    }

    public function getTrackingInfo($tracking)
    {
        $info = array();

        $result = $this->getTracking($tracking);

        if($result instanceof Mage_Shipping_Model_Tracking_Result){
            if ($trackings = $result->getAllTrackings()) {
                return $trackings[0];
            }
        }
        elseif (is_string($result) && !empty($result)) {
            return $result;
        }

        return false;
    }


    /**
     * Get tracking
     *
     * @param mixed $trackings
     * @return mixed
     */
    public function getTracking($trackings)
    {
        if (!is_array($trackings)) {
            $trackings=array($trackings);
        }
        $result = Mage::getModel('shipping/tracking_result');

        foreach($trackings as $id){
            try{
                $curl = new Varien_Http_Adapter_Curl();
                $curl->setConfig(array(
                   'maxredirects' => 5,
                   'timeout'      => 30,
                   'header'       => false,
                ))->setOptions(array(
                   CURLOPT_USERAGENT => 'Magento 1',
                ))->write(Zend_Http_Client::GET, $this->getEndPoint().'comment?trackingCode='.$id, '1.1', $this->getHttpHeaders($this->getStore()->getId()));
                $data = Mage::helper('core')->jsonDecode($curl->read());
                $curl->close();
                if(isset($data['errorCode']) && 0 < (int)$data['errorCode']){
                    $result->append(Mage::getModel('shipping/tracking_result_error')
                                    ->setCarrier(GoPeople_Shipping_Model_Carrier::CODE)
                                    ->setCarrierTitle($this->getConfigData('title'))
                                    ->setTracking($id)
                                    ->setErrorMessage(isset($data['message']) && !empty($data['message']) ? $data['message'] : 'Sorry, but we can\'t find tracking informaiton for %1.',$id));
                }
                if(isset($data['result']) && is_array($data['result']) && 0 < count($data['result'])){
                    $progressDetails = array();
                    foreach ($data['result'] as $job) {
                        $progressDetails[] = array('deliverydate'=> $job['createdTime'],'activity'=>$job['comment']['content']);
                    }
                    $result->append(Mage::getModel('shipping/tracking_result_status')
                                ->setCarrier(GoPeople_Shipping_Model_Carrier::CODE)
                                ->setCarrierTitle($this->getConfigData('title'))
                                ->setTracking($id)
                                ->addData(array('progressdetail'=>$progressDetails)));
                }
            }
            catch(\Throwable $e){
                $result->append(Mage::getModel('shipping/tracking_result_error')
                                    ->setCarrier(GoPeople_Shipping_Model_Carrier::CODE)
                                    ->setCarrierTitle($this->getConfigData('title'))
                                    ->setTracking($id)
                                    ->setErrorMessage($e->getMessage()));
            }
        }

        return $result;
    }

    /**
     * convert weight to kilograms
     *
     * @return float
     */
     public function getWeightInKG($storeId,$weight){
        switch(Mage::getStoreConfig('general/locale/weight_unit',$storeId)){
        case 'lbs': $weight *= 2.2; break;
        }
        return $weight;
    }

    /**
     * return a safe string
     *
     * @param string $text
     * @return string
     */
    protected function _slugify($text){
        $text = strtolower(preg_replace('~-+~', '-', trim(preg_replace('~[^-\w]+~', '', iconv('utf-8', 'us-ascii//TRANSLIT', preg_replace('~[^\pL\d]+~u', '-', $text))), '-')));
        return empty($text) ? 'n-a' : $text;
    }


}
