<?php
// rapot/crud_tahunan/process.php
// Generate Rapor Tahunan — Custom AI, menggunakan fungsi generate_catatan.php
// Alur: baca rapot bulanan → hitung rata-rata → koreksi nilai → generate catatan otomatis → simpan

require_once __DIR__ . '/../../bootstrap/init.php';
require_once __DIR__ . '/../config/helper.php';
require_once __DIR__ . '/koreksi_nilai.php';
require_once __DIR__ . '/../api/generate_catatan.php';   // fungsi rule-based

guard('rapot_create');

if ($_SERVER['REQUEST_METHOD'] !== 'POST' || !isset($_POST['kamar_id'])) {
    header('Location: index.php');
    exit;
}

// Validasi Token CSRF
csrf_validate();

// ============================================================
// 1. AMBIL INPUT
// ============================================================
$kamar_id = (int)$_POST['kamar_id'];
$periode  = trim($_POST['periode'] ?? '');   // Format: 2024/2025

if (empty($periode) || !preg_match('/^\d{4}\/\d{4}$/', $periode)) {
    set_flash_message('Format periode tidak valid. Gunakan format: 2024/2025', 'danger');
    header('Location: index.php');
    exit;
}

// ============================================================
// 2. AMBIL SEMUA SANTRI DI KAMAR
// ============================================================
try {
    $stmt_santri = $conn->prepare("SELECT id, nama, kamar, kelas FROM santri WHERE kamar = ? ORDER BY nama");
    $stmt_santri->bind_param('s', $kamar_id);
    $stmt_santri->execute();
    $santri_list = $stmt_santri->get_result()->fetch_all(MYSQLI_ASSOC);
    $stmt_santri->close();
} catch (Exception $e) {
    set_flash_message('Gagal mengambil data santri: ' . $e->getMessage(), 'danger');
    header('Location: index.php');
    exit;
}

if (empty($santri_list)) {
    set_flash_message('Tidak ada santri di kamar ini.', 'warning');
    header('Location: index.php');
    exit;
}

// Nama kamar dari data santri
$nama_kamar = $santri_list[0]['kamar'] ?? (string)$kamar_id;

// ============================================================
// 3. BANGUN DATA PER SANTRI (rata-rata nilai bulanan)
// ============================================================
[$tahun_awal] = explode('/', $periode);
$tahun_awal   = (int)$tahun_awal;
$tahun_akhir  = $tahun_awal + 1;

// Mapping field DB → label sub mutu yang ramah
$sub_mutu_labels = [
    'puasa_sunnah'      => 'Puasa Sunnah',
    'sholat_duha'       => 'Sholat Duha',
    'sholat_malam'      => 'Sholat Malam',
    'sedekah'           => 'Sedekah & Berbagi',
    'sunnah_tidur'      => 'Sunnah sebelum tidur',
    'ibadah_lainnya'    => 'Ibadah lainnya',
    'lisan'             => 'Lisan',
    'sikap'             => 'Sikap & tingkah laku',
    'kesopanan'         => 'Kesopanan',
    'muamalah'          => 'Muamalah',
    'tidur'             => 'Tidur',
    'keterlambatan'     => 'Keterlambatan',
    'seragam'           => 'Seragam',
    'makan'             => 'Makan',
    'arahan'            => 'Mengikuti arahan',
    'bahasa_arab'       => 'Berbahasa arab di kamar',
    'mandi'             => 'Mandi',
    'penampilan'        => 'Penampilan & berpakaian',
    'piket'             => 'Piket',
    'kerapihan_barang'  => 'Kerapihan barang',
];

$aspek_fields = [
    'Ibadah'       => ['puasa_sunnah', 'sholat_duha', 'sholat_malam', 'sedekah', 'sunnah_tidur', 'ibadah_lainnya'],
    'Akhlaq'       => ['lisan', 'sikap', 'kesopanan', 'muamalah'],
    'Kedisiplinan' => ['tidur', 'keterlambatan', 'seragam', 'makan', 'arahan', 'bahasa_arab'],
    'Kebersihan'   => ['mandi', 'penampilan', 'piket', 'kerapihan_barang'],
];

$sukses = 0;
$gagal  = 0;
$skip   = 0;

$overwrite_approved = isset($_POST['overwrite_approved']) && $_POST['overwrite_approved'] == '1';

// Memulai Transaksi Database Massal (ACID)
$conn->begin_transaction();

