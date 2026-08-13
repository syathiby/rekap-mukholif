<?php
require_once __DIR__ . '/../../bootstrap/init.php';

header('Content-Type: application/json');

// Strict Whitelist Proteksi
if (!isset($_SESSION['role']) || !in_array($_SESSION['role'], ['admin', 'pengelola'])) {
    echo json_encode(['status' => 'error', 'message' => 'Akses ditolak. Anda bukan Pengelola.']);
    exit;
}

$action = $_POST['action'] ?? '';

if ($action === 'get_aktivitas') {
    $q = "SELECT l.*, u.nama_lengkap as nama FROM log_aktifitas l JOIN users u ON l.user_id = u.id WHERE u.role = 'musyrif' ORDER BY l.id DESC LIMIT 50";
    // Jika tabel tidak ada, try-catch agar tidak fatal (karena mungkin struktur DB beda)
    try {
        $res = mysqli_query($conn, $q);
        $data = [];
        if ($res) {
            while ($row = mysqli_fetch_assoc($res)) {
                $data[] = [
                    'waktu' => date('d M H:i', strtotime($row['dibuat_pada'] ?? 'now')),
                    'nama' => $row['nama'] ?? 'Sistem',
                    'aksi' => $row['aksi'],
                    'fitur' => $row['fitur'],
                    'deskripsi' => $row['deskripsi']
                ];
            }
        }
        echo json_encode(['status' => 'success', 'data' => $data]);
    } catch (Exception $e) {
        echo json_encode(['status' => 'success', 'data' => []]); // Fallback empty
    }
    exit;
}

