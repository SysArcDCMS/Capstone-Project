---

**Colors:**
- Primary: `#0EA5E9`
- Background: `#F8FAFC`
- Surface/Cards: `#FFFFFF`
- Error: `#EF4444`
- Success: `#22C55E`
- Warning: `#F59E0B`
- Primary Text: `#0F172A`
- Secondary Text: `#64748B`

**Typography:**
- Font family: **Inter** (add via Google Fonts)
- Heading: 24sp Bold
- Body: 14sp Regular
- Caption: 12sp Regular, color `#64748B`

**Border Radius:**
- Cards: 12px
- Buttons: 8px
- Input fields: 8px

**Default Button Style:**
- Filled: background `#0EA5E9`, white text, full width
- Outlined: `#0EA5E9` border, blue text

---

## 🔧 Pre‑Flight: App State & Data Structures

Before generating any page, create these **App State variables** in FlutterFlow (`App Values → App State`). They act as the mock database and are referenced by every page below.

| Variable Name | Data Type | Is List? | Nullable? | Initial Value (Mock Data) |
|---------------|-----------|----------|-----------|---------------------------|
| `mockComplaints` | **Data Type** (see struct below) | ✅ Yes | ❌ No | (see mock JSON below) |
| `mockNotifications` | **Data Type** (see struct below) | ✅ Yes | ❌ No | (see mock JSON below) |
| `mockStaffQueue` | **Data Type** (see struct below) | ✅ Yes | ❌ No | (see mock JSON below) |
| `mockResolvedIncidents` | **Data Type** (see struct below) | ✅ Yes | ❌ No | (see mock JSON below) |
| `currentUserRole` | String | ❌ No | ❌ No | `"customer"` |
| `currentUserId` | String | ❌ No | ❌ No | `"USR-001"` |
| `isLoggedIn` | Boolean | ❌ No | ❌ No | `false` |

**Create Data Types in FlutterFlow** (under `Data Types → Create New`):

1. **Complaint** (fields: `id` String, `text` String, `category` String, `severity` String, `status` String, `submittedAt` String, `resolvedAt` String?, `assignedTeamLeader` String?, `resolutionNotes` String?, `sentimentScore` Double?, `location` String?)
2. **NotificationItem** (fields: `id` String, `title` String, `subtitle` String, `timestamp` String, `isRead` Boolean, `type` String, `relatedComplaintId` String)
3. **StaffAssignment** (fields: `incidentId` String, `complaintText` String, `category` String, `severity` String, `assignedAt` String, `customerLocation` String, `status` String)

**Add mock JSON** as the Initial Value for each list variable. Example for `mockComplaints`:

```json
[
  {
    "id": "INC-001",
    "text": "No water supply since this morning in Barangay 123. We have a newborn and need water urgently.",
    "category": "Operations Issue",
    "severity": "High",
    "status": "In Progress",
    "submittedAt": "2026-04-27T08:00:00",
    "resolvedAt": null,
    "assignedTeamLeader": "Juan Dela Cruz",
    "resolutionNotes": null,
    "sentimentScore": -0.72,
    "location": "Barangay 123, Caloocan City"
  },
  {
    "id": "INC-002",
    "text": "My water bill this month is double the usual amount. Please check.",
    "category": "Billing Issue",
    "severity": "Medium",
    "status": "Open",
    "submittedAt": "2026-04-26T14:30:00",
    "resolvedAt": null,
    "assignedTeamLeader": null,
    "resolutionNotes": null,
    "sentimentScore": -0.35,
    "location": "Barangay 45, Caloocan City"
  },
  {
    "id": "INC-003",
    "text": "Brownish water coming out of the tap. Smells metallic.",
    "category": "Water Quality Concern",
    "severity": "High",
    "status": "Resolved",
    "submittedAt": "2026-04-25T10:15:00",
    "resolvedAt": "2026-04-25T16:45:00",
    "assignedTeamLeader": "Maria Santos",
    "resolutionNotes": "Flushed main line and replaced corroded pipe section. Water now clear.",
    "sentimentScore": -0.58,
    "location": "Barangay 67, Caloocan City"
  },
  {
    "id": "INC-004",
    "text": "Water meter glass is cracked and I cannot read the numbers properly.",
    "category": "Metering Issue",
    "severity": "Low",
    "status": "Open",
    "submittedAt": "2026-04-28T09:00:00",
    "resolvedAt": null,
    "assignedTeamLeader": null,
    "resolutionNotes": null,
    "sentimentScore": -0.15,
    "location": "Barangay 12, Caloocan City"
  }
]
```

Populate `mockNotifications`, `mockStaffQueue`, and `mockResolvedIncidents` similarly with at least 3‑4 realistic entries each.

---

# 🧩 PAGE PROMPTS (Functional & Dreamflow‑Ready)

---

## PAGE 1 — CUSTOMER REGISTRATION SCREEN

