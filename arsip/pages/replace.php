<?php
$c = file_get_contents('C:\laragon\www\rekap-mukholif\arsip\pages\detail_reward_arsip.php');
$c = str_replace(['Pelanggaran', 'pelanggaran', 'Kasus', 'kasus', 'daftar_hitam', 'Daftar Hitam'], ['Reward', 'reward', 'Reward', 'reward', 'peringkat', 'Peringkat'], $c);
$c = str_replace("arsip_data_reward", "_TEMP_TABLE_", $c); // prevent double replace
$c = str_replace("arsip_data_pelanggaran", "arsip_data_reward", $c);
$c = str_replace("_TEMP_TABLE_", "arsip_data_pelanggaran", $c);

$c = str_replace("jenis_pelanggaran_id", "jenis_reward_id", $c);
$c = str_replace("jenis_pelanggaran_nama", "jenis_reward_nama", $c);

file_put_contents('C:\laragon\www\rekap-mukholif\arsip\pages\detail_reward_arsip.php', $c);
echo "Done";
