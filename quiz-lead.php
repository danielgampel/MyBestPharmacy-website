<?php
/**
 * quiz-lead.php — wellness quiz handoff endpoint.
 *
 * Receives a completed wellness-quiz submission from wellness.html and sends two emails:
 *   1. An intake to the pharmacy, formatted so the pharmacist can send the matching
 *      WholeScripts recommendation without re-reading the whole thing.
 *   2. A copy to the patient, setting expectations for the WholeScripts email that follows.
 *
 * Self-hosted on purpose: quiz answers are health information, so no third-party form
 * vendor sits in the middle. Nothing is stored on disk except a rate-limit counter.
 */

// ─────────────────────────────────────────────────────────────────────────────
// Configuration
// ─────────────────────────────────────────────────────────────────────────────

/** Where quiz intakes land. MUST be set before this goes live. */
const PHARMACY_INBOX = 'admin@mybest-pharmacy.com';

/** Envelope sender. Must exist as a real mailbox on the hosting account, or
 *  Gmail and Outlook will treat both messages as forged. */
const FROM_ADDRESS = 'wellness@mybest-pharmacy.com';
const FROM_NAME    = 'My Best Pharmacy';

/** Absolute URLs only — email clients have no page to resolve relative paths against. */
const SITE_URL  = 'https://mybest-pharmacy.com';
const LOGO_URL  = SITE_URL . '/brand_assets/email-logo.png';

/**
 * Registering through this URL ties the patient's WholeScripts account to the
 * pharmacy. Offered as a head start rather than the main path: the recommendation
 * the pharmacist sends carries its own registration link with the products already
 * attached, so a patient who signs up cold here would land on an empty catalog.
 * Included deliberately so the flow still works if recommendations turn out to
 * require an existing account.
 */
const WS_REGISTER = 'https://www.wholescripts.com/register/mybestpharmacy';

const PHARMACY_PHONE   = '(561) 200-4245';
const PHARMACY_TEL     = '5612004245';
const PHARMACY_ADDRESS = '1050 E Gateway Blvd, Suite 101, Boynton Beach, FL 33426';
const PHARMACY_HOURS   = 'Mon-Fri 9 AM - 6 PM &middot; Sat 9 AM - 2 PM &middot; Sun Closed';

/** Brand tokens (BUSINESS.md). Inlined below — email clients drop <style> blocks. */
const C_GREEN_DEEP = '#1B5226';
const C_GREEN_DARK = '#0B3016';
const C_ACCENT     = '#6ABF4B';
const C_ACCENT_DK  = '#4E9035';
const C_SURFACE    = '#F8F9FA';
const C_BORDER     = '#D8E4F0';
const C_TEXT_MUTED = '#3D5570';

/** Supplement keys the quiz can recommend, mapped to short codes for the plan code.
 *  Doubles as a whitelist — anything else in the payload is discarded. */
const PLAN_KEYS = [
    'magnesium' => 'MAG',
    'b12'       => 'B12',
    'omega'     => 'OMEGA',
    'k2d3'      => 'K2D3',
    'dim'       => 'DIM',
];

const MAX_FIELD_LEN   = 400;
const MAX_NOTE_LEN    = 2000;
const NAME_MAX        = 60;
const EMAIL_MAX       = 180;

/** Anything that looks like a link. Spam is the only thing that puts one in a name. */
const LINK_RE = '/(https?:\/\/|www\.)/i';

/** Unambiguous misspellings of the big providers only — a real domain must never
 *  land in here, because a match is rejected rather than merely flagged. */
