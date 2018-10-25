<?php
/**
 * @category    GoPeople
 * @package     GoPeople_Shipping
 * @copyright   Copyright (c) 2018 Ekky Software Pty Ltd (http://www.ekkysoftware.com)
 * @license     Proprietary, All Rights Reserved
 */

$installer = $this;
/* @var $installer Mage_Core_Model_Resource_Setup */

$installer->startSetup();
$installer->getConnection()
->addColumn($installer->getTable('sales/order'),
    'gopeople_cart_id',
    array(
        'type'     => Varien_Db_Ddl_Table::TYPE_TEXT,
        'nullable' => true,
        'length'   => 64,
        'comment'  => 'Go People\'s internal shopping cart id',
    )
);

$installer->getConnection()->addIndex(
    $installer->getTable('sales/order'),
    $installer->getIdxName('sales/order', array('shipping_method','gopeople_cart_id'), 
    Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX),
    array('shipping_method','gopeople_cart_id'),
    Varien_Db_Adapter_Interface::INDEX_TYPE_INDEX
);

$installer->endSetup();

