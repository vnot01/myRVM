# Panduan Pengujian API Autentikasi RVM System Menggunakan Postman

Dokumen ini menyediakan panduan langkah demi langkah untuk menguji endpoint API autentikasi pengguna pada sistem RVM menggunakan Postman.

**Prasyarat:**

1.  Pastikan server backend Laravel sudah berjalan (misalnya, `php artisan serve` di `http://localhost:8000`).
2.  Postman sudah terinstal.
3.  Database sudah di-migrasi dan (opsional) di-seed dengan data awal.

**Variabel Lingkungan Postman (Opsional tapi Direkomendasikan):**
Untuk memudahkan, Anda bisa membuat Environment di Postman dan mendefinisikan variabel seperti `{{baseUrl}}`.

-   `baseUrl`: `http://localhost:8000/api` (atau URL API dev Anda)

---

## 1. Registrasi Pengguna Baru

Endpoint ini digunakan untuk membuat akun pengguna baru.

-   **Method:** `POST`
-   **URL:** `{{baseUrl}}/register`
-   **Headers:**
    -   `Accept`: `application/json`
    -   `Content-Type`: `application/json`
-   **Body:**
    -   Pilih `raw` dan tipe `JSON`.
    -   **Contoh Payload JSON:**
        ```json
        {
            "name": "Pengguna Postman",
            "email": "postman.user@example.com",
            "password": "password123",
            "password_confirmation": "password123",
            "phone_number": "08123450001",
            "citizenship": "WNI",
            "identity_type": "KTP",
            "identity_number": "3171010101010009"
        }
        ```
        _(Pastikan `email`, `phone_number`, dan `identity_number` unik jika belum ada di database)_
-   **Respons Sukses (HTTP Status 201 Created):**
    ```json
    {
        "message": "User registered successfully",
        "user": {
            "name": "Pengguna Postman",
            "email": "postman.user@example.com",
            "phone_number": "08123450001",
            "citizenship": "WNI",
            "identity_type": "KTP",
            "identity_number": "3171010101010009",
            "role": "User",
            "points": 0,
            "updated_at": "2025-05-08T10:00:00.000000Z",
            "created_at": "2025-05-08T10:00:00.000000Z",
            "id": 12
        }
    }
    ```
-   **Respons Gagal (Contoh: Validasi Error - HTTP Status 422 Unprocessable Entity):**
    ```json
    {
        "errors": {
            "email": ["The email has already been taken."],
            "password": ["The password confirmation does not match."]
        }
    }
    ```

---

## 2. Login Pengguna

Endpoint ini digunakan untuk mengotentikasi pengguna dan mendapatkan `bearer_token`.

-   **Method:** `POST`
-   **URL:** `{{baseUrl}}/login`
-   **Headers:**
    -   `Accept`: `application/json`
    -   `Content-Type`: `application/json`
-   **Body:**
    -   Pilih `raw` dan tipe `JSON`.
    -   **Contoh Payload JSON:**
        ```json
        {
            "email": "postman.user@example.com",
            "password": "password123"
        }
        ```
-   **Respons Sukses (HTTP Status 200 OK):**
    ```json
    {
        "message": "Login successful",
        "user": {
            // ... detail user ...
            "id": 12,
            "name": "Pengguna Postman",
            "email": "postman.user@example.com"
            // ...
        },
        "bearer_token": "3|abcdefghijklmnopqrstuvwxyz1234567890ABCDEF", // SALIN TOKEN INI
        "token_type": "Bearer"
    }
    ```
    **PENTING:** Salin nilai `bearer_token` yang diterima. Anda akan menggunakannya untuk request yang memerlukan otentikasi.
-   **Respons Gagal (Contoh: Kredensial Salah - HTTP Status 401 Unauthorized):**
    ```json
    {
        "message": "Invalid login credentials"
    }
    ```

---

## 3. Mendapatkan Profil Pengguna (Memerlukan Otentikasi)

Endpoint ini mengambil detail pengguna yang sedang login.

