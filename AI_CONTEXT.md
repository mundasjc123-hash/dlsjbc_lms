# AI context brief - read this before helping with this project 
 
If you're an AI assistant (Claude, ChatGPT, Gemini, Copilot, or anything 
else) being asked to help build a feature for this project: paste this 
whole file into the chat first, before describing the task. It contains 
decisions already made for this codebase - please follow them rather than 
suggesting your own defaults. 
 
## What this project is 
 
A Library Management System for a school library. Capstone / thesis 
project. The system is called **DLSJBC LMS**.

Modules: OPAC (public catalog), user management, borrowing, reservations 
(called "Holds" in the code), fines, inventory, reports, audit trails, 
profile, settings, and a digital library (downloadable files - PDFs of 
theses/e-books - browsable without login, download requires login). 
 
**Not built, and not currently planned:** serials/subscription management 
(tracking recurring journal issues, claiming missing ones) - this was 
raised and deliberately left undecided. Don't add it without asking 
first. (Digital works were in this category too, but that decision has 
since been made - see "Digital Library" below.) 
 
## Hard constraints - do not suggest alternatives to these 
 
- **No frameworks.** Plain PHP 8.2+, HTML, CSS, JS, MySQL only. Do not 
  suggest Laravel, Composer, Node build tools, or any package manager for 
  the PHP side. 
- **PDO only, prepared statements only.** Never build SQL with string 
  concatenation. Never use `mysqli`. 
- **Sessions for auth**, not JWTs or third-party auth services. 
- **Money is always `DECIMAL(10,2)`**, never float. 
 
## Folder structure (grouped by feature, not by file type) 
 

