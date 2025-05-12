# Proposal Pengembangan Sistem Multi-Aplikasi Reverse Vending Machine (RVM) Terintegrasi (Revisi ke-4)

**Tanggal Revisi:** 12 Mei 2025

## 1. Abstrak Proyek

Proyek ini bertujuan untuk merancang, mengembangkan, dan mengimplementasikan sistem Reverse Vending Machine (RVM) cerdas yang terintegrasi. Sistem akan terdiri dari beberapa komponen utama: mesin RVM fisik (dikendalikan oleh Raspberry Pi dan ESP32 dengan kemampuan Computer Vision melalui API cloud), backend API terpusat (dibangun dengan Laravel), Aplikasi User mobile/PWA (dibangun dengan Flutter) untuk interaksi pengguna dan perolehan poin, serta Dashboard Admin web untuk manajemen dan monitoring sistem. Fokus utama adalah pada identifikasi otomatis jenis sampah kemasan, pemberian insentif kepada pengguna, dan pengelolaan data daur ulang yang efisien.

## 2. Latar Belakang

Peningkatan volume sampah kemasan dan rendahnya tingkat partisipasi daur ulang menjadi isu lingkungan global. Sistem RVM cerdas menawarkan solusi inovatif dengan memberikan insentif langsung kepada pengguna yang mengembalikan kemasan bekas. Dengan memanfaatkan teknologi AI (Google Gemini Vision), IoT, dan aplikasi mobile, sistem ini diharapkan dapat meningkatkan akurasi identifikasi sampah, memberikan pengalaman pengguna yang menarik, dan menyediakan data analitik yang berharga untuk pengelolaan sampah yang lebih baik.

## 3. Tujuan Proyek

1.  Mengembangkan prototipe mesin RVM fisik yang mampu mengidentifikasi jenis sampah kemasan (fokus pada botol plastik dan kaleng aluminium) secara otomatis.
2.  Membangun backend API terpusat untuk mengelola data pengguna, data RVM, transaksi deposit, poin, dan integrasi dengan layanan AI.
3.  Mengembangkan Aplikasi User (Flutter) yang intuitif bagi pengguna untuk mendaftar, login, melihat poin, men-generate token QR untuk deposit, dan melihat riwayat transaksi.
4.  Mengembangkan Dashboard Admin web untuk monitoring operasional RVM, manajemen pengguna, manajemen RVM, dan analisis data deposit.
5.  Mengintegrasikan layanan Google Gemini Vision API melalui backend untuk analisis gambar item yang dimasukkan ke RVM.
6.  Mengimplementasikan mekanisme logging lokal ("blackbox") di RVM untuk diagnostik dan pengiriman log ke server.
7.  Merancang alur update perangkat lunak RPi dan firmware ESP32 jarak jauh (OTA) yang dapat dipicu dari Dashboard Admin.

## 4. Arsitektur dan Komponen Sistem

Sistem akan terdiri dari komponen-komponen berikut yang saling berinteraksi melalui API:

### 4.1. Mesin RVM Fisik
    *   **Unit Pemrosesan Utama:** Raspberry Pi 4B.
        *   Menjalankan aplikasi Python utama (`rvm_main_app.py`) dengan state machine.
        *   Mengontrol kamera USB untuk scan QR code (Kamera QR) dan mengambil gambar item deposit (Kamera Objek).
        *   Berkomunikasi dengan backend API Laravel melalui jaringan (Wi-Fi/Ethernet via `ngrok` untuk development).
        *   Berkomunikasi dengan ESP32 melalui Serial UART untuk mengontrol aktuator dan membaca sensor.
        *   Menyimpan log operasional lokal ("blackbox").
        *   (Nantinya) Menjalankan server HTTP lokal untuk OTA firmware ESP32.
        *   (Nantinya) Menjalankan Dashboard Lokal untuk konfigurasi awal Wi-Fi.
    *   **Kontroler Hardware Level Rendah:** ESP32 WROVER Dev (ESP-IDF).
        *   Menerima perintah dari Raspberry Pi.
        *   Mengontrol motor, solenoid, pintu slot (aktuator).
        *   Mengontrol lampu LED indikator status.
        *   (Nantinya) Membaca data dari sensor (proksimitas, berat, limit switch).
        *   (Nantinya) Melakukan update firmware OTA yang dipicu oleh RPi.
    *   **Periferal:**
        *   **Kamera USB 1 (QR):** Untuk memindai QR code token pengguna. Resolusi hardcode (misalnya, 640x480).
        *   **Kamera USB 2 (Objek):** Untuk mengambil gambar item yang dimasukkan untuk analisis AI. Resolusi hardcode (misalnya, 1024x768 atau lebih tinggi jika stabil).
        *   LCD Touchscreen 7 inci (terhubung ke RPi): Sebagai UI utama mesin RVM. *(Implementasi UI grafis di RPi belum dimulai)*.
        *   LED Indikator (terhubung ke ESP32).
        *   (Nantinya) Motor, sensor.

