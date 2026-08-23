# CLAUDE.md: Frontend Website Rules

## The `fable/` Folder: Isolated, Off-Limits by Default
- `fable/` contains a separate concept/alternative design of this website. It is its own self-contained project (own CSS, own copies of pages) and must be treated as fully isolated from the main site.
- **Never read, open, edit, reference, or otherwise touch anything inside `fable/`** while working on the main website. Not for context, not for consistency checks, not "just to see." Changes to the main site (index.html, services.html, providers.html, contact.html, equipment.html, wellness.html, root CSS, etc.) must never be ported into `fable/`, and vice versa.
- Only work inside `fable/` when the user explicitly says so in that message (e.g. names "fable" or "the alternative site" directly). A prior conversation about fable does not carry forward permission; the user will speak up each time they want that project touched.
- If a task's instructions are ambiguous about which project they apply to, assume the main site and do not touch `fable/`.

## Always Do First
- **Invoke the `frontend-design` skill** before writing any frontend code, every session, no exceptions.

## Reference Images
- If a reference image is provided: match layout, spacing, typography, and color exactly. Swap in placeholder content (images via `https://placehold.co/`, generic copy). Do not improve or add to the design.
- If no reference image: design from scratch with high craft (see guardrails below).
- Screenshot your output, compare against reference, fix mismatches, re-screenshot. Do at least 2 comparison rounds. Stop only when no visible differences remain or user says so.

## Local Server
- **Always serve on localhost.** Never screenshot a `file:///` URL.
- Start the dev server: `node serve.mjs` (serves the project root at `http://localhost:3000`)
- `serve.mjs` lives in the project root. Start it in the background before taking any screenshots.
- If the server is already running, do not start a second instance.

## Screenshot Workflow
- Puppeteer is installed at `C:/Users/nateh/AppData/Local/Temp/puppeteer-test/`. Chrome cache is at `C:/Users/nateh/.cache/puppeteer/`.
- **Always screenshot from localhost:** `node screenshot.mjs http://localhost:3000`
- Screenshots are saved automatically to `./temporary screenshots/screenshot-N.png` (auto-incremented, never overwritten).
- Optional label suffix: `node screenshot.mjs http://localhost:3000 label` → saves as `screenshot-N-label.png`
- `screenshot.mjs` lives in the project root. Use it as-is.
- After screenshotting, read the PNG from `temporary screenshots/` with the Read tool; Claude can see and analyze the image directly.
- When comparing, be specific: "heading is 32px but reference shows ~24px", "card gap is 16px but should be 24px"
- Check: spacing/padding, font size/weight/line-height, colors (exact hex), alignment, border-radius, shadows, image sizing

## Output Defaults
- Single `index.html` file, all styles inline, unless user says otherwise
- Tailwind CSS via CDN: `<script src="https://cdn.tailwindcss.com"></script>`
- Placeholder images: `https://placehold.co/WIDTHxHEIGHT`
- Mobile-first responsive

## Brand Assets
- Always check the `brand_assets/` folder before designing. It may contain logos, color guides, style guides, or images.
- If assets exist there, use them. Do not use placeholders where real assets are available.
- If a logo is present, use it. If a color palette is defined, use those exact values. Do not invent brand colors.

## Anti-Generic Guardrails
- **Colors:** Never use default Tailwind palette (indigo-500, blue-600, etc.). Pick a custom brand color and derive from it.
- **Shadows:** Never use flat `shadow-md`. Use layered, color-tinted shadows with low opacity.
- **Typography:** Never use the same font for headings and body. Pair a display/serif with a clean sans. Apply tight tracking (`-0.03em`) on large headings, generous line-height (`1.7`) on body.
- **Gradients:** Layer multiple radial gradients. Add grain/texture via SVG noise filter for depth.
- **Animations:** Only animate `transform` and `opacity`. Never `transition-all`. Use spring-style easing.
- **Interactive states:** Every clickable element needs hover, focus-visible, and active states. No exceptions.
- **Images:** Add a gradient overlay (`bg-gradient-to-t from-black/60`) and a color treatment layer with `mix-blend-multiply`.
- **Spacing:** Use intentional, consistent spacing tokens, not random Tailwind steps.
- **Depth:** Surfaces should have a layering system (base → elevated → floating), not all sit at the same z-plane.

## Writing Copy: No Em Dashes, Ever
- **Never use an em dash (—) anywhere in this project.** Not in page copy, headings, `<title>`
  or meta tags, `alt` text, button labels, quiz questions and answers, form error messages, email
  templates, table cells, code comments, commit messages, or these docs. Daniel reads em dashes as
  AI filler and does not want them on the site.
- This applies to anything you write from now on, including brand-new sections and pages.
- Rewrite instead of substituting. An em dash is usually hiding a simpler sentence:
  - Two full thoughts → make them two sentences. `We're here to help — call us.` → `We're here to help. Call us.`
  - An aside or restatement → use a comma. `built for you — not a bundle` → `built for you, not a bundle`
  - A list or definition follows → use a colon. `100% Xymogen — professional-grade` → `100% Xymogen: professional-grade`
  - Page titles and meta titles → use a pipe. `Contact — My Best Pharmacy` → `Contact | My Best Pharmacy`
  - A true parenthetical → use parentheses.
- Do not swap in an en dash (–) or a double hyphen (--) as a replacement. En dashes stay reserved
  for genuine numeric and time ranges (`9 AM–6 PM`, `15–16px`, `Mon–Fri`), which are correct as-is.
- **Watch for the HTML entity form.** `&mdash;` (and `&#8212;` / `&#x2014;`) render as em dashes on
  the page but do not show up in a search for the character itself. `wellness.html` and
  `quiz-lead.php` build a lot of copy from JavaScript and PHP strings that use the entity form.
- **Before finishing any session that touched a file, run this and expect zero hits** (other than
  the examples in this file):
  `grep -rn "—\|&mdash;\|&#8212;\|&#x2014;" --include="*.html" --include="*.php" --include="*.mjs" --include="*.md" . | grep -v fable | grep -v node_modules`
- A page that looks clean on load is not proof. Most of the wellness quiz copy only reaches the DOM
  after a visitor answers the questions, so walk the quiz to the results screen before calling it done.

## Hard Rules
- Do not use an em dash (—) in any copy, comment, or doc you write (see Writing Copy above)
- Do not add sections, features, or content not in the reference
- Do not "improve" a reference design; match it
- Do not stop after one screenshot pass
- Do not use `transition-all`
- Do not use default Tailwind blue/indigo as primary color
- Every change that we work on here, I don't want you to automatically push it into GitHub. I just want you to apply the changes to the localhost, and then we'll decide whether we push it or not based on if the changes work well or not. 