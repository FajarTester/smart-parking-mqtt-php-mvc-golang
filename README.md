# Smart Parking System

Sistem manajemen parkir berbasis IoT yang mengintegrasikan perangkat keras ESP32, protokol komunikasi MQTT, backend berperforma tinggi menggunakan Golang, serta antarmuka manajemen berbasis web dengan PHP MVC. Sistem ini dirancang untuk mengotomasi proses check-in dan check-out kendaraan secara real-time melalui pembacaan kartu RFID, sekaligus menyediakan pengelolaan data terpusat menggunakan Supabase (PostgreSQL) dan MySQL.

---

## Daftar Isi

1. [Arsitektur Sistem](#arsitektur-sistem)
2. [Alur Kerja](#alur-kerja)
3. [Topik MQTT & Payload](#topik-mqtt--payload)
4. [Struktur Direktori](#struktur-direktori)
5. [Persyaratan Sistem](#persyaratan-sistem)
6. [Konfigurasi](#konfigurasi)
7. [Skema Database](#skema-database)
8. [Instalasi & Menjalankan Sistem](#instalasi--menjalankan-sistem)
9. [Simulasi & Pengujian MQTT](#simulasi--pengujian-mqtt)
10. [Logika Bisnis & Perhitungan Tarif](#logika-bisnis--perhitungan-tarif)
11. [Fitur Frontend PHP](#fitur-frontend-php)
12. [Keamanan](#keamanan)
13. [Roadmap Pengembangan](#roadmap-pengembangan)
14. [FAQ](#faq)

---

## Arsitektur Sistem

Sistem ini menggunakan arsitektur berlapis dengan pemisahan tanggung jawab yang jelas antara lapisan hardware, komunikasi, pemrosesan, dan penyimpanan data.

| Komponen            | Teknologi               | Fungsi                                                       |
| ------------------- | ----------------------- | ------------------------------------------------------------ |
| Hardware Layer      | ESP32 + RFID RC522      | Pembacaan kartu RFID pada gate masuk dan keluar              |
| Communication Layer | MQTT (Mosquitto Broker) | Pengiriman data real-time antar perangkat dan backend        |
| Processing Layer    | Golang (Go Worker)      | Pemrosesan logika bisnis check-in/check-out dan kontrol gate |
| Cloud Database      | Supabase (PostgreSQL)   | Penyimpanan data transaksi parkir secara persisten           |
| Web Frontend        | PHP MVC                 | Dashboard manajemen, login, registrasi, dan parkir manual    |
| Local Database      | MySQL                   | Penyimpanan data pengguna dan transaksi lokal frontend       |

---

## Alur Kerja

```
ESP32 (RFID Reader)
    │  Publish UID ke topic MQTT
    ▼
MQTT Broker (Mosquitto)
    │  Meneruskan pesan ke subscriber
    ▼
Go Worker (Backend)
    │  Validasi & proses logika bisnis check-in/check-out
    │  REST API ke Supabase (GET / POST / PATCH)
    │  Publish perintah servo gate OPEN/CLOSE
    └─ Publish pesan tampilan ke LCD
    ▼
Supabase (Cloud DB)
    │  Data transaksi tersimpan secara persisten
    ▼
PHP Frontend
    └─ Dashboard monitoring, parkir manual, sinkronisasi MySQL ↔ Supabase
```

---

## Topik MQTT & Payload

### Daftar Topik

| Topik                       | Arah       | Keterangan                                |
| --------------------------- | ---------- | ----------------------------------------- |
| `parking/fajar/entry/rfid`  | ESP32 → Go | Data UID kartu RFID saat kendaraan masuk  |
| `parking/fajar/exit/rfid`   | ESP32 → Go | Data UID kartu RFID saat kendaraan keluar |
| `parking/fajar/entry/servo` | Go → ESP32 | Perintah buka/tutup gate masuk            |
| `parking/fajar/exit/servo`  | Go → ESP32 | Perintah buka/tutup gate keluar           |
| `parking/fajar/lcd`         | Go → ESP32 | Pesan yang ditampilkan di layar LCD       |

### Format Payload

**Check-In / Check-Out (ESP32 → Go):**

```json
{ "uid": "1234ABCD" }
```

**Perintah Servo (Go → ESP32):**

```
OPEN
CLOSE
```

**Pesan LCD (Go → ESP32):**

```json
{ "line1": "Selamat Datang", "line2": "UID: 1234ABCD", "line3": "Fee: Rp3000" }
```

---

## Struktur Direktori

```
smart-parkir/
├── backend/
│   ├── .env
│   ├── cmd/
│   │   └── main.go              # Entry point aplikasi Go
│   ├── config/
│   │   └── config.go            # Load konfigurasi dari .env
│   ├── mqtt/
│   │   └── mqtt_client.go       # Koneksi broker & subscribe topik
│   ├── handler/
│   │   └── rfid_handler.go      # Routing logika berdasarkan topik MQTT
│   └── service/
│       ├── checkIn.go           # Logika check-in ke Supabase
│       ├── checkOut.go          # Logika check-out + kalkulasi biaya
│       └── parking_service.go   # Fungsi utilitas layanan parkir
└── frontend/
    ├── index.php
    ├── .env
    ├── controllers/
    │   ├── AuthController.php    # Login & registrasi pengguna
    │   └── ParkingController.php # Check-in/out manual & sinkronisasi
    ├── models/
    │   ├── Database.php          # Koneksi MySQL
    │   └── ParkingModel.php      # Query data parkir
    └── views/
        ├── login.php
        ├── register.php
        └── dashboard.php
```

---

## Persyaratan Sistem

| Komponen              | Versi Minimum      | Keterangan                       |
| --------------------- | ------------------ | -------------------------------- |
| Go (Golang)           | 1.20+              | Runtime backend worker           |
| PHP                   | 7.4+               | Ekstensi `mysqli` wajib aktif    |
| MySQL                 | 5.7+ / 8.0+        | Database lokal frontend          |
| Supabase              | Cloud (Free Tier+) | Database cloud PostgreSQL        |
| Mosquitto MQTT Broker | 2.x                | Dapat digunakan lokal atau cloud |
| Composer              | 2.x                | Manajer dependensi PHP           |
| ESP32 + RFID RC522    | —                  | Pembaca kartu RFID pada gate     |

---

## Konfigurasi

### Backend Go — `.env`

```ini
# Supabase Configuration
SUPABASE_URL=https://your-project-id.supabase.co
SUPABASE_KEY=your-anon-or-service-role-key

# MQTT Broker Configuration
MQTT_BROKER=tcp://localhost:1883
MQTT_TOPIC=parking/fajar/#

# Tarif Parkir (satuan: Rupiah per jam)
RATE_PER_HOUR=3000
```

### Frontend PHP — `.env`

```ini
# MySQL Local Database
DB_HOST=localhost
DB_NAME=smart_parkir
DB_USER=root
DB_PASS=your_password

# Supabase (untuk sinkronisasi data)
SUPABASE_URL=https://your-project-id.supabase.co
SUPABASE_KEY=your-anon-key

# Tarif Parkir Manual
RATE_PER_HOUR=2000
RATE_EXTRA_MINUTES=500
```

---

## Skema Database

### Supabase (PostgreSQL) — Tabel Transaksi

```sql
CREATE TABLE parkir_tb_transaksi (
  id             BIGINT GENERATED BY DEFAULT AS IDENTITY PRIMARY KEY,
  card_id        BIGINT          NOT NULL,
  checkin_time   TIMESTAMPTZ,
  checkout_time  TIMESTAMPTZ,
  status         TEXT,           -- 'IN' atau 'OUT'
  duration       INT,            -- dalam menit
  fee            INT,            -- dalam Rupiah
  jenis          TEXT            -- jenis kendaraan: 'motor' / 'mobil'
);
```

### MySQL — Tabel Pengguna

```sql
CREATE TABLE users (
  id        INT AUTO_INCREMENT PRIMARY KEY,
  username  VARCHAR(100) UNIQUE  NOT NULL,
  password  VARCHAR(255)         NOT NULL,
  role      ENUM('owner','petugas') DEFAULT 'petugas'
);
```

### MySQL — Tabel Parkir (Lokal Frontend)

```sql
CREATE TABLE parkir (
  id             INT AUTO_INCREMENT PRIMARY KEY,
  card_id        VARCHAR(50)  NOT NULL,
  checkin_time   DATETIME,
  checkout_time  DATETIME,
  status         ENUM('IN','OUT'),
  duration       INT,
  fee            INT,
  jenis          VARCHAR(30),
  input_method   ENUM('hardware','manual') DEFAULT 'manual'
);
```

---

## Instalasi & Menjalankan Sistem

### 1. Backend Go Worker

```bash
# Masuk ke direktori backend
cd backend

# Instal dependensi
go get github.com/joho/godotenv
go get github.com/eclipse/paho.mqtt.golang
go mod tidy

# Jalankan worker
go run ./cmd
```

> Output yang diharapkan: `Starting MQTT WORKER` diikuti `MQTT Connected`.

### 2. Frontend PHP

```bash
# Instal dependensi PHP
cd frontend
composer require vlucas/phpdotenv
```

Pastikan Apache dan MySQL berjalan melalui XAMPP, kemudian letakkan folder `frontend/` di dalam direktori `htdocs`. Akses aplikasi melalui:

```
http://localhost/frontend/
```

### 3. MQTT Broker (Mosquitto)

```bash
# Jalankan broker
mosquitto -v

# Monitor semua topik (opsional, untuk debugging)
mosquitto_sub -t "parking/fajar/#" -v
```

---

## Simulasi & Pengujian MQTT

Gunakan perintah berikut untuk mensimulasikan pembacaan RFID tanpa perangkat keras fisik.

**Simulasi Check-In:**

```bash
mosquitto_pub -t parking/fajar/entry/rfid -m '{"uid":"1234ABCD"}'
```

**Simulasi Check-Out:**

```bash
mosquitto_pub -t parking/fajar/exit/rfid -m '{"uid":"1234ABCD"}'
```

**Verifikasi Respons:**

| Topik Respons               | Hasil yang Diharapkan                                |
| --------------------------- | ---------------------------------------------------- |
| `parking/fajar/entry/servo` | Menerima pesan `OPEN` kemudian `CLOSE` setelah delay |
| `parking/fajar/lcd`         | Menerima data JSON berisi informasi kendaraan        |
| Supabase Dashboard          | Record baru muncul dengan `status = IN`              |
| PHP Dashboard               | Data transaksi terupdate di halaman dashboard        |

---

## Logika Bisnis & Perhitungan Tarif

### Alur Check-In (Go Worker)

1. Worker menerima UID kartu dari topik MQTT entry.
2. Sistem melakukan `GET` ke Supabase untuk memeriksa apakah kartu sudah memiliki record dengan `status = IN`.
3. Jika sudah ada record aktif → tolak, kirim notifikasi error ke LCD.
4. Jika belum ada → buat record baru dengan `checkin_time = NOW()` dan `status = IN`.
5. Publish perintah `OPEN` ke topik servo gate masuk.

### Alur Check-Out (Go Worker)

1. Worker menerima UID kartu dari topik MQTT exit.
2. Cari record dengan `card_id` yang sesuai dan `status = IN`.
3. Hitung durasi: `checkout_time - checkin_time` (detik → menit → jam).
4. Hitung biaya menggunakan metode ceiling (pembulatan ke atas per jam).
5. Update record di Supabase: isi `checkout_time`, `duration`, `fee`, ubah `status = OUT`.
6. Publish respons ke LCD dan perintah `OPEN` ke gate keluar.

### Tabel Tarif

| Layer        | Tarif Dasar | Ketentuan                                               |
| ------------ | ----------- | ------------------------------------------------------- |
| Go Worker    | Rp3.000/jam | Pembulatan ke atas per jam (ceiling)                    |
| PHP Frontend | Rp2.000/jam | Tambahan Rp500 jika melebihi 10 menit dari jam terakhir |

---

## Fitur Frontend PHP

### Autentikasi Pengguna

- **Login** — Validasi kredensial dengan hashing bcrypt.
- **Register** — Pendaftaran akun baru dengan pemilihan peran (`owner` / `petugas`).
- Manajemen sesi berbasis PHP Session.

### Parkir Manual

- Petugas dapat melakukan check-in dan check-out kendaraan melalui antarmuka web tanpa hardware.
- Input meliputi Card ID, jenis kendaraan, dan metode input.
- Data tersimpan ke MySQL lokal dan disinkronkan ke Supabase melalui REST API.

### Dashboard Monitoring

- Daftar kendaraan yang sedang parkir (`status = IN`).
- Riwayat transaksi dengan filter tanggal.
- Ringkasan pendapatan harian dan total transaksi.

### Sinkronisasi Data

- PHP Frontend mengambil dan menampilkan data dari Supabase menggunakan REST API.
- Data transaksi manual disimpan ke MySQL dan dikirim ke Supabase secara bersamaan.

---

## Keamanan

> **Peringatan:** Jangan menyimpan `SUPABASE_KEY` (terutama service role key) di dalam repository publik. Pastikan file `.env` tercantum di dalam `.gitignore`.

| Aspek           | Rekomendasi                                                                |
| --------------- | -------------------------------------------------------------------------- |
| Kredensial      | Gunakan environment variable, jangan hardcode di source code               |
| MQTT            | Aktifkan autentikasi username/password dan TLS pada environment production |
| API Supabase    | Terapkan Row Level Security (RLS) untuk membatasi akses data               |
| SQL Injection   | Gunakan prepared statement di semua query MySQL pada PHP                   |
| Autentikasi Web | Implementasikan proteksi CSRF dan validasi input di semua form             |
| Password        | Hash menggunakan `password_hash()` (bcrypt) sebelum menyimpan ke database  |

---

## Roadmap Pengembangan

- [ ] Integrasi modul RTC (DS3231) untuk sinkronisasi waktu di ESP32
- [ ] Dashboard grafis dengan visualisasi data transaksi dan total pendapatan
- [ ] Autentikasi JWT untuk keamanan endpoint API PHP
- [ ] Queue dan mekanisme retry otomatis untuk permintaan ke Supabase di Go Worker
- [ ] Notifikasi real-time menggunakan WebSocket atau Server-Sent Events (SSE)
- [ ] Docker Compose untuk containerisasi seluruh stack (Go + PHP + MySQL + Mosquitto)
- [ ] Fitur ekspor laporan transaksi ke format Excel/PDF
- [ ] Dukungan multi-gate (beberapa pintu masuk dan keluar dalam satu sistem)

---

## FAQ

**Mengapa Go Worker menggunakan Supabase sebagai database utama?**
Supabase menyediakan endpoint REST API bawaan yang memungkinkan Go Worker berinteraksi dengan database PostgreSQL menggunakan operasi HTTP standar (GET, POST, PATCH) tanpa perlu library koneksi database tambahan.

**Bagaimana data antara MySQL dan Supabase disinkronkan?**
Sinkronisasi dilakukan melalui PHP Frontend menggunakan Supabase REST API. Data yang diinput secara manual ditulis ke MySQL lokal sekaligus dikirim ke Supabase. Data dari hardware yang diproses Go Worker tersimpan di Supabase dan dapat diambil oleh PHP untuk ditampilkan di dashboard.

**Apa perbedaan parkir manual dan parkir hardware?**
Parkir hardware menggunakan kartu RFID yang dibaca ESP32 dan diproses otomatis oleh Go Worker. Parkir manual memungkinkan petugas mengisi data kendaraan secara langsung melalui antarmuka web, berguna ketika hardware mengalami gangguan atau untuk keperluan administrasi.

**Dapatkah sistem berjalan offline?**
Go Worker membutuhkan koneksi internet untuk mengakses Supabase. PHP Frontend dapat berjalan secara lokal menggunakan MySQL tanpa internet, namun fitur sinkronisasi ke Supabase membutuhkan koneksi aktif. Untuk lingkungan offline penuh, disarankan mengganti Supabase dengan PostgreSQL lokal.

---

## Referensi

- [Dokumentasi Supabase](https://supabase.com/docs)
- [Paho MQTT Go Client](https://github.com/eclipse/paho.mqtt.golang)
- [Mosquitto MQTT Broker](https://mosquitto.org/documentation/)
- [Dokumentasi Golang](https://go.dev/doc/)
- [PHP vlucas/phpdotenv](https://github.com/vlucas/phpdotenv)
- [ESP32 Arduino Framework](https://docs.espressif.com/projects/arduino-esp32/)