const DOMAIN_TYPOS = [
    'gmial.com'   => 'gmail.com',   'gmai.com'    => 'gmail.com',
    'gnail.com'   => 'gmail.com',   'gmail.co'    => 'gmail.com',
    'hotmial.com' => 'hotmail.com', 'hotmai.com'  => 'hotmail.com',
    'yaho.com'    => 'yahoo.com',   'yahooo.com'  => 'yahoo.com',
    'outlok.com'  => 'outlook.com', 'iclould.com' => 'icloud.com',
    'icoud.com'   => 'icloud.com',
];
const TLD_TYPOS = [
    'con' => 'com', 'cmo' => 'com', 'ocm' => 'com', 'comm' => 'com',
    'xom' => 'com', 'vom' => 'com', 'met' => 'net', 'ner'  => 'net',
    'ogr' => 'org',
];
const MAX_ANSWERS     = 40;
const MAX_PLAN_ITEMS  = 10;
const MIN_ELAPSED_MS  = 3000;   // Results rendered less than 3s ago == not a human.
const RATE_LIMIT_MAX  = 5;      // Submissions per IP...
const RATE_LIMIT_WIN  = 3600;   // ...per hour.

date_default_timezone_set('America/New_York');

// ─────────────────────────────────────────────────────────────────────────────
// Helpers
// ─────────────────────────────────────────────────────────────────────────────

function respond(int $status, array $body): void
{
    http_response_code($status);
    header('Content-Type: application/json; charset=utf-8');
    header('Cache-Control: no-store');
    echo json_encode($body);
    exit;
}

function fail(string $message): void
{
    respond(400, ['ok' => false, 'error' => $message]);
}

/** Collapse whitespace and clamp length. Everything from the browser passes through here. */
function clean($value, int $max = MAX_FIELD_LEN): string
{
    if (!is_string($value)) {
        return '';
    }
    $value = str_replace(["\r", "\0"], '', $value);
    $value = trim(preg_replace('/[ \t]+/', ' ', $value));
    return mb_substr($value, 0, $max);
}

/** Strip anything that could open a new header line. Use for every header value. */
function headerSafe(string $value): string
{
    return trim(preg_replace('/[\r\n]+/', ' ', $value));
}

/**
 * A person's name as an RFC 5322 display name. Angle brackets come out entirely —
 * a name like `Dana <script>` would otherwise leave the mail client reading
 * "script" as the reply address — and the rest is quoted so commas and periods
 * cannot split the header either.
 */
function displayName(string $name): string
{
    $name = headerSafe(str_replace(['<', '>'], '', $name));
    return '"' . str_replace(['\\', '"'], ['\\\\', '\\"'], $name) . '"';
}

function esc(string $value): string
{
    return htmlspecialchars($value, ENT_QUOTES | ENT_SUBSTITUTE, 'UTF-8');
}

function encodeSubject(string $subject): string
{
    $subject = headerSafe($subject);
    if (function_exists('mb_encode_mimeheader')) {
        return mb_encode_mimeheader($subject, 'UTF-8', 'B');
    }
    return $subject;
}

function clientIp(): string
{
    $ip = $_SERVER['REMOTE_ADDR'] ?? 'unknown';
    return filter_var($ip, FILTER_VALIDATE_IP) ? $ip : 'unknown';
}

/**
 * Best-effort per-IP throttle. Uses the system temp dir, which may be wiped between
 * requests on some hosts — that is acceptable, this is a speed bump, not a gate.
 */
function rateLimited(string $ip): bool
{
    $path = sys_get_temp_dir() . '/mbp-quiz-lead-' . sha1($ip) . '.json';
    $now  = time();

    $handle = @fopen($path, 'c+');
    if ($handle === false) {
        return false;
    }

    $limited = false;
    if (flock($handle, LOCK_EX)) {
        $raw   = stream_get_contents($handle);
        $stamps = json_decode($raw ?: '[]', true);
        if (!is_array($stamps)) {
            $stamps = [];
        }
        $stamps = array_values(array_filter($stamps, static fn($t) => is_int($t) && $t > $now - RATE_LIMIT_WIN));

        if (count($stamps) >= RATE_LIMIT_MAX) {
            $limited = true;
        } else {
            $stamps[] = $now;
        }

        ftruncate($handle, 0);
        rewind($handle);
        fwrite($handle, json_encode($stamps));
        fflush($handle);
        flock($handle, LOCK_UN);
    }
    fclose($handle);

    return $limited;
}