```
## Task
Create a fully functional Customer Registration page in FlutterFlow for the IMRAWS‑NLP water services app.

## Scope
- This page belongs to the **unauthenticated** flow. It is shown when a new user taps “Create Account” on the Login page.
- Only the **Customer** role can self‑register. Staff accounts are created by the Administrator via the web portal — do NOT create a staff registration path.
- After successful registration, navigate to the Login screen with a success snackbar “Account created! Please sign in.”

## UI Layout (Behaviour‑First)
- **Back arrow** (top‑left) → Navigate Back to Login page (Replace Route: false).
- **“Create Account”** title centred.
- White card (rounded‑16, shadow‑sm) containing:
  - `fullNameField`: TextField with person icon, label “Full Name”.
  - `emailField`: TextField with email icon, keyboard type = email, label “Email”.
  - `passwordField`: TextField with lock icon, obscure text, show/hide toggle suffix icon, label “Password”.
  - `confirmPasswordField`: TextField with lock icon, obscure text, label “Confirm Password”.
  - `errorText`: Text widget (red, 13sp), **Conditional Visibility** — shown only when `pageState.errorMessage` is not empty.
  - `registerButton`: Filled blue full‑width button, text “Register”.
- Footer: “Already have an account? Sign In” tappable text → Navigate Back to Login.
- App version “v1.0.0” centred at bottom.

## State Variables (Page State)
Create these **Page State** variables on this page:
1. `fullName` (String, nullable, default: empty)
2. `email` (String, nullable, default: empty)
3. `password` (String, nullable, default: empty)
4. `confirmPassword` (String, nullable, default: empty)
5. `errorMessage` (String, nullable, default: empty)
6. `isLoading` (Boolean, non‑nullable, default: false)

## Data Binding
- Bind each TextField’s **onChanged** to update its corresponding Page State variable using **Update Page State** action.
- The `errorText` widget’s **Text** property is bound to `pageState.errorMessage`.
- The `registerButton` **disabled** property is bound to: `pageState.fullName.isEmpty || pageState.email.isEmpty || pageState.password.isEmpty || pageState.confirmPassword.isEmpty || pageState.password != pageState.confirmPassword`.

## Action Flow — Register Button (On Tap)
Create an **Action Flow** on the Register button with these steps:
1. **Update Page State**: set `isLoading` = `true`.
2. **Conditional Action**: if `pageState.password` != `pageState.confirmPassword` → Update Page State `errorMessage` = `"Passwords do not match"`, then Update Page State `isLoading` = `false`, then **Break** (stop).
3. **Wait** (0.8 seconds) — simulate network call.
4. **Update App State**: Add the new user to a mock users list (or simply set `appState.isLoggedIn` = `false` for now; we simulate by navigating).
5. **Update Page State**: `isLoading` = `false`.
6. **Show Snackbar**: message = “Account created! Please sign in.”, background = green.
7. **Navigate**: to Login page (Replace Route: true).

## Loading State
When `pageState.isLoading` is `true`:
- The `registerButton` shows a CircularProgressIndicator (white, 20×20) instead of text “Register”.
- The button is disabled.

## Empty / Edge Cases
- If any field is empty, the Register button is disabled (handled by the disabled binding above).
- If passwords don’t match, show inline error and stop the flow.
- If the mock “network call” fails (you can hard‑code a false condition for now), show errorMessage “Registration failed. Please try again.”

## Acceptance Criteria
- [ ] All fields capture user input and update Page State.
- [ ] Register button is disabled until all fields are non‑empty and passwords match.
- [ ] Passwords that don’t match show the error message and stop the flow.
- [ ] On success, a snackbar appears and the user is navigated to Login.
- [ ] Back arrow returns to Login.
- [ ] “Sign In” link returns to Login.
```

---

## PAGE 2 — LOGIN SCREEN

```
## Task
Create a fully functional Login page in FlutterFlow for the IMRAWS‑NLP water services app.

## Scope
- This page belongs to the **unauthenticated** flow. It is the first screen shown when the app launches (if not logged in).
- Both Customer and Offsite Staff roles authenticate through this single login screen.
- After successful login, navigate to the correct home screen based on `appState.currentUserRole`.

## UI Layout
- White background with subtle blue gradient at top.
- Centered water droplet icon (blue) + “IMRAWS‑NLP” bold title.
- Subtitle: “Water Services Incident Management”.
- `emailField`: TextField with email icon, keyboard type = email, label “Email”.
- `passwordField`: TextField with lock icon, obscure text, show/hide toggle suffix icon, label “Password”.
- `errorText`: Text widget (red, 13sp), Conditional Visibility — shown only when `pageState.errorMessage` is not empty.
- `signInButton`: Filled blue full‑width button, text “Sign In”.
- “Forgot password?” small gray text (non‑functional placeholder for now).
- Demo Accounts box (light blue background card, rounded‑8):
  - Row 1: person icon + “customer@demo.com / demo1234” + small blue `useCustomerButton`.
  - Row 2: wrench icon + “staff@demo.com / demo1234” + small blue `useStaffButton`.
- “Create Account” tappable link → Navigate to Registration page.
- App version “v1.0.0” centred at bottom.

## State Variables (Page State)
1. `email` (String, nullable, default: empty)
2. `password` (String, nullable, default: empty)
3. `errorMessage` (String, nullable, default: empty)
4. `isLoading` (Boolean, non‑nullable, default: false)

## Data Binding
- Bind each TextField’s **onChanged** to update its Page State variable.
- `errorText` bound to `pageState.errorMessage`.
- `signInButton` disabled when `pageState.email.isEmpty || pageState.password.isEmpty`.

## Action Flow — Sign In Button (On Tap)
1. **Update Page State**: `isLoading` = `true`, `errorMessage` = `""`.
2. **Wait** (0.6 seconds) — simulate authentication.
3. **Conditional Action** — check credentials against mock hard‑coded values:
   - If `pageState.email` == `"customer@demo.com"` AND `pageState.password` == `"demo1234"`:
     - **Update App State**: `currentUserRole` = `"customer"`, `isLoggedIn` = `true`.
     - **Update Page State**: `isLoading` = `false`.
     - **Navigate**: to CustomerHomeDashboard (Replace Route: true).
   - Else if `pageState.email` == `"staff@demo.com"` AND `pageState.password` == `"demo1234"`:
     - **Update App State**: `currentUserRole` = `"staff"`, `isLoggedIn` = `true`.
     - **Update Page State**: `isLoading` = `false`.
     - **Navigate**: to StaffIncidentQueue (Replace Route: true).
   - Else:
     - **Update Page State**: `errorMessage` = `"Invalid email or password. Try the demo accounts."`, `isLoading` = `false`.

## Action Flow — Use Customer Button
1. **Update Page State**: `email` = `"customer@demo.com"`, `password` = `"demo1234"`.

## Action Flow — Use Staff Button
1. **Update Page State**: `email` = `"staff@demo.com"`, `password` = `"demo1234"`.

## Loading State
When `pageState.isLoading` is `true`: the button shows a CircularProgressIndicator instead of text, and is disabled.

## Acceptance Criteria
- [ ] Demo “Use” buttons auto‑fill email and password fields.
- [ ] Sign In with correct demo credentials navigates to the role‑appropriate home screen and sets App State.
- [ ] Incorrect credentials show error message.
- [ ] Sign In button is disabled when fields are empty.
- [ ] “Create Account” link navigates to Registration page.
```

