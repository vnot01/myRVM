# Proposal Pengembangan Sistem Multi-Aplikasi RVM - Revisi 3 (Pemisahan Auth Controller)

Dokumen ini merangkum rencana pengembangan sistem RVM dengan update terkait pemisahan controller autentikasi untuk Web dan API.

**Pemberitahuan Revisi Penting:**

-   **(Revisi 1):** Operasi CV terpusat di Backend Laravel.
-   **(Revisi 2):** Penambahan Fase 6 (Deployment & Transisi Docker). Pendaftaran middleware Laravel 11+.
-   **(Revisi 3):** Pemisahan `AuthController` untuk Web (berbasis session) dan API (berbasis token Sanctum) demi kejelasan. Google Sign-In untuk API akan menggunakan alur validasi ID Token sisi server.

## 1. Arsitektur Umum Sistem

(Tidak berubah)

## 2. Detail Komponen Sistem

### 2.1. Backend Inti (Central API - Laravel)

-   **Autentikasi:**
    -   **`App/Http/Controllers/Api/AuthController.php`**:
        -   Registrasi Email/Password (API, mengembalikan token Sanctum).
        -   Login Email/Password (API, mengembalikan token Sanctum).
        -   Logout (API, menghapus token Sanctum).
        -   User Profile (API, via token Sanctum).
        -   **Google Sign-In via ID Token (API):** Endpoint `/api/auth/google/token-signin` akan menerima ID Token Google dari klien (SPA/Mobile), memvalidasinya di sisi server, lalu membuat/login user dan mengembalikan token Sanctum.
    -   **`App/Http/Controllers/Web/AuthController.php`**:
        -   (Jika tidak pakai Breeze/Jetstream) Login/Registrasi Email/Password (Web, berbasis session).
        -   **Google Sign-In (Web):** Alur redirect standar Socialite (`/auth/google/redirect` dan `/auth/google/callback` di `routes/web.php`), membuat session web.
    -   Trait `App/Http/Controllers/Auth/HandlesGoogleUser.php` (opsional) untuk logika bersama `updateOrCreate` user dari data Google.

(Detail komponen lain tidak berubah signifikan oleh revisi ini)

## 3. Kunci API Google & Redirect URI

-   `GOOGLE_CLIENT_ID`, `GOOGLE_CLIENT_SECRET` (di `.env`).
-   `GOOGLE_REDIRECT_URI_WEB`: (di `.env`) Untuk alur Google Sign-In Web (misalnya, `http://your-app.test/auth/google/callback`). Digunakan di `config/services.php`.
-   (Gemini API Keys tetap sama)

## 4. Peta Progres Pengembangan (Revisi 3 - Minor Update pada Fase 2)

-   **Fase 1: Pondasi Backend Inti & Desain Database (0% - 25%)** - _SELESAI_
-   **Fase 2: Pengembangan API Backend Inti (Termasuk Layanan CV Terpusat) (25% - 55%)** - _SEDANG BERJALAN_
    -   **25-35%**: Implementasi `GeminiVisionService.php`. - _SELESAI_
    -   **35-45%**: Pengembangan API Endpoint RVM (`/api/rvm/deposit`, `/api/rvm/authenticate`, `/api/rvm/validate-user-token` dengan penyesuaian Cache). Middleware `auth.rvm`. - _ANDA SAAT INI DI SINI, MENYELESAIKAN INI_
    -   **45-50%**: Pengembangan API Endpoint untuk Aplikasi User (di `Api/UserController`: `depositHistory`, `generateRvmLoginToken`). Pengembangan/penyesuaian API Auth di `Api/AuthController` (registrasi, login, logout, profil via token; **Google Sign-In via ID Token API**).
    -   **50-55%**: Pengembangan API Endpoint Dasar untuk Aplikasi Dashboard Admin. Dokumentasi API.
-   (Fase 3, 4, 5, 6 tidak berubah signifikan oleh revisi Auth ini)

## (Sisa Bagian Dokumentasi seperti Konsistensi, Rekomendasi Teknologi tidak berubah signifikan)

---

## Progres Pengembangan Saat Ini: **Masih di sekitar 45%** (Kita belum menyelesaikan implementasi dan pengujian endpoint User API).
