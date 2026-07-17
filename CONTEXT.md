# CONTEXT.md — My Best Pharmacy Website
*Last updated: 2026-07-17. Allows a fresh Claude Code session to resume with zero context loss.*

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
- Compounded medications: **cash-pay only — no insurance**
- Retail Rx (non-compounded): insurance accepted; **Express Scripts (Cigna/Evernorth) in-network**
- **Nations Benefits** Medicare Advantage OTC card accepted for OTC products and medical equipment
- Patients **must bring a physical paper Rx** — not paperless
- Medical equipment available for **rent AND purchase**; purchase prices TBD → display "Call for Pricing"
- **(561) 292-0423 no longer exists — removed from every page**

---

## Pages

| File | Title / URL | Key Sections |
|------|-------------|--------------|
| `index.html` | My Best Pharmacy — Full-Service Pharmacy | Two-column hero, 4 service cards, Equipment Rental banner, patient reviews carousel (mobile), Services Strip (desktop only), Accepted Programs, Provider Callout, Location & Hours + map, Footer |
| `services.html` | For Patients | Hero, How It Works (paper Rx, cash-pay compounding), 4 patient service cards, What Is Compounding, FAQ accordion, Accepted Programs, Provider CTA, Footer |
| `wellness.html` | Wellness Program | Wellness program page (added Jun 2026) |
| `equipment.html` | Medical Equipment Rental & Sales | Two-column hero, "Rent or Purchase" callout, 7 pricing table sections (monthly/bi-weekly/deposit + "Call for Pricing" purchase column), click-to-preview photo modal, CTA, Footer |
| `providers.html` | For Providers | Hero, How to Send Rx (Fax / Call / Contact cards), Compounds We Prepare (full formulary, 3 columns), CTA, Footer |
| `contact.html` | Contact | Hero, Contact + Map (2-column), Service Area note, Provider CTA Strip, Footer |

**Footer "Quick Links" on every page:** Home, For Patients, Equipment, Wellness Program, For Providers, Contact — keep this list and its labels identical across all 6 pages (fixed Jul 2026; previously index/wellness were missing the Equipment link and mislabeled it "Services").

---

## Scripts

| File | Purpose |
|------|---------|
| `serve.mjs` | Static file server → `http://localhost:3000` |
| `screenshot.mjs` | Puppeteer full-page screenshot → `temporary screenshots/screenshot-{N}-{label}.png` (1440×900) |

---

## Deployment

- **Live via GitHub Actions** (`.github/workflows/deploy.yml`): every push to `main` FTP-deploys the repo root to SiteGround (`mybest-pharmacy.com/public_html/`).
- Excluded from deploy: `.git*`, `node_modules/`, `fable/`, `temporary screenshots/`, `README.md`, `CLAUDE.md`, `CONTEXT.md`, `LEARNINGS.md`, `package*.json`, `serve.mjs`, `screenshot.mjs`, the `.code-workspace` file, `.DS_Store`.
- `robots.txt` + `sitemap.xml` are in place and list all 6 pages.
- **SEO package deployed** (Jul 16, 2026): meta descriptions, Open Graph tags, `schema.org` Pharmacy/LocalBusiness structured data, Google Business Profile CID link.
- **Contact form still not built** — pages are informational only. Options if needed: Netlify Forms, Formspree, EmailJS.

---

## Brand Assets (`brand_assets/`)

| File | Description |
|------|-------------|
| `newpharmacylogo.PNG` | **Current logo** — all-green palette. Footer: `filter: brightness(0) invert(1)`. |
| `newbrandguidelines.png` | **Current brand guidelines** (all-green palette). |
| `og-image.png` | Open Graph share-preview image, referenced from every page's `<meta property="og:image">`. |

Equipment hero/banner photos and the storefront photo live in `website_pics/` (root), **not** `brand_assets/`:
- `website_pics/equipment-banner.jpg` — home page equipment banner
- `website_pics/equipment-hero.jpg` — equipment.html hero
- `website_pics/pharmacy-storefront.jpeg` — stacked below the logo card in the home hero