---

## PAGE 3 — CUSTOMER HOME DASHBOARD

```
## Task
Create a fully functional Customer Home Dashboard in FlutterFlow for the IMRAWS‑NLP app.

## Scope
- This page is the landing screen for authenticated **Customer** users.
- It shows a summary of the customer’s complaints and provides quick access to submit a new complaint.

## UI Layout
- Top bar: blue bell icon (top‑right) with **unread badge** (red circle with count). Tapping navigates to Notifications page.
- “Welcome back, [Name]” heading. Use mock name “John Customer”.
- Summary cards row (3 small cards):
  - “Open”: count of complaints with status “Open” (blue).
  - “In Progress”: count with status “In Progress” (amber).
  - “Resolved”: count with status “Resolved” (green).
- “Recent Complaints” section title.
- ListView showing the 3 most recent complaints from `appState.mockComplaints` (sorted by `submittedAt` descending). Each card shows: ID, text preview (1 line), status chip, and chevron right. Tap → navigate to ComplaintDetail page, passing the complaint ID as a **Parameter**.
- Floating Action Button (blue, “+” icon) → Navigate to SubmitComplaint page.
- Bottom nav bar: Home (active), My Complaints, Profile.

## State Variables (Page State)
1. `openCount` (Integer, non‑nullable, default: 0)
2. `inProgressCount` (Integer, non‑nullable, default: 0)
3. `resolvedCount` (Integer, non‑nullable, default: 0)
4. `recentComplaints` (List of Complaint, nullable, default: empty)

## On Page Load Action Flow
Create an **On Page Load** action flow:
1. **Custom Function** (or series of Update Page State actions): filter `appState.mockComplaints` where `createdBy` == `appState.currentUserId` (or simply use all mock complaints for demo).
2. **Update Page State**: set `openCount` = count of filtered complaints where status == “Open”.
3. **Update Page State**: set `inProgressCount` = count where status == “In Progress”.
4. **Update Page State**: set `resolvedCount` = count where status == “Resolved”.
5. **Update Page State**: set `recentComplaints` = filtered complaints sorted by `submittedAt` descending, take first 3.

## Data Binding
- Summary card counts are bound to `pageState.openCount`, `pageState.inProgressCount`, `pageState.resolvedCount`.
- The ListView is generated from `pageState.recentComplaints`.
- The notification badge visibility: **Conditional Visibility** — shown when unread notification count > 0. Unread count = filter `appState.mockNotifications` where `isRead` == false, then `.length`.

## Empty State
If `pageState.recentComplaints` is empty: show illustration (water drop) + “No complaints yet” + “Submit your first complaint” button → Navigate to SubmitComplaint.

## Acceptance Criteria
- [ ] Summary counts reflect actual filtered data from mockComplaints.
- [ ] Recent complaints list shows up to 3 items, each tappable to navigate to detail.
- [ ] Notification badge shows unread count and navigates to Notifications.
- [ ] FAB navigates to Submit Complaint.
- [ ] Bottom nav works — My Complaints and Profile tabs navigate correctly.
```

---

## PAGE 4 — SUBMIT COMPLAINT

