# com_pagebuilderck
com_pagebuilderck uploader
# 🚀 CVE-2026-56290 — Page Builder CK RCE Exploit Tool

<p align="center">
  <b>[<a href="#-english">English</a>] | [<a href="#-bahasa-indonesia">Bahasa Indonesia</a>]</b>
</p>

---

## 🇺🇸 English

### 🛡️ Overview
**CVE-2026-56290** is a Remote Code Execution (RCE) vulnerability affecting **Page Builder CK <= 3.5.10** via the `fonts.save` task. This modern, multi-threaded exploit tool is designed for security auditing and penetration testing with custom self-hosted uploader support.

### ✨ Features
* ⚡ **High Performance:** Asynchronous multi-threading powered by `ThreadPoolExecutor` for fast mass scanning.
* 🛡️ **Custom Payload Support (`-p`):** Easily switch between extensions (`php`, `phtml`, `php3`, etc.).
* 📊 **Smart Validation:** Automatic CSRF token extraction, endpoint verification, and marker detection.
* 📝 **Mass Scanning & Logging:** Streams targets efficiently and saves successful hits automatically to `hits.txt`.

### ⚙️ Installation

Clone the repository and install the required dependencies:

```bash
git clone [https://github.com/MRCUKIL/com_pagebuilderck.git](https://github.com/MRCUKIL/com_pagebuilderck.git)
cd com_pagebuilderck
pip install -r requirements.txt
