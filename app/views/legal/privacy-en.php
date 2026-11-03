<p class="muted">Last updated: <?= e($updated) ?></p>

<h2>Who runs this website</h2>
<p><?= e(app_name()) ?> is run by its administrator in the Kurdistan Region of Iraq. Contact: <a href="mailto:<?= e($email) ?>" dir="ltr"><?= e($email) ?></a>.</p>

<h2>What we collect and why</h2>
<ul>
    <li><strong>Account details</strong> (name, email, phone, password hash and, if you turn on two-step login, its secret key) — to let you sign in, publish listings and be contacted about them. Your phone number is shown on your listings.</li>
    <li><strong>Listings, photos and videos</strong> you upload — to publish them. Photos are re-encoded: location (GPS) and camera data are removed.</li>
    <li><strong>Enquiries</strong> (name, email, optional phone, message) — sent to the advertiser so they can reply to you.</li>
    <li><strong>Notifications</strong> — if you follow an office or save a search alert, your browser gives us a push address and keys. We store which offices you follow and the filters of your alerts. No account is needed.</li>
    <li><strong>Your location</strong> — only when you press “Near me”. It is rounded to about 100 m, used for that search, and not stored — except inside a search alert you choose to save.</li>
    <li><strong>Visit counts</strong> — for each listing we count views and taps on WhatsApp and Call per day, as numbers only, so the office can see how its listing is doing. We do not record who viewed or tapped.</li>
    <li><strong>Reports</strong> about listings, and <strong>technical data</strong> (IP address) used only to limit abuse.</li>
</ul>

<h2>Where it is stored and for how long</h2>
<p>Data is stored on our hosting server. IP addresses on enquiries and reports are erased after 30 days. Enquiries are deleted after 24 months, notification history after 12 months, inactive push subscriptions after 400 days, the admin activity log after 24 months. Listings stay until you or an administrator delete them. Deleting your account removes your listings, photos, videos, office and saved items. A copy of the database is kept as a backup each day and deleted after 7 days, so deleted information can remain in a backup for up to 7 days.</p>

<h2>Who else receives data</h2>
<ul>
    <li><strong>Push services</strong> of your browser (Google, Mozilla, Apple or Microsoft) deliver notifications. They receive an encrypted message they cannot read.</li>
    <li><strong>OpenStreetMap</strong> map tiles: when you open a map, your browser loads map images from tile.openstreetmap.org, which sees your IP address.</li>
    <li><strong>WhatsApp / phone:</strong> pressing WhatsApp or Call opens that app with the advertiser’s number; what you send there is between you and them.</li>
    <li>We do not sell data and use no advertising or analytics trackers.</li>
</ul>

<h2>Cookies and browser storage</h2>
<table class="table legal-table">
    <thead><tr><th scope="col">Name</th><th scope="col">Purpose</th><th scope="col">Duration</th></tr></thead>
    <tbody>
        <tr><td dir="ltr"><code>aqarat_sid</code> (cookie)</td><td>Session: sign-in and security token for forms (strictly necessary)</td><td>Until the browser closes; at most 7 days</td></tr>
        <tr><td dir="ltr"><code>lang</code> (cookie)</td><td>Your chosen language</td><td>1 year</td></tr>
        <tr><td dir="ltr"><code>recent</code> (localStorage)</td><td>Recently viewed listings, shown only to you, used for “Recommended for you”</td><td>Until you clear it</td></tr>
        <tr><td dir="ltr"><code>installDismissed</code> (localStorage)</td><td>Remembers that you closed the “Install” banner</td><td>14 days</td></tr>
        <tr><td dir="ltr"><code>aq-loc</code> (sessionStorage)</td><td>Your rounded location, so “Near me” does not ask again</td><td>10 minutes / until the tab closes</td></tr>
        <tr><td>Service worker cache</td><td>Pages and photos you visited, for offline use</td><td>Until replaced or you clear site data</td></tr>
    </tbody>
</table>
<p>All of these are needed for features you use, so no consent banner is shown.</p>

<h2>Your rights</h2>
<p>You can download all your data (Profile → “Download my data”), correct it in your profile, or delete your account (Profile → “Delete my account”). You can stop notifications at any time on the Notifications page or in your browser settings. For anything else, email <a href="mailto:<?= e($email) ?>" dir="ltr"><?= e($email) ?></a>.</p>

<h2>Security</h2>
<p>Connections are encrypted (HTTPS). Passwords are stored only as Argon2id hashes. The database is not reachable from the web. Administrators’ actions are logged.</p>

<h2>Changes</h2>
<p>We will update this page when the way we handle data changes and show the date above.</p>