```
## Task
Create a fully functional Submit Complaint page in FlutterFlow for the IMRAWS‑NLP app.

## Scope
- Customer role only.
- The complaint text is submitted and stored into `appState.mockComplaints` as a new entry (simulating API call).
- After submission, navigate to My Complaints list with a success snackbar.

## UI Layout
- Top: back arrow + “Submit Complaint” title.
- White card (rounded‑16):
  - `complaintTextField`: multi‑line TextField (maxLines: 6, minLines: 4), label “Describe your concern...”, hint “e.g., No water supply in Barangay 123 since this morning”.
  - Character count below field: “0/500” (updates in real‑time).
  - `severityPreview` row (Conditional Visibility — shown only after text length > 10): label “Estimated Severity:” + coloured badge. Use a simple mock: if text contains “urgent” or “no water” → “High” (red). If contains “bill” or “charge” → “Medium” (amber). Else → “Low” (green).
- `submitButton`: Filled blue full‑width button, text “Submit Complaint”.

## State Variables (Page State)
1. `complaintText` (String, nullable, default: empty)
2. `isSubmitting` (Boolean, non‑nullable, default: false)
3. `characterCount` (Integer, non‑nullable, default: 0)
4. `estimatedSeverity` (String, nullable, default: empty)

## Data Binding
- `complaintTextField` **onChanged** → Update Page State `complaintText` with current value, and Update Page State `characterCount` with `complaintText.length`.
- `submitButton` disabled when `pageState.complaintText.isEmpty || pageState.complaintText.length > 500 || pageState.isSubmitting`.
- Severity preview updates whenever `complaintText` changes (use Conditional Action: if length > 10, run severity mock logic).

## Action Flow — Submit Button (On Tap)
1. **Update Page State**: `isSubmitting` = `true`.
2. **Wait** (1.0 second) — simulate API.
3. **Update App State**: append a new Complaint struct to `appState.mockComplaints` with:
   - `id`: “INC‑” + (current timestamp milliseconds).
   - `text`: `pageState.complaintText`.
   - `category`: “Pending Classification” (mock).
   - `severity`: `pageState.estimatedSeverity`.
   - `status`: “Open”.
   - `submittedAt`: current ISO timestamp.
   - All other fields: null.
4. **Update Page State**: `isSubmitting` = `false`.
5. **Show Snackbar**: “Complaint submitted successfully! Track it in My Complaints.” (green).
6. **Navigate**: to MyComplaintsList page (Replace Route: false).

## Empty / Validation States
- Submit button disabled if text is empty or exceeds 500 characters.
- Character count turns red when > 450 characters.

## Acceptance Criteria
- [ ] Complaint text is captured and stored into mockComplaints.
- [ ] Character counter updates in real‑time.
- [ ] Estimated severity preview appears after 10+ characters.
- [ ] Submit shows loading state, then navigates with snackbar.
- [ ] New complaint appears in My Complaints list after submission.
```

---

## PAGE 5 — CUSTOMER MY COMPLAINTS LIST

```
## Task
Create a fully functional, filterable, searchable My Complaints list page in FlutterFlow.

## Scope
- Customer role only. Shows all complaints submitted by this customer (from `appState.mockComplaints`).
- Filter chips and search bar work in real‑time without page reload.

## UI Layout
- Top: back arrow + “My Complaints” title + filter icon (top‑right, non‑functional placeholder or toggles advanced filter panel).
- **Filter chips row** (horizontally scrollable): `All`, `Open`, `In Progress`, `Resolved`.
  - Active chip: filled blue bg, white text.
  - Inactive chip: outlined blue border, blue text.
- **Search bar**: TextField with search icon, hint “Search complaints...”.
- **Complaint list**: ListView of complaint cards, each showing:
  - Row 1: Complaint ID (#INC‑001, monospace) + Status chip (right‑aligned): Open=blue, In Progress=amber, Resolved=green.
  - Row 2: Complaint text preview (2 lines, ellipsis overflow, gray).
  - Row 3: Category badge + Severity badge side‑by‑side.
  - Row 4: “Submitted [date]” small gray + chevron right.
- Tap card → Navigate to ComplaintDetail, passing complaint ID as parameter.

## State Variables (Page State)
1. `allComplaints` (List of Complaint, nullable, default: from `appState.mockComplaints`)
2. `filteredComplaints` (List of Complaint, nullable, default: same as allComplaints)
3. `activeFilter` (String, non‑nullable, default: “All”)
4. `searchQuery` (String, nullable, default: empty)
5. `displayedComplaints` (List of Complaint, nullable, default: same as filteredComplaints)

## On Page Load
- **Update Page State**: set `allComplaints` = `appState.mockComplaints`.
- **Update Page State**: set `filteredComplaints` = `allComplaints`.
- **Update Page State**: set `displayedComplaints` = `filteredComplaints`.

## Filter Logic (Action Flow on each chip tap)
Create a reusable **Action Block** called `applyFilters` that:
1. Starts with `allComplaints`.
2. If `activeFilter` != “All”, filter where `status` == `activeFilter`.
3. If `searchQuery` is not empty, filter where `text` contains `searchQuery` (case‑insensitive) OR `id` contains `searchQuery`.
4. **Update Page State**: `displayedComplaints` = filtered result.

Each filter chip’s **On Tap**:
1. **Update Page State**: `activeFilter` = chip label.
2. **Execute Action Block**: `applyFilters`.

## Search Logic
Bind the search TextField **onChanged** to:
1. **Update Page State**: `searchQuery` = current value.
2. **Execute Action Block**: `applyFilters`.

## Real‑Time Behaviour (Key Functional Requirement)
The search bar uses FlutterFlow’s **Simple Search (Strings)** action or a manual filter via Page State. Since `displayedComplaints` is a Page State variable, updating it triggers a widget rebuild.

## Data Binding
- The ListView is generated from `pageState.displayedComplaints`.
- Each card’s data is bound to the Complaint struct fields of the current item.

## Empty State
If `pageState.displayedComplaints` is empty after filtering:
- Show water drop illustration + “No complaints match your filters” + “Clear Filters” button that resets `activeFilter` to “All” and `searchQuery` to empty.

## Acceptance Criteria
- [ ] Filter chips update the list in real‑time without navigation.
- [ ] Search bar filters by complaint text and ID in real‑time.
- [ ] Active filter chip is visually distinct.
- [ ] Tapping a card navigates to Complaint Detail with the correct complaint ID.
- [ ] Empty state appears when no results match.
```

