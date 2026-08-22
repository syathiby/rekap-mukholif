<?php
require_once __DIR__ . '/../../bootstrap/init.php'; // Load DB & Auth

// --- Validasi CSRF terlebih dahulu sebelum memproses aksi apapun ---
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    csrf_validate();
}

// --- 1. PROSES TAMBAH BARU ---
if (isset($_POST['add_jenis'])) {
    guard('jenis_reward_create');
    
    $nama = trim($_POST['nama_reward'] ?? '');
    $poin = (int) ($_POST['poin_reward'] ?? 0);
    $desc = trim($_POST['deskripsi'] ?? '');

    if (empty($nama) || $poin <= 0) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Nama reward dan poin wajib diisi dan poin harus lebih dari 0.'];
        header("Location: index.php");
        exit;
    }

    $query = "INSERT INTO jenis_reward (nama_reward, poin_reward, deskripsi) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);
    mysqli_stmt_bind_param($stmt, "sis", $nama, $poin, $desc);
    
    if (mysqli_stmt_execute($stmt)) {
        write_activity_log('CREATE', 'jenis_reward', "Menambahkan jenis reward baru: '$nama' (Poin: $poin)", [
            'sesudah' => ['nama_reward' => $nama, 'poin_reward' => $poin, 'deskripsi' => $desc]
        ]);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Reward baru berhasil ditambahkan!'];
    } else {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal: ' . mysqli_error($conn)];
    }
    mysqli_stmt_close($stmt);
    header("Location: index.php");
    exit;
}

