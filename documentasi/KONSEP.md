# Proposal Pengembangan Sistem Multi-Aplikasi Reverse Vending Machine (RVM) Terintegrasi (Revisi 1)

Dokumen ini merangkum rencana pengembangan sistem RVM cerdas yang terintegrasi, melibatkan backend API, aplikasi pada mesin RVM fisik, aplikasi pengguna, dan dashboard admin.

**Pemberitahuan Revisi Penting (Revisi 1):** Semua operasi Computer Vision, termasuk pra-pemrosesan gambar, konstruksi prompt, pemanggilan Google Gemini Vision API, dan parsing respons awal, akan dilakukan **eksklusif di Backend Laravel**. Aplikasi RVM (di Jetson/RPi) hanya akan bertanggung jawab untuk mengambil gambar dan mengirimkannya ke Backend Laravel, kemudian menerima hasil yang sudah diproses dan diinterpretasi.

## 1. Arsitektur Umum Sistem

Sistem akan terdiri dari beberapa komponen utama yang saling berinteraksi:

1.  **Backend Inti (Central API - Laravel):** Otak sistem, menangani logika bisnis, manajemen database, autentikasi, dan semua interaksi dengan Google Gemini Vision API.
2.  **Aplikasi RVM (Perangkat Lunak di Mesin Fisik):** Berjalan di NVIDIA Jetson Orin Nano (utama) dan/atau Raspberry Pi 4B, dengan ESP32 untuk kontrol hardware. Bertugas mengambil gambar, mengirim ke backend, menerima hasil, dan mengontrol mekanisme RVM.
3.  **Aplikasi User (Mobile/PWA):** Untuk pengguna mendaftar, login, melihat poin, dan menghasilkan token QR untuk login ke RVM.
4.  **Aplikasi Dashboard Admin (Web):** Untuk monitoring, manajemen pengguna, manajemen RVM, dan pengujian visi.

## 2. Detail Komponen Sistem

### 2.1. Backend Inti (Central API - Laravel)

-   **Bahasa/Framework:** PHP / Laravel
-   **Database:** MySQL atau PostgreSQL
-   **Fungsi Utama:**
    -   Manajemen Database:
        -   `users`: Data pengguna, poin, peran (Admin, Operator, User), status tamu, detail identitas.
        -   `reverse_vending_machines`: Detail lokasi, status, koordinat (opsional), `api_key` otentikasi mesin.
        -   `deposits`: Catatan setiap item yang dimasukkan, `user_id` (bisa guest), `rvm_id`, `detected_type` (hasil interpretasi Gemini), poin, `gemini_raw_label`, `gemini_raw_response`, `image_path` (opsional).
    -   Autentikasi & Otorisasi: Email/Password, Google Sign-In (Laravel Socialite), otentikasi RVM (API Key/Sanctum), otentikasi Dashboard Admin (role-based).
    -   **Layanan Computer Vision Terpusat (menggunakan Google Gemini Vision API):**
        -   Akan dienkapsulasi dalam `GeminiVisionService.php`.
        -   Menerima file gambar dari Aplikasi RVM atau Dashboard Admin.
        -   Melakukan resizing gambar (Intervention Image).
        -   Membangun prompt dinamis untuk Gemini API.
        -   Memanggil Google Gemini Vision API (menggunakan `GOOGLE_API_KEY` dan `GEMINI_API_ENDPOINT_*` dari `.env`).
        -   Menangani respons API dan parsing JSON awal.
    -   **API Endpoint (RESTful):**
        -   **Untuk Aplikasi RVM:**
            -   `/api/rvm/authenticate` (POST): Otentikasi RVM.
            -   `/api/rvm/validate-user-token` (POST): Validasi token QR dari Aplikasi User.
            -   `/api/rvm/deposit` (POST): Menerima file gambar, `user_id`, `rvm_id`. **Memanggil `GeminiVisionService`**, menginterpretasi hasil, menyimpan deposit, update poin, dan mengembalikan hasil terstruktur ke RVM.
        -   **Untuk Aplikasi User:**
            -   `/api/auth/*`: Registrasi, Login, Logout, Google Auth.
            -   `/api/user/profile` (GET): Info user, poin.
            -   `/api/user/rvm-token` (GET): Generate token/data untuk QR Code login RVM.
            -   `/api/user/deposits` (GET): Riwayat deposit.
        -   **Untuk Aplikasi Dashboard Admin:**
            -   `/api/admin/stats` (GET): Data agregat.
            -   `/api/admin/users` (CRUD).
            -   `/api/admin/rvms` (CRUD).
            -   `/api/admin/deposits` (GET).
            -   `/api/admin/vision-test` (POST): Menerima gambar, memanggil `GeminiVisionService`, dan mengembalikan hasil Gemini untuk pengujian.
    -   Logika Bisnis Inti: Perhitungan poin, validasi, agregasi data.