---

## PAGE 6 — CUSTOMER COMPLAINT DETAIL

```
## Task
Create a read‑only Complaint Detail page for the Customer role in FlutterFlow.

## Scope
- Shows the full lifecycle of a single complaint, including AI classification results and status timeline.
- Data is read from `appState.mockComplaints` by matching the complaint ID passed as a page parameter.

## Page Parameters
Create a **Page Parameter**: `complaintId` (String, required).

## UI Layout
- Top: back arrow + “Complaint #INC‑XXX” title (ID from complaint data).
- Scrollable content:
  - **“Complaint” card**: full complaint text in styled blockquote, submitted date, customer location.
  - **“AI Analysis” card** (blue left border accent):
    - Category: coloured badge + mock confidence bar (blue progress bar, e.g., 87%).
    - Severity: large coloured badge (High=red, Medium=amber, Low=green) + explanation text.
    - Sentiment: emoji icon (😠/😐/🙂) + label + score.
  - **“Status Timeline” card**: vertical timeline with dots and lines:
    - “Submitted” — [date] (green dot, always present).
    - “AI Classified” — [category, severity] (blue dot, shown after submission).
    - “Assigned to [Name]” — [date] (amber dot, shown if assignedTeamLeader is not null).
    - “In Progress” — [date] (amber dot, shown if status == “In Progress” or later).
    - “Resolved” — [date] (green dot, shown if status == “Resolved”).
  - If status == “Resolved”, show **“Resolution” card**: resolution notes + photo placeholder.

## State Variables (Page State)
1. `complaint` (Complaint struct, nullable, default: null)

## On Page Load Action Flow
1. **Update Page State**: find the complaint in `appState.mockComplaints` where `id` == `pageParameter.complaintId`, and set `complaint` to that struct.
2. If not found, show error text “Complaint not found” and a “Go Back” button.

## Data Binding
All displayed data is bound to `pageState.complaint.*` fields using **Conditional Visibility** for timeline steps that don’t yet exist.

## Acceptance Criteria
- [ ] Page correctly loads and displays the complaint matching the passed ID.
- [ ] AI Analysis card shows category, severity, and sentiment.
- [ ] Status timeline shows only completed steps.
- [ ] Resolution details shown only if status is Resolved.
- [ ] Handles missing complaint gracefully.
```

---

## PAGE 7 — CUSTOMER NOTIFICATIONS SCREEN

```
## Task
Create a fully functional Notifications page for the Customer role in FlutterFlow.

## Scope
- Shows all notifications from `appState.mockNotifications`.
- Notifications can be filtered, marked as read, and deleted.

## UI Layout
- Top: back arrow + “Notifications” title + “Mark all read” text button (top‑right, blue, Conditionally Visible only if unread count > 0).
- **Filter tabs**: `All` | `Unread` | `Updates` (horizontal row of tappable chips).
- **Notification list**: ListView of notification cards, each showing:
  - Left: coloured icon circle — blue (status updates), green (resolved), amber (assigned), red (urgent).
  - Centre: bold title + subtitle preview (1 line, gray).
  - Right: relative timestamp (e.g., “2h ago”).
  - Unread notifications: light blue background tint + blue left border accent.
  - Read notifications: plain white background.
- Tap notification → Navigate to ComplaintDetail with `relatedComplaintId`.
- Swipe left on notification → shows red “Delete” button.

## State Variables (Page State)
1. `notifications` (List of NotificationItem, nullable, default: from appState)
2. `filteredNotifications` (List of NotificationItem, nullable, default: same)
3. `activeFilter` (String, non‑nullable, default: “All”)
4. `unreadCount` (Integer, non‑nullable, default: 0)

## On Page Load
- Set `notifications` = `appState.mockNotifications` (sorted by timestamp descending).
- Set `filteredNotifications` = `notifications`.
- Calculate `unreadCount` = count of `notifications` where `isRead` == false.

## Filter Logic (same pattern as My Complaints)
**Action Block** `applyNotificationFilter`:
- If `activeFilter` == “Unread”: filter where `isRead` == false.
- If `activeFilter` == “Updates”: filter where `type` == “Status Updated” or “Resolved”.
- Update `filteredNotifications`.

## Tap Notification
1. **Update App State**: find the notification in `appState.mockNotifications` by ID, set `isRead` = `true`.
2. **Navigate**: to ComplaintDetail, passing `relatedComplaintId`.

## Swipe to Delete
Use FlutterFlow’s **Dismissible** widget or a conditional swipe action:
1. **Update App State**: remove the notification from `appState.mockNotifications`.
2. **Update Page State**: refresh `notifications` and `filteredNotifications` from appState.

## Mark All Read
1. **Update App State**: loop through all notifications where `isRead` == false, set `isRead` = `true`.
2. **Update Page State**: refresh lists.

## Empty State
Bell illustration + “No notifications yet”.

## Acceptance Criteria
- [ ] Filter tabs work in real‑time.
- [ ] Unread notifications are visually distinct.
- [ ] Tapping a notification marks it as read and navigates to complaint detail.
- [ ] Swipe to delete removes the notification from both page and app state.
- [ ] “Mark all read” updates all notifications and refreshes the list.
```

