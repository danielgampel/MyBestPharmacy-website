# LEARNINGS.md — Website Creation Knowledge Base
*Accumulated lessons from building My Best Pharmacy's website. Use this as an agent brief for future projects.*

---

## 1. Technical Pitfalls

### URL Encoding in Static Servers
**Problem:** `serve.mjs` uses `path.join(__dirname, urlPath)` with no `decodeURIComponent()`. Filenames with spaces, commas, or special characters (e.g., `ChatGPT Image May 16, 2026, 11_37_04 PM.png`) fail silently — the browser requests the raw URL-encoded path, the server can't find the file, and returns 404.  
**Fix:** Always copy user-provided files to URL-safe names before referencing in HTML.  
```bash
cp "brand_assets/bad name, here.png" brand_assets/good-name.png
```
**Rule:** Every image `src` must contain only letters, numbers, hyphens, underscores, and dots — no spaces, commas, or parentheses.

---

### `sed` Side Effects on CSS Variables
**Problem:** When mass-replacing a hex value (e.g., `#0E1C2F`) across files, that exact hex may appear in multiple variable definitions. A `sed` that replaces the old primary color `--blue: #0E1C2F` will also accidentally hit `--gray-900: #0E1C2F`, changing dark body text to a tinted hue.  
**Fix:** Always inspect all occurrences before running a global replace. Prefer targeted replacements using variable names rather than raw hex values.

---

### Node Server Port Conflicts
**Problem:** Starting `node serve.mjs` a second time throws `EADDRINUSE :3000`.  
**Fix:** Always check / kill existing processes first:
```bash
pkill -f "node serve.mjs" 2>/dev/null; node serve.mjs &
```

---

### Puppeteer / Headless Screenshot Animation Bypass
**Problem:** Scroll-reveal animations (`.reveal`, `.fu`) leave content invisible in full-page screenshots because Puppeteer doesn't scroll.  
**Fix already in codebase:** Detect `navigator.webdriver` and instantly apply all animation classes. Don't remove this bypass — it's load-bearing for screenshot verification.
```js
if (navigator.webdriver) { /* instantly show all .reveal and .fu */ }
```

---

## 2. Design System Rules

### CSS Custom Properties for Theming
Always declare brand colors as `:root` CSS variables at the top of every page. This makes full-site color rebranding a one-liner `sed` rather than a hunt through inline styles.
```css
:root {
  --green:    #1B5226;  /* primary */
  --green-lt: #6ABF4B;  /* accent */
  --blue:     #1B5226;  /* repurposed from old blue system */
}
```
When a client changes brand colors, replace variable values — not every instance.

---

### Shared Asset Replacement Strategy
When all pages reference the same image path (e.g., `brand_assets/pharmacylogo.png`), swapping the file at that path updates all pages at once — no HTML edits needed. Use this for logos, favicons, and any globally shared asset.

---

### Card Visual Consistency
**Mistake made:** Added `border-top: 3px solid var(--green)` to only the lead card in a 4-card grid to "highlight" it. This looked like a bug / accidental extra border rather than intentional emphasis.  
**Rule:** If you want to highlight one card in a uniform grid, use a different mechanism that reads clearly as intentional — a background color difference, a subtle `scale(1.02)` on hover, a badge/pill in the corner, or a slightly larger shadow. A single stray border on one card in a uniform row always looks like a mistake.

---

### Color Taxonomy: Don't Overload Variable Names
After rebranding from blue → forest green, `--blue` now stores a green value. This is confusing. For future projects, name variables semantically:
- `--primary`, `--primary-dark`, `--primary-light`
- `--accent`, `--surface`, `--text-body`, `--text-heading`

Never use a hue name (`--blue`, `--green`) as a semantic variable — brands change colors.

---

## 3. Content Strategy

### Business-Specific Rules (My Best Pharmacy)
- Compounded medications: **cash-pay only. No insurance.**
- Retail prescriptions (non-compounded): insurance accepted, Express Scripts in-network.
- Nations Benefits: Medicare Advantage OTC card — accepted for over-the-counter products and medical equipment.
- Paper prescription: patients **must** bring a physical copy. Not paperless.
- Equipment: both **rental and sales**. Purchase prices TBD — use "Call for Pricing."

---

### Emphasis Balance
**Mistake made:** Overemphasized compounding; underrepresented OTC and retail pharmacy.  
**Rule:** When a business has multiple revenue streams, survey all of them before choosing visual hierarchy. The client knows their business better than you do — confirm which services are lead vs. support before building the first section.

