# Fase 4 (Awal): Pengembangan Aplikasi User (Mobile/PWA) - Rencana dan Alur

Dokumen ini merinci rencana awal dan alur kerja untuk pengembangan Aplikasi User, yang bisa berupa aplikasi mobile native (React Native/Flutter) atau Progressive Web App (PWA) menggunakan Vue.js/React. Aplikasi ini akan berinteraksi dengan backend API Laravel yang sudah kita bangun.

**Progres Awal Tahap Ini: 75%**
_(Menyelesaikan Fase 3: Pengembangan Logika Inti Aplikasi RVM di Raspberry Pi)_

**Tujuan Utama Tahap Ini (75% -> 82%):**
Mengimplementasikan fungsionalitas inti Aplikasi User yang memungkinkan pengguna untuk mendaftar, login, mengelola profil dasar, melihat riwayat deposit, dan yang terpenting, men-generate token QR untuk berinteraksi dengan mesin RVM fisik.

## Pilihan Teknologi Frontend User (Perlu Diputuskan)

Sebelum memulai implementasi, perlu ada keputusan mengenai teknologi frontend yang akan digunakan:

1.  **Aplikasi Mobile Native (Cross-Platform):**

    -   **React Native (JavaScript/TypeScript):** Pilihan baik jika tim familiar dengan ekosistem React/JavaScript. Komunitas besar, banyak library.
    -   **Flutter (Dart):** Performa UI sangat baik, UI kaya, dikembangkan Google. Memerlukan pembelajaran Dart jika tim belum familiar.
    -   **Manfaat:** Pengalaman pengguna native, akses ke fitur perangkat keras (kamera, GPS, notifikasi push) lebih mudah.

2.  **Progressive Web App (PWA):**
    -   **Vue.js (JavaScript/TypeScript):** Dianggap memiliki kurva belajar landai, integrasi baik dengan Laravel (misalnya via Inertia.js jika ada bagian web lain).
    -   **React (JavaScript/TypeScript):** Sangat populer, ekosistem besar.
    -   **Manfaat:** Bisa diakses dari browser di berbagai perangkat, tidak perlu instalasi dari app store (bisa di- "add to homescreen"), pengembangan bisa lebih cepat.
    -   **Keterbatasan:** Akses ke beberapa fitur perangkat keras native mungkin lebih terbatas atau memerlukan workaround.

**Untuk panduan ini, alur umumnya akan sama, tetapi detail implementasi UI dan manajemen state akan bergantung pada framework yang dipilih.**

## Rencana Aksi dan Fitur yang Akan Diimplementasikan:

### 1. Setup Proyek Frontend

    *   Inisialisasi proyek baru menggunakan CLI dari framework yang dipilih (misalnya, `create-react-native-app`, `flutter create`, `vue create`, `create-react-app`).
    *   Setup struktur folder dasar.
    *   Instalasi library yang diperlukan (misalnya, `axios` atau `fetch` untuk HTTP request, library navigasi, library manajemen state, library untuk generate QR code).

### 2. Implementasi Alur Autentikasi Pengguna

Pengguna harus bisa mendaftar dan login ke aplikasi. Ini akan melibatkan pemanggilan endpoint API backend yang sudah ada.

-   **a. Halaman Registrasi:**
    -   **UI:** Form untuk input nama, email, password, konfirmasi password (dan field opsional lain seperti nomor telepon jika didukung backend).
    -   **Logika:**
        1.  Validasi input di sisi klien.
        2.  Saat submit, buat request `POST` ke `/api/auth/register` di backend Laravel.
        3.  **Respons Sukses:** Backend akan mengembalikan data user dan `access_token` (Sanctum). Simpan token ini dengan aman di perangkat/browser (misalnya, SecureStorage/AsyncStorage di mobile, localStorage/sessionStorage atau HttpOnly cookie untuk PWA). Arahkan pengguna ke halaman utama/dashboard aplikasi.
        4.  **Respons Gagal:** Tampilkan pesan error dari backend (misalnya, email sudah terdaftar, password tidak cocok).
-   **b. Halaman Login (Email/Password):**
    -   **UI:** Form untuk input email dan password.
    -   **Logika:**
        1.  Saat submit, buat request `POST` ke `/api/auth/login`.
        2.  **Respons Sukses:** Backend mengembalikan data user dan `access_token`. Simpan token, arahkan ke halaman utama.
        3.  **Respons Gagal:** Tampilkan pesan "Invalid credentials".
-   **c. Login dengan Google (Sisi Klien):**
    -   **UI:** Tombol "Login dengan Google".
    -   **Logika (Pendekatan ID Token):**
        1.  Gunakan SDK Google Sign-In resmi untuk platform frontend Anda (iOS, Android, JavaScript).
        2.  Saat tombol diklik, picu alur login Google SDK.
        3.  Setelah pengguna berhasil login dengan Google di sisi klien, SDK akan memberikan **ID Token Google**.
        4.  Aplikasi frontend mengirimkan **ID Token Google ini** ke backend Laravel melalui request `POST` ke `/api/auth/google/token-signin`.
        5.  **Respons Sukses:** Backend memvalidasi ID Token, membuat/login user, dan mengembalikan `access_token` (Sanctum). Simpan token Sanctum ini, arahkan ke halaman utama.
        6.  **Respons Gagal:** Tampilkan pesan error.