/* ─────────────────────────────────────────────────────────────────────────────
 * Field rules
 *
 * ⚠️ Mirrored in wellness.html (checkName / checkPhone / checkEmail / checkNote)
 * and in serve.mjs's dev shim. The browser copy exists so people get told about a
 * typo while they can still see the field; this copy is the one that decides.
 * Change a rule in one place, change all three.
 *
 * Each returns the normalized value or ends the request with a message the
 * patient can act on. Normalizing here means the pharmacist reads
 * "(561) 200-4245" no matter how it was typed, pasted, or autofilled.
 * ────────────────────────────────────────────────────────────────────────────*/

function requireName(string $raw): string
{
    $v = trim(preg_replace('/\s+/', ' ', $raw));
    if ($v === '') {
        fail('Please enter your first and last name.');
    }
    if (mb_strlen($v) > NAME_MAX) {
        fail('Please use 60 characters or fewer.');
    }
    if (preg_match('/\d/', $v)) {
        fail("Names can't contain numbers.");
    }
    if (preg_match(LINK_RE, $v)) {
        fail('Please enter your name, not a web address.');
    }
    if (!preg_match('/^[\p{L}][\p{L}\'’.\-\s]*$/u', $v)) {
        fail('Letters, hyphens and apostrophes only.');
    }
    $words = array_filter(explode(' ', $v), static fn($w) => preg_match('/\p{L}/u', $w) === 1);
    if (count($words) < 2) {
        fail('Please enter your first and last name.');
    }
    return $v;
}

function requirePhone(string $raw): string
{
    $d = preg_replace('/\D/', '', $raw);
    if (strlen($d) === 11 && $d[0] === '1') {
        $d = substr($d, 1);          // country code from autofill
    }
    $d = substr($d, 0, 10);
    if ($d === '') {
        fail('Please enter your phone number.');
    }
    if (strlen($d) < 10) {
        fail('We need all 10 digits.');
    }
    // North American numbering: area code and exchange both start 2–9.
    if (!preg_match('/^[2-9]\d{2}[2-9]\d{6}$/', $d)) {
        fail("Check the area code — that isn't a US number.");
    }
    return '(' . substr($d, 0, 3) . ') ' . substr($d, 3, 3) . '-' . substr($d, 6, 4);
}

function requireEmail(string $raw): string
{
    $v = trim($raw);
    if ($v === '') {
        fail('Please enter your email address.');
    }
    if (strlen($v) > EMAIL_MAX) {
        fail('That email address is too long.');
    }
    $shape = '/^[A-Za-z0-9._%+-]+@[A-Za-z0-9-]+(\.[A-Za-z0-9-]+)*\.[A-Za-z]{2,24}$/';
    if (strpos($v, '..') !== false || !preg_match($shape, $v) || !filter_var($v, FILTER_VALIDATE_EMAIL)) {
        fail('Please enter a valid email, like name@example.com.');
    }
    // Past the shape check the local part is known to be [A-Za-z0-9._%+-] only,
    // so echoing it back in a suggestion is safe.
    $at     = strrpos($v, '@');
    $local  = substr($v, 0, $at);
    $domain = strtolower(substr($v, $at + 1));
    if ($local[0] === '.' || substr($local, -1) === '.') {
        fail('Please enter a valid email, like name@example.com.');
    }
    if (array_key_exists($domain, DOMAIN_TYPOS)) {
        fail('Did you mean ' . $local . '@' . DOMAIN_TYPOS[$domain] . '?');
    }
    $dot = strrpos($domain, '.');
    $tld = substr($domain, $dot + 1);
    if (array_key_exists($tld, TLD_TYPOS)) {
        fail('Did you mean ' . $local . '@' . substr($domain, 0, $dot + 1) . TLD_TYPOS[$tld] . '?');
    }
    // Domain lowercased; the local part is left alone — technically it is
    // case-sensitive and not ours to rewrite.
    return $local . '@' . $domain;
}

/** Optional. Empty is fine; filled in still has rules. */
function cleanNote(string $raw): string
{
    $v = trim($raw);
    if ($v === '') {
        return '';
    }
    if (mb_strlen($v) > MAX_NOTE_LEN) {
        fail('Please keep this under 2,000 characters.');
    }
    if (preg_match(LINK_RE, $v)) {
        fail('Please leave web links out of your note.');
    }
    return $v;
}

