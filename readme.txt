=== Site Maintenance Manager ===
Contributors:      tonyzeoli
Author:            Tony Zeoli
Author URI:        https://digitalstrategyworks.com
Tags:              maintenance, updates, smtp, email, multisite
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      8.0
Stable tag:        1.5.8
License:           GPL-2.0+
License URI:       https://www.gnu.org/licenses/gpl-2.0.html

Manage WordPress updates, send branded HTML email reports, and configure SMTP email delivery — all from one admin dashboard.

== Description ==

Site Maintenance Manager is a professional WordPress maintenance plugin for developers and agencies. It centralises update management for WordPress Core, plugins, and themes, and pairs it with a polished email reporting workflow.

**What it does:**

* Scans for available WordPress Core, plugin, and theme updates in separate sections
* Updates items individually or in batch with inline success/failure feedback and plain-English error explanations
* Logs every update action automatically — searchable by item name or date range, grouped into sessions
* Builds a branded HTML maintenance report email from each update session and sends it to your client
* Configures reliable SMTP email delivery via nine supported providers — no separate SMTP plugin required
* Manages agency branding: company logo, company name, and default administrator shown on reports
* Works on single-site WordPress installs and Multisite networks

**Supported SMTP Providers:**

SendGrid, Mailgun, Brevo, SendLayer, SMTP.com, Gmail / Google Workspace, Microsoft / Outlook / Office 365, manual SMTP, or WordPress default.

**Who it is for:**

Web developers, digital agencies, and WordPress administrators who manage client sites and need a reliable, repeatable maintenance workflow with professional client-facing reporting.

== Installation ==

= Single-Site Install =

1. In your WordPress admin go to **Plugins → Add New → Upload Plugin**.
2. Upload `site-maintenance-manager.zip` and click **Install Now**.
3. Click **Activate Plugin**.
4. Navigate to **Site Maintenance** in the left-hand admin menu.
5. Open **Settings** and configure your company branding, client email, default administrator, and SMTP delivery.

= Multisite / Network Install =

1. Log in as a Super Admin and go to **Network Admin → Plugins → Add New → Upload Plugin**.
2. Upload `site-maintenance-manager.zip` and click **Install Now**.
3. Click **Network Activate** to activate across all sites simultaneously, **or** activate per-site from each site's own Plugins screen.
4. Navigate to **Site Maintenance** in the Network Admin menu or any site's admin menu.
5. Each site has its own independent Settings, Update Log, and Email Log.

= Manual Install =

1. Unzip the archive and upload the `site-maintenance-manager` folder to `/wp-content/plugins/`.
2. Activate from the WordPress Plugins screen.

---

== Using the Plugin ==

= Running Updates =

1. Go to **Site Maintenance → Updates**.
2. The page loads and automatically scans for available updates.
3. The **Performing Administrator** dropdown at the top defaults to your saved default admin (set in Settings). Override it here for this session only.
4. Check the items you want to update — or use **Select All** per section.
5. Click **Update Selected**. Each item updates sequentially with inline feedback.
6. When all items are done, the global success banner appears with a link to **Send Report Email**.

= Sending a Report Email =

1. After running updates, go to **Site Maintenance → Email Reports**.
2. The plugin automatically selects the session you just ran — you will see "Updates from session on [date]" in the Email Template section.
3. Confirm the recipient email (pre-filled from Settings) and edit the subject line if needed.
4. Click **Send Report Email**.
5. The sent email appears in the **Sent Email History** table below.

= Previewing a Sent Email =

Click the eye icon in the Sent Email History table. The email renders in a full modal preview using the current template. Even old emails show current branding because the preview always rebuilds the body from the original log entries.

= Resending an Email =

Click **Resend** in the Sent Email History table. The email is rebuilt from the original session entries and sent again to the same recipient.

---

== Frequently Asked Questions ==

= Does the plugin support Avada theme updates? =

Yes, with important notes. Avada Core and Avada Builder appear in the standard
WordPress plugin update list once your Avada license is registered, and the plugin
can update them normally. The Updates page shows a contextual notice when Avada is
installed, explaining the required update order: Avada theme first, then Avada Core,
then Avada Builder. A confirmation prompt appears if you select the Avada theme for
update, reminding you to follow up with the companion plugins.

Avada Patches are managed separately through Avada's own dashboard (Avada →
Maintenance → Plugins & Add-Ons) and do not appear in the standard WordPress update
API, so they cannot be detected or applied from this plugin. The Updates page
includes a direct link to that dashboard so you can check after completing your
regular updates.


