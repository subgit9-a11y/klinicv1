# Environment Configuration

Copy `.env.example` to `.env` and configure the values below.

## Application
| Variable | Description | Example |
|---|---|---|
| `APP_NAME` | Application name | `Klinic 360` |
| `APP_ENV` | Environment | `production` / `local` |
| `APP_KEY` | Encryption key (generate with `php artisan key:generate`) | `base64:...` |
| `APP_DEBUG` | Show errors | `false` (production) |
| `APP_URL` | Full app URL | `https://clinic.example.com` |
| `FORCE_HTTPS` | Force HTTPS redirects | `true` (production) |
| `TIMEZONE` | App timezone | `Asia/Kolkata` |

## Database
| Variable | Description | Example |
|---|---|---|
| `DB_CONNECTION` | Driver | `mysql` (prod) / `sqlite` (dev) |
| `DB_HOST` | Host | `127.0.0.1` |
| `DB_PORT` | Port | `3306` |
| `DB_DATABASE` | Name | `klinic360` |
| `DB_USERNAME` | User | `klinic_user` |
| `DB_PASSWORD` | Password | (secret) |

## Queue & Sessions
| Variable | Description | Example |
|---|---|---|
| `QUEUE_CONNECTION` | Queue driver | `database` |
| `SESSION_DRIVER` | Session driver | `database` / `file` |
| `CACHE_STORE` | Cache driver | `database` / `file` |

## Authentication
| Variable | Description | Example |
|---|---|---|
| `SANCTUM_STATEFUL_DOMAINS` | Stateful domains for SPA | `clinic.example.com` |
| `SESSION_SECURE_COOKIE` | HTTPS-only cookies | `true` (production) |

## Payments (Cashfree)
| Variable | Description |
|---|---|
| `CASHFREE_APP_ID` | Cashfree app ID |
| `CASHFREE_SECRET_KEY` | Cashfree secret key |
| `CASHFREE_API_BASE_URL` | Cashfree API base URL |
| `CASHFREE_WEBHOOK_SECRET` | Webhook signature secret |

Leave empty to run in "not configured" mode (graceful fallback, no real charges).

## Notifications
| Variable | Description |
|---|---|
| `WHATSAPP_PROVIDER_*` | WhatsApp Business API credentials |
| `SMS_PROVIDER_*` | SMS gateway credentials |
| `MAIL_*` | SMTP for email channel |

Providers gracefully fall back to logging when unconfigured.

## Video (Google Meet)
| Variable | Description |
|---|---|
| `GOOGLE_MEET_CLIENT_ID` | OAuth client ID |
| `GOOGLE_MEET_CLIENT_SECRET` | OAuth client secret |
| `GOOGLE_MEET_REDIRECT_URI` | OAuth redirect URI |

Leave empty to run in "not configured" mode (meeting creation returns a safe error).

## AI (Gemini)
| Variable | Description |
|---|---|
| `GEMINI_API_KEY` | Google Gemini API key |
| `GEMINI_MODEL` | Model name |
| `AI_MAX_DAILY_REQUESTS` | Per-tenant daily request cap |

Leave empty to run in "not configured" mode. AI output is always draft-only and never auto-committed to clinical records.

## Documents / Storage
| Variable | Description |
|---|---|
| `FILESYSTEM_DISK` | Default disk | `local` / `s3` |
| `AWS_ACCESS_KEY_ID` / `AWS_SECRET_ACCESS_KEY` | S3 credentials |
| `AWS_DEFAULT_REGION` / `AWS_BUCKET` | S3 region and bucket |

Private documents are stored on a protected disk and served via signed URLs / authorized streaming.

## Retention
| Variable | Description | Default |
|---|---|---|
| `KLINIC_AUDIT_RETENTION_DAYS` | Days to keep audit logs | `365` |

Used by the `klinic:cleanup` scheduled command.

## Security Notes
- Never commit `.env` to version control.
- Rotate API keys and gateway secrets periodically.
- Set `APP_DEBUG=false` and `FORCE_HTTPS=true` in production.
- All credentials are encrypted at rest via Laravel's config (database column encryption where applicable).