/**
 * Whitelist and sanitize a plan array from the browser. Both the chosen plan and
 * the declined list come through here, so an unknown key can never reach an email
 * from either direction.
 *
 * @return array{0: array<int, array<string, string>>, 1: array<string, string>} [items, codes]
 */
function parsePlanArray($raw): array
{
    $items = [];
    $codes = [];
    if (!is_array($raw)) {
        return [$items, $codes];
    }
    foreach (array_slice($raw, 0, MAX_PLAN_ITEMS) as $item) {
        if (!is_array($item)) {
            continue;
        }
        $key = clean($item['key'] ?? '', 40);
        if (!array_key_exists($key, PLAN_KEYS)) {
            continue;
        }
        $codes[$key] = PLAN_KEYS[$key];
        $items[] = [
            'key'      => $key,
            'name'     => clean($item['name'] ?? '', 120),
            'supports' => clean($item['supports'] ?? '', 200),
            'brand'    => clean($item['brand'] ?? '', 200),
        ];
    }
    return [$items, $codes];
}

function sendHtmlMail(string $to, string $subject, string $html, string $replyTo = ''): bool
{
    $headers = [
        'MIME-Version: 1.0',
        'Content-Type: text/html; charset=UTF-8',
        'Content-Transfer-Encoding: 8bit',
        'From: ' . headerSafe(FROM_NAME) . ' <' . headerSafe(FROM_ADDRESS) . '>',
        'X-Mailer: mybest-pharmacy-quiz',
    ];
    if ($replyTo !== '') {
        $headers[] = 'Reply-To: ' . headerSafe($replyTo);
    }

    return @mail(
        headerSafe($to),
        encodeSubject($subject),
        $html,
        implode("\r\n", $headers),
        '-f' . headerSafe(FROM_ADDRESS)
    );
}

// ─────────────────────────────────────────────────────────────────────────────
// Email templates
// ─────────────────────────────────────────────────────────────────────────────

function emailShell(string $preheader, string $inner): string
{
    return '<!doctype html><html><head><meta charset="utf-8">'
        . '<meta name="viewport" content="width=device-width,initial-scale=1"></head>'
        . '<body style="margin:0;padding:0;background:' . C_SURFACE . ';">'
        . '<div style="display:none;max-height:0;overflow:hidden;opacity:0;">' . esc($preheader) . '</div>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" style="background:' . C_SURFACE . ';">'
        . '<tr><td align="center" style="padding:26px 14px;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
        . 'style="max-width:600px;background:#ffffff;border-radius:16px;overflow:hidden;'
        . 'border:1px solid ' . C_BORDER . ';font-family:Helvetica,Arial,sans-serif;">'
        . $inner
        . '</table></td></tr></table></body></html>';
}

function emailHeaderBar(string $eyebrow, string $title): string
{
    // The logo is green artwork, so it sits on its own white band rather than on
    // the dark green bar. Alt text carries the name when images are blocked,
    // which is the default in Outlook and in Gmail for unknown senders.
    return '<tr><td align="center" style="background:#ffffff;padding:22px 30px 18px;">'
        . '<img src="' . LOGO_URL . '" alt="My Best Pharmacy" width="200" '
        . 'style="display:block;border:0;outline:none;text-decoration:none;'
        . 'width:200px;max-width:62%;height:auto;">'
        . '</td></tr>'
        . '<tr><td style="background:' . C_GREEN_DEEP . ';padding:26px 30px;">'
        . '<p style="margin:0;font-size:11px;font-weight:bold;letter-spacing:0.14em;'
        . 'text-transform:uppercase;color:' . C_ACCENT . ';">' . esc($eyebrow) . '</p>'
        . '<p style="margin:8px 0 0;font-size:22px;font-weight:bold;color:#ffffff;line-height:1.3;">'
        . esc($title) . '</p></td></tr>';
}

