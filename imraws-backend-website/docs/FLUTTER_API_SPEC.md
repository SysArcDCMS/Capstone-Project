# IMRAWS-NLP - Flutter Mobile App API Specification

**Status:** Spec frozen. Implementation parked (out of scope for current sprint).

This document defines the exact request/response shapes that the Flutter mobile
application MUST conform to when consuming the Laravel backend. It is derived
from the capstone document (Section 3.4 - Application Layer for Customer +
Offsite Staff) and the Laravel controllers in `imraws-backend-website/app/Http/Controllers/Api/`.

The Flutter app is the **consumer** of these endpoints. The Laravel backend
is the **provider**. All endpoints use JWT Bearer auth from the `auth:api`
guard.

---

## 1. Base Configuration

| Item | Value |
|------|-------|
| Base URL (dev) | `http://127.0.0.1:8000/api` |
| Base URL (prod) | `https://<oracle-cloud-vps>/api` |
| Auth header | `Authorization: Bearer <jwt>` |
| Content-Type | `application/json` (or `multipart/form-data` for uploads) |
| All responses are JSON | Laravel enforces JSON for `/api/*` (see `bootstrap/app.php`) |

### 1.1 Authentication Model

There is **one Flutter mobile app** shared by all mobile-role users (Customer + Offsite Staff per capstone Section 3.4). Role-based UI gating happens **client-side after login**, not by separate apps.

#### JWT lifecycle

```
User opens Flutter app
   ↓
Login or Register
   ↓
Server returns { access_token, user.role }
   ↓
Flutter writes JWT to flutter_secure_storage
   ↓
Every API call: Dio interceptor reads JWT and adds Authorization header
   ↓
Server's auth:api guard validates token + sets request->user()->role
   ↓
The role-based middleware ('role:customer' etc.) allows or blocks the request
```

#### Storage choice: flutter_secure_storage

| Platform | Backend | Encryption |
|---|---|---|
| iOS | Keychain | Hardware-backed |
| Android | EncryptedSharedPreferences | AES-256, Keystore-wrapped |
| Survives app restart | Yes | Yes |
| Survives uninstall | No | No (intentional) |

#### JWT contents (informational)

The token payload contains the user id + role + is_team_leader + department_team as custom claims (see `app/Models/User.php` -> `getJWTCustomClaims()`). Flutter does NOT need to parse these — it just sends the token and the server decides access.

#### Per-role screen gating

Flutter reads `user.role` from the login response and routes to the appropriate screen tree:

```
role == "customer"
   → CustomerScreen tree:
        - Submit Complaint button  → POST /api/incidents
        - My History list           → GET /api/incidents
        - Status Tracking           → GET /api/incidents/{id}
        - Notifications             → GET /api/notifications

role == "offsite_staff"
   → TeamLeaderScreen tree:
        - Assigned Queue list       → GET /api/assignments
        - Accept/Reject/Correct btn → POST /api/assignments/{id}/team-leader-action
        - Update Status btn         → PATCH /api/incidents/{id}/status
        - Availability Toggle       → POST /api/availability
        - Photo Proof Upload        → POST /api/incidents/{id}/attachments
        - Notifications             → GET /api/notifications

is_team_leader == true (subset of offsite_staff)
   → the same TeamLeaderScreen tree — Team Leaders ARE offsite staff
     with extra routing responsibility. UI may show the queue more prominently.
```

#### Token expiry + refresh

- TTL: 24 hours (`JWT_TTL=1440` in `.env`)
- On 401 from any API call: Flutter clears the JWT from secure storage and routes to the Login screen
- Flutter may proactively call `POST /api/auth/refresh` to get a new token before expiry

#### Why Customer and Offsite Staff share one app

Per capstone Section 3.4:
> "Customers, offsite team leaders, engineers, and administrators interact with the platform through a cross-platform mobile frontend developed in Flutter **and a web portal built on Laravel**."