public/ <- the only folder a browser can reach. Router lives here.
core/ <- shared code every feature uses (Database, Auth, Csrf, Audit)
features/<name>/ <- one folder per feature
routes.php returns [[method, path, absolute file path], ...]
pages/*.php the actual screens
*.php feature-specific classes (e.g. Loan.php, Policy.php)
templates/ <- header.php (top nav), staff-header.php (sidebar),
footer.php, staff-footer.php, layout.php (picks
between the two based on $layout)
database/ <- schema.sql (full schema), seed.php (starting data)
config/ <- config.php (real, gitignored), config.sample.php (template)

 
Current feature folders: `auth`, `opac`, `dashboard`, `circulation`, 
`catalog`, `digital`, `patrons`, `holds`, `fines`, `inventory`, `reports`, 
`audit`, `profile`, `users`, `settings`. 
 
Real, database-backed content exists for: `auth` (login/logout), `opac` 
(homepage, search results, and title detail page), `catalog` (browse & 
search titles, add/edit a title, add a copy), `circulation` (checkout, 
return, renew, active-loans list), and `digital` (browse, download, 
upload, delete). `dashboard` has real layout but still shows hardcoded 
sample numbers/activity, not live queries. 
 
Everything else - `patrons`, `holds`, `fines`, `inventory`, `reports`, 
`audit`, `users`, `settings` - is still a placeholder "coming soon" page. 
Check the actual file before assuming a screen has real content, since 
this list will keep shrinking as more modules get built. 
 
## Roles - exactly three, and access is genuinely different per role 
 
- **`admin`** - oversight only, NOT day-to-day library work. Sidebar: 
  Dashboard, Reports, Audit Log, Users, Settings. Deliberately does 
  **not** get Circulation, Catalog, Patrons, Holds, Fines, or Inventory - 
  that was a specific decision, not an oversight. `Users` = every account 
  in the system (patrons + staff), minus the admin's own row (so they 
  can't lock themselves out by editing/deactivating themselves). 
  `Settings` = circulation policies (loan periods, fine rates) and 
  calendar closures - values that currently only exist as database rows 
  from `seed.php`, with no UI yet to edit them. 
 
- **`librarian`** - all the day-to-day work. Sidebar: Dashboard, 
  Circulation, Catalog, Patrons, Holds, Fines, Inventory, Reports, Audit 
  Log (9 items). `Patrons` here = manage all patron (student/faculty) 
  accounts - different from `Users` above, which is admin-only and covers 
  every account type. 
 
- **`patron`** - students and faculty, one single role. The 
  student/faculty/grad distinction is handled by `patron_categories` 
  (drives borrowing limits), not by a separate login role. Patron-facing 
  pages use the top-nav layout (`header.php`), not the sidebar. 
 
**Enforcement pattern:** every staff page calls 
`Auth::requireRole(['librarian'])` or `Auth::requireRole(['admin'])` or 
`Auth::requireRole(['admin', 'librarian'])` depending on who's allowed - 
see any file in `features/*/pages/index.php` for the exact pattern. 
This is real enforcement (403 if the wrong role hits the URL directly), 
not just hidden nav links - always add both when creating a new page. 
 
**Test accounts** (from `seed.php`): `ADMIN-0001` / `admin123`, 
`LIBRARIAN-0001` / `librarian123`, `STUDENT-0001` / `student123`. 
 
## Naming - these are easy to mix up, be precise 
 
- **Patrons** (librarian sidebar) vs. **Users** (admin sidebar) - Patrons 
  is patron-accounts only; Users is every account type, admin-only. 
- **My Profile** (top bar, every role) - a user's own name/email/password 
  (+ contact info for patrons). Different from Patrons/Users, which are 
  staff managing *other* people's accounts. 
- **My Account** (patron-facing, not built yet) - a patron's own loans, 
  holds, and fines. Agreed shape: one page, with tabs (not three separate 
  pages). 
- **Holds** = the reservation feature from the original feature list. 
 
## Catalog vs. Circulation vs. Inventory - the three-way split 
 
- **Catalog** answers "what is this, and what do we call it?" - adding a 
  title, entering a copy's barcode/shelf. Happens once per copy, rarely 
  edited after. 
- **Circulation** answers "who has it right now, and until when?" - 
  checkout, return, renew. Happens constantly, every single loan. 
- **Inventory** answers "is it still here, and what shape is it in?" - 
  marking lost/damaged, stocktaking (walking the shelves to verify 
  what's actually there). Happens occasionally, for exceptions. 
 
Circulation and Inventory both change an item's `status` field, but for 
different reasons - circulation flips available/checked_out as normal 
lending; inventory only touches it for exceptions (lost, withdrawn) or to 
confirm nothing's missing. Don't merge these two into one feature. 

## Digital Library - separate from Catalog, on purpose 

Downloadable files (PDFs) with their own table (`digital_files`) and 
class (`DigitalFile.php`), not part of the physical-copy world that 
Catalog/Circulation/Inventory manage. Browsing is public (no login); 
downloading requires login. Files live under `storage/digital_files/` 
(path comes from `DIGITAL_STORAGE_PATH` in `config/config.php`) and are 
seeded by `database/seed_digital.php`, separate from the main seed. 
 
## Conventions that must be followed exactly 
 
1. **Class file names must exactly match the class name inside** - 
   `Loan.php` must contain `class Loan`. The autoloader 
   (`core/autoload.php`) finds classes this way; a mismatch means the 
   class silently fails to load. 
 
2. **Every feature owns a `routes.php`** returning an array of 
   `[HTTP method, URL path, absolute file path via __DIR__]`. The router 
   (`public/index.php`) collects these from every feature automatically - 
   never hardcode a route inside `index.php` itself. 
 
3. **Features do not query another feature's database tables directly.** 
   If the fines feature needs loan data, it calls a method on 
   circulation's `Loan` class - it does not write its own `SELECT` on the 
   `loans` table. This keeps features safe to change independently. 
   **Known exception, to be fixed:** `Loan.php` currently inserts 
   directly into `account_transactions`, which `schema.sql` marks as a 
   Fines table, because Fines has no class yet. Whoever builds the Fines 
   feature should add a proper `Fines` class and move that write behind 
   it - don't treat this exception as the pattern to copy for new code. 
 
4. **Every form that changes data includes `Csrf::field()`**, and the 
   handler calls `Csrf::verify($_POST['csrf_token'] ?? null)` before doing 
   anything else. 
 
5. **Every action that changes data calls `Audit::log(...)`** right after 
   the change succeeds. See `features/auth/pages/login.php` for the 
   pattern (logs both success and failure). 
 
6. **Passwords are hashed with `password_hash($pw, PASSWORD_ARGON2ID)`**, 
   verified with `password_verify()`. Never store or compare plain text. 
 
7. **Pages return HTML by just writing it directly** (no separate template 
   engine) - the router wraps every page's output in either the top-nav 
   or sidebar chrome via `templates/layout.php`. A page can still safely 
   call `header('Location: ...'); exit;` at any point to redirect, because 
   output is buffered until the page finishes running. 
 
8. **A page picks its own chrome** by setting `$layout = 'staff';` as the 
   very first thing it does (before any HTML) to get the sidebar. Leaving 
   it unset defaults to the top-nav chrome (patron-facing pages). See 
   `features/profile/pages/index.php` for a page that picks based on the 
   current user's role, since Profile is shared by every role. 
 
## Design system - reuse it, don't invent new styles 
 
Colors and components are already defined in 
`public/assets/shared/style.css` as CSS variables and classes. Use the 
existing classes (`.btn`, `.btn-primary`, `.btn-secondary`, `.card`, 
`.badge` + `.badge-available/out/overdue/hold`, `.stat-number`, standard 
`<table>` styling) rather than writing new one-off CSS for a new page. 
 
- Colors: `--pine #1B4332`, `--moss #2D6A4F`, `--sage #E3ECD9`, 
  `--paper #FAF9F4`, `--ink #1B1F1C`, `--brass #B8935A` 
