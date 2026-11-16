const fs = require('node:fs');
const vm = require('node:vm');
const assert = require('node:assert/strict');
const source = fs.readFileSync(require('node:path').join(__dirname, '../includes/footer.php'), 'utf8');
const start = source.indexOf('(function() {', source.indexOf('Focus Rift Pomodoro Timer Engine'));
const end = source.indexOf('/* ============ HTML5 Canvas Status Card', start);
const script = source.slice(start, end).replace(/<\?php[\s\S]*?\?>/g, 'fixture-csrf');

async function run(mode) {
  let now = 100000;
  let tick;
  const listeners = {};
  const store = new Map([
    ['lv_rift_end_time', String(now + 1000)], ['lv_rift_total_seconds', '900'],
    ['lv_rift_key', 'alpha'], ['lv_rift_token', 'fixture-token'],
  ]);
  const requests = [];
  class FormData { constructor() { this.values = new Map(); } append(k, v) { this.values.set(k, v); } }
  const document = {
    title: 'Lifevuww', visibilityState: 'visible',
    addEventListener(name, callback) { listeners[name] = callback; },
    getElementById() { return null; }, querySelectorAll() { return []; }, querySelector() { return null; },
  };
  const sandbox = {
    document, navigator: {}, console, FormData, alert(message) { throw new Error(message); },
    sessionStorage: { getItem: k => store.get(k) ?? null, setItem: (k,v) => store.set(k,String(v)), removeItem: k => store.delete(k) },
    setInterval(callback) { tick = callback; return 1; }, clearInterval() {}, setTimeout() {},
    Date: class extends Date { static now() { return now; } },
    fetch: async (url, options) => { requests.push({ url, token: options.body.values.get('session_token') }); return { json: async () => ({ success: true }) }; },
    innerWidth: 390, innerHeight: 844,
    lvRequestWakeLock() {}, lvReleaseWakeLock() {},
  };
  sandbox.window = sandbox;
  vm.runInNewContext(script, sandbox);
  listeners.DOMContentLoaded();
  now += 2000;
  if (mode === 'tick') await tick();
  else { await listeners.visibilitychange(); await Promise.resolve(); await Promise.resolve(); }
  assert.equal(requests.length, 1, `${mode}: complete timer must claim once`);
  assert.equal(requests[0].token, 'fixture-token', `${mode}: claim token must survive until sent`);
}
(async () => { await run('tick'); await run('visibility'); console.log('PASS: actual timer tick and visibility completion send the session token'); })().catch(error => { console.error(error); process.exitCode = 1; });