---

### Insurance Claims Must Be Verified Per Service Type
Don't assume a pharmacy accepts insurance for every service. Compounding is frequently cash-pay; retail is insurance-covered; DME may use Medicare/supplemental programs. Distinguish clearly in copy — mixing these up creates real-world patient confusion.

---

## 4. Workflow

### Screenshot-First Verification Loop
Never declare a task done without screenshotting. Always:
1. Save the file
2. Screenshot at `http://localhost:3000` (never `file:///`)
3. Read the PNG with the Read tool and visually inspect
4. Fix any discrepancies; screenshot again
5. Repeat until no visible issues remain

---

### Filename Strategy for User-Provided Images
When users attach images, they often have spaces and timestamps. Before touching HTML:
1. Note the original filename
2. Copy it to a clean URL-safe name in the project root or `brand_assets/`
3. Reference only the clean name in HTML

```bash
cp "pharmacypicture.jpeg" pharmacy-storefront.jpeg
cp "nationsbenefitslogo.png" brand_assets/nationsbenefitslogo.png
```

---

### Mass HTML Updates via `sed`
For global text swaps across multiple files (navigation labels, phone numbers, colors):
```bash
for f in index.html services.html equipment.html providers.html contact.html; do
  sed -i '' 's/OLD TEXT/NEW TEXT/g' "$f"
done
```
Always run `grep -n "OLD TEXT"` first to see every occurrence. Confirm the count before and after.

---

## 5. Reusable Patterns (Copy-Paste Ready)

### Accepted Programs / Partner Logos Section
```html
<section style="padding: 52px 0; background: #fff; border-top: 1px solid var(--gray-100); border-bottom: 1px solid var(--gray-100);">
  <div class="max-w-7xl mx-auto px-6 lg:px-10">
    <p style="text-align:center; font-size:11px; font-weight:600; letter-spacing:0.18em; text-transform:uppercase; color:var(--primary); margin-bottom:32px;">Accepted Benefit Programs</p>
    <div class="flex flex-wrap justify-center items-center gap-12">
      <!-- logo card -->
      <div style="background:#f5f5f5; border-radius:16px; padding:20px 32px;">
        <img src="brand_assets/partner-logo.png" style="height:56px; width:auto;" />
      </div>
    </div>
  </div>
</section>
```

---

### Scroll Cue (animated double chevron)
```html
<div class="scroll-cue" style="text-align:center; margin-top:24px;">
  <p style="font-size:10px; letter-spacing:0.18em; font-weight:600; text-transform:uppercase; color:rgba(255,255,255,0.6);">Scroll Down to See Pricing</p>
  <div class="scroll-bounce" style="margin-top:8px;">
    <svg width="22" height="22" fill="none" stroke="rgba(255,255,255,0.5)" stroke-width="2" viewBox="0 0 24 24"><path d="M6 9l6 6 6-6"/></svg>
    <svg width="22" height="22" fill="none" stroke="rgba(255,255,255,0.3)" stroke-width="2" viewBox="0 0 24 24" style="margin-top:-10px;"><path d="M6 9l6 6 6-6"/></svg>
  </div>
</div>
```
Add `@keyframes scrollBounce` to CSS: `0%,100% { transform: translateY(0) } 50% { transform: translateY(6px) }`.

---

### Uniform Card Grid (no rogue borders)
```html
<div class="grid md:grid-cols-2 lg:grid-cols-4 gap-8">
  <div class="spec-card reveal bg-white rounded-2xl p-8"
       style="box-shadow: 0 4px 20px rgba(0,0,0,0.07), 0 1px 4px rgba(0,0,0,0.04);">
    <!-- all cards same style — highlight via badge or background, not border-top -->
  </div>
</div>
```

---

## 6. Pre-Launch Checklist (for any future website)

- [ ] All image filenames URL-safe (no spaces, commas, special chars)
- [ ] All 5 pages have consistent nav (links, active state, mobile menu)
- [ ] Footer consistent across all pages (address, phone, fax, hours, quick links)
- [ ] No hardcoded old phone numbers or stale copy
- [ ] CSS variable names are semantic (not hue-named)
- [ ] Screenshot every page before declaring done
- [ ] `border-top`, `border-left` visual treatments applied consistently or not at all
- [ ] Logo appears correctly in both light and dark contexts (white card on dark hero; colored on white bg)
- [ ] Insurance/payment claims verified with client — distinguish by service type
- [ ] Mobile hamburger menu tested in screenshot at mobile viewport
