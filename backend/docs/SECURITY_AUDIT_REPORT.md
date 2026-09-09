# 🛡️ Security Audit Report
## Santa Fe Beach Club — Online Booking System

---

| Field              | Details                                          |
|--------------------|--------------------------------------------------|
| **System Name**    | Santa Fe Beach Club Booking System               |
| **Report Version** | 1.0                                              |
| **Audit Date**     | September 2026                                   |
| **Auditor**        | Justine Batuhan (System Developer)               |
| **Environment**    | PHP 8.2 · MariaDB 10.4 · Apache · XAMPP         |
| **Live URL**       | https://santafebeachclub.wuaze.com               |
| **Scanner Used**   | Wapiti 3.3.2 (OWASP-aligned web vulnerability scanner) |

---

## Executive Summary

A comprehensive security audit was performed on the Santa Fe Beach Club Booking System covering all **17 sections** of the project's Security Checklist. The audit combined automated scanning (Wapiti 3.3.2), manual code review, and configuration hardening.

### 🏆 Overall Result: PASSED — No Critical Vulnerabilities Found

| Category               | Result     |
|------------------------|------------|
| Automated Scan (Wapiti)| ✅ 0 vulnerabilities detected |
| OWASP Top 10 Coverage  | ✅ All 10 categories addressed |
| Security Headers       | ✅ 7 headers implemented       |
| PII Protection         | ✅ Phone & email masking active |
| CSRF Protection        | ✅ All forms protected          |
| File Upload Security   | ✅ Multi-layer validation       |
| Server Hardening       | ✅ Directory listing disabled   |
| Logging & Monitoring   | ✅ Security event logging active|
| Incident Response      | ✅ Plan documented              |
| Disaster Recovery      | ✅ Plan with RTO/RPO defined    |
| Security Awareness     | ✅ Team guide created           |

---

## Automated Vulnerability Scan Results

**Tool:** Wapiti 3.3.2  
**Scan Date:** August 25, 2026  
**Target:** `http://127.0.0.1/SantaBeachClub-BookingSystem/`

| Vulnerability Category                    | Findings |
|------------------------------------------|----------|
| SQL Injection                            | ✅ 0     |
| Blind SQL Injection                      | ✅ 0     |
| Reflected Cross-Site Scripting (XSS)     | ✅ 0     |
| Stored Cross-Site Scripting              | ✅ 0     |
| Cross-Site Request Forgery (CSRF)        | ✅ 0     |
| Unrestricted File Upload                 | ✅ 0     |
| Path Traversal                           | ✅ 0     |
| Command Execution                        | ✅ 0     |
| CRLF Injection                           | ✅ 0     |
| Open Redirect                            | ✅ 0     |
| Content Security Policy (CSP)            | ✅ 0     |
| HTTP Strict Transport Security (HSTS)    | ✅ 0     |
| Clickjacking Protection                  | ✅ 0     |
| MIME Type Confusion                      | ✅ 0     |
| Stack Trace Disclosure                   | ✅ 0     |
| Information Disclosure — Full Path       | ✅ 0     |
| Cleartext Submission of Password         | ✅ 0     |
| Weak Credentials                         | ✅ 0     |
| Htaccess Bypass                          | ✅ 0     |
| TLS/SSL Misconfigurations                | ✅ 0     |
| Backup File Exposure                     | ✅ 0     |
| Potentially Dangerous File               | ✅ 0     |
| Internal Server Error (Anomaly)          | ✅ 0     |
| **TOTAL VULNERABILITIES**                | **✅ 0** |

---

## Security Checklist — Implementation Status

### ✅ Section 1 — Authentication & Session Management
| Control | Status | Implementation |
|---------|--------|----------------|
| Secure password hashing | ✅ Done | `password_hash()` with `PASSWORD_BCRYPT` |
| Session timeout | ✅ Done | Idle timeout enforced in `auth_check.php` |
| Session regeneration on login | ✅ Done | `session_regenerate_id(true)` on login |
| HttpOnly + Secure cookie flags | ✅ Done | Set in `session_start()` config |
| Cache-Control on authenticated pages | ✅ Done | `no-store, no-cache, must-revalidate` headers |
| Login rate limiting | ✅ Done | `RateLimiter::check()` — max 5 attempts / 15 min per IP |
| Account lockout | ✅ Done | `RateLimiter::recordFailedLogin()` — locks account for 15 min after 5 failures |