foreach ($santri_list as $santri) {
    $sid = (int)$santri['id'];

    // ── Cek apakah rapot sudah APPROVED / EXPORTED ───────────────────
    $stmt_check = $conn->prepare("SELECT status FROM rapot_tahunan WHERE santri_id = ? AND periode = ?");
    $stmt_check->bind_param('is', $sid, $periode);
    $stmt_check->execute();
    $res_check = $stmt_check->get_result()->fetch_assoc();
    $stmt_check->close();

    if ($res_check && in_array($res_check['status'], ['APPROVED', 'EXPORTED'])) {
        if (!$overwrite_approved) {
            $skip++; // Lewati karena sudah di-approve dan user tidak centang "Timpa"
            continue;
        }
    }

    // Ambil rapot bulanan santri untuk periode ini
    try {
        $stmt_rb = $conn->prepare("
            SELECT bulan, tahun,
                   puasa_sunnah, sholat_duha, sholat_malam, sedekah, sunnah_tidur, ibadah_lainnya,
                   lisan, sikap, kesopanan, muamalah,
                   tidur, keterlambatan, seragam, makan, arahan, bahasa_arab,
                   mandi, penampilan, piket, kerapihan_barang,
                   total_poin_pelanggaran_saat_itu, total_poin_reward_saat_itu
            FROM rapot_kepengasuhan
            WHERE santri_id = ?
              AND (tahun = ? OR tahun = ?)
            ORDER BY tahun, FIND_IN_SET(bulan, 'Januari,Februari,Maret,April,Mei,Juni,Juli,Agustus,September,Oktober,November,Desember')
        ");
        $stmt_rb->bind_param('iii', $sid, $tahun_awal, $tahun_akhir);
        $stmt_rb->execute();
        $rapot_bulanan = $stmt_rb->get_result()->fetch_all(MYSQLI_ASSOC);
        $stmt_rb->close();
    } catch (Exception $e) {
        $gagal++;
        continue;
    }

    // Skip santri yang belum punya rapot bulanan
    if (empty($rapot_bulanan)) {
        $skip++;
        continue;
    }

    // Membungkus seluruh operasi mulai perhitungan sampai DB dalam satu try-catch untuk menghindari Fatal Error (white screen)
    try {
        // ── Hitung rata-rata nilai per sub-mutu ──────────────────
        $nilai_snapshot = [];
        foreach ($aspek_fields as $aspek_nama => $fields) {
            $sub_mutu_list = [];
            foreach ($fields as $field) {
                $total = 0;
                $count = 0;
                foreach ($rapot_bulanan as $rb) {
                    if (isset($rb[$field]) && $rb[$field] > 0) {
                        $total += $rb[$field];
                        $count++;
                    }
                }
                $avg = $count > 0 ? round($total / $count, 1) : 0;
                $sub_mutu_list[] = [
                    'field'          => $field,
                    'nama'           => $sub_mutu_labels[$field] ?? ucwords(str_replace('_', ' ', $field)),
                    'nilai_rata'     => $avg,
                    'nilai_final'    => $avg,
                    'ada_koreksi'    => false,
                    'alasan_koreksi' => null,
                ];
            }
            $nilai_snapshot[] = ['aspek' => $aspek_nama, 'sub_mutu' => $sub_mutu_list];
        }

        // ── Koreksi nilai berdasarkan frekuensi pelanggaran ──────
        apply_koreksi_nilai($conn, $sid, $tahun_awal, $tahun_akhir, $nilai_snapshot);

        // ── Tambahkan catatan per-aspek ke dalam snapshot ────────
        foreach ($nilai_snapshot as &$aspek_data) {
            $aspek_data['catatan'] = generate_catatan_per_aspek($aspek_data);
        }
        unset($aspek_data);

        // ── Total pelanggaran & reward setahun (Query langsung agar akurat) ───────────────────
        $sql_pel = "SELECT SUM(jp.poin) as total FROM pelanggaran p
                    JOIN jenis_pelanggaran jp ON p.jenis_pelanggaran_id = jp.id
                    WHERE p.santri_id = ? AND (YEAR(p.tanggal) = ? OR YEAR(p.tanggal) = ?)";
        $stmt_pel = $conn->prepare($sql_pel);
        $stmt_pel->bind_param('iii', $sid, $tahun_awal, $tahun_akhir);
        $stmt_pel->execute();
        $total_pelanggaran = (int)$stmt_pel->get_result()->fetch_assoc()['total'];
        $stmt_pel->close();

        $sql_rew = "SELECT SUM(jr.poin_reward) as total FROM daftar_reward rwd
                    JOIN jenis_reward jr ON rwd.jenis_reward_id = jr.id
                    WHERE rwd.santri_id = ? AND (YEAR(rwd.tanggal) = ? OR YEAR(rwd.tanggal) = ?)";
        $stmt_rew = $conn->prepare($sql_rew);
        $stmt_rew->bind_param('iii', $sid, $tahun_awal, $tahun_akhir);
        $stmt_rew->execute();
        $total_reward = (int)$stmt_rew->get_result()->fetch_assoc()['total'];
        $stmt_rew->close();

        // ── Generate catatan otomatis (rule-based, Custom AI) ─────
        $catatan_otomatis = "";
        if (has_permission('catatan_otomatis')) {
            $catatan_otomatis = generate_catatan_tahunan(
                $nilai_snapshot,
                $total_pelanggaran,
                $total_reward,
                (string)($santri['nama'] ?? '')
            );
        }

        // ── Simpan ke DB ─────────────────────────────────────────
    $snapshot_json = json_encode($nilai_snapshot, JSON_UNESCAPED_UNICODE);

    // Simpan total pelanggaran & reward dalam snapshot meta
    $meta_json = json_encode([
        'total_pelanggaran' => $total_pelanggaran,
        'total_reward'      => $total_reward,
        'jumlah_bulan'      => count($rapot_bulanan),
    ], JSON_UNESCAPED_UNICODE);

        $stmt_del = $conn->prepare("DELETE FROM rapot_tahunan WHERE santri_id = ? AND periode = ?");
        $stmt_del->bind_param('is', $sid, $periode);
        $stmt_del->execute();
        $stmt_del->close();

        $stmt_save = $conn->prepare("
            INSERT INTO rapot_tahunan
                (santri_id, periode, kamar, nilai_snapshot, narasi_ai, catatan_musyrif, status, is_fallback, generated_at)
            VALUES (?, ?, ?, ?, ?, ?, 'DRAFT', 0, NOW())
        ");
        // narasi_ai → catatan otomatis | catatan_musyrif → meta (poin)
        $stmt_save->bind_param('isssss',
            $sid, $periode, $nama_kamar,
            $snapshot_json,
            $catatan_otomatis,
            $meta_json
        );
        $stmt_save->execute();
        $stmt_save->close();
        $sukses++;

        write_activity_log('CREATE', 'rapot_tahunan',
            "Generate rapor tahunan santri ID {$sid} periode {$periode}",
            ['santri_id' => $sid, 'periode' => $periode]
        );
    } catch (Throwable $e) {
        error_log("[AsuhTrack] Gagal simpan/proses rapor tahunan santri $sid: " . $e->getMessage() . " in " . $e->getFile() . ":" . $e->getLine());
        $gagal++;
    }
}

// Jika ada yang gagal fatal di dalam transaksi, kita bisa mengevaluasi apakah harus rollback semua atau commit yang sukses
if ($gagal > 0 && $sukses === 0) {
    $conn->rollback();
} else {
    $conn->commit();
}

// ============================================================
// 4. REDIRECT DENGAN PESAN
// ============================================================
if ($sukses === 0 && $skip > 0) {
    set_flash_message("Tidak ada data rapot bulanan yang cukup. Pastikan rapot bulanan sudah diisi terlebih dahulu.", 'warning');
    header('Location: generate.php?kamar=' . urlencode($nama_kamar) . '&periode=' . urlencode($periode));
} elseif ($gagal > 0) {
    set_flash_message("Generate selesai: {$sukses} berhasil, {$gagal} gagal, {$skip} dilewati (belum ada rapot bulanan).", 'warning');
    header('Location: index.php?kamar=' . urlencode($nama_kamar) . '&periode=' . urlencode($periode));
} else {
    $msg = "Rapor tahunan berhasil di-generate untuk {$sukses} santri.";
    if ($skip > 0) $msg .= " {$skip} santri dilewati karena belum ada rapot bulanan.";
    $msg .= " Silakan review dan approve.";
    set_flash_message($msg, 'success');
    header('Location: index.php?kamar=' . urlencode($nama_kamar) . '&periode=' . urlencode($periode));
}
exit;