### 4.2. Backend API Terpusat
    *   **Teknologi:** PHP, Laravel Framework (v12).
    *   **Database:** MariaDB (nantinya di Docker).
    *   **Fungsi:**
        *   Manajemen data: `users`, `reverse_vending_machines`, `deposits`, `rvm_device_logs`, `esp32_firmware_versions`.
        *   Autentikasi dan Otorisasi (Sanctum untuk API, session untuk web admin).
        *   Menyediakan RESTful API endpoint untuk Aplikasi User, Aplikasi RVM, dan Dashboard Admin.
        *   **Integrasi Google Gemini Vision API:** Menerima gambar dari RVM, memanggil Gemini API, menginterpretasi hasil, menentukan jenis item dan poin.
        *   Logika bisnis inti (perhitungan poin, validasi, dll.).
        *   (Nantinya) Menerima dan menyimpan log "blackbox" dari RVM.
        *   (Nantinya) Mengelola perintah update OTA untuk RVM.

### 4.3. Aplikasi User
    *   **Teknologi:** Flutter (untuk Android & iOS).
    *   **Fungsi:**
        *   Registrasi & Login (Email/Password, Google Sign-In via ID Token API).
        *   Menampilkan Profil Pengguna (nama, email, total poin, riwayat identitas).
        *   **Generate Token QR untuk Deposit:** Memanggil API backend, menerima token 40 karakter, menampilkannya sebagai QR Code di **Modal Bottom Sheet** dengan timer mundur dan teks instruksi. Termasuk permintaan izin dan kontrol kecerahan layar.
        *   Menampilkan Riwayat Deposit (terpaginasi).
        *   Logout.
        *   **Polling Status Scan:** Setelah QR ditampilkan, aplikasi akan polling ke backend untuk mengetahui apakah RVM telah berhasil memvalidasi token tersebut, lalu menampilkan notifikasi "Scan Berhasil!".
    *   **Desain UI/UX Utama:**
        *   `MainShellScreen` dengan `BottomAppBar` (lekukan) dan `FloatingActionButton` (ikon QR + teks "QR") di tengah untuk memicu modal QR.
        *   Navigasi tab untuk Home, Statistik (placeholder), Riwayat (placeholder), Profil.
        *   `HomeScreen` menampilkan "Selamat Datang, [Nama User]".

### 4.4. Dashboard Admin (Web)
    *   **Teknologi:** Web (kemungkinan Laravel Blade dengan Vue.js/Livewire, atau SPA terpisah). *(Belum dimulai)*
    *   **Fungsi:**
        *   Login Admin/Operator.
        *   Monitoring status RVM (aktif, offline, versi software/firmware).
        *   Manajemen Pengguna (CRUD).
        *   Manajemen RVM (CRUD, termasuk generate `api_key` untuk RVM baru).
        *   Melihat semua transaksi deposit, filter, search.
        *   Fitur "Vision Test" untuk menguji gambar dengan Gemini API.
        *   (Nantinya) Melihat log "blackbox" dari RVM.
        *   (Nantinya) Memicu update OTA untuk RPi dan ESP32.

## 5. Bahan dan Alat yang Dibutuhkan (Sudah Digunakan atau Direncanakan)

