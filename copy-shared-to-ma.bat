@echo off
rem Link Common library to here.
mkdir D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\code\community\Siel\Acumulus\lib\siel
mklink /J D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\code\community\Siel\Acumulus\lib\siel\acumulus D:\Projecten\Acumulus\Webkoppelingen\libAcumulus

rem Link license files to here.
mklink /H D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\code\community\Siel\Acumulus\license.txt D:\Projecten\Acumulus\Webkoppelingen\libAcumulus\license.txt
mklink /H D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\code\community\Siel\Acumulus\licentie-nl.pdf D:\Projecten\Acumulus\Webkoppelingen\libAcumulus\licentie-nl.pdf
mklink /H D:\Projecten\Acumulus\Webkoppelingen\Magento\acumulus\app\code\community\Siel\Acumulus\leesmij.txt D:\Projecten\Acumulus\Webkoppelingen\leesmij.txt
