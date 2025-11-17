<?php
session_start();

// Konfigurasi keamanan
define('PDF_DIR', __DIR__ . '/pdf_files/');
define('DATA_DIR', __DIR__ . '/secure_data/');
define('MAX_ATTEMPTS', 5);
define('LOCKOUT_TIME', 300); // 5 menit dalam detik

// Fungsi untuk memuat data sekolah (tanpa kode verifikasi)
function loadSchoolData() {
    // Coba data_sekolah_public.json dulu, fallback ke data_sekolah.json
    $json_file = __DIR__ . '/data_sekolah_public.json';
    if (!file_exists($json_file)) {
        $json_file = __DIR__ . '/data_sekolah.json';
    }
    if (!file_exists($json_file)) {
        return [];
    }
    
    $json_data = file_get_contents($json_file);
    if ($json_data === false) {
        return [];
    }
    
    $data = json_decode($json_data, true);
    if (json_last_error() !== JSON_ERROR_NONE) {
        return [];
    }
    
    // Hapus kode verifikasi dari data yang ditampilkan
    foreach ($data as &$item) {
        unset($item['verification_codes']);
    }
    
    return $data;
}

// Fungsi untuk memuat kode verifikasi dari file terpisah (di luar web root)
function loadVerificationCodes() {
    $code_file = DATA_DIR . 'verification_codes.json';
    if (!file_exists($code_file)) {
        return [];
    }
    
    $json_data = file_get_contents($code_file);
    if ($json_data === false) {
        return [];
    }
    
    $codes = json_decode($json_data, true);
    return $codes ?: [];
}

// Fungsi untuk memverifikasi kode
function verifyCode($pdf_file, $input_code) {
    // Validasi input
    if (empty($input_code) || !preg_match('/^\d{4}$/', $input_code)) {
        return false;
    }
    
    // Validasi nama file untuk mencegah path traversal
    $pdf_file = basename($pdf_file);
    if (empty($pdf_file) || !preg_match('/^[a-zA-Z0-9_\-\.]+\.pdf$/', $pdf_file)) {
        return false;
    }
    
    // Cek rate limiting
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    $attempts_key = "attempts_$ip";
    
    if (!isset($_SESSION[$attempts_key])) {
        $_SESSION[$attempts_key] = ['count' => 0, 'lockout_until' => 0];
    }
    
    $attempts = $_SESSION[$attempts_key];
    
    // Cek apakah masih dalam lockout
    if ($attempts['lockout_until'] > time()) {
        $remaining = $attempts['lockout_until'] - time();
        return ['error' => 'Terlalu banyak percobaan. Silakan coba lagi dalam ' . ceil($remaining / 60) . ' menit.'];
    }
    
    // Reset counter jika lockout sudah berakhir
    if ($attempts['lockout_until'] > 0 && $attempts['lockout_until'] <= time()) {
        $_SESSION[$attempts_key] = ['count' => 0, 'lockout_until' => 0];
    }
    
    // Muat kode verifikasi
    $codes = loadVerificationCodes();
    
    // Cari kode yang sesuai
    $verified = false;
    foreach ($codes as $entry) {
        if ($entry['pdf_file'] === $pdf_file && in_array($input_code, $entry['verification_codes'])) {
            $verified = true;
            break;
        }
    }
    
    if ($verified) {
        // Reset attempts pada sukses
        $_SESSION[$attempts_key] = ['count' => 0, 'lockout_until' => 0];
        return true;
    } else {
        // Increment attempts
        $attempts['count']++;
        $_SESSION[$attempts_key]['count'] = $attempts['count'];
        
        if ($attempts['count'] >= MAX_ATTEMPTS) {
            $_SESSION[$attempts_key]['lockout_until'] = time() + LOCKOUT_TIME;
            return ['error' => 'Terlalu banyak percobaan gagal. Akun terkunci selama 5 menit.'];
        }
        
        return ['error' => 'Kode verifikasi salah. Sisa percobaan: ' . (MAX_ATTEMPTS - $attempts['count'])];
    }
}

