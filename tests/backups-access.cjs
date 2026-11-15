// Tests the real page's authorization branches without changing application accounts.
const assert = require('node:assert/strict');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
const php = process.env.PHP_BINARY || 'C:/xampp/php/php.exe';
const page = path.resolve(__dirname, '../frontend/admin_backups.php');
function request(session, method, post = {}, get = {}) {
    const code = `
        ini_set('session.save_path', sys_get_temp_dir());
        session_start();
        $_SESSION = json_decode($argv[2], true);
        $_SERVER['REQUEST_METHOD'] = $argv[3];
        $_POST = json_decode($argv[4], true);
        $_GET = json_decode($argv[5], true);
        register_shutdown_function(function () {
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            echo "\\nSTATUS:" . (http_response_code() ?: 200);
        });
        require $argv[1];
    `;
    return execFileSync(php, ['-d', 'display_errors=0', '-r', code, page, JSON.stringify(session), method, JSON.stringify(post), JSON.stringify(get)], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']});
}
const controller = path.resolve(__dirname, '../backend/controllers/admin_backups_controller.php');
const csrfHelper = path.resolve(__dirname, '../backend/helpers/csrf_helper.php');

function csrfRequest(action) {
    const code = `
        ini_set('session.save_path', sys_get_temp_dir());
        require_once $argv[1];
        require_once $argv[2];
        $_SESSION = ['admin_logged_in' => true, 'admin_role' => 'admin', 'csrf_token' => 'test-token'];
        $_SERVER['REQUEST_METHOD'] = 'POST';
        $_POST = ['action' => $argv[3]];
        register_shutdown_function(function () {
            if (session_status() === PHP_SESSION_ACTIVE) session_destroy();
            echo "\\nSTATUS:" . (http_response_code() ?: 200);
        });
        sfbc_backups_handle_post();
    `;
    return execFileSync(php, ['-d', 'display_errors=0', '-r', code, csrfHelper, controller, action], {encoding: 'utf8', stdio: ['ignore', 'pipe', 'pipe']});
}

for (const action of ['pair', 'revoke', 'download', 'installer', 'downloader']) {
    assert.match(request({}, 'POST', {action}), /STATUS:302$/);
    assert.match(request({admin_logged_in: true, admin_role: 'receptionist'}, 'POST', {action}), /STATUS:302$/);
    assert.match(request({admin_logged_in: true, admin_role: 'admin', csrf_token: 'test-token'}, 'POST', {action}), /STATUS:302$/);
    assert.match(csrfRequest(action), /STATUS:403$/);
}
assert.match(request({}, 'GET', {}, {status: 1}), /STATUS:302$/);
assert.match(request({admin_logged_in: true, admin_role: 'admin', last_activity: 1}, 'GET', {}, {status: 1}), /STATUS:302$/);
console.log('PASS: guests, receptionists, expired sessions and missing CSRF tokens cannot access backup operations.');