---

## PAGE 8 — CUSTOMER PROFILE PAGE

```
## Task
Create a Customer Profile page in FlutterFlow with functional logout.

## Scope
- Customer role only. Accessed via bottom nav “Profile” tab.
- Shows mock user information.
- Logout button clears session and navigates to Login.

## UI Layout
- Top: “Profile” title centred. No back arrow (this is a bottom nav tab).
- Profile header card (white, rounded‑16, shadow‑sm):
  - Large circular avatar (blue bg, white person icon).
  - Full name bold 18sp: “John Customer”.
  - Email gray 13sp: “customer@demo.com”.
  - Small blue outlined “Edit Profile” button (non‑functional placeholder).
- **“Account”** section title (gray uppercase small):
  - List tile: person icon + “Personal Information” + chevron right (placeholder).
  - List tile: lock icon + “Change Password” + chevron right (placeholder).
  - List tile: bell icon + “Notification Preferences” + chevron right + toggle (non‑functional).
- **“Support”** section title:
  - List tile: help‑circle icon + “Help & FAQ” (placeholder).
  - List tile: message‑circle icon + “Contact Support” (placeholder).
  - List tile: info icon + “About IMRAWS‑NLP” (placeholder).
- Divider.
- **Danger zone**: full‑width red outlined button “Log Out” with logout icon.
- App version “v1.0.0” centred at bottom.
- Bottom nav: Home, My Complaints, Profile (active).

## Action Flow — Log Out Button
1. **Show Dialog**: title “Log Out?”, content “Are you sure you want to log out?”, Cancel button (gray), “Log Out” button (red filled).
2. On “Log Out” confirm:
   - **Update App State**: `isLoggedIn` = `false`, `currentUserRole` = `""`, `currentUserId` = `""`.
   - **Navigate**: to Login page (Replace Route: true, clear navigation stack).

## Acceptance Criteria
- [ ] Logout shows confirmation dialog.
- [ ] On confirm, app state is cleared and user is sent to Login.
- [ ] Bottom nav tabs work: Home, My Complaints, Profile.
```

---

## PAGE 9 — STAFF INCIDENT QUEUE

```
## Task
Create a functional incident queue page for the Offsite Staff / Team Leader role in FlutterFlow.

## Scope
- Staff role only. Shows all incidents assigned to this staff member (from `appState.mockStaffQueue`).
- Pull‑to‑refresh updates the list.

## UI Layout
- Top: “My Queue” title + notification bell icon (top‑right, same pattern as customer notifications).
- **Summary card** (light blue bg, rounded‑12):
  - “Pending: [n]” bold left.
  - “Due Today: [n]” gray right.
- **Incident list**: ListView of assignment cards, each showing:
  - Row 1: Incident ID monospace + severity chip (right‑aligned).
  - Row 2: Complaint text preview (2 lines, gray).
  - Row 3: Category badge + “Assigned [time]” small gray.
  - Row 4: Tap to view details chevron right.
- Tap card → Navigate to StaffIncidentDetail, passing incident ID.
- Bottom nav: Queue (active), Resolved, Availability, Profile.

## State Variables (Page State)
1. `queueItems` (List of StaffAssignment, nullable, default: empty)
2. `pendingCount` (Integer, default: 0)

## On Page Load
- Set `queueItems` = filter `appState.mockStaffQueue` where `status` != “Resolved”.
- Set `pendingCount` = `queueItems.length`.

## Pull to Refresh
- **Action Flow**: reload from appState (simulate backend refresh).

## Empty State
“No pending incidents” + checkmark illustration.

## Acceptance Criteria
- [ ] Queue shows only non‑resolved incidents for this staff member.
- [ ] Summary card shows accurate pending count.
- [ ] Tapping a card navigates to detail.
- [ ] Pull‑to‑refresh reloads data.
```

---

## PAGE 10 — STAFF INCIDENT DETAIL + HITL ACTIONS

