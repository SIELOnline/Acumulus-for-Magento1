<?php

/** @var Siel_Acumulus_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/** @var Siel_Acumulus_Helper_Data $helper */
$helper = Mage::helper('acumulus');
$helper->getAcumulusConfig()->upgrade('4.6.0');

$installer->endSetup();
