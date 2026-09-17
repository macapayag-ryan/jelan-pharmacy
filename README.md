# CyberClinic — Secure Medical Appointment System

## Purpose of the Project

CyberClinic is a secure, full-stack medical appointment management system built to address a common problem in small and mid-sized clinics: the lack of an affordable, secure, and easy-to-use digital platform for managing patient appointments, medical records, and prescriptions.

The system was developed to demonstrate that strong cybersecurity practices — field-level encryption, two-factor authentication, audit logging, and automated encrypted backups — can be built into a clinic management system without requiring expensive third-party software. CyberClinic gives patients a simple way to book appointments online and gives clinic administrators full control over scheduling, medical records, and system security, all while protecting sensitive health data at every layer.

This project also serves as an academic capstone demonstrating the integration of multiple computer science and information technology subjects into one working, real-world application: System Integration, Advanced Database Management, Object-Oriented Programming, Data Structures and Algorithms, Cloud Computing, and Advanced Programming.

---

## Key Features

### For Patients
- Self-registration with encrypted personal information
- Book appointments with specialist doctors through a 3-step wizard
- View appointment history and cancel pending/approved appointments
- View medical records and prescriptions (decrypted on demand)
- In-app notifications for appointment status changes
- Optional Two-Factor Authentication (TOTP) via Google Authenticator or Authy

### For Administrators
- Dashboard with live system statistics
- Approve, reject, or complete patient appointments
- Manage doctors (add, edit, deactivate)
- View all patients and their encrypted medical data
- Add medical records and prescriptions
- Reports and Analytics with 5 views: Monthly, Weekly, Yearly, By Doctor, By Specialty
- Full audit log of every system action
- Python-powered automated database backup with AES-256 encryption
- Python-powered security scanner that detects brute-force attacks and scoring (A–F grade)

---

## Security Architecture

| Feature | Implementation |
|---|---|
| Data encryption | AES-256-CBC encryption on all personal and medical fields before database storage |
| Two-Factor Authentication | TOTP-based (RFC 6238), compatible with Google Authenticator and Authy |
| Password security | bcrypt hashing with enforced password strength rules |
| CSRF protection | Token verification on every form submission |
| Rate limiting | 5 failed attempts per 15-minute window, per account and IP |
| Session security | HttpOnly cookies, SameSite=Strict, user-agent binding, idle timeout |
| Audit logging | Every login, booking, and admin action is permanently logged |
| Automated backup | Python script: mysqldump → gzip compression → AES-256 encryption → SHA-256 integrity hash |
| Security scanning | Python script analyzing audit logs for brute-force patterns and anomalies |

---

## Subjects Integrated

| Subject | How it was applied |
|---|---|
| **System Integration** | `api.php` exposes a full REST API (JSON) with Bearer token authentication, allowing external systems to integrate with CyberClinic. PHP also integrates with Python via `exec()` for backup and security operations. |
| **Advanced Database** | `database.sql` includes 4 stored procedures, 3 triggers, and 4 views for booking logic, automatic notifications, and reporting, plus transaction-safe booking with rollback support. |
| **Object-Oriented Programming** | `includes/classes.php` implements interfaces (`Encryptable`, `Auditable`, `Notifiable`), an abstract `BaseModel`, inheritance (`BaseModel → UserModel → Patient`), and design patterns (Singleton for the database connection, Factory for notifications). |
| **Data Structures & Algorithms** | Implemented inside the `Appointment` class: a FIFO queue for processing pending appointments, binary search for fast lookup by ID, and merge sort for ordering appointments by date. |
| **Cloud Computing** | `includes/env.php` and `.env.example` implement environment-variable-based configuration so the system can be deployed to cloud platforms (Railway, Render, PlanetScale) without hardcoding credentials. |
| **Advanced Programming** | `scripts/advanced_programming.py` demonstrates threading (non-blocking backup execution), decorators (`@timer`, `@retry`, `@audit_log`), generators (memory-efficient data streaming), dataclasses, type hints, context managers, and the Singleton/Observer/Strategy design patterns. |

---

## Technology Stack

- **Backend:** PHP 8+ (procedural + OOP), PDO for database access
- **Database:** MySQL / MariaDB (via XAMPP)
- **Frontend:** HTML5, CSS3 (custom, no framework), vanilla JavaScript
- **Cybersecurity automation:** Python 3 (backup, encryption, security scanning)
- **Charts:** Chart.js
- **Server environment:** Apache (via XAMPP)

---

## Project Structure

```
cyberclinic/
├── admin/                  Admin panel pages (dashboard, appointments, doctors, etc.)
├── patient/                Patient portal pages (dashboard, booking, records, etc.)
├── includes/
│   ├── config.php          Core config, encryption, auth, session handling
│   ├── classes.php         OOP classes (Patient, Doctor, Appointment, DSA)
│   ├── env.php             Environment variable loader (Cloud Computing)
│   ├── admin_header.php / admin_footer.php
│   └── patient_header.php
├── scripts/
│   ├── backup.py           Automated encrypted database backup
│   ├── security_scan.py    Audit log threat scanner
│   └── advanced_programming.py   Threading, decorators, design patterns demo
├── assets/css/style.css    All styling
├── api.php                 REST API (System Integration)
├── database.sql            Full schema + stored procedures + triggers + views
├── index.php / login.php / register.php
├── .env.example             Environment variable template (Cloud Computing)
└── documentation.html      Subject integration reference (open in browser)
```

---

## Installation

1. Clone or download this repository into your `htdocs` folder (XAMPP).
2. Open phpMyAdmin and import `database.sql`.
3. Copy `.env.example` to `.env` and fill in your local database credentials.
4. Open `includes/config.php` and confirm `SITE_URL` matches your local setup.
5. (Optional) Install Python dependencies for the backup and security features:
   ```
   pip install cryptography mysql-connector-python
   ```
6. Start Apache and MySQL in XAMPP.
7. Visit `http://localhost/cyberclinic` (or your configured port) in your browser.

**Default admin login:**
- Email: `admin@cyberclinic.com`
- Password: `password`

Change this password immediately after first login.

---

## API Quick Reference

Base URL: `http://localhost/cyberclinic/api.php`

| Method | Endpoint | Auth | Description |
|---|---|---|---|
| GET | `?endpoint=status` | None | Health check |
| POST | `?endpoint=auth` | None | Login, returns Bearer token |
| GET | `?endpoint=doctors` | None | List all active doctors |
| GET | `?endpoint=appointments` | Patient token | Get logged-in patient's appointments |
| POST | `?endpoint=book` | Patient token | Book a new appointment |
| GET | `?endpoint=stats` | Admin token | System-wide statistics |

Full endpoint documentation is available in `documentation.html`.

---

## Authors

This project was developed as a capstone/thesis system by [Group/Team Name].

| Role | Name |
|---|---|
| Lead Programmer | |
| Documenter | |
| Documenter | |
| Adviser | |

---

## License

This project was developed for academic purposes. All rights reserved by the development team unless otherwise specified by the academic institution.
