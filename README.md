# MTK IMEI patcher for Redmi Note 8 Pro
A script that recreates NVRAM partition and makes private TWRP flashable for your phone

## Usage
1. Fill in your values in `config.txt` and run `mtk_imei.cmd`. Flashable `imei_repair.zip` will be generated in `out` folder.
2. Backup NVRAM and NVDATA partitions.
3. Flash `imei_repair.zip` in TWRP and reboot your phone.

## Uninstall
1. Restore NVRAM and NVDATA partitions from backup.
2. Mount Vendor partition and delete `/vendor/etc/init/md_patcher.rc` and `/vendor/lib/modules/md_patcher.ko` files.

## Notes
Currently only MIUI 12.5 stock kernel is supported or [ROMs](https://t.me/crDroidOSb) based on its vendor.

## Prerequisites
[Visual C++ Redistributable for Visual Studio 2015](https://www.microsoft.com/en-us/download/details.aspx?id=48145) (x64)
