# com_pagebuilderck
com_pagebuilderck uploader
# 🚀 CVE-2026-56290 — Page Builder CK RCE Exploit Tool
🚀 CVE-2026-56290 — Page Builder CK ≤ 3.5.10 PoC

<p align="center"><img src="https://img.shields.io/badge/Python-3.9+-3776AB?style=for-the-badge&logo=python&logoColor=white">
<img src="https://img.shields.io/badge/Platform-Linux%20%7C%20Windows-success?style=for-the-badge">
<img src="https://img.shields.io/badge/Status-Educational-blue?style=for-the-badge">
<img src="https://img.shields.io/badge/License-MIT-orange?style=for-the-badge"></p>---

🇺🇸 English

📖 Overview

This repository contains a Proof of Concept (PoC) for CVE-2026-56290, affecting Page Builder CK ≤ 3.5.10.

The script demonstrates how the vulnerable "fonts.save" endpoint can be tested by using a user-hosted CSS payload.

Unlike public PoCs that rely on third-party payload servers, this implementation allows researchers to host their own files, making testing more transparent and reproducible in authorized environments.

---

✨ Features

- ✅ Single target mode
- ✅ Mass target scanning
- ✅ Multi-thread support
- ✅ Automatic CSRF token detection
- ✅ HTTPS support
- ✅ Custom hosted payload
- ✅ Automatic result logging
- ✅ Lightweight and easy to use

---

📂 Repository Structure

.
├── exploit.py
├── README.md
├── hits.txt
├── html.css
├── ph.css
├── ph3.css
├── pht.css
└── uploader.php

---

⚙️ Requirements

- Python 3.9+
- requests
- urllib3

Install dependencies:

pip install requests urllib3

---

🚀 Usage

Single Target

python3 exploit.py -u https://example.com

Mass Scan

python3 exploit.py -f targets.txt

Custom Payload

python3 exploit.py -u https://example.com -p php

Supported payloads:

Option| Description
php| PHP
phtml| PHTML
php3| PHP3
default| HTML

---

⚠️ Disclaimer

This repository is provided solely for educational purposes, defensive security research, and authorized security testing.

By using this project, you agree that:

- You will only test systems you own or have explicit permission to assess.
- You are solely responsible for complying with applicable laws and regulations.
- The author and contributors assume no liability for misuse, damages, or illegal activities arising from the use of this software.

Unauthorized access to computer systems may violate local, national, or international laws.

---

🤝 Contributing

Pull Requests are welcome.

For major changes, please open an issue first to discuss what you would like to change.

---

⭐ Support

If this project is useful for your security research, consider giving it a ⭐ on GitHub.

---

🇮🇩 Bahasa Indonesia

📖 Deskripsi

Repositori ini berisi Proof of Concept (PoC) untuk CVE-2026-56290 yang memengaruhi Page Builder CK ≤ 3.5.10.

Script ini digunakan untuk menguji kerentanan pada endpoint "fonts.save" dengan menggunakan payload CSS yang di-host sendiri.

Berbeda dengan PoC publik yang menggunakan server pihak ketiga, implementasi ini memungkinkan peneliti keamanan menggunakan infrastruktur miliknya sendiri sehingga proses pengujian menjadi lebih transparan dan dapat direproduksi pada lingkungan yang memiliki izin.

---

✨ Fitur

- ✅ Mode target tunggal
- ✅ Mode pemindaian massal
- ✅ Multi-thread
- ✅ Deteksi otomatis CSRF Token
- ✅ Mendukung HTTPS
- ✅ Payload di-host sendiri
- ✅ Penyimpanan hasil otomatis
- ✅ Ringan dan mudah digunakan

---

📂 Struktur Repositori

.
├── exploit.py
├── README.md
├── hits.txt
├── html.css
├── ph.css
├── ph3.css
├── pht.css
└── uploader.php

---

⚙️ Persyaratan

- Python 3.9+
- requests
- urllib3

Install dependensi:

pip install requests urllib3

---

🚀 Cara Menggunakan

Target Tunggal

python3 exploit.py -u https://example.com

Banyak Target

python3 exploit.py -f targets.txt

Memilih Payload

python3 exploit.py -u https://example.com -p php

Pilihan payload:

Opsi| Deskripsi
php| PHP
phtml| PHTML
php3| PHP3
default| HTML

---

⚠️ Disclaimer / Penafian

Repositori ini disediakan hanya untuk tujuan edukasi, penelitian keamanan siber, dan pengujian keamanan yang sah.

Dengan menggunakan proyek ini, Anda menyatakan bahwa:

- Anda hanya akan menguji sistem yang Anda miliki atau yang telah memberikan izin tertulis.
- Anda bertanggung jawab penuh atas penggunaan perangkat lunak ini.
- Penulis maupun kontributor tidak bertanggung jawab atas segala bentuk penyalahgunaan, kerusakan, maupun pelanggaran hukum yang timbul akibat penggunaan proyek ini.

Akses tanpa izin terhadap sistem komputer dapat melanggar hukum yang berlaku di berbagai yurisdiksi.

---

🤝 Kontribusi

Pull Request selalu diterima.

Silakan buka Issue terlebih dahulu apabila ingin mengusulkan perubahan besar.

---

⭐ Dukungan

Apabila proyek ini bermanfaat untuk penelitian keamanan Anda, jangan lupa memberikan ⭐ pada repositori GitHub.

---

<p align="center">Made with ❤️ for the Cyber Security Community

</p>
