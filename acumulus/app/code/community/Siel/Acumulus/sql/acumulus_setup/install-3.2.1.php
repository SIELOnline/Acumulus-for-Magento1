<?php

/** @var $this Siel_Acumulus_Model_Resource_Setup */
$installer = $this;
$installer->startSetup();
$table = $installer->getTableDefinition();
$installer->getConnection()->createTable($table);
$installer->endSetup();
