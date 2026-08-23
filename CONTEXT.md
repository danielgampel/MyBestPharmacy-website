# CONTEXT.md: My Best Pharmacy Website
*Last updated: 2026-07-21. Allows a fresh Claude Code session to resume with zero context loss.*

> **Business facts** (address, hours, phone, business rules, brand palette) are canonical in
> [`../BUSINESS.md`](../BUSINESS.md). If anything below conflicts with that file, `BUSINESS.md` wins.

---

## Project Overview

A **6-page static informational website** for **My Best Pharmacy**, a full-service & compounding pharmacy in Boynton Beach, FL. Live in production at `mybest-pharmacy.com`.

- **Address:** 1050 E Gateway Blvd, Suite 101, Boynton Beach, FL 33426
- **Phone:** (561) 200-4245 · **Fax:** 561.200.4236
- **Hours:** Mon–Fri 9 AM–6 PM · Sat 9 AM–2 PM · Sun Closed
- **Audiences:** Patients (OTC, retail Rx, compounding), prescribers sending compound orders, people renting/buying DME

---

## Critical Business Rules
- Compounded medications: **cash-pay only, no insurance**
- Retail Rx (non-compounded): insurance accepted; **Express Scripts (Cigna/Evernorth) in-network**
- **Nations Benefits** Medicare Advantage OTC card accepted for OTC products and medical equipment
- Patients **must bring a physical paper Rx**, not paperless
- Medical equipment available for **rent AND purchase**; purchase prices TBD → display "Call for Pricing"
- **(561) 292-0423 no longer exists; removed from every page**

---

## Pages

| File | Title / URL | Key Sections |
|------|-------------|--------------|
| `index.html` | My Best Pharmacy | Full-Service Pharmacy | Two-column hero, 4 service cards, Equipment Rental banner, patient reviews carousel (mobile), Services Strip (desktop only), Accepted Programs, Provider Callout, Location & Hours + map, Footer |
| `services.html` | For Patients | Hero, How It Works (paper Rx, cash-pay compounding), 4 patient service cards, What Is Compounding, FAQ accordion, Accepted Programs, Provider CTA, Footer |
| `wellness.html` | Wellness Program | Wellness program page (added Jun 2026) |
| `equipment.html` | Medical Equipment Rental & Sales | Two-column hero, "Rent or Purchase" callout, 7 pricing table sections (monthly/bi-weekly/deposit + "Call for Pricing" purchase column), click-to-preview photo modal, CTA, Footer |
| `providers.html` | For Providers | Hero, How to Send Rx (Fax / Call / Contact cards), Compounds We Prepare (full formulary, 3 columns), CTA, Footer |
| `contact.html` | Contact | Hero, Contact + Map (2-column), Service Area note, Provider CTA Strip, Footer |

**Footer "Quick Links" on every page:** Home, For Patients, Equipment, Wellness Program, For Providers, Contact. Keep this list and its labels identical across all 6 pages (fixed Jul 2026; previously index/wellness were missing the Equipment link and mislabeled it "Services").

---

## Scripts

