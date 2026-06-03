<?php
/**
 * pengaturan/impor_data/proses.php
 * Logika Pemrosesan Sinkronisasi Data (Impor Massal)
 *
 * Tipe data yang didukung:
 *   - santri              → tabel santri
 *   - jenis_pelanggaran   → tabel jenis_pelanggaran
 *   - jenis_reward        → tabel jenis_reward
 */
require_once __DIR__ . '/../../bootstrap/init.php';
guard('impor_data');

require_once __DIR__ . '/../../vendor/autoload.php';
require_once __DIR__ . '/smart_reader.php';

use PhpOffice\PhpSpreadsheet\IOFactory;

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    header('Location: index.php');
    exit;
}

// ── Verifikasi CSRF Token ───────────────────────────────────────────────────
$csrf_token = $_POST['csrf_token'] ?? '';
// Gunakan hash_equals() untuk perbandingan timing-safe (mencegah timing attack)
if (empty($csrf_token) || empty($_SESSION['csrf_token']) || !hash_equals($_SESSION['csrf_token'], $csrf_token)) {
    $_SESSION['sync_error_type'] = 'csrf';
    $_SESSION['sync_error_msg']  = 'Sesi halaman Anda telah berakhir demi keamanan data. '
        . 'Cukup muat ulang (refresh) halaman ini untuk melanjutkan sinkronisasi.';
    header('Location: index.php');
    exit;
}

$action = $_POST['action'] ?? '';