// Handle AJAX request untuk verifikasi dan download
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'verify') {
    header('Content-Type: application/json');
    
    $pdf_file = $_POST['pdf_file'] ?? '';
    $code = $_POST['code'] ?? '';
    
    $result = verifyCode($pdf_file, $code);
    
    if ($result === true) {
        // Validasi path file
        $pdf_file = basename($pdf_file);
        $file_path = PDF_DIR . $pdf_file;
        
        // Pastikan file ada dan dalam direktori yang benar
        if (!file_exists($file_path) || !is_file($file_path)) {
            echo json_encode(['success' => false, 'error' => 'File tidak ditemukan.']);
            exit;
        }
        
        // Pastikan file benar-benar dalam PDF_DIR (mencegah path traversal)
        $real_path = realpath($file_path);
        $real_dir = realpath(PDF_DIR);
        
        if (strpos($real_path, $real_dir) !== 0) {
            echo json_encode(['success' => false, 'error' => 'Akses tidak diizinkan.']);
            exit;
        }
        
        // Generate token untuk download (valid 5 menit)
        $token = bin2hex(random_bytes(16));
        $_SESSION['download_token_' . $token] = [
            'file' => $pdf_file,
            'expires' => time() + 300
        ];
        
        echo json_encode([
            'success' => true,
            'token' => $token,
            'message' => 'Verifikasi berhasil.'
        ]);
    } else {
        echo json_encode([
            'success' => false,
            'error' => $result['error'] ?? 'Kode verifikasi salah.'
        ]);
    }
    exit;
}

// Handle download dengan token
if (isset($_GET['download']) && isset($_GET['token'])) {
    $token = $_GET['token'];
    $token_key = 'download_token_' . $token;
    
    if (!isset($_SESSION[$token_key])) {
        die('Token tidak valid atau sudah kedaluwarsa.');
    }
    
    $token_data = $_SESSION[$token_key];
    
    // Cek expiry
    if ($token_data['expires'] < time()) {
        unset($_SESSION[$token_key]);
        die('Token sudah kedaluwarsa.');
    }
    
    $pdf_file = basename($token_data['file']);
    $file_path = PDF_DIR . $pdf_file;
    
    // Validasi path lagi
    if (!file_exists($file_path) || !is_file($file_path)) {
        die('File tidak ditemukan.');
    }
    
    $real_path = realpath($file_path);
    $real_dir = realpath(PDF_DIR);
    
    if (strpos($real_path, $real_dir) !== 0) {
        die('Akses tidak diizinkan.');
    }
    
    // Hapus token setelah digunakan
    unset($_SESSION[$token_key]);
    
    // Set headers untuk download
    header('Content-Type: application/pdf');
    header('Content-Disposition: attachment; filename="' . basename($pdf_file) . '"');
    header('Content-Length: ' . filesize($file_path));
    header('Cache-Control: no-cache, must-revalidate');
    header('Pragma: no-cache');
    
    // Output file
    readfile($file_path);
    exit;
}

// Load data untuk tampilan
$data = loadSchoolData();
$grouped_data = [];

foreach ($data as $item) {
    $school_name = $item['sekolah'];
    if (!isset($grouped_data[$school_name])) {
        $grouped_data[$school_name] = [
            'pdf_file' => $item['pdf_file'],
            'pendamping_list' => []
        ];
    }
    
    foreach ($item['pendamping'] as $pendamping_nama) {
        $grouped_data[$school_name]['pendamping_list'][] = [
            'name' => $pendamping_nama
        ];
    }
}

ksort($grouped_data);
?>
<!DOCTYPE html>
<html lang="id">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Daftar Peserta Bebras Challenge 2025</title>
    <link rel="stylesheet" href="style.css">
</head>
<body>

<h1>Daftar Peserta Bebras Challenge 2025</h1>

