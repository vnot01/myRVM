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

# Troubleshooting Guide: Sistem RVM Laravel & Raspberry Pi

Dokumen ini merangkum proses identifikasi dan penyelesaian masalah umum yang dihadapi selama pengembangan sistem RVM.

## Daftar Isi

1.  [Google Sign-In API (via ID Token) Error - "Invalid Google ID token."](#google-sign-in-api-via-id-token-error---invalid-google-id-token)
2.  [Kamera USB Tidak Terdeteksi atau Gagal Inisialisasi di Raspberry Pi](#kamera-usb-tidak-terdeteksi-atau-gagal-inisialisasi-di-raspberry-pi)
3.  [Scan QR Code Tidak Stabil atau Mendeteksi Token Lama Berulang Kali](#scan-qr-code-tidak-stabil-atau-mendeteksi-token-lama-berulang-kali)

---

## 1. Google Sign-In API (via ID Token) Error - "Invalid Google ID token."

**Gejala:**

-   Request `POST` ke `/api/auth/google/token-signin` dengan `id_token` dari Google OAuth Playground mengembalikan error `{"status":"error","message":"Invalid Google ID token."}`.
-   Log Laravel menunjukkan `local.WARNING: Invalid Google ID token received by API.`

**Langkah Identifikasi & Solusi:**

1.  **Dapatkan ID Token yang Benar dari OAuth Playground:**

    -   Buka [Google OAuth 2.0 Playground](https://developers.google.com/oauthplayground/).
    -   **PENTING:** Klik ikon **roda gigi (Settings)** -> Centang **"Use your own OAuth credentials"** -> Masukkan **`GOOGLE_CLIENT_ID`** dan **`GOOGLE_CLIENT_SECRET`** dari file `.env` aplikasi Laravel Anda.
    -   **PENTING (GCP):** Pastikan `https://developers.google.com/oauthplayground` telah ditambahkan sebagai **Authorized redirect URI** di Google Cloud Console untuk Client ID Anda.
    -   Di Step 1 Playground, otorisasi scope `userinfo.email` dan `userinfo.profile`.
    -   Di Step 2 Playground, klik "Exchange authorization code for tokens". Salin nilai `"id_token"`.

2.  **Verifikasi Token dan Client ID di Backend:**

    -   **Decode ID Token:** Gunakan jwt.io untuk melihat payload token. Perhatikan klaim `"aud"` (Audience).
    -   **Log Client ID di Backend:** Di `Api/AuthController@signInWithGoogleIdToken`, log nilai `config('services.google.client_id')` yang digunakan saat `new \Google_Client(...)`.
    -   **Bandingkan:** Nilai `aud` dari token harus **sama persis** dengan `client_id` yang digunakan backend. Jika berbeda, kembali ke langkah 1 dan pastikan Playground menggunakan kredensial Anda.
    -   **Bersihkan Cache:** `php artisan config:clear` di Laravel.

3.  **Periksa Library Google API Client:**

    -   Pastikan terinstal dan terbaru:
        ```bash
        composer require google/apiclient
        composer update google/apiclient guzzlehttp/guzzle
        ```
    -   Ini adalah solusi yang berhasil dalam kasus kita setelah Client ID dipastikan cocok.

4.  **Verifikasi Waktu Server & Konektivitas:**
    -   Pastikan jam server backend sinkron dengan NTP.
    -   Pastikan server backend bisa konek ke `www.googleapis.com/oauth2/v3/certs` (misalnya, via `curl`).

---

## 2. Kamera USB Tidak Terdeteksi atau Gagal Inisialisasi di Raspberry Pi

**Gejala:**

-   Skrip Python (`rvm_main_app.py`) gagal membuka satu atau kedua kamera USB.
-   Log RPi menunjukkan error seperti:
    -   `CAMERA ERROR: Kamera Objek @ indeks X tidak bisa dibuka.`
    -   `WARN: ... cap_v4l.cpp ... can't open camera by index`
    -   `ERROR: ... obsensor_uvc_stream_channel.cpp ... Camera index out of range`
    -   `VIDIOC_REQBUFS: errno=19 (No such device)`

**Langkah Identifikasi & Solusi:**

1.  **Verifikasi Deteksi Kamera oleh OS Linux (RPi):**

    -   Jalankan `v4l2-ctl --list-devices` di terminal RPi. Ini akan mendaftar semua perangkat video yang dikenali sistem dan path device-nya (misalnya, `/dev/video0`, `/dev/video1`, `/dev/video2`).
    -   Identifikasi nama kamera Anda (misalnya, "Integrated C", "UGREEN camera") dan catat path `/dev/videoX` yang terkait.

2.  **Pendekatan Inisialisasi Kamera Bergantian (Hardcode Indeks & Resolusi):**

    -   Karena masalah stabilitas dengan dua kamera aktif bersamaan atau deteksi dinamis yang rumit, kita kembali ke pendekatan di mana kamera QR dan kamera Objek diinisialisasi dan dilepas secara bergantian hanya saat akan digunakan.
    -   **Konfigurasi Hardcode di `rvm_main_app.py`:**

        ```python
        CAMERA_QR_INDEX = 0       # GANTI dengan indeks numerik kamera QR (misal, dari /dev/video0)
        CAMERA_QR_WIDTH = 640
        CAMERA_QR_HEIGHT = 480    # Resolusi lebih rendah untuk QR scan

        CAMERA_OBJECT_INDEX = 2   # GANTI dengan indeks numerik kamera Objek (misal, dari /dev/video2)
        CAMERA_OBJECT_WIDTH = 1024 # Atau resolusi lain yang stabil untuk kamera objek
        CAMERA_OBJECT_HEIGHT = 768
        ```

    -   **Fungsi Inisialisasi Tunggal per Pemanggilan:**
        Buat fungsi `initialize_camera(index, width, height, purpose)` yang membuka satu kamera, menyetel resolusi, dan mengembalikan objek `VideoCapture`.
    -   **Fungsi Pelepasan Kamera:**
        Buat fungsi `release_camera(capture_object, purpose)` untuk melepas kamera.
    -   **Logika State Machine:**
        -   Di `STATE_STARTUP`: Tidak ada inisialisasi kamera otomatis.
        -   Di `STATE_IDLE`: Pastikan tidak ada kamera aktif (`active_camera_capture = release_camera(...)`).
        -   Di `STATE_WAITING_FOR_USER_QR`: Panggil `active_camera_capture = initialize_camera(CAMERA_QR_INDEX, ...)` sebelum loop scan. Panggil `active_camera_capture = release_camera(...)` setelah scan selesai.
        -   Di `STATE_CAPTURING_IMAGE`: Panggil `active_camera_capture = initialize_camera(CAMERA_OBJECT_INDEX, ...)` sebelum mengambil gambar. Panggil `active_camera_capture = release_camera(...)` setelah gambar diambil.
        -   Di `STATE_ERROR` dan blok `finally` utama: Pastikan `active_camera_capture` dilepas.

3.  **Periksa Masalah Daya USB:**

    -   Jika menggunakan dua kamera, pastikan RPi memiliki catu daya yang cukup (minimal 3A untuk RPi 4B).
    -   Pertimbangkan menggunakan **powered USB hub** jika daya dari port RPi tidak mencukupi untuk kedua kamera.

4.  **Kabel USB:** Gunakan kabel USB yang berkualitas baik dan tidak terlalu panjang.

5.  **Jeda Saat Inisialisasi Kamera:** Berikan jeda singkat (`time.sleep(0.5)` atau `time.sleep(1)`) setelah memanggil `cv2.VideoCapture(index)` sebelum mencoba menyetel properti atau membaca frame. Beberapa kamera memerlukan waktu untuk "bangun".

6.  **"Pemanasan" Kamera (Workaround Kasar):** Setelah kamera berhasil dibuka (`isOpened()`), coba lakukan beberapa `cap.read()` dummy untuk menstabilkan stream sebelum penggunaan sebenarnya.

---

## 3. Scan QR Code Tidak Stabil atau Mendeteksi Token Lama Berulang Kali

**Gejala:**

-   RPi mendeteksi token QR yang sama dari sesi scan sebelumnya, meskipun QR code baru sudah ditampilkan ke kamera.
-   Muncul warning `_zbar_decode_databar: Assertion "seg->finder >= 0" failed.` dari ZBar.
-   Scan timeout tanpa mendeteksi QR code yang valid meskipun sudah ditampilkan.

**Langkah Identifikasi & Solusi:**

1.  **Pastikan QR Code yang Di-scan adalah Token 40 Karakter yang Benar:**

    -   Token yang di-generate oleh `/api/user/generate-rvm-token` adalah string 40 karakter.
    -   **HANYA string token ini yang harus diubah menjadi gambar QR Code.** Jangan men-scan QR code yang berisi URL atau data lain.

2.  **Reset Variabel Scan di Awal `STATE_WAITING_FOR_USER_QR`:**

    -   Setiap kali RVM masuk ke state ini, pastikan variabel seperti `scanned_qr_token_global`, `qr_data_from_scan`, `last_detected_qr`, `consecutive_qr_reads`, `frames_without_qr` diinisialisasi ulang ke nilai defaultnya. Ini mencegah data dari scan sebelumnya memengaruhi sesi scan baru. (Ini sudah diimplementasikan di revisi kode terakhir).

3.  **Logika Deteksi Stabil QR Code:**

    -   Implementasikan counter (`consecutive_qr_reads`) yang mengharuskan QR code yang sama terdeteksi beberapa kali berturut-turut (misalnya, 3-5 kali) sebelum dianggap stabil dan valid.
    -   Jika deteksi berubah, reset counter.
    -   Jika beberapa frame tidak mendeteksi QR sama sekali, reset `last_detected_qr` dan counter untuk "melupakan" deteksi sebelumnya.

4.  **Kualitas Gambar dan Tampilan QR:**

    -   **Pencahayaan:** Pastikan area scan QR memiliki pencahayaan yang baik.
    -   **Resolusi Kamera QR:** Gunakan resolusi sedang (misalnya, 640x480) untuk `cap_qr`. Resolusi terlalu tinggi bisa memperlambat proses dan tidak selalu lebih baik untuk QR.
    -   **Kualitas QR Code:** Pastikan QR code yang ditampilkan di layar HP/monitor cukup besar, kontrasnya tinggi, tidak blur, dan memiliki quiet zone yang cukup.
    -   **Jarak dan Sudut Kamera:** Posisikan kamera QR pada jarak dan sudut yang optimal.

5.  **Jeda Sebelum Scan:** Beri jeda singkat (1-2 detik) setelah RVM masuk `STATE_WAITING_FOR_USER_QR` dan sebelum loop scan aktif dimulai, untuk memberi waktu pengguna menyiapkan QR code di depan kamera.

6.  **Menangani Error `zbar_decode_databar`:**
    -   Error assertion ini dari library ZBar biasanya karena kualitas frame yang buruk.
    -   Jika ini sering terjadi, fokus pada peningkatan kualitas gambar (pencahayaan, fokus, resolusi).
    -   Loop scan Anda akan timeout jika deteksi stabil tidak tercapai, yang merupakan penanganan yang wajar.

---
