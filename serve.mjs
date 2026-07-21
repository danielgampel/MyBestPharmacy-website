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
    const digits = String(payload.phone || '').replace(/\D/g, '');
    let error = null;
    if (String(payload.company || '').trim() !== '') {
      console.log('\n[quiz-lead] honeypot tripped — no mail sent');
      res.writeHead(200, { 'Content-Type': 'application/json' });
      res.end(JSON.stringify({ ok: true }));
      return;
    }
    if (Number(payload.elapsedMs || 0) < 3000) error = 'That went through a little too fast. Please try again.';
    else if (String(payload.name || '').trim().length < 2) error = 'Please enter your full name.';
    else if (!/^[^\s@]+@[^\s@]+\.[^\s@]{2,}$/.test(String(payload.email || ''))) error = 'Please enter a valid email address.';
    else if (digits.length < 10) error = 'Please enter a valid phone number.';
    else if (payload.consent !== true) error = 'Please agree to share your answers so our pharmacist can prepare your plan.';
    else if (!Array.isArray(payload.plan) || payload.plan.length === 0) error = 'Your plan did not come through. Please retake the quiz.';

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
    payload.plan.forEach(p => console.log(`    ☐ ${p.brand}  — ${p.name}`));
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
