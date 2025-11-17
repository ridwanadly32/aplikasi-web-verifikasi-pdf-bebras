# Aplikasi Web Verifikasi Unduhan PDF Bebras Challenge 2025 - Versi Diperbaiki

Aplikasi web berbasis PHP dengan keamanan yang ditingkatkan untuk mengunduh file PDF daftar peserta Bebras Challenge 2025 secara aman berdasarkan verifikasi kode 4 digit dari nomor kontak pendamping.

## Perbaikan Keamanan

Versi ini telah diperbaiki untuk mengatasi berbagai kerentanan keamanan yang ditemukan pada versi sebelumnya:

### 1. Server-Side Verification
- Verifikasi kode dilakukan di server, bukan di client-side
- Kode verifikasi tidak lagi terlihat di source code HTML/JavaScript

### 2. Pemisahan Data Sensitif
- Kode verifikasi dipindahkan ke file terpisah di folder `secure_data/`
- Folder `secure_data/` dilindungi dengan `.htaccess` untuk mencegah akses langsung

### 3. Rate Limiting
- Implementasi rate limiting untuk mencegah brute force attack
- Maksimal 5 percobaan sebelum akun terkunci selama 5 menit

### 4. Input Validation
- Validasi ketat pada input kode verifikasi (hanya 4 digit angka)
- Sanitasi nama file untuk mencegah path traversal attack
- Validasi path file sebelum download

### 5. Token-Based Download
- Sistem token untuk download yang valid hanya 5 menit
- Token dihapus setelah digunakan (one-time use)

### 6. Session Management
- Penggunaan session untuk tracking attempts dan token
- Session-based rate limiting per IP address

## Struktur Proyek

```
.
├── index_fixed.php          # File utama (versi diperbaiki)
├── style.css                # Stylesheet
├── data_sekolah.json        # Data sekolah (tanpa kode verifikasi)
├── data_sekolah_public.json # Data sekolah untuk tampilan (tanpa kode)
├── secure_data/             # Folder untuk data sensitif
│   ├── verification_codes.json  # Kode verifikasi (dilindungi)
│   └── .htaccess            # Proteksi akses folder
├── pdf_files/               # Folder untuk file PDF
└── README_FIXED.md          # Dokumentasi ini
```

## Instalasi

1. **Upload File:**
   - Upload semua file ke server web Anda
   - Pastikan folder `secure_data/` dan `pdf_files/` dibuat

2. **Set Permissions:**
   ```bash
   chmod 755 secure_data/
   chmod 644 secure_data/verification_codes.json
   chmod 644 secure_data/.htaccess
   ```

3. **Konfigurasi:**
   - Pastikan PHP session support aktif
   - Pastikan mod_rewrite aktif (untuk .htaccess)
   - Pastikan folder `secure_data/` tidak dapat diakses langsung dari web

4. **Upload PDF:**
   - Letakkan semua file PDF ke dalam folder `pdf_files/`
   - Pastikan nama file sesuai dengan yang ada di `verification_codes.json`

5. **Setup Data:**
   - File `verification_codes.json` harus berisi mapping antara PDF file dan kode verifikasi
   - File ini harus berada di folder `secure_data/` yang dilindungi

## Konfigurasi Keamanan

### Rate Limiting
Anda dapat mengubah konfigurasi rate limiting di `index_fixed.php`:

```php
define('MAX_ATTEMPTS', 5);        // Maksimal percobaan
define('LOCKOUT_TIME', 300);      // Waktu lockout (detik)
```

### Token Expiry
Token download berlaku selama 5 menit (300 detik). Dapat diubah di bagian:
```php
'expires' => time() + 300
```

## Cara Kerja

1. Pengguna mengunjungi halaman web
2. Mereka melihat daftar sekolah dan pendamping
3. Pengguna klik tombol "Download PDF"
4. Modal verifikasi muncul
5. Pengguna memasukkan kode 4 digit
6. **Server memverifikasi kode** (bukan client-side)
7. Jika benar, server mengirim token download
8. Browser menggunakan token untuk download file
9. Token dihapus setelah digunakan

## Keamanan Tambahan

### Rekomendasi untuk Produksi

1. **Gunakan HTTPS:**
   - Enkripsi komunikasi antara client dan server
   - Mencegah man-in-the-middle attack

2. **Database untuk Kode Verifikasi:**
   - Untuk aplikasi besar, pertimbangkan menggunakan database
   - Hash kode verifikasi dengan bcrypt atau Argon2

3. **Logging:**
   - Log semua percobaan verifikasi (berhasil/gagal)
   - Monitor untuk aktivitas mencurigakan

4. **CAPTCHA:**
   - Tambahkan CAPTCHA setelah beberapa percobaan gagal
   - Mencegah automated brute force

5. **IP Whitelisting (Opsional):**
   - Jika hanya untuk internal, pertimbangkan IP whitelisting

## Perbandingan dengan Versi Lama

| Aspek | Versi Lama | Versi Diperbaiki |
|-------|------------|------------------|
| Verifikasi | Client-side (JavaScript) | Server-side (PHP) |
| Kode Verifikasi | Terlihat di HTML/JSON | Tersimpan di folder terproteksi |
| Rate Limiting | Tidak ada | Ada (5 percobaan) |
| Path Traversal | Rentan | Dilindungi dengan validasi |
| Token System | Tidak ada | Ada (one-time use) |
| Input Validation | Minimal | Ketat |

## Troubleshooting

### Error: "Token tidak valid"
- Token mungkin sudah kedaluwarsa (5 menit)
- Token sudah digunakan sebelumnya
- Session mungkin expired

### Error: "Terlalu banyak percobaan"
- Tunggu 5 menit atau clear session
- Atau gunakan IP address berbeda

### File tidak ditemukan
- Pastikan file PDF ada di folder `pdf_files/`
- Pastikan nama file sesuai dengan `verification_codes.json`
- Periksa permission folder

## Lisensi

Copyright (c) 2025 [Niskarto / Bebras Biro USU]

Permission is hereby granted, free of charge, to any person obtaining a copy
of this software and associated documentation files (the "Software"), to deal
in the Software without restriction, including without limitation the rights
to use, copy, modify, merge, publish, distribute, sublicense, and/or sell
copies of the Software, and to permit persons to whom the Software is
furnished to do so, subject to the following conditions:

The above copyright notice and this permission notice shall be included in all
copies or substantial portions of the Software.

THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS OR
IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY,
FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE
AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER
LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM,
OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE
SOFTWARE.

