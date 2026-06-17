<?php
/**
 * admin/pengaturan/impor_data/smart_reader.php
 * AI-Like Smart Data Reader for Excel/CSV Import
 *
 * Mendukung tipe data:
 *  - santri            (nama, kelas, kamar)
 *  - jenis_pelanggaran (nama_pelanggaran, bagian, poin, kategori)
 *  - jenis_reward      (nama_reward, poin_reward, deskripsi)
 */

// ─────────────────────────────────────────────────────────────────────────────
// UTILITAS HEADER
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Membersihkan dan menstandarisasi nama header kolom Excel.
 * Mengubah menjadi huruf kecil, spasi menjadi underscore, dan menghapus
 * karakter khusus agar mudah dicocokkan secara konsisten.
 */
function standardize_headers(array $headers): array {
    return array_map(function ($h) {
        return strtolower(trim(preg_replace('/[^a-zA-Z0-9_]/', '', str_replace(' ', '_', (string)$h))));
    }, $headers);
}

// ─────────────────────────────────────────────────────────────────────────────
// PENCARIAN NILAI CERDAS (Smart Mapping)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mencari nilai dalam satu baris data berdasarkan kecocokan alias kunci secara
 * cerdas. Mendukung exact match dan partial match.
 *
 * @param array $row  Data satu baris (kolom => nilai)
 * @param array $keys Kumpulan kemungkinan nama kolom / kata kunci yang dicari
 * @return mixed|null Nilai jika ditemukan dan tidak kosong, null jika tidak ada
 */
function get_smart_value(array $row, array $keys) {
    // 1. Exact match (case-insensitive)
    foreach ($keys as $k) {
        foreach ($row as $row_key => $row_val) {
            if (strtolower(trim((string)$row_key)) === strtolower(trim((string)$k))
                && $row_val !== '' && $row_val !== null) {
                return $row_val;
            }
        }
    }
    // 2. Partial match — skip 'id' agar tidak salah cocok ke 'id_kamar', dll.
    foreach ($keys as $k) {
        if ($k === 'id') continue;
        foreach ($row as $row_key => $row_val) {
            if (strpos((string)$row_key, $k) !== false
                && $row_val !== '' && $row_val !== null) {
                return $row_val;
            }
        }
    }
    return null;
}

/**
 * Memeriksa apakah salah satu kata kunci terdeteksi dalam array header Excel.
 */
function has_smart_column(array $headers, array $keys): bool {
    foreach ($keys as $k) {
        foreach ($headers as $h) {
            if (strtolower(trim((string)$h)) === strtolower(trim((string)$k))) {
                return true;
            }
        }
    }
    foreach ($keys as $k) {
        if ($k === 'id') continue;
        foreach ($headers as $h) {
            if (strpos(strtolower(trim((string)$h)), strtolower(trim((string)$k))) !== false) {
                return true;
            }
        }
    }
    return false;
}

// ─────────────────────────────────────────────────────────────────────────────
// DETEKSI KESAMAAN NAMA
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Membandingkan dua nama secara cerdas: nama mirip = UPDATE sah, nama berbeda = FATAL.
 *
 * @return bool True = mirip/sama (aman UPDATE), False = beda total (FATAL)
 */
function is_similar_name(string $name1, string $name2): bool {
    $n1 = strtolower(trim($name1));
    $n2 = strtolower(trim($name2));

    if ($n1 === $n2) return true;

    $n1 = preg_replace('/[^a-z0-9 ]/', '', $n1);
    $n2 = preg_replace('/[^a-z0-9 ]/', '', $n2);
    $n1 = preg_replace('/\s+/', ' ', $n1);
    $n2 = preg_replace('/\s+/', ' ', $n2);

    if ($n1 === $n2) return true;

    $words1 = array_values(array_filter(explode(' ', $n1)));
    $words2 = array_values(array_filter(explode(' ', $n2)));

    if (empty($words1) || empty($words2)) return false;

    $shorter = count($words1) <= count($words2) ? $words1 : $words2;
    $longer  = count($words1) <= count($words2) ? $words2 : $words1;

    $all_match = true;
    foreach ($shorter as $sw) {
        if (strlen($sw) <= 1) continue;
        $found = false;
        foreach ($longer as $lw) {
            if ($lw === $sw || strpos($lw, $sw) === 0 || strpos($sw, $lw) === 0) {
                $found = true;
                break;
            }
        }
        if (!$found) {
            $all_match = false;
            break;
        }
    }
    if ($all_match) return true;

    $max_len = max(strlen($n1), strlen($n2));
    if ($max_len === 0) return false;

    $dist = levenshtein($n1, $n2);
    
    // BUG FIX 2: Perketat logika kemiripan
    if ($max_len <= 4) {
        // Nama sangat pendek (<= 4 huruf) wajib sama persis
        return $dist === 0;
    }
    
    // Toleransi typo 1 huruf atau max 15% beda untuk nama panjang
    return ($dist <= 1 || ($dist / $max_len) < 0.15);
}



// ─────────────────────────────────────────────────────────────────────────────
// NORMALISASI — JENIS PELANGGARAN
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalisasi nilai kolom "Bagian" jenis pelanggaran.
 * Memetakan berbagai variasi ke: Bahasa / Diniyyah / Kesantrian / Pengabdian / Tahfidz
 * Mengembalikan null jika tidak dikenali.
 */
