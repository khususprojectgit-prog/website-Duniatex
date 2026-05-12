# Database XAMPP / MariaDB — stabilitas & pemulihan

Dokumen ini menjaga lingkungan lokal agar **jarang korup** dan tahu **apa yang aman** saat ada error (`1813`, `1932`, `2002`, tabel `mysql.*` crash).

## Pencegahan (paling berpengaruh)

1. **Matikan MySQL lewat XAMPP** (tombol **Stop**), jangan mematikan PC saat MySQL masih menulis (sleep/hibernate saat sedang migrasi besar juga berisiko).
2. **Jangan pakai `php artisan migrate:fresh`** jika pernah dapat error **1813** (tablespace orphan). Pakai:
   - `composer run db-reset-local`  
   atau  
   - `php artisan duniatex:fresh-local --no-interaction`
3. **Cadangkan sebelum reset DB** (data `duniatex` akan hilang):
   - `php artisan duniatex:fresh-local --backup --no-interaction`  
   (jika `mysqldump` dari XAMPP terdeteksi, file `.sql` disimpan di `storage/app/db-backups/`.)
4. **Satu instance MySQL** di port `3306` — hindari dua installer (XAMPP + MySQL terpisah) bersaingan port.
5. **Antivirus / sync cloud**: jangan arahkan folder `C:\xampp\mysql\data` ke OneDrive/Dropbox (I/O berat memicu crash Aria/InnoDB).

## Jika MySQL XAMPP “shutdown unexpectedly”

1. Baca `C:\xampp\mysql\data\mysql_error.log` (baris **ERROR** terakhir).
2. Jika menyebut tabel `mysql\db` atau `mysql\roles_mapping` **crashed** (Aria): dari folder `C:\xampp\mysql\bin` jalankan perbaikan (contoh):
   - `aria_chk.exe -r C:\xampp\mysql\data\mysql\db.MAI`
   - `aria_chk.exe -r C:\xampp\mysql\data\mysql\roles_mapping.MAI`  
   Lalu start MySQL lagi.
3. Jika **1932 / doesn’t exist in engine** hanya untuk DB aplikasi `duniatex`: setelah backup, hentikan MySQL, hapus folder `C:\xampp\mysql\data\duniatex`, start MySQL, buat DB lagi, lalu `composer run db-reset-local`.

## Skema aplikasi (Laravel)

- ENUM `fabric_rolls.status` harus mencakup **`PENDING`** (migrasi `2026_05_07_000001` + cadangan `2026_05_12_120000`).
- Setelah DB kosong, jalankan seed: `php artisan db:seed --force` (atau sudah termasuk dalam `duniatex:fresh-local`).

## Produksi

Jangan menghapus folder data DB di server produksi seperti di XAMPP lokal. Gunakan backup resmi, replikasi, dan prosedur DBA.