// ════════════════════════════════════════════════════════════════════════════
// ACTION: PREVIEW
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'preview') {

    $type = $_POST['tipe_data'] ?? '';
    $mode = $_POST['mode_sinkronisasi'] ?? 'update_insert';

    $allowed_types = ['santri', 'jenis_pelanggaran', 'jenis_reward'];
    if (!in_array($type, $allowed_types, true)) {
        $_SESSION['sync_error_msg'] = 'Tipe data tidak valid.';
        header('Location: index.php');
        exit;
    }

    if (!isset($_FILES['file_impor']) || $_FILES['file_impor']['error'] !== UPLOAD_ERR_OK) {
        $_SESSION['sync_error_msg'] = 'Gagal mengunggah file. Pastikan Anda memilih file yang valid.';
        header('Location: index.php');
        exit;
    }

    $file_ext = strtolower(pathinfo($_FILES['file_impor']['name'], PATHINFO_EXTENSION));
    if (!in_array($file_ext, ['xls', 'xlsx', 'csv'], true)) {
        $_SESSION['sync_error_msg'] = 'Format file tidak didukung. Gunakan .xlsx atau .csv.';
        header('Location: index.php');
        exit;
    }

    $temp_file = tempnam(sys_get_temp_dir(), 'sync_') . '.' . $file_ext;
    move_uploaded_file($_FILES['file_impor']['tmp_name'], $temp_file);
    $_SESSION['sync_temp_file'] = $temp_file;
    $_SESSION['sync_type']      = $type;
    $_SESSION['sync_mode']      = $mode;

    try {
        $spreadsheet = IOFactory::load($temp_file);
        $original_rows = $spreadsheet->getActiveSheet()->toArray();

        if (count($original_rows) <= 1) {
            throw new Exception('File kosong atau hanya berisi header.');
        }

        $extracted    = extract_smart_header($original_rows, $type);
        $header       = $extracted['header'];
        $header_index = $extracted['index'];

        if ($header_index === -1) {
             throw new Exception('Format file tidak dikenali. Tidak dapat menemukan baris header yang valid.');
        }

        $rows_raw = array_values(array_slice($original_rows, $header_index + 1));
        $rows     = filter_smart_data_rows($rows_raw);
        $preview_list    = [];
        $file_ids        = [];
        $seen_excel_ids  = [];

        // ────────────────────────────────────────────────────────────────────
        // SANTRI
        // ────────────────────────────────────────────────────────────────────
        if ($type === 'santri') {

            $has_nama  = has_smart_column($header, ['nama', 'santri', 'name']);
            $has_kelas = has_smart_column($header, ['kelas', 'kls', 'grade', 'tingkat']);
            $has_kamar = has_smart_column($header, ['kamar', 'kmr', 'room', 'asrama']);

            if (!$has_nama) {
                throw new Exception("Format kolom tidak sesuai. Kolom 'Nama Santri' tidak terdeteksi.");
            }

            // Anti-salah-tipe: cek kolom yang tidak biasa pada data santri
            $non_santri = ['harga', 'nama_pelanggaran', 'nama_reward', 'poin_reward',
                           'bagian', 'deskripsi', 'kategori_pelanggaran'];
            foreach ($header as $h) {
                foreach ($non_santri as $ind) {
                    if (strpos($h, $ind) !== false) {
                        throw new Exception(
                            "Format kolom tidak sesuai. Kolom '{$h}' tidak umum pada Data Santri. "
                            . "Pastikan tipe data yang dipilih sudah benar."
                        );
                    }
                }
            }

            // Ambil semua santri dari DB
            $res = mysqli_query($conn, 'SELECT id, nama, kelas, kamar FROM santri');
            $db_map = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $db_map[$r['id']] = $r;
            }

            foreach ($rows as $idx => $row) {
                $row_data = build_row_data($header, $row);

                $id_raw = get_smart_value($row_data, ['id_santri', 'id_siswa', 'id']);
                $id     = ($id_raw !== null && trim((string)$id_raw) !== '') ? (int)$id_raw : null;
                if ($id !== null && $id <= 0) $id = null;

                $db_row = ($id && isset($db_map[$id])) ? $db_map[$id] : null;

                $nama  = $has_nama  ? (string)(get_smart_value($row_data, ['nama', 'santri', 'name']) ?? '') : ($db_row['nama'] ?? '');
                $kelas = $has_kelas ? (string)(get_smart_value($row_data, ['kelas', 'kls', 'grade', 'tingkat']) ?? '') : ($db_row['kelas'] ?? '');
                $kamar = $has_kamar ? (string)(get_smart_value($row_data, ['kamar', 'kmr', 'room', 'asrama']) ?? '') : ($db_row['kamar'] ?? '');

                if (empty($nama) && empty($id)) continue;

                if (trim($nama) === '') {
                    throw new Exception("Kolom Nama kosong pada baris " . ($idx + 2) . ". Kolom Nama wajib diisi.");
                }
                if ($id !== null) {
                    if (isset($seen_excel_ids[$id])) {
                        throw new Exception("Duplikasi ID '{$id}' pada baris " . ($idx + 2) . ". Setiap baris harus memiliki ID unik.");
                    }
                    $seen_excel_ids[$id] = true;
                }

                if ($id && isset($db_map[$id])) {
                    $file_ids[] = $id;
                    if (!is_similar_name($db_row['nama'], $nama)) {
                        $preview_list[] = [
                            'action'       => 'INSERT',
                            'is_fatal'     => true,
                            'fatal_reason' => "ID {$id} sudah terdaftar atas nama \"{$db_row['nama']}\" "
                                . "di database, tetapi di Excel tertulis \"{$nama}\". "
                                . "Jika ini data baru, kosongkan kolom ID.",
                            'data'     => ['id' => $id, 'nama' => $nama, 'kelas' => $kelas, 'kamar' => $kamar],
                            'old_data' => $db_row,
                        ];
                    } else {
                        $is_diff    = trim($nama)  !== trim($db_row['nama'])
                                   || trim($kelas) !== trim($db_row['kelas'])
                                   || trim($kamar) !== trim($db_row['kamar']);
                        if ($is_diff) {
                            $preview_list[] = [
                                'action'   => 'UPDATE',
                                'data'     => ['id' => $id, 'nama' => $nama, 'kelas' => $kelas, 'kamar' => $kamar],
                                'old_data' => $db_row,
                            ];
                        }
                    }
                } else {
                    if ($id) $file_ids[] = $id;
                    if (trim($nama) !== '') {
                        $preview_list[] = [
                            'action' => 'INSERT',
                            'data'   => ['id' => $id, 'nama' => $nama, 'kelas' => $kelas, 'kamar' => $kamar],
                        ];
                    }
                }
            }

            if ($mode === 'full_sync') {
                foreach ($db_map as $db_id => $db_row) {
                    if (!in_array($db_id, $file_ids)) {
                        $preview_list[] = ['action' => 'DELETE', 'data' => $db_row];
                    }
                }
            }

        // ────────────────────────────────────────────────────────────────────
        // JENIS PELANGGARAN
        // ────────────────────────────────────────────────────────────────────
        } elseif ($type === 'jenis_pelanggaran') {

            $has_nama     = has_smart_column($header, ['nama_pelanggaran', 'nama', 'pelanggaran']);
            $has_bagian   = has_smart_column($header, ['bagian', 'divisi', 'section', 'dept']);
            $has_poin     = has_smart_column($header, ['poin', 'point', 'skor', 'score', 'nilai']);
            $has_kategori = has_smart_column($header, ['kategori', 'category', 'tingkat', 'level']);

            if (!$has_nama) {
                throw new Exception("Format kolom tidak sesuai. Kolom 'Nama Pelanggaran' tidak terdeteksi.");
            }

            $res = mysqli_query($conn, 'SELECT id, nama_pelanggaran, bagian, poin, kategori FROM jenis_pelanggaran');
            $db_map = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $db_map[$r['id']] = $r;
            }

            foreach ($rows as $idx => $row) {
                $row_data = build_row_data($header, $row);

                $id_raw = get_smart_value($row_data, ['id_pelanggaran', 'id_jenis', 'id']);
                $id     = ($id_raw !== null && trim((string)$id_raw) !== '') ? (int)$id_raw : null;
                if ($id !== null && $id <= 0) $id = null;

                $db_row = ($id && isset($db_map[$id])) ? $db_map[$id] : null;

                $nama_pelanggaran = $has_nama
                    ? (string)(get_smart_value($row_data, ['nama_pelanggaran', 'nama', 'pelanggaran']) ?? '')
                    : ($db_row['nama_pelanggaran'] ?? '');

                $bagian_raw = $has_bagian
                    ? get_smart_value($row_data, ['bagian', 'divisi', 'section', 'dept'])
                    : ($db_row['bagian'] ?? null);
                $bagian = normalize_bagian((string)($bagian_raw ?? ''));

                $poin_raw = $has_poin
                    ? get_smart_value($row_data, ['poin', 'point', 'skor', 'score', 'nilai'])
                    : ($db_row['poin'] ?? 0);
                $poin = max(0, (int)$poin_raw);

                $kategori_raw = $has_kategori
                    ? get_smart_value($row_data, ['kategori', 'category', 'tingkat', 'level'])
                    : ($db_row['kategori'] ?? null);
                $kategori = normalize_kategori_pelanggaran((string)($kategori_raw ?? ''));

                if (empty($nama_pelanggaran) && empty($id)) continue;

                if (trim($nama_pelanggaran) === '') {
                    throw new Exception("Kolom Nama Pelanggaran kosong pada baris " . ($idx + 2) . ".");
                }
                if ($bagian === null) {
                    throw new Exception(
                        "Nilai Bagian tidak dikenali pada baris " . ($idx + 2)
                        . " (\"" . htmlspecialchars((string)($bagian_raw ?? '')) . "\"). "
                        . "Nilai valid: Bahasa, Diniyyah, Kesantrian, Pengabdian, Tahfidz."
                    );
                }
                if ($kategori === null) {
                    throw new Exception(
                        "Nilai Kategori tidak dikenali pada baris " . ($idx + 2)
                        . " (\"" . htmlspecialchars((string)($kategori_raw ?? '')) . "\"). "
                        . "Nilai valid: Ringan, Sedang, Berat, Sangat Berat."
                    );
                }
                if ($id !== null) {
                    if (isset($seen_excel_ids[$id])) {
                        throw new Exception("Duplikasi ID '{$id}' pada baris " . ($idx + 2) . ".");
                    }
                    $seen_excel_ids[$id] = true;
                }

                $data_row = [
                    'id'               => $id,
                    'nama_pelanggaran' => $nama_pelanggaran,
                    'bagian'           => $bagian,
                    'poin'             => $poin,
                    'kategori'         => $kategori,
                ];

                if ($id && isset($db_map[$id])) {
                    $file_ids[] = $id;
                    if (!is_similar_name($db_row['nama_pelanggaran'], $nama_pelanggaran)) {
                        $preview_list[] = [
                            'action'       => 'INSERT',
                            'is_fatal'     => true,
                            'fatal_reason' => "ID {$id} sudah terdaftar untuk \"{$db_row['nama_pelanggaran']}\" "
                                . "di database, tetapi di Excel tertulis \"{$nama_pelanggaran}\". "
                                . "Jika ini data baru, kosongkan kolom ID.",
                            'data'     => $data_row,
                            'old_data' => $db_row,
                        ];
                    } else {
                        $is_diff = trim($nama_pelanggaran) !== trim($db_row['nama_pelanggaran'])
                                || trim($bagian)            !== trim($db_row['bagian'])
                                || (int)$poin               !== (int)$db_row['poin']
                                || trim($kategori)          !== trim($db_row['kategori']);
                        if ($is_diff) {
                            $preview_list[] = ['action' => 'UPDATE', 'data' => $data_row, 'old_data' => $db_row];
                        }
                    }
                } else {
                    if ($id) $file_ids[] = $id;
                    $preview_list[] = ['action' => 'INSERT', 'data' => $data_row];
                }
            }

            if ($mode === 'full_sync') {
                foreach ($db_map as $db_id => $db_row) {
                    if (!in_array($db_id, $file_ids)) {
                        $preview_list[] = ['action' => 'DELETE', 'data' => $db_row];
                    }
                }
            }

        // ────────────────────────────────────────────────────────────────────
        // JENIS REWARD
        // ────────────────────────────────────────────────────────────────────
        } elseif ($type === 'jenis_reward') {

            $has_nama = has_smart_column($header, ['nama_reward', 'nama', 'reward', 'penghargaan']);
            $has_poin = has_smart_column($header, ['poin_reward', 'poin', 'point', 'skor', 'score', 'nilai']);
            $has_desk = has_smart_column($header, ['deskripsi', 'description', 'keterangan', 'desk']);

            if (!$has_nama) {
                throw new Exception("Format kolom tidak sesuai. Kolom 'Nama Reward' tidak terdeteksi.");
            }

            $res = mysqli_query($conn, 'SELECT id, nama_reward, poin_reward, deskripsi FROM jenis_reward');
            $db_map = [];
            while ($r = mysqli_fetch_assoc($res)) {
                $db_map[$r['id']] = $r;
            }

            foreach ($rows as $idx => $row) {
                $row_data = build_row_data($header, $row);

                $id_raw = get_smart_value($row_data, ['id_reward', 'id_jenis', 'id']);
                $id     = ($id_raw !== null && trim((string)$id_raw) !== '') ? (int)$id_raw : null;
                if ($id !== null && $id <= 0) $id = null;

                $db_row = ($id && isset($db_map[$id])) ? $db_map[$id] : null;

                $nama_reward = $has_nama
                    ? (string)(get_smart_value($row_data, ['nama_reward', 'nama', 'reward', 'penghargaan']) ?? '')
                    : ($db_row['nama_reward'] ?? '');

                $poin_raw = $has_poin
                    ? get_smart_value($row_data, ['poin_reward', 'poin', 'point', 'skor', 'score', 'nilai'])
                    : ($db_row['poin_reward'] ?? 0);
                $poin_reward = normalize_poin_reward($poin_raw);

                $deskripsi = $has_desk
                    ? (string)(get_smart_value($row_data, ['deskripsi', 'description', 'keterangan', 'desk']) ?? '')
                    : ($db_row['deskripsi'] ?? '');

                if (empty($nama_reward) && empty($id)) continue;

                if (trim($nama_reward) === '') {
                    throw new Exception("Kolom Nama Reward kosong pada baris " . ($idx + 2) . ". Kolom Nama Reward wajib diisi.");
                }
                if ($poin_reward < 0) {
                    throw new Exception("Poin Reward bernilai negatif pada baris " . ($idx + 2) . ".");
                }
                if ($id !== null) {
                    if (isset($seen_excel_ids[$id])) {
                        throw new Exception("Duplikasi ID '{$id}' pada baris " . ($idx + 2) . ".");
                    }
                    $seen_excel_ids[$id] = true;
                }

                $data_row = [
                    'id'          => $id,
                    'nama_reward' => $nama_reward,
                    'poin_reward' => $poin_reward,
                    'deskripsi'   => $deskripsi,
                ];

                if ($id && isset($db_map[$id])) {
                    $file_ids[] = $id;
                    if (!is_similar_name($db_row['nama_reward'], $nama_reward)) {
                        $preview_list[] = [
                            'action'       => 'INSERT',
                            'is_fatal'     => true,
                            'fatal_reason' => "ID {$id} sudah terdaftar untuk \"{$db_row['nama_reward']}\" "
                                . "di database, tetapi di Excel tertulis \"{$nama_reward}\". "
                                . "Jika ini data baru, kosongkan kolom ID.",
                            'data'     => $data_row,
                            'old_data' => $db_row,
                        ];
                    } else {
                        $is_diff = trim($nama_reward)  !== trim($db_row['nama_reward'])
                                || (float)$poin_reward !== (float)$db_row['poin_reward']
                                || trim($deskripsi)    !== trim($db_row['deskripsi']);
                        if ($is_diff) {
                            $preview_list[] = ['action' => 'UPDATE', 'data' => $data_row, 'old_data' => $db_row];
                        }
                    }
                } else {
                    if ($id) $file_ids[] = $id;
                    $preview_list[] = ['action' => 'INSERT', 'data' => $data_row];
                }
            }

            if ($mode === 'full_sync') {
                foreach ($db_map as $db_id => $db_row) {
                    if (!in_array($db_id, $file_ids)) {
                        $preview_list[] = ['action' => 'DELETE', 'data' => $db_row];
                    }
                }
            }
        }

        $_SESSION['sync_preview_data'] = $preview_list;

    } catch (Exception $e) {
        $_SESSION['sync_error_msg'] = $e->getMessage();
        if (isset($_SESSION['sync_temp_file']) && file_exists($_SESSION['sync_temp_file'])) {
            @unlink($_SESSION['sync_temp_file']);
        }
        unset($_SESSION['sync_preview_data'], $_SESSION['sync_temp_file'],
              $_SESSION['sync_type'], $_SESSION['sync_mode']);
    }

    header('Location: index.php');
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: CONFIRM — Terapkan perubahan ke database dalam satu transaksi
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'confirm') {

    if (!isset($_SESSION['sync_preview_data'], $_SESSION['sync_type'])) {
        $_SESSION['sync_error_msg'] = 'Sesi sinkronisasi tidak valid atau sudah kedaluwarsa.';
        header('Location: index.php');
        exit;
    }

    $preview_list = $_SESSION['sync_preview_data'];
    $type         = $_SESSION['sync_type'];
    $insert_count = 0;
    $update_count = 0;
    $delete_count = 0;

    mysqli_begin_transaction($conn);

    try {

        // ── SANTRI ──────────────────────────────────────────────────────────
        if ($type === 'santri') {

            $stmt_ins    = mysqli_prepare($conn, 'INSERT INTO santri (id, nama, kelas, kamar) VALUES (?, ?, ?, ?)');
            $stmt_ins_ni = mysqli_prepare($conn, 'INSERT INTO santri (nama, kelas, kamar) VALUES (?, ?, ?)');
            $stmt_upd    = mysqli_prepare($conn, 'UPDATE santri SET nama = ?, kelas = ?, kamar = ? WHERE id = ?');
            $stmt_del_pel = mysqli_prepare($conn, 'DELETE FROM pelanggaran WHERE santri_id = ?');
            $stmt_del_rew = mysqli_prepare($conn, 'DELETE FROM daftar_reward WHERE santri_id = ?');
            $stmt_del     = mysqli_prepare($conn, 'DELETE FROM santri WHERE id = ?');


            foreach ($preview_list as $row) {
                $d = $row['data'];
                if ($row['action'] === 'INSERT') {
                    if (!empty($d['id']) && empty($row['is_fatal'])) {
                        mysqli_stmt_bind_param($stmt_ins, 'isss', $d['id'], $d['nama'], $d['kelas'], $d['kamar']);
                        mysqli_stmt_execute($stmt_ins);
                    } else {
                        mysqli_stmt_bind_param($stmt_ins_ni, 'sss', $d['nama'], $d['kelas'], $d['kamar']);
                        mysqli_stmt_execute($stmt_ins_ni);
                    }
                    $insert_count++;
                } elseif ($row['action'] === 'UPDATE') {
                    mysqli_stmt_bind_param($stmt_upd, 'sssi', $d['nama'], $d['kelas'], $d['kamar'], $d['id']);
                    mysqli_stmt_execute($stmt_upd);
                    $update_count++;
                } elseif ($row['action'] === 'DELETE') {
                    $sid = $d['id'];
                    mysqli_stmt_bind_param($stmt_del_pel, 'i', $sid);
                    mysqli_stmt_execute($stmt_del_pel);
                    mysqli_stmt_bind_param($stmt_del_rew, 'i', $sid);
                    mysqli_stmt_execute($stmt_del_rew);
                    mysqli_stmt_bind_param($stmt_del, 'i', $sid);
                    mysqli_stmt_execute($stmt_del);
                    $delete_count++;
                }
            }

            write_activity_log('SYNC', 'santri',
                "Sinkronisasi massal Data Santri: {$insert_count} INSERT, {$update_count} UPDATE, {$delete_count} DELETE.",
                ['insert' => $insert_count, 'update' => $update_count, 'delete' => $delete_count]
            );

        // ── JENIS PELANGGARAN ────────────────────────────────────────────────
        } elseif ($type === 'jenis_pelanggaran') {

            $stmt_ins    = mysqli_prepare($conn, 'INSERT INTO jenis_pelanggaran (id, nama_pelanggaran, bagian, poin, kategori) VALUES (?, ?, ?, ?, ?)');
            $stmt_ins_ni = mysqli_prepare($conn, 'INSERT INTO jenis_pelanggaran (nama_pelanggaran, bagian, poin, kategori) VALUES (?, ?, ?, ?)');
            $stmt_upd    = mysqli_prepare($conn, 'UPDATE jenis_pelanggaran SET nama_pelanggaran = ?, bagian = ?, poin = ?, kategori = ? WHERE id = ?');
            $stmt_del    = mysqli_prepare($conn, 'DELETE FROM jenis_pelanggaran WHERE id = ?');

            foreach ($preview_list as $row) {
                $d = $row['data'];
                if ($row['action'] === 'INSERT') {
                    if (!empty($d['id']) && empty($row['is_fatal'])) {
                        mysqli_stmt_bind_param($stmt_ins, 'issis', $d['id'], $d['nama_pelanggaran'], $d['bagian'], $d['poin'], $d['kategori']);
                        mysqli_stmt_execute($stmt_ins);
                    } else {
                        mysqli_stmt_bind_param($stmt_ins_ni, 'ssis', $d['nama_pelanggaran'], $d['bagian'], $d['poin'], $d['kategori']);
                        mysqli_stmt_execute($stmt_ins_ni);
                    }
                    $insert_count++;
                } elseif ($row['action'] === 'UPDATE') {
                    mysqli_stmt_bind_param($stmt_upd, 'ssisi', $d['nama_pelanggaran'], $d['bagian'], $d['poin'], $d['kategori'], $d['id']);
                    mysqli_stmt_execute($stmt_upd);
                    $update_count++;
                } elseif ($row['action'] === 'DELETE') {
                    mysqli_stmt_bind_param($stmt_del, 'i', $d['id']);
                    mysqli_stmt_execute($stmt_del);
                    $delete_count++;
                }
            }

            write_activity_log('SYNC', 'jenis_pelanggaran',
                "Sinkronisasi massal Jenis Pelanggaran: {$insert_count} INSERT, {$update_count} UPDATE, {$delete_count} DELETE.",
                ['insert' => $insert_count, 'update' => $update_count, 'delete' => $delete_count]
            );

        // ── JENIS REWARD ─────────────────────────────────────────────────────
        } elseif ($type === 'jenis_reward') {

            $stmt_ins    = mysqli_prepare($conn, 'INSERT INTO jenis_reward (id, nama_reward, poin_reward, deskripsi) VALUES (?, ?, ?, ?)');
            $stmt_ins_ni = mysqli_prepare($conn, 'INSERT INTO jenis_reward (nama_reward, poin_reward, deskripsi) VALUES (?, ?, ?)');
            $stmt_upd    = mysqli_prepare($conn, 'UPDATE jenis_reward SET nama_reward = ?, poin_reward = ?, deskripsi = ? WHERE id = ?');
            $stmt_del    = mysqli_prepare($conn, 'DELETE FROM jenis_reward WHERE id = ?');

            foreach ($preview_list as $row) {
                $d = $row['data'];
                if ($row['action'] === 'INSERT') {
                    if (!empty($d['id']) && empty($row['is_fatal'])) {
                        mysqli_stmt_bind_param($stmt_ins, 'isis', $d['id'], $d['nama_reward'], $d['poin_reward'], $d['deskripsi']);
                        mysqli_stmt_execute($stmt_ins);
                    } else {
                        mysqli_stmt_bind_param($stmt_ins_ni, 'sis', $d['nama_reward'], $d['poin_reward'], $d['deskripsi']);
                        mysqli_stmt_execute($stmt_ins_ni);
                    }
                    $insert_count++;
                } elseif ($row['action'] === 'UPDATE') {
                    mysqli_stmt_bind_param($stmt_upd, 'sisi', $d['nama_reward'], $d['poin_reward'], $d['deskripsi'], $d['id']);
                    mysqli_stmt_execute($stmt_upd);
                    $update_count++;
                } elseif ($row['action'] === 'DELETE') {
                    mysqli_stmt_bind_param($stmt_del, 'i', $d['id']);
                    mysqli_stmt_execute($stmt_del);
                    $delete_count++;
                }
            }

            write_activity_log('SYNC', 'jenis_reward',
                "Sinkronisasi massal Jenis Reward: {$insert_count} INSERT, {$update_count} UPDATE, {$delete_count} DELETE.",
                ['insert' => $insert_count, 'update' => $update_count, 'delete' => $delete_count]
            );
        }

        mysqli_commit($conn);

        $type_label = match ($type) {
            'jenis_pelanggaran' => 'Jenis Pelanggaran',
            'jenis_reward'      => 'Jenis Reward',
            default             => 'Santri',
        };
        $_SESSION['sync_success_msg'] = "Sinkronisasi {$type_label} berhasil! "
            . "{$insert_count} ditambahkan, {$update_count} diperbarui, {$delete_count} dihapus.";

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['sync_error_msg'] = 'Gagal melakukan sinkronisasi: ' . $e->getMessage();
    } finally {
        if (isset($_SESSION['sync_temp_file']) && file_exists($_SESSION['sync_temp_file'])) {
            @unlink($_SESSION['sync_temp_file']);
        }
        unset($_SESSION['sync_preview_data'], $_SESSION['sync_temp_file'],
              $_SESSION['sync_type'], $_SESSION['sync_mode']);
    }

    header('Location: index.php');
    exit;
}

// ════════════════════════════════════════════════════════════════════════════
// ACTION: CANCEL
// ════════════════════════════════════════════════════════════════════════════
if ($action === 'cancel') {
    if (isset($_SESSION['sync_temp_file']) && file_exists($_SESSION['sync_temp_file'])) {
        @unlink($_SESSION['sync_temp_file']);
    }
    unset($_SESSION['sync_preview_data'], $_SESSION['sync_temp_file'],
          $_SESSION['sync_type'], $_SESSION['sync_mode']);
}

header('Location: index.php');
exit;