-   **d. Logout:**
    -   **UI:** Tombol/link logout.
    -   **Logika:**
        1.  Buat request `POST` ke `/api/auth/logout` dengan menyertakan token Sanctum user di header `Authorization: Bearer <token>`.
        2.  Hapus token Sanctum yang tersimpan di sisi klien.
        3.  Arahkan pengguna ke halaman login.
-   **Manajemen Token/Sesi:** Implementasikan cara untuk menyimpan dan mengirim token Sanctum secara otomatis untuk request API yang memerlukan otentikasi. Gunakan interceptor HTTP (jika library HTTP Anda mendukung) untuk menambahkan header `Authorization` secara otomatis.

### 3. Implementasi Halaman Profil Pengguna

-   **UI:** Menampilkan informasi pengguna seperti nama, email, dan total poin saat ini. Mungkin ada opsi untuk mengedit profil (fitur lanjutan).
-   **Logika:**
    1.  Saat halaman dimuat (dan pengguna sudah login), buat request `GET` ke `/api/auth/user` (dengan token Sanctum).
    2.  Tampilkan data user yang diterima dari respons.

### 4. Implementasi Fitur Generate Token QR untuk RVM

Ini adalah fitur inti yang menghubungkan Aplikasi User dengan RVM fisik.

-   **UI:**
    -   Tombol "Siapkan Kode Deposit" atau serupa.
    -   Setelah diklik, tampilkan gambar QR Code yang besar dan jelas.
    -   Tampilkan informasi masa berlaku token (misalnya, "Kode ini berlaku selama 5 menit").
-   **Logika:**
    1.  Saat tombol diklik, buat request `POST` ke `/api/user/generate-rvm-token` (dengan token Sanctum).
    2.  Backend akan merespons dengan `rvm_login_token` (string 40 karakter) dan `expires_in_seconds`.
    3.  Aplikasi frontend menggunakan library QR code generator (banyak tersedia untuk JavaScript, React Native, Flutter) untuk mengubah string `rvm_login_token` tersebut menjadi gambar QR Code.
    4.  Tampilkan gambar QR Code dan informasi masa berlaku kepada pengguna.

### 5. Implementasi Halaman Riwayat Deposit

-   **UI:** Menampilkan daftar transaksi deposit yang telah dilakukan pengguna, termasuk tanggal, jenis item (jika tersedia dari backend), poin yang didapat, dan mungkin lokasi RVM. Tampilan terpaginasi.
-   **Logika:**
    1.  Saat halaman dimuat, buat request `GET` ke `/api/user/deposit-history` (dengan token Sanctum). Endpoint ini sudah mendukung paginasi.
    2.  Tampilkan daftar deposit. Implementasikan "load more" atau navigasi halaman jika datanya banyak.

## Alur Kerja Umum Aplikasi User

1.  **Buka Aplikasi:**
    -   Cek apakah ada token login yang tersimpan.
    -   Jika ada, coba validasi token dengan memanggil endpoint profil (`/api/auth/user`). Jika valid, arahkan ke halaman utama. Jika tidak valid (misalnya, token expired), hapus token dan arahkan ke halaman login.
    -   Jika tidak ada token, tampilkan halaman login/registrasi.
2.  **Pengguna Login/Registrasi:** Ikuti alur autentikasi. Setelah berhasil, simpan token dan arahkan ke halaman utama.
3.  **Di Halaman Utama/Dashboard Aplikasi User:**
    -   Tampilkan info profil dasar (nama, poin).
    -   Sediakan navigasi ke:
        -   Generate Token QR RVM.
        -   Riwayat Deposit.
        -   Logout.
4.  **Saat Akan Deposit di RVM:**
    -   Pengguna navigasi ke fitur "Generate Token QR RVM".
    -   Aplikasi memanggil API, mendapatkan `rvm_login_token`, dan menampilkannya sebagai QR Code.
    -   Pengguna memindai QR Code ini di mesin RVM fisik.
5.  **Setelah Deposit Selesai (di RVM Fisik):**
    -   Pengguna bisa membuka halaman profil atau riwayat deposit di Aplikasi User untuk melihat pembaruan poin atau transaksi baru.

## Hal-hal yang Perlu Diperhatikan

-   **Keamanan Token:** Simpan token Sanctum dengan aman di sisi klien.
-   **Error Handling:** Tangani berbagai kemungkinan error API (koneksi gagal, error validasi, error server) dan tampilkan pesan yang informatif kepada pengguna.
-   **User Experience (UX):** Buat alur yang intuitif dan mudah digunakan. Berikan feedback visual saat loading atau saat ada aksi.
-   **State Management Klien:** Gunakan solusi manajemen state yang sesuai dengan framework Anda (misalnya, Redux/Context API untuk React/React Native, Vuex/Pinia untuk Vue, Provider/Bloc/GetX untuk Flutter) untuk mengelola data pengguna, status login, dll.

---

Ini adalah rencana awal untuk pengembangan Aplikasi User. Kita akan mulai dengan setup proyek dan alur autentikasi terlebih dahulu. Setelah itu, kita bisa mengimplementasikan fitur-fitur lainnya satu per satu.

**Kapan Boleh Mulai Uji Coba (untuk Aplikasi User):**
Anda bisa mulai menguji setiap fitur segera setelah diimplementasikan:

-   Registrasi dan Login: Setelah halaman dan logika API call selesai.
-   Profil User: Setelah halaman profil dan API call selesai.
-   Generate QR: Setelah halaman dan API call serta library QR code terintegrasi.
-   Riwayat: Setelah halaman riwayat dan API call selesai.

Penting untuk menguji interaksi dengan backend API secara menyeluruh.
