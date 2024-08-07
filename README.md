# MTK IMEI patcher for Xiaomi phones with G9x series SoC
A script that recreates NVRAM partition and makes private TWRP flashable for your phone

## Supported devices
- Redmi Note 8 Pro (kernel 4.14.186 and higher)
- Redmi Note 10S / Redmi Note 11 SE / POCO M5s
- Redmi Note 12S

## [Guide](https://graph.org/IMEI-Restoration-05-04)

## Usage
1. Fill in your values in `config.txt` and run `mtk_imei.cmd`. Flashable `imei_repair.zip` will be generated in `out` folder.
2. Backup NVRAM and NVDATA partitions.
3. Flash `imei_repair.zip` in TWRP and reboot your phone.

## Uninstall
1. Restore NVRAM and NVDATA partitions from backup.
2. Mount Vendor partition and delete `/vendor/etc/init/md_patcher.rc` and `/vendor/lib/modules/md_patcher.ko` files.

## Notes
- The bootloader cannot be locked if you use this.
- Make sure your Vendor partition can be mounted read-write (use RO2RW for devices with dynamic partitions).
- Flashable supports both stock and custom kernels. You have to flash it again after you clean flash or update your ROM.
- Includes prebuilt `md_patcher.ko` [kernel module](https://github.com/timjosten/Xiaomi_Kernel_OpenSource/tree/begonia-r-oss/drivers/misc/mediatek/md_patcher). Kernel maintainers should not include it in their kernels because it works as external loadable module only.
- If it's not working and you're using custom ROM, your kernel is most likely missing this [commit](https://github.com/AgentFabulous/begonia/commit/111f687d092b7fd1ccc64710795035ef30520629). Ask your maintainer to include it in the kernel.

## Prerequisites
[Visual C++ Redistributable for Visual Studio 2015-2022](https://aka.ms/vs/17/release/vc_redist.x64.exe) (x64)
