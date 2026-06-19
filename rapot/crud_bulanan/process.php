<?php
// File: rekap-mukholif/rapot/crud_bulanan/process.php
// Proses penyimpanan rapot BULANAN
// (dipindah dari rapot/crud/process.php — path diupdate)

require_once __DIR__ . '/../../bootstrap/init.php';
guard('rapot_create');

if (isset($_POST['simpan_rapot'])) {

    csrf_validate(); // Validasi CSRF

    $santri_id = (int)$_POST['santri_id'];
    $bulan     = $_POST['bulan'];
    $tahun     = (int)$_POST['tahun'];
    $catatan   = trim($_POST['catatan_musyrif'] ?? '');
    if (strlen($catatan) < 15 || preg_match_all('/[a-zA-Z0-9]/', $catatan) < 10) {
        set_flash_message('Gagal menyimpan: Catatan tidak valid. Harap isi catatan dengan jelas (minimal 15 karakter huruf/angka).', 'danger');
        header('Location: create.php');
        exit;
    }
    $musyrif_id = (int)$_SESSION['user_id'];

    $puasa_sunnah     = (int)$_POST['puasa_sunnah'];
    $sholat_duha      = (int)$_POST['sholat_duha'];
    $sholat_malam     = (int)$_POST['sholat_malam'];
    $sedekah          = (int)$_POST['sedekah'];
    $sunnah_tidur     = (int)$_POST['sunnah_tidur'];
    $ibadah_lainnya   = (int)$_POST['ibadah_lainnya'];
    $lisan            = (int)$_POST['lisan'];
    $sikap            = (int)$_POST['sikap'];
    $kesopanan        = (int)$_POST['kesopanan'];
    $muamalah         = (int)$_POST['muamalah'];
    $tidur            = (int)$_POST['tidur'];
    $keterlambatan    = (int)$_POST['keterlambatan'];
    $seragam          = (int)$_POST['seragam'];
    $makan            = (int)$_POST['makan'];
    $arahan           = (int)$_POST['arahan'];
    $bahasa_arab      = (int)$_POST['bahasa_arab'];
    $mandi            = (int)$_POST['mandi'];
    $penampilan       = (int)$_POST['penampilan'];
    $piket            = (int)$_POST['piket'];
    $kerapihan_barang = (int)$_POST['kerapihan_barang'];

    try {
        // Memulai Transaksi Database (ACID)
        $conn->begin_transaction();

        // Persiapan rentang tanggal untuk optimasi index (Ganti FIND_IN_SET)
        $bulan_list_indo = [
            'Januari' => 1, 'Februari' => 2, 'Maret' => 3, 'April' => 4,
            'Mei' => 5, 'Juni' => 6, 'Juli' => 7, 'Agustus' => 8,
            'September' => 9, 'Oktober' => 10, 'November' => 11, 'Desember' => 12
        ];
        $bulan_num = $bulan_list_indo[$bulan] ?? date('n');
        $start_date = sprintf('%04d-%02d-01', $tahun, $bulan_num);
        $end_date = date('Y-m-t', strtotime($start_date));

        // Kalkulasi Total Poin Pelanggaran
        $total_poin_pelanggaran = 0;
        $sql_poin = "
            SELECT SUM(jp.poin) AS total_poin
            FROM pelanggaran p
            JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
            WHERE p.santri_id = ?
              AND p.tanggal >= ? AND p.tanggal <= ?
        ";
        $stmt_poin = $conn->prepare($sql_poin);
        $stmt_poin->bind_param("iss", $santri_id, $start_date, $end_date);
        $stmt_poin->execute();
        $hasil_poin = $stmt_poin->get_result()->fetch_assoc();
        if ($hasil_poin && $hasil_poin['total_poin'] > 0) {
            $total_poin_pelanggaran = (int)$hasil_poin['total_poin'];
        }
        $stmt_poin->close();

        // Kalkulasi Total Poin Reward
        $total_poin_reward = 0;
        $sql_reward = "
            SELECT SUM(jr.poin_reward) AS total_poin_reward
            FROM daftar_reward rwd
            JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
            WHERE rwd.santri_id = ?
              AND rwd.tanggal >= ? AND rwd.tanggal <= ?
        ";
        $stmt_reward = $conn->prepare($sql_reward);
        $stmt_reward->bind_param("iss", $santri_id, $start_date, $end_date);
        $stmt_reward->execute();
        $hasil_reward = $stmt_reward->get_result()->fetch_assoc();
        if ($hasil_reward && $hasil_reward['total_poin_reward'] > 0) {
            $total_poin_reward = (int)$hasil_reward['total_poin_reward'];
        }
        $stmt_reward->close();

        $sql_insert = "
            INSERT INTO rapot_kepengasuhan (
                santri_id, musyrif_id, bulan, tahun,
                puasa_sunnah, sholat_duha, sholat_malam, sedekah, sunnah_tidur, ibadah_lainnya,
                lisan, sikap, kesopanan, muamalah,
                tidur, keterlambatan, seragam, makan, arahan, bahasa_arab,
                mandi, penampilan, piket, kerapihan_barang,
                total_poin_pelanggaran_saat_itu, total_poin_reward_saat_itu, catatan_musyrif
            ) VALUES (
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?
            )
        ";

        $stmt_insert = $conn->prepare($sql_insert);
        if (!$stmt_insert) {
            throw new Exception("Prepare failed: " . $conn->error);
        }

        $types = "iiss" . str_repeat("i", 22) . "s";
        $success = $stmt_insert->bind_param(
            $types,
            $santri_id, $musyrif_id, $bulan, $tahun,
            $puasa_sunnah, $sholat_duha, $sholat_malam, $sedekah, $sunnah_tidur, $ibadah_lainnya,
            $lisan, $sikap, $kesopanan, $muamalah,
            $tidur, $keterlambatan, $seragam, $makan, $arahan, $bahasa_arab,
            $mandi, $penampilan, $piket, $kerapihan_barang,
            $total_poin_pelanggaran, $total_poin_reward, $catatan
        );

        if (!$success) {
            throw new Exception("Bind param failed: " . $stmt_insert->error);
        }

        $executed = $stmt_insert->execute();
        if (!$executed) {
            throw new Exception("Execute failed: " . $stmt_insert->error);
        }

        // Catat log aktivitas (Gunakan Prepared Statement)
        $nama_santri = "Unknown";
        $stmt_santri_log = $conn->prepare("SELECT nama FROM santri WHERE id = ?");
        $stmt_santri_log->bind_param("i", $santri_id);
        $stmt_santri_log->execute();
        $r_santri = $stmt_santri_log->get_result()->fetch_assoc();
        if ($r_santri) {
            $nama_santri = $r_santri['nama'];
        }
        $stmt_santri_log->close();

        write_activity_log('CREATE', 'rapot', "Menginput nilai rapot kepengasuhan untuk '" . htmlspecialchars($nama_santri) . "' periode $bulan $tahun", [
            'santri_id'   => $santri_id,
            'nama_santri' => $nama_santri,
            'bulan'       => $bulan,
            'tahun'       => $tahun,
        ]);

        $conn->commit(); // Selesaikan transaksi dengan sukses

        set_flash_message('Rapot baru berhasil disimpan!', 'success');
        header('Location: ../index.php');
        exit;

    } catch (Throwable $e) {
        $conn->rollback(); // Batalkan semua aksi jika ada kegagalan
        error_log("[AsuhTrack] RAPOT BULANAN PROCESS ERROR: " . $e->getMessage());
        set_flash_message('Error: ' . $e->getMessage(), 'danger');
        header('Location: create.php');
        exit;

    } finally {
        if (isset($stmt_poin) && $stmt_poin) $stmt_poin->close();
        if (isset($stmt_reward) && $stmt_reward) $stmt_reward->close();
        if (isset($stmt_insert) && $stmt_insert) $stmt_insert->close();
    }

} else {
    header('Location: create.php');
    exit;
}
