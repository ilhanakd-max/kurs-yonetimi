<?php
session_start();
// --- VERİTABANI BAĞLANTISI ---
$db_host = 'sql211.infinityfree.com';
$db_name = 'if0_40197167_test';
$db_user = 'if0_40197167';
$db_pass = 'TEST'; // PANEL ŞİFRENİZ

try {
    $pdo = new PDO("mysql:host=$db_host;dbname=$db_name;charset=utf8mb4", $db_user, $db_pass);
    $pdo->setAttribute(PDO::ATTR_ERRMODE, PDO::ERRMODE_EXCEPTION);
} catch (PDOException $e) {
    die("Veritabanı hatası: " . $e->getMessage());
}

// --- API İŞLEMLERİ (AJAX Requests) ---
if (isset($_GET['action'])) {
    header('Content-Type: application/json');
    $action = $_GET['action'];
    $data = json_decode(file_get_contents('php://input'), true);

    // LOGIN
    if ($action === 'login') {
        $u = $data['username'];
        $p = $data['password'];
        
        // Admin Kontrolü
        $stmt = $pdo->prepare("SELECT * FROM users WHERE username = ?");
        $stmt->execute([$u]);
        $user = $stmt->fetch(PDO::FETCH_ASSOC);

        // İlk kurulum için varsayılan admin
        if (!$user && $u === 'admin' && $p === 'admin123') {
            $hash = password_hash('admin123', PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?, ?, ?, ?)")
                ->execute(['Sistem Yöneticisi', 'admin', $hash, 'admin']);
            $user = ['id' => 1, 'username' => 'admin', 'role' => 'admin', 'name' => 'Sistem Yöneticisi', 'password' => $hash];
        }

        if ($user && password_verify($p, $user['password'])) {
            unset($user['password']);
            echo json_encode(['status' => 'success', 'user' => $user]);
            exit;
        }

        // Öğretmen Kontrolü
        $stmt = $pdo->prepare("SELECT * FROM teachers WHERE username = ?");
        $stmt->execute([$u]);
        $teacher = $stmt->fetch(PDO::FETCH_ASSOC);
        
        $auth = false;
        if($teacher) {
            if(password_verify($p, $teacher['password'])) {
                $auth = true;
            } elseif ($p === $teacher['password']) {
                $auth = true; 
            }
        }

        if ($auth) {
             $tUser = ['id' => $teacher['id'], 'username' => $teacher['username'], 'role' => 'teacher', 'name' => $teacher['name']];
             echo json_encode(['status' => 'success', 'user' => $tUser]);
             exit;
        }

        echo json_encode(['status' => 'error', 'message' => 'Hatalı kullanıcı adı veya şifre']);
        exit;
    }

    // ŞİFRE DEĞİŞTİRME
    if ($action === 'change_password') {
        $id = $data['id'];
        $role = $data['role'];
        $newPass = $data['newPass'];
        $hash = password_hash($newPass, PASSWORD_DEFAULT);

        if ($role === 'admin') {
            $pdo->prepare("UPDATE users SET password=? WHERE id=?")->execute([$hash, $id]);
        } else {
            $pdo->prepare("UPDATE teachers SET password=? WHERE id=?")->execute([$hash, $id]);
        }
        echo json_encode(['status' => 'success']);
        exit;
    }

    // VERİLERİ ÇEK
    if ($action === 'get_all_data') {
        $response = [];
        $stmt = $pdo->query("SELECT id, name, username, role FROM users");
        $response['users'] = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        $stmt = $pdo->query("SELECT * FROM teachers");
        $response['teachers'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT * FROM courses");
        $courses = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($courses as &$c) {
            // JS uyumluluğu için teacherId alanını ekliyoruz
            $c['teacherId'] = $c['teacher_id']; 
            
            $c['cancelledDates'] = json_decode($c['cancelled_dates']) ?: [];
            $c['modifications'] = json_decode($c['modifications']) ?: (object)[];
            unset($c['cancelled_dates'], $c['modifications_json']);
        }
        $response['courses'] = $courses;

        $stmt = $pdo->query("SELECT * FROM students");
        $students = $stmt->fetchAll(PDO::FETCH_ASSOC);
        foreach($students as &$s) {
            $stmt2 = $pdo->prepare("SELECT course_id FROM student_courses WHERE student_id = ?");
            $stmt2->execute([$s['id']]);
            $s['courses'] = $stmt2->fetchAll(PDO::FETCH_COLUMN);
        }
        $response['students'] = $students;

        $stmt = $pdo->query("SELECT * FROM attendance");
        $att = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $cleanAtt = [];
        foreach($att as $a) {
            $cleanAtt[] = ['courseId' => $a['course_id'], 'studentId' => $a['student_id'], 'date' => $a['date'], 'status' => $a['status']];
        }
        $response['attendance'] = $cleanAtt;

        $stmt = $pdo->query("SELECT date, name FROM holidays");
        $response['holidays'] = $stmt->fetchAll(PDO::FETCH_ASSOC);

        $stmt = $pdo->query("SELECT * FROM meta_data");
        $meta = $stmt->fetchAll(PDO::FETCH_KEY_PAIR);
        $response['settings'] = ['title' => $meta['title'] ?? 'Çeşme Belediyesi Kültür Müdürlüğü'];
        $response['buildings'] = json_decode($meta['buildings'] ?? '[]');
        $response['classes'] = json_decode($meta['classes'] ?? '[]');

        echo json_encode($response);
        exit;
    }

    // KAYDETME İŞLEMLERİ
    if ($action === 'save_course') {
        $c = $data;
        $cancelled = json_encode($c['cancelledDates']);
        $mods = json_encode($c['modifications']);
        
        if (isset($c['id']) && $c['id'] > 0 && !str_starts_with($c['id'], 'new')) {
            $sql = "UPDATE courses SET name=?, color=?, day=?, time=?, building=?, classroom=?, teacher_id=?, start_date=?, end_date=?, cancelled_dates=?, modifications=? WHERE id=?";
            $pdo->prepare($sql)->execute([$c['name'], $c['color'], $c['day'], $c['time'], $c['building'], $c['classroom'], $c['teacherId'], $c['startDate'], $c['endDate'], $cancelled, $mods, $c['id']]);
        } else {
            $sql = "INSERT INTO courses (name, color, day, time, building, classroom, teacher_id, start_date, end_date, cancelled_dates, modifications) VALUES (?,?,?,?,?,?,?,?,?,?,?)";
            $pdo->prepare($sql)->execute([$c['name'], $c['color'], $c['day'], $c['time'], $c['building'], $c['classroom'], $c['teacherId'], $c['startDate'], $c['endDate'], $cancelled, $mods]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_course') {
        $id = $data['id'];
        $pdo->prepare("DELETE FROM courses WHERE id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_courses WHERE course_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM attendance WHERE course_id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_student') {
        $s = $data;
        $sid = $s['id'];
        if ($sid && $sid > 0 && !str_starts_with($sid, 'new')) {
            $sql = "UPDATE students SET name=?, surname=?, phone=?, email=?, tc=?, date_of_birth=?, education=?, parent_name=?, parent_phone=? WHERE id=?";
            $pdo->prepare($sql)->execute([$s['name'], $s['surname'], $s['phone'], $s['email'], $s['tc'], $s['date_of_birth'], $s['education'], $s['parent_name'], $s['parent_phone'], $sid]);
        } else {
            $sql = "INSERT INTO students (name, surname, phone, email, tc, date_of_birth, education, parent_name, parent_phone, reg_date) VALUES (?,?,?,?,?,?,?,?,?, NOW())";
            $stmt = $pdo->prepare($sql);
            $stmt->execute([$s['name'], $s['surname'], $s['phone'], $s['email'], $s['tc'], $s['date_of_birth'], $s['education'], $s['parent_name'], $s['parent_phone']]);
            $sid = $pdo->lastInsertId();
        }

        $pdo->prepare("DELETE FROM student_courses WHERE student_id=?")->execute([$sid]);
        if (!empty($s['courses'])) {
            $insert = $pdo->prepare("INSERT INTO student_courses (student_id, course_id) VALUES (?, ?)");
            foreach($s['courses'] as $cid) {
                $insert->execute([$sid, $cid]);
            }
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_student') {
        $id = $data['id'];
        $pdo->prepare("DELETE FROM students WHERE id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM student_courses WHERE student_id=?")->execute([$id]);
        $pdo->prepare("DELETE FROM attendance WHERE student_id=?")->execute([$id]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_teacher') {
        $t = $data;
        if (isset($t['id']) && $t['id'] > 0 && !str_starts_with($t['id'], 'new')) {
            if(!empty($t['password'])) {
                 $hash = password_hash($t['password'], PASSWORD_DEFAULT);
                 $pdo->prepare("UPDATE teachers SET name=?, phone=?, email=?, username=?, password=? WHERE id=?")
                ->execute([$t['name'], $t['phone'], $t['email'], $t['username'], $hash, $t['id']]);
            } else {
                 $pdo->prepare("UPDATE teachers SET name=?, phone=?, email=?, username=? WHERE id=?")
                ->execute([$t['name'], $t['phone'], $t['email'], $t['username'], $t['id']]);
            }
        } else {
            $hash = password_hash($t['password'], PASSWORD_DEFAULT);
            $pdo->prepare("INSERT INTO teachers (name, phone, email, username, password) VALUES (?,?,?,?,?)")
                ->execute([$t['name'], $t['phone'], $t['email'], $t['username'], $hash]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'delete_teacher') {
        $pdo->prepare("DELETE FROM teachers WHERE id=?")->execute([$data['id']]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_user') {
        $u = $data;
        $hash = password_hash($u['password'], PASSWORD_DEFAULT);
        $pdo->prepare("INSERT INTO users (name, username, password, role) VALUES (?,?,?,?)")
            ->execute([$u['name'], $u['username'], $hash, 'admin']);
        echo json_encode(['status'=>'success']); exit;
    }
    
    if ($action === 'delete_user') {
        $pdo->prepare("DELETE FROM users WHERE id=?")->execute([$data['id']]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_attendance') {
        $a = $data;
        $stmt = $pdo->prepare("SELECT id FROM attendance WHERE course_id=? AND student_id=? AND date=?");
        $stmt->execute([$a['courseId'], $a['studentId'], $a['date']]);
        $exist = $stmt->fetch();

        if ($exist) {
            $pdo->prepare("UPDATE attendance SET status=? WHERE id=?")->execute([$a['status'], $exist['id']]);
        } else {
            $pdo->prepare("INSERT INTO attendance (course_id, student_id, date, status) VALUES (?,?,?,?)")
                ->execute([$a['courseId'], $a['studentId'], $a['date'], $a['status']]);
        }
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'save_meta') {
        $k = $data['key'];
        $v = is_array($data['value']) ? json_encode($data['value']) : $data['value'];
        $pdo->prepare("REPLACE INTO meta_data (item_key, item_value) VALUES (?, ?)")->execute([$k, $v]);
        echo json_encode(['status'=>'success']); exit;
    }

    if ($action === 'add_holiday') {
        $pdo->prepare("INSERT INTO holidays (date, name) VALUES (?, ?)")->execute([$data['date'], $data['name']]);
        echo json_encode(['status'=>'success']); exit;
    }
    if ($action === 'delete_holiday') {
        $pdo->prepare("DELETE FROM holidays WHERE date=?")->execute([$data['date']]);
        echo json_encode(['status'=>'success']); exit;
    }
    
    if ($action === 'reset_data') {
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 0");
        $pdo->exec("TRUNCATE TABLE attendance");
        $pdo->exec("TRUNCATE TABLE student_courses");
        $pdo->exec("TRUNCATE TABLE students");
        $pdo->exec("TRUNCATE TABLE courses");
        $pdo->exec("TRUNCATE TABLE teachers");
        $pdo->exec("TRUNCATE TABLE holidays");
        $pdo->exec("SET FOREIGN_KEY_CHECKS = 1");
        echo json_encode(['status'=>'success']); exit;
    }
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
<meta charset="UTF-8">
<meta name="viewport" content="width=device-width,initial-scale=1.0">
<title>Çeşme Belediyesi Kültür Müdürlüğü - Kurs Takip</title>
<style>
*{box-sizing:border-box;margin:0;padding:0}
body{font-family:'Segoe UI',sans-serif;background:#f0f4f8;min-height:100vh;position:relative;padding-bottom:30px;}
.header{background:linear-gradient(135deg,#1e3a5f,#2d5a87);color:#fff;padding:15px;display:flex;justify-content:space-between;align-items:center;flex-wrap:wrap;gap:10px}
.header h1{font-size:1.2em;margin:0}
.header span{font-size:0.9em;opacity:0.9}
.nav{background:#2d5a87;padding:10px;display:flex;flex-wrap:wrap;gap:5px}
.nav button{background:#fff;border:none;padding:8px 15px;border-radius:5px;cursor:pointer;font-size:0.85em;transition:all 0.2s}
.nav button:hover,.nav button.active{background:#ffd700;color:#1e3a5f}
.container{padding:15px;max-width:1400px;margin:0 auto}
.card{background:#fff;border-radius:10px;padding:15px;margin-bottom:15px;box-shadow:0 2px 10px rgba(0,0,0,0.1)}
.card h2{color:#1e3a5f;margin-bottom:15px;font-size:1.1em;border-bottom:2px solid #ffd700;padding-bottom:5px}
.form-group{margin-bottom:12px}
.form-group label{display:block;margin-bottom:5px;font-weight:600;color:#333;font-size:0.9em}
.form-group input,.form-group select,.form-group textarea{width:100%;padding:10px;border:1px solid #ddd;border-radius:5px;font-size:0.9em}
.form-group input[type="color"] {padding: 2px; height: 40px; cursor: pointer;}
.checkbox-group {max-height: 150px; overflow-y: auto; border: 1px solid #ddd; padding: 10px; border-radius: 5px; background: #fafafa;}
.checkbox-item {display: flex; align-items: center; margin-bottom: 5px;}
.checkbox-item input {width: auto; margin-right: 10px;}
.btn{padding:10px 20px;border:none;border-radius:5px;cursor:pointer;font-size:0.9em;margin:2px;display:inline-flex;align-items:center;justify-content:center;gap:5px}
.btn-primary{background:#1e3a5f;color:#fff}
.btn-success{background:#28a745;color:#fff}
.btn-danger{background:#dc3545;color:#fff}
.btn-warning{background:#ffc107;color:#333}
.btn-info{background:#17a2b8;color:#fff}
.btn-secondary{background:#6c757d;color:#fff}
.btn:hover{opacity:0.9;transform:scale(1.02)}
/* Calendar Styles */
.calendar{display:grid;grid-template-columns:repeat(7,1fr);gap:5px}
.cal-header{background:#1e3a5f;color:#fff;padding:10px;text-align:center;font-weight:600;font-size:0.85em}
.cal-cell{background:#fff;min-height:120px;border:1px solid #ddd;padding:5px;cursor:pointer;font-size:0.8em;position:relative;transition:all 0.2s}
.calendar.month-view .cal-cell { min-height: 90px; }
.cal-cell:hover{filter:brightness(0.95)}
.cal-cell.today{border:3px solid #1e3a5f;z-index:2}
.cal-cell.weekend{background-color:#fff3e0;}
.cal-cell.holiday{background-color:#f8d7da;border:1px solid #f5c6cb;}
.cal-cell.other-month{background:#f9f9f9;opacity:0.5;color:#999}
.cal-date{font-weight:600;color:#1e3a5f;margin-bottom:5px;display:flex;justify-content:space-between}
.cal-holiday-label{color:#dc3545;font-size:0.75em;font-weight:bold;display:block;margin-bottom:3px}
.course-tag{padding:4px 6px;border-radius:4px;margin:3px 0;font-size:0.75em;cursor:pointer;display:block;border-left:4px solid rgba(0,0,0,0.2);font-weight:600;box-shadow:0 1px 2px rgba(0,0,0,0.1)}
.course-tag:hover{filter:brightness(0.9)}
.course-tag.cancelled{background:#ffcdd2!important;color:#333!important;text-decoration:line-through;border-left-color:#dc3545}
.modal{display:none;position:fixed;top:0;left:0;width:100%;height:100%;background:rgba(0,0,0,0.5);z-index:1000;overflow-y:auto}
.modal-content{background:#fff;margin:20px auto;padding:20px;border-radius:10px;max-width:600px;width:95%;max-height:90vh;overflow-y:auto}
.modal-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px}
.modal-close{font-size:1.5em;cursor:pointer;color:#666}
.table-responsive {width: 100%; overflow-x: auto; -webkit-overflow-scrolling: touch;}
table{width:100%;border-collapse:collapse;font-size:0.85em;min-width: 600px;}
th,td{padding:10px;border:1px solid #ddd;text-align:left}
th{background:#1e3a5f;color:#fff}
tr:nth-child(even){background:#f9f9f9}
.login-container{max-width:400px;margin:50px auto;padding:20px}
.hidden{display:none!important}
.week-nav{display:flex;justify-content:space-between;align-items:center;margin-bottom:15px;flex-wrap:wrap;gap:10px}
.view-toggle{display:flex;gap:5px}
.attendance-list{max-height:300px;overflow-y:auto}
.attendance-item{display:flex;justify-content:space-between;align-items:center;padding:8px;border-bottom:1px solid #eee;flex-wrap: wrap; gap:10px;}
.attendance-actions{display:flex;gap:5px}
.stats{display:grid;grid-template-columns:repeat(auto-fit,minmax(140px,1fr));gap:10px;margin-bottom:20px}
.stat-card{background:linear-gradient(135deg,#1e3a5f,#2d5a87);color:#fff;padding:15px;border-radius:10px;text-align:center}
.stat-card h3{font-size:1.8em}
.stat-card p{font-size:0.85em;opacity:0.9}
.conflict{background:#fff3cd;padding:10px;border-radius:5px;border-left:4px solid #ffc107;margin:10px 0}
.tabs{display:flex;gap:5px;margin-bottom:15px;flex-wrap:wrap}
.tab{padding:10px 20px;background:#e9ecef;border:none;cursor:pointer;border-radius:5px 5px 0 0;flex:1;min-width: 100px;}
.tab.active{background:#1e3a5f;color:#fff}
.tab-content{display:none}
.tab-content.active{display:block}
.filter-row{display:flex;gap:10px;flex-wrap:wrap;margin-bottom:15px;align-items:flex-end;background:#f8f9fa;padding:15px;border-radius:10px;border:1px solid #e9ecef}
.filter-row > .form-group {flex:1;min-width:180px;margin-bottom:0}
.export-buttons {margin-top:15px; display:flex; gap:10px; flex-wrap:wrap;}
footer {position: absolute; bottom: 5px; width: 100%; text-align: center; font-size: 0.8em; color: rgba(0,0,0,0.3); font-style: italic;}

@media(max-width:768px){
    .calendar.month-view {grid-template-columns:repeat(1,1fr)!important;}
    .calendar.month-view .cal-header{display:none!important;}
    .calendar.month-view .cal-cell::before{content:attr(data-day);font-weight:600;display:block;margin-bottom:5px;color:#1e3a5f}
    .calendar:not(.month-view) {grid-template-columns:repeat(1,1fr)}
    .cal-header{display:none}
    .calendar:not(.month-view) .cal-cell::before{content:attr(data-day);font-weight:600;display:block;margin-bottom:5px;color:#1e3a5f}
    .header h1{font-size:1em}
    .nav button {flex: 1 0 45%; text-align: center;}
    .filter-row {flex-direction: column; align-items: stretch;}
    .filter-row > .form-group {width: 100%;}
    .week-nav {justify-content: center;}
    .week-nav h2 {font-size: 1.1em; margin: 10px 0; width: 100%; text-align: center;}
}
</style>
</head>
<body>
<div id="loginPage" class="login-container">
<div class="card">
<h2>🏛️ Çeşme Belediyesi Kültür Müdürlüğü</h2>
<p style="margin-bottom:20px;color:#666">Kurs Takip Sistemi (Web v1.6)</p>
<div class="form-group"><label>Kullanıcı Adı</label><input type="text" id="loginUser"></div>
<div class="form-group"><label>Şifre</label><input type="password" id="loginPass"></div>
<div class="form-group" style="display:flex;align-items:center;gap:10px">
    <input type="checkbox" id="rememberMe" style="width:auto;margin:0;">
    <label for="rememberMe" style="margin:0;font-weight:normal;cursor:pointer">Beni Hatırla</label>
</div>
<button class="btn btn-primary" style="width:100%" onclick="apiLogin()">Giriş Yap</button>
</div>
</div>
<div id="mainApp" class="hidden">
<div class="header">
<div><h1>🏛️ Çeşme Belediyesi Kültür Müdürlüğü</h1><span>Kurs Takip Sistemi</span></div>
<div>
    <span id="currentUser"></span> 
    <button class="btn btn-info btn-sm" onclick="openChangePasswordModal()">🔑 Şifre Değiştir</button>
    <button class="btn btn-danger btn-sm" onclick="logout()">Çıkış</button>
</div>
</div>
<div class="nav" id="navBar"></div>
<div class="container" id="mainContent"></div>
</div>
<div class="modal" id="modal"><div class="modal-content" id="modalContent"></div></div>
<footer>Created by İlhan Akdeniz</footer>
<script>
const DAYS=['Pazartesi','Salı','Çarşamba','Perşembe','Cuma','Cumartesi','Pazar'];
const INITIAL_MOVABLE_HOLIDAYS=[
{date:'2025-03-30',name:'Ramazan Bayramı 1. Gün'},{date:'2025-03-31',name:'Ramazan Bayramı 2. Gün'},
{date:'2025-04-01',name:'Ramazan Bayramı 3. Gün'},{date:'2025-06-06',name:'Kurban Bayramı 1. Gün'},
{date:'2025-06-07',name:'Kurban Bayramı 2. Gün'},{date:'2025-06-08',name:'Kurban Bayramı 3. Gün'},
{date:'2025-06-09',name:'Kurban Bayramı 4. Gün'}
];

let data={users:[],teachers:[],courses:[],students:[],attendance:[],holidays:[],buildings:[],classes:[],settings:{}};
let currentUser=null;
let currentViewDate=new Date();
let viewMode = 'week'; 
let currentBuildingFilter = localStorage.getItem('lastBuilding') || "";

async function apiCall(action, payload = null) {
    try {
        const url = '?action=' + action;
        const options = payload ? {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify(payload)
        } : undefined;
        const res = await fetch(url, options);
        if(!res.ok) throw new Error("Sunucu hatası");
        return await res.json();
    } catch(e) {
        alert("Bir hata oluştu: " + e.message);
        return null;
    }
}

async function refreshData() {
    const res = await apiCall('get_all_data');
    if(res) {
        data = res;
        if(!data.holidays || data.holidays.length === 0) data.holidays = [...INITIAL_MOVABLE_HOLIDAYS];
        if(currentUser) showApp(false); 
    }
}

document.addEventListener('DOMContentLoaded', () => {
    const savedUser = localStorage.getItem('savedUser');
    const savedPass = localStorage.getItem('savedPass');
    if(savedUser && savedPass) {
        document.getElementById('loginUser').value = savedUser;
        document.getElementById('loginPass').value = atob(savedPass);
        document.getElementById('rememberMe').checked = true;
    }
});

async function apiLogin() {
    const u = document.getElementById('loginUser').value;
    const p = document.getElementById('loginPass').value;
    const remember = document.getElementById('rememberMe').checked;

    const res = await apiCall('login', {username:u, password:p});
    if(res && res.status === 'success') {
        currentUser = res.user;
        
        if(remember) {
            localStorage.setItem('savedUser', u);
            localStorage.setItem('savedPass', btoa(p));
        } else {
            localStorage.removeItem('savedUser');
            localStorage.removeItem('savedPass');
        }

        await refreshData();
        showApp(true);
    } else {
        alert(res ? res.message : 'Giriş başarısız');
    }
}

function logout(){
    currentUser=null;
    document.getElementById('loginPage').classList.remove('hidden');
    document.getElementById('mainApp').classList.add('hidden');
}

function showApp(firstTime){
    document.getElementById('loginPage').classList.add('hidden');
    document.getElementById('mainApp').classList.remove('hidden');
    document.getElementById('currentUser').textContent=currentUser.name+' ('+currentUser.role+')';
    renderNav();
    if(firstTime || document.querySelector('.calendar')) showCalendar();
}

function renderNav(){
    const isAdmin=currentUser.role==='admin';
    let html=`<button class="active" onclick="showCalendar()">📅 Takvim</button>`;
    if(isAdmin)html+=`<button onclick="showCourses()">📚 Kurslar</button><button onclick="showTeachers()">👨‍🏫 Öğretmenler</button>`;
    html+=`<button onclick="showStudents()">👨‍🎓 Öğrenciler</button>`;
    html+=`<button onclick="showReports()">📊 Raporlar</button>`;
    if(isAdmin)html+=`<button onclick="showAdmin()">⚙️ Ayarlar</button>`;
    document.getElementById('navBar').innerHTML=html;
}
function setActiveNav(idx){document.querySelectorAll('.nav button').forEach((b,i)=>b.classList.toggle('active',i===idx))}
function formatDate(d){
    const year = d.getFullYear();
    const month = String(d.getMonth() + 1).padStart(2, '0');
    const day = String(d.getDate()).padStart(2, '0');
    return `${year}-${month}-${day}`;
}
function getContrastYIQ(hexcolor){
    if(!hexcolor) return 'black';
    hexcolor = hexcolor.replace("#", "");
    var r = parseInt(hexcolor.substr(0,2),16);
    var g = parseInt(hexcolor.substr(2,2),16);
    var b = parseInt(hexcolor.substr(4,2),16);
    var yiq = ((r*299)+(g*587)+(b*114))/1000;
    return (yiq >= 128) ? 'black' : 'white';
}
function getHoliday(dateStr){
    const [y, m, d] = dateStr.split('-').map(Number);
    if(m===1&&d===1)return 'Yılbaşı';
    if(m===4&&d===23)return '23 Nisan Ulusal Egemenlik ve Çocuk Bayramı';
    if(m===5&&d===1)return '1 Mayıs Emek ve Dayanışma Günü';
    if(m===5&&d===19)return '19 Mayıs Atatürk\'ü Anma, Gençlik ve Spor Bayramı';
    if(m===7&&d===15)return '15 Temmuz Demokrasi ve Milli Birlik Günü';
    if(m===8&&d===30)return '30 Ağustos Zafer Bayramı';
    if(m===10&&d===29)return '29 Ekim Cumhuriyet Bayramı';
    const custom = data.holidays.find(h=>h.date===dateStr);
    if(custom) return custom.name;
    return null;
}

// --- ŞİFRE DEĞİŞTİRME ---
function openChangePasswordModal() {
    let html=`<div class="modal-header"><h2>🔑 Şifre Değiştir</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Yeni Şifre</label><input type="password" id="newPass"></div>
    <div class="form-group"><label>Yeni Şifre (Tekrar)</label><input type="password" id="newPass2"></div>
    <button class="btn btn-primary" onclick="changePassword()">Değiştir</button>`;
    showModal(html);
}

async function changePassword() {
    const p1 = document.getElementById('newPass').value;
    const p2 = document.getElementById('newPass2').value;
    if(p1 !== p2) return alert("Şifreler uyuşmuyor!");
    if(p1.length < 4) return alert("Şifre en az 4 karakter olmalı!");

    await apiCall('change_password', {id: currentUser.id, role: currentUser.role, newPass: p1});
    alert("Şifreniz başarıyla güncellendi.");
    closeModal();
}

// --- TAKVİM ---
function showCalendar(){
    setActiveNav(0);
    let dates=[];
    let title="";

    let customStart = document.getElementById('calStart')?.value;
    let customEnd = document.getElementById('calEnd')?.value;

    if(viewMode==='week'){
        const day=currentViewDate.getDay(),diff=currentViewDate.getDate()-day+(day===0?-6:1);
        const startOfWeek=new Date(currentViewDate); startOfWeek.setDate(diff);
        dates = Array.from({length:7},(_,i)=>{const dt=new Date(startOfWeek);dt.setDate(startOfWeek.getDate()+i);return dt});
        title = `${dates[0].toLocaleDateString('tr-TR')} - ${dates[6].toLocaleDateString('tr-TR')}`;
    } else if(viewMode === 'month') {
        const year=currentViewDate.getFullYear(), month=currentViewDate.getMonth();
        const firstDay=new Date(year,month,1);
        let startDayIndex = firstDay.getDay() === 0 ? 6 : firstDay.getDay() - 1; 
        let iterDate = new Date(firstDay);
        iterDate.setDate(iterDate.getDate() - startDayIndex);
        title = firstDay.toLocaleDateString('tr-TR',{month:'long', year:'numeric'});
        for(let i=0; i<42; i++){dates.push(new Date(iterDate));iterDate.setDate(iterDate.getDate()+1);}
    } else if (viewMode === 'custom' && customStart && customEnd) {
        let d = new Date(customStart);
        const e = new Date(customEnd);
        while(d <= e) {
            dates.push(new Date(d));
            d.setDate(d.getDate() + 1);
        }
        title = `${new Date(customStart).toLocaleDateString('tr-TR')} - ${new Date(customEnd).toLocaleDateString('tr-TR')}`;
    }

    let html=`<div class="card">
    <div class="filter-row" style="margin-bottom:10px; padding:10px; background:#e3f2fd; align-items:flex-end;">
        <div class="form-group" style="margin:0;">
            <label>Tesis Filtrele</label>
            <select id="calFilterBuilding" onchange="applyCalendarFilter()">
                <option value="">Tümü</option>
                ${data.buildings.map(b=>`<option value="${b}" ${currentBuildingFilter===b?'selected':''}>${b}</option>`).join('')}
            </select>
        </div>
        <div class="form-group" style="margin:0;">
            <label>Başlangıç</label>
            <input type="date" id="calStart" value="${customStart||''}">
        </div>
        <div class="form-group" style="margin:0;">
            <label>Bitiş</label>
            <input type="date" id="calEnd" value="${customEnd||''}">
        </div>
        <div class="form-group" style="margin:0;">
             <button class="btn btn-primary" onclick="applyCalendarFilter()">Uygula</button>
        </div>
    </div>

    <div class="week-nav">
        <div><button class="btn btn-primary" onclick="changeDate(-1)">◀ Önceki</button>
        <button class="btn btn-primary" onclick="changeDate(1)">Sonraki ▶</button></div>
        <h2 style="margin:0;border:none">${title}</h2>
        <div class="view-toggle">
            <button class="btn btn-outline ${viewMode==='week'?'active':''}" onclick="toggleView('week')">Hafta</button>
            <button class="btn btn-outline ${viewMode==='month'?'active':''}" onclick="toggleView('month')">Ay</button>
        </div>
    </div>
    
    <div class="calendar ${viewMode==='month'?'month-view':''}"><div class="cal-header">Pazartesi</div><div class="cal-header">Salı</div><div class="cal-header">Çarşamba</div>
    <div class="cal-header">Perşembe</div><div class="cal-header">Cuma</div><div class="cal-header">Cumartesi</div><div class="cal-header">Pazar</div>`;
    
    dates.forEach((dt,i)=>{
        const ds=formatDate(dt),today=formatDate(new Date())===ds,holidayName=getHoliday(ds);
        const isOtherMonth = viewMode==='month' && dt.getMonth() !== currentViewDate.getMonth();
        let classes = "cal-cell";
        if(today) classes += " today";
        if(isOtherMonth) classes += " other-month";
        if(holidayName) classes += " holiday"; else if(dt.getDay()===0 || dt.getDay()===6) classes += " weekend";
        
        const courses=getCoursesForDate(ds);
        
        html+=`<div class="${classes}" data-day="${DAYS[dt.getDay()===0?6:dt.getDay()-1]}" onclick="openDayModal('${ds}')">
        <div class="cal-date"><span>${dt.getDate()} ${viewMode==='week'?dt.toLocaleDateString('tr-TR',{month:'short'}):''}</span>
        ${holidayName ? '<span>🇹🇷</span>' : ''}</div>`;
        if(holidayName)html+=`<span class="cal-holiday-label">${holidayName}</span>`;
        courses.forEach(c=>{
            const cancelled=c.cancelledDates&&c.cancelledDates.includes(ds);
            const mod=c.modifications&&c.modifications[ds];
            const bgColor = c.color || '#e3f2fd';
            const txtColor = getContrastYIQ(bgColor);
            html+=`<div class="course-tag${cancelled?' cancelled':''}" style="background-color:${bgColor};color:${txtColor}" 
            onclick="event.stopPropagation();openCourseDetail('${c.id}','${ds}')">
            ${c.name} <small>${mod?mod.time:c.time}</small></div>`;
        });
        html+=`</div>`;
    });
    html+=`</div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}

function applyCalendarFilter(){
    currentBuildingFilter = document.getElementById('calFilterBuilding').value;
    localStorage.setItem('lastBuilding', currentBuildingFilter); 

    const s = document.getElementById('calStart').value;
    const e = document.getElementById('calEnd').value;
    if(s && e) {
        viewMode = 'custom';
    } else if (viewMode === 'custom') {
        viewMode = 'week';
    }
    showCalendar();
    document.getElementById('calStart').value = s;
    document.getElementById('calEnd').value = e;
}

function toggleView(mode){viewMode=mode;showCalendar()}
function changeDate(dir){if(viewMode==='week') currentViewDate.setDate(currentViewDate.getDate()+dir*7);else currentViewDate.setMonth(currentViewDate.getMonth()+dir);showCalendar()}

function getCoursesForDate(ds){
    const dt=new Date(ds),dayName=DAYS[dt.getDay()===0?6:dt.getDay()-1];
    return data.courses.filter(c=>{
        if(ds<c.startDate||ds>c.endDate) return false;
        if(c.day!==dayName) return false;
        if(currentBuildingFilter && c.building !== currentBuildingFilter) return false;
        if(currentUser && currentUser.role === 'teacher' && c.teacherId != currentUser.id) return false;
        return true;
    });
}

function openDayModal(ds){
    const isAdmin=currentUser.role==='admin',courses=getCoursesForDate(ds);
    const holidayName=getHoliday(ds);
    let html=`<div class="modal-header"><h2>📅 ${new Date(ds).toLocaleDateString('tr-TR',{weekday:'long',day:'numeric',month:'long',year:'numeric'})}</h2>
    <span class="modal-close" onclick="closeModal()">×</span></div>`;
    if(holidayName)html+=`<div class="conflict" style="background:#f8d7da;border-left-color:#dc3545">🎉 Resmi Tatil: ${holidayName}</div>`;
    html+=`<h3 style="margin:15px 0">Bu Günün Kursları</h3>`;
    if(courses.length===0)html+=`<p style="color:#888">Bu gün için kurs bulunmuyor.</p>`;
    courses.forEach(c=>{
        const cancelled=c.cancelledDates&&c.cancelledDates.includes(ds);
        const mod=c.modifications&&c.modifications[ds];
        html+=`<div class="card" style="margin:10px 0;${cancelled?'opacity:0.5':''}">
        <strong style="color:${c.color||'#333'}">● ${c.name}</strong> ${cancelled?'(İPTAL)':''}<br>
        <small>⏰ ${mod?mod.time:c.time} | 📍 ${mod?mod.classroom:c.classroom} | 🏢 ${mod?mod.building:c.building}</small><br>
        <small>👨‍🏫 ${data.teachers.find(t=>t.id==c.teacherId)?.name||'Atanmamış'}</small><br>`;
        if(isAdmin){
            if(!cancelled){
                html+=`<button class="btn btn-warning btn-sm" onclick="modifyCourse(${c.id},'${ds}')">Değiştir</button>
                <button class="btn btn-danger btn-sm" onclick="cancelCourse(${c.id},'${ds}')">İptal Et</button>`;
            } else {
                html+=`<button class="btn btn-success btn-sm" onclick="activateCourse(${c.id},'${ds}')">✅ Tekrar Aktif Et</button>`;
            }
        }
        if(!cancelled){
        html+=`<button class="btn btn-success btn-sm" onclick="openAttendance(${c.id},'${ds}')">Yoklama</button>`;
        }
        html+=`</div>`;
    });
    if(isAdmin){html+=`<hr style="margin:20px 0"><button class="btn btn-primary" onclick="openNewCourseModal('${ds}')">+ Bu Güne Kurs Ekle</button>`;}
    showModal(html);
}
function openCourseDetail(cid,ds){openAttendance(cid,ds)}

function openAttendance(cid,ds){
    const course=data.courses.find(c=>c.id==cid);
    if(!course)return;
    const students=data.students.filter(s=>s.courses && s.courses.includes(parseInt(cid)));
    const att=data.attendance.filter(a=>a.courseId==cid&&a.date===ds);
    let html=`<div class="modal-header"><h2>📋 Yoklama: ${course.name}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <p><strong>Tarih:</strong> ${new Date(ds).toLocaleDateString('tr-TR')}</p>
    <div class="attendance-list" style="margin-top:15px">`;
    if(students.length===0)html+=`<p style="color:#888">Bu kursa kayıtlı öğrenci yok.</p>`;
    students.forEach(s=>{
        const present=att.find(a=>a.studentId===s.id);
        html+=`<div class="attendance-item"><span style="cursor:pointer;text-decoration:underline" onclick="openStudentInfo(${s.id})">${s.name} ${s.surname}</span>
        <div class="attendance-actions">
        <button class="btn ${present?.status==='present'?'btn-success':'btn-secondary'}" onclick="markAttendance(${cid},'${ds}',${s.id},'present')">✓</button>
        <button class="btn ${present?.status==='absent'?'btn-danger':'btn-secondary'}" onclick="markAttendance(${cid},'${ds}',${s.id},'absent')">✗</button>
        <button class="btn ${present?.status==='excused'?'btn-info':'btn-secondary'}" onclick="markAttendance(${cid},'${ds}',${s.id},'excused')">M</button>
        </div></div>`;
    });
    html+=`</div>`;
    showModal(html);
}
function openStudentInfo(sid){
    const s = data.students.find(st => st.id === sid);
    if(!s) return;
    const courseNames = s.courses ? s.courses.map(cid => {
        const c = data.courses.find(x => x.id == cid);
        return c ? c.name : '';
    }).filter(Boolean).join(', ') : '';
    const birthDate = s.date_of_birth ? new Date(s.date_of_birth).toLocaleDateString('tr-TR') : null;
    const age = calculateStudentAge(s.date_of_birth);
    const ageLine = age !== null ? `Yaş: ${age}` : 'Yaş bilgisi bulunamadı';
    const ageInfo = age !== null ? `Doğum Tarihi: ${birthDate}<br>Yaş: ${age}` : 'Yaş bilgisi bulunamadı';
    let html=`<div class="modal-header"><h2>👨‍🎓 Öğrenci Bilgileri</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div id="studentInfoPanel">
    <div class="tabs" id="studentInfoTabs">
        <button class="tab active" onclick="showStudentInfoTab(0)">Genel Bilgiler</button>
        <button class="tab" onclick="showStudentInfoTab(1)">Yaş Bilgisi</button>
    </div>
    <div class="tab-content active" data-student-tab-content>
    <div class="form-group"><label>Ad Soyad</label><div>${s.name} ${s.surname}</div></div>
    <div class="form-group"><label>TC Kimlik</label><div>${s.tc||'-'}</div></div>
    <div class="form-group"><label>Doğum Tarihi</label><div>${birthDate || '-'}<br>${ageLine}</div></div>
    <div class="form-group"><label>Eğitim Durumu</label><div>${s.education||'-'}</div></div>
    <div class="form-group"><label>Kendi Telefonu</label><div>${s.phone||'-'}</div></div>
    <div class="form-group"><label>E-posta</label><div>${s.email||'-'}</div></div>
    <div class="form-group"><label>Veli Adı</label><div>${s.parent_name||'-'}</div></div>
    <div class="form-group"><label>Veli Telefonu</label><div>${s.parent_phone||'-'}</div></div>
    <div class="form-group"><label>Kurslar</label><div>${courseNames||'-'}</div></div>
    </div>
    <div class="tab-content" data-student-tab-content>
        <div class="form-group"><label>Yaş Bilgisi</label><div>${ageInfo}</div></div>
    </div>
    </div>`;
    showModal(html);
}
function showStudentInfoTab(idx){
    const panel = document.getElementById('studentInfoPanel');
    if(!panel) return;
    const tabs = panel.querySelectorAll('#studentInfoTabs .tab');
    const contents = panel.querySelectorAll('[data-student-tab-content]');
    tabs.forEach((t,i)=>t.classList.toggle('active',i===idx));
    contents.forEach((c,i)=>c.classList.toggle('active',i===idx));
}
function calculateStudentAge(dateString){
    if(!dateString) return null;
    const birthDate = new Date(dateString);
    if(Number.isNaN(birthDate.getTime())) return null;
    const today = new Date();
    let age = today.getFullYear() - birthDate.getFullYear();
    const monthDiff = today.getMonth() - birthDate.getMonth();
    if (monthDiff < 0 || (monthDiff === 0 && today.getDate() < birthDate.getDate())) {
        age -= 1;
    }
    return age;
}
async function markAttendance(cid,ds,sid,status){
    await apiCall('save_attendance', {courseId:cid, date:ds, studentId:sid, status:status});
    await refreshData();
    openAttendance(cid,ds);
}
async function cancelCourse(cid,ds){
    if(!confirm('Bu günün dersini iptal etmek istiyor musunuz?'))return;
    const course=data.courses.find(c=>c.id==cid);
    if(!course.cancelledDates)course.cancelledDates=[];
    course.cancelledDates.push(ds);
    await apiCall('save_course', course);
    await refreshData();
    closeModal();
    showCalendar();
}
async function activateCourse(cid,ds){
    if(!confirm('Bu dersi tekrar aktif etmek istiyor musunuz?')) return;
    const course=data.courses.find(c=>c.id==cid);
    if(course && course.cancelledDates){
        course.cancelledDates = course.cancelledDates.filter(d => d !== ds);
        await apiCall('save_course', course);
        await refreshData();
        closeModal();
        showCalendar();
    }
}

function modifyCourse(cid,ds){
    const course=data.courses.find(c=>c.id==cid);
    const mod=course.modifications&&course.modifications[ds]||{};
    let html=`<div class="modal-header"><h2>✏️ Ders Değişikliği</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Yeni Saat</label><input type="text" id="modTime" value="${mod.time||course.time}" placeholder="15:00-17:00"></div>
    <div class="form-group"><label>Yeni Tesis</label><select id="modBuilding">${data.buildings.map(b=>`<option ${(mod.building||course.building)===b?'selected':''}>${b}</option>`).join('')}</select></div>
    <div class="form-group"><label>Yeni Sınıf</label><select id="modClass">${data.classes.map(c=>`<option ${(mod.classroom||course.classroom)===c?'selected':''}>${c}</option>`).join('')}</select></div>
    <div class="form-group"><label><input type="checkbox" id="modRange"> Tarih Aralığına Uygula</label></div>
    <div id="modRangeDates" class="hidden">
    <div class="form-group"><label>Başlangıç</label><input type="date" id="modStart" value="${ds}"></div>
    <div class="form-group"><label>Bitiş</label><input type="date" id="modEnd" value="${ds}"></div></div>
    <button class="btn btn-primary" onclick="saveModification(${cid},'${ds}')">Kaydet</button>`;
    showModal(html);
    document.getElementById('modRange').onchange=function(){document.getElementById('modRangeDates').classList.toggle('hidden',!this.checked)};
}
async function saveModification(cid,ds){
    const course=data.courses.find(c=>c.id==cid);
    if(!course.modifications)course.modifications={};
    const modData={time:document.getElementById('modTime').value,building:document.getElementById('modBuilding').value,classroom:document.getElementById('modClass').value};
    if(document.getElementById('modRange').checked){
        const start=document.getElementById('modStart').value,end=document.getElementById('modEnd').value;let d=new Date(start);
        while(formatDate(d)<=end){if(DAYS[d.getDay()===0?6:d.getDay()-1]===course.day)course.modifications[formatDate(d)]=modData;d.setDate(d.getDate()+1);}
    }else course.modifications[ds]=modData;
    await apiCall('save_course', course);
    await refreshData();
    closeModal();
    showCalendar();
}

function openNewCourseModal(ds){
    let defaultBuilding = data.buildings[0];
    if(currentBuildingFilter && currentBuildingFilter !== "") {
        defaultBuilding = currentBuildingFilter;
    }

    const defaults = {name:'',day:'Pazartesi',time:'',building:defaultBuilding,classroom:data.classes[0],teacherId:'',start:'',end:'',color:'#e3f2fd'};
    if(ds){const dt=new Date(ds);defaults.day=DAYS[dt.getDay()===0?6:dt.getDay()-1];defaults.start=ds;}
    let html=`<div class="modal-header"><h2>➕ Yeni Kurs</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Kurs Adı</label><input type="text" id="cName"></div>
    <div class="form-group"><label>Renk</label><input type="color" id="cColor" value="${defaults.color}"></div>
    <div class="form-group"><label>Gün</label><select id="cDay">${DAYS.map(d=>`<option ${d===defaults.day?'selected':''}>${d}</option>`).join('')}</select></div>
    <div class="form-group"><label>Saat (Örn: 15:30-22:30)</label><input type="text" id="cTime" placeholder="15:30-22:30"></div>
    <div class="form-group"><label>Tesis</label><select id="cBuilding">${data.buildings.map(b=>`<option ${b===defaults.building?'selected':''}>${b}</option>`).join('')}</select></div>
    <div class="form-group"><label>Sınıf/Atölye</label><select id="cClass">${data.classes.map(c=>`<option>${c}</option>`).join('')}</select></div>
    <div class="form-group"><label>Öğretmen</label><select id="cTeacher"><option value="">Seçiniz</option>${data.teachers.map(t=>`<option value="${t.id}">${t.name}</option>`).join('')}</select></div>
    <div class="form-group"><label>Başlangıç Tarihi</label><input type="date" id="cStart" value="${defaults.start}"></div>
    <div class="form-group"><label>Bitiş Tarihi</label><input type="date" id="cEnd"></div>
    <button class="btn btn-primary" onclick="saveCourse()">Kaydet</button>`;
    showModal(html);
}
async function saveCourse(){
    const c={id:document.getElementById('cName').getAttribute('data-id') || 'new',
        name:document.getElementById('cName').value,color:document.getElementById('cColor').value,
        day:document.getElementById('cDay').value,time:document.getElementById('cTime').value,
        building:document.getElementById('cBuilding').value,classroom:document.getElementById('cClass').value,
        teacherId:document.getElementById('cTeacher').value,startDate:document.getElementById('cStart').value,
        endDate:document.getElementById('cEnd').value,cancelledDates:[],modifications:{}};
    await apiCall('save_course', c);
    await refreshData();
    closeModal();
    showCalendar();
}

function showCourses(){
    setActiveNav(1);
    let html=`<div class="card"><h2>📚 Kurs Yönetimi</h2>
    
    <div class="filter-row" style="background:#e3f2fd; padding:10px; margin-bottom:15px; border-radius:5px;">
        <div class="form-group" style="margin:0; flex:0 0 200px;">
            <label>Tesis Filtrele</label>
            <select id="courseFilterBuilding" onchange="filterCourses()">
                <option value="">Tümü</option>
                ${data.buildings.map(b=>`<option>${b}</option>`).join('')}
            </select>
        </div>
        <div class="form-group" style="margin:0; align-self:flex-end;">
             <button class="btn btn-primary" onclick="openNewCourseModal()">+ Yeni Kurs</button>
        </div>
    </div>

    <div class="table-responsive"><table id="courseTable" style="margin-top:15px">
    <thead><tr><th>Kurs</th><th>Renk</th><th>Gün</th><th>Saat</th><th>Tesis</th><th>Sınıf</th><th>Öğretmen</th><th>İşlem</th></tr></thead>
    <tbody>`;
    data.courses.forEach(c=>{
        const t=data.teachers.find(x=>x.id==c.teacherId);
        html+=`<tr data-building="${c.building}"><td>${c.name}</td><td><span style="display:inline-block;width:20px;height:20px;background:${c.color||'#e3f2fd'};border:1px solid #ccc;border-radius:3px"></span></td>
        <td>${c.day}</td><td>${c.time}</td><td>${c.building}</td><td>${c.classroom}</td><td>${t?.name||'-'}</td>
        <td><button class="btn btn-warning" onclick="editCourse(${c.id})">✏️</button>
        <button class="btn btn-danger" onclick="deleteCourse(${c.id})">🗑️</button></td></tr>`;
    });
    html+=`</tbody></table></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}

function filterCourses(){
    const val = document.getElementById('courseFilterBuilding').value;
    const rows = document.querySelectorAll('#courseTable tbody tr');
    rows.forEach(r => {
        if(val === "" || r.getAttribute('data-building') === val) {
            r.style.display = "";
        } else {
            r.style.display = "none";
        }
    });
}

function editCourse(id){
    const c=data.courses.find(x=>x.id===id);
    openNewCourseModal();
    setTimeout(()=>{
        document.getElementById('cName').value=c.name;document.getElementById('cName').setAttribute('data-id', c.id);
        document.getElementById('cColor').value=c.color||'#e3f2fd';document.getElementById('cDay').value=c.day;
        document.getElementById('cTime').value=c.time;document.getElementById('cBuilding').value=c.building;
        document.getElementById('cClass').value=c.classroom;document.getElementById('cTeacher').value=c.teacherId;
        document.getElementById('cStart').value=c.startDate;document.getElementById('cEnd').value=c.endDate;
    }, 50);
}
async function deleteCourse(id){
    if(confirm('Kursu silmek istiyor musunuz?')){
        await apiCall('delete_course', {id});
        await refreshData();
        showCourses();
    }
}

function showTeachers(){
    setActiveNav(2);
    let html=`<div class="card"><h2>👨‍🏫 Öğretmen Yönetimi</h2>
    <div class="table-responsive"><button class="btn btn-primary" onclick="openTeacherModal()">+ Yeni Öğretmen</button>
    <table style="margin-top:15px"><tr><th>Ad Soyad</th><th>Telefon</th><th>E-posta</th><th>Kullanıcı Adı</th><th>İşlem</th></tr>`;
    data.teachers.forEach(t=>{
        html+=`<tr><td>${t.name}</td><td>${t.phone||'-'}</td><td>${t.email||'-'}</td><td>${t.username}</td>
        <td><button class="btn btn-warning" onclick="editTeacher(${t.id})">✏️</button>
        <button class="btn btn-danger" onclick="deleteTeacher(${t.id})">🗑️</button></td></tr>`;
    });
    html+=`</table></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}
function openTeacherModal(t){
    let html=`<div class="modal-header"><h2>${t?'✏️ Düzenle':'➕ Yeni Öğretmen'}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="form-group"><label>Ad Soyad</label><input type="text" id="tName" value="${t?.name||''}"></div>
    <div class="form-group"><label>Telefon</label><input type="text" id="tPhone" value="${t?.phone||''}"></div>
    <div class="form-group"><label>E-posta</label><input type="email" id="tEmail" value="${t?.email||''}"></div>
    <div class="form-group"><label>Kullanıcı Adı</label><input type="text" id="tUser" value="${t?.username||''}"></div>
    <div class="form-group"><label>Şifre</label><input type="password" id="tPass" value="${t?.password||''}"></div>
    <button class="btn btn-primary" onclick="saveTeacher(${t?.id||0})">${t?'Güncelle':'Kaydet'}</button>`;
    showModal(html);
}
function editTeacher(id){openTeacherModal(data.teachers.find(x=>x.id===id))}
async function saveTeacher(id){
    const t={id:id||'new',name:document.getElementById('tName').value,phone:document.getElementById('tPhone').value,
    email:document.getElementById('tEmail').value,username:document.getElementById('tUser').value,password:document.getElementById('tPass').value};
    await apiCall('save_teacher', t);
    await refreshData();
    closeModal();
    showTeachers();
}
async function deleteTeacher(id){
    if(confirm('Silmek istiyor musunuz?')){
        await apiCall('delete_teacher', {id});
        await refreshData();
        showTeachers();
    }
}

// --- ÖĞRENCİLER ---
function showStudents(){
    setActiveNav(currentUser.role==='admin'?3:4);
    
    // YENİ EKLENEN KISIM: Öğretmen Filtresi
    const isTeacher = currentUser.role === 'teacher';
    let availableCourses = data.courses;
    
    // Eğer öğretmense sadece kendi kurslarını filtreye koyacağız
    if(isTeacher){
        availableCourses = data.courses.filter(c => c.teacherId == currentUser.id);
    }

    let html=`<div class="card"><h2>👨‍🎓 Öğrenci Yönetimi</h2>
    <button class="btn btn-primary" onclick="openStudentModal()">+ Yeni Öğrenci</button>
    <div class="form-group" style="margin-top:15px"><label>Kurs Filtrele</label>
    <select id="studentFilter" onchange="filterStudents()">
        <option value="">Tümü</option>
        ${availableCourses.map(c=>`<option value="${c.id}">${c.name}</option>`).join('')}
    </select></div>
    <div class="table-responsive"><table style="margin-top:15px"><tr><th>Ad</th><th>Soyad</th><th>Veli</th><th>Telefon</th><th>Kurslar</th><th>İşlem</th></tr>`;
    
    data.students.forEach(s=>{
        // EĞER ÖĞRETMENSE VE BU ÖĞRENCİ ÖĞRETMENİN HİÇBİR KURSUNA KAYITLI DEĞİLSE TABLOYA EKLEME
        if(isTeacher) {
            // Öğrencinin aldığı kurslardan en az biri öğretmenin kursları içinde var mı?
            const hasTeacherCourse = s.courses && s.courses.some(cid => availableCourses.find(ac => ac.id == cid));
            if(!hasTeacherCourse) return;
        }

        const courseNames = s.courses ? s.courses.map(cid => {const c = data.courses.find(x => x.id == cid); return c ? c.name : '';}).filter(n=>n).join(', ') : '';
        const courseIds = s.courses ? s.courses.join(',') : '';
        html+=`<tr data-courses="${courseIds}"><td>${s.name}</td><td>${s.surname}</td>
        <td>${s.parent_name||'-'} (${s.parent_phone||'-'})</td>
        <td>${s.phone||'-'}</td>
        <td>${courseNames||'-'}</td>
        <td><button class="btn btn-warning" onclick="editStudent(${s.id})">✏️</button>
        <button class="btn btn-danger" onclick="deleteStudent(${s.id})">🗑️</button></td></tr>`;
    });
    html+=`</table></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}
function filterStudents(){
    const v=document.getElementById('studentFilter').value;
    document.querySelectorAll('tr[data-courses]').forEach(r=>{
        if(!v) r.style.display=''; else { const studentCourses = r.dataset.courses.split(','); r.style.display = studentCourses.includes(v) ? '' : 'none'; }
    });
}
function openStudentModal(s){
    let html=`<div class="modal-header"><h2>${s?'✏️ Düzenle':'➕ Yeni Öğrenci'}</h2><span class="modal-close" onclick="closeModal()">×</span></div>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>Ad</label><input type="text" id="sName" value="${s?.name||''}"></div>
        <div class="form-group"><label>Soyad</label><input type="text" id="sSurname" value="${s?.surname||''}"></div>
    </div>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>TC Kimlik</label><input type="text" id="sTc" value="${s?.tc||''}"></div>
        <div class="form-group"><label>Doğum Tarihi</label><input type="date" id="sDob" value="${s?.date_of_birth||''}"></div>
    </div>
    <div class="form-group"><label>Eğitim Durumu</label><input type="text" id="sEducation" value="${s?.education||''}"></div>
    <div class="form-group"><label>Kendi Telefonu</label><input type="text" id="sPhone" value="${s?.phone||''}"></div>
    <div class="form-group"><label>E-posta</label><input type="email" id="sEmail" value="${s?.email||''}"></div>
    <hr>
    <div class="filter-row" style="background:none; border:none; padding:0; margin:0;">
        <div class="form-group"><label>Veli Adı</label><input type="text" id="sParentName" value="${s?.parent_name||''}"></div>
        <div class="form-group"><label>Veli Telefonu</label><input type="text" id="sParentPhone" value="${s?.parent_phone||''}"></div>
    </div>
    <div class="form-group"><label>Kurslar</label><div class="checkbox-group">`;
    data.courses.forEach(c => {
        const isChecked = s && s.courses && s.courses.includes(c.id);
        html += `<div class="checkbox-item"><input type="checkbox" name="courseSelect" value="${c.id}" ${isChecked ? 'checked' : ''}><span>${c.name}</span></div>`;
    });
    html+=`</div></div><button class="btn btn-primary" onclick="saveStudent(${s?.id||0})">${s?'Güncelle':'Kaydet'}</button>`;
    showModal(html);
}
function editStudent(id){openStudentModal(data.students.find(x=>x.id===id))}
async function saveStudent(id){
    const selectedCourses = Array.from(document.querySelectorAll('input[name="courseSelect"]:checked')).map(cb => parseInt(cb.value));
    const s={
        id:id||'new',
        name:document.getElementById('sName').value,
        surname:document.getElementById('sSurname').value,
        phone:document.getElementById('sPhone').value,
        email:document.getElementById('sEmail').value,
        tc:document.getElementById('sTc').value,
        date_of_birth:document.getElementById('sDob').value,
        education:document.getElementById('sEducation').value,
        parent_name:document.getElementById('sParentName').value,
        parent_phone:document.getElementById('sParentPhone').value,
        courses: selectedCourses
    };
    await apiCall('save_student', s);
    await refreshData();
    closeModal();
    showStudents();
}
async function deleteStudent(id){
    if(confirm('Silmek istiyor musunuz?')){
        await apiCall('delete_student', {id});
        await refreshData();
        showStudents();
    }
}

// --- RAPORLAR ---
function showReports(){
    setActiveNav(4);
    const isTeacher = currentUser.role === 'teacher';
    
    // Öğretmen ise sadece kendi adını seçili getir, disabled yap
    let teacherSelect = '';
    // Kurs seçimi için sadece kendi kurslarını göster
    let courseOptions = '<option value="">Tümü</option>';
    let studentOptions = '<option value="">Tümü</option>';

    let availableCourses = data.courses;
    let availableStudents = data.students;

    if(isTeacher) {
        teacherSelect = `<select id="rTeacher" disabled><option value="${currentUser.id}">${currentUser.name}</option></select>`;
        availableCourses = data.courses.filter(c => c.teacherId == currentUser.id);
        
        // Sadece bu kurslara kayıtlı öğrencileri bul
        const teacherCourseIds = availableCourses.map(c => c.id);
        availableStudents = data.students.filter(s => {
            return s.courses && s.courses.some(cid => teacherCourseIds.includes(cid));
        });

    } else {
        teacherSelect = `<select id="rTeacher"><option value="">Tümü</option>${data.teachers.map(t=>`<option value="${t.id}">${t.name}</option>`).join('')}</select>`;
    }

    courseOptions += availableCourses.map(c=>`<option value="${c.id}">${c.name}</option>`).join('');
    studentOptions += availableStudents.map(s=>`<option value="${s.id}">${s.name} ${s.surname}</option>`).join('');

    let html=`<div class="card"><h2>📊 Raporlar</h2>
    <div class="stats"><div class="stat-card"><h3>${data.courses.length}</h3><p>Toplam Kurs</p></div>
    <div class="stat-card"><h3>${data.teachers.length}</h3><p>Öğretmen</p></div>
    <div class="stat-card"><h3>${data.students.length}</h3><p>Öğrenci</p></div>
    <div class="stat-card"><h3>${data.attendance.filter(a=>a.status==='absent').length}</h3><p>Devamsızlık</p></div>
    <div class="stat-card"><h3>${data.attendance.filter(a=>a.status==='excused').length}</h3><p>Mazeretli</p></div></div>

    <div class="filter-row">
    <div class="form-group"><label>Kurs</label><select id="rCourse">${courseOptions}</select></div>
    <div class="form-group"><label>Öğretmen</label>${teacherSelect}</div>
    <div class="form-group"><label>Öğrenci</label><select id="rStudent">${studentOptions}</select></div>
    <div class="form-group"><label>Durum</label><select id="rStatus"><option value="">Tümü</option><option value="present">Geldi</option><option value="absent">Gelmedi</option><option value="excused">Mazeretli</option></select></div>
    <div class="form-group"><label>Başlangıç</label><input type="date" id="rStart"></div>
    <div class="form-group"><label>Bitiş</label><input type="date" id="rEnd"></div>
    <div class="form-group" style="align-self: flex-end;"><button class="btn btn-primary" style="width:100%" onclick="generateReport()">Rapor Oluştur</button></div></div>
    <div id="reportResult"></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
}
function generateReport(){
    const cid=document.getElementById('rCourse').value;
    const sid=document.getElementById('rStudent').value, status=document.getElementById('rStatus').value,
    start=document.getElementById('rStart').value, end=document.getElementById('rEnd').value;
    
    let tid = "";
    if(currentUser.role === 'teacher') {
        tid = currentUser.id;
    } else {
        tid = document.getElementById('rTeacher').value;
    }

    let filtered=data.attendance;
    if(cid) filtered = filtered.filter(a => a.courseId == cid);
    if(sid) filtered = filtered.filter(a => a.studentId == sid);
    if(status) filtered = filtered.filter(a => a.status === status);
    if(start) filtered = filtered.filter(a => a.date >= start);
    if(end) filtered = filtered.filter(a => a.date <= end);
    
    if(tid) { 
        const teacherCourseIds = data.courses.filter(c => c.teacherId == tid).map(c => c.id); 
        filtered = filtered.filter(a => teacherCourseIds.includes(parseInt(a.courseId))); 
    }

    const absent=filtered.filter(a=>a.status==='absent'), excused=filtered.filter(a=>a.status==='excused');
    let html=`<h3 style="margin:20px 0">Rapor Sonuçları</h3>
    <p>Toplam Kayıt: ${filtered.length} | Devamsızlık: ${absent.length} | Mazeretli: ${excused.length}</p>
    <div class="table-responsive"><table id="reportTable"><tr><th>Öğrenci</th><th>Kurs</th><th>Öğretmen</th><th>Tarih</th><th>Durum</th></tr>`;
    filtered.forEach(a=>{
        const s=data.students.find(x=>x.id===a.studentId), c=data.courses.find(x=>x.id==a.courseId), t=c?data.teachers.find(tr=>tr.id==c.teacherId):null;
        let statusText='?';
        if(a.status==='present') statusText='<span style="color:green">✓ Geldi</span>';
        else if(a.status==='absent') statusText='<span style="color:red">✗ Gelmedi</span>';
        else if(a.status==='excused') statusText='<span style="color:#17a2b8">M Mazeretli</span>';
        html+=`<tr><td>${s?.name} ${s?.surname}</td><td>${c?.name||'-'}</td><td>${t?.name||'-'}</td><td>${a.date}</td><td>${statusText}</td></tr>`;
    });
    html+=`</table></div>
    <div class="export-buttons">
    <button class="btn btn-success" onclick="downloadExcel()">📊 Excel</button>
    <button class="btn btn-primary" onclick="downloadWord()">📄 Word</button>
    <button class="btn btn-info" onclick="downloadHTML()">🌐 HTML</button>
    <button class="btn btn-secondary" onclick="printReport()">🖨️ Yazdır</button>
    </div>`;
    document.getElementById('reportResult').innerHTML=html;
}

// --- YÖNETİM ---
function showAdmin(){
    setActiveNav(5);
    let html=`<div class="card"><h2>⚙️ Yönetim Paneli</h2>
    <div class="tabs"><button class="tab active" onclick="showAdminTab(0)">Kullanıcılar</button>
    <button class="tab" onclick="showAdminTab(1)">Tesisler/Sınıflar</button>
    <button class="tab" onclick="showAdminTab(2)">Ek Tatiller</button>
    <button class="tab" onclick="showAdminTab(3)">Genel</button></div>
    <div id="adminContent"></div></div>`;
    document.getElementById('mainContent').innerHTML=html;
    showAdminTab(0);
}
function showAdminTab(idx){
    document.querySelectorAll('.tab').forEach((t,i)=>t.classList.toggle('active',i===idx));
    let html='';
    if(idx===0){
        html=`<h3>Yönetici Kullanıcılar</h3><div class="table-responsive"><button class="btn btn-primary" onclick="addUser()">+ Yeni Yönetici</button>
        <table style="margin-top:15px"><tr><th>Ad</th><th>Kullanıcı Adı</th><th>Rol</th><th>İşlem</th></tr>`;
        data.users.forEach(u=>{html+=`<tr><td>${u.name}</td><td>${u.username}</td><td>${u.role}</td><td>${u.id!==1?`<button class="btn btn-danger" onclick="deleteUser(${u.id})">🗑️</button>`:''}</td></tr>`;});
        html+=`</table></div>`;
    }else if(idx===1){
        html=`<h3>Tesisler</h3><div class="form-group"><input type="text" id="newBuilding" placeholder="Yeni tesis adı"><button class="btn btn-primary" onclick="addBuilding()">Ekle</button></div>
        <ul>${data.buildings.map((b,i)=>`<li>${b} <button class="btn btn-danger btn-sm" onclick="removeBuilding(${i})">×</button></li>`).join('')}</ul>
        <h3 style="margin-top:20px">Sınıflar/Atölyeler</h3><div class="form-group"><input type="text" id="newClass" placeholder="Yeni sınıf adı"><button class="btn btn-primary" onclick="addClass()">Ekle</button></div>
        <ul>${data.classes.map((c,i)=>`<li>${c} <button class="btn btn-danger btn-sm" onclick="removeClass(${i})">×</button></li>`).join('')}</ul>`;
    }else if(idx===2){
        html=`<h3>Resmi Tatil Yönetimi</h3><div class="form-group"><input type="date" id="newHolDate"><input type="text" id="newHolName" placeholder="Tatil adı"><button class="btn btn-primary" onclick="addHoliday()">Ekle</button></div>
        <div class="table-responsive"><table><tr><th>Tarih</th><th>Ad</th><th>İşlem</th></tr>`;
        const sortedHolidays = [...data.holidays].sort((a,b)=>a.date.localeCompare(b.date));
        sortedHolidays.forEach((h)=>{html+=`<tr><td>${h.date}</td><td>${h.name}</td><td><button class="btn btn-danger btn-sm" onclick="removeHoliday('${h.date}')">×</button></td></tr>`;});
        html+=`</table></div>`;
    }else{
        html=`<h3>Genel Ayarlar</h3><div class="form-group"><label>Kurum Adı</label><input type="text" id="settingTitle" value="${data.settings.title}"></div><button class="btn btn-primary" onclick="saveSettings()">Kaydet</button>
        <hr style="margin:20px 0"><h3>Veri Yönetimi</h3><button class="btn btn-danger" onclick="resetData()">🗑️ Tüm Verileri Sil (DİKKAT!)</button>`;
    }
    document.getElementById('adminContent').innerHTML=html;
}
function addUser(){let html=`<div class="modal-header"><h2>➕ Yeni Yönetici</h2><span class="modal-close" onclick="closeModal()">×</span></div><div class="form-group"><label>Ad Soyad</label><input type="text" id="uName"></div><div class="form-group"><label>Kullanıcı Adı</label><input type="text" id="uUser"></div><div class="form-group"><label>Şifre</label><input type="password" id="uPass"></div><button class="btn btn-primary" onclick="saveUser()">Kaydet</button>`;showModal(html);}
async function saveUser(){
    await apiCall('save_user', {name:document.getElementById('uName').value,username:document.getElementById('uUser').value,password:document.getElementById('uPass').value});
    await refreshData(); closeModal(); showAdmin();
}
async function deleteUser(id){if(confirm('Silmek istiyor musunuz?')){await apiCall('delete_user', {id}); await refreshData(); showAdmin();}}
async function addBuilding(){const v=document.getElementById('newBuilding').value;if(v){const b=[...data.buildings,v];await apiCall('save_meta',{key:'buildings',value:b});await refreshData();showAdminTab(1)}}
async function removeBuilding(i){const b=[...data.buildings];b.splice(i,1);await apiCall('save_meta',{key:'buildings',value:b});await refreshData();showAdminTab(1)}
async function addClass(){const v=document.getElementById('newClass').value;if(v){const c=[...data.classes,v];await apiCall('save_meta',{key:'classes',value:c});await refreshData();showAdminTab(1)}}
async function removeClass(i){const c=[...data.classes];c.splice(i,1);await apiCall('save_meta',{key:'classes',value:c});await refreshData();showAdminTab(1)}
async function addHoliday(){await apiCall('add_holiday',{date:document.getElementById('newHolDate').value,name:document.getElementById('newHolName').value});await refreshData();showAdminTab(2)}
async function removeHoliday(d){if(confirm('Silmek istiyor musunuz?')){await apiCall('delete_holiday',{date:d});await refreshData();showAdminTab(2)}}
async function saveSettings(){await apiCall('save_meta',{key:'title',value:document.getElementById('settingTitle').value});alert('Kaydedildi!');}
async function resetData(){if(confirm('TÜM VERİLER SİLİNECEK! Emin misiniz?')){await apiCall('reset_data');location.reload()}}

// MODAL & EXPORT
function showModal(html){document.getElementById('modalContent').innerHTML=html;document.getElementById('modal').style.display='block'}
function closeModal(){document.getElementById('modal').style.display='none'}
document.getElementById('modal').onclick=function(e){if(e.target===this)closeModal()};

function downloadExcel() {
    const table = document.getElementById('reportTable'); if(!table) return alert("Rapor yok!");
    const html = `\uFEFF<html xmlns:o="urn:schemas-microsoft-com:office:office" xmlns:x="urn:schemas-microsoft-com:office:excel"><head><meta charset="UTF-8"></head><body>${table.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'application/vnd.ms-excel' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'Rapor.xls'; a.click();
}
function downloadWord() {
    const table = document.getElementById('reportTable'); if(!table) return alert("Rapor yok!");
    const html = `\uFEFF<html><head><meta charset='utf-8'></head><body>${table.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'application/msword' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'Rapor.doc'; a.click();
}
function downloadHTML() {
    const table = document.getElementById('reportTable'); if(!table) return alert("Rapor yok!");
    const html = `<html><head><meta charset="UTF-8"><style>table{width:100%;border-collapse:collapse;}th,td{border:1px solid black;padding:8px;}th{background:#f2f2f2}</style></head><body><h2>Rapor</h2>${table.outerHTML}</body></html>`;
    const blob = new Blob([html], { type: 'text/html' });
    const a = document.createElement('a'); a.href = URL.createObjectURL(blob); a.download = 'Rapor.html'; a.click();
}
function printReport(){window.print()}
</script>
</body>
</html>
