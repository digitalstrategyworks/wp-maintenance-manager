=== Greenskeeper ===
Contributors:      tonyzeoli
Author:            Tony Zeoli
Author URI:        https://digitalstrategyworks.com
Tags:              maintenance, updates, smtp, email, multisite
Requires at least: 5.8
Tested up to:      6.9
Requires PHP:      8.0
Stable tag:        1.9.3
License:           GPL-2.0+
License URI:       https://www.gnu.org/licenses/gpl-2.0.html
Copyright:         2026 Digital Strategy Works LLC

Manage WordPress updates, filter comment spam, send branded email reports, and configure SMTP delivery — for single sites and Multisite networks.

== Description ==

Greenskeeper is a professional WordPress maintenance plugin for developers and agencies. It centralises update management for WordPress Core, plugins, and themes, pairs it with a polished email reporting workflow, and adds layered comment spam protection — all from a single purpose-built admin dashboard.

**Updates & Reporting:**

* Scans for available WordPress Core, plugin, and theme updates in separate sections
* Updates items individually or in batch with a real-time progress bar and plain-English error explanations
* Logs every update action automatically — searchable by item name or date range, grouped into sessions
* Builds a branded HTML maintenance report email from each update session and sends it to your client
* Report emails support Update Notes (admin note to recipient) and Additional Manual Updates (for licensed plugins updated outside the plugin)
* Configures reliable SMTP email delivery via nine supported providers — no separate SMTP plugin required
* Manages agency branding: company logo, company name, and default administrator shown on reports
* Works on single-site WordPress installs and Multisite networks
* Multisite: Site Scope Selector on Updates, Spam Log, and Settings — view and manage any single site or the full network from Network Admin

**Spam Filter & Comments:**

* Layer 1 — Local filtering (always active): honeypot hidden field, submission time check, link count limit, keyword blocklist, IP blocklist, duplicate comment detection
* Layer 2 — Akismet cloud filtering (optional): enter your Akismet API key to enable AI-powered spam detection. Automatically skipped when the standalone Akismet plugin is active
* Spam Log page: review every blocked comment attempt — filter by rule or IP, add offending IPs to the blocklist with one click, and bulk-delete entries
* Disable Comments: remove comment support from all post types and hide the Comments admin menu site-wide