= Does this plugin support WordPress Multisite? =

Yes. It can be network-activated by a Super Admin to cover all sites simultaneously, or activated per-site by individual Administrators. Each site maintains its own isolated database tables, update log, and email log. The plugin handles cross-site AJAX correctly on Multisite so email sending always reads from the correct sub-site's log table.

= Why are my updates not showing in the Update Log? =

If you upgraded the plugin by uploading new files without deactivating first, the database schema may not have been upgraded. Open **Update Log**, expand the **Database Diagnostic** panel at the bottom, and click **Force DB Upgrade Now**. The panel shows both database tables with their column lists highlighted in green (present) or red (missing). After the upgrade click Refresh — all sessions should appear.

= Emails are not being delivered. What should I do? =

By default WordPress sends email via PHP's `mail()` function, which many hosting providers block or which major inboxes mark as spam. Go to **Settings → SMTP & Email Delivery** and configure a dedicated SMTP provider. Use **Send Test Email** to verify your connection before sending a real report. See the SMTP Setup Guides section below for step-by-step instructions for each supported provider.

= Why did a plugin or theme update fail? =

Premium and licensed plugins that require a valid license key for automatic updates will fail with a mapped error message. The Update Log and email reports both include a plain-English explanation and an action recommendation. These items must be updated manually through the vendor's dashboard or by providing a valid license key.

= Can I send the report email from a specific email address? =

Yes. Go to **Settings → SMTP & Email Delivery** and fill in the **From Name** and **From Email** fields. The From Email must be authorised to send from your SMTP provider or domain — using an unverified address is the most common cause of delivery failures.

= Can I update WordPress Core separately from plugins and themes? =

Yes. The Updates page has three separate sections — WordPress Core, Plugins, and Themes — each with its own Select All checkbox. You can update Core alone, plugins alone, themes alone, or any combination.

= Is my SMTP password stored securely? =

Yes. Passwords and API keys are encrypted with AES-256-CBC before being saved to the database. The encryption key is derived from your WordPress installation's AUTH_KEY and SECURE_AUTH_KEY constants, which are unique per site and defined in wp-config.php. The raw password is never output into the browser — only a masked placeholder is shown when a value is already saved.

= Where is the plugin data stored? =

Two custom database tables per site:
* `{prefix}_wpmm_update_log` — one row per update action (session_id, item_name, item_type, item_slug, old_version, new_version, status, error_code, message, updated_at)
* `{prefix}_wpmm_email_log` — one row per email send (session_id, to_email, subject, body, status, sent_at)

Plugin settings (branding, SMTP, client email, default admin) are stored in the `wpmm_settings` WordPress option.

= Can I use this plugin alongside WP Mail SMTP or other SMTP plugins? =

It is recommended to use either Site Maintenance Manager's built-in SMTP configuration **or** a separate SMTP plugin — not both. Both plugins hook into `phpmailer_init` and will conflict. If you already have WP Mail SMTP, FluentSMTP, or Post SMTP installed and configured, leave Site Maintenance Manager's SMTP setting on **WordPress Default** and let the other plugin handle delivery.

---

== SMTP Setup Guides ==

Site Maintenance Manager includes a built-in SMTP configuration panel that reconfigures WordPress's email delivery without requiring a separate plugin. The following guides walk through setting up each supported provider.

Go to **Settings → SMTP & Email Delivery**, click your provider's tile, and enter the credentials described below.

---

= WordPress Default =

No configuration needed. WordPress sends email via PHP's built-in `mail()` function. This is unreliable on most shared hosting — emails are frequently blocked by spam filters or rejected by recipients' mail servers. Recommended only as a fallback.

---

= SMTP (Manual) =

Use this option with any SMTP server not listed as a named provider — for example your hosting provider's mail server or a self-hosted mail server.

**Fields:**
* **SMTP Host** — the address of your mail server (e.g. `mail.yourdomain.com`)
* **Port & Encryption** — port `587` with TLS is recommended for most servers; port `465` with SSL is also common; port `25` with no encryption should only be used on internal networks
* **Username** — your SMTP account login (usually your email address)
* **Password** — your SMTP account password
* **From Name** — the display name on outgoing emails
* **From Email** — must be authorised to send from your SMTP server

---

= SendGrid =

**Free plan:** 100 emails per day. No credit card required.

