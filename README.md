# Greenskeeper

**Version:** 1.9.1  
**Author:** [Tony Zeoli](https://digitalstrategyworks.com)  
**License:** [GPL-2.0+](https://www.gnu.org/licenses/gpl-2.0.html)  
**Copyright:** © 2026 Digital Strategy Works LLC  
**Requires WordPress:** 5.8+  
**Requires PHP:** 8.0+  
**Tested up to:** WordPress 6.9

A professional WordPress maintenance plugin for developers and agencies — named after the greenskeeper who maintains the golf course to an exacting standard so players never think about what's underneath. Manage core, plugin, and theme updates, filter comment spam, send branded HTML email reports, and configure SMTP delivery — all from one dashboard. Full Multisite support with per-site scope selection.

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
5. [Spam Filter & Comments](#spam-filter--comments)
6. [SMTP Setup Guides](#smtp-setup-guides)
   - [WordPress Default](#wordpress-default)
   - [Manual SMTP](#manual-smtp)
   - [SendGrid](#sendgrid)
   - [Mailgun](#mailgun)
   - [Brevo (Sendinblue)](#brevo-formerly-sendinblue)
   - [SendLayer](#sendlayer)
   - [SMTP.com](#smtpcom)
   - [Gmail / Google Workspace](#gmail--google-workspace)
   - [Microsoft / Outlook / Office 365](#microsoft--outlook--office-365)
7. [Database Schema](#database-schema)
8. [Multisite / Network Support](#multisite--network-support)
9. [Frequently Asked Questions](#frequently-asked-questions)
10. [Changelog](#changelog)

---

## What It Does

Greenskeeper replaces the ad hoc workflow of tab-switching between the WordPress Updates screen, a spreadsheet, and an email client. It gives you one panel to:

- **Run updates** for WordPress Core, all plugins, and all themes — in separate, clearly labelled sections with per-item checkboxes, a real-time progress bar, and plain-English error explanations
- **Log every result** automatically to a searchable, paginated history
- **Send a branded HTML report** to the client with one click, built automatically from that session's log entries, with optional Update Notes and Additional Manual Updates sections
- **Filter comment spam** using layered local rules and optional Akismet cloud filtering, with a one-toggle option to disable comments entirely
- **Configure reliable email delivery** via any of nine supported SMTP providers, without needing a separate plugin

---

## Features

### Dashboard
- Status summary cards: last update date/time, client email, default administrator, agency logo and name
- Weekly report subject line builder with date picker
- Quick-navigation tiles to all six plugin pages (Spam Log tile appears when spam filtering is enabled)

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
- **Update Notes** — optional free-text note appended to the email above the footer
- **Additional Manual Updates** — repeater field to document plugins updated outside the plugin (e.g. licensed plugins); rendered as a fourth table section in the email
- **Report Week-Ending Date** picker appends "for week of: [date]" to the subject line
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

### Settings — Spam Filter & Comments
- **Master spam filter toggle** — enable or disable all spam filtering with one switch
- **Disable Comments** — remove comment support from all post types, close all existing comments, hide the Comments admin menu, and remove discussion meta boxes from the editor; one toggle, site-wide
- **Local filtering** (always active when spam filter is on):
  - Honeypot hidden field — catches bots that fill every visible field
  - Minimum submission time — rejects comments submitted faster than a human can type
  - Maximum links per comment — configurable threshold, default 3
  - Keyword blocklist — checked against comment content, author name, and URL
  - IP blocklist — block specific IP addresses before any other check
  - Duplicate comment detection — blocks the same comment from the same IP within a rolling 1-hour window
- **Akismet cloud filtering** (optional, Layer 2):
  - Enter your Akismet API key to enable AI-powered cloud spam detection
  - Verify & Save button confirms the key against Akismet's API before storing
  - Automatically skipped when the standalone Akismet plugin is detected
  - Fails open on API errors — legitimate comments are never lost due to an outage
  - Revoke Key button removes the key and disables cloud filtering immediately
- **Akismet licensing:** free for personal non-commercial sites only; commercial and client sites require a paid plan at [akismet.com/plans](https://akismet.com/plans/)

### Settings — Remote API Access
- Generate a secret API key to allow a remote hub site to manage this spoke site
- REST API endpoints under `smm/v1` namespace: status, updates, run update, log, send report, rotate key
- Copy key to clipboard, rotate (invalidates previous key), or revoke entirely
- Full endpoint reference table shown inline when a key is active

### Spam Log
- Full history of every blocked comment attempt — locally-filtered and Akismet-blocked
- All-time stats card showing blocked count per rule (Honeypot, Too Fast, Blocked IP, Keyword, Too Many Links, Duplicate, Akismet)
- Filter by rule type or IP address
- Per-row **Block IP** button — adds the IP to the Settings blocklist instantly
- Per-row **Delete** button; bulk **Delete Selected**; **Clear All**
- Pagination: 25 entries per page with Previous/Next

### Avada Theme Support
- Detects when the Avada theme is installed and shows a contextual update-order notice
- Lists any pending Avada Core / Avada Builder updates by name with new version numbers
- Confirmation prompt when Avada theme is selected for update, reminding about companion plugin order
- Direct link to Avada's Maintenance → Plugins & Add-Ons dashboard for checking Avada Patches (which are managed outside the standard WordPress update API)
- **External update detection:** Avada Core and Avada Builder updates applied through the Avada plugins dashboard are automatically detected via `upgrader_process_complete` and logged as External sessions
- **Avada Patches** use Avada's proprietary update mechanism and cannot be auto-detected — use the Additional Manual Updates field to document these

### Manage Plugin Access
- Restrict the plugin to specific administrator accounts — client admins see nothing
- `wpmm_access` custom WordPress capability gates every page, menu item, and AJAX handler
- Checkbox table in Settings lists every site administrator with their avatar, name, email, and username
- Current user is always locked in — cannot accidentally self-revoke
- Falls back to `manage_options` on fresh installs so no lockout occurs on upgrade
- Dismissible 2FA notice on all plugin pages when no 2FA plugin is detected, with direct install links

### Multisite / Network
- Works on both single-site and Multisite networks
- Network-activate to provision all sub-sites simultaneously
- Each sub-site has its own isolated update log, email log, and spam log
- Cross-site AJAX uses `switch_to_blog()` to always read from the correct table
- **Site Scope Bar** on Updates, Email Reports, Spam Log, and Settings (Spam Filter card) when in Network Admin
- Dropdown lists every registered site; selecting one scopes all operations to that site
- **Updates per-site mode:** filters to plugins/themes activated on the selected site only
- **Spam Filter per-site settings:** All Sites view shows a summary table; selecting a site loads and saves that site's settings independently
- **Network email report:** All Sites mode builds a consolidated report with a section per site
- **Spam Log per-site:** selecting a site shows only that site's blocked attempts

---

## Installation

### From WordPress admin (recommended)

1. Go to **Plugins → Add New → Upload Plugin**
2. Upload `greenskeeper.zip`
3. Click **Install Now** then **Activate Plugin**
4. Navigate to **Site Maintenance** in the left admin menu

### For Multisite / Network

1. Log in as Super Admin → **Network Admin → Plugins → Add New → Upload Plugin**
2. Upload and install
3. Click **Network Activate** — all existing sites are provisioned immediately; new sites are provisioned automatically

### Manual

1. Unzip and upload the `greenskeeper` folder to `/wp-content/plugins/`
2. Activate from the WordPress Plugins screen

### First-time setup

After activation:
1. Go to **Site Maintenance → Settings**
2. Upload your company logo and enter your company name
3. Enter the client email address
4. Select the default administrator who performs updates
5. Configure your SMTP provider (see [SMTP Setup Guides](#smtp-setup-guides))
6. Use **Send Test Email** to verify delivery
7. Optionally enable spam filtering and configure local rules or add an Akismet API key

---

## Plugin Pages & Usage Guide

### Dashboard

The Dashboard gives you a quick health check of the site maintenance workflow:

- **Most Recent Update** — date and time of the last update session
- **Client Email** — the address reports go to, with a link to edit in Settings
- **Default Administrator** — who performed the last session, with a change link
- **Agency** — your logo and company name (shown only when configured)

The **Weekly Report Configuration** section has a date picker to build the email subject line: `[Site Name] [URL] Weekly WordPress Upgrades and Maintenance for week of: [Date]`.

Quick-navigation tiles link to Updates, Update Log, Email Reports, Spam Log (when spam filtering is enabled), and Settings.

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

### Spam Log

All comment attempts blocked by any filter rule are recorded here — locally-filtered and Akismet-blocked alike.

**Reading the stats card:** Shows a count tile per rule type and a Total Blocked tile. These are all-time cumulative counts since spam filtering was activated.

**Filtering the table:** Use the **Rule** dropdown to show only a specific rule's entries. Use the **IP** field to show only entries from one address. Click **Apply** to filter, **× Clear** to reset.

**Block IP:** Clicking **Block IP** on any row adds that IP to the blocklist in Settings immediately. The button gives instant feedback confirming the add (or noting it was already listed).

**Deleting entries:** Check individual rows and click **Delete Selected**, or click **Clear All** to wipe the log. Neither action affects WordPress's Comments screen — it only removes records from the plugin's own spam log table.

**Akismet entries:** Comments caught by Akismet appear in this log with the rule shown as "Akismet". They also continue to appear in **WordPress Admin → Comments → Spam** as they normally would.

---

### Settings — Manage Plugin Access

Go to **Site Maintenance → Settings** and scroll to the **Manage Plugin Access** card.

The table lists every WordPress Administrator on this site. Check the accounts that should have access to Greenskeeper and uncheck any that should not — for example, a client's administrator account.

Click **Save Access Settings**. Changes take effect immediately. Unchecked users will no longer see the Site Maintenance menu item or be able to reach any plugin page.

Your own account always remains checked and cannot be unchecked. If you need to remove your own access, another authorized administrator must do it.

**Recommending 2FA:** A dismissible notice appears on all plugin pages when no 2FA plugin is active on the site. Click the links in the notice to install WP 2FA or Two Factor directly from the WordPress plugin repository.

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

## Spam Filter & Comments

### Enabling Spam Filtering

1. Go to **Site Maintenance → Settings** and scroll to the **Spam Filter & Comments** card.
2. Toggle **Spam Filter** on. Local filtering activates immediately — no further configuration required.
3. Optionally configure the local filter thresholds: minimum submission time, maximum links, keyword blocklist, IP blocklist.
4. Click **Save Spam Settings**.

### Adding Akismet Cloud Filtering

1. Get an API key from [akismet.com](https://akismet.com). Personal non-commercial sites can use the free plan; commercial and client sites require a paid plan.
2. In the **Akismet Cloud Filtering** section, paste your key into the API Key field.
3. Click **Verify & Save Key**. The plugin checks the key against Akismet's servers before saving.
4. A green "Connected ✓" badge appears when the key is active.
5. To remove the key later, click **Remove Key**.

> **Note:** If the standalone Akismet plugin is already active on this site, the Settings page shows a notice and the plugin skips its own Akismet API call automatically. Only local filtering runs alongside the standalone plugin.

### Disabling Comments Site-Wide

1. In the **Spam Filter & Comments** card, toggle **Disable Comments** on.
2. Click **Save Spam Settings**.
3. Comment forms are immediately removed from all post types, all existing comments are closed, the Comments admin menu is hidden, and discussion meta boxes are removed from the editor.

> **Note:** Disabling comments does not delete existing comment data — it only prevents new comments and hides the comment UI. Re-enabling the toggle restores full comment functionality.

### Akismet Commercial Licensing Notice

Akismet's free plan is restricted to personal, non-commercial websites with no advertising, no products for sale, and no services offered. Every commercial website — including client sites managed on retainer by a web agency — is required to use a paid Akismet plan.

Greenskeeper provides the Akismet API integration. Compliance with Akismet's terms of service is the responsibility of the site owner. Visit [akismet.com/plans](https://akismet.com/plans/) to review plan options.

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

### `{prefix}_wpmm_spam_log`

| Column | Type | Description |
|--------|------|-------------|
| `id` | BIGINT | Primary key |
| `blocked_at` | DATETIME | When the attempt was blocked |
| `rule` | VARCHAR(50) | Rule that triggered: `honeypot`, `too_fast`, `blocked_ip`, `keyword`, `too_many_links`, `duplicate`, `akismet` |
| `author_ip` | VARCHAR(100) | Submitter's IP address |
| `author_name` | VARCHAR(255) | Comment author name |
| `author_email` | VARCHAR(255) | Comment author email |
| `author_url` | VARCHAR(500) | Comment author URL |
| `comment_content` | TEXT | Comment body (full text) |
| `post_id` | BIGINT | ID of the post the comment was submitted on |

### Settings storage

All plugin settings (company name, logo URL, client email, default admin ID, SMTP configuration, spam filter configuration, Akismet API key, REST API key) are stored as a single serialised array in the `wpmm_settings` WordPress option. The SMTP password/API key is stored AES-256-CBC encrypted.

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

### Can I review blocked spam comments?

Yes. Go to **Site Maintenance → Spam Log**. Every comment blocked by any local filter rule is recorded there with the timestamp, rule that triggered, IP address, author details, and a content preview. Akismet-blocked comments are logged here too and also appear in WordPress's native Comments → Spam queue.

From the page you can filter by rule or IP, add an IP to the blocklist with one click, delete individual entries, or clear the entire log.

### Where are spam log entries stored?

In the `{prefix}_wpmm_spam_log` database table, created automatically when the plugin activates or upgrades. Entries are never deleted automatically — use the **Delete Selected** or **Clear All** controls on the Spam Log page to manage storage.

### Who can access Greenskeeper?

By default (on a fresh install), every WordPress Administrator can access the plugin. Once you save the Manage Plugin Access card in Settings, only explicitly checked administrators can see the plugin. All others — including client admins — see no menu item and cannot reach any plugin page.

### Can a client with Administrator access see the plugin?

Not after you configure Manage Plugin Access. Go to **Settings → Manage Plugin Access**, uncheck the client's administrator account, and click **Save Access Settings**. That account will no longer see the Site Maintenance menu.

### What if I get locked out of the plugin?

Lockout from within the plugin UI is impossible — your own account is always kept in the access list. If locked out through a direct database change, delete the `wpmm_settings` option (resets to the `manage_options` fallback) or add your user ID back to the `access_user_ids` array in that option.

### Does the plugin support two-factor authentication?

The plugin doesn't implement 2FA itself — it detects whether a 2FA plugin is active and shows a dismissible notice with install links if none is found. We recommend protecting `wpmm_access` accounts with [WP 2FA](https://wordpress.org/plugins/wp-2fa/) or [Two Factor](https://wordpress.org/plugins/two-factor/).

### How does Greenskeeper handle Multisite networks?

In Network Admin, a Site Scope Bar appears at the top of the Updates, Spam Log, and Settings pages. Choose a specific site to scope all operations to that site, or select "All Sites" for the full network view.

### What does single-site scope do on the Updates page?

The plugin and theme lists are filtered to only items activated on the selected site (including network-activated plugins). Updates run in that site's context and log to that site's own `wpmm_update_log` table.

### Are spam filter settings shared across a Multisite network?

No. Each site has independent spam settings. In Network Admin, the Settings page Spam Filter card shows an overview table when "All Sites" is selected, and the full configuration form when a specific site is chosen.

### How do spam filter settings work on Multisite?

Each sub-site has its own independent spam settings. From Network Admin, go to **Settings → Spam Filter & Comments**. The All Sites view shows a summary of every site. Select a site from the Site Scope Bar to edit its settings — changes only affect that site.

### Can I run updates for all sites at once or one at a time?

Both. In Network Admin, the Site Scope Bar on the Updates page defaults to All Sites (all installed updates). Selecting a site filters to that site's activated plugins and themes only, and runs updates in that site's context.

### Does the network email report cover all sites?

Yes. When the Email Reports page is in All Sites scope, the report is a consolidated email with a per-site section. When a single site is selected, the report covers only that site in the standard format.

### Does Greenskeeper report updates made outside the plugin?

Yes, from v1.9.1. Greenskeeper hooks into `upgrader_process_complete` which fires for any update going through WordPress's standard `Plugin_Upgrader` or `Theme_Upgrader`. This includes the WordPress Updates screen and the Avada plugins dashboard (Avada Core, Avada Builder). External updates are logged automatically with an "External" session badge and appear in the next report email.

### How does Greenskeeper handle Avada updates?

Two scenarios:

**Avada theme, Avada Core, Avada Builder** — updates through the standard WordPress mechanism (including the Avada plugins dashboard) are auto-detected and logged.

**Avada Patches** — applied through Avada's Maintenance → Plugins & Add-Ons dashboard using Avada's proprietary update system. These do not fire WordPress hooks and cannot be auto-detected. Document them with the Additional Manual Updates field on the Email Reports page.

### Does the spam filter work without an Akismet key?

Yes. The local filtering layer runs entirely on your server with no external API calls. It catches the majority of automated bot spam using a honeypot field, submission time check, link count limit, keyword blocklist, IP blocklist, and duplicate detection. Adding an Akismet key activates a second layer of AI-powered cloud filtering for more comprehensive coverage.

### Do I need a paid Akismet account?

Akismet's free plan is for personal, non-commercial sites only. Any commercial website — including client sites managed by a web agency — requires a paid Akismet plan. Visit [akismet.com/plans](https://akismet.com/plans/) to choose the right plan. Greenskeeper provides the integration; licensing is your responsibility.

### Will the spam filter conflict with the standalone Akismet plugin?

No. The plugin detects when the standalone Akismet plugin is active and skips its own Akismet API call automatically. Only local filtering runs alongside the standalone plugin, preventing double-filtering.

### What happens if Akismet is unreachable?

The plugin fails open — the comment is allowed through rather than blocked. This prevents legitimate comments from being lost during a temporary API outage. Local filters still run normally.

### Can I disable comments completely?

Yes. The **Disable Comments** toggle in Settings removes comment support from every post type, closes all existing comments, hides the Comments admin menu, and removes discussion meta boxes from the editor. Re-enabling the toggle restores full comment functionality. Existing comment data is preserved in both states.



**Do I need a separate SMTP plugin?**
No. Greenskeeper includes built-in SMTP configuration. If you already use WP Mail SMTP, FluentSMTP, or Post SMTP, leave this plugin's SMTP setting on WordPress Default to avoid conflicts.

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

## Copyright & Licensing

**Plugin code** is licensed under the [GNU General Public License v2.0 or later (GPL-2.0+)](https://www.gnu.org/licenses/gpl-2.0.html). You are free to use, modify, and redistribute the plugin code under the terms of that licence.

**Documentation and written content** — including the plugin description, installation and usage guides, SMTP setup guides, FAQs, and all other original prose in readme.txt, README.md, and within the plugin's admin interface — is © 2026 Digital Strategy Works LLC. All rights reserved. Reproduction or redistribution of the documentation outside the terms of the GPL as it applies to software is prohibited without prior written permission.

**Greenskeeper**, the Greenskeeper logo, and the golf-flag mark are trademarks of Digital Strategy Works LLC. Unauthorised use of the Greenskeeper name or visual identity in a manner that implies endorsement or affiliation is prohibited.

For licensing enquiries: [tony@digitalstrategyworks.com](mailto:tony@digitalstrategyworks.com)

---

## Changelog

### 1.9.1
- Feature: External update detection — updates made via WordPress Updates screen, Avada plugins dashboard, or any standard WP upgrade hook are auto-logged and included in reports
- Avada Patches (Avada proprietary mechanism) not detectable — must use Additional Manual Updates
- Fix: Themes missing from email reports (item_type normalised to accept both `theme` and `themes`)
- Fix: Update Notes and Additional Manual Updates merged into the Send Maintenance Report card
- Feature: Spam activity since last report included as a section in every maintenance email
- Feature: Administrator First Name + Last Name shown in emails (falls back to display_name)

### 1.9.0
- Rename: Greenskeeper → Greenskeeper (display only; wpmm_ internals unchanged)
- Feature: Site Scope Selector on Updates, Spam Log, and Settings in Network Admin
- Feature: Updates single-site scope filters to activated plugins/themes for that site
- Feature: Updates All Sites mode with consolidated per-site network email report
- Feature: Spam Filter per-site settings with Network Admin overview table
- Feature: wpmm_build_network_email_body() for consolidated network maintenance emails

### 1.8.0
- Feature: Manage Plugin Access — restrict the plugin to specific administrators
- New `wpmm_access` custom capability; Manage Plugin Access card in Settings
- Current user always locked in; falls back to manage_options if no access list saved
- Dismissible 2FA recommendation notice when no 2FA plugin is detected

### 1.7.0
- Feature: Spam Log page — full history of all blocked comment attempts
- New `wpmm_spam_log` database table stores every blocked attempt with rule, IP, author details, and content
- Akismet-blocked comments also logged for unified visibility
- Stats card, paginated filterable table, Block IP button, Delete, bulk delete, Clear All
- Dashboard Spam Log tile shown when spam filtering is active
- Bug fix: WPMM_SLUG_SPAM added to enqueue slugs array so plugin CSS/JS loads correctly on Spam Log page

### 1.6.0
- Feature: Spam Filter & Comments card in Settings
- Layer 1 local filtering: honeypot, submission time, IP blocklist, keyword blocklist, link count, duplicate detection
- Layer 2 Akismet cloud filtering: optional API key, verify/revoke, auto-skipped when standalone Akismet plugin is active
- Disable Comments toggle: removes comment support site-wide with one click
- Toggle switches with live label updates throughout Settings

### 1.5.9.1
- Changelog updated with all versions 1.5.1–1.5.9 that were missing. No code changes.

### 1.5.9
- Feature: REST API spoke endpoints (smm/v1) for remote hub management
- Six endpoints: GET /status, GET /updates, POST /update, GET /log, POST /send-report, POST /rotate-key
- API key authentication via X-SMM-API-Key header
- Remote API Access card in Settings with key management and endpoint reference

### 1.5.8
- Plugin Check compliance (third round): phpcs:ignore annotations moved inline on all direct DB call lines
- manual_entries JSON sanitized before json_decode()

### 1.5.7
- Feature: Update Notes card on Email Reports page — append an admin note to any report email

### 1.5.6
- Feature: Additional Manual Updates repeater on Email Reports page
- Manual entries appear as a fourth section in the email body

### 1.5.5
- Critical fix: premium plugins (ACF Pro, Gravity Forms) no longer deactivated after update
- Replaced Plugin_Upgrader::install() with transient-injection + upgrade() to preserve active state

### 1.5.4
- Plugin Check compliance (second round): inline phpcs:ignore on all direct DB call lines

### 1.5.3
- Fix: datepicker calendar on Email Reports page was rendering transparent
- Full jQuery UI datepicker CSS added to admin.css, removing external CDN dependency

### 1.5.2
- Fix: duplicate tip card on Update Log page removed
- Fix: tip card restored on Email Reports page; version bump to bust CSS cache

### 1.5.1
- Feature: Report Week-Ending Date picker moved to Email Reports page
- Feature: Full-width progress bar replaces spinning arrow on Updates page
- Fix: success banner no longer persists when new updates are available
- Feature: Tip card added to all five plugin pages (PayPal + Venmo)

### 1.5.0
- WordPress.org Plugin Check compliance: all errors and warnings resolved
- Replaced external jQuery UI CSS with WordPress bundled version
- Added wp_unslash() to all $_GET and $_POST reads
- Replaced bare integer echoes with absint(), wrapped HTML output in wp_kses_post()
- Replaced unconditional error_log() with WP_DEBUG-gated trigger_error()
- readme.txt: tags ≤ 5, short description ≤ 150 chars, Description ≤ 2500 chars

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
