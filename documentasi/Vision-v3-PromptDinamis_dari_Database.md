# Proposal Pengembangan Sistem Multi-Aplikasi RVM - Revisi 4 (Vision v3 - Prompt Dinamis dari Database)

Dokumen ini merangkum rencana pengembangan sistem RVM dengan update terkait **Vision v3**, sebuah pendekatan untuk mengelola prompt dan konfigurasi Google Gemini Vision API secara dinamis melalui database, memungkinkan fine-tuning tanpa mengubah kode.

**Pemberitahuan Revisi Penting:**
*   (Revisi 1): Operasi CV terpusat di Backend Laravel.
*   (Revisi 2): Penambahan Fase 6 (Deployment & Transisi Docker). Pendaftaran middleware Laravel 11+.
*   (Revisi 3): Pemisahan `AuthController` untuk Web dan API.
*   **(Revisi 4): Implementasi Vision v3 - Prompt Gemini Vision dan Konfigurasi Generasi disimpan dan dikelola melalui tabel `prompt_templates` di database.**

## 1. Arsitektur Umum Sistem
(Tidak berubah secara fundamental, namun interaksi `GeminiVisionService` sekarang melibatkan database).

## 2. Detail Komponen Sistem (Perubahan Utama)

### 2.1. Backend Inti (Central API - Laravel)

*   **Database:**
    *   **Penambahan Tabel Baru:** `prompt_templates`
        *   **Kolom:** `id`, `name` (unique), `description`, `target_prompt` (text), `condition_prompt` (text), `label_guidance` (text), `output_instructions` (text), `generation_config` (json, nullable), `is_active` (boolean, default false, indexed), `created_at`, `updated_at`.
        *   **Tujuan:** Menyimpan berbagai versi template prompt dan konfigurasi generasi (`temperature`, `maxOutputTokens`, dll.) untuk Gemini Vision API. Satu template ditandai sebagai `is_active=true` untuk digunakan oleh sistem.
*   **Model Eloquent:**
    *   **Penambahan Model Baru:** `App\Models\PromptTemplate`
        *   Memiliki properti `fillable`, `casts` (untuk `generation_config` ke array, `is_active` ke boolean).
        *   Memiliki scope `active()` untuk mudah mengambil template yang aktif.
        *   Memiliki metode `buildFullPrompt()` untuk menggabungkan bagian-bagian prompt menjadi satu string instruksi lengkap.
*   **Service Layer:**
    *   **Modifikasi `App\Services\GeminiVisionService.php`:**
        *   Tidak lagi menyimpan prompt secara hardcoded.
        *   Menambahkan metode `getActivePromptTemplate()` yang mengambil template aktif dari database (menggunakan model `PromptTemplate`) dengan implementasi caching (misalnya, `Cache::remember()`) untuk efisiensi.
        *   Metode `analyzeImageFromFile()` sekarang memanggil `getActivePromptTemplate()`.
        *   Metode `callGeminiApi()` sekarang menerima `prompt` (string) dan `generationConfig` (array, nullable) sebagai argumen, yang didapat dari template aktif.
*   **API Endpoint Admin (Akan Datang):**
    *   Perlu endpoint baru (misalnya, di bawah `/api/admin/prompt-templates/*`) untuk mengelola CRUD (Create, Read, Update, Delete) pada `prompt_templates`.
    *   Perlu endpoint khusus untuk mengaktifkan (`activate`) sebuah template (yang juga harus menonaktifkan template aktif sebelumnya dan membersihkan cache template aktif).

### (Komponen Aplikasi RVM, Aplikasi User, Dashboard Admin tidak berubah secara langsung oleh revisi ini, namun fungsionalitas Dashboard Admin akan diperluas nanti untuk mengelola template).

## 3. Kunci API Google Gemini Vision
(Tidak berubah)

## 4. Peta Progres Pengembangan (Revisi 4 - Update Fase 2 dan Penambahan Tugas Admin)

*   **Fase 1: Pondasi Backend Inti & Desain Database (0% - 25%)** - *SELESAI*
    *   *Termasuk migration tabel `prompt_templates` sebagai bagian dari pondasi.*