<div class="school-list">
    <?php
    foreach ($grouped_data as $school_name => $school_info) {
        $sekolah_nama = htmlspecialchars($school_name, ENT_QUOTES, 'UTF-8');
        $pdf_file = htmlspecialchars($school_info['pdf_file'], ENT_QUOTES, 'UTF-8');
        $pendamping_list = $school_info['pendamping_list'];

        echo "<div class='school-item'>";
        echo "<h3>$sekolah_nama</h3>";
        echo "<ul class='pendamping-list'>";

        foreach ($pendamping_list as $pendamping) {
            $pendamping_nama = htmlspecialchars($pendamping['name'], ENT_QUOTES, 'UTF-8');

            echo "<li>";
            echo "<strong>Pendamping:</strong> $pendamping_nama ";
            echo "<button class='download-btn' onclick='openModal(\"" . addslashes($pdf_file) . "\", \"" . addslashes($pendamping_nama) . "\")'>Download PDF</button>";
            echo "</li>";
        }

        echo "</ul>";
        echo "</div>";
    }
    ?>
</div>

<!-- The Modal -->
<div id="myModal" class="modal">
    <div class="modal-content">
        <span class="close" onclick="closeModal()">&times;</span>
        <h3>Verifikasi Unduhan</h3>
        <p id="modal-pendamping-name"></p>
        <p>Masukkan 4 digit terakhir nomor telepon pendamping untuk mengunduh PDF.</p>
        <form id="verificationForm">
            <input type="hidden" id="pdfFileInput" name="pdf_file">
            <label for="verificationCode">Kode Verifikasi (4 Digit):</label>
            <input type="text" id="verificationCode" name="code_input" placeholder="XXXX" maxlength="4" pattern="[0-9]{4}" required>
            <div id="errorMessage" class="error-message"></div>
            <div id="successMessage" class="success-message"></div>
            <button type="submit">Verifikasi dan Unduh</button>
        </form>
    </div>
</div>

<script>
    const modal = document.getElementById("myModal");

    function openModal(pdfFile, pendampingName) {
        document.getElementById("pdfFileInput").value = pdfFile;
        document.getElementById("modal-pendamping-name").textContent = "Pendamping: " + pendampingName;
        document.getElementById("verificationCode").value = "";
        document.getElementById("errorMessage").textContent = "";
        document.getElementById("successMessage").textContent = "";
        modal.style.display = "block";
    }

    function closeModal() {
        modal.style.display = "none";
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            closeModal();
        }
    }

    // Server-side verification
    document.getElementById("verificationForm").addEventListener("submit", function(event) {
        event.preventDefault();

        const input_code = document.getElementById("verificationCode").value.trim();
        const errorMessageDiv = document.getElementById("errorMessage");
        const successMessageDiv = document.getElementById("successMessage");
        const pdfFileToDownload = document.getElementById("pdfFileInput").value;

        // Clear previous messages
        errorMessageDiv.textContent = "";
        successMessageDiv.textContent = "";

        // Client-side validation
        if (input_code.length !== 4 || !/^\d{4}$/.test(input_code)) {
            errorMessageDiv.textContent = "Kode harus berupa 4 digit angka.";
            return;
        }

        // Disable button during request
        const submitBtn = event.target.querySelector('button[type="submit"]');
        const originalText = submitBtn.textContent;
        submitBtn.disabled = true;
        submitBtn.textContent = "Memverifikasi...";

        // Send verification request to server
        const formData = new FormData();
        formData.append('action', 'verify');
        formData.append('pdf_file', pdfFileToDownload);
        formData.append('code', input_code);

        fetch('', {
            method: 'POST',
            body: formData
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                successMessageDiv.textContent = data.message;
                // Download file using token
                const downloadUrl = `?download=1&token=${data.token}`;
                window.location.href = downloadUrl;
                // Close modal after a short delay
                setTimeout(() => {
                    closeModal();
                }, 1000);
            } else {
                errorMessageDiv.textContent = data.error || 'Kode verifikasi salah.';
            }
        })
        .catch(error => {
            console.error('Error:', error);
            errorMessageDiv.textContent = 'Terjadi kesalahan. Silakan coba lagi.';
        })
        .finally(() => {
            submitBtn.disabled = false;
            submitBtn.textContent = originalText;
        });
    });
</script>

</body>
</html>