```
## Task
Create a functional Incident Detail screen for the Offsite Staff Team Leader with working HITL (Human‑in‑the‑Loop) actions: Accept, Correct, and Reject.

## Scope
- Staff role only. Shows full incident details + AI analysis + assignment info.
- Actions modify the incident status in App State and log feedback.

## Page Parameters
- `incidentId` (String, required)

## UI Layout
- Top: back arrow + Incident ID title + **overflow menu** (3 dots, top‑right).
- **Overflow menu** (PopupMenuButton or BottomSheet with actions):
  - “📋 Copy Incident ID” → copies to clipboard, shows snackbar “Copied!”.
  - “🔔 Mark as Urgent” → confirmation dialog, on confirm: Update App State: set severity = “High” for this incident.
  - “📞 Call Customer Support” → launches phone dialer with mock number.
- Scrollable content:
  - **“Complaint” card**: full text, submitted date, location.
  - **“AI Analysis” card** (blue left border): Category badge + confidence bar, Severity badge + explanation, Sentiment icon + score.
  - **“Assignment” card**: your name + department + assigned timestamp.
  - **“Your Decision” section** (amber background card):
    - 3 large action buttons:
      1. Green filled “✓ Accept & Start Work”
      2. Amber outlined “✎ Correct Classification”
      3. Red outlined “✗ Reject Assignment”
    - Tapping Correct → reveals dropdown selectors for Category and Severity + notes field + “Submit Correction” button.
    - Tapping Reject → reveals required reason text field + “Submit Rejection” button.

## State Variables (Page State)
1. `incident` (StaffAssignment struct, nullable)
2. `showCorrectionForm` (Boolean, default: false)
3. `showRejectionForm` (Boolean, default: false)
4. `correctedCategory` (String, nullable)
5. `correctedSeverity` (String, nullable)
6. `correctionNotes` (String, nullable)
7. `rejectionReason` (String, nullable)
8. `isSubmitting` (Boolean, default: false)

## On Page Load
- Find incident in `appState.mockStaffQueue` by `incidentId`, set to `pageState.incident`.

## Action Flow — Accept
1. Show confirmation dialog: “Accept Incident?” + “This will move the incident to In Progress.”.
2. On confirm:
   - **Update App State**: set incident status = “In Progress” in both `mockStaffQueue` and `mockComplaints`.
   - **Navigate Back** to Staff Queue.

## Action Flow — Correct
1. **Update Page State**: `showCorrectionForm` = `true`.
2. On “Submit Correction” tap:
   - Validate that `correctedCategory` and `correctedSeverity` are not null.
   - **Update App State**: update the incident’s category and severity in mock data. Set status = “In Progress”. Log correction to `mockFeedback` (new App State list).
   - **Update Page State**: `isSubmitting` = `false`, `showCorrectionForm` = `false`.
   - Show snackbar “Correction submitted. Incident updated.”
   - **Navigate Back**.

## Action Flow — Reject
1. **Update Page State**: `showRejectionForm` = `true`.
2. On “Submit Rejection” tap:
   - Validate that `rejectionReason` is not empty.
   - **Update App State**: set incident status = “Flagged for Review”. Log rejection to `mockFeedback`.
   - Show snackbar “Incident rejected and flagged for Engineer review.”
   - **Navigate Back**.

## Acceptance Criteria
- [ ] All three HITL actions (Accept, Correct, Reject) update App State and navigate back.
- [ ] Correct form requires category, severity, and notes before submission.
- [ ] Reject form requires a reason before submission.
- [ ] Overflow menu actions work: Copy ID, Mark as Urgent, Call Support.
- [ ] Confirmation dialogs shown before destructive actions.
```

---

## PAGE 11 — STAFF AVAILABILITY

```
## Task
Create a functional Availability management page for Offsite Staff.

## Scope
- Staff role only. Toggle current availability status and set planned schedule.

## UI Layout
- Top: “My Availability” title centred. Back arrow.
- **Current Status card** (large, rounded‑16):
  - Large status icon (green/amber/gray circle).
  - Bold status label (e.g., “Available”).
  - **Segmented Button** or row of 4 toggle chips: `Available`, `On Duty`, `Unavailable`, `On Break`.
    - Only one selectable at a time.
    - Tapping a chip updates both the card display and App State.
  - “Last updated: [timestamp]” small gray below.
- **“Schedule” section title**:
  - List of next 7 days, each with a Switch toggle “Planned Available”.
  - Tapping a switch updates App State (mock).
- Bottom nav: Queue, Resolved, Availability (active), Profile.

## State Variables (Page State)
1. `currentStatus` (String, non‑nullable, default: “Available”)
2. `lastUpdated` (String, nullable, default: current timestamp)

## Action Flow — Status Chip Tap
1. **Update Page State**: `currentStatus` = chip label, `lastUpdated` = now.
2. **Update App State**: store availability status (for Engineer web portal consumption).

## Acceptance Criteria
- [ ] Status chips are mutually exclusive and update the display instantly.
- [ ] Current status is visually prominent with appropriate colour.
- [ ] Schedule switches toggle and persist to App State.
- [ ] “Last updated” timestamp refreshes on each change.
```

---

## PAGE 12 — STAFF RESOLUTION UPDATE

