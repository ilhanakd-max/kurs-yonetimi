<?php
// GÜNCELLEME: HTTP başlığını en başta ayarlayarak tarayıcının doğru karakter setini kullanmasını garanti altına al.
// Bu satır, herhangi bir HTML veya PHP çıktısından önce olmalıdır.
header('Content-Type: text/html; charset=utf-8');

// config.php - Veritabanı bağlantı ayarları
// NOT: Bu dosya veritabanı bilgilerinizi içerdiği için dikkatli saklanmalıdır.
$db_host = 'sql211.infinityfree.com';
$db_name = 'if0_40197167_cesmebld';
$db_user = 'if0_40197167';
$db_pass = 'Aeg151851';

try {
    // GÜNCELLEME: Veritabanı bağlantısı modernize edildi.
    // DSN (Data Source Name) içinde charset=utf8mb4 belirtmek, karakter setini ayarlamak için en doğru yöntemdir.
    // Ekstra ->exec("SET NAMES ...") komutları kaldırıldı çünkü bu DSN parametresi aynı işi yapar ve çift kodlama riskini ortadan kaldırır.
    $dsn = "mysql:host=$db_host;dbname=$db_name;charset=utf8mb4";
    $options = [
        PDO::ATTR_ERRMODE            => PDO::ERRMODE_EXCEPTION,
        PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
        PDO::ATTR_EMULATE_PREPARES   => false,
    ];
    $pdo = new PDO($dsn, $db_user, $db_pass, $options);
    
    // Saat dilimini ayarla
    $pdo->exec("SET time_zone = '+03:00'");

    // UYGULAMA AYARLARI TABLOSUNU OLUŞTUR (VARSA ATLA)
    try {
        $pdo->exec("CREATE TABLE IF NOT EXISTS app_settings (
            setting_key VARCHAR(100) PRIMARY KEY,
            setting_value TEXT,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP
        ) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci");
    } catch(PDOException $e) {
        error_log('Ayar tablosu oluşturulamadı: ' . $e->getMessage());
    }

} catch(PDOException $e) {
    // GÜVENLİK GÜNCELLEMESİ: Ayrıntılı hata mesajlarını kullanıcıya gösterme.
    // Bunları bir log dosyasına yazmak daha güvenlidir.
    error_log("Veritabanı bağlantı hatası: " . $e->getMessage());
    die("Veritabanı bağlantısında bir sorun oluştu. Lütfen daha sonra tekrar deneyin.");
}

// Oturum başlat - güvenli oturum ayarları
ini_set('session.use_strict_mode', '1');
$session_secure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
    || (isset($_SERVER['SERVER_PORT']) && (int)$_SERVER['SERVER_PORT'] === 443);
session_set_cookie_params([
    'lifetime' => 0,
    'path' => '/',
    'domain' => '',
    'secure' => $session_secure,
    'httponly' => true,
    'samesite' => 'Strict',
]);
session_start();

// YENİ: Dinamik durum ve ödeme bilgilerini veritabanından çek
// Bu değişkenler uygulamanın her yerinde kullanılacak
global $all_event_statuses, $all_payment_statuses;
try {
    $all_event_statuses = [];
    foreach ($pdo->query("SELECT * FROM event_statuses") as $row) {
        $all_event_statuses[$row['status_key']] = $row;
    }

    $all_payment_statuses = [];
    foreach ($pdo->query("SELECT * FROM payment_statuses") as $row) {
        $all_payment_statuses[$row['status_key']] = $row;
    }
} catch (PDOException $e) {
    // Tablolar henüz oluşturulmadıysa hata verebilir.
    // Bu durumu daha zarif yönetmek için geçici boş diziler atayabiliriz.
    if (strpos($e->getMessage(), 'Table') !== false && strpos($e->getMessage(), 'doesn\'t exist') !== false) {
        error_log("Uyarı: 'event_statuses' veya 'payment_statuses' tabloları bulunamadı. Lütfen SQL komutlarını çalıştırın. - " . $e->getMessage());
        $all_event_statuses = [
            'confirmed' => ['display_name' => 'Onaylı (HATA)', 'color' => '#dc3545'],
            'option' => ['display_name' => 'Opsiyon (HATA)', 'color' => '#dc3545'],
            'cancelled' => ['display_name' => 'İptal (HATA)', 'color' => '#dc3545'],
            'free' => ['display_name' => 'Ücretsiz (HATA)', 'color' => '#dc3545']
        ];
        $all_payment_statuses = [
            'paid' => ['display_name' => 'Ödendi (HATA)', 'color' => '#dc3545'],
            'not_paid' => ['display_name' => 'Ödenmedi (HATA)', 'color' => '#dc3545'],
            'to_be_paid' => ['display_name' => 'Ödeme Bekleniyor (HATA)', 'color' => '#dc3545']
        ];
    } else {
        error_log("Durum bilgileri çekilirken hata: " . $e->getMessage());
        die("Durum bilgileri yüklenirken bir sorun oluştu.");
    }
}

$maintenance_mode_enabled = get_app_setting('maintenance_mode', '0') === '1';

// Son etkinlik güncellemeleri ayarları
$updates_enabled = get_app_setting('updates_enabled', '0') === '1';
$updates_max_count = max(1, (int) get_app_setting('updates_max_count', '10'));
$updates_duration = max(1, (int) get_app_setting('updates_duration', '24'));
$updates_duration_unit = get_app_setting('updates_duration_unit', 'hours');
if (!in_array($updates_duration_unit, ['hours', 'days'], true)) {
    $updates_duration_unit = 'hours';
}


// DÜZELTME: Karakter seti sorunlarını önlemek için mb_internal_encoding ayarı
// PHP'nin dize fonksiyonlarının UTF-8'i doğru işlemesini sağlar.
if (function_exists('mb_internal_encoding')) {
    mb_internal_encoding("UTF-8");
}
if (function_exists('mb_http_output')) {
    mb_http_output("UTF-8");
}

// DÜZELTME: Türkçe tarih fonksiyonu ve dizisi, kullanılmadan ÖNCE tanımlanmalıdır.
// Bu blok dosyanın sonundan buraya taşındı.

// Türkçe ay isimleri
$turkish_months = [
    1 => 'Ocak', 2 => 'Şubat', 3 => 'Mart', 4 => 'Nisan', 5 => 'Mayıs', 6 => 'Haziran',
    7 => 'Temmuz', 8 => 'Ağustos', 9 => 'Eylül', 10 => 'Ekim', 11 => 'Kasım', 12 => 'Aralık'
];

// Türkçe gün isimleri
$turkish_days = [
    'Sunday'    => 'Pazar',
    'Monday'    => 'Pazartesi',
    'Tuesday'   => 'Salı',
    'Wednesday' => 'Çarşamba',
    'Thursday'  => 'Perşembe',
    'Friday'    => 'Cuma',
    'Saturday'  => 'Cumartesi'
];

// Türkçe tarih fonksiyonu
function turkish_date($format, $timestamp = null) {
    global $turkish_months, $turkish_days;
    if ($timestamp === null) $timestamp = time();
    if ($timestamp === false) $timestamp = time(); // Düzeltme: strtotime(null) 'false' dönerse
    
    $date = date($format, $timestamp);
    
    // Ay isimlerini çevir
    $month_num = date('n', $timestamp);
    if (isset($turkish_months[$month_num])) {
        $date = str_replace(date('F', $timestamp), $turkish_months[$month_num], $date);
        $date = str_replace(date('M', $timestamp), mb_substr($turkish_months[$month_num], 0, 3, 'UTF-8'), $date);
    }

    // Gün isimlerini çevir
    $day_en = date('l', $timestamp); // Get the full English day name (e.g., "Monday")
    if (isset($turkish_days[$day_en])) {
        $date = str_replace($day_en, $turkish_days[$day_en], $date);
    }
    
    return $date;
}

// CSRF token oluştur veya kontrol et
function generateCSRFToken() {
    if (!isset($_SESSION['csrf_token'])) {
        $_SESSION['csrf_token'] = bin2hex(random_bytes(32)); // Güçlü rastgele token
    }
    return $_SESSION['csrf_token'];
}

function validateCSRFToken($token) {
    return isset($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token);
}

// Basit güvenlik fonksiyonları
function clean_input($data) {
    // inputları temizlerken, veritabanına kaydedilecek Türkçe karakterlerin bozulmaması için
    // sadece trim ve strip_tags kullanılıp htmlspecialchars kaldırıldı.
    // HTML çıktısı alınırken (echo) htmlspecialchars kullanılmalıdır.
    $sanitized = trim(strip_tags((string) $data));
    // Kontrol karakterlerini kaldır
    return preg_replace('/[\x00-\x1F\x7F]/u', '', $sanitized);
}

// Uygulama ayarları fonksiyonları
function get_app_setting($key, $default = null) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("SELECT setting_value FROM app_settings WHERE setting_key = ? LIMIT 1");
        $stmt->execute([$key]);
        $value = $stmt->fetchColumn();
        if ($value === false) {
            return $default;
        }
        return $value;
    } catch(PDOException $e) {
        error_log("Ayar okuma hatası ({$key}): " . $e->getMessage());
        return $default;
    }
}

function set_app_setting($key, $value) {
    global $pdo;

    try {
        $stmt = $pdo->prepare("INSERT INTO app_settings (setting_key, setting_value) VALUES (?, ?) ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)");
        $stmt->execute([$key, $value]);
        return true;
    } catch(PDOException $e) {
        error_log("Ayar kaydetme hatası ({$key}): " . $e->getMessage());
        return false;
    }
}

// Son etkinlik güncellemelerini tabloya kaydet
function record_event_update($event_id, $update_type, $event_data, $duration, $duration_unit, $updates_enabled) {
    global $pdo;

    if (!$updates_enabled) {
        return;
    }

    $duration_seconds = max(1, (int) $duration) * ($duration_unit === 'days' ? 86400 : 3600);
    $expire_at = date('Y-m-d H:i:s', time() + $duration_seconds);

    $unit_name = $event_data['unit_name'] ?? '';
    if ($unit_name === '' && !empty($event_data['unit_id'])) {
        try {
            $stmt = $pdo->prepare("SELECT unit_name FROM units WHERE id = ? LIMIT 1");
            $stmt->execute([(int) $event_data['unit_id']]);
            $unit_name = (string) $stmt->fetchColumn();
        } catch (PDOException $e) {
            error_log('Birim bilgisi alınamadı: ' . $e->getMessage());
        }
    }

    $event_title = $event_data['event_name'] ?? 'Etkinlik';
    $event_date_text = '';
    if (!empty($event_data['event_date'])) {
        $timestamp = strtotime($event_data['event_date']);
        if ($timestamp !== false) {
            $event_date_text = turkish_date('d M Y', $timestamp);
        }
    }

    $meta_parts = [];
    if ($event_date_text !== '') {
        $meta_parts[] = $event_date_text;
    }
    if ($unit_name !== '') {
        $meta_parts[] = $unit_name;
    }
    $meta_text = empty($meta_parts) ? '' : ' (' . implode(' · ', $meta_parts) . ')';

    $message_text = trim($event_title . $meta_text);

    try {
        $stmt = $pdo->prepare("INSERT INTO event_updates (event_id, update_type, message_text, expire_at) VALUES (?, ?, ?, ?)");
        $stmt->execute([(int) $event_id, $update_type, $message_text, $expire_at]);
    } catch (PDOException $e) {
        error_log('Etkinlik güncellemesi kaydedilemedi: ' . $e->getMessage());
    }
}

function is_valid_date_string($date) {
    if (!is_string($date) || $date === '') {
        return false;
    }

    $dt = DateTime::createFromFormat('Y-m-d', $date);
    return $dt && $dt->format('Y-m-d') === $date;
}

// Admin giriş kontrolü
function is_admin() {
    return isset($_SESSION['admin']) && $_SESSION['admin'] === true;
}

// Basit admin giriş kontrolü
function check_admin_login($username, $password, $pdo) {
    try {
        $sql = "SELECT * FROM admin_users WHERE username = ? AND is_active = TRUE";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$username]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);
        if ($user) {
            // Hash'li şifreyi kontrol et
            if (password_verify($password, $user['password'])) {
                return $user;
            }
        }
        return false;
    } catch(PDOException $e) {
        error_log("Admin giriş hatası: " . $e->getMessage());
        return false;
    }
}

// Tatil kontrolü
function is_holiday($date, $pdo) {
    static $holiday_cache = [];

    if (isset($holiday_cache[$date])) {
        return $holiday_cache[$date];
    }

    $sql = "SELECT * FROM holidays WHERE holiday_date = ?";
    $stmt = $pdo->prepare($sql);
    $stmt->execute([$date]);
    $holiday = $stmt->fetch(PDO::FETCH_ASSOC);

    if ($holiday) {
        $holiday_cache[$date] = $holiday;
        return $holiday;
    }

    $recurring_name = get_recurring_holiday_for_date($date);
    if ($recurring_name !== null) {
        $holiday_cache[$date] = [
            'holiday_date' => $date,
            'holiday_name' => $recurring_name,
            'id' => null,
            'is_recurring' => true,
        ];
        return $holiday_cache[$date];
    }

    $holiday_cache[$date] = false;
    return false;
}

function get_recurring_holiday_definitions() {
    return [
        '01-01' => 'Yılbaşı',
        '04-23' => 'Ulusal Egemenlik ve Çocuk Bayramı',
        '05-01' => 'Emek ve Dayanışma Günü',
        '05-19' => 'Atatürk\'ü Anma, Gençlik ve Spor Bayramı',
        '07-15' => 'Demokrasi ve Milli Birlik Günü',
        '08-30' => 'Zafer Bayramı',
        '10-29' => 'Cumhuriyet Bayramı',
    ];
}

function get_variable_islamic_holidays_for_year($year) {
    static $cache = [];

    if (isset($cache[$year])) {
        return $cache[$year];
    }

    if (!class_exists('IntlCalendar')) {
        $cache[$year] = [];
        return $cache[$year];
    }

    try {
        $timezone = new DateTimeZone('Europe/Istanbul');
        $calendar = IntlCalendar::createInstance($timezone, 'tr_TR@calendar=islamic-umalqura');

        if (!$calendar) {
            $calendar = IntlCalendar::createInstance($timezone, 'tr_TR@calendar=islamic');
        }

        if (!$calendar) {
            $cache[$year] = [];
            return $cache[$year];
        }

        $start = new DateTimeImmutable(sprintf('%04d-01-01 00:00:00', (int) $year), $timezone);
        $end = $start->modify('+1 year');
        $current = $start;

        $ramazan_names = [
            1 => 'Ramazan Bayramı 1. Gün',
            2 => 'Ramazan Bayramı 2. Gün',
            3 => 'Ramazan Bayramı 3. Gün',
        ];

        $kurban_names = [
            10 => 'Kurban Bayramı 1. Gün',
            11 => 'Kurban Bayramı 2. Gün',
            12 => 'Kurban Bayramı 3. Gün',
            13 => 'Kurban Bayramı 4. Gün',
        ];

        $holidays = [];

        while ($current < $end) {
            $timestamp = (int) $current->format('U');
            $calendar->setTime($timestamp * 1000);

            $month = $calendar->get(IntlCalendar::FIELD_MONTH);
            $day = $calendar->get(IntlCalendar::FIELD_DAY_OF_MONTH);

            if ($month === 9 && isset($ramazan_names[$day])) {
                $holidays[$current->format('Y-m-d')] = $ramazan_names[$day];
            } elseif ($month === 11 && isset($kurban_names[$day])) {
                $holidays[$current->format('Y-m-d')] = $kurban_names[$day];
            }

            $current = $current->modify('+1 day');
        }

        ksort($holidays);
        $cache[$year] = $holidays;
    } catch (Exception $e) {
        error_log('Dinamik dini tatiller hesaplanırken hata oluştu: ' . $e->getMessage());
        $cache[$year] = [];
    }

    return $cache[$year];
}

function get_recurring_holidays_for_year($year) {
    $definitions = get_recurring_holiday_definitions();
    $holidays = [];

    foreach ($definitions as $month_day => $name) {
        $holidays[sprintf('%04d-%s', (int) $year, $month_day)] = $name;
    }

    $variable_holidays = get_variable_islamic_holidays_for_year((int) $year);

    foreach ($variable_holidays as $date => $name) {
        $holidays[$date] = $name;
    }

    ksort($holidays);

    return $holidays;
}

function get_recurring_holiday_for_date($date) {
    $timestamp = strtotime($date);
    if ($timestamp === false) {
        return null;
    }

    $month_day = date('m-d', $timestamp);
    $definitions = get_recurring_holiday_definitions();

    if (isset($definitions[$month_day])) {
        return $definitions[$month_day];
    }

    $year = (int) date('Y', $timestamp);
    $variable_holidays = get_variable_islamic_holidays_for_year($year);

    return $variable_holidays[$date] ?? null;
}

// Hafta sonu kontrolü
function is_weekend($date) {
    $day = date('N', strtotime($date));
    return $day >= 6; // 6=Cumartesi, 7=Pazar
}

// Belirli birim ve tarihteki etkinlikleri getir
function get_events_by_unit_and_date($unit_id, $date, $pdo) {
    try {
        $sql = "SELECT e.*, u.unit_name, u.color
                FROM events e
                JOIN units u ON e.unit_id = u.id
                WHERE e.unit_id = ? AND e.event_date = ?
                ORDER BY e.event_time";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$unit_id, $date]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    } catch(PDOException $e) {
        error_log("Etkinlik getirme hatası: " . $e->getMessage());
        return [];
    }
}

// AJAX isteklerini işleme
if (isset($_GET['ajax'])) {
    $ajax_action = clean_input($_GET['ajax']);
    if ($ajax_action === 'get_events' && is_admin()) {
        header('Content-Type: application/json');
        $unit_id = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        $event_date_raw = $_GET['event_date'] ?? '';
        $event_date = clean_input($event_date_raw);

        if ($unit_id === false || !is_valid_date_string($event_date)) {
            http_response_code(400);
            echo json_encode(['error' => 'Geçersiz parametreler']);
            exit;
        }

        $sql = "SELECT id, event_name, event_time, contact_info, status FROM events WHERE unit_id = ? AND event_date = ?";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([$unit_id, $event_date]);
        $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
        echo json_encode($events);
        exit;
    }

    http_response_code(403);
    exit;
}

// Yardımcı: Varsa mevcut çıktı tamponlarını temizle
function clear_output_buffers() {
    while (ob_get_level() > 0) {
        ob_end_clean();
    }
}

function normalize_payment_amount($value) {
    if ($value === null) {
        return null;
    }

    if (is_numeric($value)) {
        return (float) $value;
    }

    $normalized = trim((string) $value);
    if ($normalized === '' || $normalized === '0' || $normalized === '-0') {
        return $normalized === '' ? null : 0.0;
    }

    $normalized = str_replace("\xc2\xa0", ' ', $normalized); // NBSP temizle
    $normalized = str_replace(' ', '', $normalized);
    $normalized = preg_replace('/[^\d,\.\-]/u', '', $normalized);
    if ($normalized === '') {
        return null;
    }

    if (strpos($normalized, ',') !== false && strpos($normalized, '.') !== false) {
        $normalized = str_replace('.', '', $normalized);
        $normalized = str_replace(',', '.', $normalized);
    } else {
        $normalized = str_replace(',', '.', $normalized);
    }

    if ($normalized === '' || $normalized === '.' || $normalized === '-.') {
        return null;
    }

    return is_numeric($normalized) ? (float) $normalized : null;
}