/** The pharmacist's working document: contact, what to send, then the evidence. */
function pharmacyEmail(array $d): string
{
    $inner = emailHeaderBar('Wellness quiz submission', $d['name']);

    // Contact — first because it is what gets acted on.
    $inner .= '<tr><td style="padding:24px 30px 0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
        . 'style="background:' . C_SURFACE . ';border:1px solid ' . C_BORDER . ';border-radius:12px;">'
        . '<tr><td style="padding:16px 18px;font-size:15px;color:' . C_GREEN_DARK . ';line-height:1.8;">'
        . '<strong>Email</strong> &nbsp;<a href="mailto:' . esc($d['email']) . '" style="color:' . C_ACCENT_DK . ';">' . esc($d['email']) . '</a><br>'
        . '<strong>Phone</strong> &nbsp;<a href="tel:' . esc(preg_replace('/\D/', '', $d['phone'])) . '" style="color:' . C_ACCENT_DK . ';">' . esc($d['phone']) . '</a><br>'
        . '<strong>Submitted</strong> &nbsp;' . esc($d['submitted'])
        . '</td></tr></table></td></tr>';

    // The action block.
    $items = '';
    foreach ($d['plan'] as $item) {
        $items .= '<tr><td style="padding:9px 0;border-bottom:1px solid ' . C_BORDER . ';font-size:15px;color:' . C_GREEN_DARK . ';">'
            . '&#9744;&nbsp;&nbsp;<strong>' . esc($item['brand']) . '</strong>'
            . '<span style="color:' . C_TEXT_MUTED . ';font-size:13px;"> &nbsp;&mdash; ' . esc($item['name']) . '</span>'
            . '</td></tr>';
    }

    $inner .= '<tr><td style="padding:22px 30px 0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
        . 'style="border:2px solid ' . C_ACCENT . ';border-radius:12px;">'
        . '<tr><td style="padding:18px 20px;">'
        . '<p style="margin:0;font-size:11px;font-weight:bold;letter-spacing:0.13em;text-transform:uppercase;color:' . C_ACCENT_DK . ';">'
        . 'Send these in WholeScripts</p>'
        . '<p style="margin:6px 0 12px;font-size:13px;color:' . C_TEXT_MUTED . ';">'
        . esc($d['tierLabel']) . ' tier &middot; plan code <strong style="color:' . C_GREEN_DARK . ';">' . esc($d['planCode']) . '</strong></p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $items . '</table>'
        . '<p style="margin:14px 0 0;font-size:13px;color:' . C_TEXT_MUTED . ';line-height:1.6;">'
        . 'Send to <strong>' . esc($d['email']) . '</strong> with referral code <strong>mybestpharmacy</strong>. '
        . 'They have no WholeScripts account yet unless they told you otherwise.</p>';

    // What they turned down. Worth knowing before the call, but it is not part of
    // the order, so it sits quietly under the checklist.
    if ($d['skipped'] !== '') {
        $inner .= '<p style="margin:10px 0 0;font-size:13px;color:' . C_TEXT_MUTED . ';line-height:1.6;">'
            . 'Also suggested, patient skipped: <strong>' . esc($d['skipped']) . '</strong>.</p>';
    }

    $inner .= '</td></tr></table></td></tr>';

    if ($d['note'] !== '') {
        $inner .= '<tr><td style="padding:22px 30px 0;">'
            . '<p style="margin:0 0 6px;font-size:11px;font-weight:bold;letter-spacing:0.13em;text-transform:uppercase;color:' . C_ACCENT_DK . ';">In their words</p>'
            . '<p style="margin:0;padding:14px 16px;background:' . C_SURFACE . ';border-radius:10px;font-size:15px;'
            . 'color:' . C_GREEN_DARK . ';line-height:1.65;white-space:pre-wrap;">' . esc($d['note']) . '</p></td></tr>';
    }

    // Full answers — reference material, so it comes last.
    $rows = '';
    foreach ($d['answers'] as $qa) {
        $rows .= '<tr><td style="padding:10px 0;border-bottom:1px solid ' . C_BORDER . ';">'
            . '<p style="margin:0;font-size:13px;color:' . C_TEXT_MUTED . ';">' . esc($qa['question']) . '</p>'
            . '<p style="margin:3px 0 0;font-size:15px;font-weight:bold;color:' . C_GREEN_DARK . ';">' . esc($qa['answer']) . '</p>'
            . '</td></tr>';
    }

    $inner .= '<tr><td style="padding:22px 30px 26px;">'
        . '<p style="margin:0 0 4px;font-size:11px;font-weight:bold;letter-spacing:0.13em;text-transform:uppercase;color:' . C_ACCENT_DK . ';">Their answers</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $rows . '</table></td></tr>';

    return emailShell($d['name'] . ' - ' . $d['planCode'], $inner);
}

