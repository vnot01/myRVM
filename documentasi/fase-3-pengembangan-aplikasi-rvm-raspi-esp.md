# Fase 3: Pengembangan Aplikasi RVM (Perangkat Lunak di Mesin Fisik) - Menggunakan Raspberry Pi 4B & ESP32

Dokumen ini merinci alur kerja, diagram pin (konseptual untuk komunikasi), dan metode simulasi untuk pengembangan awal perangkat lunak RVM menggunakan Raspberry Pi 4B sebagai unit kontrol utama (pengganti sementara Jetson Orin Nano) dan ESP32-WROVER-E untuk kontrol sensor/aktuator. Kamera USB akan digunakan dengan Raspberry Pi 4B.

**Progres Awal Fase Ini: 55%**
**Target Penyelesaian Fase Ini: 75%**

## 1. Komponen Perangkat Keras yang Digunakan (Tahap Ini)

*   **Unit Kontrol Utama:** Raspberry Pi 4B
*   **Kontroler Sensor/Aktuator:** ESP32-WROVER-E (v1.6)
*   **Kamera:** Kamera USB Universal (terhubung ke Raspberry Pi 4B)
*   **Koneksi RPi-ESP32:** Kabel Jumper (untuk Komunikasi Serial UART)
*   **Periferal (Awalnya Disimulasikan):**
    *   Sensor Proksimitas (untuk deteksi item)
    *   LED Indikator (status mesin)
    *   Motor DC/Servo (untuk mekanisme pemilah)
    *   LCD Display (jika ada, terhubung ke RPi)

## 2. Alur Kerja Utama Sistem (RPi + ESP32 + API Backend)

Berikut adalah alur kerja yang akan diimplementasikan dan diuji:

1.  **Inisiasi & Mode Idle:**
    *   **RPi (Python):** Aplikasi utama berjalan, melakukan inisialisasi (kamera, koneksi serial ke ESP32). Menunggu sinyal dari ESP32 atau input pengguna (jika ada UI di RPi). Mencetak status "Ready" ke konsol RPi (atau LCD jika ada).
    *   **ESP32 (C/C++):** Firmware berjalan, inisialisasi pin. Memonitor input simulasi untuk sensor. Siap menerima perintah dari RPi.

2.  **Simulasi Item Dimasukkan:**
    *   **ESP32:** Pengguna memicu simulasi item masuk (misalnya, menekan tombol yang terhubung ke GPIO ESP32, atau mengirim perintah via Serial Monitor Arduino IDE ke ESP32).
    *   **ESP32 -> RPi:** ESP32 mengirim pesan "ITEM_DETECTED" (atau kode serupa) ke RPi melalui koneksi serial.

3.  **RPi Memproses Deteksi Item:**
    *   **RPi:** Menerima pesan "ITEM_DETECTED" dari ESP32.
    *   **RPi:** (Untuk pengujian awal) Menggunakan `user_identifier` yang sudah ditentukan (misalnya, hardcoded atau input manual di RPi) atau mengimplementasikan alur scan QR Code jika kamera juga digunakan untuk itu (fitur lanjutan).
    *   **RPi:** Mengaktifkan Kamera USB.
    *   **RPi:** Mengambil gambar item menggunakan OpenCV atau `libcamera`.
    *   **RPi:** (Opsional) Melakukan pra-pemrosesan gambar minimal jika perlu (misalnya, resize).

4.  **RPi Mengirim Data ke Backend API Laravel:**
    *   **RPi:** Membuat request `POST` ke `{{BASE_URL}}/api/rvm/deposit`.
        *   **Header:** `X-RVM-ApiKey: <API_KEY_RVM_INI>`
        *   **Body (form-data):** `image: <file_gambar_item>`, `user_identifier: <id_user_numerik>`
    *   **RPi:** Mengirim request dan menunggu respons JSON dari backend.

5.  **Backend Laravel & Gemini Vision API:**
    *   (Proses ini sudah dikembangkan dan diuji di Fase 2: `RvmController` memanggil `GeminiVisionService`, yang memanggil Google Gemini Vision API, menginterpretasi hasil, menyimpan ke DB, dan mengembalikan respons).

6.  **RPi Menerima dan Menindaklanjuti Respons Backend:**
    *   **RPi:** Menerima respons JSON dari backend (status terima/tolak, jenis item, poin).
    *   **RPi:** Mencetak informasi status ke konsol RPi (atau LCD).
    *   **RPi -> ESP32:** Berdasarkan respons backend:
        *   Jika item diterima: Mengirim perintah ke ESP32, misalnya "AKTUATOR_TERIMA_PET" atau "LED_HIJAU_ON".
        *   Jika item ditolak: Mengirim perintah ke ESP32, misalnya "AKTUATOR_TOLAK" atau "LED_MERAH_ON".