Engineers and Administrators use the **Laravel web portal** (separate code path). Only Customers and Offsite Staff use the Flutter app. Both are served from the same binary because they share auth infrastructure and many UI patterns (login, notifications, profile).

---

## 2. Endpoints Consumed by Flutter

### 2.1 Authentication

#### POST /api/auth/register
**Used by:** Customer self-registration screen (DFD 1.1)
**Role required:** none (public)

Request body:
```json
{
  "full_name": "Robert Johnson",
  "email": "robert.j@example.com",
  "password": "secret123",
  "contact_no": "+639171234567",
  "address": "123 Test St, Caloocan City"
}
```

Response 201:
```json
{
  "access_token": "eyJ0eXAiOiJKV1QiLC...",
  "token_type": "bearer",
  "expires_in": 86400,
  "user": {
    "id": 2,
    "full_name": "Robert Johnson",
    "email": "robert.j@example.com",
    "role": "customer",
    "is_team_leader": false,
    "department_team": null
  }
}
```

#### POST /api/auth/login
**Used by:** Login screen (DFD 1.2)
**Role required:** none (public)

Request body:
```json
{ "email": "robert.j@example.com", "password": "secret123" }
```

Response 200: same shape as register.

Flutter must:
- Store `access_token` in `flutter_secure_storage`.
- Attach to every subsequent request via Dio interceptor.
- Refresh on 401 using `POST /api/auth/refresh`.

---

### 2.2 Customer Endpoints (DFD 2.0)

#### POST /api/incidents
**Used by:** Customer "Submit Complaint" button (DFD 2.1 -> 2.11)
**Role required:** customer (or any role passing `customer_id`)

Request body:
```json
{
  "description": "Walang tubig sa amin since kahapon pa.",
  "location": "Brgy 12, Caloocan City"
}
```

Response 201:
```json
{
  "data": {
    "id": 1,
    "customer_id": 2,
    "description": "Walang tubig...",
    "location": "Brgy 12, Caloocan City",
    "category": "Operations",
    "severity": "High",
    "composite_score": 1.2,
    "status": "in_progress",
    "submitted_at": "2026-09-07T14:53:51.000000Z",
    "created_at": "2026-09-07T14:53:51.000000Z",
    "customer": { "id": 2, "full_name": "Robert Johnson", "email": "..." }
  },
  "analysis": {
    "category": "Operations",
    "category_confidence": 0.9791,
    "sentiment": "NEGATIVE",
    "sentiment_score": 0.9,
    "composite_score": 1.2,
    "severity": "High"
  },
  "assignment": {
    "id": 1,
    "incident_id": 1,
    "team_leader_id": 5,
    "engineer_review_id": null,
    "action_status": "assigned",
    "assigned_at": "..."
  }
}
```

Flutter UX note: show a confirmation toast with `category`, `severity`, and
`composite_score` so the customer can see how their complaint was classified.

#### GET /api/incidents
**Used by:** Customer "Complaint History" and "Status Tracking" screens
**Role required:** any authenticated. Customers see only their own.

Query params: `?status=open&per_page=20`

Response 200:
```json
{
  "data": [
    {
      "id": 1,
      "category": "Operations",
      "severity": "High",
      "status": "in_progress",
      "submitted_at": "2026-09-07T14:53:51.000000Z",
      "customer": { "id": 2, "full_name": "..." }
    }
  ],
  "links": {...},
  "meta": {...}
}
```

#### GET /api/incidents/{id}
**Used by:** Customer "View Complaint Detail" screen
**Role required:** customer can only view their own.

Response 200: full incident with assignments, feedback, attachments.

---

### 2.3 Offsite Staff Endpoints (Capstone Section 1.4 / DFD 4.0)

#### GET /api/assignments
**Used by:** Offsite Staff "Assigned Incidents List" (DFD 5.1)
**Role required:** any authenticated. Offsite staff see only their own.

Response 200:
```json
{
  "data": [
    {
      "id": 1,
      "incident_id": 1,
      "team_leader_id": 5,
      "engineer_review_id": null,
      "assigned_at": "...",
      "action_status": "assigned",
      "incident": {
        "id": 1,
        "description": "...",
        "category": "Operations",
        "severity": "High",
        "composite_score": 1.2
      }
    }
  ]
}
```

