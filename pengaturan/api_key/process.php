<?php
// pengaturan/api_key/process.php
// Logika: save API key & test koneksi ke Gemini

require_once __DIR__ . '/../../bootstrap/init.php';
guard('setup_api_ai');

$action = $_GET['action'] ?? $_POST['action'] ?? '';

// ============================================================
// ACTION: TEST KONEKSI
// ============================================================
if ($action === 'test') {
    header('Content-Type: application/json');

    // Ambil key dari DB
    try {
        $stmt = $conn->prepare("SELECT nilai FROM pengaturan WHERE nama = 'gemini_api_key' LIMIT 1");
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        $stmt->close();
    } catch (Exception $e) {
        echo json_encode(['success' => false, 'message' => 'Gagal membaca database: ' . $e->getMessage()]);
        exit;
    }

    if (empty($row['nilai'])) {
        echo json_encode(['success' => false, 'message' => 'API Key belum diisi. Silakan simpan key terlebih dahulu.']);
        exit;
    }

    $api_key  = $row['nilai'];
    $model    = 'gemini-2.5-flash';
    $endpoint = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$api_key}";

    // Kirim request minimal ke Gemini
    $payload = json_encode([
        'contents' => [
            ['role' => 'user', 'parts' => [['text' => 'Balas dengan kata "OK" saja.']]]
        ],
        'generationConfig' => ['maxOutputTokens' => 10]
    ]);

    $ch = curl_init($endpoint);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_POST           => true,
        CURLOPT_POSTFIELDS     => $payload,
        CURLOPT_HTTPHEADER     => ['Content-Type: application/json'],
        CURLOPT_TIMEOUT        => 15,
    ]);
    $response    = curl_exec($ch);
    $http_code   = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $curl_error  = curl_error($ch);
    curl_close($ch);

    if ($curl_error) {
        echo json_encode(['success' => false, 'message' => 'Gagal terhubung ke Gemini: ' . $curl_error]);
        exit;
    }

    $data = json_decode($response, true);

    if ($http_code === 200 && isset($data['candidates'])) {
        echo json_encode(['success' => true, 'message' => 'Koneksi berhasil! API Key valid dan model Gemini merespons dengan normal.']);
    } elseif ($http_code === 400) {
        echo json_encode(['success' => false, 'message' => 'API Key tidak valid atau format request salah (HTTP 400). Periksa kembali key Anda.']);
    } elseif ($http_code === 403) {
        echo json_encode(['success' => false, 'message' => 'Akses ditolak (HTTP 403). API Key mungkin tidak punya izin ke model ini.']);
    } elseif ($http_code === 429) {
        echo json_encode(['success' => false, 'message' => 'Rate limit tercapai (HTTP 429). Key valid, tapi kuota habis sementara.']);
    } else {
        $err_msg = $data['error']['message'] ?? 'Unknown error';
        echo json_encode(['success' => false, 'message' => "Gemini merespons dengan HTTP {$http_code}: {$err_msg}"]);
    }
    exit;
}

// ============================================================
// ACTION: SAVE API KEY
// ============================================================
if ($action === 'save' && $_SERVER['REQUEST_METHOD'] === 'POST') {
    $api_key = trim($_POST['api_key'] ?? '');

    if (empty($api_key)) {
        set_flash_message('API Key tidak boleh kosong.', 'danger');
        header('Location: index.php');
        exit;
    }

    // Validasi prefix dihapus karena ada kemungkinan format key baru tidak menggunakan AIza

    try {
        $stmt_check = $conn->prepare("SELECT id FROM pengaturan WHERE nama = 'gemini_api_key'");
        $stmt_check->execute();
        $exists = $stmt_check->get_result()->num_rows > 0;
        $stmt_check->close();

        if ($exists) {
            $stmt = $conn->prepare("UPDATE pengaturan SET nilai = ? WHERE nama = 'gemini_api_key'");
            $stmt->bind_param("s", $api_key);
        } else {
            $stmt = $conn->prepare("
                INSERT INTO pengaturan (nama, nilai)
                VALUES ('gemini_api_key', ?)
            ");
            $stmt->bind_param("s", $api_key);
        }
        $stmt->execute();
        $stmt->close();

        // Catat log aktivitas
        write_activity_log('UPDATE', 'pengaturan', 'Memperbarui Gemini API Key', [
            'kunci'   => 'gemini_api_key',
            'user_id' => $_SESSION['user_id'] ?? null,
        ]);

        set_flash_message('API Key berhasil disimpan. Gunakan tombol "Test Koneksi" untuk memverifikasi.', 'success');
    } catch (Exception $e) {
        error_log('[AsuhTrack] Gagal simpan API key: ' . $e->getMessage());
        set_flash_message('Gagal menyimpan API Key: ' . $e->getMessage(), 'danger');
    }

    header('Location: index.php');
    exit;
}

// Default: redirect jika action tidak dikenal
header('Location: index.php');
exit;
