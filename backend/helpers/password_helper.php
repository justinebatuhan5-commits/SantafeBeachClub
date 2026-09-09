<?php
/**
 * Password Helper — SantaBeachClub Booking System
 *
 * Centralises all password hashing, verification, and validation logic
 * to enforce the Strong Password Policy (OWASP-aligned).
 *
 * Hashing  : Argon2id via PASSWORD_ARGON2ID (OWASP recommended, 2026)
 * Min length: 8 characters
 * Complexity: uppercase + lowercase + digit + special character
 * Blocklist : prevents extremely common / compromised passwords
 *
 * Migration : Existing bcrypt hashes are transparently upgraded to Argon2id
 *             on the user's next successful login via pw_needs_rehash().
 */

// ─────────────────────────────────────────────────────────────
// Hashing constants
// ─────────────────────────────────────────────────────────────

/** Use Argon2id — resistant to both side-channel and GPU brute-force attacks. */
define('PW_ALGO', PASSWORD_ARGON2ID);

/**
 * Argon2id tuning (OWASP recommended minimums):
 *  memory_cost : 65536 KB  (64 MB)  — increases memory required per hash
 *  time_cost   : 4         iterations
 *  threads     : 1         (safe default for shared hosting)
 */
define('PW_OPTIONS', [
    'memory_cost' => 65536,  // 64 MB
    'time_cost'   => 4,
    'threads'     => 1,
]);

// ─────────────────────────────────────────────────────────────
// Common / compromised password blocklist
// ─────────────────────────────────────────────────────────────
const COMMON_PASSWORDS = [
    'password',  'password1',  'password123', 'password!',
    '12345678',  '123456789',  '1234567890',  '123456',
    'qwerty123', 'qwerty',     'qwertyuiop',  'letmein',
    'iloveyou',  'monkey',     'dragon',      'master',
    'sunshine',  'princess',   'welcome',     'shadow',
    'superman',  'batman',     'football',    'baseball',
    'trustno1',  'passw0rd',   'abc123',      'admin',
    'admin123',  'admin1234',  'admin@123',   'admin@1234',
    'root',      'root123',    'toor',        'test',
    'test123',   'guest',      'guest123',    'login',
    'changeme',  'secret',     'letmein1',    '111111',
    '000000',    'aaaaaa',     '123123',      '654321',
    'beachclub', 'beach123',   'santa',       'santa123',
    'santafe',   'santabeach', 'booking',     'booking123',
];

// ─────────────────────────────────────────────────────────────
// Public API
// ─────────────────────────────────────────────────────────────

/**
 * Hash a plaintext password using Argon2id.
 *
 * @param  string $plaintext
 * @return string  The Argon2id hash.
 */
function pw_hash(string $plaintext): string
{
    return password_hash($plaintext, PW_ALGO, PW_OPTIONS);
}

/**
 * Verify a plaintext password against a stored hash.
 *
 * @param  string $plaintext
 * @param  string $hash
 * @return bool
 */
function pw_verify(string $plaintext, string $hash): bool
{
    return password_verify($plaintext, $hash);
}

/**
 * Check whether a stored hash needs to be upgraded
 * (e.g. cost factor changed, algorithm changed).
 *
 * @param  string $hash
 * @return bool
 */
function pw_needs_rehash(string $hash): bool
{
    return password_needs_rehash($hash, PW_ALGO, PW_OPTIONS);
}

/**
 * Validate a candidate password against the strong password policy.
 *
 * Policy (all must pass):
 *  - Minimum 8 characters
 *  - At least one uppercase letter
 *  - At least one lowercase letter
 *  - At least one digit
 *  - At least one special character  (!@#$%^&* etc.)
 *  - Not on the common/compromised password blocklist
 *
 * @param  string      $password   The candidate password.
 * @return string|null  null on success, or a human-readable error string.
 */
function pw_validate(string $password): ?string
{
    if (strlen($password) < 8) {
        return 'Password must be at least 8 characters long.';
    }

    if (!preg_match('/[A-Z]/', $password)) {
        return 'Password must contain at least one uppercase letter.';
    }

    if (!preg_match('/[a-z]/', $password)) {
        return 'Password must contain at least one lowercase letter.';
    }

    if (!preg_match('/[0-9]/', $password)) {
        return 'Password must contain at least one number.';
    }

    if (!preg_match('/[^A-Za-z0-9]/', $password)) {
        return 'Password must contain at least one special character (e.g. !@#$%^&*).';
    }

    if (in_array(strtolower($password), COMMON_PASSWORDS, true)) {
        return 'That password is too common or has been compromised. Please choose a different one.';
    }

    return null; // All checks passed
}