/** The patient's copy: their plan, then what to expect so the WholeScripts email lands warm. */
function patientEmail(array $d): string
{
    $first = explode(' ', $d['name'])[0];

    $inner = emailHeaderBar('Your personalized plan', 'Thanks, ' . $first . ' — here is your plan');

    $inner .= '<tr><td style="padding:24px 30px 0;">'
        . '<p style="margin:0;font-size:16px;line-height:1.7;color:' . C_GREEN_DARK . ';">'
        . 'Based on your answers, these are the supplements our program suggests as a starting point.</p></td></tr>';

    $items = '';
    foreach ($d['plan'] as $item) {
        $items .= '<tr><td style="padding:14px 16px;background:' . C_SURFACE . ';border:1px solid ' . C_BORDER . ';'
            . 'border-radius:12px;font-size:16px;color:' . C_GREEN_DARK . ';">'
            . '<strong>' . esc($item['name']) . '</strong><br>'
            . '<span style="font-size:14px;color:' . C_TEXT_MUTED . ';">' . esc($item['supports']) . '</span><br>'
            . '<span style="font-size:13px;color:' . C_TEXT_MUTED . ';">' . esc($item['brand']) . '</span>'
            . '</td></tr><tr><td style="height:10px;line-height:10px;">&nbsp;</td></tr>';
    }
    $inner .= '<tr><td style="padding:18px 30px 0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $items . '</table></td></tr>';

    // If they asked us something, say so before anything else — otherwise the
    // email reads like nobody looked at it.
    if ($d['note'] !== '') {
        $inner .= '<tr><td style="padding:18px 30px 14px;">'
            . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
            . 'style="border-left:3px solid ' . C_ACCENT . ';"><tr>'
            . '<td style="padding:2px 0 2px 14px;">'
            . '<p style="margin:0;font-size:14px;color:' . C_TEXT_MUTED . ';">You told us:</p>'
            . '<p style="margin:4px 0 0;font-size:15px;color:' . C_GREEN_DARK . ';line-height:1.65;white-space:pre-wrap;">'
            . esc($d['note']) . '</p>'
            . '<p style="margin:8px 0 0;font-size:14px;color:' . C_TEXT_MUTED . ';line-height:1.6;">'
            . 'Your pharmacist will go over that when reviewing your plan.</p>'
            . '</td></tr></table></td></tr>';
    }

    // Three steps, in the order they will actually happen.
    $steps = [
        ['A pharmacist reviews your plan', 'We check it against your hormone therapy, prescriptions, and anything else you take, then set how much of each one you take and when.'],
        ['WholeScripts emails you', 'WholeScripts is the practitioner-grade dispensary we order through &mdash; it is not sold in stores. Look for the subject line <strong>My Best Pharmacy sent you a recommendation</strong>. That one is from us.'],
        ['Open it and check out', 'Your supplements are already in the cart, with your pharmacist\'s directions for taking them. Create your free account right there and order.'],
    ];

    $stepRows = '';
    $n = 0;
    foreach ($steps as [$title, $body]) {
        $n++;
        $stepRows .= '<tr>'
            . '<td width="34" valign="top" style="padding:0 12px 18px 0;">'
            . '<table role="presentation" cellpadding="0" cellspacing="0" border="0"><tr>'
            . '<td width="30" height="30" align="center" valign="middle" style="background:' . C_ACCENT . ';border-radius:15px;'
            . 'font-size:14px;font-weight:bold;color:#ffffff;">' . $n . '</td></tr></table></td>'
            . '<td valign="top" style="padding:0 0 18px;">'
            . '<p style="margin:0;font-size:16px;font-weight:bold;color:' . C_GREEN_DARK . ';line-height:1.4;">' . esc($title) . '</p>'
            . '<p style="margin:4px 0 0;font-size:14px;color:' . C_TEXT_MUTED . ';line-height:1.65;">' . $body . '</p>'
            . '</td></tr>';
    }

    $inner .= '<tr><td style="padding:12px 30px 0;">'
        . '<p style="margin:0 0 14px;font-size:11px;font-weight:bold;letter-spacing:0.13em;text-transform:uppercase;color:' . C_ACCENT_DK . ';">What happens next</p>'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0">' . $stepRows . '</table></td></tr>';

    // Optional head start. Deliberately quieter than the three steps above — the
    // recommendation is the path that arrives with products attached.
    $inner .= '<tr><td style="padding:4px 30px 0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
        . 'style="border:1px solid ' . C_BORDER . ';border-radius:12px;"><tr><td style="padding:16px 18px;">'
        . '<p style="margin:0;font-size:15px;font-weight:bold;color:' . C_GREEN_DARK . ';">Want to get a head start?</p>'
        . '<p style="margin:5px 0 12px;font-size:14px;line-height:1.65;color:' . C_TEXT_MUTED . ';">'
        . 'Create your free WholeScripts account through our pharmacy now &mdash; it takes about a minute, '
        . 'and your recommendation will be waiting in it. You do not have to do this first; '
        . 'the email above works on its own.</p>'
        . '<a href="' . WS_REGISTER . '" style="display:inline-block;padding:11px 22px;background:' . C_ACCENT . ';'
        . 'color:#ffffff;font-size:15px;font-weight:bold;text-decoration:none;border-radius:9px;">'
        . 'Create my free account</a>'
        . '</td></tr></table></td></tr>';

    $inner .= '<tr><td style="padding:14px 30px 0;">'
        . '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" border="0" '
        . 'style="background:' . C_SURFACE . ';border-radius:12px;"><tr><td style="padding:16px 18px;">'
        . '<p style="margin:0;font-size:15px;line-height:1.7;color:' . C_GREEN_DARK . ';">'
        . '<strong>Nothing is charged until you check out yourself.</strong> You will see the full price '
        . 'before you order, and you can drop anything you are not ready for.</p>'
        . '<p style="margin:10px 0 0;font-size:15px;line-height:1.7;color:' . C_GREEN_DARK . ';">'
        . 'Want the cost up front, have a question, or would rather order over the phone? Call us at '
        . '<a href="tel:' . PHARMACY_TEL . '" style="color:' . C_ACCENT_DK . ';font-weight:bold;">' . PHARMACY_PHONE . '</a>.</p>'
        . '</td></tr></table></td></tr>';

    $inner .= '<tr><td style="padding:24px 30px 26px;">'
        . '<p style="margin:0;font-size:12px;color:' . C_TEXT_MUTED . ';line-height:1.65;">'
        . 'These are supplement suggestions based on your quiz answers, not medical advice. '
        . 'Always consult your physician for guidance specific to your health needs.</p>'
        . '<p style="margin:14px 0 0;font-size:12px;color:' . C_TEXT_MUTED . ';line-height:1.65;">'
        . '<strong style="color:' . C_GREEN_DARK . ';">My Best Pharmacy</strong><br>'
        . PHARMACY_ADDRESS . '<br>' . PHARMACY_HOURS . '</p></td></tr>';

    return emailShell('Your supplement plan and what happens next', $inner);
}