*   **Perangkat Keras RVM:**
    *   Raspberry Pi 4B (dengan SD Card, catu daya).
    *   ESP32 WROVER Dev module.
    *   Kamera USB (2 unit: satu untuk QR, satu untuk objek).
    *   LCD Touchscreen 7 inci (untuk UI RVM, terhubung ke RPi).
    *   Kabel Jumper, Breadboard (untuk prototyping awal).
    *   LED (untuk indikator dan simulasi aktuator).
    *   (Nantinya) Motor, driver motor, sensor proksimitas, sensor berat, limit switch, chassis RVM.
*   **PC/Laptop Pengembangan:**
    *   OS: Windows/macOS/Linux.
    *   IDE: Visual Studio Code dengan ekstensi Flutter, Dart, PlatformIO, Remote-SSH.
    *   Android Studio (untuk Android SDK, AVD Manager, dan build tools Android).
    *   Xcode (jika menargetkan iOS, memerlukan macOS).
    *   Git & Klien GitHub.
    *   Postman (untuk pengujian API).
*   **Perangkat Lunak & Layanan:**
    *   PHP, Composer, Laravel (untuk backend).
    *   MariaDB (untuk database backend).
    *   Flutter SDK, Dart SDK (untuk Aplikasi User).
    *   Python 3, pip, venv (untuk aplikasi di Raspberry Pi).
    *   PlatformIO Core (untuk pengembangan ESP32 ESP-IDF).
    *   Akun Google Cloud Platform (untuk Gemini Vision API Key, OAuth Client ID).
    *   Akun GitHub (untuk repository kode).
    *   `ngrok` (atau layanan tunneling lain) untuk mengekspos server lokal saat development.
    *   (Nantinya) Docker, Docker Compose (untuk deployment).
