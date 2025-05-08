# Panduan Pengujian API Deposit RVM System Menggunakan Postman

Dokumen ini menyediakan panduan langkah demi langkah untuk menguji endpoint API deposit pada sistem Reverse Vending Machine (RVM) menggunakan Postman.

## Prasyarat

1.  Aplikasi backend Laravel RVM sudah berjalan (misalnya, melalui `php artisan serve` atau server lokal lainnya).
2.  Postman sudah terinstal.
3.  Database sudah di-seed dengan data awal, termasuk:
    -   Setidaknya satu RVM di tabel `reverse_vending_machines` dengan `api_key` yang valid dan status `active`.
    -   Setidaknya satu user di tabel `users` yang bisa digunakan sebagai `user_identifier`.
4.  File gambar (JPEG/PNG) item (botol/kaleng) untuk diunggah.
5.  Middleware `AuthenticateRvm` dan rute API `/api/rvm/deposit` sudah dikonfigurasi dengan benar.

## Variabel Lingkungan Postman (Opsional tapi Direkomendasikan)

Untuk kemudahan, Anda bisa mengatur variabel lingkungan di Postman:

-   `BASE_URL`: URL dasar aplikasi Anda (misalnya, `http://localhost:8000` atau `http://your-app.test`).
-   `RVM_API_KEY`: API Key dari salah satu RVM dummy yang aktif (misalnya, `RVM001-DvyJCmY3hNBhnpa3Ro26faTJYe7n8Zwm`).
-   `USER_ID_TEST`: ID dari user dummy untuk pengujian (misalnya, `1` atau `2`).

## Langkah-langkah Pengujian Endpoint Deposit

Endpoint yang akan diuji: `/api/rvm/deposit`

1.  **Buka Postman dan Buat Request Baru:**

    -   Klik tombol `+` untuk membuka tab request baru.

2.  **Pilih Metode Request:**

    -   Pilih metode HTTP: `POST`.

3.  **Masukkan URL Request:**

    -   Masukkan URL: `{{BASE_URL}}/api/rvm/deposit` (jika menggunakan variabel lingkungan)
    -   Atau URL lengkap: `http://localhost:8000/api/rvm/deposit`

4.  **Atur Headers:**

    -   Pindah ke tab `Headers`.
    -   Tambahkan header berikut:
        -   Key: `Accept`, Value: `application/json`
        -   Key: `X-RVM-ApiKey`, Value: `{{RVM_API_KEY}}` (atau API Key RVM Anda secara manual)

5.  **Atur Body Request:**

    -   Pindah ke tab `Body`.
    -   Pilih tipe body: `form-data`.
    -   Tambahkan key-value pair berikut:
        -   **Key 1:**
            -   Nama Key: `image`
            -   Di ujung kanan kolom Value, klik dropdown dan pilih `File`.
            -   Klik tombol `Select Files` dan pilih file gambar item (misalnya, `botol_kosong.jpg`, `kaleng_isi.png`).
        -   **Key 2:**
            -   Nama Key: `user_identifier`
            -   Value: `{{USER_ID_TEST}}` (atau ID user Anda secara manual, contoh: `1`).

    ![Contoh Setup Body Postman](https_placeholder_image_url_postman_body_setup.png)

6.  **Kirim Request:**

    -   Klik tombol `Send`.

7.  **Analisis Respons:**

    -   **Status Code:**
        -   `200 OK`: Biasanya menandakan request berhasil diproses (baik item diterima maupun ditolak secara logis). Periksa body respons untuk detailnya.
        -   `401 Unauthorized`: Kemungkinan `X-RVM-ApiKey` salah, tidak ada, atau RVM tidak aktif (jika middleware/controller menangani ini dengan 401).
        -   `422 Unprocessable Entity`: Validasi input gagal (misalnya, tidak ada gambar, `user_identifier` kosong, atau format gambar tidak didukung). Body respons akan berisi detail `errors`.
        -   `500 Internal Server Error`: Terjadi error di sisi server (misalnya, error saat memanggil API Gemini, error database, atau bug di kode). Periksa log Laravel (`storage/logs/laravel.log`) untuk detailnya.
    -   **Body Respons (JSON):**
        -   Jika berhasil (item diterima):
            ```json
            {
                "status": "success",
                "message": "Item accepted!",
                "item_type": "PET_MINERAL_EMPTY", // Contoh
                "points_awarded": 10, // Contoh
                "deposit_id": 25, // Contoh
                "user_total_points": 110 // Contoh
            }
            ```
        -   Jika ditolak secara logis (misalnya, item tidak dikenali, ada isi):
            ```json
            {
                "status": "rejected",
                "reason": "REJECTED_HAS_CONTENT_OR_TRASH", // Contoh
                "message": "Item rejected. Please take your item back. Reason: REJECTED HAS CONTENT OR TRASH", // Contoh
                "item_type": "REJECTED_HAS_CONTENT_OR_TRASH", // Contoh
                "points_awarded": 0,
                "deposit_id": 24 // Contoh
            }
            ```
        -   Perhatikan field `item_type`, `reason`, `points_awarded`, dan `message`.

8.  **Verifikasi di Database (Opsional tapi Penting):**
    -   Buka tool database Anda.
    -   Periksa tabel `deposits` untuk baris baru dengan `deposit_id` yang sesuai.
    -   Periksa kolom `detected_type`, `points_awarded`, `image_path`, `gemini_raw_label`, dan `gemini_raw_response`. Kolom `gemini_raw_label` dan `gemini_raw_response` sangat berguna untuk memahami hasil dari Gemini dan menyempurnakan logika interpretasi Anda.
    -   Jika item diterima dan poin diberikan, periksa tabel `users` untuk memastikan kolom `points` pengguna yang sesuai telah diperbarui.

## Skenario Pengujian Tambahan

-   **Tanpa Header `X-RVM-ApiKey`:** Harusnya menghasilkan error `401 Unauthorized` (atau pesan dari middleware Anda).
-   **Dengan `X-RVM-ApiKey` yang Salah/Tidak Valid:** Harusnya menghasilkan error `401 Unauthorized`.
-   **RVM dengan Status `inactive` atau `maintenance`:** Harusnya menghasilkan error `403 Forbidden` atau pesan yang sesuai dari middleware/controller.
-   **Tanpa Field `image` di Body:** Harusnya menghasilkan error `422 Unprocessable Entity`.
-   **Tanpa Field `user_identifier` di Body:** Harusnya menghasilkan error `422 Unprocessable Entity`.
-   **Dengan Format File `image` yang Tidak Didukung:** Harusnya menghasilkan error `422 Unprocessable Entity`.
-   **Dengan Gambar yang Sangat Berbeda dari Botol/Kaleng:** Amati `gemini_raw_label` dan bagaimana logika interpretasi Anda menanganinya (kemungkinan besar `REJECTED_UNIDENTIFIED` atau `REJECTED_UNKNOWN_TYPE`).
-   **Dengan Gambar Botol Kosong (Beberapa Jenis):** Uji apakah logika interpretasi Anda benar mengklasifikasikan dan memberi poin.
-   **Dengan Gambar Botol/Kaleng Berisi atau Ada Sampah:** Uji apakah terdeteksi sebagai `REJECTED_HAS_CONTENT_OR_TRASH`.

Dengan mengikuti panduan ini, Anda dapat menguji fungsionalitas inti dari API deposit RVM Anda secara menyeluruh. Iterasi pada logika interpretasi label Gemini berdasarkan hasil pengujian akan menjadi kunci.
