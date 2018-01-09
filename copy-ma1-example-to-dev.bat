@echo off
mkdir D:\Projecten\Acumulus\Magento\www\app\code\community\Siel 2> nul
rmdir /s /q D:\Projecten\Acumulus\Magento\www\app\code\community\Siel\AcumulusCustomiseInvoice 2> nul
mklink /J D:\Projecten\Acumulus\Magento\www\app\code\community\Siel\AcumulusCustomiseInvoice D:\Projecten\Acumulus\Webkoppelingen\Magento1\acumulus-customise-invoice\app\code\community\Siel\AcumulusCustomiseInvoice
del D:\Projecten\Acumulus\Magento\www\app\etc\modules\Siel_AcumulusCustomiseInvoice.xml 2> nul
mklink /H D:\Projecten\Acumulus\Magento\www\app\etc\modules\Siel_AcumulusCustomiseInvoice.xml D:\Projecten\Acumulus\Webkoppelingen\Magento1\acumulus-customise-invoice\app\etc\modules\Siel_AcumulusCustomiseInvoice.xml
