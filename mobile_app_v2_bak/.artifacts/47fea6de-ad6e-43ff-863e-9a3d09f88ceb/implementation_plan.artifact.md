# Implementation Plan - Role-Based Dashboard & Bottom Navigation

Adjust the application to support specific dashboard views and bottom navigation center buttons based on the user's role and `is_team_leader` status.

## User Review Required

> [!IMPORTANT]
> **Dashboard Uniformity**: You mentioned Offsite Staff should have the "same screen as customer screen". I interpret this as the **visual layout** (Header style, cards, lists) should be identical, but showing **Assignments** instead of **Complaints**.
> **Manage Field Crew**: I will create a new route `/manage-crew` and a placeholder screen for this functionality.
> **Center Button logic**:
> - **Customer**: Shows "Report" button.
> - **Offsite Staff (is_team_leader = true)**: Shows "Manage Field Crew" button.
> - **Offsite Staff (is_team_leader = false)**: No center button.
> - **Admin/Engineer**: Currently no center button (same as non-TL staff) unless you specify otherwise.

## Proposed Changes

### 1. Flutter Providers (`AuthProvider`)

#### [MODIFY] [auth_provider.dart](file:///C:/Projects/Mobile Design/mobile_app_v2_bak/lib/providers/auth_provider.dart)
- Add `bool get isTeamLeader`.
- Define `CenterButtonType` enum: `none`, `report`, `manageCrew`.
- Add `CenterButtonType get centerButtonType` logic.

### 2. Flutter UI Components

#### [MODIFY] [bottom_nav_bar.dart](file:///C:/Projects/Mobile Design/mobile_app_v2_bak/lib/widgets/bottom_nav_bar.dart)
- Replace `isCustomer` with `centerButtonType`.
- Update FAB to show either "Add" (Report) or "Group/People" (Manage Field Crew) icon based on type.
- Adjust labels and colors for the center button.

### 3. Flutter Screens

#### [NEW] [manage_crew_screen.dart](file:///C:/Projects/Mobile Design/mobile_app_v2_bak/lib/screens/manage_crew_screen.dart)
- Create a placeholder screen for Field Crew management.

#### [MODIFY] [main.dart](file:///C:/Projects/Mobile Design/mobile_app_v2_bak/lib/main.dart)
- Register `/manage-crew` route.

#### [MODIFY] [offsite_screen.dart](file:///C:/Projects/Mobile Design/mobile_app_v2_bak/lib/screens/offsite_screen.dart)
- Update header to match `HomeScreen` (use "Good morning/afternoon" greeting).
- Ensure card styling matches `HomeScreen` exactly.
- Connect center button to `/manage-crew`.

#### [MODIFY] Global Navigation Updates
- Update all screens (`About`, `Account`, `Settings`, etc.) to pass the correct `centerButtonType` to the bottom navigation.

## Verification Plan

### Automated Tests
- `flutter analyze` to ensure no breaks in widget property changes.

### Manual Verification
1. **Customer Login**: Verify "Report" button is visible and leads to `ComplaintScreen`.
2. **Offsite Staff (TL=true)**: Verify "Manage Field Crew" button is visible and leads to `ManageCrewScreen`.
3. **Offsite Staff (TL=false)**: Verify NO center button is visible.
4. **Admin/Engineer**: Verify NO center button is visible.
5. **Layout Check**: Verify `OffsiteScreen` looks visually identical to `HomeScreen` in terms of header and card structure.
