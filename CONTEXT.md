# CONTEXT.md — My Best Pharmacy Website
*Last updated: 2026-05-17. Allows a fresh Claude Code session to resume with zero context loss.*

---

## Project Overview

A **5-page static informational website** for **My Best Pharmacy**, a full-service & compounding pharmacy in Boynton Beach, FL.

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
| `index.html` | My Best Pharmacy — Full-Service Pharmacy | Two-column hero (h1 + CTAs left, logo card + storefront photo right), 4 service cards, Equipment Rental banner (real image), Services Strip, Accepted Programs, Provider Callout, Location & Hours + map, Footer |
| `services.html` | For Patients | Hero, How It Works (paper Rx, cash-pay compounding), 4 patient service cards, What Is Compounding, FAQ accordion, Accepted Programs, Provider CTA, Footer |
| `providers.html` | For Providers | Hero, How to Send Rx (Fax / Call / Contact cards), Compounds We Prepare (full formulary, 3 columns), CTA, Footer |
| `contact.html` | Contact | Hero, Contact + Map (2-column), Service Area note, Provider CTA Strip, Footer |
| `equipment.html` | Medical Equipment Rental & Sales | Two-column hero, "Rent or Purchase" callout, 7 pricing table sections (monthly/bi-weekly/deposit + "Call for Pricing" purchase column), CTA, Footer |

---

## Scripts

| File | Purpose |
|------|---------|
| `serve.mjs` | Static file server → `http://localhost:3000` |
| `screenshot.mjs` | Puppeteer full-page screenshot → `temporary screenshots/screenshot-{N}-{label}.png` (1440×900) |

---

## Brand Assets (`brand_assets/`)

| File | Description |
|------|-------------|
| `pharmacylogo.png` | **Current logo** — all-green palette, white/light background (not transparent). Footer: `filter: brightness(0) invert(1)`. |
| `nationsbenefitslogo.png` | Nations Benefits partner logo. Used on index.html and services.html. |
| `newbrandguidlines.png` | **Current brand guidelines** (all-green palette). `pharmacybrandguidlines.png` is the old blue version — superseded. |
| `equipment-banner.png` | Real equipment photo used in home page equipment banner. |
| `equipment-hero.png` | Real equipment photo used in equipment.html hero. |
| `pharmacy-storefront.jpeg` | Pharmacy storefront photo stacked below the logo card in home hero. |

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

**Typography:** Cormorant Garamond (headings) + DM Sans (body)

**Hero gradient:** `linear-gradient(150deg, #061a0c 0%, #1B5226 55%, #0f3d1a 100%)`

**Nav (all pages):** Always white `rgba(255,255,255,0.95)` · Height `h-20 md:h-28` · Logo `h-16 md:h-24` · Links: Home | For Patients | Equipment | Contact | [For Providers — green button]

**Logo in dark heroes:** Wrapped in white card (`border-radius:22px; padding:20px 36px`; height 140px on interior pages). Home hero logo card: `max-width:560px`, `border-radius:36px`, `padding:52px 56px`, `logo-float` animation.

**Animation:** `.fu` / `.fu-0–5` fadeUp, `.reveal` scroll reveal, `.logo-float` hero float. Headless bypass: `if (navigator.webdriver)` instantly shows all animated elements.

---

## Known Issues / Todo

- Footer quick links on some pages may still say "Services" instead of "For Patients" — not confirmed fixed
- **Deployment not done:** No hosting, domain, or CI. Options: Netlify drag-and-drop, GitHub Pages, Vercel. Old domain `www.mybestpharmacyfl.com` — check availability.
- **SEO not started:** Missing meta descriptions, Open Graph tags, schema.org LocalBusiness
- **Contact form not started:** Page is informational only. Options: Netlify Forms, Formspree, EmailJS
- **Equipment page — real photos needed:** All 35 items in `equipment.html` currently show branded placeholder images. To add real photos, open `equipment.html`, scroll to the `EQ_DATA` object in the last `<script>` block, and replace each `image:` URL (currently `https://placehold.co/480x260/1B5226/6ABF4B?text=...`) with a real photo URL or local file path. The modal, tooltip, and descriptions stay as-is — only the image URLs need to change.

---

## How to Resume

```bash
cd "/Users/danielgampel/Desktop/Pharmacy Website"
lsof -ti:3000          # check if server running
node serve.mjs &       # start if not
node screenshot.mjs http://localhost:3000 label
node screenshot.mjs http://localhost:3000/equipment.html label
```