### 2.2. Aplikasi RVM (Perangkat Lunak di Mesin Fisik)

-   **Platform Utama:** NVIDIA Jetson Orin Nano Developer Kit
-   **Platform Pendukung (Opsional):** Raspberry Pi 4B
-   **Kontroler Hardware:** ESP32 / ESP8266
-   **Periferal:** Kamera, LCD 7 inch Touchscreen, Sensor Proksimitas, Motor, LED.
-   **Bahasa Utama (Jetson/RPi):** Python
-   **Bahasa Kontroler (ESP32):** C/C++ (Arduino Core atau ESP-IDF)
-   **Fungsi Utama:**
    -   Manajemen state mesin RVM.
    -   Antarmuka pengguna di LCD Touchscreen.
    -   Login pengguna melalui pemindaian QR Code (dari Aplikasi User) dengan validasi ke Backend API.
    -   Pengambilan gambar item yang dimasukkan menggunakan kamera.
    -   **Mengirim file gambar mentah** (atau base64) beserta `user_id` dan `rvm_id` ke endpoint `/api/rvm/deposit` di Backend Laravel.
    -   **Menerima hasil deteksi yang sudah diproses dan diinterpretasi** dari Backend Laravel.
    -   Mengontrol mekanisme fisik (pemilah, pintu) dan indikator (LED) melalui ESP32 berdasarkan respons dari backend.
    -   Konektivitas Wi-Fi (dengan Kasetsart University VPN jika diperlukan untuk akses ke Backend).

### 2.3. Aplikasi User (Mobile/PWA)

-   **Teknologi (Pilihan):** React Native, Flutter (untuk Mobile); Vue.js, React (untuk PWA).
-   **Fungsi Utama:**
    -   Signup/Login (Email/Password, Google).
    -   Menampilkan profil pengguna dan total poin.
    -   **Menghasilkan QR Code/Token unik** untuk login ke RVM.
    -   Menampilkan riwayat transaksi deposit.
    -   (Opsional) Peta lokasi RVM, fitur tukar poin.
    -   Semua operasi memanggil API di Backend Laravel.

### 2.4. Aplikasi Dashboard Admin (Web)

-   **Teknologi (Pilihan):**
    -   Terintegrasi Laravel: Blade + Vue.js (Inertia.js) / Alpine.js / Livewire.
    -   SPA Terpisah: Vue.js atau React.
-   **Fungsi Utama:**
    -   Login untuk Admin/Operator.
    -   Visualisasi data agregat (total poin, botol, grafik).
    -   Manajemen Pengguna (CRUD).
    -   Manajemen RVM (CRUD, status).
    -   Melihat detail semua transaksi deposit.
    -   **Fitur "Vision Test":** Mengunggah gambar, mengirim ke Backend API (`/api/admin/vision-test`), dan menampilkan hasil dari Gemini.
    -   Akses API ke Backend Laravel.

## 3. Kunci API Google Gemini Vision

-   `GOOGLE_API_KEY`: `AIzaSyDZ0-c-n2iAd9R0LM_r76uEN58YRxh9gq8` (Akan disimpan di `.env` Backend Laravel).
-   `GEMINI_API_ENDPOINT_PRO`: `https://generativelanguage.googleapis.com/v1beta/models/gemini-pro-vision:generateContent` (Akan disimpan di `.env` Backend Laravel).
-   `GEMINI_API_ENDPOINT_FLASH`: `https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash:generateContent` (Akan disimpan di `.env` Backend Laravel, pilih salah satu yang akan digunakan sebagai default).

