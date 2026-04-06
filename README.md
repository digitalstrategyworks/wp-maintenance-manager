# Site Maintenance Manager

**Version:** 1.5.8  
**Author:** [Tony Zeoli](https://digitalstrategyworks.com)  
**License:** [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)  
**Requires WordPress:** 5.8+  
**Requires PHP:** 8.0+  
**Tested up to:** WordPress 6.7

A professional WordPress maintenance plugin for developers and agencies. Manage core, plugin, and theme updates, send branded HTML email reports to clients, and configure reliable SMTP email delivery — all from a single, purpose-built admin dashboard.

---

## Table of Contents

1. [What It Does](#what-it-does)
2. [Features](#features)
3. [Installation](#installation)
4. [Plugin Pages & Usage Guide](#plugin-pages--usage-guide)
   - [Dashboard](#dashboard)
   - [Updates](#updates)
   - [Update Log](#update-log)
   - [Email Reports](#email-reports)
   - [Settings](#settings)
5. [SMTP Setup Guides](#smtp-setup-guides)
   - [WordPress Default](#wordpress-default)
   - [Manual SMTP](#manual-smtp)
   - [SendGrid](#sendgrid)
   - [Mailgun](#mailgun)
   - [Brevo (Sendinblue)](#brevo-formerly-sendinblue)
   - [SendLayer](#sendlayer)
   - [SMTP.com](#smtpcom)
   - [Gmail / Google Workspace](#gmail--google-workspace)
   - [Microsoft / Outlook / Office 365](#microsoft--outlook--office-365)
6. [Database Schema](#database-schema)
7. [Multisite / Network Support](#multisite--network-support)
8. [Frequently Asked Questions](#frequently-asked-questions)
9. [Changelog](#changelog)

---

## What It Does

Site Maintenance Manager replaces the ad hoc workflow of tab-switching between the WordPress Updates screen, a spreadsheet, and an email client. It gives you one panel to:

- **Run updates** for WordPress Core, all plugins, and all themes — in separate, clearly labelled sections with per-item checkboxes, inline success/failure feedback, and plain-English error explanations
- **Log every result** automatically to a searchable, paginated history
- **Send a branded HTML report** to the client with one click, built automatically from that session's log entries
- **Configure reliable email delivery** via any of nine supported SMTP providers, without needing a separate plugin

---

## Features

### Dashboard
- Status summary cards: last update date/time, client email, default administrator, agency logo and name
- Weekly report subject line builder with date picker
- Quick-navigation tiles to all five plugin pages

### Updates
- Three separate sections: WordPress Core, Plugins, Themes
- Per-item checkboxes plus Select All per section
- Batch update (Update Selected) and individual item update
- Per-session administrator override — select any WordPress admin for this session only
- Inline success/failure status after each update
- 24-entry error code dictionary with plain-English failure explanations (license required, update server unreachable, disk write failure, etc.)
- Global success banner on batch completion with link to send the report
- Refresh button re-scans without page reload

### Update Log
- Full history of every update action, grouped into sessions
- Sessions shown in a collapsible accordion, most recent open by default
- Live search autocomplete: start typing an item name for instant suggestions
- Date range filtering (From / To)
- Per-page selector: 20, 50, or 100 sessions
- Previous / Next pagination at both top and bottom of the list
- Refresh button for immediate reload
- Database Diagnostic panel with column health indicators and Force DB Upgrade Now button

### Email Reports
- Branded HTML email built automatically from the current update session
- Email header: Site Name + URL at top; agency logo + company name inline; "WordPress website updates administered by [Admin Name]"
- Sectioned tables: WordPress Core, Plugins, Themes — each with item name, versions, and status
- Sent Email History with preview modal and resend button
- Preview and Resend always rebuild the email body from original log entries using the current template
- Session tracking persists across page navigation (Updates → Email Reports)

### Settings — Company & Branding
- Upload company logo via WordPress media library (saved, encrypted, displayed in header and emails)
- Company name inline-edit
- Both appear in plugin header on every page and in email templates

### Settings — Client Contact
- Client email address inline-edit
- Pre-populates everywhere: Updates page notice, Email Reports form, email From: header

### Settings — Site Administrators
- Table of all WordPress Administrators with Gravatar, name, username, email, registration date
- Radio to select the default performing administrator
- Default appears in email reports and email From: header; overridable per session on Updates page

### Settings — SMTP & Email Delivery
- Nine provider tiles: WordPress Default, Manual SMTP, SendGrid, Mailgun, Brevo, SendLayer, SMTP.com, Gmail, Microsoft
- Pre-configured server details for all named providers
- Context-sensitive help panel with step-by-step setup instructions per provider
- AES-256-CBC encryption for stored passwords and API keys (key derived from AUTH_KEY + SECURE_AUTH_KEY)
- Stored credentials never exposed in HTML — only a masked placeholder
- From Name and From Email fields
- Send Test Email with real-time pass/fail reporting

### Avada Theme Support
- Detects when the Avada theme is installed and shows a contextual update-order notice
- Lists any pending Avada Core / Avada Builder updates by name with new version numbers
- Confirmation prompt when Avada theme is selected for update, reminding about companion plugin order
- Direct link to Avada's Maintenance → Plugins & Add-Ons dashboard for checking Avada Patches (which are managed outside the standard WordPress update API)

### Multisite / Network
- Works on both single-site and Multisite networks
- Network-activate to provision all sub-sites simultaneously
- Each sub-site has its own isolated update log and email log
- Cross-site AJAX uses `switch_to_blog()` to always read from the correct table

---

## Installation

### From WordPress admin (recommended)

1. Go to **Plugins → Add New → Upload Plugin**
2. Upload `site-maintenance-manager.zip`
3. Click **Install Now** then **Activate Plugin**
4. Navigate to **Site Maintenance** in the left admin menu

### For Multisite / Network

1. Log in as Super Admin → **Network Admin → Plugins → Add New → Upload Plugin**
2. Upload and install
3. Click **Network Activate** — all existing sites are provisioned immediately; new sites are provisioned automatically

### Manual

1. Unzip and upload the `site-maintenance-manager` folder to `/wp-content/plugins/`
2. Activate from the WordPress Plugins screen

### First-time setup

After activation:
1. Go to **Site Maintenance → Settings**
2. Upload your company logo and enter your company name
3. Enter the client email address
4. Select the default administrator who performs updates
5. Configure your SMTP provider (see [SMTP Setup Guides](#smtp-setup-guides))
6. Use **Send Test Email** to verify delivery

---

## Plugin Pages & Usage Guide

### Dashboard

The Dashboard gives you a quick health check of the site maintenance workflow:

- **Most Recent Update** — date and time of the last update session
- **Client Email** — the address reports go to, with a link to edit in Settings
- **Default Administrator** — who performed the last session, with a change link
- **Agency** — your logo and company name (shown only when configured)

The **Weekly Report Configuration** section has a date picker to build the email subject line: `[Site Name] [URL] Weekly WordPress Upgrades and Maintenance for week of: [Date]`.

Quick-navigation tiles link to Updates, Update Log, Email Reports, and Settings.

---

### Updates

**Running a batch update:**

1. The page auto-loads available updates on arrival. Click **Refresh Updates** to re-scan.
2. Use the **Performing Administrator** dropdown to select who ran this session. This pre-fills from your Settings default but can be changed per session.
3. Check items you want to update. Each section (Core, Plugins, Themes) has a **Select All** checkbox.
4. Click **Update Selected**. Items update one at a time with live feedback:
   - ✅ **Update Successful** — item updated, new version shown
   - ❌ **Update Failed** — error description shown inline (e.g. "License Required: This plugin requires a valid license key for automatic updates")
5. The global success banner appears when all selected items complete. Click **Send Report Email** to go directly to the Email Reports page.

**Single-item update:** Click the **Update** button next to any individual item.

**Error codes:** The plugin maps 24 known WordPress updater error codes to plain-English labels, detail explanations, and recommended actions. These appear in both the UI and the email report.

---

### Update Log

Sessions are grouped in a collapsible accordion ordered newest-first. Each session header shows:
- Date and time the session began
- Success count (green pill), failure count (red pill), total items count (grey pill)

Clicking a session header expands it to show a table of every item updated in that session: item name, type, version change, status badge, and any error notes.

**Search autocomplete:** Start typing in the search field (minimum 2 characters). A dropdown appears with matching item names from the full update history. Click a suggestion or press Enter to search. Arrow keys navigate the list; Escape closes it.

**Date range filter:** Use the From and To date pickers to show only sessions within a period.

**Per-page selector:** Choose Last 20, 50, or 100 sessions from the dropdown in the card header.

**Pagination:** Previous and Next links appear at the top and bottom of the session list showing "Sessions X–Y of Z".

**Database Diagnostic panel** (expandable, at bottom of page): Shows both database tables (`wpmm_update_log` and `wpmm_email_log`) with:
- Column names highlighted green (present) or red (missing)
- Row counts
- 5 most recent rows from each table
- **Force DB Upgrade Now** button — use this if you upgraded the plugin by uploading files and sessions are missing. It runs the full schema migration immediately.

---

### Email Reports

**Sending a report:**

1. After running updates, navigate to **Email Reports**. The plugin automatically selects the most recent session — you will see "Updates from session on [date]" in the Email Template section.
2. The recipient is pre-filled from Settings (or enter one manually if not set).
3. Edit the subject line if needed.
4. Click **Send Report Email**.

**Sent Email History table columns:** Sent At, To, Subject, Status (Sent / Failed), Preview (eye icon), Resend.

**Preview modal:** The eye icon opens the full rendered email in a modal. Even emails sent months ago will show the current template and branding because the preview rebuilds the body from the original log entries each time.

**Resend:** Rebuilds the email from the original session entries and sends it again. This means resent emails reflect the current template design even if the template has changed since the original send.

---

### Settings

Settings has four cards. Each can be configured independently.

**Company & Branding**
- **Logo** — Click **Upload Logo** to open the WordPress media library. Select or upload an image. The logo saves automatically on selection. Recommended: PNG or SVG, at least 300px wide, transparent background. The logo is rendered white in the plugin header (via CSS) and in email headers.
- **Company Name** — Displayed in the plugin header and in email reports. Click Edit to change it, Save to confirm, Cancel to discard.

**Client Contact**
- **Client Email Address** — Click Edit to enter or change. This address pre-populates the Email Reports send form and is shown in the Updates page notice.

**Site Administrators**
- A table of all WordPress Administrators. Click the radio button next to an administrator and click **Save Default Administrator** to set the default performing admin. This name and email appear in email reports and in the SMTP From: header.

**SMTP & Email Delivery** — see [SMTP Setup Guides](#smtp-setup-guides) below.

---

## SMTP Setup Guides

Go to **Settings → SMTP & Email Delivery** and click the tile for your chosen provider.

### WordPress Default

No configuration needed. WordPress uses PHP's `mail()` function. Unreliable on most shared hosting — use a named provider for anything important.

---

### Manual SMTP

Use any SMTP server not listed as a named provider.

| Field | Description |
|-------|-------------|
| SMTP Host | Your mail server address (e.g. `mail.yourdomain.com`) |
| Port | `587` (TLS) — recommended; `465` (SSL); `25` (none) |
| Encryption | TLS/STARTTLS for port 587; SSL for port 465 |
| Username | Your SMTP login (usually your email address) |
| Password | Your SMTP password |
| From Name | Display name on outgoing emails |
| From Email | Must be authorised by your SMTP server |

---

### SendGrid

**Free plan:** 100 emails/day. No credit card required.

**Server:** `smtp.sendgrid.net` · Port `587` · TLS *(pre-configured)*

1. Create a free account at [sendgrid.com](https://sendgrid.com)
2. Complete **Sender Identity** verification (domain authentication recommended, or single sender for testing)
3. Go to **Settings → API Keys → Create API Key**
4. Choose **Restricted Access** → **Mail Send → Full Access**
5. Copy the API key (shown once only)
6. In plugin Settings: **Username** = `apikey` (literally, that exact text); **Password** = the API key you copied
7. **From Email** = your verified sender address

---

### Mailgun

**Free tier:** 5,000 emails/month for 3 months, then pay-as-you-go.

**Server:** `smtp.mailgun.org` · Port `587` · TLS *(pre-configured)*

1. Create an account at [mailgun.com](https://mailgun.com)
2. Add and verify your sending domain under **Sending → Domains**
3. Go to **Sending → Domain Settings → SMTP credentials**
4. Note the SMTP login (e.g. `postmaster@yourdomain.com`) and generate/copy the password
5. In plugin Settings: **Username** = your SMTP login; **Password** = the SMTP password
6. **From Email** = a verified sender address in Mailgun

> **Note:** Mailgun's free tier restricts delivery to verified recipient addresses. Add recipients under **Sending → Overview → Authorised Recipients**.

---

### Brevo (formerly Sendinblue)

**Free plan:** 300 emails/day, unlimited contacts.

**Server:** `smtp-relay.brevo.com` · Port `587` · TLS *(pre-configured)*

1. Create a free account at [brevo.com](https://brevo.com)
2. Click your name (top-right) → **SMTP & API → SMTP tab**
3. Note your **Login** (your Brevo account email address)
4. Click **Generate a new SMTP Key** and copy the key
5. In plugin Settings: **Username** = your Brevo login email; **Password** = the SMTP key
6. **From Email** = a sender address you have verified in Brevo

---

### SendLayer

**Pricing:** Paid plans; free trial available.

**Server:** `smtp.sendlayer.net` · Port `587` · TLS *(pre-configured)*

1. Sign up at [sendlayer.com](https://sendlayer.com) and add your sending domain
2. In your SendLayer dashboard, copy your **SMTP Username** and **SMTP Password**
3. In plugin Settings: enter those credentials; set a verified address as **From Email**

---

### SMTP.com

**Free trial:** 50,000 emails.

**Server:** `send.smtp.com` · Port `587` · TLS *(pre-configured)*

1. Create an account at [smtp.com](https://smtp.com)
2. Go to **Sender → SMTP credentials**
3. Copy your **Sender Name** (Username) and **API Key** (Password)
4. In plugin Settings: **Username** = Sender Name; **Password** = API Key
5. **From Email** = your verified sender address

---

### Gmail / Google Workspace

**Server:** `smtp.gmail.com` · Port `587` · TLS *(pre-configured)*

> **Important:** Google disabled plain password (basic auth) for SMTP in May 2022. An App Password is required. OAuth 2.0 is not supported by this plugin.

#### Personal Gmail

1. Go to [myaccount.google.com](https://myaccount.google.com) → **Security**
2. Confirm **2-Step Verification** is enabled (required for App Passwords)
3. In the Security search bar, search for **App Passwords**
4. Click **Create**, choose **Other (custom name)**, enter `WordPress`
5. Google shows a **16-character code** — copy it immediately (shown once only)
6. In plugin Settings: **Username** = `you@gmail.com`; **Password** = the 16-character code
7. **From Email** = your Gmail address

#### Google Workspace (paid)

The App Password method works identically for Workspace accounts. As an alternative, your Workspace admin can configure a **SMTP relay service** (no App Passwords needed, supports higher volume):

1. Workspace Admin Console → **Apps → Google Workspace → Gmail → SMTP relay service**
2. Add a relay for your domain
3. Use Manual SMTP option in the plugin with the relay host provided by Google

**Sending limits:** Personal Gmail ~500 emails/day; Google Workspace ~2,000/day.

---

### Microsoft / Outlook / Office 365

**Server:** `smtp.office365.com` · Port `587` · TLS *(pre-configured)*

> **Important:** Microsoft deprecated basic auth for Exchange Online in October 2022 but retained SMTP AUTH specifically. App Passwords are required for personal accounts; organisation accounts need SMTP AUTH enabled by an admin.

#### Personal Outlook.com / Hotmail accounts

1. Go to [account.microsoft.com/security](https://account.microsoft.com/security)
2. Confirm **Two-step verification** is enabled under **Advanced security options**
3. Click **Create a new app password**
4. Copy the generated password
5. In plugin Settings: **Username** = `you@outlook.com`; **Password** = the app password
6. **From Email** = your Outlook address

#### Microsoft 365 / Office 365 organisations

1. A Microsoft 365 admin must enable SMTP AUTH for the sending mailbox:
   **Microsoft 365 Admin Centre → Users → Active Users → [select user] → Mail tab → Manage email apps → check Authenticated SMTP**
2. Once enabled: **Username** = `you@yourcompany.com`; **Password** = your regular Microsoft 365 password
3. If your organisation enforces MFA, generate an App Password at [mysignins.microsoft.com](https://mysignins.microsoft.com) → **Security info → Add method → App password**, and use that instead

**Troubleshooting:** If `smtp.office365.com` does not connect for a personal Outlook.com account, use the Manual SMTP option with host `smtp-mail.outlook.com` on port `587`.

---

## Database Schema

The plugin creates two tables per WordPress site (or sub-site on Multisite):

### `{prefix}_wpmm_update_log`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `session_id` | VARCHAR(64) | Groups all items from one update run |
| `item_name` | VARCHAR(255) | Plugin, theme, or "WordPress" |
| `item_type` | VARCHAR(20) | `core`, `plugin`, or `theme` |
| `item_slug` | VARCHAR(255) | WordPress slug or plugin file path |
| `old_version` | VARCHAR(50) | Version before update |
| `new_version` | VARCHAR(50) | Version after update |
| `status` | VARCHAR(20) | `success` or `failed` |
| `error_code` | VARCHAR(100) | Raw WP_Error code (mapped to plain English in UI) |
| `message` | TEXT | Additional context |
| `updated_at` | DATETIME | When the update ran |

### `{prefix}_wpmm_email_log`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `session_id` | VARCHAR(64) | Links to the update session this email covers |
| `to_email` | VARCHAR(255) | Recipient address |
| `subject` | VARCHAR(500) | Email subject line |
| `body` | LONGTEXT | Full HTML email body |
| `status` | VARCHAR(20) | `sent` or `failed` |
| `sent_at` | DATETIME | When the email was sent |

### Settings storage

All plugin settings (company name, logo URL, client email, default admin ID, SMTP configuration) are stored as a single serialised array in the `wpmm_settings` WordPress option. The SMTP password/API key is stored AES-256-CBC encrypted.

---

## Multisite / Network Support

- **Activation:** Network-activate to provision all existing sub-sites at once, or activate per-site individually
- **Provisioning:** New sub-sites created after network activation are provisioned automatically via `wp_initialize_site`
- **Isolation:** Each sub-site has its own `wpmm_update_log` and `wpmm_email_log` tables with its own prefix
- **Capabilities:** `manage_network` is required in Network Admin context; `manage_options` on per-site
- **AJAX:** Email sending correctly identifies the originating sub-site via `wpmm_last_session.blog_id` and switches to that blog's database context before querying
- **Settings:** Currently shared via `wpmm_settings` — to use different branding per site, activate per-site rather than network-activating

---

## Frequently Asked Questions

**Do I need a separate SMTP plugin?**
No. Site Maintenance Manager includes built-in SMTP configuration. If you already use WP Mail SMTP, FluentSMTP, or Post SMTP, leave this plugin's SMTP setting on WordPress Default to avoid conflicts.

**My updates are not appearing in the Update Log.**
If you upgraded the plugin by uploading files (without deactivating first), the database may not be fully upgraded. Open Update Log, expand the Database Diagnostic panel, and click Force DB Upgrade Now.

**Can I update only plugins, or only themes?**
Yes. Each section on the Updates page has its own Select All checkbox. You can select and update any combination of Core, Plugins, and Themes independently.

**Are SMTP credentials stored securely?**
Yes. Passwords and API keys are encrypted with AES-256-CBC. The encryption key is derived from your site's `AUTH_KEY` and `SECURE_AUTH_KEY` constants defined in `wp-config.php`. The raw value is never written to HTML or shown in the browser.

**The email preview shows no plugins/themes.**
This happens for emails sent before version 1.4.1 when the template had an interpolation bug. Resend the email — it will be rebuilt from the original log entries using the current template.

**Can I customise the email template?**
The email template is defined in `includes/email.php`. It uses inline styles for email client compatibility. The header automatically reflects your configured logo, company name, and administrator. For deeper customisation, fork the file and modify `wpmm_build_email_body()`.

---

## Changelog

### 1.4.5
- Added Gmail / Google Workspace SMTP (smtp.gmail.com:587, App Password required)
- Added Microsoft / Outlook / Office 365 SMTP (smtp.office365.com:587)
- Step-by-step setup instructions for both in the Settings help panel
- Username hints update contextually per provider

### 1.4.4
- New: SMTP & Email Delivery card in Settings
- Nine mailer tiles: WordPress Default, Manual SMTP, SendGrid, Mailgun, Brevo, SendLayer, SMTP.com, Gmail, Microsoft
- AES-256-CBC encryption for stored credentials
- Send Test Email with real-time pass/fail reporting

### 1.4.3
- Email header: Site Name/URL top, logo + company name inline, administered-by statement
- Update Log: per-page selector (20/50/100) and Previous/Next pagination at top and bottom

### 1.4.2
- Fix: "No update entries found" email bug — session ID resolution now uses a dedicated flag
- Fix: Preview modal now shows full content — rebuilds from log entries, not stale HTML
- Logo in email header reduced 50%

### 1.4.1
- Fix: Plugins/themes missing from email body — string concatenation replaces interpolation
- Email header reordered per spec

### 1.4.0
- Critical fix: DB schema upgrade now runs reliably on all install paths
- INFORMATION_SCHEMA column checks replace fragile SHOW COLUMNS
- Database Diagnostic panel with Force DB Upgrade Now button

### 1.3.9
- Critical fix: SQL_NO_CACHE removed — caused fatal error on MySQL 8.0+
- Live autocomplete search on Update Log

### 1.3.0
- New: Settings page (logo, company name, client email, default administrator)
- Email template: agency branding, administrator attribution, sectioned Core/Plugins/Themes tables

### 1.2.2
- Initial release

---

## License

GPL-2.0+ — see [LICENSE](https://www.gnu.org/licenses/gpl-2.0.html)
