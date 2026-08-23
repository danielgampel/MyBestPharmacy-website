import http from 'http';
import fs from 'fs';
import path from 'path';
import { fileURLToPath } from 'url';

const __dirname = path.dirname(fileURLToPath(import.meta.url));
const PORT = 3000;

const MIME = {
  '.html': 'text/html',
  '.css':  'text/css',
  '.js':   'application/javascript',
  '.mjs':  'application/javascript',
  '.json': 'application/json',
  '.png':  'image/png',
  '.jpg':  'image/jpeg',
  '.jpeg': 'image/jpeg',
  '.gif':  'image/gif',
  '.svg':  'image/svg+xml',
  '.ico':  'image/x-icon',
  '.woff': 'font/woff',
  '.woff2':'font/woff2',
  '.ttf':  'font/ttf',
};

function serveFile(filePath, res) {
  const ext = path.extname(filePath).toLowerCase();
  const contentType = MIME[ext] || 'application/octet-stream';
  fs.readFile(filePath, (err, data) => {
    if (err) {
      res.writeHead(404, { 'Content-Type': 'text/plain' });
      res.end('404 Not Found');
      return;
    }
    res.writeHead(200, { 'Content-Type': contentType });
    res.end(data);
  });
}

/**
 * Dev-only stand-in for quiz-lead.php. Production runs PHP on SiteGround; there's
 * no PHP locally, so mirror the endpoint's contract and print the payload instead
 * of mailing it. This file is excluded from deploy, so it never ships.
 */
