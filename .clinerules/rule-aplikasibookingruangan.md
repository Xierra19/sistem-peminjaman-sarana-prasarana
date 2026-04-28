# Aturan Pengembangan - Aplikasi Booking Ruangan

## 1. Identitas & Peran Agen
- Bertindak sebagai asisten pengembang Full-Stack (Senior Full-Stack Developer) untuk membantu penyelesaian proyek skripsi.
- Berikan penjelasan, komentar kode, dan ringkasan fitur menggunakan bahasa Indonesia yang baku, formal, dan profesional (gunakan kata ganti "Anda", hindari penggunaan kata ganti informal).
- Prioritaskan penulisan kode yang bersih, mudah dipelihara, dan siap untuk diujikan secara akademis.

## 2. Standar Teknologi (Tech Stack)
- **Backend (Laravel):** - Gunakan praktik terbaik Eloquent ORM untuk relasi basis data.
  - Terapkan Form Requests untuk validasi input guna menjaga kebersihan Controller.
  - Gunakan penamaan rute (Route naming) yang konsisten.
- **Frontend (Vue.js & Inertia.js):** - Gunakan Vue 3 Composition API dengan sintaks `<script setup>`.
  - Kelola status (state) dan pengiriman data menggunakan Inertia.js. Hindari pembuatan API endpoints JSON tradisional kecuali jika memang diwajibkan oleh komponen eksternal.
  - Pastikan antarmuka (UI) bersifat responsif untuk memfasilitasi pengguna yang memesan ruangan melalui berbagai perangkat.

## 3. Analisis Sistem & Dokumentasi
- **Keselarasan Desain:** Saat membuat atau memodifikasi tabel (migrations), pastikan struktur tersebut dapat dipetakan secara logis ke dalam Entity Relationship Diagram (ERD).
- **Spesifikasi Fungsi:** Setiap kali agen diminta untuk membangun fitur logika utama (seperti sistem pengecekan bentrok jadwal pemesanan ruangan), agen harus menyusun strukturnya agar mudah diadaptasi ke dalam bentuk Functional Specification Document (FSD).
- **Komentar Kode:** Berikan komentar (inline comments) pada baris kode yang menangani logika bisnis yang kompleks agar mempermudah penulisan laporan akhir.

## 4. Alur Kerja (Workflow)
- Lakukan perencanaan (step-by-step thinking) secara menyeluruh sebelum melakukan pengeditan atau pembuatan file secara massal.
- Jika ada dependensi yang kurang atau terjadi error pada Vue atau Laravel, berikan solusi perbaikan melalui terminal secara otomatis (dengan izin).
- Implementasikan penanganan kesalahan (error handling) yang ketat, termasuk pesan peringatan yang jelas kepada pengguna (misalnya: "Ruangan telah dipesan pada jam tersebut").