// --- 2. PROSES EDIT SATUAN ---
if (isset($_POST['edit_jenis'])) {
    guard('jenis_reward_edit');

    $id   = (int) $_POST['id'];
    $nama = trim($_POST['nama_reward']);
    $poin = (int) $_POST['poin_reward'];
    $desc = trim($_POST['deskripsi'] ?? '');

    mysqli_begin_transaction($conn);

    try {
        // 1. Ambil poin reward lama
        $stmt_old = mysqli_prepare($conn, "SELECT poin_reward, nama_reward, deskripsi FROM jenis_reward WHERE id = ?");
        mysqli_stmt_bind_param($stmt_old, "i", $id);
        mysqli_stmt_execute($stmt_old);
        $old_data = mysqli_fetch_assoc(mysqli_stmt_get_result($stmt_old));
        mysqli_stmt_close($stmt_old);

        if (!$old_data) {
            throw new Exception("Jenis reward tidak ditemukan.");
        }
        $poin_lama = (int)$old_data['poin_reward'];

        // 2. Update master data jenis_reward
        $query = "UPDATE jenis_reward SET nama_reward=?, poin_reward=?, deskripsi=? WHERE id=?";
        $stmt = mysqli_prepare($conn, $query);
        mysqli_stmt_bind_param($stmt, "sisi", $nama, $poin, $desc, $id);
        if (!mysqli_stmt_execute($stmt)) {
            throw new Exception(mysqli_stmt_error($stmt));
        }
        mysqli_stmt_close($stmt);

        // 3. Sinkronisasi poin santri jika nilai reward berubah
        if ($poin !== $poin_lama) {
            $selisih = $poin - $poin_lama;
            // Reward bersifat mengurangi poin pelanggaran santri:
            // Jika reward bertambah (+selisih), maka poin_aktif santri berkurang (-selisih).
            $stmt_sync = mysqli_prepare($conn, "
                UPDATE santri s
                JOIN (
                    SELECT santri_id, COUNT(*) AS jumlah_reward
                    FROM daftar_reward
                    WHERE jenis_reward_id = ?
                    GROUP BY santri_id
                ) r ON s.id = r.santri_id
                SET s.poin_aktif = s.poin_aktif - (? * r.jumlah_reward)
            ");
            mysqli_stmt_bind_param($stmt_sync, "ii", $id, $selisih);
            if (!mysqli_stmt_execute($stmt_sync)) {
                throw new Exception(mysqli_stmt_error($stmt_sync));
            }
            mysqli_stmt_close($stmt_sync);
        }

        mysqli_commit($conn);

        write_activity_log('UPDATE', 'jenis_reward', "Mengubah jenis reward: '$nama' (Poin: $poin, Sebelum: $poin_lama)", [
            'id' => $id,
            'sebelum' => $old_data,
            'sesudah' => ['nama_reward' => $nama, 'poin_reward' => $poin, 'deskripsi' => $desc]
        ]);
        $_SESSION['message'] = ['type' => 'success', 'text' => 'Data reward berhasil diperbarui dan saldo poin santri telah disinkronkan.'];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal memperbarui reward: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit;
}

// --- 3. PROSES BULK EDIT (Edit Rame-rame) ---
if (isset($_POST['bulk_update'])) {
    guard('jenis_reward_edit');

    $ids   = $_POST['ids'] ?? [];
    $namas = $_POST['nama_reward'] ?? [];
    $poins = $_POST['poin_reward'] ?? [];
    $descs = $_POST['deskripsi'] ?? [];
    
    if (empty($ids)) {
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Tidak ada data untuk diproses.'];
        header("Location: index.php");
        exit;
    }

    mysqli_begin_transaction($conn);

    try {
        $success_count = 0;
        $stmt_get_old = mysqli_prepare($conn, "SELECT poin_reward FROM jenis_reward WHERE id = ?");
        $stmt_update  = mysqli_prepare($conn, "UPDATE jenis_reward SET nama_reward=?, poin_reward=?, deskripsi=? WHERE id=?");
        $stmt_sync    = mysqli_prepare($conn, "
            UPDATE santri s
            JOIN (
                SELECT santri_id, COUNT(*) AS jumlah_reward
                FROM daftar_reward
                WHERE jenis_reward_id = ?
                GROUP BY santri_id
            ) r ON s.id = r.santri_id
            SET s.poin_aktif = s.poin_aktif - (? * r.jumlah_reward)
        ");

        foreach ($ids as $id) {
            $id_int = (int) $id;
            $nama   = trim($namas[$id_int] ?? '');
            $poin   = (int) ($poins[$id_int] ?? 0);
            $desc   = trim($descs[$id_int] ?? '');

            if (empty($nama)) continue;

            // Ambil poin lama
            mysqli_stmt_bind_param($stmt_get_old, "i", $id_int);
            mysqli_stmt_execute($stmt_get_old);
            $res_old = mysqli_stmt_get_result($stmt_get_old);
            $row_old = mysqli_fetch_assoc($res_old);
            $poin_lama = $row_old ? (int)$row_old['poin_reward'] : $poin;

            // Update master reward
            mysqli_stmt_bind_param($stmt_update, "sisi", $nama, $poin, $desc, $id_int);
            if (!mysqli_stmt_execute($stmt_update)) {
                throw new Exception(mysqli_stmt_error($stmt_update));
            }

            // Sinkronkan poin jika ada selisih
            if ($poin !== $poin_lama) {
                $selisih = $poin - $poin_lama;
                mysqli_stmt_bind_param($stmt_sync, "ii", $id_int, $selisih);
                if (!mysqli_stmt_execute($stmt_sync)) {
                    throw new Exception(mysqli_stmt_error($stmt_sync));
                }
            }

            $success_count++;
        }

        mysqli_stmt_close($stmt_get_old);
        mysqli_stmt_close($stmt_update);
        mysqli_stmt_close($stmt_sync);

        mysqli_commit($conn);

        if ($success_count > 0) {
            write_activity_log('UPDATE', 'jenis_reward', "Bulk update $success_count jenis reward sekaligus dan sinkronisasi saldo poin");
        }
        $_SESSION['message'] = ['type' => 'success', 'text' => "$success_count data reward berhasil diperbarui dan saldo poin santri disinkronkan."];

    } catch (Exception $e) {
        mysqli_rollback($conn);
        $_SESSION['message'] = ['type' => 'danger', 'text' => 'Gagal bulk update reward: ' . $e->getMessage()];
    }

    header("Location: index.php");
    exit;
}

// --- 4. PROSES TAMBAH BANYAK (Bulk Insert) ---
if (isset($_POST['add_bulk'])) {
    guard('jenis_reward_create');

    $lines = explode("\n", trim($_POST['bulk_input']));
    $success_count = 0;
    $errors = [];

    $query = "INSERT INTO jenis_reward (nama_reward, poin_reward, deskripsi) VALUES (?, ?, ?)";
    $stmt = mysqli_prepare($conn, $query);

    foreach ($lines as $line) {
        $line = trim($line);
        if (empty($line)) continue;

        // Pisahkan dengan koma (,)
        $parts = array_map('trim', explode(',', $line));
        
        $nama = $parts[0] ?? '';
        $poin = (int) ($parts[1] ?? 0);
        $desc = $parts[2] ?? '';

        if (empty($nama) || $poin <= 0) {
            $errors[] = "Baris tidak valid: '$line'";
            continue;
        }

        mysqli_stmt_bind_param($stmt, "sis", $nama, $poin, $desc);
        if (mysqli_stmt_execute($stmt)) {
            $success_count++;
        } else {
            $errors[] = "Gagal menyimpan: '$nama' - " . mysqli_error($conn);
        }
    }
    mysqli_stmt_close($stmt);

    if (!empty($errors)) {
        $_SESSION['message'] = [
            'type' => 'warning',
            'text' => "$success_count berhasil ditambahkan. Ada error: " . implode('<br>', array_slice($errors, 0, 3))
        ];
    } else {
        write_activity_log('CREATE', 'jenis_reward', "Bulk tambah $success_count jenis reward sekaligus");
        $_SESSION['message'] = [
            'type' => 'success',
            'text' => "Berhasil menambahkan $success_count reward sekaligus!"
        ];
    }

    header("Location: index.php");
    exit;
}
?>