function normalize_bagian(?string $value): ?string {
    if ($value === null || trim($value) === '') return null;
    $v = strtolower(trim($value));

    $map = [
        'Bahasa'     => ['bahasa', 'lang', 'language', 'bhs'],
        'Diniyyah'   => ['diniyyah', 'dini', 'agama', 'religion', 'din'],
        'Kesantrian' => ['kesantrian', 'santri', 'asrama', 'dorm', 'kes'],
        'Pengabdian' => ['pengabdian', 'abdi', 'pengabdi', 'service', 'peng'],
        'Tahfidz'    => ['tahfidz', 'tahfiz', 'hafidz', 'hafiz', 'quran', 'hafalan', 'tah'],
    ];

    foreach ($map as $resmi => $aliases) {
        foreach ($aliases as $alias) {
            if (strpos($v, $alias) !== false) {
                return $resmi;
            }
        }
    }
    return null;
}

/**
 * Normalisasi nilai kolom "Kategori" jenis pelanggaran.
 * Memetakan berbagai variasi ke: Ringan / Sedang / Berat / Sangat Berat
 * Mengembalikan null jika tidak dikenali.
 */
function normalize_kategori_pelanggaran(?string $value): ?string {
    if ($value === null || trim($value) === '') return null;
    $v = strtolower(trim($value));

    if (strpos($v, 'sangat') !== false && strpos($v, 'berat') !== false) return 'Sangat Berat';
    if (strpos($v, 'ringan') !== false || strpos($v, 'minor') !== false
        || strpos($v, 'kecil') !== false || $v === 'r') {
        return 'Ringan';
    }
    if (strpos($v, 'sedang') !== false || strpos($v, 'medium') !== false
        || strpos($v, 'menengah') !== false || $v === 's') {
        return 'Sedang';
    }
    if (strpos($v, 'berat') !== false || strpos($v, 'major') !== false
        || strpos($v, 'serius') !== false || strpos($v, 'besar') !== false || $v === 'b') {
        return 'Berat';
    }
    return null;
}

// ─────────────────────────────────────────────────────────────────────────────
// NORMALISASI — JENIS REWARD
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Normalisasi nilai poin reward — pastikan angka positif.
 */
function normalize_poin_reward($value): float {
    if ($value === null || $value === '') return 0.0;
    $v = (float)str_replace([',', ' '], ['.', ''], (string)$value);
    return max(0.0, $v);
}

// ─────────────────────────────────────────────────────────────────────────────
// HELPER: Bangun row_data dari header + row dengan aman
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Menggabungkan header dan row menjadi associative array secara aman.
 * Menangani kasus di mana row lebih panjang atau lebih pendek dari header.
 */
function build_row_data(array $header, array $raw_row): array {
    $row_data = [];
    foreach ($header as $col_idx => $col_name) {
        $row_data[$col_name] = $raw_row[$col_idx] ?? '';
    }
    return $row_data;
}

// ─────────────────────────────────────────────────────────────────────────────
// DETEKSI HEADER DAN FILTER BARIS (SMART ENGINE)
// ─────────────────────────────────────────────────────────────────────────────

/**
 * Mencari baris header yang valid berdasarkan tipe data.
 * Membutuhkan setidaknya 2 kolom terisi dan kolom-kolom spesifik untuk tipe terkait.
 *
 * @return array Bentuk: ['header' => [...], 'index' => int]
 */
function extract_smart_header(array $original_rows, string $type): array {
    $header = [];
    $header_index = -1;
    
    foreach ($original_rows as $idx => $r) {
        $test_header = standardize_headers($r);
        $filled_cols = count(array_filter($test_header, fn($h) => trim((string)$h) !== ''));
        
        // Baris header tabel asli pasti memiliki setidaknya 2 kolom terpisah
        if ($filled_cols >= 2) {
            $is_header = false;
            if ($type === 'santri') {
                $is_header = has_smart_column($test_header, ['nama', 'nama_santri']) && has_smart_column($test_header, ['kelas', 'kls', 'kamar']);
            } elseif ($type === 'jenis_pelanggaran') {
                $is_header = has_smart_column($test_header, ['nama', 'nama_pelanggaran', 'pelanggaran']) && has_smart_column($test_header, ['poin', 'kategori']);
            } elseif ($type === 'jenis_reward') {
                $is_header = has_smart_column($test_header, ['nama', 'nama_reward', 'reward']) && has_smart_column($test_header, ['poin']);
            }

            if ($is_header) {
                $header = $test_header;
                $header_index = $idx;
                break;
            }
        }
    }
    
    return ['header' => $header, 'index' => $header_index];
}

/**
 * Menyaring baris data mentah untuk membuang baris kosong atau baris
 * keterangan/footer sistem (yang biasanya hanya punya 1 kolom berisi teks panjang).
 */
function filter_smart_data_rows(array $rows_raw): array {
    $rows = [];
    foreach ($rows_raw as $row) {
        $filled_cells = array_filter($row, function($c) { return trim((string)$c) !== ''; });
        if (empty($filled_cells)) continue; // Skip baris kosong sepenuhnya
        
        // Jika hanya 1 kolom yang terisi, cek apakah ini teks panjang/keterangan
        if (count($filled_cells) === 1) {
            $text = strtolower(trim((string)reset($filled_cells)));
            if (strlen($text) > 80 || preg_match('/(dokumen|rahasia|dicetak|halaman|asuh|sistem|mengetahui|periode|laporan|keterangan|ringkasan|total)/i', $text)) {
                continue; // Skip baris keterangan
            }
        }
        $rows[] = $row;
    }
    return $rows;
}

