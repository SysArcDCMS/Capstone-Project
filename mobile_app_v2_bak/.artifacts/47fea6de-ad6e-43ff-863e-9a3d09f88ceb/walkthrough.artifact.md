# Walkthrough - Backend Integration for IMRAWS-NLP

Successfully connected the existing Flutter mobile application to the Laravel REST API backend with PostgreSQL.

## Changes Made

### 1. Database & Laravel Backend
- **tbl_users**: Added `account_number` field to support Flutter's registration requirements.
- **Models**: Implemented Eloquent models for all tables (`User`, `Incident`, `Assignment`, `Availability`, `Notification`, `Feedback`, `AuditLog`) with custom table names and primary keys.
- **Authentication**: Fully implemented **Laravel Sanctum** token-based authentication.
- **RBAC**: Implemented `RoleMiddleware` to enforce access control (Administrator, Engineer, Offsite Staff, Customer) at the API level.
- **API Endpoints**: Created controllers and routes for all required business logic:
    - Profile management.
    - Incident submission and history (with ownership checks).
    - Task assignments for field staff.
    - Real-time availability updates.
    - System notifications.
- **Seeder**: Created a `UserSeeder` with test accounts for all roles.

### 2. Flutter Mobile Application
- **Dependencies**: Added `http`, `flutter_secure_storage`, `provider`, and `intl`.
- **Architecture**: Established a centralized service-provider architecture:
    - `ApiService`: Base client with JWT/Sanctum token injection.
    - `AuthService`, `IncidentService`, etc.: Domain-specific logic.
    - `AuthProvider`, `IncidentProvider`: State management using Provider.
- **UI Integration**:
    - **Login/Signup**: Connected to backend; redirects users to the correct dashboard based on their role.
    - **Customer Dashboard**: Displays real complaint history and latest status from the database.
    - **Complaint Submission**: Real submission with NLP classification placeholder.
    - **Offsite Staff Screen**: Shows real assigned tasks and allows status/availability updates.
    - **Account Settings**: Displays and persists user profile information.

## Verification Results

### Backend API
- `php artisan migrate:fresh` executed successfully.
- `php artisan db:seed` populated test data correctly.
- All protected routes verified for Authentication and RBAC.

### Flutter Analysis
- `flutter analyze` confirmed no critical errors in the implementation.

## How to Run

### Backend
1. Ensure PostgreSQL is running and the database `imraws_db` exists.
2. Update `.env` with your DB credentials if different from the default.
3. Run migrations and seed:
   ```bash
   php artisan migrate:fresh --seed
   ```
4. Start the server:
   ```bash
   php artisan serve
   ```

### Mobile App
1. Ensure the backend server is running.
2. If using Android Emulator, the `baseUrl` in `lib/services/api_config.dart` is pre-configured to `10.0.2.2`.
3. Run the app:
   ```bash
   flutter run
   ```

### Test Accounts
- **Customer**: `customer@gmail.com` / `password123`
- **Staff (TL)**: `staff@imraws.com` / `password123`
- **Admin**: `admin@imraws.com` / `password123`
