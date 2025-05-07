# Catatan Penting:

---

-   **Keunikan Data**: Pastikan data yang Anda masukkan untuk kolom `unique` (seperti `email`, `phone_number`, `identity_number` di **users**, dan `api_key` di `reverse_vending_machines`)
-   **Foreign Key**: **DepositSeeder** mengambil `user_id` dan `rvm_id` dari data yang sudah ada. Jadi, pastikan **UserSeeder** dan **ReverseVendingMachineSeeder** dijalankan terlebih dahulu. Urutan pemanggilan di **DatabaseSeeder.php**.
-   **Password**: **Password** untuk **user dummy** di **UserSeeder** adalah **`password`** atau **`password123`**.
