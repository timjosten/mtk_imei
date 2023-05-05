<?php
  ini_set('memory_limit', '1024M');

  function intval32($value)
  {
    $value = $value & 0xFFFFFFFF;
    if($value & 0x80000000)
      $value = -((~$value & 0xFFFFFFFF) + 1);
    return $value;
  }

  function checksum_nvram($data, $tempNum = 0x0)
  {
    $sum = 0;
    $size = strlen($data);
    for($i = 0; $i < $size; $i += 4)
    {
      $value = @unpack('l', $data, $i)[1];
      if($value !== false)  // unpack() can get out of bounds
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

  echo "MTK IMEI patcher by timjosten\n\n";

  $config = file_get_contents('config.txt') or
    die("Cannot open config file.\n");
  $config = json_decode($config, true) or
    die("Malformed config file.\n");
  $config['wifi_mac'] = strtoupper(str_replace(':', '', $config['wifi_mac']));
  $config['bt_mac']   = strtoupper(str_replace(':', '', $config['bt_mac']));
  if(strlen($config['product'])  == 0
  || strlen($config['chip_id'])  != 34
  || strlen($config['imei_1'])   != 15
  || strlen($config['imei_2'])   != 15
  //|| strlen($config['meid'])     != 14
  || strlen($config['wifi_mac']) != 12
  || strlen($config['bt_mac'])   != 12)
    die("Incorrect values in config file.\n");

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
  $data .= $config['product'];
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

  $header = file_get_contents('data/header.bin') or
    die("Cannot open header data.\n");
  $cssd = $header.$cssd;

  $nvram = file_get_contents('data/nvram.bin') or
    die("Cannot open original NVRAM image.\n");
  $toc_size = 0x20000;
  $content_size = unpack('V', $nvram, 4)[1];
  $cssd_offset = strpos($nvram, '/mnt/vendor/nvdata/md/NVRAM/NVD_IMEI/CSSD_000') or
    die("Cannot find CSSD LID in NVRAM image.\n");
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
  fseek($fp, $cssd_offset);
  fwrite($fp, $cssd);
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

  copy('data/patch.zip', 'out/imei_repair.zip') or
    die("Cannot copy zip archive.\n");
  $zip = new ZipArchive;
  $zip->open('out/imei_repair.zip') or
    die("Cannot open zip archive.\n");
  $zip->addFile('out/nvram.img', 'nvram.img') or
    die("Cannot add file to zip archive.\n");
  $zip->close();

  exit("Success!\n");
