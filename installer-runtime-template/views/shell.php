<?php /** @var array<string, mixed> $state */ ?>
<!DOCTYPE html>
<html lang="en" data-theme="dark">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PBB - Hub Relay Server Installer</title>
    <meta name="csrf-token" content="">
    <link rel="stylesheet" href="/relay-installer/installer.css">
</head>
<body class="relay-installer-body">
    <div class="relay-installer-shell">
        <aside class="ui-panel relay-installer-rail">
            <div class="relay-installer-brand">
                <p class="ui-eyebrow">Relay Installer</p>
                <h1 class="ui-title">Fresh Host Setup</h1>
                <p class="relay-installer-note">Prepare this host for first-time Relay installation with environment checks before HQ binding and app bootstrap.</p>
            </div>
            <ol class="relay-installer-steps" aria-label="Installer steps">
                <li class="relay-installer-step is-active" data-step-item="1"><span class="relay-installer-step-index">1</span><div><strong>Environment</strong><p>Check runtime, extensions, filesystem, archive support, and DB drivers.</p></div></li>
                <li class="relay-installer-step" data-step-item="2"><span class="relay-installer-step-index">2</span><div><strong>HQ Identity</strong><p>Validate HQ Hub ID and token, then derive Relay identity.</p></div></li>
                <li class="relay-installer-step" data-step-item="3"><span class="relay-installer-step-index">3</span><div><strong>Install Settings</strong><p>Collect remaining runtime settings and installation targets.</p></div></li>
                <li class="relay-installer-step" data-step-item="4"><span class="relay-installer-step-index">4</span><div><strong>Execution</strong><p>Run install tasks, provision admin access, and finalize cleanup.</p></div></li>
            </ol>
        </aside>
        <main class="relay-installer-main">
            <section class="ui-panel relay-installer-panel">
                <div class="ui-shell-header relay-installer-header" data-step-panel="1">
                    <div>
                        <p class="ui-eyebrow">Step 1</p>
                        <h2 class="ui-title">Environment Check</h2>
                        <p class="relay-installer-note">The installer verifies host readiness before any HQ identity or application setup begins.</p>
                    </div>
                    <div class="relay-installer-summary" id="installer-summary"><span class="ui-badge relay-installer-badge is-pending">PENDING</span></div>
                </div>
                <div class="relay-installer-statusbar" id="installer-statusbar" data-step-panel="1">
                    <div class="relay-installer-statuscopy">
                        <strong id="installer-status-title">Running checks...</strong>
                        <p id="installer-status-detail">Gathering environment requirements for this host.</p>
                    </div>
                </div>
                <div class="relay-installer-groups" id="installer-groups" aria-live="polite" data-step-panel="1"></div>
                <section class="ui-panel relay-installer-phase-card" id="installer-hq-card" data-step-panel="2" hidden>
                    <div class="ui-shell-header"><div><p class="ui-eyebrow">Step 2</p><h3 class="ui-title">HQ Identity</h3><p class="relay-installer-note">Validate the HQ Hub ID and assigned token, then derive the Relay node identity directly from HQ.</p></div></div>
                    <form class="relay-installer-form" id="installer-hq-form">
                        <label class="ui-field"><span class="ui-label">HQ Hub ID</span><input class="ui-input" type="number" min="1" name="hq_hub_id" placeholder="10" required></label>
                        <label class="ui-field"><span class="ui-label">HQ Token</span><input class="ui-input" type="password" name="hq_token" autocomplete="off" required></label>
                        <div class="relay-installer-inline-actions"><button class="ui-button ui-button-primary" type="submit" id="installer-hq-submit">Validate HQ Identity</button></div>
                    </form>
                    <div class="relay-installer-review" id="installer-hq-review" hidden></div>
                </section>
                <section class="ui-panel relay-installer-phase-card" id="installer-settings-card" data-step-panel="3" hidden>
                    <div class="ui-shell-header"><div><p class="ui-eyebrow">Step 3</p><h3 class="ui-title">Install Settings</h3><p class="relay-installer-note">Capture the local Relay admin details and runtime settings for the later install execution phase.</p></div></div>
                    <form class="relay-installer-form" id="installer-settings-form">
                        <label class="ui-field"><span class="ui-label">Admin Name</span><input class="ui-input" type="text" name="admin_name" maxlength="255" required></label>
                        <label class="ui-field"><span class="ui-label">Admin Email</span><input class="ui-input" type="email" name="admin_email" maxlength="255" required></label>
                        <label class="ui-field"><span class="ui-label">Database Driver</span><select class="ui-input" name="database_driver" id="installer-database-driver"><option value="sqlite">SQLite</option><option value="mysql">MySQL</option></select></label>
                        <div class="relay-installer-settings-group" id="installer-sqlite-fields">
                            <label class="ui-field"><span class="ui-label">SQLite Path</span><input class="ui-input" type="text" name="sqlite_path" placeholder="<?= htmlspecialchars($sqlitePlaceholder, ENT_QUOTES, 'UTF-8') ?>"></label>
                        </div>
                        <div class="relay-installer-settings-group" id="installer-mysql-fields" hidden>
                            <label class="ui-field"><span class="ui-label">Database Host</span><input class="ui-input" type="text" name="database_host" placeholder="localhost"></label>
                            <label class="ui-field"><span class="ui-label">Database Port</span><input class="ui-input" type="number" min="1" max="65535" name="database_port" value="3306"></label>
                            <label class="ui-field"><span class="ui-label">Database Name</span><input class="ui-input" type="text" name="database_name" placeholder="pbb_relay"></label>
                            <label class="ui-field"><span class="ui-label">Database Username</span><input class="ui-input" type="text" name="database_username" placeholder="root"></label>
                            <label class="ui-field"><span class="ui-label">Database Password</span><input class="ui-input" type="password" name="database_password"></label>
                        </div>
                        <div class="relay-installer-inline-actions"><button class="ui-button ui-button-primary" type="submit" id="installer-settings-submit">Save Install Settings</button></div>
                    </form>
                    <div class="relay-installer-review" id="installer-settings-review" hidden></div>
                </section>
                <div class="relay-installer-phase-placeholder ui-panel" id="installer-next-phase" data-step-panel="4" hidden>
                    <p class="ui-eyebrow">Next Phase</p>
                    <h3 class="ui-title">Ready for install execution</h3>
                    <p class="relay-installer-note">Environment, HQ identity, and install settings are now persisted. Execute the installer to write configuration, migrate the target database, provision the first admin, and finalize the install lock.</p>
                    <div class="relay-installer-inline-actions">
                        <button class="ui-button ui-button-primary" type="button" id="installer-execute">Execute Installation</button>
                        <button class="ui-button ui-button-ghost" type="button" id="installer-cleanup" hidden>Run Installer Cleanup</button>
                    </div>
                    <div class="relay-installer-review" id="installer-success-review" hidden></div>
                </div>
            </section>
            <footer class="ui-panel relay-installer-footer">
                <div class="relay-installer-footer-copy">
                    <p class="ui-eyebrow">Installer State</p>
                    <strong id="installer-state-label"><?= htmlspecialchars(strtoupper((string) ($state['status'] ?? 'fresh')), ENT_QUOTES, 'UTF-8') ?></strong>
                    <p class="relay-installer-note">Installer state is stored outside the application database.</p>
                </div>
                <div class="relay-installer-actions">
                    <button class="ui-button ui-button-ghost" type="button" id="installer-refresh">Run Checks Again</button>
                    <button class="ui-button ui-button-primary" type="button" id="installer-continue" disabled>Continue</button>
                </div>
            </footer>
        </main>
    </div>
    <script id="relay-installer-config" type="application/json"><?= $configJson ?></script>
    <script type="module" src="/relay-installer/installer.js"></script>
</body>
</html>