```
## Task
Create a functional Resolution Update page for Offsite Staff to mark an incident as resolved.

## Scope
- Staff role only. Accessed from Incident Detail after accepting an incident.
- Allows uploading resolution notes and an optional photo.

## Page Parameters
- `incidentId` (String, required)

## UI Layout
- Top: back arrow + “Resolve Incident #INC‑XXX” title.
- White card:
  - `resolutionNotesField`: multi‑line TextField (minLines: 4), label “Resolution Notes”.
  - `photoUploadButton`: outlined button “📷 Attach Photo (Optional)”.
    - On tap: use FlutterFlow’s **Upload Media** action (or mock with placeholder image).
  - If photo attached, show preview thumbnail (100×100, rounded‑8).
- `resolveButton`: Green filled full‑width button “✓ Mark as Resolved”.

## State Variables (Page State)
1. `resolutionNotes` (String, nullable, default: empty)
2. `photoUrl` (String, nullable, default: null)
3. `isSubmitting` (Boolean, default: false)

## Action Flow — Resolve Button
1. **Update Page State**: `isSubmitting` = `true`.
2. **Update App State**: find incident in `mockStaffQueue` and `mockComplaints` by ID, set:
   - `status` = “Resolved”
   - `resolutionNotes` = `pageState.resolutionNotes`
   - `resolvedAt` = current timestamp
3. **Update App State**: move incident from `mockStaffQueue` to `mockResolvedIncidents`.
4. **Update App State**: add a notification to `mockNotifications` of type “Resolved”.
5. **Update Page State**: `isSubmitting` = `false`.
6. **Show Snackbar**: “Incident resolved successfully!”
7. **Navigate Back**: to Staff Queue.

## Acceptance Criteria
- [ ] Resolution notes are captured and stored.
- [ ] Photo upload works (or mock preview shown).
- [ ] Resolve button updates App State across all relevant lists.
- [ ] Notification is generated for the customer.
- [ ] On success, navigates back with snackbar.
```

---

## PAGE 13 — STAFF RESOLVED INCIDENTS PAGE

```
## Task
Create a functional Resolved Incidents page for Offsite Staff.

## Scope
- Staff role only. Displays all incidents resolved by this staff member.
- Date range filters and search.

## UI Layout
- Top: “Resolved” title + search icon. No back arrow — this is a bottom nav tab.
- **Date range filter**: `Today` | `This Week` | `This Month` | `All Time` (horizontal chips, single‑select).
- **Summary card** (light green bg, rounded‑12):
  - “Total Resolved: [n]” bold left.
  - “Avg. Resolution Time: [n] hrs” gray right.
- **Resolved incidents list**: ListView of cards, each showing:
  - Row 1: Incident ID monospace + green “Resolved” chip.
  - Row 2: Complaint text preview (2 lines, gray italic).
  - Row 3: Category badge + Severity badge.
  - Row 4: “Resolved [date]” + resolution time chip (e.g., “2h 30m”).
- Tap card → Navigate to read‑only version of Incident Detail.
- Bottom nav: Queue, Resolved (active), Availability, Profile.

## State Variables (Page State)
1. `resolvedList` (List of StaffAssignment, nullable)
2. `filteredResolved` (List of StaffAssignment, nullable)
3. `activeDateFilter` (String, default: “All Time”)
4. `totalResolved` (Integer, default: 0)
5. `avgResolutionHours` (Double, default: 0.0)

## On Page Load
- Populate `resolvedList` from `appState.mockResolvedIncidents`.
- Apply date filter logic: parse `resolvedAt`, compare with filter range.
- Calculate `totalResolved` and `avgResolutionHours`.

## Filter Logic (Action Block)
- If “Today”: filter where resolvedAt is today.
- If “This Week”: filter where resolvedAt is within last 7 days.
- If “This Month”: filter where resolvedAt is within last 30 days.
- Else: show all.

## Empty State
Green checkmark illustration + “No resolved incidents yet”.

## Acceptance Criteria
- [ ] Date filters work and update the list.
- [ ] Summary card shows accurate stats.
- [ ] Tapping a card navigates to read‑only detail.
- [ ] Pull‑to‑refresh reloads from App State.
```

---

## PAGE 14 — STAFF PROFILE PAGE

```
## Task
Create a Staff Profile page in FlutterFlow with stats and functional logout.

## Scope
- Staff / Team Leader role only. Accessed via bottom nav “Profile” tab.

## UI Layout
- Top: “Profile” title centred. No back arrow.
- Profile header card:
  - Large circular avatar (dark blue bg, white person icon).
  - Full name bold 18sp: “Juan Dela Cruz”.
  - Role badge: “Team Leader” blue filled chip.
  - Department: “Operations Dept.” gray.
  - Email: “staff@demo.com” gray.
  - Small blue outlined “Edit Profile” (placeholder).
- **Stats row** (3 small cards): “Assigned: [n]” blue, “Resolved: [n]” green, “Pending: [n]” amber.
- **“Account” section**:
  - Personal Information, Change Password, Notification Preferences (all placeholders).
- **“Work” section**:
  - My Schedule, My Performance, Availability History (all placeholders).
- **“Support” section**:
  - Help & FAQ, About IMRAWS‑NLP (placeholders).
- Divider.
- **“Log Out”** red outlined button.
- Bottom nav: Queue, Resolved, Availability, Profile (active).

## State Variables (Page State)
1. `assignedCount` (Integer, default: 0)
2. `resolvedCount` (Integer, default: 0)
3. `pendingCount` (Integer, default: 0)

## On Page Load
- Calculate counts from `appState.mockStaffQueue`, `mockResolvedIncidents`, etc.

## Action Flow — Log Out (same pattern as Customer Profile)
1. Confirmation dialog.
2. On confirm: clear App State (`isLoggedIn`, `currentUserRole`, etc.).
3. Navigate to Login (Replace Route: true).

## Acceptance Criteria
- [ ] Stats reflect actual mock data counts.
- [ ] Logout clears session and navigates to Login.
```

