# MTK IMEI patcher for Xiaomi phones with Helio G Series SoC
A script that recreates NVRAM partition and makes private TWRP flashable for your phone

## Supported devices
- Redmi Note 8 Pro
- Redmi Note 10S / Redmi Note 11 SE / POCO M5s
- Redmi Note 12S
- Redmi 10 / Redmi 10 2022 / Redmi 10 Prime
- Redmi 12C / POCO C55

## [Guide](https://graph.org/IMEI-Restoration-05-04)

## Usage
1. Fill in your values in `config.txt` and run `mtk_imei.cmd`. Flashable `imei_repair.zip` will be generated in `out` folder.
2. Backup NVRAM and NVDATA partitions.
3. Flash `imei_repair.zip` in TWRP and reboot your phone.

## Uninstall
1. Restore NVRAM and NVDATA partitions from backup.
2. Mount Vendor partition and delete `/vendor/etc/init/md_patcher.rc` and `/vendor/lib/modules/md_patcher.ko` files.

## Notes
- The generated zip is also a Magisk module.
- The bootloader cannot be locked if you use this.
- Flashable supports both stock and custom kernels. You have to flash it again after you clean flash or update your ROM.
- Includes prebuilt `md_patcher.ko` [kernel module](https://github.com/timjosten/Xiaomi_Kernel_OpenSource/tree/begonia-r-oss/drivers/misc/mediatek/md_patcher). Kernel maintainers should not include it in their kernels because it works as external loadable module only.
- If it's not working and you're using custom ROM, your kernel is most likely missing this [commit](https://github.com/AgentFabulous/begonia/commit/111f687d092b7fd1ccc64710795035ef30520629). Ask your maintainer to include it in the kernel.

## Prerequisites
[Visual C++ Redistributable for Visual Studio 2015-2022](https://aka.ms/vs/17/release/vc_redist.x64.exe) (x64)
