# API Reference

REST API at `/api/v1` (Document 2 §22). All authenticated endpoints require a Sanctum bearer token; tenant context is derived from the token. Rate limited: login `throttle:5,1`, authenticated routes `throttle:60,1`.

## Authentication
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/auth/login` | Issue an API token (email + password) |
| POST | `/api/v1/auth/logout` | Revoke the current token |

### Login
```http
POST /api/v1/auth/login
Content-Type: application/json

{ "email": "doctor@clinic.com", "password": "secret" }
```
Response: `{ "token": "...", "user": {...} }`. Subsequent requests use `Authorization: Bearer <token>`.

## Patients
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/patients` | List (paginated) |
| POST | `/api/v1/patients` | Create |
| GET | `/api/v1/patients/{patient}` | Show |
| PUT/PATCH | `/api/v1/patients/{patient}` | Update |
| DELETE | `/api/v1/patients/{patient}` | Delete |

## Appointments
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/appointments` | List |
| POST | `/api/v1/appointments` | Create |
| GET | `/api/v1/appointments/{appointment}` | Show |
| POST | `/api/v1/appointments/{appointment}/cancel` | Cancel |

## Consultations
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/consultations` | List |
| POST | `/api/v1/consultations` | Create |
| GET | `/api/v1/consultations/{consultation}` | Show |

## Treatments
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/treatments` | List |
| POST | `/api/v1/treatments` | Create (booking) |
| GET | `/api/v1/treatments/{treatment}` | Show |
| POST | `/api/v1/treatments/{treatment}/complete` | Complete |
| POST | `/api/v1/treatments/{treatment}/cancel` | Cancel |

## IPD Admissions
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/ipd-admissions` | List |
| POST | `/api/v1/ipd-admissions` | Admit |
| GET | `/api/v1/ipd-admissions/{ipdAdmission}` | Show |
| POST | `/api/v1/ipd-admissions/{ipdAdmission}/discharge` | Discharge |

## Billing / Invoices
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/invoices` | List |
| POST | `/api/v1/invoices` | Create |
| GET | `/api/v1/invoices/{invoice}` | Show |
| POST | `/api/v1/invoices/{invoice}/issue` | Issue |
| POST | `/api/v1/invoices/{invoice}/payments` | Record payment |

## Prescriptions
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/patients/{patient}/prescriptions` | List for patient |
| POST | `/api/v1/patients/{patient}/prescriptions` | Create |
| GET | `/api/v1/prescriptions/{prescription}` | Show (shallow) |

## Teleconsultations
| Method | Endpoint | Description |
|---|---|---|
| GET | `/api/v1/teleconsultations` | List |
| GET | `/api/v1/teleconsultations/{teleconsultation}` | Show |

## Webhooks
| Method | Endpoint | Description |
|---|---|---|
| POST | `/api/v1/webhooks/payments` | Payment gateway webhook (signature-verified, not token-authed) |

## Response Format
- Collections: `{ "data": [ ... ] }` (wrapped)
- Single resources: object at top level (no `data` wrapper) for show endpoints
- Validation errors: `422` with `{ "message": "...", "errors": { ... } }`
- Auth errors: `401`; Authorization errors: `403`; Not found: `404`
- Rate limit exceeded: `429`

## Conventions
- All write endpoints use Form Requests for validation.
- Responses use API Resources for consistent serialization.
- Tenant isolation is enforced server-side; you cannot access another tenant's records.
- Authorization is enforced via Laravel Policies mapped to RBAC permissions.
