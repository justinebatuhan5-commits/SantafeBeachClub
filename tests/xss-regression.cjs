// Run: node tests/xss-regression.cjs (PHP must be on PATH).
const assert = require('node:assert/strict');
const fs = require('node:fs');
const vm = require('node:vm');
const {execFileSync} = require('node:child_process');
const path = require('node:path');
process.chdir(path.join(__dirname, '..'));
const context = {window: {location: {href: 'https://example.test/frontend/dashboard'}}, document: {readyState: 'loading', addEventListener() {}}, URL};
vm.runInNewContext(fs.readFileSync('frontend/assets/js/security.js', 'utf8'), context);
const security = context.window.Security;
const decode = s => s.replace(/&quot;/g, '"').replace(/&#039;/g, "'").replace(/&lt;/g, '<').replace(/&gt;/g, '>').replace(/&amp;/g, '&');
const payloads = [
  `O'Brien "Beach" & café`,
  `</script><img src=x onerror=alert(1)>`,
  `');globalThis.compromised=true;//`,
  '` ${globalThis.compromised=true} \\ \n',
  `&quot; onmouseover=alert(1) x="`
];
for (const payload of payloads) {
  const encoded = JSON.parse(execFileSync('php', ['-r',
    'require "backend/helpers/output_helper.php"; $v=$argv[1]; echo json_encode([xss_html($v),xss_json($v),xss_attr_json($v),xss_attr_string($v)]);', payload], {encoding: 'utf8'}));
  assert(!/[<>"']/.test(encoded[0]));
  assert(!/[<>&']/.test(encoded[1]));
  assert(!/[<>"']/.test(encoded[2]));
  assert(!/[<>"']/.test(encoded[3]));
  assert.equal(JSON.parse(encoded[1]), payload);
  const sandbox = {};
  assert.equal(vm.runInNewContext(decode(encoded[2]), sandbox), payload);
  for (const quote of ["'", '"']) {
    assert.equal(vm.runInNewContext(quote + decode(encoded[3]) + quote, sandbox), payload);
  }
  assert.equal(sandbox.compromised, undefined);
  const html = security.escapeHTML(payload);
  assert(!/[<>"'`]/.test(html));
  // The receipt pipeline crosses a script context followed by an HTML context.
  assert.equal(vm.runInNewContext('`<strong>${escape(' + encoded[1] + ')}</strong>`', {escape: security.escapeHTML}), '<strong>' + html + '</strong>');
}
for (const url of ['javascript:alert(1)', 'data:text/html,<script>alert(1)</script>', 'java\nscript:alert(1)']) assert.equal(security.safeURL(url), '#');
assert.equal(security.safeURL('payments?id=1'), 'https://example.test/frontend/payments?id=1');
assert.equal(security.safeURL('https://example.test/a'), 'https://example.test/a');

// Verify AI Concierge widget safe Markdown renderer strips XSS and unsafe links
const widgetSrc = fs.readFileSync('frontend/assets/js/components/chatbot-widget.js', 'utf8');
const fnMatch = widgetSrc.match(/function sfbcEscapeHtml[\s\S]*?window\.sfbcFormatSafeReply\s*=\s*sfbcFormatSafeReply;/);
assert(fnMatch, 'sfbcFormatSafeReply must be present in chatbot-widget.js');
const chatCtx = { window: {} };
vm.runInNewContext(fnMatch[0], chatCtx);
for (const payload of payloads) {
  const rendered = chatCtx.window.sfbcFormatSafeReply(payload).replace(/<br>/g, '');
  assert(!/[<>]/.test(rendered), 'No raw angle brackets may survive in escaped payload output');
}
assert(!/javascript:/i.test(chatCtx.window.sfbcFormatSafeReply('[Click](javascript:alert(1))')));
assert(/href="book\?room_type=beach_villa&amp;checkin=2026-12-20&amp;checkout=2026-12-22"/.test(
  chatCtx.window.sfbcFormatSafeReply('[Book Villa](book?room_type=beach_villa&checkin=2026-12-20&checkout=2026-12-22)')
));

console.log('XSS regression checks passed: HTML, script, event attributes, receipts, URL schemes, and AI Concierge markdown.');
