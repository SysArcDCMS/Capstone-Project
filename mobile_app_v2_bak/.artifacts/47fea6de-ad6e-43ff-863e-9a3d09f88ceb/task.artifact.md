# Tasks - Backend Integration for IMRAWS-NLP

## Phase 1: Laravel Backend Implementation

### A. Database/Model Verification
- `[x]` Update `tbl_users` migration to include `account_number`
- `[x]` Update `User` model to include `account_number` in fillable
- `[x]` Create/Verify models for `Incident`, `Assignment`, `Availability`, `Notification`, `Feedback`, `AuditLog`
- `[x]` Run migrations and verify table structure in PostgreSQL

### B. Authentication with Sanctum
- `[x]` Refine `AuthController` (login/register/logout)
- `[x]` Ensure it handles `password_hash` correctly as per existing migration

### C. RBAC Middleware
- `[x]` Create `RoleMiddleware` to handle role-based access control
- `[x]` Register middleware in `bootstrap/app.php` (Laravel 11+)

### D. User/Profile APIs
- `[x]` Implement `GET /api/me` and `PUT /api/profile`

### E. Incident APIs
- `[x]` Implement `POST /api/incidents` (Customer)
- `[x]` Implement `GET /api/my-incidents` (Customer)
- `[x]` Implement `GET /api/incidents` (Admin/Engineer/Staff)
- `[x]` Implement `GET /api/incidents/{id}`

### F. Assignment APIs
- `[x]` Implement `GET /api/assignments`
- `[x]` Implement `POST /api/assignments/{id}/accept|reject|correct`

### G. Availability APIs
- `[x]` Implement `GET /api/availability/me`
- `[x]` Implement `PUT /api/availability/me`

### H. Notification APIs
- `[x]` Implement `GET /api/notifications`
- `[x]` Implement `POST /api/notifications/{id}/read`

### I. Feedback/HITL APIs
- `[x]` Implement endpoints for Team Leader feedback on NLP classifications

### J. Audit Log Functionality
- `[x]` Implement helper/service to record audit logs

### K. Reports/Admin APIs
- `[x]` Implement basic user management for Admin

### L. Python NLP Integration Bridge
- `[x]` Create `NlpService` placeholder/interface in Laravel

---

## Phase 2: Flutter Integration

### M. Flutter API/Service Layer
- `[x]` Add dependencies to `pubspec.yaml`
- `[x]` Create `ApiClient` and `BaseService`
- `[x]` Implement models in Dart

### N. Flutter Authentication Integration
- `[x]` Connect `LoginScreen` to `AuthService`
- `[x]` Connect `SignUpScreen` to `AuthService`
- `[x]` Implement role-based redirection logic

### O. Flutter Complaint Integration
- `[x]` Connect `ComplaintScreen` to `IncidentService`
- `[x]` Connect `HomeScreen` (Customer) to `IncidentService` for history

### P. Flutter Offsite Staff Integration
- `[x]` Connect `OffsiteScreen` to `AssignmentService` and `AvailabilityService`

### Q. Flutter Notifications/Profile Integration
- `[x]` Connect `AccountScreen` to `AuthService`
- `[x]` Connect notifications to the UI

### S. Role Restriction (Customer Only Complaints)
- `[x]` Update Laravel `api.php` to restrict `POST /api/incidents` to `customer` role
- `[x]` Update Flutter `MayniladBottomNav` to conditionally show "Report" FAB
- `[x]` Update all Flutter screens to pass `isCustomer` flag to bottom nav

---

## Phase 3: Verification
### R. End-to-End Testing
- `[x]` API testing with Postman/cURL
- `[x]` Flutter integration testing
- `[x]` Final audit of all requirements