**Setup steps:**
1. Create a free account at [sendgrid.com](https://sendgrid.com).
2. Complete the Sender Identity verification (domain authentication or single sender).
3. Go to **Settings → API Keys → Create API Key**.
4. Choose **Restricted Access** and enable **Mail Send → Full Access**.
5. Copy the API key (it is only shown once).
6. In Site Maintenance Manager Settings: select **SendGrid**, enter `apikey` (literally, that exact text) as the **Username**, and paste the API key as the **Password**.
7. Set your verified sender address as the **From Email**.

**Server details (pre-configured):** `smtp.sendgrid.net` — port `587` — TLS

---

= Mailgun =

**Free tier:** 5,000 emails per month for the first 3 months, then pay-as-you-go.

**Setup steps:**
1. Create an account at [mailgun.com](https://mailgun.com).
2. Add and verify your sending domain under **Sending → Domains**.
3. Go to **Sending → Domain Settings → SMTP credentials**.
4. Note your SMTP login (usually `postmaster@yourdomain.com`) and generate or copy the password.
5. In Site Maintenance Manager Settings: select **Mailgun**, enter your SMTP login as the **Username**, and the SMTP password as the **Password**.
6. Set a verified sender address as the **From Email**.

**Server details (pre-configured):** `smtp.mailgun.org` — port `587` — TLS

*Note: Mailgun's free tier restricts sending to verified recipient addresses only. Add recipients under Sending → Overview → Authorised Recipients if you are on the free plan.*

---

= Brevo (formerly Sendinblue) =

**Free plan:** 300 emails per day, unlimited contacts.

**Setup steps:**
1. Create a free account at [brevo.com](https://brevo.com).
2. Go to your account profile (top-right) → **SMTP & API**.
3. Under the **SMTP** tab, note your **Login** (your Brevo account email) and click **Generate a new SMTP Key** to create a password.
4. In Site Maintenance Manager Settings: select **Brevo**, enter your Brevo login email as the **Username**, and the SMTP key as the **Password**.
5. Set a sender address you have verified in Brevo as the **From Email**.

**Server details (pre-configured):** `smtp-relay.brevo.com` — port `587` — TLS

---

= SendLayer =

**Pricing:** Paid plans starting at low volume tiers; free trial available.

**Setup steps:**
1. Sign up at [sendlayer.com](https://sendlayer.com) and add your sending domain.
2. From the SendLayer dashboard, copy your **SMTP Username** and **SMTP Password**.
3. In Site Maintenance Manager Settings: select **SendLayer**, enter those credentials, and set a verified address as the **From Email**.

**Server details (pre-configured):** `smtp.sendlayer.net` — port `587` — TLS

---

= SMTP.com =

**Free trial:** 50,000 emails.

**Setup steps:**
1. Create an account at [smtp.com](https://smtp.com).
2. Go to **Sender → SMTP credentials**.
3. Copy your **Sender Name** (this is the Username) and your **API Key** (this is the Password).
4. In Site Maintenance Manager Settings: select **SMTP.com**, enter the Sender Name as **Username** and the API Key as **Password**.
5. Set your verified sender address as the **From Email**.

**Server details (pre-configured):** `send.smtp.com` — port `587` — TLS

---

= Gmail / Google Workspace =

**Important:** Google disabled plain password (basic auth) for SMTP in May 2022. You must use an App Password. OAuth 2.0 is not supported by this plugin.

**Personal Gmail — setup steps:**
1. Sign in to your Google Account at [myaccount.google.com](https://myaccount.google.com).
2. Go to **Security** and confirm that **2-Step Verification** is turned on. (App Passwords are not available without it.)
3. In the Security search bar, search for **App Passwords**.
4. Click **Create**, choose **Other (custom name)**, and type `WordPress` or `Site Maintenance Manager`.
5. Google displays a 16-character code. Copy it immediately — it will not be shown again.
6. In Site Maintenance Manager Settings: select **Gmail / Google**, enter your full Gmail address (`you@gmail.com`) as the **Username**, and paste the 16-character App Password as the **Password**.
7. Set your Gmail address as the **From Email**.

**Google Workspace (paid) — setup steps:**
The App Password method above works identically for Workspace accounts. Alternatively, your Workspace admin can configure a **SMTP relay** in the Google Admin console (Apps → Google Workspace → Gmail → SMTP relay service), which allows sending from any user in your domain without per-account App Passwords and supports higher sending volumes.

**Server details (pre-configured):** `smtp.gmail.com` — port `587` — TLS/STARTTLS

*Gmail sending limits: personal accounts are limited to approximately 500 emails per day; Google Workspace accounts to 2,000 per day.*

---

= Microsoft / Outlook =

**Important:** Microsoft deprecated basic authentication for Exchange Online in October 2022 but preserved it specifically for SMTP AUTH submissions. App Passwords are required for personal accounts; organisation accounts need SMTP AUTH enabled by an admin.

**Personal Outlook.com accounts — setup steps:**
1. Go to [account.microsoft.com/security](https://account.microsoft.com/security).
2. Under **Advanced security options**, confirm **Two-step verification** is on.
3. Click **Create a new app password**.
4. Copy the generated password.
5. In Site Maintenance Manager Settings: select **Microsoft / Outlook**, enter your full Outlook address (`you@outlook.com` or `you@hotmail.com`) as the **Username**, and the app password as the **Password**.

**Microsoft 365 / Office 365 organisations — setup steps:**
1. A Microsoft 365 admin must enable SMTP AUTH for the sending mailbox. In the **Microsoft 365 Admin Centre** go to: **Users → Active Users → select the user → Mail tab → Manage email apps → check Authenticated SMTP**.
2. Once enabled, use the regular Microsoft 365 email address and password as the Username and Password.
3. If your organisation enforces Multi-Factor Authentication (MFA), generate an App Password from [mysignins.microsoft.com](https://mysignins.microsoft.com) → **Security info → Add method → App password**.

**Server details (pre-configured):** `smtp.office365.com` — port `587` — TLS/STARTTLS

*Note: For older personal Outlook.com accounts that do not connect on smtp.office365.com, try using the manual SMTP option with host `smtp-mail.outlook.com` on port `587`.*

---

== Screenshots ==

1. **Dashboard** — Status summary cards showing last update date, client email, default administrator, and agency branding. Quick-navigation tiles link to all pages.
2. **Updates** — Three sections (WordPress Core, Plugins, Themes) with checkboxes, version numbers, and the performing administrator dropdown. Inline success/failure feedback after each update.
3. **Update Log** — Collapsible session accordion with search autocomplete, date filtering, per-page selector, and Previous/Next pagination.
4. **Email Reports** — Send form, email template preview, and Sent Email History table with preview modal and resend button.
5. **Settings** — Company & Branding (logo upload, company name), Client Contact, Site Administrators table with Gravatar and radio selection, SMTP & Email Delivery card with provider tile grid.
6. **Email Preview Modal** — Full rendered HTML email preview inside the WordPress admin.
7. **SMTP Configuration** — Provider tile grid with context-sensitive setup instructions and Send Test Email feature.
8. **Database Diagnostic** — Expandable panel showing table columns, row counts, and Force DB Upgrade button.

== Changelog ==

= 1.5.0 =
**WordPress.org Plugin Check compliance pass**

All errors and warnings reported by the Plugin Check plugin have been resolved:

* admin/admin.php: Replaced external jQuery UI CSS (code.jquery.com) with the
  bundled WordPress version (wp-jquery-ui-dialog) to comply with the no external
  resource offloading rule.
* admin/admin.php: Wrapped echo __() in esc_html__() per output escaping rules.
* admin/admin.php: Added wp_unslash() to all $_GET input reads (log_search,
  log_from, log_to, per_page, sess_page).
* admin/admin.php: Replaced all bare echo $integer with echo absint($integer)
  (sess_total, lim, per_page, success_count, fail_count, diag counts).
* admin/admin.php: Wrapped echo $make_pagination() in wp_kses_post().
* admin/admin.php: Wrapped echo $from_label in esc_html() after building the
  value as a plain string rather than a pre-escaped string.
* admin/admin.php: Added phpcs:ignore with explanatory comments on direct DB
  queries that use safe table names (prefix + fixed string, no user input).
* admin/settings.php: Wrapped echo $m['sub'] in wp_kses_post() — the sub-labels
  contain HTML entities and are hardcoded, not user input.
* includes/ajax.php: Added wp_unslash() to all $_POST reads. Added
  phpcs:ignore WordPress.Security.NonceVerification.Missing with explanatory
  comments — nonce IS verified via wpmm_ajax_cap_check() → check_ajax_referer(),
  but the static analyser cannot trace into called functions.
* includes/smtp.php: Added wp_unslash() to all $_POST reads. smtp_enc is now
  sanitized through a $raw_enc variable before the in_array() check.
  smtp_password is now sanitized with sanitize_text_field() before use.
* includes/email.php: Replaced unconditional error_log() with a WP_DEBUG-gated
  trigger_error() call, acceptable to WordPress.org reviewers.
* includes/db.php: Added file-level phpcs:disable block for the five DB-related
  rules that cannot apply to schema management (CREATE TABLE, ALTER TABLE,
  SHOW COLUMNS, INFORMATION_SCHEMA). All table names are $wpdb->prefix + fixed
  strings — no user input is ever interpolated.
* includes/updates.php: Added phpcs:ignore on the direct wpdb->insert() call.
* readme.txt: Tags reduced from 8 to 5 (WordPress.org maximum).
* readme.txt: Short description trimmed to 124 characters (maximum 150).
* readme.txt: Description section trimmed from 7,102 to 1,304 characters
  (maximum 2,500).


= 1.4.9 =
* Renamed plugin from "WP Maintenance Manager" to "Site Maintenance Manager"
  to comply with WordPress.org plugin repository naming rules, which prohibit
  plugin display names beginning with "WP". All display names, text domain,
  plugin slug, folder name, and main PHP filename updated accordingly.
  Internal function and database prefixes (wpmm_) are unchanged to preserve
  compatibility with existing installations.


= 1.4.8 =
* Feature: Avada theme detection. When the Avada theme is installed, the Updates
  page shows a contextual notice explaining the required update order: Avada theme
  first, then Avada Core, then Avada Builder. When Avada Core or Avada Builder
  have available updates in the WordPress update transient, the notice lists them
  by name so they are easy to find in the Plugins section below.
* Feature: When Avada theme is selected for update and Update Selected is clicked,
  a confirmation dialog reminds the administrator to update Avada Core and Avada
  Builder afterward, in that order.
* Feature: A direct link to the Avada Maintenance / Plugins & Add-Ons dashboard
  is shown so the administrator can check for Avada Patches, which are managed
  entirely within Avada's own dashboard and do not appear in the standard
  WordPress update API.
* Fix: SMTP From Name now correctly uses the value saved in Settings. Previously,
  an empty string saved in smtp_from_name was used as-is via PHP's ?? operator,
  causing PHPMailer to fall back to the site name. Fixed by using !empty() checks
  so an empty string falls through to company_name then the site name.
* Fix: "All selected items updated successfully" banner now clears at the start
  of each new batch and is replaced by an in-progress indicator showing how many
  items are being updated. The banner reappears only when the new batch completes.


= 1.4.5 =
* Added Gmail / Google Workspace SMTP support (smtp.gmail.com:587, requires App Password).
* Added Microsoft / Outlook / Office 365 SMTP support (smtp.office365.com:587).
* Comprehensive step-by-step setup instructions for both providers in the Settings help panel.
* Username hints update contextually when each provider tile is selected.

= 1.4.4 =
* New feature: SMTP & Email Delivery card in Settings.
* Nine mailer options: WordPress Default, Manual SMTP, SendGrid, Mailgun, Brevo, SendLayer, SMTP.com, Gmail, Microsoft.
* Visual provider tile selector with context-sensitive help text and step-by-step instructions.
* AES-256-CBC encryption for stored passwords and API keys.
* Send Test Email button with real-time success/failure feedback.
* Hooks into phpmailer_init — no separate SMTP plugin required.

= 1.4.3 =
* Email header redesigned: Site Name/URL at top, then logo + company name inline, then "WordPress website updates administered by [Admin Name]".
* Update Log: per-page limit selector (20 / 50 / 100 sessions).
* Update Log: Previous / Next pagination bars at top and bottom of session list.

= 1.4.2 =
* Fix: Emails showing "No update entries found for this session." Session ID resolution now uses a dedicated flag (updatesRanThisLoad) set only when updates are actually run, preventing the cross-page session mismatch.
* Fix: Sent Email History previews now show full plugin/theme lists. Preview modal rebuilds body from original log entries rather than returning stale stored HTML.
* Email header logo reduced to 50% size.

= 1.4.1 =
* Fix: Plugins and themes missing from email body. Template assembly rewritten using explicit string concatenation instead of double-quoted string interpolation.
* Added isset() guards on all entry property accesses.
* Email header: Site Name and URL moved to the top; agency branding below.

= 1.4.0 =
* Critical fix: Update Log and Email History not recording new entries on existing installs. Root cause was DB schema never upgraded after file-upload updates. admin_init hook now runs wpmm_create_tables() once per version bump with delete_option() before and update_option() only on success.
* Column checks now use INFORMATION_SCHEMA.COLUMNS for reliability across all MySQL/MariaDB versions.
* Database Diagnostic panel added to Update Log page: shows both tables, columns (green/red), row counts, most recent rows, and Force DB Upgrade Now button.

= 1.3.9 =
* Critical fix: Update Log showing only one old session. SQL_NO_CACHE removed — it was causing a fatal syntax error on MySQL 8.0+.
* Feature: Live autocomplete search on the Update Log page.

= 1.3.8 =
* Fix: Resent emails now use the current template. session_id stored in wpmm_email_log; resend rebuilds body from original log entries.
* Fix: Session items sorted ascending so session start time is shown correctly.
* Added Refresh button to Update Log card header.

= 1.3.7 =
* Email template header redesigned per spec: company logo and name side-by-side, completed-by statement, site block with external-link icon on URL.

= 1.3.6 =
* Fix: Company name and client email saved values now display correctly after saving. show()/hide() replaced with CSS class toggling to preserve display:flex on the saved-field-display element.

= 1.3.5 =
* Fix: All Settings page save buttons now work. Four bugs resolved: duplicate HTML IDs, missing type="button" attributes, wp.media not loaded before script, and stale element ID mapping in JS.

= 1.3.4 =
* Fix: Settings page now loads with full plugin styling. Asset enqueue now uses slug-suffix matching instead of a stored option that could be stale.

= 1.3.3 =
* Fix: Settings page showing generic WordPress styling. wpmm_page_hooks option could be stale; enqueue now checks hook string directly.

= 1.3.2 =
* Fix: Logo upload working. wp_enqueue_media() moved to admin_enqueue_scripts hook. media-editor added as script dependency on Settings page.
* Settings page design overhauled to match all other plugin pages.

= 1.3.1 =
* Fix: Email reports containing no content (cross-page session loss). Session ID now persisted in wpmm_last_session option; Email Reports page reads it as a server-side hidden field.
* Fix: Multisite email reports now query the correct sub-site's log table via switch_to_blog().

= 1.3.0 =
* New: Settings page with Company & Branding (logo, company name), Client Contact (email), and Site Administrators (default admin selection).
* Client email moved from Dashboard to Settings.
* Dashboard redesigned with status summary cards and Settings quick-nav tile.
* Updates page: Performing Administrator dropdown per session.
* Email template: agency logo, company name, administrator name in header; sectioned Core/Plugins/Themes tables.

= 1.2.3 =
* Fix: Update Log showing no entries. Subquery SQL approach removed; grouping now done in PHP.
* Fix: Email preview modal spinner not stopping. show()/hide() replaced with CSS class toggling.

= 1.2.2 =
* Initial public release with Dashboard, Updates, Update Log, and Email Reports pages.
* WordPress Core, Plugin, and Theme update support.
* Multisite/Network support.
* 24-entry error code dictionary with plain-English explanations.

== Upgrade Notice ==

= 1.5.0 =
WordPress.org Plugin Check compliance fixes. No database or functional changes.


= 1.4.9 =
Plugin renamed to Site Maintenance Manager for WordPress.org compliance. No database or functional changes.


= 1.4.8 =
Adds Avada theme detection and update order guidance. Fixes SMTP From Name using site name instead of configured value.


= 1.4.5 =
Adds Gmail and Microsoft SMTP support. No database changes.

= 1.4.4 =
Adds built-in SMTP configuration. No database changes. If you use a separate SMTP plugin, leave Site Maintenance Manager's SMTP setting on WordPress Default.

= 1.4.3 =
Email header redesigned. Update Log gains per-page selector and Prev/Next pagination. No database changes.

= 1.4.2 =
Fixes emails showing "No update entries found" and preview modal missing plugin lists.

= 1.4.1 =
Fixes plugins/themes missing from email body. Upgrade recommended.

= 1.4.0 =
Critical database fix. After upgrading, open Update Log and click Force DB Upgrade Now in the Database Diagnostic panel. Verify all columns are highlighted green before running new updates.

= 1.3.9 =
Critical fix: Update Log now displays all sessions. Upgrade required for anyone on 1.3.8.

= 1.3.5 =
Critical fix: Settings page save buttons now work. Upgrade required for 1.3.0–1.3.4.

= 1.3.1 =
Critical fix: Email reports now include update content. Affects all installs, especially Multisite.