**Important — Akismet licensing:** Akismet is free for personal, non-commercial sites only. Any commercial or client site requires a paid Akismet plan available at [akismet.com/plans](https://akismet.com/plans/). Greenskeeper provides the integration; you are responsible for having a valid Akismet licence appropriate for your site's use.

**Supported SMTP Providers:**

SendGrid, Mailgun, Brevo, SendLayer, SMTP.com, Gmail / Google Workspace, Microsoft / Outlook / Office 365, manual SMTP, or WordPress default.

**Who it is for:**

Web developers, digital agencies, and WordPress administrators who manage client sites and need a reliable, repeatable maintenance and security workflow with professional client-facing reporting. Named after the greenskeeper who maintains the golf course — meticulous, professional, invisible.

== Installation ==

= Single-Site Install =

1. In your WordPress admin go to **Plugins → Add New → Upload Plugin**.
2. Upload `greenskeeper.zip` and click **Install Now**.
3. Click **Activate Plugin**.
4. Navigate to **Site Maintenance** in the left-hand admin menu.
5. Open **Settings** and configure your company branding, client email, default administrator, SMTP delivery, and spam filtering.

= Multisite / Network Install =

1. Log in as a Super Admin and go to **Network Admin → Plugins → Add New → Upload Plugin**.
2. Upload `greenskeeper.zip` and click **Install Now**.
3. Click **Network Activate** to activate across all sites simultaneously, **or** activate per-site from each site's own Plugins screen.
4. Navigate to **Site Maintenance** in the Network Admin menu or any site's admin menu.
5. Each site has its own independent Settings, Update Log, and Email Log.

= Manual Install =

1. Unzip the archive and upload the `greenskeeper` folder to `/wp-content/plugins/`.
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

= Using the Spam Log =

1. Go to **Site Maintenance → Spam Log**.
2. The stats card at the top shows how many attempts each rule has blocked since activation.
3. The table lists every blocked attempt with date, rule, IP address, author details, and a content preview.
4. Click **Block IP** next to any row to add that IP to the blocklist in Settings immediately.
5. Use the **Rule** and **IP** filters to narrow the list. Click **Apply** to filter; **×&nbsp;Clear** to reset.
6. Check individual rows and click **Delete Selected** to remove entries, or **Clear All** to wipe the entire log.

= Previewing a Sent Email =

Click the eye icon in the Sent Email History table. The email renders in a full modal preview using the current template. Even old emails show current branding because the preview always rebuilds the body from the original log entries.

= Resending an Email =

Click **Resend** in the Sent Email History table. The email is rebuilt from the original session entries and sent again to the same recipient.

---

== External Services ==

This plugin connects to one external service: the Akismet API. All other
functionality runs entirely on your own server with no external connections.

= Akismet Spam Filtering (optional) =

**What it is:** Akismet is a cloud-based spam detection service operated by
Automattic, Inc. Greenskeeper includes an optional integration that allows you
to submit comment data to Akismet's API for spam classification.

**This feature is entirely opt-in.** Akismet is only activated if you enter an
Akismet API key in Greenskeeper → Settings → Spam Filter & Comments. If no key
is entered, no data is ever sent to Akismet.

**What data is sent and when:** When Akismet is enabled and a comment is
submitted on your site, Greenskeeper sends the following data to Akismet's API:

* Your site URL
* The commenter's IP address
* The commenter's browser user agent string
* The HTTP referrer header from the comment request
* The URL of the page the comment was submitted on
* The comment type, author name, author email, author URL, and comment content

This data is sent each time a new comment is submitted and passes Greenskeeper's
local filters. If Akismet is unreachable, Greenskeeper fails open (allows the
comment through) rather than blocking it.

Additionally, when you click "Verify & Save Key" in Settings, your Akismet API
key and your site URL are sent to Akismet's verification endpoint to confirm the
key is valid.

**Akismet's terms of service and privacy policy:**

* Terms of service: https://akismet.com/tos/
* Privacy policy: https://automattic.com/privacy/

**Important licensing note:** Akismet's free plan is for personal,
non-commercial sites only. Any commercial or client site requires a paid
Akismet plan. See https://akismet.com/plans/ for details. Greenskeeper provides
the integration; you are responsible for holding a valid Akismet licence
appropriate for your site's use.

== Frequently Asked Questions ==


= Does Greenskeeper report updates made outside the plugin? =

Yes, from version 1.9.1. Greenskeeper hooks into WordPress's
upgrader_process_complete action, which fires for any update that runs
through WordPress's standard Plugin_Upgrader or Theme_Upgrader — including
updates made from the WordPress Updates screen, the Avada plugins dashboard
(for Avada Core and Avada Builder), or any other standard WordPress update
mechanism.

These external updates are logged automatically with a session labelled
"External" in the Update Log, and are included as a separate "Updates Made
Outside Greenskeeper" section in the next maintenance report email.

= How does Greenskeeper handle Avada theme updates? =

Greenskeeper handles Avada-related updates in two ways depending on the
update type:

**Avada theme, Avada Core, and Avada Builder updates** — once your Avada
license is registered, these appear in the standard WordPress updates list
and can be updated from either the WordPress Updates screen or the Avada
plugins dashboard. Both routes fire WordPress's standard upgrade hooks.
Greenskeeper detects and logs these automatically and includes them in the
maintenance report email.

**Avada Patches** — patches applied through Avada's own Maintenance →
Plugins & Add-Ons dashboard use Avada's proprietary update mechanism and
do not fire WordPress's standard hooks. Greenskeeper cannot detect these
automatically. They should be documented manually using the Additional
Manual Updates field on the Email Reports page before sending the report.

The Updates page also shows a contextual notice when the Avada theme is
installed, explaining the required update order: Avada theme first, then
Avada Core, then Avada Builder.

= What is the difference between an external update and a manual update? =

An **external update** is one that Greenskeeper detected automatically
because it went through WordPress's standard update mechanism (Plugin_Upgrader
or Theme_Upgrader). These are logged and included in the email without
any action from you.

A **manual update** is one that Greenskeeper cannot detect — for example
an Avada Patch, a plugin updated through a vendor's own proprietary
dashboard, or an FTP file replacement. These must be documented using the
Additional Manual Updates field on the Email Reports page.

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

Three custom database tables per site:
* `{prefix}_wpmm_update_log` — one row per update action (session_id, item_name, item_type, item_slug, old_version, new_version, status, error_code, message, updated_at)
* `{prefix}_wpmm_email_log` — one row per email send (session_id, to_email, subject, body, status, sent_at)
* `{prefix}_wpmm_spam_log` — one row per blocked comment attempt (rule, author_ip, author_name, author_email, author_url, comment_content, post_id, blocked_at)

Plugin settings (branding, SMTP, spam filter configuration, client email, default admin, API key) are stored in the `wpmm_settings` WordPress option.



= Can I review blocked spam comments? =

Yes. Go to **Site Maintenance → Spam Log**. Every comment attempt blocked by
any local filter rule (honeypot, time check, keyword, IP, link count, duplicate)
is logged there with the author's IP, name, email, and a content preview. Akismet-
blocked comments are logged here too and also appear in WordPress's native
Comments → Spam queue.

From the Spam Log you can: filter by rule or IP, add an IP to the blocklist
with one click, delete individual entries, or clear the entire log.



= Why were themes not showing in my email reports? =