7.  **ESP32 Menjalankan Simulasi Aksi:**
    *   **ESP32:** Menerima perintah dari RPi.
    *   **ESP32:** **Menjalankan simulasi aksi** dengan mencetak pesan ke Serial Monitor Arduino IDE (misalnya, "SIMULASI: Motor Pemilah PET Aktif", "SIMULASI: LED Merah Menyala").
    *   **ESP32 -> RPi:** Mengirim pesan konfirmasi/acknowledgment kembali ke RPi (misalnya, "ACK_AKSI_SELESAI").

8.  **Sistem Kembali Idle:**
    *   RPi mencetak status "Menunggu item berikutnya..." dan kembali ke mode idle.

## 3. Diagram Pin dan Koneksi (RPi 4B UART ke ESP32-WROVER-E UART0)

Komunikasi utama antara Raspberry Pi 4B dan ESP32-WROVER-E akan menggunakan Serial UART.

*   **Raspberry Pi 4B (menggunakan Primary UART - `/dev/ttyS0` atau `/dev/serial0`):**
    *   **GPIO14 (TXD0):** Terhubung ke pin RXD ESP32.
    *   **GPIO15 (RXD0):** Terhubung ke pin TXD ESP32.
    *   **GND:** Terhubung ke pin GND ESP32.

*   **ESP32-WROVER-E (v1.6) (menggunakan UART0 - biasanya untuk programming & monitor):**
    *   **GPIO3 (U0RXD):** Terhubung ke pin TXD Raspberry Pi (GPIO14). **(ESP32 Menerima)**
    *   **GPIO1 (U0TXD):** Terhubung ke pin RXD Raspberry Pi (GPIO15). **(ESP32 Mengirim)**
    *   **GND:** Terhubung ke pin GND Raspberry Pi.

**Diagram Koneksi Kabel Jumper:**

Raspberry Pi 4B ESP32-WROVER-E (v1.6)

```
GPIO14 (Pin 8, TXD) ----> GPIO3 (U0RXD)
GPIO15 (Pin 10, RXD) <---- GPIO1 (U0TXD)
GND (Any GND Pin) <----> GND (Any GND Pin)
```


**Penting:**
*   Pastikan Anda telah mengaktifkan port serial di Raspberry Pi (`raspi-config` -> Interface Options -> Serial Port) dan menonaktifkan login shell serial jika Anda menggunakan `/dev/ttyS0`. `/dev/serial0` biasanya merupakan alias yang lebih aman.
*   Level logika kedua perangkat adalah 3.3V, jadi koneksi langsung aman.
*   Baud rate komunikasi serial harus sama di kedua perangkat (misalnya, 9600 atau 115200).

## 4. Cara Melakukan Simulasi Periferal

**4.1. Simulasi di Sisi ESP32 (Firmware C/C++):**

*   **Sensor Proksimitas (Deteksi Item Masuk):**
    *   **Metode 1 (Tombol Fisik):** Hubungkan sebuah push button ke salah satu pin input digital ESP32 (dengan pull-up atau pull-down resistor yang sesuai). Saat tombol ditekan, program ESP32 akan mengirim pesan "ITEM_DETECTED" ke RPi melalui serial.
    *   **Metode 2 (Perintah Serial dari Arduino IDE):** Dalam `loop()` ESP32, cek apakah ada input dari `Serial` (yang terhubung ke USB komputer). Jika Anda mengetik perintah seperti "SIM_ITEM" di Serial Monitor Arduino IDE, ESP32 akan mengirim pesan "ITEM_DETECTED" ke RPi melalui UART yang terhubung ke RPi.
        ```cpp
        // Contoh di ESP32 sketch
        void loop() {
          if (Serial.available() > 0) { // Input dari USB Serial Monitor
            String cmd = Serial.readStringUntil('\n');
            if (cmd == "SIM_ITEM") {
              Serial1.println("ITEM_DETECTED"); // Serial1 adalah UART ke RPi
              Serial.println("SIM: Mengirim ITEM_DETECTED ke RPi");
            }
          }
          // ... (logika lain, misalnya membaca perintah dari RPi via Serial1)
        }
        ```

