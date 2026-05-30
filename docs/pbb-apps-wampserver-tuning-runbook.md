# PBB Apps WAMPServer Tuning Runbook

This runbook records the baseline tuning required for PBB Laravel apps on WAMPServer, with Relay as the first measured application. It is intended for fresh installs, reinstall verification, and post-update troubleshooting.

## Scope

- Windows host running WAMPServer.
- Apache 2.4 with PHP 8.2.
- Laravel-based PBB apps such as Relay, Kit, Hotline, Helpers, Maestro, and related local services.
- Relay endpoints tested through `https://relay.pbb.ph`.

## Known Runtime Shape

On the current Relay install, PHP is served by Apache's PHP module, not PHP-CGI/FastCGI:

```text
PHP SAPI: apache2handler
Loaded PHP ini: C:\wamp64\bin\apache\apache2.4.58\bin\php.ini
Apache PHP module: C:\wamp64\bin\php\php8.2.29\php8apache2_4.dll
```

`mod_fcgid` is loaded by Apache, but there is no active `FcgidWrapper`, `Action`, or PHP-CGI handler for `.php` requests in the inspected WAMP config. Because of that, `FcgidMinProcessesPerClass` and similar FastCGI pool settings do not improve Relay unless the host is later switched to PHP-CGI/FastCGI.

## Required Laravel Production Settings

Each installed PBB Laravel app should use production-oriented settings after setup and before performance testing:

```env
APP_ENV=production
APP_DEBUG=false
LOG_LEVEL=warning
```

After changing `.env`, clear and rebuild Laravel caches with the app's PHP version:

```powershell
cd C:\wamp64\www\pbb\relay
C:\wamp64\bin\php\php8.2.29\php.exe artisan optimize:clear
C:\wamp64\bin\php\php8.2.29\php.exe artisan optimize
```

Use the same pattern for other PBB apps, changing only the app path.

## WAMP PHP Settings

For Apache-served PHP, tune the Apache PHP ini:

```text
C:\wamp64\bin\apache\apache2.4.58\bin\php.ini
```

Recommended baseline:

```ini
realpath_cache_size = 16384k
realpath_cache_ttl = 600

opcache.enable=1
opcache.memory_consumption=256
opcache.interned_strings_buffer=16
opcache.max_accelerated_files=20000
opcache.validate_timestamps=1
opcache.revalidate_freq=60
```

Keep `opcache.validate_timestamps=1` for development and field installs where files may be replaced manually. For a tightly controlled production host, it can be disabled, but then Apache must be restarted after every code update.

After editing `php.ini`, restart Apache/WAMP as administrator. Verify that the web runtime has loaded the new values, not just the CLI runtime.

## Relay HTTP Behavior

Relay protocol responses under `/api/v1/*` should send:

```text
Connection: close
```

This reduces persistent-connection stalls observed from local PowerShell and app clients. It is controlled by:

```env
RELAY_HTTP_FORCE_CONNECTION_CLOSE=true
```

The default is enabled in Relay config. If this is disabled, valid `POST /api/v1/messages` calls can show substantially worse client-side latency.

## Relay Worker And Data Prep

After data prep or install steps change Relay config, hub registry state, or generated `hub.json`, restart the Relay workers so they use the new runtime state.

Typical commands:

```powershell
cd C:\wamp64\www\pbb\relay
C:\wamp64\bin\php\php8.2.29\php.exe artisan queue:restart
```

If a worker is managed by NSSM, Task Scheduler, WAMP tooling, or another supervisor, restart the supervisor-managed worker process as well.

## Manual Relay Hub Snapshot

To manually generate `public\hub.json`:

```powershell
cd C:\wamp64\www\pbb\relay
C:\wamp64\bin\php\php8.2.29\php.exe artisan relay:hq-heartbeat --once
```

Requirements:

```env
RELAY_HQ_HEARTBEAT_ENABLED=true
RELAY_HQ_API_ENABLED=true
RELAY_HQ_API_TOKEN=<hq token>
RELAY_HQ_LOCAL_HQ_ID=<hub id>
```

If the command says `Relay HQ heartbeat not sent: HQ heartbeat is disabled.`, enable `RELAY_HQ_HEARTBEAT_ENABLED=true` in `.env`, then rerun `artisan optimize:clear` and `artisan optimize` if config is cached.

## Measuring Relay Submission Time

Use a valid local Relay client key for `/api/v1/messages`. The HQ token is for HQ registry and heartbeat calls; it is not the local message submission key.

Payload shape:

```json
{
  "source_system": "sitrep.app",
  "target_systems": ["relay-loopback"],
  "message_type": "sitrep.record",
  "payload_format": "json",
  "payload_version": "1.0",
  "reference_type": "sitrep",
  "reference_id": "58",
  "attachments_count": 0,
  "payload": {},
  "priority": "normal"
}
```

Required header:

```text
X-Relay-Key: <active HubRelayClient api_key>
```

Expected success:

```text
HTTP 201
Connection: close
deliveries_count: 1 or more, depending on targets
```

## Measured Relay Results

Measured on the current WAMPServer Relay install after Laravel optimize and PHP OPcache/realpath tuning:

```text
GET /api/status
  warmed average: ~721 ms
  warmed median:  ~735 ms
  observed range: ~428 ms to ~1031 ms

POST /api/v1/messages, small payload
  warmed average: ~2001 ms
  warmed median:  ~1025 ms
  observed range: ~607 ms to ~8082 ms

POST /api/v1/messages, SITREP 000058 payload
  posted body:    ~72.7 KB compact JSON
  warmed average: ~1743 ms
  warmed median:  ~1111 ms
  observed range: ~519 ms to ~4039 ms
```

The larger SITREP payload is accepted and is not the primary cause of the worst latency spikes. Remaining multi-second spikes are more likely from Windows Apache/PHP worker behavior, database or disk stalls, or local host contention.

## Fresh Install Checklist

1. Confirm the app `.env` has production settings.
2. Run migrations:

   ```powershell
   C:\wamp64\bin\php\php8.2.29\php.exe artisan migrate --force
   ```

3. Rebuild Laravel caches:

   ```powershell
   C:\wamp64\bin\php\php8.2.29\php.exe artisan optimize:clear
   C:\wamp64\bin\php\php8.2.29\php.exe artisan optimize
   ```

4. Confirm Apache PHP ini contains the OPcache and realpath settings above.
5. Restart Apache/WAMP as administrator.
6. Restart app queue workers.
7. Generate or refresh `public\hub.json` if the app participates in HQ registry.
8. Test `/api/health`, `/api/status`, and one real message submission.
9. Record average, median, minimum, and maximum timings.

## Troubleshooting

- If `php artisan` fails with syntax or framework errors, verify the command uses `C:\wamp64\bin\php\php8.2.29\php.exe`. Plain `php` may resolve to an older PHP version.
- If web PHP still shows old OPcache values, Apache has not fully restarted or the wrong `php.ini` was edited.
- If `/api/v1/messages` returns `401 Missing X-Relay-Key header`, use a local Relay client API key, not the HQ heartbeat token.
- If `/api/v1/messages` returns `422`, validate the local app submission schema; it is not the same as the hub-to-hub receive envelope.
- If only the first request after restart is slow, treat it as cold start and run a warmed timing pass.
- If warmed timings still spike above several seconds, inspect MariaDB query latency, Windows disk usage, antivirus scanning, Apache worker saturation, and queue worker contention.
