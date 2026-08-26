# RFID-ESP32-RBAC-TBAC

Sistem kontrol akses yang dibangun menggunakan ESP32, pembaca RFID MFRC522, LCD I2C, dan server web berbasis PHP. Proyek ini dilengkapi dengan fitur *Role-Based Access Control* (RBAC) dan *Time-Based Access Control* (TBAC), yang memungkinkan kontrol presisi mengenai siapa saja yang dapat mengakses ruangan tertentu dan pada waktu kapan (contoh: berdasarkan jadwal perkuliahan untuk dosen dan mahasiswa).

## Fitur

- **Mikrokontroler ESP32:** Unit pemrosesan utama untuk membaca kartu RFID dan berkomunikasi dengan server.
- **Pembaca RFID MFRC522:** Untuk memindai kartu ID pengguna.
- **Layar LCD I2C (16x2):** Menampilkan umpan balik (*feedback*) secara *real-time*, pesan status, dan informasi pengguna langsung pada perangkat.
- **Portal WiFiManager:** Konfigurasi awal WiFi dan URL server dengan mudah melalui *captive portal*.
- **Auto-Discovery (mDNS & Network Sweep):** ESP32 dapat menemukan server PHP di jaringan lokal secara otomatis.
- **Heartbeat & Pemeliharaan:** Pelaporan status *online*/*offline* secara *real-time* ke server dan pemulihan koneksi SPI RFID secara otomatis.
- **Server Web RBAC & TBAC:** *Backend* PHP untuk mengelola pengguna, peran (contoh: dosen, mahasiswa), jadwal, ruangan, dan perangkat.
- **Kontrol Relay:** Mengontrol *solenoid door lock* (kunci pintu otomatis) atau mekanisme serupa.

## Kebutuhan Perangkat Keras (Hardware)

- ESP32 Development Board
- Modul RFID MFRC522
- LCD 16x2 dengan Modul I2C (Alamat biasanya `0x27`)
- Modul Relay 5V (untuk memicu kunci pintu solenoid/elektrik)
- Push Button / Tombol Tekan (untuk Mode Boot/Registrasi)
- Kabel Jumper & Breadboard/PCB

## Konfigurasi Pin

| Pin ESP32 | Pin Komponen     | Keterangan                          |
|-----------|------------------|-------------------------------------|
| GPIO 21   | I2C SDA          | Untuk LCD                           |
| GPIO 22   | I2C SCL          | Untuk LCD                           |
| GPIO 27   | Relay IN         | Solenoid Door Lock                  |
| GPIO 13   | Push Button      | Tombol Boot/Registrasi Eksternal    |
| GPIO 5    | RFID SS (SDA)    | MFRC522                             |
| GPIO 18   | RFID SCK         | MFRC522                             |
| GPIO 19   | RFID MISO        | MFRC522                             |
| GPIO 23   | RFID MOSI        | MFRC522                             |
| GPIO 4    | RFID RST         | MFRC522                             |

## Setup & Instalasi

### 1. Setup Server Web
1. Jalankan isi dari direktori `server/` di server lokal PHP/MySQL (misalnya: XAMPP, Nginx/Apache + PHP).
2. Buat database MySQL dan impor tabel yang diperlukan (pengguna, peran, jadwal, perangkat, dll.).
3. Perbarui `server/database.php` atau `server/config.php` dengan kredensial database Anda.

### 2. Firmware ESP32
1. Buka `akseskontrolLCD.ino` di Arduino IDE.
2. Instal pustaka (*libraries*) berikut:
   - `ArduinoJson`
   - `LiquidCrystal_I2C`
   - `MFRC522`
   - `WiFiManager`
3. *Flash* (unggah) kode ke *board* ESP32 Anda.
4. Pada saat *booting* pertama, ESP32 akan memancarkan WiFi AP dengan nama `RFID-ACCESS` (Kata sandi: `12345678`). Hubungkan perangkat Anda ke jaringan tersebut.
5. Buka *captive portal*, masukkan informasi WiFi lokal Anda, lalu tentukan `URL Server API` dan `Device ID`.
6. ESP32 akan melakukan *restart*, terhubung ke WiFi Anda, dan secara otomatis menemukan serta berkomunikasi dengan *backend* PHP.

## Penggunaan

- **Mode Normal:** Tempelkan kartu RFID yang terdaftar pada pembaca (*reader*). ESP32 akan melakukan *ping* ke server, memeriksa izin akses berdasarkan waktu dan peran, lalu membuka relay jika akses diberikan. Layar LCD akan menampilkan pesan selamat datang beserta detail pengguna.
- **Mode Registrasi:** Tekan tombol fisik yang terhubung ke GPIO 13 untuk masuk ke mode registrasi. Tempelkan kartu baru untuk mengirimkan UID-nya ke server agar memudahkan pendaftaran melalui *dashboard* web.
- **Reset WiFi:** Tekan dan tahan tombol selama 3 detik untuk menghapus pengaturan WiFiManager dan mengulang proses instalasi jaringan.

## Lisensi

Proyek ini dilisensikan di bawah Lisensi MIT - lihat file [LICENSE](LICENSE) untuk informasi lebih lanjut.
