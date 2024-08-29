# MTK IMEI patcher for Xiaomi phones with Helio G Series SoC
A script that recreates the NVRAM partition and makes a private TWRP flashable for your phone

## Supported devices
- Redmi Note 8 Pro
- Redmi Note 10S / Redmi Note 11 SE / POCO M5s
- Redmi Note 12S
- Redmi 10 / Redmi 10 2022 / Redmi 10 Prime / Redmi 10 Prime 2022 / Redmi Note 11 4G
- Redmi 12C / POCO C55

## [Guide](https://graph.org/IMEI-Restoration-05-04)

## Usage
1. Fill in your values in `config.txt` and run `mtk_imei.cmd`. The flashable `imei_repair.zip` will be generated in `out` folder.
2. Backup NVRAM and NVDATA partitions.
3. Flash `imei_repair.zip` in TWRP and reboot your phone.

## Uninstall
1. Restore NVRAM and NVDATA partitions from backup.
2. Mount Vendor partition and delete `/vendor/etc/init/md_patcher.rc` and `/vendor/lib/modules/md_patcher.ko` files.

## Notes
- The bootloader cannot be locked if you use this.
- The generated zip is also a Magisk module. It is installed automatically for devices with read-only dynamic partitions. Make sure your recovery can mount the internal storage before flashing the zip.
- If you don't want to use Magisk, make sure your system partitions can be mounted read-write (use RO2RW for devices with dynamic partitions).
- The flashable supports both stock and custom ROMs. You have to flash it again after you clean flash or update your ROM.
- If it's not working in *patch_cert 1* mode and you're using a custom ROM, then your kernel is most likely missing this [commit](https://github.com/AgentFabulous/begonia/commit/111f687d092b7fd1ccc64710795035ef30520629). Ask your maintainer to include it in the kernel.

## Prerequisites
[Visual C++ Redistributable for Visual Studio 2015-2022](https://aka.ms/vs/17/release/vc_redist.x64.exe) (x64)