*   **LED Indikator:**
    *   Alih-alih menyalakan LED fisik, cetak status ke Serial Monitor Arduino IDE.
        ```cpp
        // Contoh di ESP32 sketch saat menerima perintah dari RPi
        // String commandFromRPi = Serial1.readStringUntil('\n');
        // if (commandFromRPi == "LED_HIJAU_ON") {
        //   Serial.println("SIM: LED Hijau Menyala");
        //   // digitalWrite(PIN_LED_HIJAU, HIGH); // Ini untuk LED fisik
        //   Serial1.println("ACK_LED_HIJAU_ON"); // Konfirmasi ke RPi
        // }
        ```

*   **Motor DC/Servo (Mekanisme Pemilah):**
    *   Sama seperti LED, cetak aksi motor ke Serial Monitor Arduino IDE.
        ```cpp
        // Contoh di ESP32 sketch
        // if (commandFromRPi == "AKTUATOR_TERIMA_PET") {
        //   Serial.println("SIM: Motor Pemilah PET Aktif");
        //   // Logika kontrol motor fisik di sini
        //   delay(2000); // Simulasi waktu motor bergerak
        //   Serial.println("SIM: Motor Pemilah PET Selesai");
        //   Serial1.println("ACK_AKSI_SELESAI");
        // }
        ```

**4.2. Simulasi di Sisi Raspberry Pi (Skrip Python):**

*   **Kamera USB:**
    *   Untuk pengembangan awal tanpa ingin selalu memproses gambar nyata, Anda bisa membuat fungsi di Python yang:
        *   Membaca file gambar contoh (misalnya, `botol_kosong_dummy.jpg`) dari disk RPi.
        *   Mengembalikan data gambar ini seolah-olah baru saja diambil dari kamera.
        *   Anda bisa memiliki switch atau argumen skrip untuk memilih antara menggunakan kamera USB asli atau file dummy.
        ```python
        # Contoh di Python RPi
        import cv2
        USE_REAL_CAMERA = True # Ubah ke False untuk pakai gambar dummy

        def capture_image_from_camera():
            if USE_REAL_CAMERA:
                cap = cv2.VideoCapture(0) # 0 adalah ID kamera USB default
                if not cap.isOpened():
                    print("Error: Tidak bisa membuka kamera.")
                    return None
                ret, frame = cap.read()
                cap.release()
                if ret:
                    cv2.imwrite("temp_capture.jpg", frame) # Simpan sementara
                    return "temp_capture.jpg" # Kembalikan path
                return None
            else:
                print("SIM: Menggunakan gambar dummy 'dummy_bottle.jpg'")
                # Pastikan file dummy_bottle.jpg ada
                return "dummy_bottle.jpg" 
        ```

*   **LCD Display:**
    *   Jika belum ada LCD, semua output ke LCD bisa dialihkan ke `print()` di konsol RPi.
        ```python
        # Contoh di Python RPi
        def display_message_on_lcd(message):
            # if lcd_is_connected:
            #   lcd.text(message, 1)
            # else:
            print(f"LCD_SIM: {message}")
        ```

## 5. Urutan Implementasi dan Pengujian Awal (Fase 3)

1.  **Setup Dasar (RPi & ESP32):**
    *   Instal OS dan software yang dibutuhkan di RPi.
    *   Setup Arduino IDE untuk ESP32.
    *   Buat koneksi fisik Serial UART antara RPi dan ESP32.
2.  **Komunikasi Serial Dua Arah Sederhana:**
    *   RPi mengirim "PING", ESP32 merespons "PONG".
    *   Verifikasi baud rate dan koneksi.
3.  **Implementasi Alur Deteksi Item (Simulasi Sensor):**
    *   ESP32 mengirim "ITEM_DETECTED" ke RPi (via tombol atau perintah serial dari PC).
    *   RPi menerima sinyal ini.
4.  **Integrasi Kamera USB di RPi:**
    *   RPi mengambil gambar menggunakan kamera USB (atau file dummy untuk awal).
5.  **Integrasi Pemanggilan API Deposit oleh RPi:**
    *   RPi mengirim gambar dan `user_identifier` (hardcoded dulu) ke `/api/rvm/deposit`.
    *   RPi menerima dan mencetak respons JSON dari backend.
6.  **RPi Mengirim Perintah Aksi (Simulasi) ke ESP32:**
    *   Berdasarkan respons API, RPi mengirim perintah (misalnya, "LED_HIJAU_ON_SIM") ke ESP32.
7.  **ESP32 Merespons Perintah (Simulasi):**
    *   ESP32 menerima perintah dan mencetak aksi simulasi ke Serial Monitor Arduino IDE.
    *   ESP32 mengirim ACK ke RPi.
8.  **Iterasi dan Penyempurnaan:** Tambahkan logika state machine, error handling, dll.