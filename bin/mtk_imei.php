<?php
  ini_set('memory_limit', '1024M');

  function intval32($value)
  {
    $value = $value & 0xFFFFFFFF;
    if($value & 0x80000000)
      $value = -((~$value & 0xFFFFFFFF) + 1);
    return $value;
  }

  function checksum_nvram($data)
  {
    $sum = 0;
    $tempNum = 0;
    $size = strlen($data);
    for($i = 0; $i < $size; $i += 4)
    {
      $buf = substr($data, $i, 4);
      $value = unpack('l', str_pad($buf, 4, "\x00"))[1];
      if(strlen($buf) < 4)  // last incomplete read
      {
        $tempNum = $value;
        break;
      }
      $sum = ($i / 4) % 2 == 0 ? $sum ^ $value : $sum + $value;
    }
    return pack('l', intval32($sum + $tempNum) ^ $size);
  }

  function checksum_2b($data)
  {
    $sum = 0;
    $size = strlen($data);
    for($i = 0; $i < $size; $i++)
    {
      $value = unpack('C', $data, $i)[1];
      $sum = $i % 2 == 0 ? $sum + $value : $sum ^ $value;
    }
    return "\xAA".pack('C', $sum);
  }

  function checksum_8b($data)
  {
    $sum = '';
    $md5 = md5($data, true);
    for($i = 0; $i < 8; $i++)
      $sum[$i] = chr(ord($md5[$i]) ^ ord($md5[$i + 8]));
    return $sum;
  }

  function swap_bytes(&$data)
  {
    for($i = 0; $i < strlen($data); $i += 2)
    {
      $temp = $data[$i + 1];
      $data[$i + 1] = $data[$i];
      $data[$i] = $temp;
    }
  }

  function scramble_iv_key(&$iv, &$key, $xor)
  {
    swap_bytes($iv);
    swap_bytes($key);
    for($i = 0; $i < strlen($iv); $i++)
    {
      $iv[$i] = chr(ord($iv[$i]) ^ ord($xor[$i]));
      $key[$i] = chr(ord($key[$i]) ^ ord($iv[$i]));
    }
  }

  function convert_imei($imei)
  {
    if($imei == '000000000000000')
      return str_repeat("\xFF", 10);
    $imei .= 'F0000';
    swap_bytes($imei);
    return hex2bin($imei);
  }

  echo "MTK IMEI patcher by timjosten\n\n";

  $config = file_get_contents('config.txt') or
    die("Cannot open config file.\n");
  $config = json_decode($config, true) or
    die("Malformed config file.\n");
  $config['device']   = strlen($config['device']) ? $config['device'] : $config['product'];
  $config['patch_cert'] = $config['device'] == 'begonia' ? 1 : $config['patch_cert'];  // begonia is rsa-only
  $config['kernel']   = strlen($config['kernel']) ? $config['kernel'] : '4.14.186';
  $config['wifi_mac'] = strtoupper(str_replace(':', '', $config['wifi_mac']));
  $config['bt_mac']   = strtoupper(str_replace(':', '', $config['bt_mac']));
  if(strlen($config['device'])   == 0
  || strlen($config['chip_id'])  != 34
  || strlen($config['imei_1'])   != 15
  || strlen($config['imei_2'])   != 15
  //|| strlen($config['meid'])     != 14
  || strlen($config['wifi_mac']) != 12
  || strlen($config['bt_mac'])   != 12)
    die("Incorrect values in config file.\n");
  if($config['imei_1'] == $config['imei_2'])
    die("IMEI1 and IMEI2 must be different.\n");

  $alias_list = file_get_contents('data/alias.txt') or
    die("Cannot open alias list.\n");
  $alias_list = json_decode($alias_list, true) or
    die("Malformed alias list.\n");
  $device = $config['device'];
  foreach($alias_list as $original => $aliases)
    foreach($aliases as $alias)
      if($alias == $device)
      {
        $device = $original;
        break 2;
      }

  $privatekey_2048 = file_get_contents('data/private_2048.pem') or
    die("Cannot open private key 2048.\n");
  $privatekey_1024 = file_get_contents('data/private_1024.pem') or
    die("Cannot open private key 1024.\n");
  $modulus_1024 = openssl_pkey_get_details(openssl_pkey_get_private($privatekey_1024))['rsa']['n'];
  $exponent_1024 = '10001';
  if(strlen($modulus_1024) != 128)
    die("Cannot extract modulus from private key 1024.\n");

  $data = $exponent_1024;
  $data .= bin2hex($modulus_1024);
  $data .= $config['device'];
  $signature = "\x00\x01";
  $signature .= str_repeat("\xFF", 221);
  $signature .= "\x00";
  $signature .= hash('sha256', $data, true);
  openssl_private_encrypt($signature, $devPubKeySign, $privatekey_2048, OPENSSL_NO_PADDING) or
    die("Cannot sign using private key 2048.\n");
  $devPubKeySign = strtoupper(bin2hex($devPubKeySign));

  $data = "\x00\x01\x00\x62";  // ends with 0x72 for chinese begonia
  $data .= "\x01\x22".$config['chip_id'];
  $data .= "\x02\x0F".$config['imei_1'];
  $data .= "\x03\x0F".$config['imei_2'];
  //$data .= "\x04\x0E".$config['meid'];
  $data .= "\x05\x0C".$config['wifi_mac'];
  $data .= "\x06\x0C".$config['bt_mac'];
  $data = bin2hex($data);
  $signature = "\x00\x01";
  $signature .= str_repeat("\xFF", 93);
  $signature .= "\x00";
  $signature .= hash('sha256', $data, true);
  openssl_private_encrypt($signature, $crticalDataSign, $privatekey_1024, OPENSSL_NO_PADDING) or
    die("Cannot sign using private key 1024.\n");
  $crticalDataSign = bin2hex($crticalDataSign);

  $cssd = 'devPubKeyModulus:'.bin2hex($modulus_1024);
  $cssd .= "\\ndevPubKeyExponent:$exponent_1024";
  $cssd .= "\\ndevPubKeySign:$devPubKeySign";
  $cssd .= "\\ncriticalData:$data";
  $cssd .= "\\ncrticalDataSign:$crticalDataSign";
  $cssd = str_pad($cssd, 4096, "\x00");
  $cssd .= checksum_8b($cssd);

  $ld0b = '';
  for($i = 1; $i <= 10; $i++)
  {
    switch($i)
    {
      case 1:
      case 2:
        $imei = convert_imei($config["imei_$i"]);
        break;
      default:
        $imei = str_repeat("\xFF", 10);
    }
    $ld0b .= str_pad($imei.checksum_8b($imei), 32, "\x00");
  }
  $xor  = "\x8F\x9C\x61\x51\xDC\x86\xB9\x16\x3A\x37\x50\x6D\x9D\xFF\x77\x53\x46\x4B\xA7\x3E\x5E\xDE\xF3\x62\x5B\xA1\x8D\x48\x12\x35\x80\x5B";
  $iv   = "\x01\x02\x03\x04\x05\x06\x07\x08\x09\x0A\x0B\x0C\x0D\x0E\x0F\x10\x11\x12\x0B\x14\x15\x16\x17\x18\x19\x1A\x1B\x1C\x00\x00\x00\x00";
  $key  = "\x35\x23\x32\x53\x42\x45\x54\x24\x43\x86\x68\x34\x78\x56\x34\x12\x78\x56\x34\x12\x43\x86\x68\x34\x42\x45\x54\x24\x35\x23\x32\x53";
  $key2 = "\xBE\x41\x0C\x67\x39\x4D\x98\x01\x72\x56\xAA\x3C\x8F\x21\xBB\x42";
  scramble_iv_key($iv, $key, $xor);
  $key2 = openssl_encrypt($key2, 'aes-256-cbc', $key,  OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING, substr($iv, 0, 16));
  $ld0b = openssl_encrypt($ld0b, 'aes-128-ecb', $key2, OPENSSL_RAW_DATA | OPENSSL_ZERO_PADDING);

  $nvram = @file_get_contents("data/$device/nvram.bin") or
    die("Cannot open original NVRAM image (this device is not supported yet).\n");
  $toc_size = 0x20000;
  $content_size = unpack('V', $nvram, 4)[1];
  $ld0b_offset = strpos($nvram, '/mnt/vendor/nvdata/md/NVRAM/NVD_IMEI/LD0B_00');
  if($ld0b_offset !== false)
    $ld0b_offset = $toc_size + unpack('V', $nvram, $ld0b_offset - 8)[1];
  $cssd_offset = strpos($nvram, '/mnt/vendor/nvdata/md/NVRAM/NVD_IMEI/CSSD_000');
  if($cssd_offset !== false)
    $cssd_offset = $toc_size + unpack('V', $nvram, $cssd_offset - 8)[1];
  $wifi_offset = strpos($nvram, '/mnt/vendor/nvdata/APCFG/APRDEB/WIFI') or
    die("Cannot find WIFI file in NVRAM image.\n");
  $wifi_size = unpack('V', $nvram, $wifi_offset - 4)[1];
  $wifi_offset = $toc_size + unpack('V', $nvram, $wifi_offset - 8)[1];
  $bt_offset = strpos($nvram, '/mnt/vendor/nvdata/APCFG/APRDEB/BT_Addr') or
    die("Cannot find BT_Addr file in NVRAM image.\n");
  $bt_size = unpack('V', $nvram, $bt_offset - 4)[1];
  $bt_offset = $toc_size + unpack('V', $nvram, $bt_offset - 8)[1];
  $nvram = str_replace('/CALIBRAT/', '/CALIBRUH/', $nvram);
  @mkdir('out');
  file_put_contents('out/nvram.img', $nvram) or
    die("Cannot save NVRAM image.\n");
  $fp = fopen('out/nvram.img', 'r+b') or
    die("Cannot open NVRAM image.\n");
  if($ld0b_offset !== false)
  {
    fseek($fp, $ld0b_offset + 12);
    fwrite($fp, "\x0A");
    fseek($fp, $ld0b_offset + 64);
    fwrite($fp, $ld0b);
  }
  if($cssd_offset !== false)
  {
    fseek($fp, $cssd_offset + 64);
    fwrite($fp, $cssd);
  }
  fseek($fp, $wifi_offset + 4);
  fwrite($fp, hex2bin($config['wifi_mac']));
  fseek($fp, $wifi_offset);
  $wifi = fread($fp, $wifi_size - 2);
  fwrite($fp, checksum_2b($wifi));
  fseek($fp, $bt_offset);
  fwrite($fp, hex2bin($config['bt_mac']));
  fseek($fp, $bt_offset);
  $bt = fread($fp, $bt_size - 2);
  fwrite($fp, checksum_2b($bt));
  fseek($fp, $toc_size);
  $content = fread($fp, $content_size);
  fseek($fp, 0x0C);
  fwrite($fp, checksum_nvram($content));
  fclose($fp);

  $patch_cert = $config['patch_cert'] && $cssd_offset !== false;
  $kernel = $patch_cert ? '-'.$config['kernel'] : '';
  $out = "out/imei_repair-${config['device']}${kernel}.zip";
  copy('data/patch.zip', $out) or
    die("Cannot copy zip archive.\n");
  $zip = new ZipArchive;
  $zip->open($out) or
    die("Cannot open zip archive.\n");
  $zip->addFile('out/nvram.img', 'nvram.img') or
    die("Cannot add nvram.img to zip archive.\n");
  if($patch_cert)
  {
    $zip->addFile("data/$device/md_patcher${kernel}.ko", 'vendor/lib/modules/md_patcher.ko') or
      die("Cannot add md_patcher.ko to zip archive (the specified kernel version '${config['kernel']}' is not supported).\n");
    $zip->deleteName('system/bin/toybox2');
    $zip->deleteName('system/etc/init/md_patcher.rc');
  }
  else
    $zip->deleteName('vendor/etc/init/md_patcher.rc');
  $zip->addFile("data/$device/updater-script.txt", 'META-INF/com/google/android/updater-script') or
    die("Cannot add updater-script to zip archive.\n");
  $zip->close();
  unlink('out/nvram.img');

  exit("Success!\n");
