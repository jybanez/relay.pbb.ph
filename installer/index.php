<?php

declare(strict_types=1);

header('Content-Type: text/html; charset=utf-8');

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>PBB Relay Installer</title>
    <style>
        body { margin: 0; background: #101418; color: #e8eef4; font: 16px/1.5 system-ui, sans-serif; }
        main { max-width: 760px; margin: 8vh auto; padding: 32px; }
        section { border: 1px solid #2d3946; background: #182028; border-radius: 8px; padding: 24px; }
        h1 { margin: 0 0 12px; font-size: 24px; }
        code { color: #9bd3ff; }
    </style>
</head>
<body>
    <main>
        <section>
            <h1>PBB Relay Installer</h1>
            <p>This release exposes the Kit Setup unattended installer contract.</p>
            <p>Use <code>php installer/install-run.php --config relay.config.json --report relay.report.json</code> for automated installs.</p>
            <p>The browser-first standalone installer remains available in Relay installer build artifacts as the generated root <code>index.php</code> plus <code>installer.zip</code>.</p>
        </section>
    </main>
</body>
</html>
