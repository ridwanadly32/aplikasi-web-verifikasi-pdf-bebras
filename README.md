# Aplikasi Web Verifikasi Unduhan PDF Bebras Challenge 2025

Proyek ini saya buat untuk tugas mata kuliah **Manajemen Keamanan Siber**. Aplikasi web ini menampilkan daftar sekolah dan pendamping Bebras Challenge 2025, lalu mengizinkan pendamping mengunduh file PDF setelah memasukkan kode verifikasi 4 digit.

## Fitur Utama
- Verifikasi kode dilakukan **di server (PHP)**, bukan di JavaScript.
- Kode verifikasi disimpan di folder `secure_data/` yang diproteksi.
- Ada pembatasan percobaan (rate limiting) supaya tidak mudah di‑brute‑force.
- Download PDF menggunakan token sekali pakai (lebih aman daripada link langsung).

## Struktur Singkat
```text
index.php              # Halaman utama aplikasi
style.css              # Tampilan dasar
data_sekolah_public.json  # Data sekolah dan pendamping (tanpa kode verifikasi)
secure_data/
  ├── .htaccess                 # Proteksi folder
  └── verification_codes.json   # Kode verifikasi (dibuat di server, tidak perlu di‑commit)
Bebras.ipynb           # Notebook untuk generate PDF dan data dari Excel
```

## Cara Menjalankan (Versi Singkat)
1. Taruh semua file di folder web server (XAMPP, Laragon, hosting, dll.).  
2. Buat folder `secure_data/` dan `pdf_files/` jika belum ada.
3. Di server, buat file `secure_data/verification_codes.json` berisi pasangan `pdf_file` dan `verification_codes` sesuai kebutuhan.
4. Taruh semua file PDF di folder `pdf_files/` dengan nama yang sama seperti di JSON.
5. Akses `index.php` lewat browser → pilih pendamping → masukkan kode verifikasi → download PDF.