// ─────────────────────────────────────────────────────────────────────────────
// Request handling
// ─────────────────────────────────────────────────────────────────────────────

if (($_SERVER['REQUEST_METHOD'] ?? '') !== 'POST') {
    header('Allow: POST');
    respond(405, ['ok' => false, 'error' => 'Method not allowed.']);
}

$payload = json_decode(file_get_contents('php://input') ?: '', true);
if (!is_array($payload)) {
    fail('We could not read that submission. Please try again.');
}

// Honeypot: a real person never fills this in. Look successful, send nothing.
if (clean($payload['company'] ?? '') !== '') {
    respond(200, ['ok' => true]);
}

if ((int) ($payload['elapsedMs'] ?? 0) < MIN_ELAPSED_MS) {
    fail('That went through a little too fast. Please try again.');
}

// clean() first (CRLF, control characters, absurd lengths), then the field rules.
// The note gets a roomier cap so an over-long one is reported rather than
// silently truncated mid-sentence.
$name  = requireName(clean($payload['name'] ?? '', 200));
$email = requireEmail(clean($payload['email'] ?? '', 300));
$phone = requirePhone(clean($payload['phone'] ?? '', 40));
$note  = cleanNote(clean($payload['note'] ?? '', MAX_NOTE_LEN * 2));