---

### ✅ Section 2 — Multi-Factor Authentication (MFA)
| Control | Status | Implementation |
|---------|--------|----------------|
| OTP-based MFA for staff/admin | ✅ Done | Email OTP via PHPMailer on login |
| OTP expiry | ✅ Done | 10-minute expiry enforced |
| MFA lockout after failed attempts | ✅ Done | Logged as `MFA_LOCKOUT` event |

---

### ✅ Section 3 — Input Validation & Output Encoding
| Control | Status | Implementation |
|---------|--------|----------------|
| Server-side input validation | ✅ Done | All API endpoints validate inputs |
| Output encoding (XSS prevention) | ✅ Done | `htmlspecialchars()` on all user-facing output |
| Parameterized queries (SQL injection) | ✅ Done | PDO prepared statements throughout |

---

### ✅ Section 4 — CSRF Protection
| Control | Status | Implementation |
|---------|--------|----------------|
| CSRF tokens on all forms | ✅ Done | `csrf_token()` helper used on all POST forms |
| Token validation on submission | ✅ Done | Server-side validation before processing |
| Token regeneration per session | ✅ Done | New token issued per session |

---

### ✅ Section 5 — PII / Data Privacy Protection (RA 10173 Compliance)
| Control | Status | Implementation |
|---------|--------|----------------|
| Data Minimization Policy | ✅ Done | `terms.php` & policy — collects only essential fields (name, email, phone, proof); no credit card or CVV collected/stored |
| Data Retention & Disposal Policy | ✅ Done | `terms.php` & policies — bookings held 2 yrs, payment proofs 90 days, OTP/tokens 10-15 mins, security logs 90 days |
| Phone number masking | ✅ Done | `pii_masker.php` — format: `+63 9** *** **34` |
| Email address masking | ✅ Done | `pii_masker.php` — format: `te***@gmail.com` |
| Toggle for full view (modal only) | ✅ Done | Masked by default, expandable in modal |
| Applied in guest directory | ✅ Done | `guests.php` and `admin_reservations.php` |

---

### ✅ Section 6 — Secure File Upload
| Control | Status | Implementation |
|---------|--------|----------------|
| MIME type validation (server-side) | ✅ Done | `finfo_file()` checks real MIME type |
| Extension whitelist | ✅ Done | Only `.jpg`, `.jpeg`, `.png`, `.pdf` allowed |
| File renamed to random hash | ✅ Done | `bin2hex(random_bytes(16))` — no original name kept |
| Upload size limit | ✅ Done | 5MB max enforced |
| .htaccess barrier in uploads folder | ✅ Done | Blocks PHP execution in `uploads/` and `uploads/receipts/` |

---

### ✅ Section 7 — Secure Configuration & Server Hardening
| Control | Status | Implementation |
|---------|--------|----------------|
| Directory listing disabled | ✅ Done | `Options -Indexes` in root `.htaccess` |
| Server version hidden | ✅ Done | `ServerSignature Off`, `X-Powered-By` unset |
| Sensitive file access blocked | ✅ Done | `.env`, `.git`, `config.php`, `.log` all deny-all |
| Hidden directory access blocked | ✅ Done | `RewriteRule (^|/)\.` blocks dot-file access |
| HTTPS redirect (production only) | ✅ Done | HTTP → HTTPS rewrite skips `localhost` |

---

### ✅ Section 8 — HTTP Security Headers
| Header | Status | Value |
|--------|--------|-------|
| `Content-Security-Policy` | ✅ Done | Strict CSP with allowed CDNs only |
| `X-Frame-Options` | ✅ Done | `SAMEORIGIN` |
| `X-Content-Type-Options` | ✅ Done | `nosniff` |
| `X-XSS-Protection` | ✅ Done | `1; mode=block` |
| `Referrer-Policy` | ✅ Done | `strict-origin-when-cross-origin` |
| `Permissions-Policy` | ✅ Done | Camera/mic restricted to self |
| `Strict-Transport-Security` | ✅ Done | Enabled on HTTPS connections, `max-age=31536000` |

