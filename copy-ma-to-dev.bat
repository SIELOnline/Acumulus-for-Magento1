@echo off
mkdir D:\Projecten\Acumulus\Magento\www\app\code\community\Siel
mklink /J D:\Projecten\Acumulus\Magento\www\app\code\community\Siel\Acumulus D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\code\community\Siel\Acumulus
mklink /H D:\Projecten\Acumulus\Magento\www\app\etc\modules\Siel_Acumulus.xml D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\etc\modules\Siel_Acumulus.xml
mklink /H D:\Projecten\Acumulus\Magento\www\skin\adminhtml\base\default\siel-acumulus-config-form.css D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\skin\adminhtml\base\default\siel-acumulus-config-form.css