- Fonts: Fraunces (serif) for headings, Inter (sans) for body/UI/tables 
 
## Responsive / Mobile Design

The system will have a **responsive mobile interface for the same web 
application**, not a separate mobile app.

Mobile responsiveness should eventually cover:

- Top navigation and subnavigation
- Login modal
- Staff sidebar
- OPAC/catalog grid
- Tables and other staff pages
- Other components as the system is developed

The current project has a viewport meta tag, but responsive behavior should 
be added as pages are developed. Do not treat mobile as a separate 
application or backend.

## Authentication / Login

Authentication uses PHP sessions and the existing login system.

The login modal uses the native HTML `<dialog>` element.

- Escape-to-close is handled natively by the browser.
- The backdrop uses the native `::backdrop`.
- JavaScript is still used for opening the dialog and submitting the 
  login form through `fetch`.
- The login JavaScript is currently inline in `templates/header.php`.
- The modal was enlarged to approximately 440px.
- The close button is positioned inside the dialog to prevent clipping 
  and horizontal overflow.

**The previous OPAC/login JavaScript issue involving `#login-form` has 
already been resolved. Do not treat it as an outstanding issue.**

## Database 
 
Full schema already exists in `database/schema.sql` (all modules, ~26 
tables) - don't create new tables casually; check if one already fits. 
Base accounts come from `database/seed.php`. There are also optional, 
feature-specific seed scripts for demoing without hand-testing every 
state yourself: `seed_opac.php` (sample titles/copies), 
`seed_circulation.php` (loans in every state - active, renewed, overdue, 
returned late), and `seed_digital.php` (sample digital titles). 
 
## Still missing (known gaps, not yet built) 
 
- Patron's "My Account" page (loans/holds/fines) 
- Real content for the remaining staff placeholder screens: `patrons`, 
  `holds`, `fines`, `inventory`, `reports`, `audit`, `users`, `settings` 
  (currently just "coming soon" - check the actual file before assuming 
  otherwise) 
- Dashboard's numbers/activity feed are still hardcoded sample data, not 
  live queries - wire it up once circulation/holds/fines have real data 
  worth showing 
- A proper `Fines` class (see the exception noted under convention #3 
  above) 
 
## For more detail 
 
- `README.md` - full local setup instructions, including the Virtual Host 
  configuration required to run this locally. 
- `CONTRIBUTING.md` - Git branching workflow and who owns which feature 
  folder.