This was a bug in versions prior to 1.9.1. Theme update log entries stored
with item_type as 'themes' (plural) were incorrectly bucketed into the
Plugins section. Version 1.9.1 normalises both spellings. No data was lost
— resending any previous email from the Sent Email History table will now
show themes in the correct Themes section.

= Does the email report include spam filter activity? =

Yes, from version 1.9.1. Every maintenance report email includes a Spam
Activity section listing comment attempts blocked since the last report was
sent. Each entry shows when it was blocked, which rule caught it, the
submitter's IP, and a content preview. If no spam was blocked since the
last report the section is omitted entirely.

= How does the administrator name appear in email reports? =

From version 1.9.1 the email uses the administrator's First Name and Last
Name from their WordPress user profile. Go to Users &rarr; Your Profile and
fill in the First Name and Last Name fields, then click Update Profile. If
no first or last name is saved, the Display Name is used as a fallback.

= Who can access Greenskeeper? =

By default, any WordPress Administrator can access the plugin. Once you save the
Manage Plugin Access settings (Settings → Manage Plugin Access), only the
administrators you have explicitly checked can see or use any part of the plugin.
Unchecked administrators see no menu item and cannot reach any plugin page.

Your own account is always locked in — you cannot accidentally remove your own
access from within the plugin.

= Can a client with Administrator access see the plugin? =

Not after you configure the Manage Plugin Access card. Go to
**Site Maintenance → Settings**, scroll to **Manage Plugin Access**, uncheck the
client's administrator account, and click **Save Access Settings**. That account
will no longer see the Site Maintenance menu or any plugin page.

= What if I get locked out of the plugin? =

Lockout cannot happen from within the plugin UI — your own account is always
kept in the access list automatically. If you are locked out through a direct
database change, connect to the database and either delete the `wpmm_settings`
option (which resets to the manage_options fallback) or add your user ID back
to the `access_user_ids` array in that option.

= Does the plugin support two-factor authentication? =

The plugin does not implement 2FA itself. Instead, it detects whether a 2FA plugin
is active and shows a notice on every plugin page if none is found, with direct
links to install WP 2FA or Two Factor. We recommend protecting the administrator
accounts that have wpmm_access with 2FA via one of these dedicated plugins:
WP 2FA (by Melapress), Two Factor (official WordPress.org plugin),
Wordfence Security, or iThemes Security Pro.


= How do spam filter settings work on a Multisite network? =

Each sub-site has its own independent spam filter settings stored in that
site's wpmm_settings option. From Network Admin, go to
Settings → Spam Filter & Comments. The All Sites view shows a summary table
of every site with its spam status, Akismet connection, and comments status.
Select a specific site from the Site Scope Bar to edit its settings. Changes
only affect that site and are saved immediately.

= Can I run updates for all sites at once, or only one site at a time? =

Both. In Network Admin, the Site Scope Bar on the Updates page defaults to
All Sites, which shows all available updates for all installed plugins and
themes. Selecting a specific site filters the list to only the plugins and
themes activated on that site, and runs updates in that site's context.

= Does the network email report cover all sites? =

Yes. When the Email Reports page is in All Sites scope (Network Admin,
no site selected), sending the report builds a consolidated email with a
section per site. Each section contains that site's own Core, Plugins, and
Themes update tables. When a single site is selected, the report covers
only that site in the standard single-site format.

= Does the spam filter work without an Akismet API key? =

Yes. The local filtering layer (honeypot field, submission time check, link count
limit, keyword blocklist, IP blocklist, and duplicate detection) runs entirely on
your server with no external API calls. Local filtering alone catches the majority
of automated bot spam. Adding an Akismet API key activates a second layer of
AI-powered cloud filtering for more comprehensive coverage.

= Do I need a paid Akismet account? =

