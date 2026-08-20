# Queue & Scheduler Operations

Production runs two background processes beyond the web server: the **queue worker** (sends notifications, runs OCR, processes payments) and the **scheduler** (reminders, retries, reconciliation). Both are required — a web-only deployment silently skips reminders and OCR.

## 1. What runs in the background

| Process | What it does |
|---|---|
| Queue worker (`queue:work`) | Executes `SendNotificationJob` (WhatsApp/SMS/email/in-app delivery) and `ProcessDocumentOcr` (document text extraction). Both are `ShouldQueue` jobs dispatched from web requests and scheduled commands. |
| Scheduler (`schedule:run`, every minute) | Runs 7 registered commands: appointment reminders (*/15 min), treatment reminders (*/15 min), follow-up reminders (daily 09:00), notification retry (*/5 min), subscription checks (00:30), payment reconciliation (hourly), audit/records cleanup (02:00). |

The queue driver is `database` (`jobs` + `failed_jobs` tables, both migrated). Redis is a performance upgrade, not a requirement.

## 2. Production processes

### VPS (systemd, preferred)

`/etc/systemd/system/klinic-worker.service`:
```ini
[Unit]
Description=Klinic360 queue worker
After=network.target mariadb.service

[Service]
User=www-data
Group=www-data
Restart=always
RestartSec=5
WorkingDirectory=/var/www/klinic360
ExecStart=/usr/bin/php artisan queue:work --sleep=3 --tries=3 --timeout=120 --max-time=3600

[Install]
WantedBy=multi-user.target
```
```bash
sudo systemctl enable --now klinic-worker
```

Scheduler via cron (per-minute):
```cron
* * * * * cd /var/www/klinic360 && php artisan schedule:run >> /dev/null 2>&1
```

### cPanel / shared hosting (no systemd)

Add two cron entries:
```cron
* * * * * cd ~/klinic360 && php artisan schedule:run >> /dev/null 2>&1
* * * * * cd ~/klinic360 && php artisan queue:work --stop-when-empty --timeout=120 >> /dev/null 2>&1
```
`queue:work --stop-when-empty` exits when drained and is re-spawned by the next cron tick — an acceptable substitute for a persistent worker at clinic-scale volumes. Overlapping runs are safe (the database driver serializes job takeout).

## 3. Failure handling

- Jobs carry their own retry budget: `SendNotificationJob` (tries=3, backoff=60s), `ProcessDocumentOcr` (tries=3, backoff=30s, timeout=120s).
- After the budget is exhausted the job lands in `failed_jobs`. Inspect and retry:
  ```bash
  php artisan queue:failed            # list
  php artisan queue:retry <id>        # retry one
  php artisan queue:retry all         # retry all
  php artisan queue:flush             # clear after triage
  ```
- `SendNotificationJob::failed()` additionally marks its `notification_deliveries` row FAILED so the scheduler's `klinic:retry-notifications` picks it up again under the attempt cap — no manual intervention needed for transient provider errors.
- `queue:restart` is required after deployments (`git pull`) so long-running workers reload the new code.

## 4. Verification (pre-launch checklist)

1. `php artisan schedule:list` — all 7 commands appear.
2. `php artisan schedule:run` — exits 0 on an empty database.
3. Dispatch a real job and confirm the worker drains it:
   ```bash
   php artisan tinker --execute="\App\Jobs\ProcessDocumentOcr::dispatch(1);"
   php artisan queue:work --once --stop-when-empty
   ```
4. Force a permanent failure (any job with `tries=1` that throws) and confirm it appears in `queue:failed`.
5. Book an appointment with a phone number configured for WhatsApp/SMS (dev: `LOG_CHANNEL=stack` shows the send attempt; the in-app delivery appears under Notifications).
6. Suspend a tenant and confirm its API tokens 403 and web sessions log out (tenant-isolation enforcement).

The automated operational proofs live in `tests/Feature/Queue/QueueOperationsTest.php` and `SchedulerRunTest.php` and run against the real `database` queue driver.