*   **Fase 2: Pengembangan API Backend Inti (Termasuk Layanan CV Terpusat) (25% - 55%)** - *SEDANG BERJALAN (Target saat ini 50% -> 55%)*
    *   **25-35%**: Implementasi `GeminiVisionService.php` (versi awal). - *SELESAI*
    *   **35-45%**: Pengembangan API Endpoint RVM (`deposit`, `authenticate`, `validateUserToken`). Middleware `auth.rvm`. - *SELESAI*
    *   **45-50%**: Pengembangan API Endpoint User (`register`, `login`, `logout`, `profile`, `google/token-signin`, `depositHistory`, `generateRvmToken`). Pemisahan Auth Controller Web & API. - *SELESAI*
    *   **50-55%**:
        *   **Modifikasi `GeminiVisionService`** untuk menggunakan `PromptTemplate` aktif dari database (Vision v3).
        *   Pembuatan **Seeder** (`PromptTemplateSeeder`) untuk template awal.
        *   Pengembangan API Endpoint Admin Dasar (`/api/admin/stats`).
        *   Pengembangan API Endpoint Admin Vision Test (`/api/admin/vision-test` - sekarang juga menggunakan template aktif).
        *   Pembuatan & Pendaftaran Middleware `CheckRole`.
        *   Definisi Rute API Admin dasar (`/api/admin/*`) dilindungi role.
        *   Dokumentasi API dasar.

*   **Fase 3: Pengembangan Aplikasi RVM (55% - 75%)** - *Belum Dimulai*
*   **Fase 4: Pengembangan Aplikasi User & Aplikasi Dashboard Admin (Frontend) (75% - 90%)** - *Belum Dimulai*
    *   *Pengembangan **CRUD Prompt Templates** di frontend Dashboard Admin akan masuk di fase ini.*
*   **Fase 5: Integrasi, Pengujian, & Penyempurnaan (90% - 97%)** - *Belum Dimulai*
*   **Fase 6: Persiapan Deployment & Transisi ke Docker (97% - 100%)** - *Belum Dimulai*

## 5. Konsistensi Data dan Variabel
(Tidak berubah)

## 6. Rekomendasi Teknologi Utama
(Tidak berubah signifikan, hanya penambahan tabel `prompt_templates` di MariaDB).

## 7. Pendaftaran Middleware Khusus
(Tidak berubah - `auth.rvm`, `role` didaftarkan di `bootstrap/app.php`).

## Keuntungan Pendekatan Vision v3 (Prompt Dinamis dari Database)

1.  **Fleksibilitas Tinggi:** Memungkinkan perubahan prompt dan parameter (`temperature`, `maxOutputTokens`, dll.) secara real-time melalui Dashboard Admin tanpa perlu mengubah kode backend atau melakukan deployment ulang.
2.  **Fine-Tuning Lebih Mudah:** Admin dapat dengan mudah bereksperimen dengan berbagai versi prompt untuk meningkatkan akurasi deteksi Gemini Vision API terhadap jenis sampah spesifik atau kondisi pencahayaan yang berbeda.
3.  **Iterasi Cepat:** Proses coba-ganti-uji prompt menjadi jauh lebih cepat karena hanya melibatkan perubahan data di database dan pembersihan cache.
4.  **Non-Developer Friendly (Sebagian):** Admin yang tidak memiliki latar belakang programming (namun memahami konsep prompt engineering) dapat berkontribusi dalam meningkatkan performa sistem deteksi.
5.  **Versioning dan Histori:** Database menyimpan histori template prompt yang pernah digunakan. Jika prompt baru ternyata lebih buruk, mudah untuk kembali ke versi sebelumnya dengan mengaktifkan template lama.
6.  **A/B Testing (Potensial):** Di masa depan, sistem bisa dikembangkan untuk melakukan A/B testing dengan mengarahkan sebagian request ke template prompt yang berbeda untuk membandingkan hasilnya secara kuantitatif.
7.  **Sentralisasi Pengelolaan Prompt:** Semua prompt dan konfigurasinya tersimpan rapi di satu tempat (database), bukan tersebar di kode.

---