*   **Kredensial & Kunci API:**
    *   `GOOGLE_API_KEY` (untuk Gemini Vision).
    *   `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (untuk Google Sign-In).
    *   `RVM_API_KEY` (per RVM, di-generate oleh backend).

## 6. Peta Progres Pengembangan (Revisi 4)

*   **Fase 1: Pondasi Backend Inti & Desain Database (0% - 25%)** - **SELESAI**
*   **Fase 2: Pengembangan API Backend Inti (Termasuk Layanan CV Terpusat) (25% - 55%)** - **SELESAI**
*   **Fase 3: Pengembangan Aplikasi RVM (Perangkat Lunak di Mesin Fisik - Raspberry Pi) (55% - 75%)**
    *   Setup Lingkungan RPi & Komunikasi Awal RPi <-> ESP32 (55% - 60%) - *SELESAI*
    *   Pengembangan Logika Inti `rvm_main_app.py` (State Machine, Integrasi Kamera Hardcode, Komunikasi API, Pembacaan QR Nyata, Interaksi ESP32 Dasar, Logging Blackbox) (60% - 75%) - **SEDANG DIKERJAKAN (Target saat ini ~72-75%, menunggu konfirmasi stabilitas kamera objek dan QR scan)**.
*   **Fase 4: Pengembangan Aplikasi User & Aplikasi Dashboard Admin (Frontend) (75% - 90%)**
    *   **Pengembangan Awal Aplikasi User (Flutter) (75% - 82%):**
        *   Setup Lingkungan Flutter & Aplikasi "Hello World" (75% - 76%) - *SELESAI*
        *   Implementasi LoginScreen & RegistrationScreen, Navigasi Dasar (76% - 78%) - *SELESAI*
        *   Implementasi Penyimpanan Token, Alur Startup (`AuthCheckScreen`), Logout, `ProfileScreen` dasar, `HomeScreen` dasar dengan FAB dan Modal Bottom Sheet QR (termasuk timer dan tombol generate ulang di modal, polling status scan) (78% - **SEDANG DIKERJAKAN (Target saat ini ~81-82%)**).
    *   Pengembangan Lanjutan Aplikasi User (Flutter) (82% - 85%): Penyempurnaan UI/UX, fitur riwayat deposit, integrasi penuh kontrol kecerahan, notifikasi scan sukses.
    *   Pengembangan Dasar Dashboard Admin (Web) (85% - 90%): CRUD User & RVM, tampilan statistik, Vision Test.
*   **Fase 5: Integrasi Menyeluruh, Pengujian, & Penyempurnaan (90% - 97%)**
    *   Pengujian End-to-End semua aplikasi. Debugging. Pengujian keamanan. UAT.
    *   Finalisasi Dokumentasi.
*   **Fase 6: Persiapan Deployment & Transisi ke Lingkungan Produksi (Docker) (97% - 100%)**
    *   Dockerisasi Aplikasi (Laravel, MariaDB, MinIO, Nginx/Reverse Proxy).
    *   Setup Lingkungan Produksi di Server. DNS, SSL.
    *   Implementasi mekanisme update OTA (ESP32) dan `git pull` otomatis (RPi) yang dipicu dari Dashboard Admin.
    *   Implementasi pengiriman log blackbox RPi ke server.
    *   Deployment Aplikasi. Monitoring Awal. Go-Live.

## 7. Alur dan Cara Kerja Utama Sistem

### 7.1. Alur Registrasi & Login Pengguna (Aplikasi User Flutter)
1.  Pengguna membuka Aplikasi User. `AuthCheckScreen` memeriksa token tersimpan.
2.  Jika tidak ada token/tidak valid, tampilkan `LoginScreen`.
3.  Pengguna bisa memilih registrasi (email/pass) atau login (email/pass atau Google).
4.  **Registrasi/Login Email/Pass:** Aplikasi User memanggil API backend (`/api/auth/register` atau `/api/auth/login`). Backend memvalidasi, membuat user (jika register), dan mengembalikan token Sanctum serta data user.
5.  **Login Google (API):** Aplikasi User menggunakan SDK Google Sign-In klien untuk mendapatkan ID Token Google. ID Token ini dikirim ke API backend (`/api/auth/google/token-signin`). Backend memvalidasi ID Token, membuat/login user, dan mengembalikan token Sanctum.
6.  Aplikasi User menyimpan token Sanctum dan detail user (misalnya, di `shared_preferences`).
7.  Navigasi ke `MainShellScreen` (yang menampilkan `HomeScreen`).

### 7.2. Alur Deposit Item di RVM
1.  **Pengguna di `HomeScreen` (Flutter):** Menekan FAB "QR".
2.  **Aplikasi User:**
    *   Meminta izin (jika perlu) dan memaksimalkan kecerahan layar.
    *   Memanggil API backend `/api/user/generate-rvm-token` (mengirim token Sanctum user).
    *   Backend men-generate `rvm_login_token` (40 karakter), menyimpannya di Cache dengan `user_id` dan masa berlaku (5 menit), lalu mengirimkannya kembali ke Aplikasi User.
    *   Aplikasi User menampilkan `rvm_login_token` sebagai **QR Code di Modal Bottom Sheet** beserta timer mundur 5 menit dan instruksi.
    *   Aplikasi User mulai **polling** ke `/api/user/check-rvm-scan-status?token=<rvm_login_token>` setiap 7-10 detik.
3.  **Pengguna di Mesin RVM (Raspberry Pi):**
    *   RVM dalam `STATE_IDLE`, menampilkan pesan "Scan QR atau Masukkan Item" di LCD RPi.
    *   Pengguna memilih opsi "Scan QR" (disimulasikan via input di RPi, nantinya via UI LCD RPi).
    *   RPi masuk `STATE_WAITING_FOR_USER_QR`. Kamera QR (`cap_qr`) diaktifkan.
    *   Pengguna mengarahkan QR Code dari HP-nya ke kamera QR RVM.
    *   RPi (menggunakan `pyzbar`) men-decode QR dan mendapatkan `rvm_login_token`.
    *   RPi masuk `STATE_VALIDATING_USER_TOKEN`.
4.  **Validasi Token oleh RVM ke Backend:**
    *   RPi mengirim `rvm_login_token` yang di-scan ke API backend `/api/rvm/validate-user-token`.
    *   Backend (`RvmController@validateUserToken`) memeriksa token di Cache. Jika valid dan belum dipakai:
        *   Mengambil `user_id`.
        *   **Menghapus token dari Cache (one-time use).**
        *   Mengembalikan respons sukses ke RPi berisi `user_id` dan `user_name`.
    *   Jika token tidak valid/expired: Backend mengembalikan error.
5.  **Interaksi Lanjutan di RVM:**
    *   Jika validasi token sukses: RPi masuk `STATE_WAITING_FOR_ITEM`. LCD RPi menampilkan "Halo, [Nama User]! Masukkan item." RPi mengirim perintah `SLOT_OPEN` ke ESP32.
    *   Jika validasi token gagal: RPi kembali ke `STATE_IDLE`. LCD RPi menampilkan error.
    *   Pengguna memasukkan item. (Disimulasikan, nantinya via sensor dari ESP32).
    *   RPi masuk `STATE_CAPTURING_IMAGE`. Kamera Objek (`cap_object`) diaktifkan. Lampu internal dinyalakan (via ESP32). Gambar item diambil. Lampu dimatikan. Kamera objek dilepas.
    *   RPi masuk `STATE_PROCESSING_IMAGE_WITH_AI`. Gambar dikirim ke API backend `/api/rvm/deposit` (bersama `user_identifier` numerik dan `RVM_API_KEY` di header).
6.  **Pemrosesan di Backend dan Respons:**
    *   Backend (`RvmController@deposit`) menerima gambar, memanggil `GeminiVisionService`.
    *   Gemini menganalisis gambar. Hasil interpretasi (jenis item, poin) dikembalikan ke RPi.
    *   Data deposit disimpan di database. Poin user diupdate.
7.  **Hasil di RVM dan Aplikasi User:**
    *   **RPi:** Berdasarkan respons API deposit:
        *   Jika diterima: Masuk `STATE_ITEM_ACCEPTED`. LCD RPi menampilkan "Item Diterima, Poin: X". RPi kirim perintah `SORT_VALID_ITEM` dan `INDICATE_STATUS_SUCCESS` ke ESP32.
        *   Jika ditolak: Masuk `STATE_ITEM_REJECTED`. LCD RPi menampilkan "Item Ditolak: [Alasan]". RPi kirim perintah `RETURN_REJECTED_ITEM` dan `INDICATE_STATUS_REJECTED` ke ESP32.
    *   **Aplikasi User (Flutter):** Polling ke `/api/user/check-rvm-scan-status` akhirnya mendapatkan respons dari backend bahwa token sudah divalidasi (`scanned_and_validated`).
        *   Aplikasi User menghentikan polling dan timer QR.
        *   Menampilkan popup "Scan Berhasil!".
        *   Menutup Modal QR.
        *   Mengembalikan kecerahan layar.
        *   (Otomatis) Me-refresh data user (poin/riwayat) dengan memanggil API profil/riwayat lagi.
8.  RPi kembali ke `STATE_IDLE`.

## 8. Cara Pengujian (Ringkasan)

*   **Backend API Laravel:** Diuji menggunakan Postman untuk setiap endpoint (auth, user, rvm, admin). Pastikan respons JSON sesuai, validasi input bekerja, dan logika bisnis benar.
*   **Aplikasi RVM (Raspberry Pi Python):**
    *   Uji setiap state dan transisi secara manual melalui input konsol.
    *   Verifikasi komunikasi serial dengan ESP32 (perintah dan ACK).
    *   Verifikasi pengambilan gambar dari kedua kamera (QR dan Objek) secara bergantian.
    *   Verifikasi pemanggilan API ke backend (gunakan `ngrok` untuk mengekspos backend lokal).
    *   Periksa file log blackbox (`rvm_logs/...`).
    *   Uji skenario error (kamera gagal, ESP32 tidak respons, API offline/error).
*   **Aplikasi User (Flutter):**
    *   Uji di emulator Android (AVD) atau perangkat fisik.
    *   Uji alur registrasi, login (email/pass dan Google), logout.
    *   Uji tampilan profil dan refresh data.
    *   Uji fitur generate token QR, tampilan modal, timer, dan tombol generate ulang.
    *   Uji alur polling status scan (mungkin perlu mock respons dari backend untuk `/check-rvm-scan-status` pada awalnya).
    *   Uji interaksi dengan RVM (scan QR yang ditampilkan Aplikasi User oleh kamera RPi).
    *   Periksa Debug Console Flutter untuk output `debugPrint` dan error.
*   **ESP32:**
    *   Uji penerimaan perintah serial dari RPi dan pengiriman ACK.
    *   Verifikasi kontrol LED (sebagai simulasi aktuator) berfungsi.
    *   Gunakan Serial Monitor PlatformIO untuk debugging (saat RPi tidak aktif berkomunikasi di UART0).

---