function handleQuizLead(req, res) {
  let body = '';
  req.on('data', chunk => { body += chunk; });
  req.on('end', () => {
    let payload;
    try {
      payload = JSON.parse(body);
    } catch {
      res.writeHead(400, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: false, error: 'We could not read that submission. Please try again.' }));
      return;
    }

    // Same rejections quiz-lead.php applies, so the UI's error paths are testable.
    // ⚠️ Field rules mirrored from quiz-lead.php and wellness.html. Keep all three in step.
    const LINK_RE = /(https?:\/\/|www\.)/i;
    const DOMAIN_TYPOS = {
      'gmial.com': 'gmail.com', 'gmai.com': 'gmail.com', 'gnail.com': 'gmail.com',
      'gmail.co': 'gmail.com', 'hotmial.com': 'hotmail.com', 'hotmai.com': 'hotmail.com',
      'yaho.com': 'yahoo.com', 'yahooo.com': 'yahoo.com', 'outlok.com': 'outlook.com',
      'iclould.com': 'icloud.com', 'icoud.com': 'icloud.com',
    };
    const TLD_TYPOS = { con: 'com', cmo: 'com', ocm: 'com', comm: 'com', xom: 'com', vom: 'com', met: 'net', ner: 'net', ogr: 'org' };

    const nameError = (raw) => {
      const v = String(raw || '').replace(/\s+/g, ' ').trim();
      if (!v) return 'Please enter your first and last name.';
      if (v.length > 60) return 'Please use 60 characters or fewer.';
      if (/\d/.test(v)) return "Names can't contain numbers.";
      if (LINK_RE.test(v)) return 'Please enter your name, not a web address.';
      if (!/^[\p{L}][\p{L}'’.\-\s]*$/u.test(v)) return 'Letters, hyphens and apostrophes only.';
      if (v.split(' ').filter(w => /\p{L}/u.test(w)).length < 2) return 'Please enter your first and last name.';
      return null;
    };
    const phoneError = (raw) => {
      let d = String(raw || '').replace(/\D/g, '');
      if (d.length === 11 && d[0] === '1') d = d.slice(1);
      d = d.slice(0, 10);
      if (!d) return 'Please enter your phone number.';
      if (d.length < 10) return 'We need all 10 digits.';
      if (!/^[2-9]\d{2}[2-9]\d{6}$/.test(d)) return "Check the area code. That isn't a US number.";
      return null;
    };
    const emailError = (raw) => {
      const v = String(raw || '').trim();
      if (!v) return 'Please enter your email address.';
      if (v.length > 180) return 'That email address is too long.';
      if (v.includes('..') || !/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,24}$/.test(v)) {
        return 'Please enter a valid email, like name@example.com.';
      }
      const at = v.lastIndexOf('@');
      const local = v.slice(0, at);
      const domain = v.slice(at + 1).toLowerCase();
      if (local.startsWith('.') || local.endsWith('.')) return 'Please enter a valid email, like name@example.com.';
      if (DOMAIN_TYPOS[domain]) return `Did you mean ${local}@${DOMAIN_TYPOS[domain]}?`;
      const dot = domain.lastIndexOf('.');
      const tld = domain.slice(dot + 1);
      if (TLD_TYPOS[tld]) return `Did you mean ${local}@${domain.slice(0, dot + 1)}${TLD_TYPOS[tld]}?`;
      return null;
    };
    const noteError = (raw) => {
      const v = String(raw || '').trim();
      if (!v) return null;
      if (v.length > 2000) return 'Please keep this under 2,000 characters.';
      if (LINK_RE.test(v)) return 'Please leave web links out of your note.';
      return null;
    };

    let error = null;
    if (String(payload.company || '').trim() !== '') {
      console.log('\n[quiz-lead] honeypot tripped, no mail sent');
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: true }));
      return;
    }
    if (Number(payload.elapsedMs || 0) < 3000) error = 'That went through a little too fast. Please try again.';
    else error = nameError(payload.name) || emailError(payload.email) || phoneError(payload.phone) || noteError(payload.note);
    if (!error && payload.consent !== true) error = 'Please agree to share your answers so our pharmacist can prepare your plan.';
    if (!error && (!Array.isArray(payload.plan) || payload.plan.length === 0)) error = 'Your plan did not come through. Please retake the quiz.';

    if (error) {
      console.log(`\n[quiz-lead] rejected: ${error}`);
      res.writeHead(400, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: false, error }));
      return;
    }

    const codes = { magnesium: 'MAG', b12: 'B12', omega: 'OMEGA', k2d3: 'K2D3', dim: 'DIM' };
    const order = ['magnesium', 'b12', 'omega', 'k2d3', 'dim'];
    const keys = payload.plan.map(p => p.key);
    const planCode = order.filter(k => keys.includes(k)).map(k => codes[k]).join('-');

    console.log('\n──────────── quiz-lead submission ────────────');
    console.log(`  ${payload.name}  ·  ${payload.email}  ·  ${payload.phone}`);
    console.log(`  Tier: ${payload.tier}   Plan code: ${planCode}`);
    if (payload.note) console.log(`  Note: ${payload.note}`);
    console.log('  Send in WholeScripts:');
    payload.plan.forEach(p => console.log(`    ☐ ${p.brand}  · ${p.name}`));
    const declined = Array.isArray(payload.declined) ? payload.declined.filter(d => !keys.includes(d.key)) : [];
    if (declined.length) console.log(`  Also suggested, patient skipped: ${declined.map(d => d.name).join(', ')}`);
    console.log('  Answers:');
    (payload.answers || []).forEach(a => console.log(`    ${a.question}\n      → ${a.answer}`));
    console.log('──────────────────────────────────────────────\n');

    res.writeHead(200, { 'Content-Type': 'application/json' });
    res.end(JSON.stringify({ ok: true }));
  });
}

const server = http.createServer((req, res) => {
  let urlPath = req.url.split('?')[0];
  if (urlPath === '/') urlPath = '/index.html';

  if (urlPath === '/quiz-lead.php') {
    if (req.method !== 'POST') {
      res.writeHead(405, { 'Content-Type': 'application/json', 'Allow': 'POST' });
      res.end(JSON.stringify({ ok: false, error: 'Method not allowed.' }));
      return;
    }
    handleQuizLead(req, res);
    return;
  }

  const filePath = path.join(__dirname, urlPath);

  // Mirror the production .htaccess clean-URL behavior: if the path has no
  // extension and doesn't exist as-is, try it with .html appended.
  if (!path.extname(filePath) && !fs.existsSync(filePath)) {
    const htmlPath = `${filePath}.html`;
    if (fs.existsSync(htmlPath)) {
      serveFile(htmlPath, res);
      return;
    }
  }

  serveFile(filePath, res);
});

server.listen(PORT, () => {
  console.log(`Server running at http://localhost:${PORT}`);
});
