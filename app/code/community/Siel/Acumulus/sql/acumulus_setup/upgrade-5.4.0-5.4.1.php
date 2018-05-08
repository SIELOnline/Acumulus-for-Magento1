<?php

/** @var Siel_Acumulus_Model_Resource_Setup $this */
$installer = $this;
$installer->startSetup();

/** @var Siel_Acumulus_Helper_Data $helper */
$helper = Mage::helper('acumulus');
$helper->getAcumulusContainer()->getConfig()->upgrade('5.4.0');

$installer->endSetup();