function build_status_summary_counts($data) {
    global $all_event_statuses;

    $canonical_statuses = ['confirmed', 'option', 'free', 'cancelled'];
    $counts = array_fill_keys($canonical_statuses, 0);

    foreach ($data as $event) {
        $status_key = $event['status'] ?? '';
        if (isset($counts[$status_key])) {
            $counts[$status_key]++;
        }
    }

    $labels = [
        'confirmed' => $all_event_statuses['confirmed']['display_name'] ?? 'Onaylı',
        'option' => $all_event_statuses['option']['display_name'] ?? 'Opsiyonlu',
        'free' => $all_event_statuses['free']['display_name'] ?? 'Ücretsiz',
        'cancelled' => $all_event_statuses['cancelled']['display_name'] ?? 'İptal',
    ];

    return [$counts, $labels];
}

function build_status_summary_text($data) {
    [$counts, $labels] = build_status_summary_counts($data);

    return 'Toplam: '
        . $labels['confirmed'] . ' ' . $counts['confirmed'] . ' | '
        . $labels['option'] . ' ' . $counts['option'] . ' | '
        . $labels['free'] . ' ' . $counts['free'] . ' | '
        . $labels['cancelled'] . ' ' . $counts['cancelled'];
}

function build_status_summary_html($data) {
    [$counts, $labels] = build_status_summary_counts($data);

    return 'Toplam: '
        . htmlspecialchars($labels['confirmed'], ENT_QUOTES, 'UTF-8') . ' ' . $counts['confirmed'] . ' | '
        . htmlspecialchars($labels['option'], ENT_QUOTES, 'UTF-8') . ' ' . $counts['option'] . ' | '
        . htmlspecialchars($labels['free'], ENT_QUOTES, 'UTF-8') . ' ' . $counts['free'] . ' | '
        . htmlspecialchars($labels['cancelled'], ENT_QUOTES, 'UTF-8') . ' ' . $counts['cancelled'];
}

// TXT dosyası oluşturma fonksiyonu (GÜNCELLENDİ)
function generateTXT($data, $title, $date_range, $filters) {
    global $all_event_statuses, $all_payment_statuses; // YENİ: Global durumları kullan
    clear_output_buffers(); // Düzeltme: Önceki çıktıları (uyarılar dahil) temizle
    header('Content-Type: text/plain; charset=utf-8');
    header('Content-Disposition: attachment; filename="etkinlik_raporu_' . date('Y-m-d') . '.txt"');

    $output = $title . "\r\n";
    $output .= str_repeat("=", mb_strlen($title, 'UTF-8')) . "\r\n";
    $output .= "Tarih Aralığı: " . $date_range . "\r\n";
    if (!empty($filters)) {
        $output .= "Filtreler: " . $filters . "\r\n";
    }
    $output .= "\r\n" . str_repeat("-", 50) . "\r\n\r\n";

    if (empty($data)) {
        $output .= "Belirtilen kriterlere uygun etkinlik bulunamadı.\r\n";
    } else {
        foreach ($data as $index => $event) {
            // YENİ: Durum ve Ödeme metinlerini dinamik olarak al
            $event_status = $event['status'] ?? '';
            $status_text = $all_event_statuses[$event_status]['display_name'] ?? $event_status;
            
            $payment_text = '-';
            $event_payment_status = $event['payment_status'] ?? '';
            if ($event_status !== 'free' && $event_status !== 'cancelled' && !empty($event_payment_status)) {
                $payment_text = $all_payment_statuses[$event_payment_status]['display_name'] ?? '-';
            }

            $output .= "Kayıt: " . ($index + 1) . "\r\n";
            $output .= "Tarih         : " . date('d.m.Y', strtotime($event['event_date'] ?? 'now')) . "\r\n"; // Düzeltme
            $output .= "Birim         : " . ($event['unit_name'] ?? '-') . "\r\n"; // Düzeltme
            $output .= "Etkinlik Adı  : " . ($event['event_name'] ?? '-') . "\r\n"; // Düzeltme
            $output .= "Saat          : " . ($event['event_time'] ?? '-') . "\r\n"; // Düzeltme
            $output .= "İletişim      : " . ($event['contact_info'] ?? '-') . "\r\n";
            $output .= "Durum         : " . $status_text . "\r\n";
            $output .= "Ödeme         : " . $payment_text . "\r\n";
            $output .= str_repeat("-", 50) . "\r\n\r\n";
        }
    }

    $summary_text = build_status_summary_text($data);
    $output .= $summary_text . "\r\n";
    $output .= "Rapor Oluşturulma Tarihi: " . date('d.m.Y H:i:s') . "\r\n";

    echo $output;
    exit;
}

// DOC dosyası oluşturma fonksiyonu (GÜNCELLENDİ)
function generateDOC($data, $title, $date_range, $filters) {
    global $all_event_statuses, $all_payment_statuses; // YENİ: Global durumları kullan
    clear_output_buffers(); // Düzeltme: Önceki çıktıları (uyarılar dahil) temizle
    header('Content-Type: application/msword; charset=utf-8');
    header('Content-Disposition: attachment; filename="etkinlik_raporu_' . date('Y-m-d') . '.doc"');

    // DÜZELTME: Görünür BOM karakterlerini (ï»¿) önlemek için BOM satırı kaldırıldı.
    // Karakter setini doğru tanıması için meta etiketi yeterlidir.
    $output = '<html><head><meta http-equiv="Content-Type" content="text/html; charset=utf-8"><title>' . htmlspecialchars($title) . '</title>
    <style>
        body { font-family: Calibri, sans-serif; font-size: 11pt; }
        h1 { font-size: 16pt; text-align: center; }
        p { font-size: 11pt; }
        table { border-collapse: collapse; width: 100%; }
        th, td { border: 1px solid #999; padding: 8px; text-align: left; }
        th { background-color: #f2f2f2; font-weight: bold; }
        tr:nth-child(even) { background-color: #f9f9f9; }

        
        /* GÖLGELENDİRME EKLENTİSİ: Etkinlik kutularının daha belirgin görünmesi için */
        .day-card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease;
        }
        
        .day-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.12) !important;
            transform: translateY(-2px);
        }
        
        .event-item {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
            border-radius: 0.4rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .event-item:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08) !important;
        }
        
        /* Takvim grid görünümü için gölgelendirme ayarı */
        @media (min-width: 992px) {
            .calendar-view .day-card {
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
            }
            
            .calendar-view .day-card:hover {
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
            }
        }

    
        
        /* TABLO ÇERÇEVE EKLENTİSİ: Tüm tablolara siyah çerçeve */
        .table {
            border: 2px solid #000000 !important;
            border-collapse: separate !important;
            border-radius: 8px !important;
            overflow: hidden !important;
        }
        
        .table th {
            border: 1px solid #000000 !important;
            background-color: var(--primary-color) !important;
            color: white !important;
            padding: 12px !important;
        }
        
        .table td {
            border: 1px solid #000000 !important;
            padding: 10px !important;
        }
        
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa !important;
        }
        
        .table tbody tr:hover {
            background-color: #e9ecef !important;
        }
        
        .table-responsive {
            border: 2px solid #000000 !important;
            border-radius: 8px !important;
        }
        
    
    </style>
    </head><body>';
    $output .= '<h1>' . htmlspecialchars($title) . '</h1>';
    $output .= '<p><strong>Tarih Aralığı:</strong> ' . htmlspecialchars($date_range) . '</p>';
    // GÜVENLİK GÜNCELLEMESİ: Filtre metninde olası XSS'i önlemek için htmlspecialchars eklendi
    if (!empty($filters)) {
        $output .= '<p><strong>Filtreler:</strong> ' . htmlspecialchars($filters) . '</p>';
    }
    $output .= '<table>';
    $output .= '<thead><tr><th>Tarih</th><th>Birim</th><th>Etkinlik Adı</th><th>Saat</th><th>İletişim</th><th>Durum</th><th>Ödeme</th></tr></thead>';
    $output .= '<tbody>';
    foreach ($data as $event) {
        // YENİ: Durum ve Ödeme metinlerini dinamik olarak al
        $event_status = $event['status'] ?? '';
        $status_text = $all_event_statuses[$event_status]['display_name'] ?? $event_status;
        
        $payment_text = '-';
        $event_payment_status = $event['payment_status'] ?? '';
        if ($event_status !== 'free' && $event_status !== 'cancelled' && !empty($event_payment_status)) {
            $payment_text = $all_payment_statuses[$event_payment_status]['display_name'] ?? '-';
        }

        $output .= '<tr>';
        $output .= '<td>' . turkish_date('d M Y', strtotime($event['event_date'] ?? 'now')) . '</td>';
        $output .= '<td>' . htmlspecialchars($event['unit_name'] ?? '') . '</td>'; // Düzeltme
        $output .= '<td>' . htmlspecialchars($event['event_name'] ?? '') . '</td>'; // Düzeltme
        $output .= '<td>' . htmlspecialchars($event['event_time'] ?? '') . '</td>'; // Düzeltme
        $output .= '<td>' . htmlspecialchars($event['contact_info'] ?? '') . '</td>';
        $output .= '<td>' . $status_text . '</td>';
        $output .= '<td>' . $payment_text . '</td>';
        $output .= '</tr>';
    }
    $summary_text = build_status_summary_html($data);
    $output .= '</tbody>';
    $output .= '<tfoot><tr><td colspan="7"><strong>' . $summary_text . '</strong></td></tr></tfoot>';
    $output .= '</table>';
    $output .= '<p><strong>Toplam Etkinlik:</strong> ' . count($data) . '</p>';
    $output .= '<p><strong>Rapor Oluşturulma Tarihi:</strong> ' . turkish_date('d M Y H:i:s') . '</p>';
    $output .= '</body></html>';
    echo $output;
    exit;
}


