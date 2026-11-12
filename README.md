# Barcode Hunting

Aplikasi permainan **Barcode Hunting** berbasis PHP dan MySQL untuk penyelenggaraan event multi-cabang. Peserta mendaftar melalui halaman publik, memindai barcode menggunakan kamera perangkat, mengumpulkan seluruh barcode yang ditugaskan, dan menyelesaikan permainan dalam batas waktu yang ditentukan. Admin mengelola data cabang, barcode, konfigurasi permainan, serta memantau hasil peserta.

## Fitur

### Halaman peserta
- Registrasi menggunakan nama dan nomor HP.
- Permainan diakses berdasarkan kode cabang melalui parameter URL `?cabang=`.
- Pemindaian barcode menggunakan kamera melalui library [html5-qrcode](https://github.com/mebjas/html5-qrcode).
- Daftar barcode peserta ditetapkan per sesi dan memiliki urutan pemindaian.
- Timer permainan, progres barcode, hint/petunjuk, serta status sesi.
- Dukungan jeda dan melanjutkan sesi sesuai status permainan.
- Validasi sesi peserta dan token CSRF pada alur permainan.

### Panel admin
- Dashboard juara pada setiap cabang.
- Manajemen data cabang.
- Manajemen master barcode dan hint.
- Pengaturan game per cabang, jumlah barcode, durasi, serta status aktif.
- Daftar peserta yang mendaftar melalui halaman publik.
- Leaderboard berdasarkan hasil scan dan waktu bermain.
- Rekap hasil permainan per cabang dan ekspor CSV.

## Teknologi

| Bagian | Teknologi |
| --- | --- |
| Backend | PHP 8.1+ |
| Database | MySQL 8.0+ / MariaDB yang kompatibel |
| Koneksi database | PDO |
| Frontend admin | Bootstrap 5.3, Bootstrap Icons |
| Pemindai kamera | html5-qrcode 2.3.8 |
| JavaScript | JavaScript native |
| Zona waktu aplikasi | Asia/Jakarta |

> Halaman pemindai membutuhkan akses kamera. Saat dijalankan di luar `localhost`, browser umumnya mensyaratkan HTTPS dan izin kamera dari pengguna.

## Struktur proyek

```text
barcode-hunter/
├── admin/
│   ├── assets/                 # CSS dan JavaScript panel admin
│   ├── includes/               # Bootstrap admin, layout, dan helper data game
│   ├── sql/                    # SQL instalasi dan migrasi database
│   ├── index.php               # Dashboard juara per cabang
│   ├── barcode.php             # Master barcode
│   ├── cabang.php              # Master cabang
│   ├── game.php                # Konfigurasi permainan
│   ├── users.php               # Data peserta
│   ├── leaderboard.php         # Peringkat peserta
│   └── reports.php             # Rekap dan ekspor CSV
├── api/
│   ├── _bootstrap.php          # Bootstrap API admin dan validasi CSRF
│   ├── barcode/                # Endpoint CRUD dan alokasi barcode
│   └── cabang/                 # Endpoint data cabang
├── assets/
│   ├── game.css                # Tampilan permainan
│   ├── game.js                 # Interaksi permainan dan pemindaian
│   └── register.js              # Registrasi peserta
├── config/
│   ├── config.example.php      # Contoh konfigurasi koneksi database
│   └── config.php              # Konfigurasi lokal (jangan di-commit)
├── admin/
│   └── sql/
│       ├── db_barcode_hunter.sql
│       ├── upgrade_v2.sql
│       └── upgrade_game_tables.sql
├── game-bootstrap.php          # Bootstrap halaman peserta
├── index.php                   # Registrasi/pintu masuk permainan
├── game.php                    # Halaman pemindaian barcode
└── session.php                 # Endpoint sesi permainan
```

## Persyaratan

- PHP 8.1 atau lebih baru.
- Ekstensi PHP `pdo_mysql`, `json`, dan `mbstring` (disarankan).
- MySQL 8.0 atau MariaDB yang mendukung fitur SQL pada skrip migrasi.
- Apache/Nginx atau development server PHP.
- Browser modern dengan dukungan kamera untuk perangkat peserta.
- HTTPS untuk akses kamera di lingkungan hosting (localhost dikecualikan oleh browser).

## Instalasi lokal

1. Clone repository:

   ```bash
   git clone https://github.com/BetaBee2312/barcode-hunter.git
   cd barcode-hunter
   ```

2. Buat database MySQL. Nama database yang digunakan pada contoh konfigurasi adalah `db_barcode_hunting_multicabang`.

   ```sql
   CREATE DATABASE db_barcode_hunting_multicabang
     CHARACTER SET utf8mb4
     COLLATE utf8mb4_unicode_ci;
   ```

3. Import skema awal melalui phpMyAdmin atau MySQL CLI:

   ```bash
   mysql -u root -p db_barcode_hunting_multicabang < admin/sql/db_barcode_hunter.sql
   ```

   Jika database yang digunakan sudah berisi tabel versi lama, tinjau dan jalankan migrasi yang sesuai dari folder `admin/sql/`. Untuk instalasi yang dimulai dari skema lama, `upgrade_v2.sql` menambahkan tabel peserta, pengaturan game, sesi, dan relasi barcode sesi. Jangan menjalankan semua file SQL secara membabi buta pada database yang sama; periksa struktur saat ini terlebih dahulu dan backup database sebelum migrasi.

4. Buat konfigurasi database:

   - Salin `config/config.example.php` menjadi `config/config.php`.
   - Sesuaikan `$dbHost`, `$dbPort`, `$dbName`, `$dbUser`, dan `$dbPass`.
   - File `config/config.php` memuat koneksi PDO dalam variabel `$pdo`.

   Contoh:

   ```php
   <?php
   declare(strict_types=1);

   date_default_timezone_set('Asia/Jakarta');

   $dbHost = '127.0.0.1';
   $dbPort = '3306';
   $dbName = 'db_barcode_hunting_multicabang';
   $dbUser = 'root';
   $dbPass = '';

   $dsn = "mysql:host={$dbHost};port={$dbPort};dbname={$dbName};charset=utf8mb4";

   $pdo = new PDO($dsn, $dbUser, $dbPass, [
       PDO::ATTR_ERRMODE => PDO::ERRMODE_EXCEPTION,
       PDO::ATTR_DEFAULT_FETCH_MODE => PDO::FETCH_ASSOC,
       PDO::ATTR_EMULATE_PREPARES => false,
   ]);
   ```

5. Jalankan aplikasi menggunakan Apache/Nginx (misalnya Laragon/XAMPP), dengan document root mengarah ke folder proyek. Alternatif untuk pengembangan lokal:

   ```bash
   php -S 127.0.0.1:8000
   ```

6. Buka halaman admin melalui `http://127.0.0.1:8000/admin/` dan halaman peserta melalui `http://127.0.0.1:8000/?cabang=KODE_CABANG`.

## Konfigurasi dan alur penggunaan

### Login administrator

1. Import file `admin/sql/admin_auth.sql` ke database aplikasi untuk membuat tabel `tb_admin`.
2. Dari terminal pada folder root proyek, jalankan `php admin/create_admin.php` untuk membuat akun pertama. Akun pertama otomatis mendapat role `superadmin`; akun berikutnya mendapat role `admin`.
3. Buka `/admin/login.php` dan masuk menggunakan username serta password yang dibuat melalui terminal.

Pembuatan akun sengaja hanya tersedia melalui CLI, bukan pendaftaran publik. Password disimpan menggunakan `password_hash()`. Session admin memakai cookie terpisah dari session konsumen, berakhir setelah 30 menit tidak aktif, dan memiliki batas maksimum 8 jam. Gunakan HTTPS pada hosting agar cookie session admin memakai atribut Secure.

### Persiapan admin

1. Pastikan database sudah diimpor dan file `config/config.php` tersedia.
2. Tambahkan data cabang pada menu **Cabang**.
3. Tambahkan barcode pada menu **Data Barcode**. Setiap barcode memiliki serial, kode cabang, nomor urut, dan hint.
4. Buat konfigurasi permainan pada menu **Game** untuk cabang terkait. Atur nama game, jumlah barcode target, durasi, dan status aktif.
5. Bagikan URL peserta dengan kode cabang, misalnya:

   ```text
   https://domain-anda.example/?cabang=23212
   ```

### Alur peserta

1. Peserta membuka tautan cabang.
2. Peserta mengisi nama dan nomor HP.
3. Aplikasi membuat atau melanjutkan sesi peserta sesuai data yang tersimpan.
4. Peserta membuka kamera dan memindai barcode yang terdaftar pada sesi.
5. Progres dan sisa waktu ditampilkan pada halaman permainan.
6. Setelah sesi berjalan, admin dapat melihat hasil pada dashboard, leaderboard, dan laporan.

## Database

Skema utama yang digunakan aplikasi meliputi:

| Tabel | Kegunaan |
| --- | --- |
| `tb_cabang` | Master cabang |
| `tb_barcode` | Master barcode, serial, nomor, dan hint per cabang |
| `tb_game` | Konfigurasi/identitas game per cabang |
| `tb_game_setting` | Nama game, durasi, dan status aktif |
| `tb_user` | Data peserta yang mendaftar |
| `tb_game_session` | Sesi permainan peserta dan status timer |
| `tb_session_barcode` | Daftar barcode yang ditetapkan untuk sesi peserta |
| `tb_game_barcode` | Relasi barcode pada model game yang digunakan pada migrasi/versi sebelumnya |

ID game, sesi, peserta, dan barcode disimpan sebagai identifier teks. Kolom teks menggunakan `utf8mb4_unicode_ci` pada skema SQL yang disediakan.

**Catatan migrasi:** `db_barcode_hunter.sql` merupakan dump skema lengkap yang tersedia di repository. `upgrade_v2.sql` dan `upgrade_game_tables.sql` adalah skrip upgrade untuk kondisi skema tertentu, bukan file instalasi yang harus selalu dijalankan berurutan. Backup database sebelum menjalankan perubahan struktur.

## Keamanan dan deployment

- Jangan commit `config/config.php`, kredensial database, atau data peserta produksi.
- Gunakan `config/config.example.php` sebagai template konfigurasi.
- Gunakan HTTPS di hosting agar akses kamera dapat berjalan.
- Pastikan direktori `config/` tidak dapat diakses publik melalui web server.
- Gunakan akun database dengan hak akses minimum yang dibutuhkan aplikasi.
- Backup database secara berkala, terutama sebelum menjalankan migrasi.
- Endpoint API admin memvalidasi sesi admin dan token CSRF. Endpoint permainan menggunakan sesi peserta dan token CSRF.
- Batasi akses panel admin di lingkungan produksi sesuai kebijakan operasional.

## Troubleshooting

**Koneksi database gagal**
- Periksa host, port, nama database, username, dan password pada `config/config.php`.
- Pastikan ekstensi `pdo_mysql` aktif dan server MySQL berjalan.

**Tabel atau kolom belum ditemukan**
- Periksa database aktif yang dipilih pada konfigurasi.
- Bandingkan struktur database dengan `admin/sql/db_barcode_hunter.sql` dan migrasi yang relevan.
- Jangan mengimpor dump lengkap ke database produksi yang sudah berisi data tanpa backup.

**Kamera tidak dapat dibuka**
- Gunakan HTTPS atau `localhost`.
- Berikan izin kamera untuk situs tersebut.
- Pastikan kamera tidak sedang digunakan aplikasi lain dan browser mendukung akses kamera.

**Game tidak tersedia untuk cabang**
- Periksa kode cabang pada URL.
- Pastikan konfigurasi game cabang tersebut tersedia dan statusnya aktif.
- Pastikan jumlah barcode yang diminta tersedia untuk cabang tersebut.

## Pengembangan

Perubahan backend dapat dilakukan pada file PHP di root, `api/`, dan `admin/`. Tampilan serta perilaku halaman peserta berada pada `assets/`, sedangkan aset panel admin berada pada `admin/assets/`. Perubahan skema database perlu disertai skrip migrasi yang menjelaskan kondisi awal dan dampaknya.

## Lisensi

Belum ada file lisensi di repository ini. Seluruh hak penggunaan dan distribusi mengikuti ketentuan pemilik repository sampai lisensi ditambahkan.
