@echo off
mkdir D:\Projecten\Acumulus\Magento\www\app\code\community\Siel
mklink /J D:\Projecten\Acumulus\Magento\www\app\code\community\Siel\AcumulusCustomiseInvoice D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus-customise-invoice\app\code\community\Siel\AcumulusCustomiseInvoice
mklink /H D:\Projecten\Acumulus\Magento\www\app\etc\modules\Siel_AcumulusCustomiseInvoice.xml D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus-customise-invoice\app\etc\modules\Siel_AcumulusCustomiseInvoice.xml