function generateXLS($data, $title, $date_range, $filters) {
    global $all_event_statuses, $all_payment_statuses;
    clear_output_buffers();
    header('Content-Type: application/vnd.ms-excel; charset=utf-8');
    header('Content-Disposition: attachment; filename="etkinlik_raporu_' . date('Y-m-d') . '.xls"');
    header('Cache-Control: max-age=0');

    $output = '<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel" xmlns="http://www.w3.org/TR/REC-html40">';
    $output .= '<head><meta http-equiv="Content-Type" content="text/html; charset=utf-8">';
    $output .= '<style>body{font-family:Calibri,Arial,sans-serif;font-size:12px;}';
    $output .= 'h1{font-size:18px;text-align:center;margin-bottom:20px;}';
    $output .= 'table{border-collapse:collapse;width:100%;}';
    $output .= 'th,td{border:1px solid #000;padding:8px;text-align:left;white-space:nowrap;}';
    $output .= 'th{background-color:#f2f2f2;font-weight:bold;}';
    $output .= 'tr:nth-child(even){background-color:#f9f9f9;}';
    $output .= '</style></head><body>';

    $output .= '<h1>' . htmlspecialchars($title, ENT_QUOTES, 'UTF-8') . '</h1>';
    $output .= '<p><strong>Tarih Aralığı:</strong> ' . htmlspecialchars($date_range, ENT_QUOTES, 'UTF-8') . '</p>';
    if (!empty($filters)) {
        $output .= '<p><strong>Filtreler:</strong> ' . htmlspecialchars($filters, ENT_QUOTES, 'UTF-8') . '</p>';
    }

    $output .= '<table>';
    $output .= '<thead><tr>';
    $headers = ['Tarih', 'Birim', 'Etkinlik Adı', 'Saat', 'İletişim', 'Durum', 'Ödeme'];
    foreach ($headers as $header) {
        $output .= '<th>' . htmlspecialchars($header, ENT_QUOTES, 'UTF-8') . '</th>';
    }
    $output .= '</tr></thead><tbody>';

    if (!empty($data)) {
        foreach ($data as $event) {
            $event_status = $event['status'] ?? '';
            $status_text = $all_event_statuses[$event_status]['display_name'] ?? $event_status;

            $payment_text = '-';
            $event_payment_status = $event['payment_status'] ?? '';
            if ($event_status !== 'free' && $event_status !== 'cancelled' && !empty($event_payment_status)) {
                $payment_text = $all_payment_statuses[$event_payment_status]['display_name'] ?? '-';
            }

            $payment = trim((string)($event['payment'] ?? ''));
            if ($payment === '0' || $payment === '-0') {
                $payment = '';
            }

            $payment_display = $payment !== '' ? $payment : $payment_text;

            $output .= '<tr>';
            $output .= '<td>' . htmlspecialchars(turkish_date('d M Y', strtotime($event['event_date'] ?? 'now')), ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($event['unit_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($event['event_name'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($event['event_time'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($event['contact_info'] ?? '', ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($status_text, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '<td>' . htmlspecialchars($payment_display, ENT_QUOTES, 'UTF-8') . '</td>';
            $output .= '</tr>';
        }
    } else {
        $output .= '<tr><td colspan="7" style="text-align:center;">Veri bulunamadı</td></tr>';
    }

    $summary_text = build_status_summary_html($data);
    $output .= '</tbody>';
    $output .= '<tfoot><tr><td colspan="7"><strong>' . $summary_text . '</strong></td></tr></tfoot>';
    $output .= '</table>';
    $output .= '<p><strong>Toplam Etkinlik:</strong> ' . count($data) . '</p>';
    $output .= '<p><strong>Rapor Oluşturulma Tarihi:</strong> ' . htmlspecialchars(turkish_date('d M Y H:i:s'), ENT_QUOTES, 'UTF-8') . '</p>';
    $output .= '</body></html>';

    echo $output;
    exit;
}


// POST isteği kontrolü
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    // Admin giriş işlemi
    if (isset($_POST['admin_login'])) {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $login_error = "Geçersiz istek! (CSRF)";
        } else {
            $username = clean_input($_POST['username'] ?? '');
            $password = $_POST['password'] ?? '';

            if ($username === '' || $password === '') {
                $login_error = "Kullanıcı adı ve şifre zorunludur!";
            } else {
                $user = check_admin_login($username, $password, $pdo);
                if ($user) {
                    session_regenerate_id(true);
                    $_SESSION['admin'] = true;
                    $_SESSION['admin_user'] = [
                        'id' => $user['id'],
                        'username' => $user['username'],
                        'full_name' => $user['full_name'],
                    ];

                    $redirect_path = isset($_SERVER['PHP_SELF']) ? filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL) : '/';
                    $redirect_params = [];
                    foreach ($_GET as $key => $value) {
                        if (is_array($value)) {
                            continue;
                        }
                        $redirect_params[$key] = clean_input($value);
                    }
                    if (!empty($redirect_params)) {
                        $redirect_path .= '?' . http_build_query($redirect_params);
                    }

                    header('Location: ' . $redirect_path);
                    exit;
                } else {
                    $login_error = "Geçersiz kullanıcı adı veya şifre!";
                }
            }
        }
    }

    // Admin çıkış işlemi
    if (isset($_POST['admin_logout'])) {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Geçersiz istek! (CSRF)";
        } else {
            $_SESSION = [];
            if (ini_get('session.use_cookies')) {
                $params = session_get_cookie_params();
                setcookie(session_name(), '', time() - 42000, $params['path'], $params['domain'], $params['secure'], $params['httponly']);
            }
            session_destroy();
        }
        $redirect_path = isset($_SERVER['PHP_SELF']) ? filter_var($_SERVER['PHP_SELF'], FILTER_SANITIZE_URL) : '/';
        header('Location: ' . $redirect_path);
        exit;
    }

    // Etkinlik silme işlemi (Ana Sayfa'dan)
    if (isset($_POST['delete_event_index']) && is_admin()) {
        // CSRF token'ı kontrol et
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
            exit;
        }
        $id = (int)$_POST['event_id'];
        try {
            $event_for_log = null;
            try {
                $event_info_stmt = $pdo->prepare("SELECT e.*, u.unit_name FROM events e LEFT JOIN units u ON e.unit_id = u.id WHERE e.id = ? LIMIT 1");
                $event_info_stmt->execute([$id]);
                $event_for_log = $event_info_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log('Silinecek etkinlik bilgisi alınamadı: ' . $e->getMessage());
            }
            $sql = "DELETE FROM events WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $_SESSION['message'] = "Etkinlik başarıyla silindi!";
            if ($event_for_log) {
                record_event_update($id, 'cancelled', $event_for_log, $updates_duration, $updates_duration_unit, $updates_enabled);
            }
        } catch(PDOException $e) {
            // GÜVENLİK GÜNCELLEMESİ: Hata mesajını gizle
            error_log("Etkinlik silme hatası (index): " . $e->getMessage());
            $_SESSION['error'] = "Silme işlemi sırasında bir veritabanı hatası oluştu.";
        }
        // Ana sayfada kal
        header("Location: " . $_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
        exit;
    }

    // Etkinlik silme işlemi (Admin Paneli)
    if (isset($_POST['delete_event']) && is_admin()) {
        if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
            $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            header("Location: ?page=admin&tab=events");
            exit;
        }
        $id = (int)$_POST['event_id'];
        try {
            $event_for_log = null;
            try {
                $event_info_stmt = $pdo->prepare("SELECT e.*, u.unit_name FROM events e LEFT JOIN units u ON e.unit_id = u.id WHERE e.id = ? LIMIT 1");
                $event_info_stmt->execute([$id]);
                $event_for_log = $event_info_stmt->fetch(PDO::FETCH_ASSOC);
            } catch (PDOException $e) {
                error_log('Silinecek etkinlik bilgisi alınamadı (admin): ' . $e->getMessage());
            }
            $sql = "DELETE FROM events WHERE id = ?";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$id]);
            $_SESSION['message'] = "Etkinlik başarıyla silindi!";
            if ($event_for_log) {
                record_event_update($id, 'cancelled', $event_for_log, $updates_duration, $updates_duration_unit, $updates_enabled);
            }
        } catch(PDOException $e) {
            // GÜVENLİK GÜNCELLEMESİ: Hata mesajını gizle
            error_log("Etkinlik silme hatası (admin): " . $e->getMessage());
            $_SESSION['error'] = "Silme işlemi sırasında bir veritabanı hatası oluştu.";
        }
        // Admin paneline yönlendir
        // GÜNCELLEME: Filtreleri koru
        $query_params = $_GET;
        unset($query_params['page']); // page=admin zaten ekli
        unset($query_params['tab']); // tab=events zaten ekli
        header("Location: ?page=admin&tab=events&" . http_build_query($query_params));
        exit;
    }

    // Rapor oluşturma
    if (isset($_POST['generate_report'])) {
        $start_date = clean_input($_POST['start_date']);
        $end_date = clean_input($_POST['end_date']);
        $unit_ids = isset($_POST['unit_ids']) ? $_POST['unit_ids'] : [];
        $status_filter = isset($_POST['status_filter']) ? clean_input($_POST['status_filter']) : '';
        $payment_filter = isset($_POST['payment_filter']) ? clean_input($_POST['payment_filter']) : '';
        $export_type = isset($_POST['generate_report']) ? clean_input($_POST['generate_report']) : '';

        try {
            $sql = "SELECT e.*, u.unit_name, u.color
                    FROM events e
                    JOIN units u ON e.unit_id = u.id
                    WHERE e.event_date BETWEEN ? AND ?";
            $params = [$start_date, $end_date];
            // Birim filtresi
            if (!empty($unit_ids)) {
                if (!in_array('all', $unit_ids)) {
                    $placeholders = str_repeat('?,', count($unit_ids) - 1) . '?';
                    $sql .= " AND e.unit_id IN ($placeholders)";
                    $params = array_merge($params, $unit_ids);
                }
                // 'all' seçiliyse (veya 'all' ile birlikte diğerleri), filtre ekleme, tüm birimler gelsin.
            } else {
                // $unit_ids boşsa (hiçbir birim seçilmemişse), hiçbir sonuç getirme.
                $sql .= " AND 1=0"; 
            }
            // Durum filtresi
            if (!empty($status_filter)) {
                $sql .= " AND e.status = ?";
                $params[] = $status_filter;
            }
            // Ödeme filtresi
            if (!empty($payment_filter)) {
                $sql .= " AND e.payment_status = ?";
                $params[] = $payment_filter;
            }
            $sql .= " ORDER BY e.event_date, u.unit_name, e.event_time";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $report_data = $stmt->fetchAll(PDO::FETCH_ASSOC);

            // TXT/DOC/XLS oluşturma
            if (!empty($export_type) && in_array($export_type, ['txt', 'doc', 'xls'], true)) {
                $title = "Çeşme Belediyesi Kültür Müdürlüğü Etkinlik Raporu";
                $date_range = turkish_date('d M Y', strtotime($start_date)) . ' - ' . turkish_date('d M Y', strtotime($end_date));
                $filters = [];
                if (!empty($unit_ids) && !in_array('all', $unit_ids)) {
                    $unit_names = [];
                    foreach ($unit_ids as $unit_id) {
                        $stmt = $pdo->prepare("SELECT unit_name FROM units WHERE id = ?");
                        $stmt->execute([$unit_id]);
                        $unit = $stmt->fetch(PDO::FETCH_ASSOC);
                        if ($unit) $unit_names[] = $unit['unit_name'];
                    }
                    $filters[] = 'Birimler: ' . implode(', ', $unit_names);
                } else {
                    $filters[] = 'Tüm Birimler';
                }
                if (!empty($status_filter)) {
                    // YENİ: Dinamik durum adını al
                    $filters[] = 'Durum: ' . ($all_event_statuses[$status_filter]['display_name'] ?? $status_filter);
                }
                if (!empty($payment_filter)) {
                    // YENİ: Dinamik ödeme durumu adını al
                    $filters[] = 'Ödeme: ' . ($all_payment_statuses[$payment_filter]['display_name'] ?? $payment_filter);
                }
                $filters_text = implode(' | ', $filters);

                if ($export_type === 'txt') {
                    generateTXT($report_data, $title, $date_range, $filters_text);
                }
                if ($export_type === 'doc') {
                    generateDOC($report_data, $title, $date_range, $filters_text);
                }
                if ($export_type === 'xls') {
                    generateXLS($report_data, $title, $date_range, $filters_text);
                }
            }
            $_SESSION['report_data'] = $report_data;
            $_SESSION['report_filters'] = [
                'start_date' => $start_date,
                'end_date' => $end_date,
                'unit_ids' => $unit_ids,
                'status_filter' => $status_filter,
                'payment_filter' => $payment_filter
            ];
            header("Location: ?page=admin&tab=reports");
            exit;
        } catch(PDOException $e) {
            // GÜVENLİK GÜNCELLEMESİ: Hata mesajını gizle
            error_log("Rapor oluşturma hatası: " . $e->getMessage());
            $_SESSION['error'] = "Rapor oluşturulurken bir veritabanı hatası oluştu.";
            header("Location: ?page=admin&tab=reports");
            exit;
        }
    }

    // Sadece admin yetkisine sahip kullanıcılar için çalışsın
    if (is_admin()) {
        $message = '';
        $error = '';

        // Yeni etkinlik ekleme veya güncelleme
        if (isset($_POST['save_event'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $error = "Geçersiz istek! (CSRF)";
            } else {
                $id = isset($_POST['event_id']) ? (int)$_POST['event_id'] : 0;
                $unit_id = clean_input($_POST['unit_id']);
                $date = clean_input($_POST['event_date']);
                $name = clean_input($_POST['event_name']);
                $time = clean_input($_POST['event_time']);
                $contact = clean_input($_POST['event_contact']);
                $notes = clean_input($_POST['event_notes']);
                $status = clean_input($_POST['event_status']);
                $payment = clean_input($_POST['payment_status']);

                if ($status === 'free' || $status === 'cancelled') {
                    $payment = null; // null olarak ayarla
                }

                try {
                    $existing_event = null;
                    if ($id > 0) {
                        $event_fetch = $pdo->prepare("SELECT e.*, u.unit_name FROM events e LEFT JOIN units u ON e.unit_id = u.id WHERE e.id = ? LIMIT 1");
                        $event_fetch->execute([$id]);
                        $existing_event = $event_fetch->fetch(PDO::FETCH_ASSOC);
                    }

                    if ($id > 0) {
                        $sql = "UPDATE events SET unit_id = ?, event_date = ?, event_name = ?, event_time = ?,
                                contact_info = ?, notes = ?, status = ?, payment_status = ?, updated_at = NOW()
                                WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$unit_id, $date, $name, $time, $contact, $notes, $status, $payment, $id]);
                        $_SESSION['message'] = "Etkinlik başarıyla güncellendi!";
                    } else {
                        $sql = "INSERT INTO events (unit_id, event_date, event_name, event_time, contact_info, notes, status, payment_status)
                                VALUES (?, ?, ?, ?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$unit_id, $date, $name, $time, $contact, $notes, $status, $payment]);
                        $_SESSION['message'] = "Etkinlik başarıyla eklendi!";
                    }

                    $event_id = $id > 0 ? $id : (int) $pdo->lastInsertId();
                    $update_type = $id > 0 ? 'updated' : 'new';
                    if ($status === 'cancelled' && ($existing_event === null || $existing_event['status'] !== 'cancelled')) {
                        $update_type = 'cancelled';
                    }

                    $event_data_for_log = [
                        'event_name' => $name,
                        'event_date' => $date,
                        'unit_id' => $unit_id,
                        'unit_name' => $existing_event['unit_name'] ?? ''
                    ];
                    record_event_update($event_id, $update_type, $event_data_for_log, $updates_duration, $updates_duration_unit, $updates_enabled);

                    if (isset($_POST['source_page']) && $_POST['source_page'] == 'index') {
                        header("Location: ".$_SERVER['PHP_SELF'] . "?" . http_build_query($_GET));
                        exit;
                    } elseif (isset($_POST['source_page']) && $_POST['source_page'] == 'admin') {
                        // GÜNCELLEME: Admin panelindeysek, filtreleri koruyarak yönlendir
                        $query_params = $_GET;
                        unset($query_params['page']); // page=admin zaten ekli
                        unset($query_params['tab']); // tab=events zaten ekli
                        header("Location: ?page=admin&tab=events&" . http_build_query($query_params));
                        exit;
                    }
                } catch(PDOException $e) {
                    error_log("Etkinlik kaydetme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "İşlem sırasında bir veritabanı hatası oluştu.";
                }
            }
        }

        // Birim işlemleri
        if (isset($_POST['save_unit'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = isset($_POST['unit_id']) ? (int)$_POST['unit_id'] : 0;
                $name = clean_input($_POST['unit_name']);
                $color = clean_input($_POST['unit_color']);
                $active = isset($_POST['unit_active']) ? 1 : 0;
                try {
                    if ($id > 0) {
                        $sql = "UPDATE units SET unit_name = ?, color = ?, is_active = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$name, $color, $active, $id]);
                        $_SESSION['message'] = "Birim başarıyla güncellendi!";
                    } else {
                        $sql = "INSERT INTO units (unit_name, color, is_active) VALUES (?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$name, $color, $active]);
                        $_SESSION['message'] = "Birim başarıyla eklendi!";
                    }
                } catch(PDOException $e) {
                    error_log("Birim kaydetme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "İşlem sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=units");
            exit;
        }

        // Birim silme
        if (isset($_POST['delete_unit'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = (int)$_POST['unit_id'];
                try {
                    $sql = "DELETE FROM units WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);
                    $_SESSION['message'] = "Birim başarıyla silindi!";
                } catch(PDOException $e) {
                    error_log("Birim silme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "Silme işlemi sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=units");
            exit;
        }

        // Tatil işlemleri
        if (isset($_POST['save_holiday'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = isset($_POST['holiday_id']) ? (int)$_POST['holiday_id'] : 0;
                $name = clean_input($_POST['holiday_name']);
                $date = clean_input($_POST['holiday_date']);
                try {
                    if ($id > 0) {
                        $sql = "UPDATE holidays SET holiday_name = ?, holiday_date = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$name, $date, $id]);
                        $_SESSION['message'] = "Tatil başarıyla güncellendi!";
                    } else {
                        $sql = "INSERT INTO holidays (holiday_name, holiday_date) VALUES (?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$name, $date]);
                        $_SESSION['message'] = "Tatil başarıyla eklendi!";
                    }
                } catch(PDOException $e) {
                    error_log("Tatil kaydetme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "İşlem sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=holidays");
            exit;
        }

        // Tatil silme
        if (isset($_POST['delete_holiday'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = (int)$_POST['holiday_id'];
                try {
                    $sql = "DELETE FROM holidays WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);
                    $_SESSION['message'] = "Tatil başarıyla silindi!";
                } catch(PDOException $e) {
                    error_log("Tatil silme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "Silme işlemi sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=holidays");
            exit;
        }
        
        // Admin kullanıcı işlemleri
        if (isset($_POST['save_admin_user'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = isset($_POST['user_id']) ? (int)$_POST['user_id'] : 0;
                $username = clean_input($_POST['username'] ?? '');
                $password = $_POST['password'] ?? '';
                $full_name = clean_input($_POST['full_name'] ?? '');
                $email = clean_input($_POST['email'] ?? '');
                $active = isset($_POST['user_active']) ? 1 : 0;
                if ($username === '') {
                    throw new Exception("Kullanıcı adı zorunludur!");
                }
                if (!preg_match('/^[A-Za-z0-9._-]{3,}$/u', $username)) {
                    throw new Exception("Kullanıcı adı en az 3 karakter olmalı ve yalnızca harf, rakam, nokta, alt çizgi veya tire içerebilir.");
                }
                if ($email !== '' && !filter_var($email, FILTER_VALIDATE_EMAIL)) {
                    throw new Exception("Geçersiz e-posta adresi!");
                }
                try {
                    if ($id > 0) {
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ? AND id <> ?");
                        $stmt->execute([$username, $id]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception("Bu kullanıcı adı zaten kullanılıyor!");
                        }
                        $sql = "UPDATE admin_users SET username = ?, full_name = ?, email = ?, is_active = ?";
                        $params = [$username, $full_name, $email, $active];
                        if ($password !== '') {
                            $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                            $sql .= ", password = ?";
                            $params[] = $hashed_password;
                        }
                        $sql .= " WHERE id = ?";
                        $params[] = $id;
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute($params);
                        $_SESSION['message'] = "Kullanıcı başarıyla güncellendi!";
                    } else {
                        if ($password === '') {
                            throw new Exception("Yeni kullanıcı için şifre zorunludur!");
                        }
                        $stmt = $pdo->prepare("SELECT COUNT(*) FROM admin_users WHERE username = ?");
                        $stmt->execute([$username]);
                        if ($stmt->fetchColumn() > 0) {
                            throw new Exception("Bu kullanıcı adı zaten kullanılıyor!");
                        }
                        $hashed_password = password_hash($password, PASSWORD_DEFAULT);
                        $sql = "INSERT INTO admin_users (username, password, full_name, email, is_active) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$username, $hashed_password, $full_name, $email, $active]);
                        $_SESSION['message'] = "Kullanıcı başarıyla eklendi!";
                    }
                } catch(Exception $e) {
                    $_SESSION['error'] = "İşlem sırasında hata oluştu: " . $e->getMessage();
                } catch(PDOException $e) {
                    error_log("Kullanıcı kaydetme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "İşlem sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=users");
            exit;
        }

        // Admin kullanıcı silme
        if (isset($_POST['delete_admin_user'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = (int)$_POST['user_id'];
                if ($id == $_SESSION['admin_user']['id']) {
                    $_SESSION['error'] = "Kendi kullanıcınızı silemezsiniz!";
                } else {
                    try {
                        $sql = "DELETE FROM admin_users WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$id]);
                        $_SESSION['message'] = "Kullanıcı başarıyla silindi!";
                    } catch(PDOException $e) {
                        error_log("Kullanıcı silme hatası: " . $e->getMessage());
                        $_SESSION['error'] = "Silme işlemi sırasında bir veritabanı hatası oluştu.";
                    }
                }
            }
            header("Location: ?page=admin&tab=users");
            exit;
        }

        // YENİ: Duyuru işlemleri
        if (isset($_POST['save_announcement'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = isset($_POST['announcement_id']) ? (int)$_POST['announcement_id'] : 0;
                $content = clean_input($_POST['content']);
                $show_author = isset($_POST['show_author']) ? 1 : 0;
                $start_date = !empty($_POST['start_date']) ? clean_input($_POST['start_date']) : null;
                $end_date = !empty($_POST['end_date']) ? clean_input($_POST['end_date']) : null;
                $admin_user_id = $_SESSION['admin_user']['id'];
                
                try {
                    if ($id > 0) {
                        $sql = "UPDATE announcements SET content = ?, show_author = ?, start_date = ?, end_date = ? WHERE id = ?";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$content, $show_author, $start_date, $end_date, $id]);
                        $_SESSION['message'] = "Duyuru başarıyla güncellendi!";
                    } else {
                        $sql = "INSERT INTO announcements (content, admin_user_id, show_author, start_date, end_date) VALUES (?, ?, ?, ?, ?)";
                        $stmt = $pdo->prepare($sql);
                        $stmt->execute([$content, $admin_user_id, $show_author, $start_date, $end_date]);
                        $_SESSION['message'] = "Duyuru başarıyla eklendi!";
                    }
                } catch(PDOException $e) {
                    error_log("Duyuru kaydetme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "İşlem sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=announcements");
            exit;
        }
        
        // YENİ: Duyuru silme
        if (isset($_POST['delete_announcement'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                $id = (int)$_POST['announcement_id'];
                try {
                    $sql = "DELETE FROM announcements WHERE id = ?";
                    $stmt = $pdo->prepare($sql);
                    $stmt->execute([$id]);
                    $_SESSION['message'] = "Duyuru başarıyla silindi!";
                } catch(PDOException $e) {
                    error_log("Duyuru silme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "Silme işlemi sırasında bir veritabanı hatası oluştu.";
                }
            }
            header("Location: ?page=admin&tab=announcements");
            exit;
        }

        // YENİ: Ayarları Kaydetme
        if (isset($_POST['save_status_settings'])) {
            if (!isset($_POST['csrf_token']) || !validateCSRFToken($_POST['csrf_token'])) {
                $_SESSION['error'] = "Geçersiz istek! (CSRF)";
            } else {
                try {
                    $pdo->beginTransaction();
                    
                    // Etkinlik Durumlarını Güncelle
                    if (isset($_POST['event_status']) && is_array($_POST['event_status'])) {
                        $stmt_event = $pdo->prepare("UPDATE event_statuses SET display_name = ?, color = ? WHERE status_key = ?");
                        foreach ($_POST['event_status'] as $key => $values) {
                            $stmt_event->execute([clean_input($values['display_name']), clean_input($values['color']), $key]);
                        }
                    }

                    // Ödeme Durumlarını Güncelle
                    if (isset($_POST['payment_status']) && is_array($_POST['payment_status'])) {
                        $stmt_payment = $pdo->prepare("UPDATE payment_statuses SET display_name = ?, color = ? WHERE status_key = ?");
                        foreach ($_POST['payment_status'] as $key => $values) {
                            $stmt_payment->execute([clean_input($values['display_name']), clean_input($values['color']), $key]);
                        }
                    }

                    $maintenance_mode_value = isset($_POST['maintenance_mode']) ? '1' : '0';
                    if (!set_app_setting('maintenance_mode', $maintenance_mode_value)) {
                        throw new Exception('Bakım modu ayarı kaydedilemedi.');
                    }

                    $updates_enabled_value = isset($_POST['updates_enabled']) ? '1' : '0';
                    $updates_max_count_value = isset($_POST['updates_max_count']) ? max(1, (int) $_POST['updates_max_count']) : 10;
                    $updates_duration_value = isset($_POST['updates_duration']) ? max(1, (int) $_POST['updates_duration']) : 24;
                    $updates_duration_unit_value = (isset($_POST['updates_duration_unit']) && $_POST['updates_duration_unit'] === 'days') ? 'days' : 'hours';

                    if (!set_app_setting('updates_enabled', $updates_enabled_value)) {
                        throw new Exception('Güncelleme gösterimi ayarı kaydedilemedi.');
                    }
                    if (!set_app_setting('updates_max_count', $updates_max_count_value)) {
                        throw new Exception('Gösterilecek güncelleme sayısı kaydedilemedi.');
                    }
                    if (!set_app_setting('updates_duration', $updates_duration_value)) {
                        throw new Exception('Güncelleme süresi kaydedilemedi.');
                    }
                    if (!set_app_setting('updates_duration_unit', $updates_duration_unit_value)) {
                        throw new Exception('Güncelleme süresi birimi kaydedilemedi.');
                    }

                    $pdo->commit();
                    $_SESSION['message'] = "Ayarlar başarıyla güncellendi!";
                } catch(Exception $e) {
                    $pdo->rollBack();
                    error_log("Ayar kaydetme hatası: " . $e->getMessage());
                    $_SESSION['error'] = "Ayarlar kaydedilirken bir hata oluştu.";
                }
            }
            header("Location: ?page=admin&tab=settings");
            exit;
        }
    }
}

// Sayfa parametresi
$page = isset($_GET['page']) ? clean_input($_GET['page']) : 'index';
$allowed_pages = ['index', 'admin'];
if (!in_array($page, $allowed_pages, true)) {
    $page = 'index';
}

$unit_id = filter_input(INPUT_GET, 'unit_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
if ($unit_id === false || $unit_id === null) {
    $unit_id = 1;
}

// Yeni: Yeni ay ve yıl parametrelerini al
$selected_month = filter_input(INPUT_GET, 'month', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1, 'max_range' => 12]]);
if ($selected_month === false || $selected_month === null) {
    $selected_month = (int) date('n');
}

$current_year = (int) date('Y');
$selected_year = filter_input(INPUT_GET, 'year', FILTER_VALIDATE_INT, ['options' => ['min_range' => $current_year - 5, 'max_range' => $current_year + 5]]);
if ($selected_year === false || $selected_year === null) {
    $selected_year = $current_year;
}

// Mesaj ve hata kontrolü
if (isset($_SESSION['message'])) {
    $message = $_SESSION['message'];
    unset($_SESSION['message']);
}
if (isset($_SESSION['error'])) {
    $error = $_SESSION['error'];
    unset($_SESSION['error']);
}

?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çeşme Belediyesi Kültür Müdürlüğü - Etkinlik Takvimi</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <style>
        :root {
            --primary-color: #1a365d;
            --secondary-color: #2d6a4f;
            --accent-color: #e63946;
            --light-color: #f8f9fa;
            --dark-color: #14213d;
            --success-color: #2a9d8f;
            --warning-color: #f4a261;
            --info-color: #3a86ff;
            --bs-border-radius: .6rem;
            --card-shadow: 0 4px 20px rgba(0, 0, 0, 0.08);
            --hover-shadow: 0 8px 30px rgba(0, 0, 0, 0.12);
            
            <?php
            // YENİ: Dinamik Durum Renklerini CSS Değişkenleri Olarak Ekle
            foreach ($all_event_statuses as $key => $status) {
                echo "--status-color-$key: " . htmlspecialchars($status['color']) . ";\n";
            }
            foreach ($all_payment_statuses as $key => $status) {
                echo "--payment-color-$key: " . htmlspecialchars($status['color']) . ";\n";
            }
            ?>
        }
        
        body {
            font-family: 'Segoe UI', system-ui, -apple-system, sans-serif;
            background-color: #f8fafc;
            color: #334155;
            line-height: 1.6;
        }

        .maintenance-alert {
            border-left: 6px solid var(--warning-color);
            box-shadow: var(--card-shadow);
            background: #fffbea;
        }

        .navbar {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            box-shadow: 0 4px 12px rgba(0, 0, 0, 0.1);
            padding: 0.8rem 0;
        }
        
        .navbar-brand {
            font-weight: 700;
            font-size: 1.3rem;
            display: flex;
            flex-direction: column;
            align-items: flex-start;
            line-height: 1.2;
        }

        .navbar-brand .brand-title {
            display: flex;
            align-items: center;
            gap: 0.5rem;
        }

        .navbar-brand i {
            font-size: 1.5rem;
        }

        .navbar-brand .brand-subtitle {
            font-size: 0.75rem;
            font-weight: 500;
            color: rgba(255, 255, 255, 0.85);
            margin-top: 0.15rem;
        }
        
        .navbar-nav .nav-link {
            font-weight: 500;
            padding: 0.5rem 1rem;
            border-radius: 0.5rem;
            transition: all 0.3s ease;
        }
        
        .navbar-nav .nav-link:hover, .navbar-nav .nav-link.active {
            background-color: rgba(255, 255, 255, 0.1);
        }
        
        .container {
            max-width: 1400px;
        }
        
        .card, .admin-panel, .month-selector, .unit-selector, .legend {
            border: none;
            border-radius: var(--bs-border-radius);
            box-shadow: var(--card-shadow);
            transition: all 0.3s ease;
            margin-bottom: 1.5rem;
            overflow: hidden;
        }
        
        .card:hover, .admin-panel:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .card-header {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: white;
            border: none;
            padding: 1rem 1.5rem;
            font-weight: 600;
        }
        
        .card-body {
            padding: 1.5rem;
        }
        
        .card-title {
            font-weight: 600;
            color: var(--primary-color);
            margin-bottom: 1rem;
        }
        
        .calendar-view {
            display: grid;
            grid-template-columns: repeat(auto-fill, minmax(320px, 1fr));
            gap: 1.5rem;
        }
        
        @media (max-width: 768px) {
            .calendar-view {
                grid-template-columns: 1fr;
            }
        }
        
        .day-card {
    box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.08) !important;
            background-color: white;
            border-radius: var(--bs-border-radius);
            display: flex;
            flex-direction: column;
            overflow: hidden;
            transition: all 0.3s ease;
        }
        
        .day-card:hover {
            transform: translateY(-5px);
            box-shadow: var(--hover-shadow);
        }
        
        .day-card.no-events {
            background-color: #f8f9fa;
        }
        
        .day-header {
            font-weight: 600;
            padding: 1rem;
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: white;
            font-size: 0.95rem;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .day-header-content {
            flex-grow: 1;
        }
        
        .day-header-actions {
            margin-left: 10px;
        }
        
        .day-card.weekend .day-header {
            background: linear-gradient(135deg, var(--secondary-color), #40916c);
        }
        
        .day-card.holiday .day-header {
            background: linear-gradient(135deg, var(--warning-color), #e76f51);
        }
        
        .day-card .day-content {
            padding: 1rem;
            flex-grow: 1;
        }
        
        .event-item {
            position: relative;
            padding: 1rem;
            border-top: 1px solid #e9ecef;
            transition: background-color 0.2s ease;
            border-radius: 0.5rem;
            margin-bottom: 0.5rem;
            background-color: #f8fafc;
        }
.event-item:first-child {
            border-top: none;
        }
        
        .event-item:hover {
            background-color: #e2e8f0;
        }
        
        .event-item h6 {
            margin-bottom: 0.25rem; /* Küçültüldü */
            font-weight: 600;
            color: var(--primary-color);
            font-size: 0.85rem; /* Küçültüldü */
        }
        
        .event-time, .event-contact, .event-notes {
            font-size: 0.8rem; /* Küçültüldü */
            color: #64748b;
            margin-bottom: 3px; /* Küçültüldü */
        }
        
        .event-status {
            margin-top: 0.5rem;
        }
        
        .event-status .badge {
            margin-right: 5px;
            font-size: 0.75rem;
            padding: 0.4rem 0.6rem;
        }
        
        .login-container {
            max-width: 400px;
            margin: 100px auto;
            padding: 2rem;
            background-color: white;
            border-radius: var(--bs-border-radius);
            box-shadow: var(--card-shadow);
        }
        
        footer {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: white;
            padding: 1.5rem 0;
            margin-top: 3rem;
        }
        
        .welcome-message {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: white;
            border-radius: var(--bs-border-radius);
            padding: 2rem;
            margin-bottom: 2rem;
            box-shadow: var(--card-shadow);
        }
        
        .admin-badge {
            background-color: var(--success-color);
            color: white;
            padding: 0.4rem 0.8rem;
            border-radius: 1rem;
            font-size: 0.85rem;
            font-weight: 500;
        }
        
        .btn-edit-quick, .btn-delete-quick, .btn-add-day {
            position: relative;
            color: #94a3b8;
            background-color: transparent;
            border: none;
            padding: 0.5rem;
            line-height: 1;
            border-radius: 50%;
            transition: all 0.2s ease;
            opacity: 0;
            z-index: 10;
            font-size: 0.8rem;
            margin-left: 5px;
            width: 30px;
            height: 30px;
            display: flex;
            align-items: center;
            justify-content: center;
        }
        
        .event-item:hover .btn-edit-quick,
        .event-item:hover .btn-delete-quick,
        .day-card:hover .btn-add-day {
            opacity: 1;
        }
        
        /* GÜNCELLENDİ: Grid görünümünde hover butonu her zaman görünür olsun */
        @media (min-width: 992px) {
            .day-card:hover .btn-add-day {
                opacity: 1;
            }
            .day-card .day-header-actions .btn-add-day {
                opacity: 0; /* Normalde gizli */
            }
            .day-card:hover .day-header-actions .btn-add-day {
                opacity: 1; /* Hover'da görünür */
            }
        }
        
        .btn-edit-quick:hover {
            color: var(--primary-color);
            background-color: #e2e8f0;
        }
        
        .btn-delete-quick:hover {
            color: var(--accent-color);
            background-color: #fecaca;
        }
        
        .btn-add-day:hover {
            color: var(--info-color);
            background-color: #dbeafe;
        }
        
        .payment-status-container {
            transition: all 0.3s ease;
        }
        
        .payment-status-container.hidden {
            display: none !important;
        }
        
        /* .bg-purple artık kullanılmıyor, var(--payment-color-to_be_paid) tarafından yönetiliyor */
        
        .report-filters {
            background-color: #f8fafc;
            padding: 1.5rem;
            border-radius: var(--bs-border-radius);
            margin-bottom: 1.5rem;
        }
        
        .unit-checkboxes {
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #e2e8f0;
            padding: 1rem;
            border-radius: 0.5rem;
            background-color: white;
        }
        
        .report-summary {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: white;
            padding: 1.5rem;
            border-radius: var(--bs-border-radius);
            margin-bottom: 1.5rem;
        }
        
        .export-buttons {
            margin-top: 1.5rem;
            padding: 1.5rem;
            background-color: #f8fafc;
            border-radius: var(--bs-border-radius);
        }
        
        .table-responsive {
            overflow-x: auto;
            border-radius: var(--bs-border-radius);
            box-shadow: 0 0 0 1px #e2e8f0;
        }
        
        .table {
            margin-bottom: 0;
        }
        
        .table th {
            background-color: var(--primary-color);
            color: white;
            font-weight: 600;
            border: none;
            padding: 1rem;
        }
        
        .table td {
            padding: 1rem;
            vertical-align: middle;
            border-color: #e2e8f0;
        }
        
        .table tbody tr:hover {
            background-color: #f1f5f9;
        }
        
        .table th.actions, .table td.actions {
            width: 1%;
            white-space: nowrap;
            text-align: center;
        }
        
        .action-buttons .btn {
            margin: 0 2px;
            padding: 0.25rem 0.5rem;
            font-size: 0.875rem;
            border-radius: 0.4rem;
        }
        
        .existing-events {
            max-height: 300px;
            overflow-y: auto;
            margin-bottom: 1rem;
            border: 1px solid #e2e8f0;
            border-radius: 0.5rem;
            padding: 1rem;
            background-color: white;
        }
        
        .event-list-item {
            padding: 0.8rem;
            border-bottom: 1px solid #e2e8f0;
            border-radius: 0.4rem;
            margin-bottom: 0.5rem;
            background-color: #f8fafc;
        }
        
        .event-list-item:last-child {
            border-bottom: none;
            margin-bottom: 0;
        }
        
        .announcement-section {
            background-color: white;
            border-radius: var(--bs-border-radius);
            box-shadow: var(--card-shadow);
            margin-bottom: 2rem;
            overflow: hidden;
        }

        .recent-updates .badge-update-new {
            background-color: var(--success-color);
        }

        .recent-updates .badge-update-updated {
            background-color: var(--info-color);
        }

        .recent-updates .badge-update-cancelled {
            background-color: var(--accent-color);
        }

        .recent-updates .list-group-item {
            padding-left: 0;
            padding-right: 0;
            border: none;
        }

        .recent-updates .list-group-item + .list-group-item {
            border-top: 1px solid #e9ecef;
        }
        
        .accordion-button:not(.collapsed) {
            background-color: var(--primary-color);
            color: white;
        }
        
        .accordion-button:focus {
            box-shadow: 0 0 0 0.25rem rgba(26, 54, 93, 0.25);
        }
        
        .badge-new {
            font-size: 0.75em;
            padding: .3em .6em;
            margin-left: 8px;
        }
        
        .announcement-meta {
            font-size: 0.8rem;
            color: #64748b;
            margin-top: 0.5rem;
        }
        
        .btn {
            border-radius: 0.5rem;
            font-weight: 500;
            padding: 0.5rem 1rem;
            transition: all 0.3s ease;
        }
        
        .btn-primary {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            border: none;
        }
        
        .btn-primary:hover {
            transform: translateY(-2px);
            box-shadow: 0 4px 12px rgba(26, 54, 93, 0.3);
        }
        
        .btn-success {
            background: linear-gradient(135deg, var(--success-color), #2a9d8f);
            border: none;
        }
        
        .btn-warning {
            background: linear-gradient(135deg, var(--warning-color), #f4a261);
            border: none;
        }
        
        .btn-danger {
            background: linear-gradient(135deg, var(--accent-color), #e63946);
            border: none;
        }
        
        .btn-info {
            background: linear-gradient(135deg, var(--info-color), #3a86ff);
            border: none;
        }
        
        .form-control, .form-select {
            border-radius: 0.5rem;
            padding: 0.5rem 1rem;
            border: 1px solid #cbd5e1;
            transition: all 0.3s ease;
        }
        
        .form-control:focus, .form-select:focus {
            box-shadow: 0 0 0 0.25rem rgba(26, 54, 93, 0.25);
            border-color: var(--primary-color);
        }
        
        .alert {
            border-radius: 0.5rem;
            border: none;
            padding: 1rem 1.5rem;
        }
        
        .nav-tabs {
            border-bottom: 2px solid #e2e8f0;
        }
        
        .nav-tabs .nav-link {
            border: none;
            border-radius: 0.5rem 0.5rem 0 0;
            padding: 0.75rem 1.5rem;
            font-weight: 500;
            color: #64748b;
            transition: all 0.3s ease;
        }
        
        .nav-tabs .nav-link.active {
            color: var(--primary-color);
            background-color: white;
            border-bottom: 3px solid var(--primary-color);
        }
        
        .nav-tabs .nav-link:hover {
            color: var(--primary-color);
            background-color: #f1f5f9;
        }
        
        .modal-content {
            border-radius: var(--bs-border-radius);
            border: none;
            box-shadow: 0 10px 40px rgba(0, 0, 0, 0.15);
        }
        
        .modal-header {
            background: linear-gradient(135deg, var(--primary-color), #2c5282);
            color: white;
            border-radius: var(--bs-border-radius) var(--bs-border-radius) 0 0;
            padding: 1.5rem;
        }
        
        .modal-title {
            font-weight: 600;
        }
        
        .modal-body {
            padding: 1.5rem;
        }
        
        .modal-footer {
            padding: 1rem 1.5rem;
            border-top: 1px solid #e2e8f0;
        }
        
        .badge {
            font-weight: 500;
            border-radius: 0.5rem;
        }
        
        .legend .badge {
            margin-right: 0.5rem;
            margin-bottom: 0.5rem;
        }
        
        .input-group .btn {
            border-radius: 0 0.5rem 0.5rem 0;
        }
        
        .input-group .form-control:first-child {
            border-radius: 0.5rem 0 0 0.5rem;
        }
        
        .input-group-text {
            border-radius: 0.5rem 0 0 0.5rem;
        }

        <?php
        // YENİ: Dinamik Badge Stilleri
        function generateBadgeStyle($status) {
            $style = "background-color: " . htmlspecialchars($status['color']) . " !important; color: white !important;";
            // Basit parlaklık kontrolü
            $hex = ltrim($status['color'], '#');
            if (strlen($hex) == 6) {
                $r = hexdec(substr($hex, 0, 2));
                $g = hexdec(substr($hex, 2, 2));
                $b = hexdec(substr($hex, 4, 2));
                $luminance = (0.299 * $r + 0.587 * $g + 0.114 * $b) / 255;
                if ($luminance > 0.65) { // Açık renkler için eşik
                    $style .= " color: #212529 !important; text-shadow: none;";
                } else {
                    $style .= " text-shadow: 1px 1px 1px rgba(0,0,0,0.2);";
                }
            }
            return $style;
        }

        foreach ($all_event_statuses as $key => $status) {
            echo ".badge-status-$key { " . generateBadgeStyle($status) . " }\n";
        }
        foreach ($all_payment_statuses as $key => $status) {
            echo ".badge-payment-$key { " . generateBadgeStyle($status) . " }\n";
        }
        ?>

        /* YENİ: Takvim Grid Stilleri */
        .calendar-header {
            display: none; /* Mobilde gizli */
        }

        .calendar-view {
            display: grid;
            /* Mobilde (varsayılan) dikey liste */
            grid-template-columns: 1fr;
            gap: 1.5rem;
        }

        @media (min-width: 992px) {
            /* Masaüstü: Haftalık Grid Başlığı */
            .calendar-header {
                display: grid;
                grid-template-columns: repeat(7, 1fr);
                gap: 5px;
                margin-bottom: 0.5rem;
            }
            .calendar-header-day {
                text-align: center;
                font-weight: 600;
                padding: 0.75rem 0.25rem;
                background-color: var(--primary-color);
                color: white;
                border-radius: 0.5rem;
            }

            /* Masaüstü: 7 Sütunlu Takvim Grid'i */
            .calendar-view {
                grid-template-columns: repeat(7, 1fr);
                gap: 0; /* Boşlukları kaldır, kenarlıklar yönetecek */
                border: 1px solid #e2e8f0;
                border-radius: var(--bs-border-radius);
                overflow: hidden;
                background-color: white;
            }

            .day-card {
                border-radius: 0;
                margin-bottom: 0;
                box-shadow: none;
                border: none;
                border-top: 1px solid #e9ecef;
                border-left: 1px solid #e9ecef;
                min-height: 150px; /* Gün kutularına minimum yükseklik */
                transition: background-color 0.2s ease;
            }
            
            /* Grid kenarlıklarını temizle */
            .calendar-view .day-card:nth-child(7n + 1) {
                border-left: none; 
            }
            .calendar-view .day-card:nth-child(-n + 7) {
                border-top: none;
            }
            
            /* Hover efektlerini kaldır/azalt */
            .card:hover, .admin-panel:hover {
                transform: none;
                box-shadow: var(--card-shadow);
            }
            .day-card:hover {
                transform: none;
                box-shadow: none;
                background-color: #f8fafc;
            }
            
            /* Gün Başlığını Küçült */
            .day-header {
                font-weight: 600;
                padding: 0.5rem;
                background: none; /* Arkaplanı kaldır */
                color: #334155; /* Normal metin rengi */
                font-size: 0.85rem;
                border-bottom: none; /* Kenarlığı kaldır */
            }

            /* Hafta sonu/Tatil renklerini ayarla */
            .day-card.weekend .day-header-content .day-number {
                color: var(--secondary-color);
            }
            .day-card.holiday .day-header-content .day-number {
                color: #b45309; /* Koyu turuncu */
                font-weight: 700;
            }

            /* Mobildeki tam tarihi gizle */
            .day-header-content .day-full-date {
                display: none;
            }
            /* Masaüstünde gün numarasını göster */
            .day-header-content .day-number {
                display: block;
                font-size: 1.1rem;
                font-weight: 600;
            }
            
            /* Başlıktaki rozetleri gizle (renk yeterli) - DÜZELTME: Tatil etiketleri artık gösteriliyor */
            .day-header-content .badge {
                /* display: none; */
            }

            /* İçeriği küçült */
            .day-card .day-content {
                padding: 0.5rem;
                flex-grow: 1;
            }
            
            /* "Etkinlik yok" yazısını gizle */
            .day-card.no-events .day-content .text-muted {
                display: none;
            }
            
            /* Boş gün kutuları */
            .day-card.empty {
                background-color: #f8fafc;
                min-height: auto;
            }
        }
/* GÜNCELLENDİ: Masaüstü için gün numarası (varsayılan gizli) */
        .day-header-content .day-number {
            display: none;
        }

        /* GÜNCELLENDİ: Mobildeki başlık (varsayılan görünür) */
        .day-header-content .day-full-date {
            display: block;
        }

        
        /* GÖLGELENDİRME EKLENTİSİ: Etkinlik kutularının daha belirgin görünmesi için */
        .day-card {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1), 0 2px 4px rgba(0, 0, 0, 0.08) !important;
            transition: all 0.3s ease;
        }
        
        .day-card:hover {
            box-shadow: 0 8px 16px rgba(0, 0, 0, 0.15), 0 4px 8px rgba(0, 0, 0, 0.12) !important;
            transform: translateY(-2px);
        }
        
        .event-item {
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.05) !important;
            border-radius: 0.4rem;
            margin-bottom: 0.75rem;
            transition: all 0.2s ease;
        }
        
        .event-item:hover {
            box-shadow: 0 4px 8px rgba(0, 0, 0, 0.08) !important;
        }
        
        /* Takvim grid görünümü için gölgelendirme ayarı */
        @media (min-width: 992px) {
            .calendar-view .day-card {
                box-shadow: 0 2px 4px rgba(0, 0, 0, 0.06) !important;
            }
            
            .calendar-view .day-card:hover {
                box-shadow: 0 4px 8px rgba(0, 0, 0, 0.1) !important;
            }
        }

    
        
        /* TABLO ÇERÇEVE EKLENTİSİ: Tüm tablolara siyah çerçeve */
        .table {
            border: 2px solid #000000 !important;
            border-collapse: separate !important;
            border-radius: 8px !important;
            overflow: hidden !important;
        }
        
        .table th {
            border: 1px solid #000000 !important;
            background-color: var(--primary-color) !important;
            color: white !important;
            padding: 12px !important;
        }
        
        .table td {
            border: 1px solid #000000 !important;
            padding: 10px !important;
        }
        
        .table tbody tr:nth-child(even) {
            background-color: #f8f9fa !important;
        }
        
        .table tbody tr:hover {
            background-color: #e9ecef !important;
        }
        
        .table-responsive {
            border: 2px solid #000000 !important;
            border-radius: 8px !important;
        }
        
    
    </style>
</head>
<body>
    <nav class="navbar navbar-expand-lg navbar-dark">
        <div class="container">
            <a class="navbar-brand" href="?page=index">
                <span class="brand-title">
                    <i class="fas fa-landmark"></i>
                    <span class="brand-name">Çeşme Belediyesi Kültür Müdürlüğü</span>
                </span>
                <span class="brand-subtitle">Etkinlik Takip Uygulaması</span>
            </a>
            <button class="navbar-toggler" type="button" data-bs-toggle="collapse" data-bs-target="#navbarNav">
                <span class="navbar-toggler-icon"></span>
            </button>
            <div class="collapse navbar-collapse" id="navbarNav">
                <ul class="navbar-nav ms-auto">
                    <li class="nav-item">
                        <a class="nav-link <?php echo $page === 'index' ? 'active' : ''; ?>" href="?page=index">Ana Sayfa</a>
                    </li>
                    <?php if(is_admin()): ?>
                        <li class="nav-item">
                            <a class="nav-link <?php echo $page === 'admin' ? 'active' : ''; ?>" href="?page=admin">Admin Paneli</a>
                        </li>
                        <li class="nav-item">
                            <span class="nav-link admin-badge">
                                <i class="fas fa-user me-1"></i><?php echo htmlspecialchars($_SESSION['admin_user']['username']); ?>
                            </span>
                        </li>
                        <li class="nav-item">
                            <form method="post" class="d-inline">
                                <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                                <button type="submit" name="admin_logout" class="btn btn-outline-light btn-sm">Çıkış</button>
                            </form>
                        </li>
                    <?php else: ?>
                        <li class="nav-item">
                            <a class="nav-link" href="#" data-bs-toggle="modal" data-bs-target="#loginModal">Admin Girişi</a>
                        </li>
                    <?php endif; ?>
                </ul>
            </div>
        </div>
    </nav>

    <?php
    // Index sayfası içeriği
    if ($page === 'index') {
        $csrf_token = generateCSRFToken();
        $recent_updates = [];
        try {
            // YENİ: Aktif duyuruları getir
            $announcements_sql = "SELECT a.*, au.full_name
                                FROM announcements a
                                JOIN admin_users au ON a.admin_user_id = au.id
                                WHERE (a.start_date IS NULL OR a.start_date <= NOW())
                                AND (a.end_date IS NULL OR a.end_date >= NOW())
                                ORDER BY a.created_at DESC";
            $announcements_stmt = $pdo->prepare($announcements_sql);
            $announcements_stmt->execute();
            $announcements = $announcements_stmt->fetchAll(PDO::FETCH_ASSOC);

            if ($updates_enabled) {
                $updates_limit = max(1, (int) $updates_max_count);
                $updates_sql = "SELECT eu.*, e.event_name, e.event_date, u.unit_name
                                FROM event_updates eu
                                LEFT JOIN events e ON eu.event_id = e.id
                                LEFT JOIN units u ON e.unit_id = u.id
                                WHERE eu.expire_at >= NOW()
                                ORDER BY eu.created_at DESC
                                LIMIT $updates_limit";
                $updates_stmt = $pdo->prepare($updates_sql);
                $updates_stmt->execute();
                $recent_updates = $updates_stmt->fetchAll(PDO::FETCH_ASSOC);
            }

            $sql = "SELECT * FROM units WHERE is_active = TRUE ORDER BY unit_name";
            $stmt = $pdo->prepare($sql);
            $stmt->execute();
            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch(PDOException $e) {
            die("Veriler getirilirken hata oluştu. Lütfen yönetici ile iletişime geçin.");
        }
        $selected_unit = $unit_id;
        $search_term = isset($_GET['search']) ? clean_input($_GET['search']) : '';
        try {
            $sql = "SELECT e.*, u.unit_name, u.color
                    FROM events e
                    JOIN units u ON e.unit_id = u.id";
            $params = [];
            $where_clauses = [];
            
            // HATA DÜZELTMESİ BURADA BAŞLIYOR
            if (!empty($search_term)) {
                $where_clauses[] = "(e.event_name LIKE :search1
                                   OR e.contact_info LIKE :search2
                                   OR e.notes LIKE :search3
                                   OR e.event_time LIKE :search4
                                   OR u.unit_name LIKE :search5)";
                $search_value = "%$search_term%";
                $params[':search1'] = $search_value;
                $params[':search2'] = $search_value;
                $params[':search3'] = $search_value;
                $params[':search4'] = $search_value;
                $params[':search5'] = $search_value;
            }
            // HATA DÜZELTMESİ BURADA BİTİYOR
            else {
                $where_clauses[] = "u.id = :unit_id";
                $params[':unit_id'] = $selected_unit;
                $where_clauses[] = "YEAR(event_date) = :year";
                $params[':year'] = $selected_year;
                $where_clauses[] = "MONTH(event_date) = :month";
                $params[':month'] = $selected_month;
            }
            if (!empty($where_clauses)) {
                $sql .= " WHERE " . implode(" AND ", $where_clauses);
            }
            $sql .= " ORDER BY e.event_date, e.event_time";
            $stmt = $pdo->prepare($sql);
            $stmt->execute($params);
            $events = $stmt->fetchAll(PDO::FETCH_ASSOC);
            $grouped_events = [];
            foreach ($events as $event) {
                $date = $event['event_date'];
                $grouped_events[$date][] = $event;
            }
        } catch(PDOException $e) {
            die("Veritabanı hatası: " . $e->getMessage());
        }
        ?>
        <div class="container mt-4">
            <?php if ($maintenance_mode_enabled): ?>
                <div class="alert alert-warning maintenance-alert" role="alert">
                    <h5 class="mb-1"><i class="fas fa-tools me-2"></i>Bakım Modu Aktif</h5>
                    <p class="mb-0">Sitemizde planlı bakım çalışması yapılmaktadır. Bu süre boyunca ana sayfadaki içerik geçici olarak kapatılmıştır. Anlayışınız için teşekkür ederiz.</p>
                    <?php if (is_admin()): ?>
                        <p class="mb-0 mt-2 small text-muted">Not: Bakım modunu Admin Paneli &gt; Ayarlar bölümünden devre dışı bırakabilirsiniz.</p>
                    <?php endif; ?>
                </div>
            <?php else: ?>
                <?php if (isset($error) && $error): ?>
                    <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
                <?php endif; ?>
                <?php if (isset($message) && $message): ?>
                    <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
                <?php endif; ?>
                
                <?php // YENİ: Duyurular Bölümü ?>
                <?php if (!empty($announcements)): ?>
                    <div class="announcement-section">
                        <div class="accordion" id="announcementsAccordion">
                            <div class="accordion-item">
                                <h2 class="accordion-header" id="headingOne">
                                    <button class="accordion-button" type="button" data-bs-toggle="collapse" data-bs-target="#collapseAnnouncements" aria-expanded="true" aria-controls="collapseAnnouncements">
                                        <i class="fas fa-bullhorn me-2"></i> Önemli Duyurular (<?php echo count($announcements); ?>)
                                    </button>
                                </h2>
                                <div id="collapseAnnouncements" class="accordion-collapse collapse show" aria-labelledby="headingOne" data-bs-parent="#announcementsAccordion">
                                    <div class="accordion-body">
                                        <?php foreach ($announcements as $announcement): ?>
                                            <div class="p-2 mb-2 border-bottom">
                                                <p class="mb-1">
                                                    <?php 
                                                    // 48 saatten daha yeni ise "Yeni" etiketi göster
                                                    if (strtotime($announcement['created_at']) > time() - (48 * 3600)) {
                                                        echo '<span class="badge bg-danger badge-new">YENİ</span>';
                                                    }
                                                    ?>
                                                    <?php echo nl2br(htmlspecialchars($announcement['content'])); ?>
                                                </p>
                                                <div class="announcement-meta">
                                                    <span>Yayınlanma: <?php echo turkish_date('d M Y, H:i', strtotime($announcement['created_at'])); ?></span>
                                                    <?php if ($announcement['show_author'] && !empty($announcement['full_name'])): ?>
                                                        <span> | Yazar: <?php echo htmlspecialchars($announcement['full_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>

                <?php if ($updates_enabled): ?>
                    <div class="card mb-4 recent-updates">
                        <div class="card-body">
                            <div class="d-flex align-items-center justify-content-between mb-2">
                                <h5 class="card-title mb-0"><i class="fas fa-clock me-2"></i>Son Etkinlik Güncellemeleri</h5>
                                <span class="badge bg-secondary">Son <?php echo (int) $updates_max_count; ?> kayıt</span>
                            </div>
                            <?php if (!empty($recent_updates)): ?>
                                <ul class="list-group list-group-flush">
                                    <?php foreach ($recent_updates as $update): ?>
                                        <li class="list-group-item d-flex justify-content-between align-items-start">
                                            <div class="me-3">
                                                <?php
                                                    $badge_class = 'badge-update-updated';
                                                    $badge_text = 'Güncelleme';
                                                    if ($update['update_type'] === 'new') {
                                                        $badge_class = 'badge-update-new';
                                                        $badge_text = 'Yeni';
                                                    } elseif ($update['update_type'] === 'cancelled') {
                                                        $badge_class = 'badge-update-cancelled';
                                                        $badge_text = 'İptal';
                                                    }
                                                ?>
                                                <span class="badge <?php echo $badge_class; ?> me-2"><?php echo $badge_text; ?></span>
                                                <span class="fw-semibold"><?php echo htmlspecialchars($update['message_text']); ?></span>
                                                <?php if (!empty($update['unit_name'])): ?>
                                                    <div class="text-muted small mt-1">
                                                        <i class="fas fa-building me-1"></i><?php echo htmlspecialchars($update['unit_name']); ?>
                                                    </div>
                                                <?php endif; ?>
                                            </div>
                                            <small class="text-muted">
                                                <?php echo turkish_date('d M Y, H:i', strtotime($update['created_at'])); ?>
                                            </small>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            <?php else: ?>
                                <div class="alert alert-info mb-0">
                                    Belirtilen süre içinde gösterilecek güncelleme bulunmuyor.
                                </div>
                            <?php endif; ?>
                            <p class="text-muted small mt-3 mb-0">
                                Son <?php echo (int) $updates_duration; ?> <?php echo $updates_duration_unit === 'days' ? 'gün' : 'saat'; ?> içindeki değişiklikler listelenir.
                            </p>
                        </div>
                    </div>
                <?php endif; ?>

                <div class="row mb-4">
                    <div class="col-md-6">
                        <div class="unit-selector card">
                            <div class="card-body">
                                <h5 class="card-title">Birim Seçimi</h5>
                                <form method="get" accept-charset="UTF-8">
                                    <select class="form-select" name="unit_id" onchange="this.form.submit()">
                                        <?php foreach ($units as $unit): ?>
                                            <option value="<?php echo $unit['id']; ?>" <?php echo $selected_unit == $unit['id'] ? 'selected' : ''; ?>>
                                                <?php echo htmlspecialchars($unit['unit_name']); ?>
                                            </option>
                                        <?php endforeach; ?>
                                    </select>
                                    <input type="hidden" name="month" value="<?php echo $selected_month; ?>">
                                    <input type="hidden" name="year" value="<?php echo $selected_year; ?>">
                                </form>
                            </div>
                        </div>
                    </div>
                    <div class="col-md-6">
                        <div class="month-selector card">
                            <div class="card-body">
                                <h5 class="card-title">Ay Seçimi</h5>
                                <form method="get" id="monthForm" accept-charset="UTF-8">
                                    <div class="input-group">
                                        <select class="form-select" id="monthSelect" name="month" onchange="this.form.submit()">
                                            <?php for ($m = 1; $m <= 12; $m++): ?>
                                                <option value="<?php echo $m; ?>" <?php echo $selected_month == $m ? 'selected' : ''; ?>>
                                                    <?php echo $turkish_months[$m]; ?>
                                                </option>
                                            <?php endfor; ?>
                                        </select>
                                        <input type="number" class="form-control" name="year" value="<?php echo $selected_year; ?>" min="2000" max="2100">
                                        <button class="btn btn-primary" type="submit">Git</button>
                                    </div>
                                    <input type="hidden" name="unit_id" value="<?php echo $selected_unit; ?>">
                                </form>
                            </div>
                        </div>
                    </div>
                </div>

                <div class="card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Etkinlik Ara</h5>
                        <form method="get" accept-charset="UTF-8">
                            <div class="input-group">
                                <input type="text" class="form-control" name="search" value="<?php echo htmlspecialchars($search_term); ?>" placeholder="Etkinlik adı, iletişim, notlar...">
                                <button class="btn btn-primary" type="submit">Ara</button>
                                <?php if (!empty($search_term)): ?>
                                    <a href="?unit_id=<?php echo $selected_unit; ?>&month=<?php echo $selected_month; ?>&year=<?php echo $selected_year; ?>" class="btn btn-secondary">Aramayı Temizle</a>
                                <?php endif; ?>
                            </div>
                        </form>
                    </div>
                </div>

                <div class="legend card mb-4">
                    <div class="card-body">
                        <h5 class="card-title">Renk Anlamları</h5>
                        <div class="d-flex flex-wrap gap-3">
                            <?php // YENİ: Dinamik efsane (legend) ?>
                            <?php foreach ($all_event_statuses as $key => $status): ?>
                                <span class="badge badge-status-<?php echo $key; ?>"><?php echo htmlspecialchars($status['display_name']); ?></span>
                            <?php endforeach; ?>
                            <?php foreach ($all_payment_statuses as $key => $status): ?>
                                <span class="badge badge-payment-<?php echo $key; ?>"><?php echo htmlspecialchars($status['display_name']); ?></span>
                            <?php endforeach; ?>
                        </div>
                    </div>
                </div>

                <div class="calendar-header" aria-hidden="true">
                    <div class="calendar-header-day">Pazartesi</div>
                    <div class="calendar-header-day">Salı</div>
                    <div class="calendar-header-day">Çarşamba</div>
                    <div class="calendar-header-day">Perşembe</div>
                    <div class="calendar-header-day">Cuma</div>
                    <div class="calendar-header-day">Cumartesi</div>
                    <div class="calendar-header-day">Pazar</div>
                </div>

                <div class="calendar-view">
                    <?php
                    if (!empty($search_term)) {
                        // ARAMA SONUÇLARI (DEĞİŞMEDİ)
                        // Arama sonuçları her zaman dikey liste olarak kalır
                        if (empty($grouped_events)) {
                            echo '<div class="alert alert-info w-100">Arama kriterlerinize uygun etkinlik bulunamadı.</div>';
                        } else {
                            ksort($grouped_events);
                            foreach ($grouped_events as $date => $day_events):
                                $is_holiday = is_holiday($date, $pdo);
                                $is_weekend = is_weekend($date);
                                $class = '';
                                if ($is_holiday) $class = 'holiday';
                                elseif ($is_weekend) $class = 'weekend';
                                if (empty($day_events)) $class .= ' no-events';
                    ?>
                                <div class="day-card <?php echo $class; ?>">
                                    <div class="day-header">
                                        <div class="day-header-content">
                                            <span class="day-full-date">
                                                <?php echo turkish_date('d M Y, l', strtotime($date)); ?>
                                            </span>
                                            <span class="day-number"><?php echo (int)date('d', strtotime($date)); ?></span>

                                            <?php if ($is_holiday): ?>
                                                <span class="badge bg-warning ms-2"><?php echo htmlspecialchars($is_holiday['holiday_name']); ?></span>
                                            <?php elseif ($is_weekend): ?>
                                                <span class="badge bg-info ms-2">Hafta Sonu</span>
                                            <?php endif; ?>
                                        </div>
                                        <?php if (is_admin()): ?>
                                            <div class="day-header-actions">
                                                <button class="btn-add-day" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="newEventForDay('<?php echo $date; ?>', '<?php echo $selected_unit; ?>')">
                                                    <i class="fas fa-plus"></i>
                                                </button>
                                            </div>
                                        <?php endif; ?>
                                    </div>
                                    <div class="day-content">
                                        <?php foreach ($day_events as $event): ?>
                                            <div class="event-item" style="border-left: 4px solid <?php echo htmlspecialchars($event['color'] ?? '#ccc'); ?>;">
                                                <h6><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></h6>
                                                <p class="event-time"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($event['event_time'] ?? ''); ?></p>
                                                <?php if (!empty($event['contact_info'])): ?>
                                                    <p class="event-contact"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($event['contact_info']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($event['notes'])): ?>
                                                    <p class="event-notes"><i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($event['notes']); ?></p>
                                                <?php endif; ?>
                                                <div class="event-status">
                                                    <?php // YENİ: Dinamik durum etiketleri ?>
                                                    <?php
                                                        $event_status = $event['status'] ?? '';
                                                        $status_data = $all_event_statuses[$event_status] ?? null;
                                                        if ($status_data):
                                                    ?>
                                                        <span class="badge badge-status-<?php echo $event_status; ?>"><?php echo htmlspecialchars($status_data['display_name']); ?></span>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    $event_payment_status = $event['payment_status'] ?? '';
                                                    $payment_data = $all_payment_statuses[$event_payment_status] ?? null;
                                                    if ($event_status !== 'free' && $event_status !== 'cancelled' && $payment_data):
                                                    ?>
                                                        <span class="badge badge-payment-<?php echo $event_payment_status; ?>"><?php echo htmlspecialchars($payment_data['display_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (is_admin()): ?>
                                                    <button class="btn-edit-quick" data-bs-toggle="modal" data-bs-target="#eventModal"
                                                            data-event-id="<?php echo $event['id']; ?>"
                                                            data-unit-id="<?php echo $event['unit_id']; ?>"
                                                            data-event-date="<?php echo $event['event_date']; ?>"
                                                            data-event-name="<?php echo htmlspecialchars($event['event_name'] ?? ''); ?>"
                                                            data-event-time="<?php echo htmlspecialchars($event['event_time'] ?? ''); ?>"
                                                            data-event-contact="<?php echo htmlspecialchars($event['contact_info'] ?? ''); ?>"
                                                            data-event-notes="<?php echo htmlspecialchars($event['notes'] ?? ''); ?>"
                                                            data-event-status="<?php echo $event['status'] ?? ''; ?>"
                                                            data-event-payment="<?php echo $event['payment_status'] ?? ''; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-delete-quick" onclick="confirmDelete(<?php echo $event['id']; ?>, this, '<?php echo $csrf_token; ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    </div>
                                </div>
                    <?php
                            endforeach;
                        }
                    } else {
                        // TAKVİM GÖRÜNÜMÜ (GÜNCELLENDİ)
                        $days_in_month = cal_days_in_month(CAL_GREGORIAN, $selected_month, $selected_year);
                        $first_day_timestamp = strtotime("$selected_year-$selected_month-01");
                        $start_day_of_week = (int)date('N', $first_day_timestamp); // 1 (Pzt) - 7 (Paz)

                        // 1. Ayın ilk gününden önceki boş günler (Pazartesi 1 ise)
                        for ($i = 1; $i < $start_day_of_week; $i++) {
                            echo '<div class="day-card empty" aria-hidden="true"></div>';
                        }
                        
                        // 2. Ayın günleri
                        for ($day = 1; $day <= $days_in_month; $day++) {
                            $date = sprintf("%04d-%02d-%02d", $selected_year, $selected_month, $day);
                            $day_events = $grouped_events[$date] ?? [];
                            $is_holiday = is_holiday($date, $pdo);
                            $is_weekend = is_weekend($date);
                            $class = '';
                            if ($is_holiday) $class = 'holiday';
                            elseif ($is_weekend) $class = 'weekend';
                            if (empty($day_events)) $class .= ' no-events';
                    ?>
                            <div class="day-card <?php echo $class; ?>">
                                <div class="day-header">
                                    <div class="day-header-content">
                                        <span class="day-full-date">
                                            <?php echo turkish_date('d M Y, l', strtotime($date)); ?>
                                        </span>
                                        <span class="day-number"><?php echo $day; ?></span>
                                        
                                        <?php if ($is_holiday): ?>
                                            <span class="badge bg-warning ms-2"><?php echo htmlspecialchars($is_holiday['holiday_name']); ?></span>
                                        <?php elseif ($is_weekend): ?>
                                            <span class="badge bg-info ms-2">Hafta Sonu</span>
                                        <?php endif; ?>
                                    </div>
                                    <?php if (is_admin()): ?>
                                        <div class="day-header-actions">
                                            <button class="btn-add-day" data-bs-toggle="modal" data-bs-target="#eventModal"
                                                    onclick="newEventForDay('<?php echo $date; ?>', '<?php echo $selected_unit; ?>')">
                                                <i class="fas fa-plus"></i>
                                            </button>
                                        </div>
                                    <?php endif; ?>
                                </div>
                                <div class="day-content">
                                    <?php if (empty($day_events)): ?>
                                        <p class="text-muted text-center my-4">Etkinlik yok</p>
                                    <?php else: ?>
                                        <?php foreach ($day_events as $event): ?>
                                            <div class="event-item" style="border-left: 4px solid <?php echo htmlspecialchars($event['color'] ?? '#ccc'); ?>;">
                                                <h6><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></h6>
                                                <p class="event-time"><i class="fas fa-clock me-1"></i><?php echo htmlspecialchars($event['event_time'] ?? ''); ?></p>
                                                <?php if (!empty($event['contact_info'])): ?>
                                                    <p class="event-contact"><i class="fas fa-phone me-1"></i><?php echo htmlspecialchars($event['contact_info']); ?></p>
                                                <?php endif; ?>
                                                <?php if (!empty($event['notes'])): ?>
                                                    <p class="event-notes"><i class="fas fa-sticky-note me-1"></i><?php echo htmlspecialchars($event['notes']); ?></p>
                                                <?php endif; ?>
                                                <div class="event-status">
                                                    <?php // YENİ: Dinamik durum etiketleri ?>
                                                    <?php
                                                        $event_status = $event['status'] ?? '';
                                                        $status_data = $all_event_statuses[$event_status] ?? null;
                                                        if ($status_data):
                                                    ?>
                                                        <span class="badge badge-status-<?php echo $event_status; ?>"><?php echo htmlspecialchars($status_data['display_name']); ?></span>
                                                    <?php endif; ?>
                                                    
                                                    <?php 
                                                    $event_payment_status = $event['payment_status'] ?? '';
                                                    $payment_data = $all_payment_statuses[$event_payment_status] ?? null;
                                                    if ($event_status !== 'free' && $event_status !== 'cancelled' && $payment_data):
                                                    ?>
                                                        <span class="badge badge-payment-<?php echo $event_payment_status; ?>"><?php echo htmlspecialchars($payment_data['display_name']); ?></span>
                                                    <?php endif; ?>
                                                </div>
                                                <?php if (is_admin()): ?>
                                                    <button class="btn-edit-quick" data-bs-toggle="modal" data-bs-target="#eventModal"
                                                            data-event-id="<?php echo $event['id']; ?>"
                                                            data-unit-id="<?php echo $event['unit_id']; ?>"
                                                            data-event-date="<?php echo $event['event_date']; ?>"
                                                            data-event-name="<?php echo htmlspecialchars($event['event_name'] ?? ''); ?>"
                                                            data-event-time="<?php echo htmlspecialchars($event['event_time'] ?? ''); ?>"
                                                            data-event-contact="<?php echo htmlspecialchars($event['contact_info'] ?? ''); ?>"
                                                            data-event-notes="<?php echo htmlspecialchars($event['notes'] ?? ''); ?>"
                                                            data-event-status="<?php echo $event['status'] ?? ''; ?>"
                                                            data-event-payment="<?php echo $event['payment_status'] ?? ''; ?>">
                                                        <i class="fas fa-edit"></i>
                                                    </button>
                                                    <button class="btn-delete-quick" onclick="confirmDelete(<?php echo $event['id']; ?>, this, '<?php echo $csrf_token; ?>')">
                                                        <i class="fas fa-trash"></i>
                                                    </button>
                                                <?php endif; ?>
                                            </div>
                                        <?php endforeach; ?>
                                    <?php endif; ?>
                                </div>
                            </div>
                    <?php
                        } // for $day
                        
                        // 3. Ayın son gününden sonraki boş günler (Pazar 7 ise)
                        $last_day_of_week = (int)date('N', strtotime("$selected_year-$selected_month-$days_in_month"));
                        if ($last_day_of_week < 7) {
                            for ($i = $last_day_of_week; $i < 7; $i++) {
                                echo '<div class="day-card empty" aria-hidden="true"></div>';
                            }
                        }
                    } // end if $search_term
                    ?>
                </div>
            <?php endif; ?>
        </div>
    <?php
    } elseif ($page === 'admin') {
        if (!is_admin()) {
            header("Location: ?page=index");
            exit;
        }
        $tab = isset($_GET['tab']) ? clean_input($_GET['tab']) : 'events';
        $allowed_tabs = ['events', 'units', 'holidays', 'announcements', 'reports', 'users', 'settings'];
        if (!in_array($tab, $allowed_tabs, true)) {
            $tab = 'events';
        }
        // YENİ: Admin paneli etkinlik arama ve filtreleme terimleri
        $search_admin_term = isset($_GET['search_admin']) ? clean_input($_GET['search_admin']) : '';
        $filter_unit_id = filter_input(INPUT_GET, 'filter_unit_id', FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
        if ($filter_unit_id === false || $filter_unit_id === null) {
            $filter_unit_id = 0;
        }
        $filter_start_date = isset($_GET['filter_start_date']) ? clean_input($_GET['filter_start_date']) : '';
        if (!empty($filter_start_date) && !is_valid_date_string($filter_start_date)) {
            $filter_start_date = '';
        }
        $filter_end_date = isset($_GET['filter_end_date']) ? clean_input($_GET['filter_end_date']) : '';
        if (!empty($filter_end_date) && !is_valid_date_string($filter_end_date)) {
            $filter_end_date = '';
        }
        $filter_status = isset($_GET['filter_status']) ? clean_input($_GET['filter_status']) : '';
        $filter_payment_status = isset($_GET['filter_payment_status']) ? clean_input($_GET['filter_payment_status']) : '';

        // VARSAYILAN: Yönetici tablosunda yalnızca mevcut ayın etkinliklerini göster
        if (empty($filter_start_date) && empty($filter_end_date)) {
            $filter_start_date = date('Y-m-01');
            $filter_end_date = date('Y-m-t');
        }
        
        $csrf_token = generateCSRFToken();
    ?>
        <div class="container mt-4">
            <?php if (isset($error) && $error): ?>
                <div class="alert alert-danger"><?php echo htmlspecialchars($error); ?></div>
            <?php endif; ?>
            <?php if (isset($message) && $message): ?>
                <div class="alert alert-success"><?php echo htmlspecialchars($message); ?></div>
            <?php endif; ?>

            <div class="welcome-message">
                <h2 class="mb-3">Hoş Geldiniz, <?php echo htmlspecialchars($_SESSION['admin_user']['full_name'] ?? $_SESSION['admin_user']['username']); ?></h2>
                <p class="mb-0">Admin panelinden etkinlikleri, birimleri ve tatilleri yönetebilirsiniz.</p>
            </div>
            <ul class="nav nav-tabs mb-4">
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'events' ? 'active' : ''; ?>" href="?page=admin&tab=events">Etkinlik Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'units' ? 'active' : ''; ?>" href="?page=admin&tab=units">Birim Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'holidays' ? 'active' : ''; ?>" href="?page=admin&tab=holidays">Tatil Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'announcements' ? 'active' : ''; ?>" href="?page=admin&tab=announcements">Duyuru Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'reports' ? 'active' : ''; ?>" href="?page=admin&tab=reports">Raporlama</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'users' ? 'active' : ''; ?>" href="?page=admin&tab=users">Kullanıcı Yönetimi</a></li>
                <li class="nav-item"><a class="nav-link <?php echo $tab === 'settings' ? 'active' : ''; ?>" href="?page=admin&tab=settings">Ayarlar</a></li> <?php // YENİ SEKME ?>
            </ul>
            <div class="admin-panel card">
                <div class="card-body">
                    <?php if ($tab === 'events'): ?>
                        <h5 class="card-title">Etkinlik Yönetimi</h5>
                        <div class="d-flex justify-content-between align-items-center mb-3">
                             <button class="btn btn-primary" data-bs-toggle="modal" data-bs-target="#eventModal" onclick="newEvent()">
                                <i class="fas fa-plus me-1"></i>Yeni Etkinlik Ekle
                            </button>
                        </div>

                        <div class="report-filters mb-4">
                            <form method="get" accept-charset="UTF-8">
                                <input type="hidden" name="page" value="admin">
                                <input type="hidden" name="tab" value="events">
                                <div class="row g-3">
                                    <div class="col-md-3">
                                        <label for="filter_unit_id" class="form-label">Birim</label>
                                        <select class="form-select" id="filter_unit_id" name="filter_unit_id">
                                            <option value="">Tüm Birimler</option>
                                            <?php
                                            try {
                                                $units_stmt = $pdo->prepare("SELECT id, unit_name FROM units WHERE is_active = TRUE ORDER BY unit_name");
                                                $units_stmt->execute();
                                                $all_units_filter = $units_stmt->fetchAll(PDO::FETCH_ASSOC);
                                                foreach ($all_units_filter as $unit):
                                            ?>
                                                <option value="<?php echo $unit['id']; ?>" <?php echo ($filter_unit_id == $unit['id']) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($unit['unit_name']); ?>
                                                </option>
                                            <?php 
                                                endforeach; 
                                            } catch(PDOException $e) {
                                                echo '<option value="">Birimler yüklenemedi</option>';
                                            }
                                            ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_start_date" class="form-label">Başlangıç Tarihi</label>
                                        <input type="date" class="form-control" id="filter_start_date" name="filter_start_date" value="<?php echo htmlspecialchars($filter_start_date); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_end_date" class="form-label">Bitiş Tarihi</label>
                                        <input type="date" class="form-control" id="filter_end_date" name="filter_end_date" value="<?php echo htmlspecialchars($filter_end_date); ?>">
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_status" class="form-label">Durum</label>
                                        <?php // YENİ: Dinamik Durum Filtresi ?>
                                        <select class="form-select" id="filter_status" name="filter_status">
                                            <option value="">Tümü</option>
                                            <?php foreach ($all_event_statuses as $key => $status): ?>
                                                <option value="<?php echo $key; ?>" <?php echo ($filter_status == $key) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status['display_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="filter_payment_status" class="form-label">Ödeme Durumu</label>
                                        <?php // YENİ: Dinamik Ödeme Filtresi ?>
                                        <select class="form-select" id="filter_payment_status" name="filter_payment_status">
                                            <option value="">Tümü</option>
                                            <?php foreach ($all_payment_statuses as $key => $status): ?>
                                                <option value="<?php echo $key; ?>" <?php echo ($filter_payment_status == $key) ? 'selected' : ''; ?>>
                                                    <?php echo htmlspecialchars($status['display_name']); ?>
                                                </option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-6">
                                        <label for="search_admin" class="form-label">Arama (Etkinlik, birim, iletişim...)</label>
                                        <input type="text" name="search_admin" id="search_admin" class="form-control" placeholder="Arama terimi..." value="<?php echo htmlspecialchars($search_admin_term); ?>">
                                    </div>
                                    <div class="col-md-3 d-flex align-items-end">
                                        <button class="btn btn-primary w-100 me-2" type="submit"><i class="fas fa-filter"></i> Filtrele</button>
                                        <a href="?page=admin&tab=events" class="btn btn-secondary w-100" title="Filtreleri Temizle"><i class="fas fa-times"></i> Temizle</a>
                                    </div>
                                </div>
                            </form>
                        </div>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tarih</th>
                                        <th>Birim</th>
                                        <th>Etkinlik Adı</th>
                                        <th>Saat</th>
                                        <th>İletişim</th>
                                        <th>Durum</th>
                                        <th>Ödeme</th>
                                        <th class="actions">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        // GÜNCELLEME: Arama ve filtreleme terimlerine göre filtreleme yap
                                        $params_admin = [];
                                        $sql = "SELECT e.*, u.unit_name
                                                FROM events e
                                                JOIN units u ON e.unit_id = u.id";
                                        
                                        $where_clauses_admin = [];

                                        // *** BAŞLANGIÇ: Düzeltilen Kısım ***
                                        if (!empty($search_admin_term)) {
                                            // DÜZELTME: Her LIKE için benzersiz bir ad-değişken (placeholder) kullanılmalı.
                                            $where_clauses_admin[] = "(e.event_name LIKE :search1 OR u.unit_name LIKE :search2 OR e.contact_info LIKE :search3 OR e.notes LIKE :search4)";
                                            $search_value = "%$search_admin_term%";
                                            $params_admin[':search1'] = $search_value;
                                            $params_admin[':search2'] = $search_value;
                                            $params_admin[':search3'] = $search_value;
                                            $params_admin[':search4'] = $search_value;
                                        }
                                        // *** BİTİŞ: Düzeltilen Kısım ***

                                        if (!empty($filter_unit_id)) {
                                            $where_clauses_admin[] = "e.unit_id = :unit_id";
                                            $params_admin[':unit_id'] = $filter_unit_id;
                                        }
                                        if (!empty($filter_start_date)) {
                                            $where_clauses_admin[] = "e.event_date >= :start_date";
                                            $params_admin[':start_date'] = $filter_start_date;
                                        }
                                        if (!empty($filter_end_date)) {
                                            $where_clauses_admin[] = "e.event_date <= :end_date";
                                            $params_admin[':end_date'] = $filter_end_date;
                                        }
                                        if (!empty($filter_status)) {
                                            $where_clauses_admin[] = "e.status = :status";
                                            $params_admin[':status'] = $filter_status;
                                        }
                                        if (!empty($filter_payment_status)) {
                                            $where_clauses_admin[] = "e.payment_status = :payment_status";
                                            $params_admin[':payment_status'] = $filter_payment_status;
                                        }

                                        if (!empty($where_clauses_admin)) {
                                            $sql .= " WHERE " . implode(" AND ", $where_clauses_admin);
                                        }
                                        
                                        $sql .= " ORDER BY e.event_date DESC, e.event_time";
                                        
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute($params_admin);
                                        $admin_events = $stmt->fetchAll(PDO::FETCH_ASSOC);

                                        foreach ($admin_events as $event):
                                            // YENİ: Durum ve Ödeme metinlerini dinamik olarak al
                                            $event_status = $event['status'] ?? '';
                                            $status_data = $all_event_statuses[$event_status] ?? null;
                                            $status_text = $status_data ? htmlspecialchars($status_data['display_name']) : $event_status;
                                            
                                            $payment_text = '-';
                                            $event_payment_status = $event['payment_status'] ?? '';
                                            $payment_data = $all_payment_statuses[$event_payment_status] ?? null;

                                            if ($event_status !== 'free' && $event_status !== 'cancelled' && $payment_data) {
                                                $payment_text = htmlspecialchars($payment_data['display_name']);
                                            }
                                    ?>
                                            <tr>
                                                <td><?php echo $event['id']; ?></td>
                                                <td><?php echo turkish_date('d M Y', strtotime($event['event_date'] ?? 'now')); ?></td>
                                                <td><?php echo htmlspecialchars($event['unit_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($event['event_time'] ?? ''); ?></td>
                                                <td><?php echo htmlspecialchars($event['contact_info'] ?? ''); ?></td>
                                                <td><?php echo $status_text; ?></td>
                                                <td><?php echo $payment_text; ?></td>
                                                <td class="actions">
                                                    <div class="action-buttons d-flex justify-content-center">
                                                        <button class="btn btn-sm btn-primary edit-event" data-bs-toggle="modal" data-bs-target="#eventModal"
                                                                data-event-id="<?php echo $event['id']; ?>"
                                                                data-unit-id="<?php echo $event['unit_id']; ?>"
                                                                data-event-date="<?php echo $event['event_date']; ?>"
                                                                data-event-name="<?php echo htmlspecialchars($event['event_name'] ?? ''); ?>"
                                                                data-event-time="<?php echo htmlspecialchars($event['event_time'] ?? ''); ?>"
                                                                data-event-contact="<?php echo htmlspecialchars($event['contact_info'] ?? ''); ?>"
                                                                data-event-notes="<?php echo htmlspecialchars($event['notes'] ?? ''); ?>"
                                                                data-event-status="<?php echo $event['status'] ?? ''; ?>"
                                                                data-event-payment="<?php echo $event['payment_status'] ?? ''; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu etkinliği silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="event_id" value="<?php echo $event['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <button type="submit" name="delete_event" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php } catch(PDOException $e) { ?>
                                        <tr><td colspan="9">Etkinlikler getirilirken hata oluştu. (Hata: <?php echo $e->getMessage(); ?>)</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($tab === 'units'): ?>
                        <h5 class="card-title">Birim Yönetimi</h5>
                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#unitModal" onclick="newUnit()">
                            <i class="fas fa-plus me-1"></i>Yeni Birim Ekle
                        </button>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Birim Adı</th>
                                        <th>Renk</th>
                                        <th>Aktif</th>
                                        <th class="actions">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM units ORDER BY unit_name";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($units as $unit):
                                    ?>
                                            <tr>
                                                <td><?php echo $unit['id']; ?></td>
                                                <td><?php echo htmlspecialchars($unit['unit_name']); ?></td>
                                                <td><span class="badge" style="background-color: <?php echo $unit['color']; ?>; color: #fff; text-shadow: 1px 1px 1px #000;"><?php echo $unit['color']; ?></span></td>
                                                <td><?php echo $unit['is_active'] ? 'Evet' : 'Hayır'; ?></td>
                                                <td class="actions">
                                                    <div class="action-buttons d-flex justify-content-center">
                                                        <button class="btn btn-sm btn-primary edit-unit" data-bs-toggle="modal" data-bs-target="#unitModal"
                                                                data-unit-id="<?php echo $unit['id']; ?>"
                                                                data-unit-name="<?php echo htmlspecialchars($unit['unit_name']); ?>"
                                                                data-unit-color="<?php echo $unit['color']; ?>"
                                                                data-unit-active="<?php echo $unit['is_active']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu birimi silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="unit_id" value="<?php echo $unit['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <button type="submit" name="delete_unit" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php } catch(PDOException $e) { ?>
                                        <tr><td colspan="5">Birimler getirilirken hata oluştu.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($tab === 'holidays'): ?>
                        <h5 class="card-title">Tatil Yönetimi</h5>
                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#holidayModal" onclick="newHoliday()">
                            <i class="fas fa-plus me-1"></i>Yeni Tatil Ekle
                        </button>
                        <?php $recurring_holidays = get_recurring_holidays_for_year((int) date('Y')); ?>
                        <?php if (!empty($recurring_holidays)): ?>
                            <div class="alert alert-info small" role="alert">
                                <strong>Bilgi:</strong> Yinelenen resmi tatiller takvimde otomatik olarak gösterilir.
                                <ul class="mb-0 mt-2">
                                    <?php foreach ($recurring_holidays as $recurring_date => $recurring_name): ?>
                                        <li>
                                            <?php echo turkish_date('d F', strtotime($recurring_date)); ?> –
                                            <?php echo htmlspecialchars($recurring_name, ENT_QUOTES, 'UTF-8'); ?>
                                        </li>
                                    <?php endforeach; ?>
                                </ul>
                            </div>
                        <?php endif; ?>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Tatil Adı</th>
                                        <th>Tarih</th>
                                        <th class="actions">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM holidays ORDER BY holiday_date";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                        $holidays = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($holidays as $holiday):
                                    ?>
                                            <tr>
                                                <td><?php echo $holiday['id']; ?></td>
                                                <td><?php echo htmlspecialchars($holiday['holiday_name']); ?></td>
                                                <td><?php echo turkish_date('d M Y', strtotime($holiday['holiday_date'])); ?></td>
                                                <td class="actions">
                                                    <div class="action-buttons d-flex justify-content-center">
                                                        <button class="btn btn-sm btn-primary edit-holiday" data-bs-toggle="modal" data-bs-target="#holidayModal"
                                                                data-holiday-id="<?php echo $holiday['id']; ?>"
                                                                data-holiday-name="<?php echo htmlspecialchars($holiday['holiday_name']); ?>"
                                                                data-holiday-date="<?php echo $holiday['holiday_date']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu tatili silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="holiday_id" value="<?php echo $holiday['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <button type="submit" name="delete_holiday" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php } catch(PDOException $e) { ?>
                                        <tr><td colspan="4">Tatiller getirilirken hata oluştu.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php // YENİ: Duyuru Yönetimi Sekmesi ?>
                    <?php elseif ($tab === 'announcements'): ?>
                        <h5 class="card-title">Duyuru Yönetimi</h5>
                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#announcementModal" onclick="newAnnouncement()">
                            <i class="fas fa-plus me-1"></i>Yeni Duyuru Ekle
                        </button>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead style="white-space:normal;">
                                    <tr>
                                        <th>ID</th>
                                        <th style="min-width: 300px;">İçerik</th>
                                        <th>Yazar</th>
                                        <th>Başlangıç Tarihi</th>
                                        <th>Bitiş Tarihi</th>
                                        <th class="actions">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody style="white-space:normal;">
                                    <?php
                                    try {
                                        $sql = "SELECT a.*, au.username, au.full_name FROM announcements a JOIN admin_users au ON a.admin_user_id = au.id ORDER BY a.created_at DESC";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                        $all_announcements = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($all_announcements as $ann):
                                    ?>
                                            <tr>
                                                <td><?php echo $ann['id']; ?></td>
                                                <td><?php echo htmlspecialchars(mb_substr($ann['content'], 0, 100)) . (mb_strlen($ann['content']) > 100 ? '...' : ''); ?></td>
                                                <td>
                                                    <?php echo htmlspecialchars($ann['full_name'] ?: $ann['username']); ?>
                                                    <?php if ($ann['show_author']): ?>
                                                        <span class="badge bg-success">Görünür</span>
                                                    <?php else: ?>
                                                        <span class="badge bg-secondary">Gizli</span>
                                                    <?php endif; ?>
                                                </td>
                                                <td><?php echo $ann['start_date'] ? turkish_date('d M Y H:i', strtotime($ann['start_date'])) : '-'; ?></td>
                                                <td><?php echo $ann['end_date'] ? turkish_date('d M Y H:i', strtotime($ann['end_date'])) : '-'; ?></td>
                                                <td class="actions">
                                                    <div class="action-buttons d-flex justify-content-center">
                                                        <button class="btn btn-sm btn-primary edit-announcement" data-bs-toggle="modal" data-bs-target="#announcementModal"
                                                                data-announcement-id="<?php echo $ann['id']; ?>"
                                                                data-content="<?php echo htmlspecialchars($ann['content']); ?>"
                                                                data-show-author="<?php echo $ann['show_author']; ?>"
                                                                data-start-date="<?php echo $ann['start_date'] ? date('Y-m-d\TH:i', strtotime($ann['start_date'])) : ''; ?>"
                                                                data-end-date="<?php echo $ann['end_date'] ? date('Y-m-d\TH:i', strtotime($ann['end_date'])) : ''; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu duyuruyu silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="announcement_id" value="<?php echo $ann['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <button type="submit" name="delete_announcement" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php } catch(PDOException $e) { ?>
                                        <tr><td colspan="6">Duyurular getirilirken hata oluştu.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php elseif ($tab === 'reports'): ?>
                         <h5 class="card-title">Raporlama</h5>
                        <div class="report-filters">
                            <form method="post" accept-charset="UTF-8">
                                <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                <div class="row mb-3">
                                    <div class="col-md-3">
                                        <label for="start_date" class="form-label">Başlangıç Tarihi</label>
                                        <input type="date" class="form-control" id="start_date" name="start_date" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="end_date" class="form-label">Bitiş Tarihi</label>
                                        <input type="date" class="form-control" id="end_date" name="end_date" required>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="status_filter" class="form-label">Durum Filtresi</label>
                                        <?php // YENİ: Dinamik Durum Filtresi ?>
                                        <select class="form-select" id="status_filter" name="status_filter">
                                            <option value="">Tümü</option>
                                            <?php foreach ($all_event_statuses as $key => $status): ?>
                                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($status['display_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                    <div class="col-md-3">
                                        <label for="payment_filter" class="form-label">Ödeme Filtresi</label>
                                        <?php // YENİ: Dinamik Ödeme Filtresi ?>
                                        <select class="form-select" id="payment_filter" name="payment_filter">
                                            <option value="">Tümü</option>
                                            <?php foreach ($all_payment_statuses as $key => $status): ?>
                                                <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($status['display_name']); ?></option>
                                            <?php endforeach; ?>
                                        </select>
                                    </div>
                                </div>
                                <div class="mb-3">
                                    <label class="form-label">Birim Filtresi</label>
                                    <div class="unit-checkboxes">
                                        <div class="form-check">
                                            <input class="form-check-input" type="checkbox" id="all_units" value="all" name="unit_ids[]" checked>
                                            <label class="form-check-label" for="all_units"><strong>Tüm Birimler</strong></label>
                                        </div>
                                        <?php
                                        try {
                                            $sql = "SELECT * FROM units WHERE is_active = TRUE ORDER BY unit_name";
                                            $stmt = $pdo->prepare($sql);
                                            $stmt->execute();
                                            $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                            foreach ($units as $unit):
                                        ?>
                                                <div class="form-check">
                                                    <input class="form-check-input unit-checkbox" type="checkbox" id="unit_<?php echo $unit['id']; ?>" name="unit_ids[]" value="<?php echo $unit['id']; ?>" checked>
                                                    <label class="form-check-label" for="unit_<?php echo $unit['id']; ?>"><?php echo htmlspecialchars($unit['unit_name']); ?></label>
                                                </div>
                                            <?php endforeach; ?>
                                        <?php } catch(PDOException $e) { ?>
                                            <p>Birimler yüklenemedi.</p>
                                        <?php } ?>
                                    </div>
                                </div>
                                <button type="submit" name="generate_report" value="generate" class="btn btn-primary">
                                    <i class="fas fa-chart-bar me-1"></i>Rapor Oluştur
                                </button>
                            </form>
                        </div>
                        <?php
                        if (isset($_SESSION['report_data'])) {
                            $report_data = $_SESSION['report_data'];
                            $filters = $_SESSION['report_filters'];
                            unset($_SESSION['report_data']);
                            unset($_SESSION['report_filters']);
                            if (!empty($report_data)) {
                        ?>
                                <div class="report-summary">
                                    <h5>Rapor Özeti</h5>
                                    <p>Toplam Etkinlik: <?php echo count($report_data); ?></p>
                                    <p>Tarih Aralığı: <?php echo turkish_date('d M Y', strtotime($filters['start_date'])) . ' - ' . turkish_date('d M Y', strtotime($filters['end_date'])); ?></p>
                                </div>
                                <div class="table-responsive">
                                    <table class="table table-hover">
                                        <thead>
                                            <tr>
                                                <th>Tarih</th>
                                                <th>Birim</th>
                                                <th>Etkinlik Adı</th>
                                                <th>Saat</th>
                                                <th>İletişim</th>
                                                <th>Durum</th>
                                                <th>Ödeme</th>
                                            </tr>
                                        </thead>
                                        <tbody>
                                            <?php foreach ($report_data as $event): ?>
                                                <tr>
                                                    <td><?php echo turkish_date('d M Y', strtotime($event['event_date'] ?? 'now')); ?></td>
                                                    <td><?php echo htmlspecialchars($event['unit_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($event['event_name'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($event['event_time'] ?? ''); ?></td>
                                                    <td><?php echo htmlspecialchars($event['contact_info'] ?? ''); ?></td>
                                                    <td>
                                                        <?php // YENİ: Dinamik Rapor Durumları
                                                            $event_status = $event['status'] ?? '';
                                                            $status_data = $all_event_statuses[$event_status] ?? null;
                                                            if ($status_data):
                                                        ?>
                                                            <span class="badge badge-status-<?php echo $event_status; ?>"><?php echo htmlspecialchars($status_data['display_name']); ?></span>
                                                        <?php endif; ?>
                                                    </td>
                                                    <td>
                                                        <?php 
                                                        $event_payment_status = $event['payment_status'] ?? '';
                                                        $payment_data = $all_payment_statuses[$event_payment_status] ?? null;
                                                        if ($event_status !== 'free' && $event_status !== 'cancelled' && $payment_data):
                                                        ?>
                                                            <span class="badge badge-payment-<?php echo $event_payment_status; ?>"><?php echo htmlspecialchars($payment_data['display_name']); ?></span>
                                                        <?php else: ?>
                                                            <span class="text-muted">-</span>
                                                        <?php endif; ?>
                                                    </td>
                                                </tr>
                                            <?php endforeach; ?>
                                        </tbody>
                                    </table>
                                </div>
                                <div class="export-buttons">
                                    <h5>Raporu İndir</h5>
                                    <div class="d-flex gap-2">
                                        <form method="post" accept-charset="UTF-8">
                                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                                            <?php foreach ($filters['unit_ids'] as $unit_id): ?>
                                                <input type="hidden" name="unit_ids[]" value="<?php echo htmlspecialchars($unit_id); ?>">
                                            <?php endforeach; ?>
                                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($filters['status_filter']); ?>">
                                            <input type="hidden" name="payment_filter" value="<?php echo htmlspecialchars($filters['payment_filter']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" name="generate_report" value="txt" class="btn btn-info">
                                                <i class="fas fa-file-alt me-1"></i>TXT Olarak İndir
                                            </button>
                                        </form>
                                        <form method="post" accept-charset="UTF-8">
                                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                                            <?php foreach ($filters['unit_ids'] as $unit_id): ?>
                                                <input type="hidden" name="unit_ids[]" value="<?php echo htmlspecialchars($unit_id); ?>">
                                            <?php endforeach; ?>
                                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($filters['status_filter']); ?>">
                                            <input type="hidden" name="payment_filter" value="<?php echo htmlspecialchars($filters['payment_filter']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" name="generate_report" value="doc" class="btn btn-success">
                                                <i class="fas fa-file-word me-1"></i>DOC Olarak İndir
                                            </button>
                                        </form>
                                        <form method="post" accept-charset="UTF-8">
                                            <input type="hidden" name="start_date" value="<?php echo htmlspecialchars($filters['start_date']); ?>">
                                            <input type="hidden" name="end_date" value="<?php echo htmlspecialchars($filters['end_date']); ?>">
                                            <?php foreach ($filters['unit_ids'] as $unit_id): ?>
                                                <input type="hidden" name="unit_ids[]" value="<?php echo htmlspecialchars($unit_id); ?>">
                                            <?php endforeach; ?>
                                            <input type="hidden" name="status_filter" value="<?php echo htmlspecialchars($filters['status_filter']); ?>">
                                            <input type="hidden" name="payment_filter" value="<?php echo htmlspecialchars($filters['payment_filter']); ?>">
                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                            <button type="submit" name="generate_report" value="xls" class="btn btn-warning">
                                                <i class="fas fa-file-excel me-1"></i>XLS Olarak İndir
                                            </button>
                                        </form>
                                    </div>
                                </div>
                                <?php
                            } else {
                                echo '<div class="alert alert-info mt-3">Seçilen kriterlere uygun etkinlik bulunamadı.</div>';
                            }
                        }
                        ?>
                    <?php elseif ($tab === 'users'): ?>
                        <h5 class="card-title">Kullanıcı Yönetimi</h5>
                        <button class="btn btn-primary mb-3" data-bs-toggle="modal" data-bs-target="#adminUserModal" onclick="newAdminUser()">
                            <i class="fas fa-user-plus me-1"></i>Yeni Kullanıcı Ekle
                        </button>
                        <div class="table-responsive">
                            <table class="table table-hover">
                                <thead>
                                    <tr>
                                        <th>ID</th>
                                        <th>Kullanıcı Adı</th>
                                        <th>Tam Ad</th>
                                        <th>E-posta</th>
                                        <th>Aktif</th>
                                        <th>Oluşturulma Tarihi</th>
                                        <th class="actions">İşlemler</th>
                                    </tr>
                                </thead>
                                <tbody>
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM admin_users ORDER BY username";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                        $users = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($users as $user):
                                    ?>
                                            <tr>
                                                <td><?php echo $user['id']; ?></td>
                                                <td><?php echo htmlspecialchars($user['username']); ?></td>
                                                <td><?php echo htmlspecialchars($user['full_name']); ?></td>
                                                <td><?php echo htmlspecialchars($user['email']); ?></td>
                                                <td><?php echo $user['is_active'] ? 'Evet' : 'Hayır'; ?></td>
                                                <td><?php echo turkish_date('d M Y H:i', strtotime($user['created_at'])); ?></td>
                                                <td class="actions">
                                                    <div class="action-buttons d-flex justify-content-center">
                                                        <button class="btn btn-sm btn-primary edit-user" data-bs-toggle="modal" data-bs-target="#adminUserModal"
                                                                data-user-id="<?php echo $user['id']; ?>"
                                                                data-username="<?php echo htmlspecialchars($user['username']); ?>"
                                                                data-full-name="<?php echo htmlspecialchars($user['full_name']); ?>"
                                                                data-email="<?php echo htmlspecialchars($user['email']); ?>"
                                                                data-user-active="<?php echo $user['is_active']; ?>">
                                                            <i class="fas fa-edit"></i>
                                                        </button>
                                                        <form method="post" class="d-inline" onsubmit="return confirm('Bu kullanıcıyı silmek istediğinize emin misiniz?');">
                                                            <input type="hidden" name="user_id" value="<?php echo $user['id']; ?>">
                                                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">
                                                            <button type="submit" name="delete_admin_user" class="btn btn-sm btn-danger">
                                                                <i class="fas fa-trash"></i>
                                                            </button>
                                                        </form>
                                                    </div>
                                                </td>
                                            </tr>
                                        <?php endforeach; ?>
                                    <?php } catch(PDOException $e) { ?>
                                        <tr><td colspan="7">Kullanıcılar getirilirken hata oluştu.</td></tr>
                                    <?php } ?>
                                </tbody>
                            </table>
                        </div>
                    <?php // YENİ: AYARLAR SEKMESİ ?>
                    <?php elseif ($tab === 'settings'): ?>
                        <h5 class="card-title">Genel Ayarlar</h5>
                        <p>Buradan etkinlik durumları ve ödeme durumları için görüntülenen adları ve renkleri düzenleyebilirsiniz. <strong>Not:</strong> Anahtarlar (örn: 'confirmed') sistem için gereklidir ve değiştirilemez.</p>
                        
                        <form method="post" accept-charset="UTF-8">
                            <input type="hidden" name="csrf_token" value="<?php echo $csrf_token; ?>">

                            <div class="form-check form-switch mb-4">
                                <input class="form-check-input" type="checkbox" role="switch" id="maintenance_mode" name="maintenance_mode" value="1" <?php echo $maintenance_mode_enabled ? 'checked' : ''; ?>>
                                <label class="form-check-label" for="maintenance_mode"><strong>Bakım Modu</strong> - Ziyaretçilere bakım mesajı göster</label>
                                <div class="form-text">Bakım modunu etkinleştirdiğinizde ana sayfada ziyaretçilere bilgilendirme mesajı gösterilir. Yönetici paneli kullanılmaya devam edebilir.</div>
                            </div>

                            <div class="card mb-4">
                                <div class="card-body">
                                    <h6 class="card-title">Son Etkinlik Güncellemeleri</h6>
                                    <div class="form-check form-switch mb-3">
                                        <input class="form-check-input" type="checkbox" role="switch" id="updates_enabled" name="updates_enabled" value="1" <?php echo $updates_enabled ? 'checked' : ''; ?>>
                                        <label class="form-check-label" for="updates_enabled">Ana sayfada son değişiklikleri göster</label>
                                    </div>
                                    <div class="row g-3">
                                        <div class="col-md-4">
                                            <label for="updates_max_count" class="form-label">Gösterilecek değişiklik sayısı</label>
                                            <input type="number" class="form-control" id="updates_max_count" name="updates_max_count" min="1" max="50" value="<?php echo (int) $updates_max_count; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="updates_duration" class="form-label">Geçerlilik süresi</label>
                                            <input type="number" class="form-control" id="updates_duration" name="updates_duration" min="1" max="168" value="<?php echo (int) $updates_duration; ?>">
                                        </div>
                                        <div class="col-md-4">
                                            <label for="updates_duration_unit" class="form-label">Süre birimi</label>
                                            <select class="form-select" id="updates_duration_unit" name="updates_duration_unit">
                                                <option value="hours" <?php echo $updates_duration_unit === 'hours' ? 'selected' : ''; ?>>Saat</option>
                                                <option value="days" <?php echo $updates_duration_unit === 'days' ? 'selected' : ''; ?>>Gün</option>
                                            </select>
                                        </div>
                                    </div>
                                </div>
                            </div>

                            <div class="mb-4">
                                <button type="button" id="backupButton" class="btn btn-primary">🚀 Backup Database to GitHub</button>
                            </div>

                            <h6>Etkinlik Durumları</h6>
                            <div class="table-responsive mb-4">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Anahtar (Değişmez)</th>
                                            <th>Görünen Ad</th>
                                            <th>Renk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_event_statuses as $key => $status): ?>
                                        <tr>
                                            <td><strong><?php echo $key; ?></strong></td>
                                            <td>
                                                <input type="text" class="form-control" 
                                                       name="event_status[<?php echo $key; ?>][display_name]" 
                                                       value="<?php echo htmlspecialchars($status['display_name']); ?>" required>
                                            </td>
                                            <td>
                                                <input type="color" class="form-control form-control-color" 
                                                       name="event_status[<?php echo $key; ?>][color]" 
                                                       value="<?php echo htmlspecialchars($status['color']); ?>" required>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>

                            <h6>Ödeme Durumları</h6>
                            <div class="table-responsive mb-4">
                                <table class="table">
                                    <thead>
                                        <tr>
                                            <th>Anahtar (Değişmez)</th>
                                            <th>Görünen Ad</th>
                                            <th>Renk</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php foreach ($all_payment_statuses as $key => $status): ?>
                                        <tr>
                                            <td><strong><?php echo $key; ?></strong></td>
                                            <td>
                                                <input type="text" class="form-control" 
                                                       name="payment_status[<?php echo $key; ?>][display_name]" 
                                                       value="<?php echo htmlspecialchars($status['display_name']); ?>" required>
                                            </td>
                                            <td>
                                                <input type="color" class="form-control form-control-color" 
                                                       name="payment_status[<?php echo $key; ?>][color]" 
                                                       value="<?php echo htmlspecialchars($status['color']); ?>" required>
                                            </td>
                                        </tr>
                                        <?php endforeach; ?>
                                    </tbody>
                                </table>
                            </div>
                            
                            <button type="submit" name="save_status_settings" class="btn btn-primary">Ayarları Kaydet</button>
                        </form>
                    <?php endif; ?>
                </div>
            </div>
        </div>
    <?php
    }    ?>

    <div class="modal fade" id="eventModal" tabindex="-1" aria-labelledby="eventModalLabel" aria-hidden="true">
        <div class="modal-dialog modal-lg">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="eventModalLabel">Etkinlik Ekle/Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="eventForm" accept-charset="UTF-8">
                    <div class="modal-body">
                        <input type="hidden" name="event_id" id="event_id">
                        <input type="hidden" name="source_page" value="<?php echo $page; ?>">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="unit_id" class="form-label">Birim</label>
                                <select class="form-select" id="unit_id" name="unit_id" required>
                                    <option value="">Birim Seçin</option>
                                    <?php
                                    try {
                                        $sql = "SELECT * FROM units WHERE is_active = TRUE ORDER BY unit_name";
                                        $stmt = $pdo->prepare($sql);
                                        $stmt->execute();
                                        $units = $stmt->fetchAll(PDO::FETCH_ASSOC);
                                        foreach ($units as $unit) {
                                            echo '<option value="' . $unit['id'] . '">' . htmlspecialchars($unit['unit_name']) . '</option>';
                                        }
                                    } catch(PDOException $e) {
                                        echo '<option value="">Birimler yüklenemedi</option>';
                                    }
                                    ?>
                                </select>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="event_date" class="form-label">Tarih</label>
                                <input type="date" class="form-control" id="event_date" name="event_date" required>
                            </div>
                        </div>
                        <div id="existingEventsSection" class="mb-3" style="display: none;">
                            <h6>Bu Tarih ve Birimdeki Mevcut Etkinlikler:</h6>
                            <div class="existing-events" id="existingEventsList"></div>
                            <div class="alert alert-info">
                                <small>Yeni bir etkinlik eklemek için aşağıdaki formu doldurun.</small>
                            </div>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_name" class="form-label">Etkinlik Adı</label>
                                <input type="text" class="form-control" id="event_name" name="event_name" required>
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="event_time" class="form-label">Saat</label>
                                <input type="text" class="form-control" id="event_time" name="event_time" placeholder="Örn: 14:00-16:00" required>
                            </div>
                        </div>
                        <div class="mb-3">
                            <label for="event_contact" class="form-label">İletişim Bilgisi</label>
                            <input type="text" class="form-control" id="event_contact" name="event_contact" placeholder="İsteğe bağlı">
                        </div>
                        <div class="mb-3">
                            <label for="event_notes" class="form-label">Notlar</label>
                            <textarea class="form-control" id="event_notes" name="event_notes" rows="3"></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="event_status" class="form-label">Durum</label>
                                <?php // YENİ: Dinamik Durum Dropdown ?>
                                <select class="form-select" id="event_status" name="event_status" required>
                                    <?php foreach ($all_event_statuses as $key => $status): ?>
                                        <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($status['display_name']); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </div>
                            <div class="col-md-6">
                                <div class="mb-3 payment-status-container" id="payment_status_container">
                                    <label for="payment_status" class="form-label">Ödeme Durumu</label>
                                    <?php // YENİ: Dinamik Ödeme Dropdown ?>
                                    <select class="form-select" id="payment_status" name="payment_status">
                                        <option value="">Seçiniz</option>
                                        <?php foreach ($all_payment_statuses as $key => $status): ?>
                                            <option value="<?php echo $key; ?>"><?php echo htmlspecialchars($status['display_name']); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                            </div>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="save_event" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="unitModal" tabindex="-1" aria-labelledby="unitModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="unitModalLabel">Birim Ekle/Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="unitForm" accept-charset="UTF-8">
                    <div class="modal-body">
                        <input type="hidden" name="unit_id" id="modal_unit_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="unit_name" class="form-label">Birim Adı</label>
                            <input type="text" class="form-control" id="unit_name" name="unit_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="unit_color" class="form-label">Renk</label>
                            <input type="color" class="form-control form-control-color" id="unit_color" name="unit_color" value="#3498db" title="Birim rengini seçin">
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="unit_active" name="unit_active" value="1" checked>
                            <label class="form-check-label" for="unit_active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="save_unit" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="holidayModal" tabindex="-1" aria-labelledby="holidayModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="holidayModalLabel">Tatil Ekle/Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="holidayForm" accept-charset="UTF-8">
                    <div class="modal-body">
                        <input type="hidden" name="holiday_id" id="holiday_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="holiday_name" class="form-label">Tatil Adı</label>
                            <input type="text" class="form-control" id="holiday_name" name="holiday_name" required>
                        </div>
                        <div class="mb-3">
                            <label for="holiday_date" class="form-label">Tarih</label>
                            <input type="date" class="form-control" id="holiday_date" name="holiday_date" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="save_holiday" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>
    
    <?php // YENİ: Duyuru Modalı ?>
    <div class="modal fade" id="announcementModal" tabindex="-1" aria-labelledby="announcementModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="announcementModalLabel">Duyuru Ekle/Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="announcementForm" accept-charset="UTF-8">
                    <div class="modal-body">
                        <input type="hidden" name="announcement_id" id="announcement_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="content" class="form-label">Duyuru İçeriği</label>
                            <textarea class="form-control" id="content" name="content" rows="4" required></textarea>
                        </div>
                        <div class="row">
                            <div class="col-md-6 mb-3">
                                <label for="start_date_modal" class="form-label">Başlangıç (İsteğe Bağlı)</label>
                                <input type="datetime-local" class="form-control" id="start_date_modal" name="start_date">
                            </div>
                            <div class="col-md-6 mb-3">
                                <label for="end_date_modal" class="form-label">Bitiş (İsteğe Bağlı)</label>
                                <input type="datetime-local" class="form-control" id="end_date_modal" name="end_date">
                            </div>
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="show_author" name="show_author" value="1">
                            <label class="form-check-label" for="show_author">Yazarın adını ana sayfada göster</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="save_announcement" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="adminUserModal" tabindex="-1" aria-labelledby="adminUserModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="adminUserModalLabel">Kullanıcı Ekle/Düzenle</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" id="adminUserForm" accept-charset="UTF-8">
                    <div class="modal-body">
                        <input type="hidden" name="user_id" id="user_id">
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="username" class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="password" class="form-label">Şifre (Değiştirmek istemiyorsanız boş bırakın)</label>
                            <input type="password" class="form-control" id="password" name="password">
                        </div>
                        <div class="mb-3">
                            <label for="full_name" class="form-label">Tam Ad</label>
                            <input type="text" class="form-control" id="full_name" name="full_name">
                        </div>
                        <div class="mb-3">
                            <label for="email" class="form-label">E-posta</label>
                            <input type="email" class="form-control" id="email" name="email">
                        </div>
                        <div class="mb-3 form-check">
                            <input class="form-check-input" type="checkbox" id="user_active" name="user_active" value="1" checked>
                            <label class="form-check-label" for="user_active">Aktif</label>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="save_admin_user" class="btn btn-primary">Kaydet</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <div class="modal fade" id="loginModal" tabindex="-1" aria-labelledby="loginModalLabel" aria-hidden="true">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header">
                    <h5 class="modal-title" id="loginModalLabel">Admin Girişi</h5>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <form method="post" accept-charset="UTF-8">
                    <div class="modal-body">
                        <?php if (isset($login_error)): ?>
                            <div class="alert alert-danger"><?php echo $login_error; ?></div>
                        <?php endif; ?>
                        <input type="hidden" name="csrf_token" value="<?php echo generateCSRFToken(); ?>">
                        <div class="mb-3">
                            <label for="login_username" class="form-label">Kullanıcı Adı</label>
                            <input type="text" class="form-control" id="login_username" name="username" required>
                        </div>
                        <div class="mb-3">
                            <label for="login_password" class="form-label">Şifre</label>
                            <input type="password" class="form-control" id="login_password" name="password" required>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">İptal</button>
                        <button type="submit" name="admin_login" class="btn btn-primary">Giriş Yap</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <footer class="mt-5">
        <div class="container text-center">
            <p class="mb-0">© <?php echo date('Y'); ?> Çeşme Belediyesi Kültür Müdürlüğü - Tüm hakları saklıdır.</p>
            <p class="mb-0" style="font-size: 0.85rem;">Created by İlhan Akdeniz</p>
        </div>
    </footer>

    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // YENİ: PHP'den dinamik durum haritalarını JS'e aktar
        const allEventStatuses = <?php echo json_encode($all_event_statuses); ?>;
        const allPaymentStatuses = <?php echo json_encode($all_payment_statuses); ?>;

        document.addEventListener('DOMContentLoaded', function() {
            const eventModal = new bootstrap.Modal(document.getElementById('eventModal'));
            const unitModal = new bootstrap.Modal(document.getElementById('unitModal'));
            const holidayModal = new bootstrap.Modal(document.getElementById('holidayModal'));
            const adminUserModal = new bootstrap.Modal(document.getElementById('adminUserModal'));
            const announcementModal = document.getElementById('announcementModal') ? new bootstrap.Modal(document.getElementById('announcementModal')) : null;

            const backupButton = document.getElementById('backupButton');
            if (backupButton) {
                backupButton.addEventListener('click', function() {
                    fetch('backup.php')
                        .then(response => {
                            if (!response.ok) {
                                throw new Error('Backup failed');
                            }
                            return response.text();
                        })
                        .then(() => {
                            alert('Yedekleme başarıyla tamamlandı.');
                        })
                        .catch(() => {
                            alert('Yedekleme sırasında bir hata oluştu.');
                        });
                });
            }

            function populateEventModal(button) {
                const form = document.getElementById('eventForm');
                form.querySelector('#event_id').value = button.dataset.eventId || '';
                form.querySelector('#event_date').value = button.dataset.eventDate || new Date().toISOString().split('T')[0];
                form.querySelector('#event_name').value = button.dataset.eventName || '';
                form.querySelector('#event_time').value = button.dataset.eventTime || '';
                form.querySelector('#event_contact').value = button.dataset.eventContact || '';
                form.querySelector('#event_notes').value = button.dataset.eventNotes || '';
                form.querySelector('#event_status').value = button.dataset.eventStatus || 'option'; // 'option' varsayılan
                form.querySelector('#unit_id').value = button.dataset.unitId || '';
                form.querySelector('#payment_status').value = button.dataset.eventPayment || '';
                document.getElementById('unit_id').disabled = false;
                togglePaymentStatus(form.querySelector('#event_status').value);
                document.getElementById('existingEventsSection').style.display = 'none';
                eventModal.show();
            }

            document.querySelectorAll('.btn-edit-quick, .edit-event').forEach(button => {
                button.addEventListener('click', () => populateEventModal(button));
            });

            document.getElementById('unit_id').addEventListener('change', checkExistingEvents);
            document.getElementById('event_date').addEventListener('change', checkExistingEvents);

            function checkExistingEvents() {
                const unitId = document.getElementById('unit_id').value;
                const eventDate = document.getElementById('event_date').value;
                const eventId = document.getElementById('event_id').value;
                if (!eventId && unitId && eventDate) {
                    fetch(`?ajax=get_events&unit_id=${unitId}&event_date=${eventDate}`)
                        .then(response => response.json())
                        .then(data => {
                            const section = document.getElementById('existingEventsSection');
                            const list = document.getElementById('existingEventsList');
                            if (data.length > 0) {
                                list.innerHTML = '';
                                data.forEach(event => {
                                    const item = document.createElement('div');
                                    item.className = 'event-list-item';
                                    item.innerHTML = `
                                        <strong>${event.event_name}</strong> - ${event.event_time}
                                        ${event.contact_info ? `<br><small>${event.contact_info}</small>` : ''}
                                        <span class="badge ${getStatusBadgeClass(event.status)} float-end">${getStatusText(event.status)}</span>`;
                                    list.appendChild(item);
                                });
                                section.style.display = 'block';
                            } else {
                                section.style.display = 'none';
                            }
                        }).catch(error => {
                            console.error('Error:', error);
                            document.getElementById('existingEventsSection').style.display = 'none';
                        });
                } else {
                    document.getElementById('existingEventsSection').style.display = 'none';
                }
            }

            // YENİ: Dinamik JS fonksiyonları
            function getStatusText(status) {
                return allEventStatuses[status] ? allEventStatuses[status].display_name : status;
            }

            function getStatusBadgeClass(status) {
                // CSS'e .badge-status-[key] sınıflarını enjekte ettiğimiz için,
                // sadece sınıf adını döndürmemiz yeterli.
                return allEventStatuses[status] ? `badge-status-${status}` : 'bg-secondary';
            }

            document.getElementById('event_status').addEventListener('change', function() {
                togglePaymentStatus(this.value);
            });

            function togglePaymentStatus(status) {
                const container = document.getElementById('payment_status_container');
                // Bu anahtar isimler ('free', 'cancelled') veritabanında değiştirilmediği sürece çalışır.
                if (status === 'free' || status === 'cancelled') {
                    container.classList.add('hidden');
                    document.getElementById('payment_status').value = '';
                } else {
                    container.classList.remove('hidden');
                }
            }

            window.newEvent = function() {
                document.getElementById('eventForm').reset();
                document.getElementById('event_id').value = '';
                document.getElementById('event_date').value = new Date().toISOString().split('T')[0];
                document.getElementById('event_status').value = 'option'; // Varsayılan durum
                document.getElementById('unit_id').disabled = false;
                togglePaymentStatus('option'); // Varsayılan duruma göre ayarla
            }

            window.newEventForDay = function(date, unitId) {
                document.getElementById('eventForm').reset();
                document.getElementById('event_id').value = '';
                document.getElementById('event_date').value = date;
                document.getElementById('unit_id').value = unitId;
                document.getElementById('unit_id').disabled = false;
                document.getElementById('event_status').value = 'option'; // Varsayılan durum
                document.getElementById('existingEventsSection').style.display = 'block';
                checkExistingEvents();
                togglePaymentStatus('option'); // Varsayılan duruma göre ayarla
            }

            document.querySelectorAll('.edit-unit').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('modal_unit_id').value = this.dataset.unitId;
                    document.getElementById('unit_name').value = this.dataset.unitName;
                    document.getElementById('unit_color').value = this.dataset.unitColor;
                    document.getElementById('unit_active').checked = this.dataset.unitActive === '1';
                });
            });

            window.newUnit = function() {
                document.getElementById('unitForm').reset();
                document.getElementById('modal_unit_id').value = '';
                document.getElementById('unit_active').checked = true;
            }

            document.querySelectorAll('.edit-holiday').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('holiday_id').value = this.dataset.holidayId;
                    document.getElementById('holiday_name').value = this.dataset.holidayName;
                    document.getElementById('holiday_date').value = this.dataset.holidayDate;
                });
            });

            window.newHoliday = function() {
                document.getElementById('holidayForm').reset();
                document.getElementById('holiday_id').value = '';
                document.getElementById('holiday_date').value = new Date().toISOString().split('T')[0];
            }
            
            document.querySelectorAll('.edit-user').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('user_id').value = this.dataset.userId;
                    document.getElementById('username').value = this.dataset.username;
                    document.getElementById('full_name').value = this.dataset.fullName;
                    document.getElementById('email').value = this.dataset.email;
                    document.getElementById('user_active').checked = this.dataset.userActive === '1';
                    document.getElementById('password').value = '';
                });
            });

            window.newAdminUser = function() {
                document.getElementById('adminUserForm').reset();
                document.getElementById('user_id').value = '';
                document.getElementById('user_active').checked = true;
            }

            // YENİ: Duyuru Modalı JS
            document.querySelectorAll('.edit-announcement').forEach(button => {
                button.addEventListener('click', function() {
                    document.getElementById('announcement_id').value = this.dataset.announcementId;
                    document.getElementById('content').value = this.dataset.content;
                    document.getElementById('show_author').checked = this.dataset.showAuthor === '1';
                    document.getElementById('start_date_modal').value = this.dataset.startDate;
                    document.getElementById('end_date_modal').value = this.dataset.endDate;
                });
            });

            window.newAnnouncement = function() {
                document.getElementById('announcementForm').reset();
                document.getElementById('announcement_id').value = '';
            }

            // Raporlama Checkbox JS
            const allUnitsCheckbox = document.getElementById('all_units');
            const unitCheckboxes = document.querySelectorAll('.unit-checkbox');
            
            if (allUnitsCheckbox) {
                allUnitsCheckbox.addEventListener('change', function() {
                    unitCheckboxes.forEach(checkbox => {
                        checkbox.checked = this.checked;
                    });
                });
            }

            if (unitCheckboxes && unitCheckboxes.length > 0) {
                unitCheckboxes.forEach(checkbox => {
                    checkbox.addEventListener('change', function() {
                        if (!this.checked) {
                            allUnitsCheckbox.checked = false;
                        } else {
                            const allChecked = Array.from(unitCheckboxes).every(cb => cb.checked);
                            allUnitsCheckbox.checked = allChecked;
                        }
                    });
                });
            }

            const startDateInput = document.getElementById('start_date');
            const endDateInput = document.getElementById('end_date');
            if (startDateInput && endDateInput) {
                const today = new Date().toISOString().split('T')[0];
                if (!startDateInput.value) startDateInput.value = today;
                if (!endDateInput.value) endDateInput.value = today;
            }
        });
        function confirmDelete(eventId, element, csrfToken) {
            if (confirm('Bu etkinliği silmek istediğinizden emin misiniz?')) {
                const form = document.createElement('form');
                form.method = 'POST';
                form.style.display = 'none';
                form.innerHTML = `
                    <input type="hidden" name="event_id" value="${eventId}">
                    <input type="hidden" name="delete_event_index" value="1">
                    <input type="hidden" name="csrf_token" value="${csrfToken}">
                `;
                document.body.appendChild(form);
                form.submit();
            }
        }
    </script>
</body>
</html>