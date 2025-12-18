<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Çeşme Belediyesi Kurs Yönetim Sistemi</title>
    <style>
        /* Önceki tasarımdan alınan temel stiller buraya eklenecek */
        :root {
            --primary-color: #005a9c;
            --secondary-color: #f0f8ff;
            --text-color: #333;
            --light-text-color: #fff;
            --border-color: #ddd;
            --header-height: 60px;
        }
        * { box-sizing: border-box; margin: 0; padding: 0; font-family: -apple-system, BlinkMacSystemFont, "Segoe UI", Roboto, "Helvetica Neue", Arial, sans-serif; }
        body { background-color: #f4f7fa; color: var(--text-color); line-height: 1.6; }
        .container { max-width: 1200px; margin: 0 auto; padding: 20px; }
        header { background-color: var(--primary-color); color: var(--light-text-color); padding: 0 20px; height: var(--header-height); display: flex; align-items: center; justify-content: space-between; box-shadow: 0 2px 4px rgba(0,0,0,0.1); }
        header h1 { font-size: 1.5rem; }

        /* Takvim Stilleri */
        .calendar-container { background-color: #fff; padding: 20px; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        .calendar-header { display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px; }
        .calendar-header h2 { color: var(--primary-color); }
        .calendar-grid { display: grid; grid-template-columns: 50px repeat(7, 1fr); gap: 1px; background-color: var(--border-color); }
        .grid-header, .time-slot, .day-cell { background-color: #fff; padding: 10px; text-align: center; }
        .grid-header { font-weight: bold; }
        .time-slot { font-size: 0.8rem; color: #666; border-right: 1px solid var(--border-color); }
        .day-cell { position: relative; min-height: 100px; padding: 2px; }
        .course-block { background-color: var(--primary-color); color: white; padding: 5px; border-radius: 4px; margin-bottom: 2px; font-size: 0.8em; overflow: hidden; cursor: pointer; }
        nav > * { margin-left: 15px; }
        .nav-button { background: none; border: none; color: white; cursor: pointer; font-size: 1em; text-decoration: none; }

        /* Floating Action Button (FAB) */
        .fab { position: fixed; bottom: 30px; right: 30px; width: 60px; height: 60px; border-radius: 50%; background-color: var(--primary-color); color: white; font-size: 2rem; border: none; box-shadow: 0 4px 8px rgba(0,0,0,0.2); cursor: pointer; }

        /* Modal Stilleri */
        .modal { display: none; position: fixed; z-index: 1000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.5); }
        .modal-content { background-color: #fefefe; margin: 10% auto; padding: 20px; border: 1px solid #888; width: 80%; max-width: 500px; border-radius: 8px; }
        .close-btn { color: #aaa; float: right; font-size: 28px; font-weight: bold; cursor: pointer; }
    </style>
<?php
require_once 'config.php';

// Oturum kontrolü
if (!isset($_SESSION['user_id'])) {
    header('Location: login.php');
    exit;
}
?>
</head>
<body>
    <header>
        <h1>Çeşme Belediyesi Kurs Yönetim Sistemi</h1>
        <nav>
            <span>Hoş geldiniz, <?php echo htmlspecialchars($_SESSION['full_name']); ?>!</span>
            <?php if (isAdmin()): ?>
                <a href="admin.php" class="nav-button">Yönetim Paneli</a>
            <?php endif; ?>
            <button id="logout-btn" class="nav-button">Çıkış Yap</button>
        </nav>
    </header>

    <main class="container">
        <!-- Ana Görünüm (Takvim veya Giriş Ekranı) -->
        <div id="app-view">
            <?php if (isAdmin()): ?>
            <button id="add-schedule-btn" class="fab">+</button>
            <?php endif; ?>

            <div class="calendar-container">
                <div class="calendar-header">
                    <button id="prev-week">Önceki Hafta</button>
                    <h2 id="week-title">Yükleniyor...</h2>
                    <button id="next-week">Sonraki Hafta</button>
                </div>
                <div class="calendar-grid" id="calendar-grid">
                    <!-- Takvim içeriği JavaScript ile doldurulacak -->
                </div>
            </div>
        </div>

        <!-- Kurs Programı Ekleme Modalı -->
        <div id="schedule-modal" class="modal">
            <div class="modal-content">
                <span class="close-btn">&times;</span>
                <h2>Yeni Kurs Programı</h2>
                <form id="schedule-form">
                    <div class="form-group">
                        <label for="course-id">Kurs Seçin</label>
                        <select id="course-id" name="course_id" required></select>
                        <small>Eğer kurs listede yoksa, önce <a href="admin.php">Yönetim Paneli</a>'nden ekleyin.</small>
                    </div>
                    <div class="form-group">
                        <label for="classroom-id">Sınıf / Atölye</label>
                        <select id="classroom-id" name="classroom_id" required></select>
                    </div>
                    <div class="form-group">
                        <label>Tekrarlama Ayarları</label>
                        <input type="date" name="start_date" required>
                        <span> - </span>
                        <input type="date" name="end_date" required>
                    </div>
                    <div class="form-group">
                        <select name="day_of_week" required>
                            <option value="1">Her Pazartesi</option>
                            <option value="2">Her Salı</option>
                            <option value="3">Her Çarşamba</option>
                            <option value="4">Her Perşembe</option>
                            <option value="5">Her Cuma</option>
                            <option value="6">Her Cumartesi</option>
                            <option value="0">Her Pazar</option>
                        </select>
                    </div>
                    <div class="form-group">
                        <input type="time" name="start_time" required>
                        <span> - </span>
                        <input type="time" name="end_time" required>
                    </div>
                     <div class="form-group">
                        <label>Öğrencileri Kaydet</label>
                        <div id="student-checklist" class="student-checklist"></div>
                    </div>
                    <button type="submit">Programı Oluştur</button>
                </form>
            </div>
        </div>

        <!-- Yoklama Modalı -->
        <div id="attendance-modal" class="modal">
             <div class="modal-content">
                <span class="close-btn">&times;</span>
                <h2 id="attendance-modal-title">Yoklama</h2>
                <form id="attendance-form">
                    <input type="hidden" name="schedule_id" id="attendance-schedule-id">
                    <div id="attendance-student-list" class="student-checklist">
                        <!-- Öğrenci listesi API'den yüklenecek -->
                    </div>
                    <button type="submit">Yoklamayı Kaydet</button>
                </form>
            </div>
        </div>

    </main>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            let currentWeekStartDate = getStartOfWeek(new Date());

            const calendarGrid = document.getElementById('calendar-grid');
            const weekTitle = document.getElementById('week-title');
            const prevWeekBtn = document.getElementById('prev-week');
            const nextWeekBtn = document.getElementById('next-week');

            /**
             * Verilen bir tarihin haftasının başlangıcını (Pazartesi) bulur.
             */
            function getStartOfWeek(date) {
                const d = new Date(date);
                const day = d.getDay();
                const diff = d.getDate() - day + (day === 0 ? -6 : 1);
                d.setDate(diff);
                d.setHours(0, 0, 0, 0);
                return d;
            }

            /**
             * API'den ders programı verilerini çeker ve takvimi günceller.
             */
            async function fetchAndRenderSchedules() {
                const weekStart = new Date(currentWeekStartDate);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 7);

                const startDateStr = weekStart.toISOString().split('T')[0];
                const endDateStr = weekEnd.toISOString().split('T')[0];

                try {
                    const response = await fetch(`api.php?action=get_schedules&start=${startDateStr}&end=${endDateStr}`);
                    if (!response.ok) {
                        throw new Error('Veri çekilemedi: ' + response.statusText);
                    }
                    const schedules = await response.json();
                    renderCalendar(schedules);
                } catch (error) {
                    console.error('API Hatası:', error);
                    calendarGrid.innerHTML = 'Veriler yüklenirken bir hata oluştu.';
                }
            }

            /**
             * Gelen ders programı verileriyle takvimi oluşturur.
             */
            function renderCalendar(schedules = []) {
                calendarGrid.innerHTML = ''; // Temizle

                const weekStart = new Date(currentWeekStartDate);
                const weekEnd = new Date(weekStart);
                weekEnd.setDate(weekEnd.getDate() + 6);

                // Başlığı güncelle
                weekTitle.textContent = `${weekStart.toLocaleDateString('tr-TR')} - ${weekEnd.toLocaleDateString('tr-TR')}`;

                // Grid başlıkları ve saatleri oluştur
                calendarGrid.innerHTML += `<div class="grid-header"></div>`;
                const days = ['Pazartesi', 'Salı', 'Çarşamba', 'Perşembe', 'Cuma', 'Cumartesi', 'Pazar'];
                days.forEach(day => {
                    calendarGrid.innerHTML += `<div class="grid-header">${day}</div>`;
                });

                for (let hour = 8; hour < 23; hour++) {
                    calendarGrid.innerHTML += `<div class="time-slot">${hour}:00</div>`;
                    for (let dayIndex = 0; dayIndex < 7; dayIndex++) {
                        calendarGrid.innerHTML += `<div class="day-cell" data-day-index="${dayIndex}" data-hour="${hour}"></div>`;
                    }
                }

                // Dersleri takvime yerleştir
                schedules.forEach(schedule => {
                    const startTime = new Date(schedule.start_time);
                    const dayIndex = (startTime.getDay() + 6) % 7; // Ptesi=0, Salı=1...
                    const hour = startTime.getHours();
                    const cell = calendarGrid.querySelector(`.day-cell[data-day-index='${dayIndex}'][data-hour='${hour}']`);

                    if (cell) {
                        const courseBlock = document.createElement('div');
                        courseBlock.className = 'course-block';
                        courseBlock.setAttribute('data-schedule-id', schedule.id);
                        courseBlock.innerHTML = `
                            <strong>${schedule.course_name}</strong><br>
                            <small>${schedule.classroom_name} (${schedule.building_name})</small><br>
                            <small>${startTime.toLocaleTimeString('tr-TR', { hour: '2-digit', minute: '2-digit' })}</small>
                        `;
                        cell.appendChild(courseBlock);
                    }
                });
            }

            // Olay dinleyicileri
            prevWeekBtn.addEventListener('click', () => {
                currentWeekStartDate.setDate(currentWeekStartDate.getDate() - 7);
                fetchAndRenderSchedules();
            });

            nextWeekBtn.addEventListener('click', () => {
                currentWeekStartDate.setDate(currentWeekStartDate.getDate() + 7);
                fetchAndRenderSchedules();
            });

            // Başlangıçta takvimi yükle
            fetchAndRenderSchedules();

            // Modal Yönetimi
            const scheduleModal = document.getElementById('schedule-modal');
            const addScheduleBtn = document.getElementById('add-schedule-btn');
            const closeModalBtn = scheduleModal.querySelector('.close-btn');
            const scheduleForm = document.getElementById('schedule-form');

            // Yoklama Modalı Elementleri
            const attendanceModal = document.getElementById('attendance-modal');
            const closeAttendanceModalBtn = attendanceModal.querySelector('.close-btn');
            const attendanceForm = document.getElementById('attendance-form');
            const attendanceStudentList = document.getElementById('attendance-student-list');
            const attendanceScheduleIdInput = document.getElementById('attendance-schedule-id');

            async function populateScheduleForm() {
                const response = await fetch('api.php?action=get_form_data');
                const data = await response.json();

                const courseSelect = document.getElementById('course-id');
                courseSelect.innerHTML = data.courses.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

                const classroomSelect = document.getElementById('classroom-id');
                classroomSelect.innerHTML = data.classrooms.map(c => `<option value="${c.id}">${c.name}</option>`).join('');

                const studentChecklist = document.getElementById('student-checklist');
                studentChecklist.innerHTML = data.students.map(s => `
                    <label><input type="checkbox" name="student_ids[]" value="${s.id}"> ${s.full_name}</label>
                `).join('');
            }

            if (addScheduleBtn) {
                addScheduleBtn.onclick = () => {
                    populateScheduleForm();
                    scheduleModal.style.display = 'block';
                };
            }
            closeModalBtn.onclick = () => { scheduleModal.style.display = 'none'; };

            scheduleForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(scheduleForm);
                const data = Object.fromEntries(formData.entries());

                // Checkbox'lardan öğrenci ID'lerini topla
                const studentIds = [];
                scheduleForm.querySelectorAll('input[name="student_ids[]"]:checked').forEach(checkbox => {
                    studentIds.push(checkbox.value);
                });
                data.student_ids = studentIds;

                const response = await fetch('api.php?action=add_schedule', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                const result = await response.json();
                if (response.ok) {
                    alert('Program başarıyla oluşturuldu!');
                    scheduleModal.style.display = 'none';
                    fetchAndRenderSchedules(); // Takvimi yenile
                } else {
                    alert('Hata: ' + result.error);
                }
            });

            window.onclick = (event) => {
                if (event.target == scheduleModal) {
                    scheduleModal.style.display = 'none';
                }
                if (event.target == attendanceModal) {
                    attendanceModal.style.display = 'none';
                }
            };

            // Takvimdeki derse tıklayınca yoklama modalını aç
            calendarGrid.addEventListener('click', async (e) => {
                const courseBlock = e.target.closest('.course-block');
                if (courseBlock) {
                    const scheduleId = courseBlock.getAttribute('data-schedule-id');
                    const response = await fetch(`api.php?action=get_attendance_data&schedule_id=${scheduleId}`);
                    const data = await response.json();

                    attendanceScheduleIdInput.value = scheduleId;
                    attendanceStudentList.innerHTML = data.students.map(s => {
                        const isChecked = data.present_ids.includes(s.id) ? 'checked' : '';
                        return `<label><input type="checkbox" name="present_ids[]" value="${s.id}" ${isChecked}> ${s.full_name}</label>`;
                    }).join('');

                    attendanceModal.style.display = 'block';
                }
            });

            closeAttendanceModalBtn.onclick = () => { attendanceModal.style.display = 'none'; };

            // Yoklamayı kaydet
            attendanceForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const formData = new FormData(attendanceForm);
                const presentIds = Array.from(formData.getAll('present_ids[]'));
                const scheduleId = formData.get('schedule_id');

                const response = await fetch('api.php?action=save_attendance', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ schedule_id: scheduleId, present_ids: presentIds })
                });

                const result = await response.json();
                if (response.ok) {
                    alert('Yoklama kaydedildi!');
                    attendanceModal.style.display = 'none';
                } else {
                    alert('Hata: ' + result.error);
                }
            });

            // Çıkış yap butonu
            document.getElementById('logout-btn').addEventListener('click', async () => {
                const response = await fetch('api.php?action=logout', { method: 'POST' });
                const result = await response.json();
                if (result.success) {
                    window.location.href = 'login.php';
                }
            });
        });
    </script>
</body>
</html>