---

### ✅ Section 9 — Error Handling
| Control | Status | Implementation |
|---------|--------|----------------|
| Production error display off | ✅ Done | `display_errors = Off` in production |
| Stack traces masked | ✅ Done | `error_handler.php` hides paths in production |
| Errors logged (not displayed) | ✅ Done | `log_errors = On`, logged to `backend/logs/` |

---

### ✅ Section 10 — Security Logging & Monitoring
| Control | Status | Implementation |
|---------|--------|----------------|
| Login event logging | ✅ Done | `security_logger.php` logs all login attempts |
| Failed login tracking | ✅ Done | `FAILED_LOGIN` events with IP and timestamp |
| Account lockout event logging | ✅ Done | `ACCOUNT_LOCKED` event logged with username + IP |
| Automated Security Email Alerts | ✅ Done | `SecurityLogger::sendSecurityAlertEmail()` auto-dispatches incident alert emails to Admin via PHPMailer on `CRITICAL` events (e.g. `BRUTE_FORCE_LOCKOUT`) |
| MFA event logging | ✅ Done | `MFA_LOCKOUT`, `MFA_SUCCESS` events |
| Admin-facing log viewer | ✅ Done | `admin_logs.php` — Security Audit tab |
| Log files protected from web access | ✅ Done | `.htaccess` deny-all on `.log` files |

---

### ✅ Section 11 — Access Control (RBAC + IDOR/BOLA)
| Control | Status | Implementation |
|---------|--------|----------------|
| Role-based access (admin/staff/guest) | ✅ Done | `auth_check.php` + `rbac_helper.php` enforces role per page |
| Unauthorized access blocked | ✅ Done | Redirect to login + event logged |
| Admin pages restricted | ✅ Done | Session + role check on every admin page |
| IDOR/BOLA protection | ✅ Done | `guest_lookup_api.php`: requires `booking_id AND email` match — prevents enumeration by ID alone |

---

### ✅ Section 12 — Password Reset Security
| Control | Status | Implementation |
|---------|--------|----------------|
| Secure token-based reset | ✅ Done | `password_reset_helper.php` — cryptographic token |
| Token expiry | ✅ Done | 1-hour expiry enforced |
| One-time use token | ✅ Done | Token invalidated after use |
| Email notification on change | ✅ Done | PHPMailer sends confirmation email |

---

### ✅ Section 13 — Deployment Security
| Control | Status | Implementation |
|---------|--------|----------------|
| Secrets stored in GitHub Secrets | ✅ Done | FTP credentials in Actions secrets |
| Sensitive files excluded from deploy | ✅ Done | `.env`, `.git`, `*.md`, `.github/` excluded |
| Automated deploy pipeline | ✅ Done | GitHub Actions FTP Deploy on push to `main` |

---

### ✅ Section 14 — Dependency & Software Security
| Control | Status | Implementation |
|---------|--------|----------------|
| Automated dependency security scanning | ✅ Done | `.github/dependabot.yml` — automated weekly scans for Python dependencies and CI/CD actions with auto-generated security PRs |
| `.gitignore` covers sensitive files | ✅ Done | `.env`, `vendor/`, logs, backups excluded |
| No known-vulnerable libraries in use | ✅ Done | PHPMailer and microservice dependencies reviewed and pinned |

---

### ✅ Section 15 — Transport Security
| Control | Status | Implementation |
|---------|--------|----------------|
| HTTPS enforced in production | ✅ Done | `.htaccess` redirect + HSTS header |
| Unencrypted channel check | ✅ Done | Wapiti scan: 0 findings |

---

### ✅ Section 16 — Incident Response & Disaster Recovery
| Document | Status | Location |
|----------|--------|----------|
| Incident Response Plan | ✅ Done | `backend/docs/INCIDENT_RESPONSE_PLAN.md` |
| Disaster Recovery Plan | ✅ Done | `backend/docs/DISASTER_RECOVERY_PLAN.md` |
| RTO (Recovery Time Objective) | ✅ Defined | 2 hours |
| RPO (Recovery Point Objective) | ✅ Defined | 24 hours (daily backups) |
| 5-Phase response procedure | ✅ Done | Detect → Contain → Eradicate → Recover → Review |
| Severity classification | ✅ Done | P1 Critical → P4 Low |