if (($payload['consent'] ?? false) !== true) {
    fail('Please agree to share your answers so our pharmacist can prepare your plan.');
}

// Plan. Keys are whitelisted; display strings are the quiz's own, escaped on output.
// The patient picks which recommendations they actually want, so this is what they
// kept — never the full set the quiz produced.
[$plan, $codes] = parsePlanArray($payload['plan'] ?? null);
if (!$plan) {
    fail('Your plan did not come through. Please retake the quiz.');
}

// What was recommended and turned down. Context for the pharmacist only — it never
// reaches the patient's copy, and it never affects the plan code.
[$declined] = parsePlanArray($payload['declined'] ?? null);
$declined = array_values(array_filter($declined, static fn($i) => !isset($codes[$i['key']])));

// Canonical order, so the same set always produces the same code.
$planCode = implode('-', array_values(array_intersect_key(PLAN_KEYS, $codes)));

$tier      = clean($payload['tier'] ?? '', 20);
$tierLabel = $tier === 'essential' ? 'Essential' : 'Premium';

$rawAnswers = is_array($payload['answers'] ?? null) ? array_slice($payload['answers'], 0, MAX_ANSWERS) : [];
$answers = [];
foreach ($rawAnswers as $qa) {
    if (!is_array($qa)) {
        continue;
    }
    $question = clean($qa['question'] ?? '', 300);
    $answer   = clean($qa['answer'] ?? '', MAX_FIELD_LEN);
    if ($question !== '' && $answer !== '') {
        $answers[] = ['question' => $question, 'answer' => $answer];
    }
}

if (rateLimited(clientIp())) {
    respond(429, ['ok' => false, 'error' => 'We already have your submission. Please call us at ' . PHARMACY_PHONE . '.']);
}

$data = [
    'name'      => $name,
    'email'     => $email,
    'phone'     => $phone,
    'note'      => $note,
    'plan'      => $plan,
    'skipped'   => implode(', ', array_column($declined, 'name')),
    'planCode'  => $planCode,
    'tierLabel' => $tierLabel,
    'answers'   => $answers,
    'submitted' => date('M j, Y \a\t g:i A T'),
];

if (PHARMACY_INBOX === '') {
    error_log('quiz-lead.php: PHARMACY_INBOX is unset — submission from ' . $email . ' was not delivered.');
    respond(500, ['ok' => false, 'error' => 'We could not reach the pharmacy right now. Please call us at ' . PHARMACY_PHONE . '.']);
}

$intakeSent = sendHtmlMail(
    PHARMACY_INBOX,
    'Wellness quiz - ' . $name . ' - ' . count($plan) . ' supplement' . (count($plan) === 1 ? '' : 's'),
    pharmacyEmail($data),
    displayName($name) . ' <' . headerSafe($email) . '>'
);

if (!$intakeSent) {
    error_log('quiz-lead.php: intake mail() failed for ' . $email);
    respond(500, ['ok' => false, 'error' => 'We could not send that through. Please call us at ' . PHARMACY_PHONE . '.']);
}

// The patient copy is a courtesy — the pharmacy already has what it needs, so a
// failure here must not tell the patient their submission was lost.
if (!sendHtmlMail($email, 'Your personalized supplement plan from My Best Pharmacy', patientEmail($data))) {
    error_log('quiz-lead.php: patient copy failed for ' . $email);
}

respond(200, ['ok' => true]);