---

## Equipment Catalog (`equipment.html`)

34 priced line items across 7 category tables. **19 of them have a real photo** wired up via `data-item="<key>"` on the `<tr>` + a matching entry in the `EQ_DATA` object (last `<script>` block) — clicking/tapping a row opens a photo + description modal. The other 15 (Elevating Legrests, Air Mattress, Mattress, IV Pole, both Medela pumps, Suction Pump Aspirator, Billi Blanket, both Oxygen Concentrators, Oxygen Cylinder, Nebulizer, CPAP, Oxygen Tank Service, Crutches) are plain text/price rows with no photo preview — intentionally left that way (Jul 17, 2026 decision), even though matching photo files for most of them already sit unused in `website_pics/equipment/`.

- To add/replace a photo for any item: drop the file in `website_pics/equipment/`, add `data-item="<key>"` + the `eq-eye` icon span to its `<tr>`, and add a matching entry to `EQ_DATA`.
- `website_pics/equipment/suction-pump.jpg` is a 0-byte broken file if that item is ever wired up — needs a real photo re-sourced first.

---

## Design System

**Palette (all forest green — rebranded from blue):**
```css
:root {
  --blue:      #1B5226;   /* primary forest green */
  --blue-dk:   #0B3016;   /* footer bg, darkest green */
  --green:     #6ABF4B;   /* accent lime green */
  --green-dk:  #4E9035;   /* hover green */
  --off-white: #F8F9FA;
  --gray-900:  #0B3016;   /* body text — dark green (was navy; changed during recolor) */
}
```
> If body text looks off, reset `--gray-900` to `#111`.

**No shared stylesheet** — each of the 6 pages carries its own inline `:root` block and CSS. Known drift: `index.html`, `providers.html`, and `wellness.html` also define `--blue-md: #14421E`, which `services.html`, `contact.html`, and `equipment.html` lack. When editing the palette, grep all 6 files — don't assume one page's `:root` is authoritative.

**Typography:** Cormorant Garamond (headings) + DM Sans (body)

**Hero gradient:** `linear-gradient(150deg, #061a0c 0%, #1B5226 55%, #0f3d1a 100%)`

**Nav (all pages):** Always white `rgba(255,255,255,0.95)` · Height `h-20 md:h-28` · Logo `h-16 md:h-24` · Links: Home | For Patients | Wellness Program | Equipment | Providers | Contact

**Logo in dark heroes:** Wrapped in white card (`border-radius:22px; padding:20px 36px`; height 140px on interior pages). Home hero logo card: `max-width:560px`, `border-radius:36px`, `padding:52px 56px`, `logo-float` animation.

**Animation:** `.fu` / `.fu-0–5` fadeUp, `.reveal` scroll reveal, `.logo-float` hero float. Headless bypass: `if (navigator.webdriver)` instantly shows all animated elements.

---

## Known Issues / Todo

- **Accessibility/typography debt (open):** body and pricing-table text is set at 14px (13px on mobile) sitewide — below the 15–16px recommended for the pharmacy's older patient demographic. The lime accent `--green: #6ABF4B` is also used for body text/links on white backgrounds in places, which is roughly 2:1 contrast — below WCAG AA's 4.5:1 for text. `--green-dk: #4E9035` already exists as a darker, higher-contrast alternative but is barely used. Fix: reserve lime for buttons/badges on dark backgrounds; use `--green-dk` (or `--gray-900`) for any green text on light backgrounds; bump body/table text to 15–16px.
- **Suction Pump Aspirator photo missing** — see Equipment Catalog section above; the sourced file is 0 bytes.
- **CSS variable drift** — `--blue-md` only defined on 3 of 6 pages (see Design System above).

---

## How to Resume

```bash
cd "/Users/danielgampel/Desktop/MyBestPharmacy/Pharmacy Website"
lsof -ti:3000          # check if server running
node serve.mjs &       # start if not
node screenshot.mjs http://localhost:3000 label
node screenshot.mjs http://localhost:3000/equipment.html label
```