if ($action === 'get_stats') {
    // Total musyrif aktif
    $q_musyrif = mysqli_query($conn, "SELECT COUNT(*) as total FROM users WHERE role = 'musyrif' AND is_active = 1");
    $total_musyrif = mysqli_fetch_assoc($q_musyrif)['total'] ?? 0;
    
    // Aktivitas hari ini
    $today = date('Y-m-d');
    $q_aktivitas = mysqli_query($conn, "SELECT COUNT(*) as total FROM log_aktifitas l JOIN users u ON l.user_id = u.id WHERE DATE(l.dibuat_pada) = '$today' AND u.role = 'musyrif'");
    $total_aktivitas = mysqli_fetch_assoc($q_aktivitas)['total'] ?? 0;
    
    // Pengumuman aktif
    $q_pengumuman = mysqli_query($conn, "SELECT COUNT(*) as total FROM pengumuman_sistem WHERE status_aktif = 1");
    $total_pengumuman = mysqli_fetch_assoc($q_pengumuman)['total'] ?? 0;

    // Musyrif Belum Rapot (Tahun Ajaran)
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    $currentDay = (int)date('j');
    $daysInMonth = (int)date('t'); // Jumlah hari di bulan ini (28-31)
    $startYear = ($currentMonth >= 7) ? $currentYear : ($currentYear - 1);
    
    // Bulan target: jika belum masuk 7 hari sebelum berganti bulan, bulan ini belum wajib
    $deadlineDay = $daysInMonth - 7;
    $maxMonth = $currentMonth;
    $maxYear = $currentYear;
    if ($currentDay <= $deadlineDay) {
        $maxMonth--;
        if ($maxMonth < 1) {
            $maxMonth = 12;
            $maxYear--;
        }
    }
    
    $expected = [];
    $y = $startYear; $m = 7;
    while ($y < $maxYear || ($y == $maxYear && $m <= $maxMonth)) {
        $expected[] = ['month' => $m, 'year' => $y];
        $m++; if ($m > 12) { $m = 1; $y++; }
    }
    
    // Ambil data submission
    $total_belum_rapot = 0;
    $res_m = mysqli_query($conn, "
        SELECT u.id, COUNT(s.id) as total_santri 
        FROM users u 
        LEFT JOIN santri s ON u.kamar_id = s.kamar 
        WHERE u.role = 'musyrif' AND u.is_active = 1 
        GROUP BY u.id
    ");
    if ($res_m && mysqli_num_rows($res_m) > 0) {
        $m_ids = [];
        $m_santri_count = [];
        while($r = mysqli_fetch_assoc($res_m)) {
            $m_ids[] = $r['id'];
            $m_santri_count[$r['id']] = $r['total_santri'];
        }
        $ids_str = implode(',', $m_ids);
        
        $nama_bulan_id = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        
        $conds = [];
        foreach($expected as $exp) {
            $nama_bln = $nama_bulan_id[$exp['month']];
            $conds[] = "(bulan = '$nama_bln' AND tahun = {$exp['year']})";
        }
        $cond_str = count($conds) > 0 ? implode(" OR ", $conds) : "1=0"; // Prevent SQL error if expected is empty
        
        $q_r = "SELECT musyrif_id, bulan, tahun, COUNT(*) as total_rapot FROM rapot_kepengasuhan WHERE musyrif_id IN ($ids_str) AND ($cond_str) GROUP BY musyrif_id, bulan, tahun";
        $res_r = mysqli_query($conn, $q_r);
        $submitted = [];
        if ($res_r) {
            while($r = mysqli_fetch_assoc($res_r)) {
                $submitted[$r['musyrif_id']][$r['bulan'].'-'.$r['tahun']] = $r['total_rapot'];
            }
        }
        
        foreach($m_ids as $id) {
            $has_missing = false;
            $t_santri = $m_santri_count[$id];
            
            if ($t_santri > 0) {
                foreach($expected as $exp) {
                    $nama_bln = $nama_bulan_id[$exp['month']];
                    $key = $nama_bln.'-'.$exp['year'];
                    $rapot_count = isset($submitted[$id][$key]) ? $submitted[$id][$key] : 0;
                    
                    if($rapot_count < $t_santri) {
                        $has_missing = true;
                        break;
                    }
                }
            }
            if ($has_missing) $total_belum_rapot++;
        }
    }
    
    // Hitung total Rapot Janggal
    $total_rapot_janggal = 0;
    if (!empty($m_ids)) {
        $nama_bulan_db = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        $janggal_conds = [];
        if ($currentDay <= $deadlineDay) {
            $janggal_conds[] = "(bulan = '{$nama_bulan_db[$currentMonth]}' AND tahun = $currentYear)";
        }
        $ck_m = $currentMonth + 1; $ck_y = $currentYear;
        for ($i = 0; $i < 12; $i++) {
            if ($ck_m > 12) { $ck_m = 1; $ck_y++; }
            $janggal_conds[] = "(bulan = '{$nama_bulan_db[$ck_m]}' AND tahun = $ck_y)";
            $ck_m++;
        }
        
        $j_cond = implode(' OR ', $janggal_conds);
        $q_j = "SELECT COUNT(DISTINCT musyrif_id) as total_janggal FROM rapot_kepengasuhan WHERE musyrif_id IN ($ids_str) AND ($j_cond)";
        $res_j = mysqli_query($conn, $q_j);
        if ($res_j) {
            $total_rapot_janggal = mysqli_fetch_assoc($res_j)['total_janggal'] ?? 0;
        }
    }

    echo json_encode([
        'status' => 'success', 
        'data' => [
            'musyrif' => $total_musyrif,
            'aktivitas' => $total_aktivitas,
            'pengumuman' => $total_pengumuman,
            'belum_rapot' => $total_belum_rapot,
            'rapot_janggal' => $total_rapot_janggal
        ]
    ]);
    exit;
}

if ($action === 'get_kinerja') {
    $currentMonth = (int)date('n');
    $currentYear = (int)date('Y');
    $currentDay = (int)date('j');
    $daysInMonth = (int)date('t');
    $startYear = ($currentMonth >= 7) ? $currentYear : ($currentYear - 1);
    
    // Bulan target: jika belum masuk 7 hari sebelum berganti bulan, bulan ini belum wajib
    $deadlineDay = $daysInMonth - 7;
    $maxMonth = $currentMonth;
    $maxYear = $currentYear;
    if ($currentDay <= $deadlineDay) {
        $maxMonth--;
        if ($maxMonth < 1) {
            $maxMonth = 12;
            $maxYear--;
        }
    }
    
    $expected = [];
    $y = $startYear; $m = 7;
    while ($y < $maxYear || ($y == $maxYear && $m <= $maxMonth)) {
        $expected[] = ['month' => $m, 'year' => $y];
        $m++; if ($m > 12) { $m = 1; $y++; }
    }
    
    $res_m = mysqli_query($conn, "
        SELECT u.id, u.username, u.nama_lengkap, u.role, u.is_active, COUNT(s.id) as total_santri 
        FROM users u 
        LEFT JOIN santri s ON u.kamar_id = s.kamar 
        WHERE u.role = 'musyrif' AND u.is_active = 1 
        GROUP BY u.id 
        ORDER BY u.nama_lengkap ASC
    ");
    $data = [];
    if ($res_m && mysqli_num_rows($res_m) > 0) {
        $all_musyrif = [];
        $m_ids = [];
        while($r = mysqli_fetch_assoc($res_m)) {
            $all_musyrif[$r['id']] = $r;
            $m_ids[] = $r['id'];
        }
        $ids_str = implode(',', $m_ids);
        
        $nama_bulan_id = [1=>'Jan',2=>'Feb',3=>'Mar',4=>'Apr',5=>'Mei',6=>'Jun',7=>'Juli',8=>'Agustus',9=>'Sep',10=>'Okt',11=>'Nov',12=>'Des'];
        $nama_bulan_db = [1=>'Januari',2=>'Februari',3=>'Maret',4=>'April',5=>'Mei',6=>'Juni',7=>'Juli',8=>'Agustus',9=>'September',10=>'Oktober',11=>'November',12=>'Desember'];
        
        $conds = [];
        foreach($expected as $exp) {
            $nama_bln = $nama_bulan_db[$exp['month']];
            $conds[] = "(bulan = '$nama_bln' AND tahun = {$exp['year']})";
        }
        $cond_str = count($conds) > 0 ? implode(" OR ", $conds) : "1=0"; // Prevent SQL error
        
        $q_r = "SELECT musyrif_id, bulan, tahun, COUNT(*) as total_rapot FROM rapot_kepengasuhan WHERE musyrif_id IN ($ids_str) AND ($cond_str) GROUP BY musyrif_id, bulan, tahun";
        $res_r = mysqli_query($conn, $q_r);
        $submitted = [];
        if ($res_r) {
            while($r = mysqli_fetch_assoc($res_r)) {
                $submitted[$r['musyrif_id']][$r['bulan'].'-'.$r['tahun']] = $r['total_rapot'];
            }
        }
        
        // Cek peringatan 24 jam terakhir (yang masih aktif)
        $recent_warnings = [];
        $q_warnings = "SELECT target_user_id FROM pengumuman_sistem WHERE judul = 'Peringatan Rapot!' AND status_aktif = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $res_w = mysqli_query($conn, $q_warnings);
        if ($res_w) {
            while($r = mysqli_fetch_assoc($res_w)) {
                $recent_warnings[$r['target_user_id']] = true;
            }
        }
        
        foreach($all_musyrif as $id => $m_data) {
            $missing = [];
            $t_santri = $m_data['total_santri'];
            
            if ($t_santri > 0) {
                foreach($expected as $exp) {
                    $nama_bln_db = $nama_bulan_db[$exp['month']];
                    $key = $nama_bln_db.'-'.$exp['year'];
                    $rapot_count = isset($submitted[$id][$key]) ? $submitted[$id][$key] : 0;
                    
                    if($rapot_count < $t_santri) {
                        // Tambahkan indikator sisa rapot
                        $sisa = $t_santri - $rapot_count;
                        $missing[] = $nama_bulan_id[$exp['month']] . ' ' . $exp['year'] . ' (sisa ' . $sisa . ' santri)';
                    }
                }
            }
            if (count($missing) > 0) {
                $m_data['tertunggak'] = $missing;
                $m_data['has_recent_warning'] = isset($recent_warnings[$id]);
                $data[] = $m_data;
            }
        }
    }
    
    // ── DETEKSI RAPOT JANGGAL ──────────────────────────────────────────────────
    // Rapot dianggap janggal jika dibuat untuk bulan yang belum memasuki
    // periode pengisian (7 hari terakhir bulan tersebut).
    // Bangun daftar bulan-tahun yang BELUM boleh diisi (future/premature)
    // Sebuah bulan BOLEH diisi jika hari ini >= (hari_terakhir_bulan_itu - 6)
    // Kita cek semua rapot pada periode tahun ajaran ini dan selanjutnya
    $janggal_conds = [];
    
    // Cek bulan yang sama dengan sekarang tapi sebelum deadline
    if ($currentDay <= $deadlineDay) {
        $bln_indo_now = $nama_bulan_db[$currentMonth];
        $janggal_conds[] = "(bulan = '$bln_indo_now' AND tahun = $currentYear)";
    }
    
    // Cek semua bulan setelah bulan sekarang (masa depan)
    $ck_m = $currentMonth + 1; $ck_y = $currentYear;
    for ($i = 0; $i < 12; $i++) {
        if ($ck_m > 12) { $ck_m = 1; $ck_y++; }
        $janggal_conds[] = "(bulan = '{$nama_bulan_db[$ck_m]}' AND tahun = $ck_y)";
        $ck_m++;
    }
    
    $janggal_data = [];
    if (!empty($janggal_conds) && !empty($m_ids)) {
        $j_cond = implode(' OR ', $janggal_conds);
        
        // Cek recent warnings janggal per musyrif
        $q_jwarn = "SELECT target_user_id FROM pengumuman_sistem WHERE judul = 'Peringatan Rapot Janggal!' AND status_aktif = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR) AND target_user_id IN ($ids_str)";
        $res_jwarn = mysqli_query($conn, $q_jwarn);
        $recent_janggal_warnings = [];
        if ($res_jwarn) {
            while ($rjw = mysqli_fetch_assoc($res_jwarn)) {
                $recent_janggal_warnings[$rjw['target_user_id']] = true;
            }
        }
        
        $q_j = "
            SELECT r.musyrif_id, r.bulan, r.tahun, COUNT(*) as total_rapot,
                   u.nama_lengkap, u.username, u.is_active
            FROM rapot_kepengasuhan r
            JOIN users u ON r.musyrif_id = u.id
            WHERE r.musyrif_id IN ($ids_str) AND ($j_cond)
            GROUP BY r.musyrif_id, r.bulan, r.tahun
            ORDER BY u.nama_lengkap
        ";
        $res_j = mysqli_query($conn, $q_j);
        if ($res_j) {
            $grouped_janggal = [];
            while ($rj = mysqli_fetch_assoc($res_j)) {
                $mid = $rj['musyrif_id'];
                if (!isset($grouped_janggal[$mid])) {
                    $grouped_janggal[$mid] = [
                        'id'                    => $mid,
                        'nama_lengkap'          => $rj['nama_lengkap'],
                        'username'              => $rj['username'],
                        'is_active'             => $rj['is_active'],
                        'has_recent_warning'    => isset($recent_janggal_warnings[$mid]),
                        'bulan_janggal'         => []
                    ];
                }
                $grouped_janggal[$mid]['bulan_janggal'][] = $rj['bulan'] . ' ' . $rj['tahun'] . ' (' . $rj['total_rapot'] . ' rapot)';
            }
            $janggal_data = array_values($grouped_janggal);
        }
    }
    
    echo json_encode(['status' => 'success', 'data' => $data, 'janggal' => $janggal_data]);
    exit;
}

if ($action === 'get_musyrif') {
    $q = "SELECT id, username, nama_lengkap, role, is_active FROM users WHERE role = 'musyrif' ORDER BY nama_lengkap ASC";
    $res = mysqli_query($conn, $q);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $data[] = $row;
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'toggle_status') {
    $id = (int)($_POST['id'] ?? 0);
    $status = (int)($_POST['status'] ?? 0);
    
    // Jangan izinkan suspend diri sendiri
    if ($id === (int)$_SESSION['user_id']) {
        echo json_encode(['status' => 'error', 'message' => 'Anda tidak bisa men-suspend diri sendiri!']);
        exit;
    }
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET is_active = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "ii", $status, $id);
    if (mysqli_stmt_execute($stmt)) {
        write_activity_log('MANAGE_USER', 'pengelola', "Mengubah status aktif user ID $id menjadi $status");
        echo json_encode(['status' => 'success', 'message' => 'Status akun berhasil diperbarui.']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mengubah status.']);
    }
    exit;
}

if ($action === 'buat_peringatan') {
    $target_id = (int)($_POST['target_id'] ?? 0);
    $pesan = mysqli_real_escape_string($conn, $_POST['pesan'] ?? '');
    $tipe = $_POST['tipe'] ?? 'rapot'; // 'rapot' atau 'janggal'
    
    if ($target_id && $pesan) {
        $user_id = $_SESSION['user_id'];
        $judul = ($tipe === 'janggal') ? 'Peringatan Rapot Janggal!' : 'Peringatan Rapot!';
        
        // Cek apakah sudah ada peringatan aktif dalam 24 jam terakhir (per judul)
        $judul_esc = mysqli_real_escape_string($conn, $judul);
        $check_q = "SELECT id FROM pengumuman_sistem WHERE target_user_id = '$target_id' AND judul = '$judul_esc' AND status_aktif = 1 AND created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)";
        $check_res = mysqli_query($conn, $check_q);
        if ($check_res && mysqli_num_rows($check_res) > 0) {
            echo json_encode(['status' => 'error', 'message' => 'Peringatan sudah dikirimkan dalam 24 jam terakhir. Silakan tunggu sebelum mengirim peringatan lagi.']);
            exit;
        }
        
        $q = "INSERT INTO pengumuman_sistem (target_user_id, judul, pesan, status_aktif, created_by) VALUES ('$target_id', '$judul_esc', '$pesan', 1, '$user_id')";
        if (mysqli_query($conn, $q)) {
            echo json_encode(['status' => 'success']);
        } else {
            echo json_encode(['status' => 'error', 'message' => mysqli_error($conn)]);
        }
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Data tidak lengkap.']);
    }
    exit;
}

if ($action === 'reset_password') {
    $id = (int)($_POST['id'] ?? 0);
    $new_pass = '123456';
    $hash = password_hash($new_pass, PASSWORD_DEFAULT);
    
    $stmt = mysqli_prepare($conn, "UPDATE users SET password = ? WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "si", $hash, $id);
    if (mysqli_stmt_execute($stmt)) {
        write_activity_log('MANAGE_USER', 'pengelola', "Mereset password user ID $id");
        echo json_encode(['status' => 'success', 'message' => 'Password direset ke: 123456']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal mereset password.']);
    }
    exit;
}

if ($action === 'get_broadcast') {
    $q = "
        SELECT p.*, u.nama_lengkap as target_nama, 
               (p.created_at >= DATE_SUB(NOW(), INTERVAL 24 HOUR)) as is_within_24h
        FROM pengumuman_sistem p
        LEFT JOIN users u ON p.target_user_id = u.id
        ORDER BY p.id DESC LIMIT 20
    ";
    $res = mysqli_query($conn, $q);
    $data = [];
    while ($row = mysqli_fetch_assoc($res)) {
        $status = $row['status_aktif'];
        if ($status == 1 && $row['is_within_24h'] == 0) {
            $status = 2; // 2 = Expired
        }
        
        $target = $row['target_user_id'] ? $row['target_nama'] : 'Semua Musyrif';
        
        $data[] = [
            'id' => $row['id'],
            'tanggal' => date('d M Y H:i', strtotime($row['created_at'])),
            'judul' => htmlspecialchars($row['judul'] ?? 'Pengumuman Sistem!'),
            'pesan' => htmlspecialchars($row['pesan']),
            'target' => htmlspecialchars($target),
            'status_aktif' => $status
        ];
    }
    echo json_encode(['status' => 'success', 'data' => $data]);
    exit;
}

if ($action === 'buat_pengumuman' || $action === 'buat_broadcast') {
    $judul = trim($_POST['judul'] ?? 'Pengumuman Sistem!');
    $pesan = trim($_POST['pesan'] ?? '');
    $target_users_str = trim($_POST['target_users'] ?? '');
    
    if (empty($pesan)) {
        echo json_encode(['status' => 'error', 'message' => 'Pesan tidak boleh kosong.']);
        exit;
    }
    
    $user_id = (int)$_SESSION['user_id'];
    
    if (empty($target_users_str)) {
        // Semua Musyrif
        $stmt = mysqli_prepare($conn, "INSERT INTO pengumuman_sistem (judul, pesan, created_by) VALUES (?, ?, ?)");
        mysqli_stmt_bind_param($stmt, "ssi", $judul, $pesan, $user_id);
        if (!mysqli_stmt_execute($stmt)) {
            echo json_encode(['status' => 'error', 'message' => 'Gagal menyimpan pengumuman.']);
            exit;
        }
    } else {
        // Spesifik Musyrif
        $targets = explode(',', $target_users_str);
        $stmt = mysqli_prepare($conn, "INSERT INTO pengumuman_sistem (target_user_id, judul, pesan, created_by) VALUES (?, ?, ?, ?)");
        foreach($targets as $tid) {
            $tid = (int)$tid;
            if ($tid > 0) {
                mysqli_stmt_bind_param($stmt, "issi", $tid, $judul, $pesan, $user_id);
                mysqli_stmt_execute($stmt);
            }
        }
    }
    
    write_activity_log('BROADCAST', 'pengelola', "Membuat pengumuman sistem baru");
    echo json_encode(['status' => 'success']);
    exit;
}

if ($action === 'tutup_broadcast') {
    $id = (int)($_POST['id'] ?? 0);
    $stmt = mysqli_prepare($conn, "UPDATE pengumuman_sistem SET status_aktif = 0 WHERE id = ?");
    mysqli_stmt_bind_param($stmt, "i", $id);
    if (mysqli_stmt_execute($stmt)) {
        write_activity_log('BROADCAST', 'pengelola', "Menutup pengumuman ID $id");
        echo json_encode(['status' => 'success']);
    } else {
        echo json_encode(['status' => 'error', 'message' => 'Gagal menutup pengumuman.']);
    }
    exit;
}

echo json_encode(['status' => 'error', 'message' => 'Action tidak dikenali.']);
