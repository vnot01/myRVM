# Troubleshooting Guide:

---

## Google Sign-In API (via ID Token) Error - "Invalid Google ID token."

### Problem Description

Dokumen ini merangkum proses identifikasi dan penyelesaian masalah saat endpoint API Laravel (`/api/auth/google/token-signin`) yang seharusnya memvalidasi ID Token Google dari klien (SPA/Mobile) mengembalikan error `{"status":"error","message":"Invalid Google ID token."}` (HTTP Status 401 atau 500), padahal ID Token didapatkan dari sumber yang valid seperti Google OAuth Playground.

### Gejala

1.  Request `POST` ke endpoint API `/api/auth/google/token-signin` dengan `id_token` yang valid (diperoleh dari Google OAuth Playground atau alur klien Google SDK).
2.  Backend Laravel mengembalikan respons error JSON: `{"status":"error","message":"Invalid Google ID token."}`.
3.  Log Laravel (`storage/logs/laravel.log`) menunjukkan warning: `local.WARNING: Invalid Google ID token received by API. {"id_token_start":"..."}`. Ini mengindikasikan panggilan `$client->verifyIdToken($idToken)` di dalam controller mengembalikan `false` atau `null`.
4.  Pengujian Login Google melalui alur Web (menggunakan Laravel Socialite, misalnya untuk dashboard web) mungkin **berhasil** dengan kredensial yang sama, menunjukkan konfigurasi dasar Client ID/Secret kemungkinan benar.

### Langkah-langkah Identifikasi Masalah

Proses identifikasi melibatkan eliminasi kemungkinan penyebab satu per satu:

**Tahap 1: Verifikasi Token dan Konfigurasi Dasar**

1.  **Dapatkan ID Token yang Valid untuk Pengujian:**

    -   Gunakan **Google OAuth 2.0 Playground** ([https://developers.google.com/oauthplayground/](https://developers.google.com/oauthplayground/)).
    -   **Konfigurasi Playground dengan Kredensial Anda:**
        -   Klik ikon **roda gigi (Settings)** di pojok kanan atas.
        -   Centang **"Use your own OAuth credentials"**.
        -   Masukkan **`GOOGLE_CLIENT_ID`** dan **`GOOGLE_CLIENT_SECRET`** dari file `.env` aplikasi Laravel Anda. Klik "Close".
        -   **Penting:** Pastikan Anda telah menambahkan `https://developers.google.com/oauthplayground` sebagai **Authorized redirect URI** di Google Cloud Console untuk Client ID Anda. Jika belum, tambahkan dan simpan (tunggu beberapa menit).
    -   **Otorisasi API:**
        -   Di **Step 1**, pilih atau masukkan scope `https://www.googleapis.com/auth/userinfo.email` dan `https://www.googleapis.com/auth/userinfo.profile` (scope `openid` biasanya otomatis). Klik **"Authorize APIs"**.
        -   Selesaikan proses login/izin Google.
    -   **Dapatkan Token:**
        -   Anda akan kembali ke Playground. Di **Step 2**, klik **"Exchange authorization code for tokens"**.
        -   Di panel respons, **salin nilai string yang panjang untuk key `"id_token"`**. Ini token yang akan digunakan untuk pengujian API.

2.  **Verifikasi Token yang Dikirim ke API:**

    -   Saat menguji dengan Postman (atau klien API lain), pastikan ID Token yang Anda salin dari Playground di langkah sebelumnya **disalin dengan lengkap dan benar** ke dalam body JSON request ke `/api/auth/google/token-signin`.
    -   Contoh Body JSON Postman:
        ```json
        {
            "id_token": "PASTE_ID_TOKEN_DARI_PLAYGROUND_DI_SINI"
        }
        ```

3.  **Verifikasi Konfigurasi Client ID di Backend:**
    -   **Tujuan:** Memastikan Client ID yang digunakan oleh library `Google_Client` di backend sama dengan Client ID yang tertera sebagai Audience (`aud`) di dalam ID Token.
    -   **Tindakan 1: Decode ID Token:**
        -   Gunakan tool online seperti **jwt.io**.
        -   Paste ID Token dari Playground ke bagian "Encoded".
        -   Lihat bagian **Payload**. Cari klaim `"aud"` (Audience). Catat nilai Client ID ini.
    -   **Tindakan 2: Log Client ID Backend:**
        -   Tambahkan logging di `Api/AuthController@signInWithGoogleIdToken` _sebelum_ memanggil `$client->verifyIdToken()`:
            ```php
            $clientIdUsed = config('services.google.client_id');
            Log::info('Verifying Google ID Token with Client ID:', ['client_id_from_config' => $clientIdUsed]);
            // ... (lanjutan kode)
            ```
    -   **Tindakan 3: Bandingkan:** Jalankan request Postman lagi. Bandingkan nilai `client_id_from_config` di log Laravel dengan nilai `aud` yang Anda lihat di jwt.io. Keduanya harus **sama persis**.
    -   **Jika Tidak Cocok:** Kembali ke langkah 1 (pastikan Playground menggunakan kredensial Anda) dan verifikasi `GOOGLE_CLIENT_ID` di `.env` serta `config/services.php`. Jalankan `php artisan config:clear`. Ulangi sampai cocok.

**Tahap 2: Investigasi Proses Validasi Token di Backend**

Jika Client ID sudah dipastikan cocok tetapi error "Invalid Google ID token" masih muncul:

4.  **Periksa Instalasi & Versi Library Google:**

    -   Pastikan library Google API Client terinstal dan up-to-date.
    -   **Tindakan:** Jalankan di terminal:
        ```bash
        composer require google/apiclient
        composer update google/apiclient guzzlehttp/guzzle
        ```
    -   Perhatikan output composer untuk memastikan tidak ada error instalasi atau konflik dependensi.

5.  **Verifikasi Waktu Server:**

    -   Validasi JWT sensitif terhadap waktu. Perbedaan waktu signifikan antara server Anda dan server Google bisa menyebabkan token dianggap belum valid atau sudah kedaluwarsa.
    -   **Tindakan:** Cek waktu sistem di server backend Anda. Pastikan sudah sinkron menggunakan NTP (Network Time Protocol).

6.  **Periksa Konektivitas Jaringan & SSL/TLS:**

    -   Backend mungkin perlu menghubungi server Google untuk mengambil kunci publik guna memverifikasi tanda tangan token.
    -   **Tindakan:** Coba lakukan request dari terminal server backend Anda:
        ```bash
        curl https://www.googleapis.com/oauth2/v3/certs
        ```
        Apakah respons JSON berisi kunci berhasil ditampilkan? Jika tidak (error koneksi, timeout, error SSL), perbaiki masalah jaringan atau konfigurasi SSL/TLS server Anda.

7.  **Periksa Log Laravel Lebih Detail:**
    -   Pastikan `LOG_LEVEL=debug` di `.env`.
    -   Periksa `storage/logs/laravel.log` lagi setelah request gagal. Cari pesan error spesifik yang mungkin dilempar oleh library `google/apiclient` atau GuzzleHttp yang tidak tertangkap sebagai "Invalid Google ID token" biasa. Pertimbangkan menambahkan blok `catch (\Google\Exception $e)` di controller untuk log yang lebih spesifik.

### Solusi yang Berhasil (dalam kasus ini)

Masalah teratasi setelah menjalankan:

```bash
composer update google/apiclient guzzlehttp/guzzle
```