Akismet's free plan is for personal, non-commercial sites only. Any commercial
website — including client sites managed by an agency — requires a paid Akismet
plan. Visit [akismet.com/plans](https://akismet.com/plans/) to choose the right
plan. Greenskeeper provides the Akismet integration; licensing is your
responsibility.

= Will the spam filter conflict with the standalone Akismet plugin? =

No. Greenskeeper detects when the standalone Akismet plugin is already
active and skips its own Akismet API call automatically. Only the local filtering
layer runs in that case, so you never get double-filtering. The Settings page shows
a notice when the standalone plugin is detected.

= What happens if Akismet is unreachable when a comment is submitted? =

The plugin fails open — the comment is allowed through rather than being blocked.
This prevents legitimate comments from being lost due to a temporary API outage or
network issue. Local filters still run normally regardless of Akismet availability.

= Can I disable comments completely across the entire site? =

Yes. The Disable Comments toggle in Settings → Spam Filter & Comments removes
comment support from every post type, closes all existing comments via WordPress
filter hooks, hides the Comments admin menu item, redirects direct access to the
comments admin page, and removes discussion meta boxes from the post and page
editors. This is a site-wide setting — it applies to all post types including
custom ones.

= How do I add an IP address to the blocklist after catching a spammer? =

Two ways to add an IP. From the **Spam Log** page, click the **Block IP** button on any row — the IP is added to the blocklist instantly without leaving the page. Alternatively go to **Settings → Spam Filter & Comments**, add the address to the Blocked IP Addresses textarea (one per line), and click **Save Spam Settings**.


= How does Greenskeeper handle Multisite networks? =

In Network Admin, a Site Scope Bar appears at the top of the Updates, Spam Log,
and Settings pages. You can select "All Sites" to operate across the entire
network, or choose a specific site to scope the view to that site only.

= What does "All Sites" mode do on the Updates page? =

In All Sites mode the Updates page shows every available update for every plugin
and theme installed on the network, regardless of which site has it activated.
Running updates applies them network-wide and logs results to the network admin
site's update log. The email report lists each site as a separate section with
its own Core, Plugins, and Themes tables.

= What does single-site scope do on the Updates page? =

When you select a specific site, the Updates page filters the plugin and theme
list to only show items activated on that site (including network-activated
plugins). Updates run in that site's context and log to that site's own
wpmm_update_log table. The email report uses the single-site format.

= Are spam filter settings shared across all sites in a network? =

No. Each site has its own independent spam filter settings. In Network Admin,
select a site from the scope bar on the Settings page to view and edit that
site's spam configuration. Selecting "All Sites" shows a summary table of all
sites with their spam filter status, Akismet connection status, and comments
toggle state.

= Can I use this plugin alongside WP Mail SMTP or other SMTP plugins? =

It is recommended to use either Greenskeeper's built-in SMTP configuration **or** a separate SMTP plugin — not both. Both plugins hook into `phpmailer_init` and will conflict. If you already have WP Mail SMTP, FluentSMTP, or Post SMTP installed and configured, leave Greenskeeper's SMTP setting on **WordPress Default** and let the other plugin handle delivery.

---

== SMTP Setup Guides ==

Greenskeeper includes a built-in SMTP configuration panel that reconfigures WordPress's email delivery without requiring a separate plugin. The following guides walk through setting up each supported provider.

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
6. In Greenskeeper Settings: select **SendGrid**, enter `apikey` (literally, that exact text) as the **Username**, and paste the API key as the **Password**.
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
5. In Greenskeeper Settings: select **Mailgun**, enter your SMTP login as the **Username**, and the SMTP password as the **Password**.
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
4. In Greenskeeper Settings: select **Brevo**, enter your Brevo login email as the **Username**, and the SMTP key as the **Password**.
5. Set a sender address you have verified in Brevo as the **From Email**.

**Server details (pre-configured):** `smtp-relay.brevo.com` — port `587` — TLS

---

= SendLayer =

**Pricing:** Paid plans starting at low volume tiers; free trial available.

**Setup steps:**
1. Sign up at [sendlayer.com](https://sendlayer.com) and add your sending domain.
2. From the SendLayer dashboard, copy your **SMTP Username** and **SMTP Password**.
3. In Greenskeeper Settings: select **SendLayer**, enter those credentials, and set a verified address as the **From Email**.

**Server details (pre-configured):** `smtp.sendlayer.net` — port `587` — TLS

---

= SMTP.com =

**Free trial:** 50,000 emails.

**Setup steps:**
1. Create an account at [smtp.com](https://smtp.com).
2. Go to **Sender → SMTP credentials**.
3. Copy your **Sender Name** (this is the Username) and your **API Key** (this is the Password).
4. In Greenskeeper Settings: select **SMTP.com**, enter the Sender Name as **Username** and the API Key as **Password**.
5. Set your verified sender address as the **From Email**.

**Server details (pre-configured):** `send.smtp.com` — port `587` — TLS

---

= Gmail / Google Workspace =

**Important:** Google disabled plain password (basic auth) for SMTP in May 2022. You must use an App Password. OAuth 2.0 is not supported by this plugin.

**Personal Gmail — setup steps:**
1. Sign in to your Google Account at [myaccount.google.com](https://myaccount.google.com).
2. Go to **Security** and confirm that **2-Step Verification** is turned on. (App Passwords are not available without it.)
3. In the Security search bar, search for **App Passwords**.
4. Click **Create**, choose **Other (custom name)**, and type `WordPress` or `Greenskeeper`.
5. Google displays a 16-character code. Copy it immediately — it will not be shown again.
6. In Greenskeeper Settings: select **Gmail / Google**, enter your full Gmail address (`you@gmail.com`) as the **Username**, and paste the 16-character App Password as the **Password**.
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
5. In Greenskeeper Settings: select **Microsoft / Outlook**, enter your full Outlook address (`you@outlook.com` or `you@hotmail.com`) as the **Username**, and the app password as the **Password**.

**Microsoft 365 / Office 365 organisations — setup steps:**
1. A Microsoft 365 admin must enable SMTP AUTH for the sending mailbox. In the **Microsoft 365 Admin Centre** go to: **Users → Active Users → select the user → Mail tab → Manage email apps → check Authenticated SMTP**.
2. Once enabled, use the regular Microsoft 365 email address and password as the Username and Password.
3. If your organisation enforces Multi-Factor Authentication (MFA), generate an App Password from [mysignins.microsoft.com](https://mysignins.microsoft.com) → **Security info → Add method → App password**.

**Server details (pre-configured):** `smtp.office365.com` — port `587` — TLS/STARTTLS

*Note: For older personal Outlook.com accounts that do not connect on smtp.office365.com, try using the manual SMTP option with host `smtp-mail.outlook.com` on port `587`.*

---

== Screenshots ==

1. **Dashboard** — Status summary cards showing last update date, client email, default administrator, and agency branding. Quick-navigation tiles link to all pages.
2. **Updates** — Three sections (WordPress Core, Plugins, Themes) with checkboxes, version numbers, and performing administrator dropdown. Real-time progress bar with per-item status during batch updates.
3. **Update Log** — Collapsible session accordion with search autocomplete, date filtering, per-page selector, and Previous/Next pagination.
4. **Email Reports** — Send form with subject line builder, Report Week-Ending Date picker, Update Notes textarea, Additional Manual Updates repeater, and Sent Email History table with preview modal and resend.
5. **Settings — Company, Client & Administrators** (Greenskeeper) — Logo upload, company name, client email, and Site Administrators table with Gravatar and radio selection.
6. **Settings — Spam Filter & Comments** — Master spam toggle, Disable Comments toggle, local filtering configuration (min time, max links, keyword blocklist, IP blocklist), and Akismet API key field with verify/revoke.
7. **Spam Log** — All-time stats by rule, paginated blocked-attempt table with filter, Block IP and Delete per row, bulk delete, and Clear All.
8. **Settings — SMTP & Email Delivery** — Provider tile grid with context-sensitive setup instructions and Send Test Email feature.
9. **Email Preview Modal** — Full rendered HTML email preview inside the WordPress admin.
10. **Database Diagnostic** — Expandable panel showing table columns, row counts, and Force DB Upgrade button.

== Copyright ==

Greenskeeper is copyright 2026 Digital Strategy Works LLC.

**Plugin code** is licensed under the GNU General Public License v2.0 or later
(GPL-2.0+). You are free to use, modify, and redistribute the plugin code under
the terms of that licence. A copy of the GPL is included in the plugin package
and is available at https://www.gnu.org/licenses/gpl-2.0.html.

**Documentation and written content** — including but not limited to the plugin
description, installation and usage guides, SMTP setup guides, FAQs, and all
other original prose contained in readme.txt, README.md, and within the plugin's
admin interface — is the intellectual property of Digital Strategy Works LLC and
is protected by copyright. Reproduction or redistribution of the documentation
outside the terms of the GPL as it applies to software is prohibited without
prior written permission from Digital Strategy Works LLC.

Greenskeeper, the Greenskeeper logo, and the golf-flag mark are trademarks of
Digital Strategy Works LLC. Unauthorised use of the Greenskeeper name or visual
identity in a manner that implies endorsement or affiliation is prohibited.

For licensing enquiries contact: tony@digitalstrategyworks.com

== Changelog ==

= 1.9.3 =
* Fix: Replaced date() with gmdate() in external update session ID generation
  to avoid timezone-affected output (WordPress coding standards requirement).
* Fix: Applied esc_sql() to all table name variables ($spam_table, $log_table,
  $email_table) across admin.php, email.php, rest-api.php, spam-filter.php,
  and db.php to satisfy Plugin Check's UnescapedDBParameter requirement.
* Fix: Restructured ternary database query in ajax.php that was incorrectly
  triggering NotPrepared warnings due to static analysis limitations.
* Fix: Wrapped remaining unprepared queries in $wpdb->prepare() in rest-api.php
  (status endpoint, log endpoint COUNT, send-report fallback).
* Fix: Added missing phpcs:ignore annotation for the dynamic IN() clause in
  bulk spam log delete — a known false positive for array_fill() placeholders.
* Fix: TRUNCATE TABLE query now uses esc_sql() on the table name.
* Fix: Short description in readme.txt trimmed to meet the 150-character limit.
* Code: Added explanatory comment for load_plugin_textdomain() clarifying it
  was added at WordPress.org reviewer request and is a no-op once hosted.

= 1.9.2 =
* Security: Sanitized all $_SERVER variables (HTTP_USER_AGENT, HTTP_REFERER,
  REMOTE_ADDR) with sanitize_text_field() + wp_unslash() before use.
* Security: Wrapped remaining unprepared database queries in $wpdb->prepare().
* Security: Converted INFORMATION_SCHEMA index check query to use
  $wpdb->prepare() with parameterised values.
* Documentation: Added == External Services == section to readme.txt
  documenting the optional Akismet API integration — what data is sent,
  when, and links to Akismet's terms of service and privacy policy.
* Code: Added authoritative inline comments to updates.php explaining why
  require_once of WP core admin files is legitimate, why set_site_transient()
  is not a phone-home update checker (it is local WordPress cache), and why
  Plugin_Upgrader::upgrade() and Theme_Upgrader::upgrade() do not change
  plugin or theme activation status.
* Code: Improved docblock on wpmm_get_scoped_site_id() explaining the
  deliberate nonce exception for the read-only display-scope GET parameter.

= 1.9.1.3 =
* Fix: Email preview modal was cutting off body content at the footer.
  The modal body now scrolls the full email. Root causes were overflow:hidden
  on the modal body container, and the iframe having no mechanism to expand
  to its content height. The modal body is now overflow-y:auto and the iframe
  auto-resizes to its full content height after load via a JS onload handler.
* Feature: Greenskeeper version number now displayed below the logo in the
  admin header on every plugin page, making it easy to confirm which version
  is installed without visiting the Plugins screen.

= 1.9.1.2 =
* Fix: Email report footer was overlapping and cutting off body content
  (update tables, external updates, spam activity, and administrator notes)
  when the report contained more than a few entries. The body div now uses
  a generous padding-bottom and all content sections render fully above the
  footer regardless of report length.
* Fix: Administrator Update Notes block was assembled outside the body div,
  causing it to float between the body and footer in some email clients. The
  note block is now correctly positioned inside the body div above the footer.

= 1.9.1 =
* Fix: Theme updates were not appearing in email reports. The item_type
  bucketing in wpmm_build_email_body() now accepts both 'theme' and
  'themes' for full backward compatibility.
* Fix: Update Notes and Additional Manual Updates were in separate cards
  below the Send button, making it unclear they would be included in
  the email. Both sections have been merged into the Send Maintenance
  Report card above the Send button.
* Feature: Spam activity since the last sent report is now included as
  a section in every maintenance email. The Spam Activity section shows
  each blocked comment attempt (when, rule, IP, content preview) that
  occurred between the last sent report and the current send.
* Feature: Administrator full name (First Name + Last Name from WordPress
  user profile) now shown in email reports and the From: preview on the
  Email Reports page. Falls back to display_name if no first/last name
  is saved in the user profile.
* Feature: External update detection via the upgrader_process_complete
  hook. Updates made through the WordPress Updates screen, the Avada
  plugins dashboard (Avada Core, Avada Builder), or any standard WordPress
  update mechanism are now automatically logged with a session labelled
  External. These appear in the Update Log with an External badge and are
  included as a separate section in the next maintenance report email.
* Note: Avada Patches applied via Avada's Maintenance → Plugins & Add-Ons
  dashboard use a proprietary mechanism that does not fire WordPress update
  hooks and cannot be auto-detected. Document these using Additional Manual
  Updates.
* Documentation: Tested up to WordPress 6.9. Changelog and FAQs updated.

= 1.9.0 =
* Rename: plugin renamed from Greenskeeper to Greenskeeper.
  All display names, slug, and text domain updated. Internal wpmm_ prefixes
  and database table names unchanged for full backward compatibility.
* Feature: Multisite Site Scope Selector on Updates, Spam Log, and Settings pages.
  A scope bar appears in Network Admin with a dropdown populated by get_sites().
  Selecting a site filters the page to that site's context; "All Sites" shows
  the full network view.
* Feature: Updates page — single-site scope filters plugin and theme lists to
  only items activated on the selected site (site-level and network-activated).
  Updates run in that site's blog context and log to its own wpmm_update_log.
* Feature: Updates page — All Sites mode shows all network-wide available updates.
  Email report in All Sites mode is a consolidated network report with one
  section per site, each with its own Core/Plugins/Themes tables.
* Feature: Spam Filter settings are per-site in Multisite. Network Admin shows
  a summary overview table (spam on/off, Akismet status, comments status per
  site) when All Sites is selected, and the full settings form when a specific
  site is selected.
* Feature: Network email report — new wpmm_build_network_email_body() function
  builds a consolidated HTML email listing every site updated in a network run
  with its own update table sections and a site header for each.

= 1.8.0 =
* Feature: Manage Plugin Access card in Settings.
* A new wpmm_access custom WordPress capability controls who can see and
  use the plugin. Only administrators explicitly granted this capability
  will see the Site Maintenance menu — it is completely invisible to all
  other users including client administrators.
* Manage Plugin Access card in Settings lists every site administrator
  with a checkbox. The current user is always locked in to prevent
  accidental self-lockout.
* Access settings are synced to WordPress user capabilities immediately
  on save via wpmm_grant_access_to_admins().
* On fresh activation or if no users have been explicitly granted access,
  the plugin falls back to manage_options so existing installs are not
  accidentally locked.
* Two-factor authentication notice: a dismissible admin notice on all
  plugin pages recommends installing WP 2FA or Two Factor if no 2FA
  plugin is detected on the site.

= 1.7.0 =
* Feature: Spam Log page — sixth page under the Site Maintenance menu.
* All locally-blocked comment attempts are now logged to a new
  wpmm_spam_log database table (blocked_at, rule, IP, author name,
  author email, author URL, content, post ID).
* Akismet-caught spam is also logged to the same table for unified
  visibility alongside WordPress's native Comments → Spam queue.
* Spam Log page shows a stats summary (blocked count per rule), a
  paginated and filterable table of all blocked attempts, and per-row
  actions: Block IP (adds to Settings blocklist) and Delete.
* Bulk delete selected rows and Clear All button.
* Filter by rule type or IP address.
* Dashboard quick-nav tile for Spam Log shown when spam filtering is
  enabled.
* All-time blocked counts per rule shown on the Spam Log stats card.

= 1.6.0 =
* Feature: Spam Filter & Comments card in Settings.
* Layer 1 — Local filtering (always active when spam filter is on): honeypot
  hidden field, minimum submission time check, maximum links per comment,
  configurable keyword blocklist, configurable IP blocklist, duplicate comment
  detection within a rolling 1-hour window.
* Layer 2 — Akismet cloud filtering: optional, activated by entering an API key.
  Skipped automatically when the standalone Akismet plugin is already active.
  Failed Akismet requests fail open (comment allowed) rather than blocking
  legitimate comments if the API is unreachable.
* Disable Comments: toggle to remove comment support from all post types,
  close all existing comments, hide the Comments admin menu, and remove
  discussion meta boxes from the post editor.
* Akismet key verification: Verify & Save button confirms the key is valid
  against the Akismet API before storing it. Revoke button removes the key.
* Toggle switches for all boolean settings with live label updates.

= 1.5.9.1 =
* Changelog updated to include all versions from 1.5.1 through 1.5.9 which
  were missing from previous releases. No functional code changes.

= 1.5.9 =
* Feature: REST API spoke endpoints (smm/v1 namespace) enabling a remote hub
  site to manage updates, fetch logs, and send reports without a WordPress login.
* Six endpoints: GET /status, GET /updates, POST /update, GET /log,
  POST /send-report, POST /rotate-key.
* Authentication via X-SMM-API-Key header with hash_equals() comparison.
* New Remote API Access card in Settings for generating, copying, rotating,
  and revoking the API key, with a full endpoint reference table shown inline.

= 1.5.8 =
* Plugin Check compliance (third round) — all remaining warnings resolved.
* includes/ajax.php: DirectQuery and NoCaching phpcs:ignore annotations moved
  inline onto the get_results() call lines (were on preceding comment lines).
* includes/ajax.php: manual_entries JSON input now passes through
  sanitize_text_field(wp_unslash()) before json_decode() to satisfy
  InputNotSanitized check.
* admin/admin.php: DirectQuery and NoCaching ignores moved inline onto both
  branches of the get_results() ternary in wpmm_render_log().

= 1.5.7 =
* Feature: Update Notes card on the Email Reports page.
* Administrators can append a plain-text note to any outgoing maintenance report.
* The note appears in the email body between the update tables and the footer,
  inside a styled amber callout box titled "Note from your administrator".
* Line breaks are preserved. The field is optional — leaving it blank adds
  nothing to the email.

= 1.5.6 =
* Feature: Additional Manual Updates repeater on the Email Reports page.
* Administrators can document plugins or themes updated manually outside the
  plugin's automated process (e.g. Avada, ACF Pro, Gravity Forms).
* Each repeater row has a plugin/theme dropdown (auto-fills current version),
  a Previous Version field, and an Updated To field.
* Manual entries are included as a fourth section in the email body titled
  "Additional Manual Updates" with a styled table matching Core/Plugins/Themes.
* Manual entries are composed fresh on each send and not stored in the database.

= 1.5.5 =
* Critical fix: plugins like Advanced Custom Fields PRO and Gravity Forms were
  being deactivated after a successful update.
* Root cause: when a premium plugin's update transient entry was missing,
  the code called Plugin_Upgrader::install() which internally calls
  deactivate_plugins() as part of a fresh-install flow.
* Fix: replaced install() with a transient-injection approach — a synthetic
  entry containing the package URL is inserted, upgrade() is called instead,
  and the entry is cleaned up after. upgrade() preserves plugin active state.
* Same fix applied to the equivalent fallback branch in the theme update path.

= 1.5.4 =
* Plugin Check compliance (second round) — all remaining warnings resolved.
* includes/db.php: phpcs:ignore added inline on the ALTER TABLE query() call
  for NotPrepared, DirectQuery, NoCaching, and UnescapedDBParameter.
* includes/ajax.php: phpcs:ignore comments moved to inline on every individual
  $_POST read line; DirectQuery/NoCaching ignores added to all get_row(),
  get_results(), and get_col() calls.
* includes/ajax.php: DirectQuery ignore added to wpdb->insert() in email log.
* admin/admin.php: UnescapedDBParameter added to Dashboard get_row() ignore;
  NonceVerification.Recommended ignores moved to per-line inline comments;
  UnescapedDBParameter added to Update Log get_results() both branches;
  Email Reports get_results() ignore restructured to target exact lines.

= 1.5.3 =
* Fix: jQuery UI datepicker calendar on the Email Reports page was rendering
  transparent (no background, no borders, no text colour).
* Root cause: a previous Plugin Check fix replaced the external jQuery UI CDN
  stylesheet with wp-jquery-ui-dialog, which only loads dialog styles, not
  datepicker styles.
* Fix: full datepicker styles are now written directly into admin.css. No
  external CDN and no dependency on WordPress bundled handles that may only
  include a subset of jQuery UI components.

= 1.5.2 =
* Fix: duplicate tip card on the Update Log page removed. Each of the five
  plugin pages now has exactly one tip card call.
* Fix: tip card missing from Email Reports page restored.
* Fix: margin above the tip card was not rendering at the updated value because
  browsers were serving a cached stylesheet. Version bump to 1.5.2 forces the
  browser to download the updated admin.css (via ?ver= query string change).

= 1.5.1 =
* Feature: Report Week-Ending Date picker moved from Dashboard to Email Reports
  page. Selecting a date appends "for week of: [date]" to the subject line.
  A Clear Date link removes it. The subject field remains fully editable.
* Feature: Progress bar replaces the spinning arrow on the Updates page.
  The bar fills from 0% to 100% as each item completes, with a live text line
  showing each item name as it finishes. Holds at 100% for 600ms before the
  success banner appears.
* Fix: success banner no longer persists when returning to the Updates page
  after a completed session. When a new scan finds available updates, the
  banner from the previous session is automatically cleared.
* Feature: Tip card added to all five plugin pages (Dashboard, Updates, Update
  Log, Email Reports, Settings). Amber-styled card with PayPal and Venmo links.

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
* Renamed plugin from "WP Maintenance Manager" to "Greenskeeper"
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

= 1.9.1.2 =
Fixes email report footer overlapping body content. Update tables, external updates, spam activity, and admin notes now render fully above the footer. Recommended update for all users.

= 1.9.1 =
Fixes themes missing from email reports, merges Update Notes into the Send card, adds spam activity section to emails, and shows administrator full name. No database changes.

= 1.9.0 =
Plugin renamed to Greenskeeper. Adds Multisite Site Scope Selector for Updates, Spam Log, and Settings. No database changes. Internal prefixes unchanged — existing data is preserved.

= 1.9.1.2 =
Fixes email report footer overlapping body content. Update tables, external updates, spam activity, and admin notes now render fully above the footer. Recommended update for all users.

= 1.9.1 =
Fixes themes missing from email reports, merges Update Notes into the Send card, adds spam activity section to emails, and shows administrator full name. No database changes.

= 1.9.0 =
Plugin renamed to Greenskeeper. All database tables and internal prefixes unchanged — no data migration required. Adds multisite network scope selector for Updates, Spam Log, and Spam Filter settings.

= 1.8.0 =
Adds Manage Plugin Access — control which administrators can see the plugin. On first upgrade, all current administrators retain access. Uncheck client accounts in Settings → Manage Plugin Access to hide the plugin from them.

= 1.7.0 =
Adds Spam Log page with full blocked-attempt history, stats, and IP blocklist management. Creates a new wpmm_spam_log database table on upgrade.

= 1.6.0 =
Adds layered comment spam filtering with optional Akismet integration, and a Disable Comments toggle. No database changes.

= 1.5.9.1 =
Changelog-only update. No functional changes.


= 1.5.0 =
WordPress.org Plugin Check compliance fixes. No database or functional changes.


= 1.4.9 =
Plugin renamed to Greenskeeper for WordPress.org compliance. No database or functional changes.


= 1.4.8 =
Adds Avada theme detection and update order guidance. Fixes SMTP From Name using site name instead of configured value.


= 1.4.5 =
Adds Gmail and Microsoft SMTP support. No database changes.

= 1.4.4 =
Adds built-in SMTP configuration. No database changes. If you use a separate SMTP plugin, leave Greenskeeper's SMTP setting on WordPress Default.

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