#### POST /api/assignments/{id}/team-leader-action
**Used by:** Offsite Staff "Accept / Reject / Correct" buttons (DFD 4.4 / 4.5 / 4.7)
**Role required:** offsite_staff (and must be the assigned team leader)

Three variants of body:

a) Accept:
```json
{ "action": "accept" }
```

b) Reject (DFD 4.6 requires rejection reason):
```json
{ "action": "reject", "rejection_reason": "Wrong department, this is billing" }
```

c) Correct (DFD 4.7):
```json
{
  "action": "correct",
  "corrected_category": "Billing",
  "corrected_severity": "Medium"
}
```

Response 200:
```json
{
  "data": {
    "assignment": { "id": 1, "action_status": "correct", ... },
    "feedback": {
      "id": 1,
      "incident_id": 1,
      "action_taken": "correct",
      "corrected_category": "Billing",
      "corrected_severity": "Medium",
      "feedback_timestamp": "..."
    }
  }
}
```

Flutter UX: on success, navigate back to assignment list. Show snackbar:
- Accept: "Incident accepted"
- Reject: "Incident flagged for Engineer review"
- Correct: "Correction submitted for Engineer review"

#### PATCH /api/incidents/{id}/status
**Used by:** Offsite Staff "Update Status" buttons (DFD 5.3, 5.7)
**Role required:** offsite_staff, engineer, administrator

Body:
```json
{
  "status": "in_progress",
  "resolution_notes": "On the way to fix the pipe"
}
```

Valid statuses: `open`, `in_progress`, `resolved`, `rejected`.

Response 200: updated incident.

#### POST /api/availability
**Used by:** Offsite Staff "Availability Toggle" (Section 1.4)
**Role required:** offsite_staff

Body:
```json
{ "status": "available" }
```

Valid statuses: `available`, `on_duty`, `unavailable`, `on_break`.

Response 200:
```json
{
  "data": {
    "staff_id": 5,
    "status": "available",
    "updated_at": "..."
  }
}
```

#### GET /api/availability
**Used by:** Offsite Staff "View own status", Engineer "View team status"
**Role required:** any authenticated. Offsite staff see only own. Engineers see all.

Response 200:
```json
{
  "data": [
    { "staff_id": 5, "full_name": "Engr. Axel Brion", "status": "available" },
    { "staff_id": 6, "full_name": "Engr. Kairo Zenith", "status": "on_break" }
  ]
}
```

---

### 2.4 Photo Proof (DFD 5.6)

#### POST /api/incidents/{id}/attachments
**Used by:** Offsite Staff "Attach Photo Proof" button (DFD 5.6)
**Role required:** offsite_staff (must be assigned to the incident)

Content-Type: `multipart/form-data`
Fields:
- `file`: binary image (jpg, png; max 5 MB)
- `caption`: optional text

Response 201:
```json
{
  "data": {
    "id": 1,
    "incident_id": 1,
    "file_path": "attachments/1/photo-uuid.jpg",
    "original_name": "pipe-fix.jpg",
    "mime_type": "image/jpeg",
    "file_size": 245678,
    "caption": "Replaced broken valve",
    "url": "http://127.0.0.1:8000/storage/attachments/1/photo-uuid.jpg",
    "created_at": "..."
  }
}
```

Flutter UX: camera icon -> photo library OR camera capture -> upload -> thumbnail shown.

#### GET /api/incidents/{id}/attachments
**Used by:** Show thumbnails on incident detail.
**Role required:** any authenticated who can view the incident.

Response 200:
```json
{
  "data": [
    { "id": 1, "url": "...", "caption": "Replaced broken valve", ... }
  ]
}
```

---

### 2.5 Notifications (DFD 4.8 / 5.10)

#### GET /api/notifications
**Used by:** Customer + Offsite Staff "Notifications" screen
**Role required:** any authenticated (returns only own notifications)