-   **Method:** `GET`
-   **URL:** `{{baseUrl}}/user`
-   **Headers:**
    -   `Accept`: `application/json`
    -   `Authorization`: `Bearer <YOUR_BEARER_TOKEN>`
        _(Ganti `<YOUR_BEARER_TOKEN>` dengan token yang Anda dapatkan dari endpoint Login)_
-   **Body:** Tidak ada (untuk GET request).
-   **Respons Sukses (HTTP Status 200 OK):**
    ```json
    {
        "id": 12,
        "name": "Pengguna Postman",
        "email": "postman.user@example.com",
        "email_verified_at": null,
        "google_id": null,
        "avatar": null,
        "phone_number": "08123450001",
        "citizenship": "WNI",
        "identity_type": "KTP",
        "identity_number": "3171010101010009",
        "points": 0,
        "role": "User",
        "is_guest": 0,
        "created_at": "2025-05-08T10:00:00.000000Z",
        "updated_at": "2025-05-08T10:00:00.000000Z"
    }
    ```
-   **Respons Gagal (Contoh: Token Tidak Valid/Tidak Ada - HTTP Status 401 Unauthorized):**
    ```json
    {
        "message": "Unauthenticated."
    }
    ```

---

## 4. Logout Pengguna (Memerlukan Otentikasi)

Endpoint ini digunakan untuk logout pengguna dan mencabut `bearer_token` yang sedang digunakan.

-   **Method:** `POST`
-   **URL:** `{{baseUrl}}/logout`
-   **Headers:**
    -   `Accept`: `application/json`
    -   `Authorization`: `Bearer <YOUR_BEARER_TOKEN>`
-   **Body:** Tidak ada.
-   **Respons Sukses (HTTP Status 200 OK):**
    ```json
    {
        "message": "Successfully logged out"
    }
    ```
    Setelah logout, `bearer_token` yang sama seharusnya tidak bisa digunakan lagi untuk mengakses endpoint terproteksi seperti `/api/user`.

---

## 5. Otentikasi dengan Google

Alur ini melibatkan interaksi browser dan API.

### 5.1. Redirect ke Google

-   **Aksi:** Buka URL ini di browser web Anda (bukan langsung dari Postman untuk alur penuh).
-   **URL:** `{{baseUrl}}/auth/google/redirect`
-   **Hasil yang Diharapkan:** Browser Anda akan diarahkan ke halaman login/otorisasi Google.

### 5.2. Callback dari Google

-   **Aksi:** Setelah Anda login dan memberikan izin di halaman Google, Google akan mengarahkan browser Anda kembali ke URL callback yang telah dikonfigurasi (misalnya, `{{baseUrl}}/auth/google/callback`) dengan parameter `code` dan `state` di URL. Endpoint Laravel Anda akan menangani ini.
-   **URL (yang dipanggil Google):** `{{baseUrl}}/auth/google/callback?code=...&state=...`
-   **Hasil yang Diharapkan (Respons dari API Anda ke Browser):**
    Browser akan menampilkan respons JSON dari endpoint callback Anda.
    ```json
    {
        "message": "Google authentication successful",
        "user": {
            // ... detail user dari Google atau yang baru dibuat/diupdate ...
            "name": "Nama Anda Dari Google",
            "email": "email.anda@gmail.com",
            "google_id": "google_user_id_unik"
            // ...
        },
        "bearer_token": "4|anotherabcdefghijklmnopqrstuvwxyz9876543210FEDCBA", // TOKEN BARU
        "token_type": "Bearer"
    }
    ```
    **PENTING:** Anda bisa menyalin `bearer_token` baru ini dan menggunakannya untuk request terotentikasi (misalnya ke `/api/user`) untuk memverifikasi sesi login via Google.

**Catatan Pengujian Google Sign-In:**

-   Pastikan Client ID, Client Secret, dan Redirect URI Anda sudah dikonfigurasi dengan benar di Google Cloud Console dan di file `.env` Laravel Anda.
-   Pastikan `APP_URL` di `.env` sudah sesuai.
-   Jika ada error `redirect_uri_mismatch`, periksa kembali konfigurasi Redirect URI Anda di Google Cloud Console; harus sama persis dengan yang diminta oleh Socialite/aplikasi Anda (termasuk `http` vs `https` dan port).

---
