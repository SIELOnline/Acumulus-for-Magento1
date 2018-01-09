@echo off
mkdir D:\Projecten\Acumulus\Magento\www\app\code\community\Siel 2> nul
rmdir /s /q D:\Projecten\Acumulus\Magento\www\app\code\community\Siel\Acumulus 2> nul
mklink /J D:\Projecten\Acumulus\Magento\www\app\code\community\Siel\Acumulus D:\Projecten\Acumulus\Webkoppelingen\Magento1\acumulus\app\code\community\Siel\Acumulus
del D:\Projecten\Acumulus\Magento\www\app\etc\modules\Siel_Acumulus.xml 2> nul
mklink /H D:\Projecten\Acumulus\Magento\www\app\etc\modules\Siel_Acumulus.xml D:\Projecten\Acumulus\Webkoppelingen\Magento1\acumulus\app\etc\modules\Siel_Acumulus.xml
del D:\Projecten\Acumulus\Magento\www\skin\adminhtml\base\default\siel-acumulus-config-form.css 2> nul
mklink /H D:\Projecten\Acumulus\Magento\www\skin\adminhtml\base\default\siel-acumulus-config-form.css D:\Projecten\Acumulus\Webkoppelingen\Magento1\acumulus\skin\adminhtml\base\default\siel-acumulus-config-form.css
