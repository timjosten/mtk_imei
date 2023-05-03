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
Flashable supports all 4.14 kernels (both stock and OSS). You have to flash it every time you wipe your Vendor partition.
Includes prebuilt `md_patcher.ko` [kernel module](https://github.com/timjosten/Xiaomi_Kernel_OpenSource/tree/begonia-r-oss/drivers/misc/mediatek/md_patcher). Kernel maintainers should not include it in their kernels because it works as external loadable module only.

## Prerequisites
[Visual C++ Redistributable for Visual Studio 2015](https://www.microsoft.com/en-us/download/details.aspx?id=48145) (x64)
