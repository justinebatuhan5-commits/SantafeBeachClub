# Security Awareness Guide — Santa Fe Beach Club WebApp Team

**Version:** 1.0  
**Date:** September 2026  
**Scope:** All developers, testers, and maintainers of the Santa Fe Beach Club Booking System  
**Classification:** Internal — Team Use Only

---

## Purpose

This guide establishes the minimum security awareness requirements for every member of the WebApp development team. Following these rules protects the system, its users, and the team itself from preventable security incidents.

---

## Section 1 — Credential and Password Policy

### ❌ Do NOT share passwords
- Every team member must have their **own unique login credentials** for every system (GitHub, cPanel, database, admin panel).
- Sharing a single account across multiple people makes it impossible to audit who did what.
- If you need to give someone access, create a **new account** for them with the appropriate role.

### ❌ Do NOT share database credentials through Messenger, group chats, or email
- Database usernames and passwords must **never** be sent over Messenger, Facebook Groups, Discord, or similar platforms.
- These platforms are **not encrypted end-to-end** for file sharing and messages may be logged or leaked.
- Use `.env` files locally and share secrets only through a **password manager** or a **secure encrypted channel** (e.g., Bitwarden Send, 1Password).
- The `.env` file is already gitignored — **never commit it** to GitHub.

### ✅ Use strong individual accounts
- Passwords must be at least **12 characters** long and include uppercase, lowercase, numbers, and symbols.
- Use a **password manager** (Bitwarden is free) to generate and store unique passwords.
- Never reuse a password across two different services.
- Change any password immediately if you suspect it has been compromised.

---

## Section 2 — Multi-Factor Authentication (MFA)

### ✅ Enable MFA on GitHub and all important services
- Every team member must enable **two-factor authentication (2FA)** on their GitHub account.
  - Go to: `GitHub → Settings → Password and authentication → Enable two-factor authentication`
  - Use an authenticator app (Google Authenticator, Authy) — **not SMS** if possible.
- Enable MFA on:
  - GitHub ✅ (required)
  - cPanel / hosting control panel ✅ (if available)
  - Email accounts used for deployment notifications ✅

---

## Section 3 — Safe Browsing and Link Verification

### ✅ Verify suspicious links before clicking
- Do **not** click links in unexpected emails or messages claiming to be from GitHub, cPanel, or Google.
- Hover over any link to inspect the actual URL before clicking.
- If you receive a suspicious link in a group chat, report it and **do not open it** on a device that has access to the codebase or admin panel.
- Use [VirusTotal](https://www.virustotal.com) or [Google Safe Browsing](https://transparencyreport.google.com/safe-browsing/search) to check a URL if you are unsure.

---

## Section 4 — Safe Development Environment Practices

### ❌ Do NOT download unknown files into the development environment
- Never install software, plugins, or libraries from untrusted or unofficial sources.
- Only install packages from:
  - **Composer** (PHP packages via `composer.json`)
  - **npm / pip** with verified package names
  - Official vendor sites or GitHub repositories with verifiable authorship
- Be cautious of **typosquatting** — malicious packages that have names similar to popular libraries (e.g., `phpmailer` vs `php-mailer`).
- Scan downloaded files with your antivirus before execution.

---

## Section 5 — Test Data Policy

### ❌ Do NOT use real sensitive personal information for testing unless necessary and properly authorized
- Never use real guest names, phone numbers, email addresses, or payment details for testing purposes.
- Real PII in a test environment is a **data breach waiting to happen**.
- If real data must be used (e.g., during UAT with an actual client), get **written authorization** from the client first.

### ✅ Use dummy/test accounts whenever possible
Use the following pattern for all test data:

| Field        | Example Test Value             |
|--------------|--------------------------------|
| Name         | `Test User` / `Juan dela Cruz` |
| Email        | `testuser@example.com`         |
| Phone        | `09000000000`                  |
| Booking Ref  | `TEST-001`, `TEST-002`, etc.   |
| Password     | `TestPassword123!`             |

- Delete all test data before any production push.
- Never commit test data to the repository.

---

## Section 6 — GitHub and Version Control Security

- **Never commit secrets** — API keys, database passwords, `.env` files, or session secrets must never appear in any commit.
  - Run `git status` before every commit and double-check what you are staging.
  - Use `git diff --staged` to review changes before committing.
- Enable **branch protection** on `main`/`master` so no one can force-push directly.
- Use **pull requests** for all significant changes — have at least one other team member review.
- If you accidentally commit a secret, assume it is **compromised immediately**. Rotate the credential and rewrite git history using `git filter-repo`.

---

## Section 7 — Incident Reporting

If you discover or suspect a security incident:

1. **Do not panic** — but act quickly.
2. **Do not post about it** on public channels, social media, or group chats.
3. **Immediately notify** the team lead or project owner privately.
4. Follow the full procedure in [`INCIDENT_RESPONSE_PLAN.md`](./INCIDENT_RESPONSE_PLAN.md).

| Severity | Example                                     | Who to Notify                  |
|----------|---------------------------------------------|--------------------------------|
| Critical | Database breach, admin account compromised  | Team Lead immediately          |
| High     | Suspicious login attempt, file modification | Team Lead within 1 hour        |
| Medium   | Failed brute force, suspicious traffic      | Log and report within 24 hours |

---

## Quick Reference Checklist

Use this checklist at the start of every development session:

- [ ] I am logged in with **my own account** (not a shared one)
- [ ] I have **2FA enabled** on GitHub
- [ ] My `.env` file is present locally and **not committed**
- [ ] I will only use **dummy/test data** during development
- [ ] I will **verify links** before clicking in emails or chat
- [ ] I will **not share** credentials over Messenger or group chats
- [ ] I will **not install** unknown packages or files

---

## References

- [INCIDENT_RESPONSE_PLAN.md](./INCIDENT_RESPONSE_PLAN.md) — What to do when a breach occurs
- [DISASTER_RECOVERY_PLAN.md](./DISASTER_RECOVERY_PLAN.md) — How to restore system operations
- [OWASP Top 10](https://owasp.org/www-project-top-ten/) — Most common web vulnerabilities
- [GitHub 2FA Setup](https://docs.github.com/en/authentication/securing-your-account-with-two-factor-authentication-2fa)
- [Bitwarden (Free Password Manager)](https://bitwarden.com)

---

*This document must be reviewed and acknowledged by all team members before gaining access to production systems.*