| File | Purpose |
|------|---------|
| `serve.mjs` | Static file server → `http://localhost:3000`. Also stubs `POST /quiz-lead.php` (there's no PHP locally); it applies the same validation and prints the payload instead of mailing it. Deploy-excluded. |
| `screenshot.mjs` | Puppeteer full-page screenshot → `temporary screenshots/screenshot-{N}-{label}.png` (1440×900) |
| `quiz-lead.php` | **Deploys to production.** Wellness-quiz handoff endpoint; see below. |

---

## Deployment

- **Live via GitHub Actions** (`.github/workflows/deploy.yml`): every push to `main` FTP-deploys the repo root to SiteGround (`mybest-pharmacy.com/public_html/`).
- Excluded from deploy: `.git*`, `node_modules/`, `fable/`, `temporary screenshots/`, `README.md`, `CLAUDE.md`, `CONTEXT.md`, `LEARNINGS.md`, `package*.json`, `serve.mjs`, `screenshot.mjs`, the `.code-workspace` file, `.DS_Store`.
- `robots.txt` + `sitemap.xml` are in place and list all 6 pages.
- **SEO package deployed** (Jul 16, 2026): meta descriptions, Open Graph tags, `schema.org` Pharmacy/LocalBusiness structured data, Google Business Profile CID link.
- **The wellness quiz has a form; no other page does**; the rest are informational only.

---

## Wellness Quiz → WholeScripts Handoff (`wellness.html` + `quiz-lead.php`)

> **Status (Jul 21 2026): the email handoff is DORMANT, reverted off the live page.** Dad found the
> pharmacist-emails-a-cart flow too manual, so `wellness.html` was reverted to the earlier **self-serve
> WholeScripts** experience: the results page shows a display-only plan (single Xymogen formula, no
> Essential tier, no checkboxes), each card names the exact WholeScripts product to search + a "Search
> on WholeScripts" button, and a "Join our pharmacy on WholeScripts" callout gives the register-then-
> order-it-yourself steps. The whole page also pivoted to a **no-subscription** message (order a month
> at a time; no auto-refill/auto-billing/"delivery included"). **The email code is intentionally kept,
> not deleted:** `quiz-lead.php`, the `serve.mjs` shim, and `email previews/` are untouched and the
> full form implementation is in git history (commit `cc63293`) if we ever re-enable it. Everything
> below describes that dormant email flow.

Added Jul 2026. The quiz used to end by sending patients to register at WholeScripts cold, which
dropped them on an unfamiliar catalog with an empty cart. It now hands the pharmacy a ready-to-send
intake instead.

**Why this shape:** WholeScripts lets a practitioner account send a recommendation to a patient's
email *even when that patient has no account yet*: the products arrive attached and the account is
created during checkout. There is no public WholeScripts API (automation runs only through paid EHR
partnerships: Practice Better, CharmHealth, OptiMantra), so the pharmacist sends it manually. The
website's job is to make that a ~90-second task.

**Flow:** quiz → plan shown (never gated) → **patient picks which supplements they actually want** →
handoff form → `POST /quiz-lead.php` → two emails → pharmacist opens WholeScripts and sends the
recommendation → patient registers and checks out.

**Why selection happens on the results page, not in the cart:** the engine returns up to 5 items and
often 4. Deleting them at checkout would be too late; the pharmacist would already have spent time
recommending things the patient never wanted, and "we curated this for you" breaks the moment the
cart is being emptied. Choosing up front means the recommendation that gets sent is already correct.

**There is deliberately no way to add a supplement the quiz didn't recommend.** DIM is safety-gated
in `RULES.dim.blocked` (never for males, estrogen or combination therapy, or alongside hot flashes);
an "add anything" control would route around that gate. Unchecking is the only edit offered.

**`quiz-lead.php` notes:**
- `PHARMACY_INBOX` is `admin@mybest-pharmacy.com`. If it is ever blanked the endpoint returns a 500
  rather than silently dropping submissions.
- `FROM_ADDRESS` (`wellness@mybest-pharmacy.com`) must exist as a real SiteGround mailbox or both
  messages get treated as forged.
- `plan[]` is **what the patient kept**, `declined[]` is what they turned down. Both go through the
  same `parsePlanArray()` whitelist; a key appearing in both is dropped from `declined`. The plan code
  and the WholeScripts checklist derive from the kept items only; the declined names appear once, as
  a muted "Also suggested, patient skipped: …" line for the pharmacist. The patient copy never
  mentions them.
- The `Reply-To` display name goes through `displayName()`. Without it a name containing `<` would
  leave a mail client reading the wrong string as the reply address.
- Self-hosted on purpose: quiz answers are health information, so no third-party form vendor is in
  the path. Nothing is persisted except a per-IP rate-limit counter in the temp dir.
- The browser sends **human-readable answer labels**, so PHP never maps quiz values to text and can't
  drift from `QUESTIONS`. In exchange PHP treats every field as hostile (length caps, CRLF stripping,
  `htmlspecialchars` on output) and whitelists plan keys via `PLAN_KEYS`.
- Anti-spam is a honeypot field plus a 3-second minimum between results rendering and submit. No captcha.
- `mail()` on SiteGround is often spam-foldered by Gmail. If deliverability is poor, switch to
  authenticated SMTP via PHPMailer, still self-hosted, still no third party.

**`wellness.html` notes:**
- **Reasons engine.** `RULES` is a table of `[predicate, reason]` pairs per supplement, plus an
  optional `blocked` gate. `evaluate()` returns `{ key: [reasons] }`; `recommend()` is just the keys
  of that in `ORDER`. One source means a supplement can never appear without a reason drawn from the
  patient's own answers. **Changing a predicate changes who gets recommended what**, so re-run the
  equivalence check against a snapshot of the old function before shipping any edit here. Reason
  strings must contain no commas; three of them get joined into one sentence by `reasonSentence()`.
- `SUPPS[key].about` is the plain-English "What is this?" copy. Supports phrasing only, no disease
  claims, same posture as the disclaimer at the bottom of the results.
- **Selection state** is a `Set` of keys in `selected`, initialized to everything in `showResults()`.
  The formula toggle re-renders the whole results body, so `selected`, `expanded` (open disclosure
  panels), and `captureDraft()` (typed form input) all exist to survive that round trip. `resetQuiz()`
  clears all three.
- Each card is a real `<input type="checkbox">` styled as the quiz's circular check, so keyboard and
  screen readers work. The whole card toggles it, with the label and the disclosure carved out of the
  card-level handler, because reacting to those would toggle twice.
- Heading, submit label, and the empty-selection hint all read from `selected.size` in
  `updateSelectionUI()`, so they cannot disagree. Zero selected disables submit; `quiz-lead.php`
  rejects an empty plan as well.
- The handoff lives in the `.ws-join` card, built by `handoffMarkup()` / `handoffDoneMarkup()`.
- Once submitted, `handoffSent` switches the cards to a static list of what was actually sent.
- **Removed Jul 2026:** the per-supplement "Buy on WholeScripts" buttons and the `ws-modal` popup.
  They fought with tap-to-select on the same card, and the handoff form plus the "head start" button
  already cover both signing up and calling.

## Brand Assets (`brand_assets/`)

| File | Description |
|------|-------------|
| `newpharmacylogo.PNG` | **Current logo**, all-green palette. Footer: `filter: brightness(0) invert(1)`. |
| `newbrandguidelines.png` | **Current brand guidelines** (all-green palette). |
| `og-image.png` | Open Graph share-preview image, referenced from every page's `<meta property="og:image">`. |
| `email-logo.png` | 440×238, 23 KB, white-flattened logo for the `quiz-lead.php` emails. Generated from `newpharmacylogo.PNG` with sharp. Must stay reachable at `https://mybest-pharmacy.com/brand_assets/email-logo.png`; email clients can't use relative paths, and CSS filters (how the footer whitens the logo) don't work in email, so it sits on a white band above the green header. |

Equipment hero/banner photos and the storefront photo live in `website_pics/` (root), **not** `brand_assets/`:
- `website_pics/equipment-banner.jpg`: home page equipment banner
- `website_pics/equipment-hero.jpg`: equipment.html hero
- `website_pics/pharmacy-storefront.jpeg`: stacked below the logo card in the home hero

---

## Equipment Catalog (`equipment.html`)

34 priced line items across 7 category tables. **19 of them have a real photo** wired up via `data-item="<key>"` on the `<tr>` + a matching entry in the `EQ_DATA` object (last `<script>` block); clicking/tapping a row opens a photo + description modal. The other 15 (Elevating Legrests, Air Mattress, Mattress, IV Pole, both Medela pumps, Suction Pump Aspirator, Billi Blanket, both Oxygen Concentrators, Oxygen Cylinder, Nebulizer, CPAP, Oxygen Tank Service, Crutches) are plain text/price rows with no photo preview, intentionally left that way (Jul 17, 2026 decision), even though matching photo files for most of them already sit unused in `website_pics/equipment/`.

- To add/replace a photo for any item: drop the file in `website_pics/equipment/`, add `data-item="<key>"` + the `eq-eye` icon span to its `<tr>`, and add a matching entry to `EQ_DATA`.
- `website_pics/equipment/suction-pump.jpg` is a 0-byte broken file if that item is ever wired up; needs a real photo re-sourced first.

---

## Design System

**Palette (all forest green, rebranded from blue):**
```css
:root {
  --blue:      #1B5226;   /* primary forest green */
  --blue-dk:   #0B3016;   /* footer bg, darkest green */
  --green:     #6ABF4B;   /* accent lime green */
  --green-dk:  #4E9035;   /* hover green */
  --off-white: #F8F9FA;
  --gray-900:  #0B3016;   /* body text, dark green (was navy; changed during recolor) */
}
```
> If body text looks off, reset `--gray-900` to `#111`.

**No shared stylesheet.** Each of the 6 pages carries its own inline `:root` block and CSS. Known drift: `index.html`, `providers.html`, and `wellness.html` also define `--blue-md: #14421E`, which `services.html`, `contact.html`, and `equipment.html` lack. When editing the palette, grep all 6 files; don't assume one page's `:root` is authoritative.

**Typography:** Cormorant Garamond (headings) + DM Sans (body)

**Hero gradient:** `linear-gradient(150deg, #061a0c 0%, #1B5226 55%, #0f3d1a 100%)`

**Nav (all pages):** Always white `rgba(255,255,255,0.95)` · Height `h-20 md:h-28` · Logo `h-16 md:h-24` · Links: Home | For Patients | Wellness Program | Equipment | Providers | Contact

**Logo in dark heroes:** Wrapped in white card (`border-radius:22px; padding:20px 36px`; height 140px on interior pages). Home hero logo card: `max-width:560px`, `border-radius:36px`, `padding:52px 56px`, `logo-float` animation.

**Animation:** `.fu` / `.fu-0–5` fadeUp, `.reveal` scroll reveal, `.logo-float` hero float. Headless bypass: `if (navigator.webdriver)` instantly shows all animated elements.

---

## Known Issues / Todo

- **Wellness handoff, reverted to self-serve (Jul 21, 2026):** the email handoff is off the live page
  (see the Status banner in the WholeScripts Handoff section). The three items below are **resolved or
  moot** now that the site is self-serve, but kept for history:
  - ✅ `wellness@mybest-pharmacy.com` exists as a real mailbox (created Jul 21, 2026). Now unused by the
    live page (the email flow is dormant).
  - ~~Confirm WholeScripts can email a recommendation to an account-less address~~: **moot on the live
    page**: patients now register first, then order themselves. Still relevant only if the email flow is
    ever re-enabled.
  - ~~Monthly refills / autoship not wired~~: **resolved by messaging**: the page no longer sells
    auto-refill; it explicitly says no subscription, order a month at a time.
  - ~~Delivery-included wording conflict~~: **fixed**: "delivery included" copy removed; the page now
    says supplements ship direct from WholeScripts.
  - **Referral link is the sole WholeScripts entry point.** The only external WholeScripts link on the
    page is the sign-up button → `WS_REGISTER` (`.../register/mybestpharmacy`). Per-card buttons now
    **Copy the product name** to the clipboard (no cold search deep-link), so a patient can't register
    outside the referral and lose the pharmacy connection. The results page spells out sign up → search
    → add to cart → check out in 3 plain steps, with a "call us and we'll do it with you" fallback.
- **Accessibility/typography debt (open):** body and pricing-table text is set at 14px (13px on mobile) sitewide, below the 15–16px recommended for the pharmacy's older patient demographic. The lime accent `--green: #6ABF4B` is also used for body text/links on white backgrounds in places, which is roughly 2:1 contrast, below WCAG AA's 4.5:1 for text. `--green-dk: #4E9035` already exists as a darker, higher-contrast alternative but is barely used. Fix: reserve lime for buttons/badges on dark backgrounds; use `--green-dk` (or `--gray-900`) for any green text on light backgrounds; bump body/table text to 15–16px.
- **Suction Pump Aspirator photo missing**: see Equipment Catalog section above; the sourced file is 0 bytes.
- **CSS variable drift**: `--blue-md` only defined on 3 of 6 pages (see Design System above).

---

## How to Resume

```bash
cd "/Users/danielgampel/Desktop/MyBestPharmacy/Pharmacy Website"
lsof -ti:3000          # check if server running
node serve.mjs &       # start if not
node screenshot.mjs http://localhost:3000 label
node screenshot.mjs http://localhost:3000/equipment.html label
```
