<?php
// config.php
// Veritabanı bağlantı bilgileri ve temel ayarlar.

// Veritabanı Ayarları
define('DB_HOST', 'sql211.infinityfree.com');
define('DB_USERNAME', 'if0_40197167');
define('DB_PASSWORD', 'Aeg151851');
define('DB_NAME', 'if0_40197167_test');

// PDO ile Veritabanı Bağlantısı
try {
    $pdo = new PDO("mysql:host=" . DB_HOST . ";dbname=" . DB_NAME . ";charset=utf8mb4", DB_USERNAME, DB_PASSWORD);
    // Hata modunu ayarla
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
    // Sonuçları obje olarak döndür
    $pdo->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    // Bağlantı hatası durumunda işlemi sonlandır ve hata mesajı göster
    header('Content-Type: application/json');
    http_response_code(500);
    echo json_encode(['error' => 'Veritabanı bağlantısı kurulamadı. Lütfen yapılandırmayı kontrol edin.']);
    exit;
}

// Oturum (Session) Yönetimi
if (session_status() == PHP_SESSION_NONE) {
    session_start();
}

// Temel Yardımcı Fonksiyonlar (İleride eklenebilir)
// Örneğin: Yetkilendirme kontrolü
function isAdmin() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'admin';
}

function isTeacher() {
    return isset($_SESSION['user_role']) && $_SESSION['user_role'] === 'teacher';
}
?>
