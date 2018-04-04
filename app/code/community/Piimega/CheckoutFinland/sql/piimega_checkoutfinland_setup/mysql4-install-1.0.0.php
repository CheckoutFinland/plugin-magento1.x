<?php

$setup = new Mage_Sales_Model_Mysql4_Setup('core_setup');

$setup->addAttribute('order_payment', 'checkoutfinland_transaction_id', array(
      'type' => 'varchar',
      'user_defined' => 0,
      'input' => '',
      'visible' => 1,
      'label' => 'CheckoutFinland Payment ID',
      'global' => 1,
      'is_configurable' => 1,
      'group' => 'General',
      'default' => ''
      )
);

$setup->addAttribute('order', 'checkoutfinland_stamp', array(
      'type' => Varien_Db_Ddl_Table::TYPE_BIGINT,
      'user_defined' => 0,
      'input' => '',
      'visible' => 0,
      'label' => 'CheckoutFinland Stamp',
      'global' => 1,
      'is_configurable' => 0,
      'group' => 'General'
      )
);


