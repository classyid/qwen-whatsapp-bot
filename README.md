# WA Qwen Assistant

Bot WhatsApp pintar yang mengintegrasikan WhatsApp dengan model AI Qwen melalui Ollama. Dibangun menggunakan PHP dengan fitur yang mudah dikustomisasi.

## 🌟 Fitur Utama

- 🤖 Integrasi dengan model AI Qwen melalui Ollama
- 📱 Webhook WhatsApp yang mudah dikonfigurasi
- 🚀 Sistem start/stop untuk mengontrol bot
- 🛡️ Proteksi anti-spam terintegrasi
- 💬 Mendukung reply message (quoted)
- 📝 Menyimpan riwayat chat ke database
- ⚡ Performa tinggi dan respon cepat

## 📋 Prasyarat

- PHP 7.4 atau lebih tinggi
- MySQL/MariaDB
- Ollama yang sudah terinstall dengan model Qwen
- Server dengan akses HTTPS (untuk webhook WhatsApp)
- Multi-device WhatsApp API Client (seperti: @adiwajshing/baileys, atau serupa)

## 🛠️ Instalasi

1. Clone repository ini:
```bash
git clone https://github.com/classyid/qwen-whatsapp-bot.git
cd wa-qwen-assistant
```

2. Salin file config contoh:
```bash
cp config.example.php config.php
```

3. Edit konfigurasi database di `config.php`:
```php
$db_host = 'localhost';
$db_name = 'nama_database';
$db_user = 'username_database';
$db_pass = 'password_database';
```

4. Import struktur database:
```sql
mysql -u username -p nama_database < database.sql
```

5. Sesuaikan endpoint Ollama di `index.php`:
```php
define('OLLAMA_API', 'http://<ip-server-ollama>:11434/api/generate');
```

## 🚀 Penggunaan

### Perintah Dasar
- `/start` - Mengaktifkan bot
- `/stop` - Menonaktifkan bot

### Contoh Implementasi
```php
// Inisialisasi webhook
require_once 'index.php';

// Bot akan otomatis merespons pesan setelah user melakukan /start
```

## ⚙️ Konfigurasi

### Anti-Spam
Anda dapat mengubah batasan anti-spam di `index.php`:
```php
define('MAX_REQUESTS_PER_MINUTE', 10); // Jumlah maksimal request per menit
define('CACHE_TIMEOUT', 60); // Timeout dalam detik
```

### Parameter Qwen
Sesuaikan parameter model Qwen sesuai kebutuhan:
```php
$data = [
    'model' => 'qwen',
    'options' => [
        'temperature' => 0.7,
        'top_k' => 40,
        'top_p' => 0.95
    ]
];
```

## 🔒 Keamanan

- Pastikan selalu validasi input user
- Gunakan HTTPS untuk webhook
- Batasi akses ke endpoint webhook
- Enkripsi kredensial database
- Monitor log secara berkala

## 🤝 Kontribusi

Kontribusi selalu diterima! Silakan buat pull request atau laporkan issue jika menemukan bug.

## 📝 Lisensi

Proyek ini dilisensikan di bawah MIT License - lihat file [LICENSE](LICENSE) untuk detail.
## 📞 Kontak

Jika Anda memiliki pertanyaan atau saran, silakan hubungi kami melalui:
- Email: kontak@classy.id
- Telegram: @classyid
