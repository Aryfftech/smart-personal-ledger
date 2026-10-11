# Smart Personal Ledger

Smart Personal Ledger: Integrating OCR Technology for Automated Financial Data Entry.
Final Year Project, Diploma in Computer Science, Universiti Poly-Tech Malaysia (2026).

**Developer:** Aryff Suleiman bin Mohammad Fahmy (AM2412018291)
**Supervisor:** Madam Wan Asnida bte Wan Jusoh

## Features
- Scan a printed receipt; OCR reads the shop name, total and date
- Confirmation screen to correct values before saving
- Automatic category suggestion with editable keyword rules
- Monthly budgets with warnings at 80% and over 100%
- Dashboard with category and six-month charts

## Built with
PHP 8.2, MySQL (XAMPP), Python, Flask, Tesseract OCR, OpenCV, Chart.js

## How to run
1. Install XAMPP and Tesseract OCR.
2. Copy this folder into `xampp/htdocs/smart_ledger`.
3. In phpMyAdmin, create a database named `smart_ledger` and import `smart_ledger.sql`.
4. Set the MySQL password in `db.php` if yours is not empty.
5. Install Python packages: `pip install flask opencv-python pytesseract`.
6. Run `start_ocr.bat` to start the OCR service.
7. Open `http://localhost/smart_ledger` in a browser.