---

### ✅ Section 17 — Security Awareness for WebApp Team
| Document | Status | Location |
|----------|--------|----------|
| Security Awareness Guide | ✅ Done | `backend/docs/SECURITY_AWARENESS_GUIDE.md` |
| Password policy | ✅ Documented | No sharing, 12+ chars, password manager |
| MFA policy | ✅ Documented | Required on GitHub and hosting panel |
| Test data policy | ✅ Documented | No real PII; dummy accounts only |
| Incident reporting procedure | ✅ Documented | Linked to Incident Response Plan |

---

## OWASP Top 10 Coverage Matrix

| # | OWASP Category | Status | Control Implemented |
|---|----------------|--------|---------------------|
| A01 | Broken Access Control | ✅ Mitigated | Role-based auth, session checks |
| A02 | Cryptographic Failures | ✅ Mitigated | HTTPS, bcrypt hashing, HSTS |
| A03 | Injection (SQL/HTML) | ✅ Mitigated | PDO prepared statements, htmlspecialchars |
| A04 | Insecure Design | ✅ Mitigated | CSRF tokens, input validation, secure defaults |
| A05 | Security Misconfiguration | ✅ Mitigated | Headers, htaccess hardening, error suppression |
| A06 | Vulnerable Components | ✅ Mitigated | Dependencies reviewed, gitignore hardened |
| A07 | Auth & Session Failures | ✅ Mitigated | MFA, session regeneration, secure cookies |
| A08 | Software Integrity Failures | ✅ Mitigated | Signed GitHub deploys, no unknown packages |
| A09 | Logging & Monitoring | ✅ Mitigated | security_logger.php, admin_logs.php |
| A10 | SSRF | ✅ Mitigated | Wapiti scan: 0 SSRF findings |

---

## Key Security Files Reference

| File | Purpose |
|------|---------|
| [`backend/helpers/security_headers.php`](../helpers/security_headers.php) | Sets all 7 HTTP security headers + CSP |
| [`backend/helpers/auth_check.php`](../helpers/auth_check.php) | Session validation, role enforcement, cache headers |
| [`backend/helpers/pii_masker.php`](../helpers/pii_masker.php) | Masks phone numbers and emails for display |
| [`backend/helpers/security_logger.php`](../helpers/security_logger.php) | Logs all security events with IP and timestamp |
| [`backend/helpers/error_handler.php`](../helpers/error_handler.php) | Suppresses stack traces in production |
| [`backend/helpers/password_reset_helper.php`](../helpers/password_reset_helper.php) | Secure token-based password reset |
| [`.htaccess`](../../.htaccess) | Server hardening, HTTPS redirect, file blocking |
| [`frontend/uploads/.htaccess`](../../frontend/uploads/.htaccess) | Blocks PHP execution in upload directory |
| [`backend/docs/INCIDENT_RESPONSE_PLAN.md`](./INCIDENT_RESPONSE_PLAN.md) | 5-phase incident response procedure |
| [`backend/docs/DISASTER_RECOVERY_PLAN.md`](./DISASTER_RECOVERY_PLAN.md) | Disaster scenarios and recovery steps |
| [`backend/docs/SECURITY_AWARENESS_GUIDE.md`](./SECURITY_AWARENESS_GUIDE.md) | Team security policies and checklist |

---

## Conclusion

The Santa Fe Beach Club Booking System has undergone a thorough security audit across all 17 checklist sections. The system demonstrates:

- **Zero vulnerabilities** detected by automated scanning (Wapiti 3.3.2)
- **Full OWASP Top 10 coverage** through implemented controls
- **Defence-in-depth** — multiple layers of protection at server, application, and process levels
- **Documented procedures** for incident response, disaster recovery, and team security awareness
- **Production-ready hardening** compatible with InfinityFree shared hosting and automated FTP deployment

> *This audit was conducted as part of the capstone project for the Santa Fe Beach Club Booking System. All security controls were implemented, tested, and verified by the development team.*

---

*Generated: September 2026 | Auditor: Justine Batuhan | Version: 1.0*