Query params: `?unread_only=true`

Response 200:
```json
{
  "data": [
    {
      "id": 12,
      "incident_id": 1,
      "user_id": 2,
      "message": "Your complaint #1 has been adjudicated: override.",
      "is_read": false,
      "created_at": "..."
    }
  ]
}
```

#### PATCH /api/notifications/{id}/read
**Used by:** Tap-to-dismiss a notification
**Role required:** any authenticated (must own the notification)

Response 200:
```json
{ "data": { "id": 12, "is_read": true } }
```

---

### 2.6 Profile (DFD 1.8)

#### GET /api/auth/me
Returns the current user's profile. Already documented in Section 2.1.

#### PATCH /api/auth/profile
**Used by:** "Edit Profile" screen.
**Role required:** any authenticated

Body (all fields optional):
```json
{
  "full_name": "Robert J.",
  "contact_no": "+639180000000",
  "address": "New address"
}
```

Response 200: updated `me` shape.

---

## 3. Roles and Access Matrix (Flutter perspective)

| Endpoint | customer | offsite_staff | engineer | admin |
|----------|----------|---------------|----------|-------|
| POST /api/auth/register | yes (creates customer) | no | no | no |
| POST /api/incidents | yes | no | no | no (admin uses web) |
| GET /api/incidents | own only | assigned only | all | all |
| GET /api/assignments | no | own only | all | all |
| POST .../team-leader-action | no | own only | no | no |
| PATCH .../status | no | own only | yes | yes |
| POST /api/availability | no | own only | no | no |
| GET /api/availability | no | own only | all | all |
| POST /api/incidents/{id}/attachments | no | own only | no | no |

Anything not in Flutter's scope (engineer adjudication, admin user CRUD,
reports) is **web-portal only** and is documented separately.

---

## 4. Error Handling

All error responses follow Laravel's standard shape:

```json
{
  "message": "The given data was invalid.",
  "errors": {
    "email": ["The email field is required."]
  }
}
```

Flutter must:
- On 401: clear stored JWT, route to login.
- On 403: show "You do not have permission for this action."
- On 422: show field-level validation errors.
- On 5xx: show "Server error, please try again."

---

## 5. Required Flutter Dependencies

```yaml
dependencies:
  dio: ^5.4.0                    # HTTP client with interceptors
  flutter_secure_storage: ^9.0.0  # JWT storage
  image_picker: ^1.0.0           # Photo capture
  cached_network_image: ^3.3.0    # Thumbnail caching
  intl: ^0.19.0                  # Date formatting
  flutter_bloc: ^8.1.0           # State management
```

---

## 6. State Management Recommendation

For each user role, the app should have a dedicated screen tree:

- `CustomerScreen`: Submit, History, Detail, Notifications, Profile
- `TeamLeaderScreen`: Queue, Detail, AcceptActionSheet, AvailabilityToggle, Notifications, Profile

Use `flutter_bloc` for state. Each API call wrapped in a Bloc that emits:
`Loading -> Success(data) | Failure(message)`.

---

## 7. Out of Scope for Flutter

The following are explicitly handled by the **Laravel web portal**, not Flutter:

- Engineer adjudication (Approve / Override / Reassign)
- Admin user CRUD
- Admin routing rule configuration
- Reports / dashboard analytics
- Audit log inspection

See `imraws-backend-website/routes/web.php` for the web portal routes.

---

## 8. When Implementation Resumes

To pick up Flutter implementation later:

1. Copy this spec into a new Flutter project: `flutter create imraws_mobile`.
2. Set base URL via `--dart-define=API_URL=...`.
3. Wire Dio interceptor with secure storage for JWT.
4. Implement screens in order: Login -> Register -> Customer Submit -> Customer History -> TeamLeader Queue -> TeamLeader Action.
5. Test against the running Laravel backend on `http://127.0.0.1:8000`.
6. Use the actual tokens and incidents from Phase 9 (smoke test data) as fixtures.