## 4. Peta Progres Pengembangan (Revisi 1)

-   **Fase 1: Pondasi Backend Inti & Desain Database (0% - 25%)**

    -   **0-5%**: Setup Proyek Laravel. Konfigurasi `.env` (termasuk API Key Gemini).
    -   **5-15%**: Finalisasi Desain Database. Migrations (tabel `users`, `reverse_vending_machines`, `deposits` dengan kolom relevan seperti `gemini_raw_label`, `gemini_raw_response`).
    -   **15-20%**: Model Eloquent dengan relasi.
    -   **20-25%**: Autentikasi User Dasar (Email/Password, Google Sign-In). Seeder dasar.

-   **Fase 2: Pengembangan API Backend Inti (Termasuk Layanan CV Terpusat) (25% - 55%)**

    -   **25-35%**: Implementasi `GeminiVisionService.php` di Laravel (resizing gambar, bangun prompt, call Gemini API, parsing dasar respons).
    -   **35-45%**: Pengembangan API Endpoint `/api/rvm/deposit` (integrasi `GeminiVisionService`, interpretasi hasil Gemini, simpan deposit, update poin, respons ke RVM) dan endpoint RVM lainnya.
    -   **45-50%**: Pengembangan API Endpoint untuk Aplikasi User.
    -   **50-55%**: Pengembangan API Endpoint Dasar untuk Dashboard Admin (termasuk `/api/admin/vision-test` menggunakan `GeminiVisionService`). Dokumentasi API.

-   **Fase 3: Pengembangan Aplikasi RVM (Perangkat Lunak di Mesin Fisik) (55% - 75%)**

    -   **55-60%**: Setup Jetson Orin Nano. Komunikasi Jetson <-> ESP32.
    -   **60-70%**: Logika Inti Aplikasi RVM (Python) (ambil gambar, kirim ke API Laravel, terima & tampilkan hasil).
    -   **70-72%**: Kontroler Sensor/Aktuator (C/C++ di ESP32).
    -   **72-75%**: Integrasi UI Dasar di LCD RVM. Perakitan prototipe fisik.

-   **Fase 4: Pengembangan Aplikasi User & Aplikasi Dashboard Admin (Frontend) (75% - 95%)**

    -   **75-85%**: Aplikasi User (Mobile/PWA).
    -   **85-95%**: Aplikasi Dashboard Admin (Web).

-   **Fase 5: Integrasi Menyeluruh, Pengujian, Penyempurnaan & Deployment (95% - 100%)**
    -   **95-98%**: Pengujian End-to-End. Debugging. UAT.
    -   **98-99%**: Finalisasi Dokumentasi.
    -   **99-100%**: Deployment. Monitoring.

## 5. Konsistensi Data dan Variabel

-   **Penamaan:** `camelCase` untuk PHP/JS, `PascalCase` untuk class PHP/JS/Python, `snake_case` untuk Python var/func, kolom DB, key API JSON. `snake_case` atau `camelCase` untuk C/C++.
-   **Struktur Data API (JSON):** `snake_case` untuk keys.
-   **Endpoint API:** Desain RESTful.
-   **Pesan Error & Kode Status HTTP:** Standar dan konsisten.
-   **Version Control (Git):** Branch naming convention dan pesan commit deskriptif.

## 6. Rekomendasi Teknologi Utama

-   **Backend:** PHP (Laravel), MySQL/PostgreSQL.
-   **Aplikasi RVM (Jetson/RPi):** Python.
-   **Kontroler Sensor (ESP32):** C/C++ (Arduino Core).
-   **Frontend User App:** React Native/Flutter (Mobile) atau Vue.js/React (PWA).
-   **Frontend Admin Dashboard:** Laravel Blade+Vue.js (Inertia.js) atau SPA Vue.js/React.

---

## Progres Pengembangan Saat Ini: **0%**
