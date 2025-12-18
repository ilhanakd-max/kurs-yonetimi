<?php
require_once 'config.php';

// Sadece adminlerin bu sayfaya erişebilmesini sağla
if (!isAdmin()) {
    header('Location: index.php'); // veya bir "yetkiniz yok" sayfasına yönlendir
    exit;
}
?>
<!DOCTYPE html>
<html lang="tr">
<head>
    <meta charset="UTF-8">
    <title>Yönetim Paneli - Kurs Yönetim Sistemi</title>
    <style>
        /* index.php'den alınan temel stiller */
        :root { --primary-color: #005a9c; --border-color: #ddd; }
        body { font-family: sans-serif; background-color: #f4f7fa; }
        .container { max-width: 1000px; margin: 20px auto; padding: 20px; background-color: #fff; border-radius: 8px; box-shadow: 0 2px 10px rgba(0,0,0,0.1); }
        h1, h2, h3 { color: var(--primary-color); }
        a { color: var(--primary-color); }
        /* Form ve liste stilleri buraya eklenecek */
    </style>
</head>
<body>

    <div class="container">
        <a href="index.php">&larr; Takvime Geri Dön</a>
        <h1>Yönetim Paneli</h1>

        <!-- Sekmeler -->
        <div class="tabs">
            <button class="tab-button active" data-tab="students">Öğrenci Yönetimi</button>
            <button class="tab-button" data-tab="courses">Kurs Yönetimi</button>
            <button class="tab-button" data-tab="reports">Raporlama</button>
        </div>

        <!-- Öğrenci Yönetimi İçeriği -->
        <div id="students" class="tab-content active">
            <h2>Öğrenci Yönetimi</h2>
            <form id="student-form">
                <h3>Yeni Öğrenci Ekle</h3>
                <input type="text" id="student-name" placeholder="Öğrenci Adı Soyadı" required>
                <button type="submit">Ekle</button>
            </form>
            <h3>Mevcut Öğrenciler</h3>
            <ul id="student-list">
                <!-- Öğrenciler API'den dinamik olarak yüklenecek -->
            </ul>
        </div>

        <!-- Diğer sekmelerin içeriği buraya gelecek -->

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const studentList = document.getElementById('student-list');
            const studentForm = document.getElementById('student-form');
            const studentNameInput = document.getElementById('student-name');

            // Mevcut öğrencileri yükle
            async function fetchStudents() {
                const response = await fetch('api.php?action=get_students');
                const students = await response.json();
                studentList.innerHTML = '';
                students.forEach(student => {
                    const li = document.createElement('li');
                    li.innerHTML = `<span>${student.full_name}</span> <button class="delete-btn" data-id="${student.id}">Sil</button>`;
                    studentList.appendChild(li);
                });
            }

            // Yeni öğrenci ekle
            studentForm.addEventListener('submit', async (e) => {
                e.preventDefault();
                const response = await fetch('api.php?action=add_student', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ full_name: studentNameInput.value })
                });
                const result = await response.json();
                if (result.success) {
                    studentNameInput.value = '';
                    fetchStudents(); // Listeyi yenile
                } else {
                    alert('Hata: ' + result.error);
                }
            });

            // Öğrenci sil
            studentList.addEventListener('click', async (e) => {
                if (e.target.classList.contains('delete-btn')) {
                    const studentId = e.target.getAttribute('data-id');
                    if (confirm('Bu öğrenciyi silmek istediğinizden emin misiniz?')) {
                        const response = await fetch('api.php?action=delete_student', {
                            method: 'POST',
                            headers: { 'Content-Type': 'application/json' },
                            body: JSON.stringify({ id: studentId })
                        });
                        const result = await response.json();
                        if (result.success) {
                            fetchStudents(); // Listeyi yenile
                        } else {
                            alert('Hata: ' + result.error);
                        }
                    }
                }
            });

            // Başlangıçta öğrencileri yükle
            fetchStudents();
        });
    </script>
</body>
</html>
