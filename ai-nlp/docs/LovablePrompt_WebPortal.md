# Lovable Prompt — IMRAWS-NLP Web Portal

---

## PASTE THIS INTO LOVABLE:

---

Build a modern, professional web portal called **IMRAWS-NLP** (Incident Management and Routing App and Web System for Water Services). This is the web dashboard for two user roles: **Engineer** and **Administrator**. Use a clean, modern design with a dark navy/slate sidebar, white content area, and a blue-teal accent color scheme. Use shadcn/ui components throughout. The app should feel like a premium SaaS operations dashboard — think Linear, Vercel dashboard, or Supabase UI.

Use **React + TypeScript + Tailwind CSS + shadcn/ui**. Use **React Router** for navigation. Use **mock data** for all data — no real backend calls. All state should be managed in React (useState/useContext). Make everything fully interactive with mock data responses.

---

## DESIGN SYSTEM

- **Primary color:** Blue-teal `#0EA5E9` (sky-500)
- **Sidebar:** Dark slate `#0F172A` (slate-900) with white text
- **Background:** `#F8FAFC` (slate-50)
- **Cards:** White with subtle `shadow-sm` and `rounded-xl`
- **Severity colors:**
  - High → `red-500` with light red background badge
  - Medium → `amber-500` with light amber background badge
  - Low → `green-500` with light green background badge
- **Category colors:**
  - Billing → `violet-500`
  - Water Quality → `blue-500`
  - Metering → `orange-500`
  - Operations → `teal-500`
- **Sentiment colors:**
  - NEGATIVE → `red-500`
  - NEUTRAL → `slate-400`
  - POSITIVE → `green-500`
- **Font:** Inter (Google Fonts)
- **Border radius:** `rounded-xl` for cards, `rounded-lg` for buttons/inputs
- All tables should use shadcn/ui `Table` component with hover row highlight
- All modals/dialogs use shadcn/ui `Dialog`
- All dropdowns use shadcn/ui `Select`
- All form inputs use shadcn/ui `Input` and `Textarea`
- All notifications use shadcn/ui `Toast`
- Use `lucide-react` icons throughout

---

## APP STRUCTURE

### Layout
- **Persistent left sidebar** (collapsible on mobile) with:
  - App logo: a water droplet icon + "IMRAWS" in bold white + "NLP" in sky-400
  - Navigation grouped by role
  - Current user avatar + name + role badge at the bottom
  - Logout button at the very bottom
- **Top header bar** with:
  - Page title (dynamic based on current route)
  - A notification bell icon with a red badge count
  - A "NLP Service: Online" status pill (green dot + text) — this reflects the health check
  - Current user's name

### Role switching (for demo purposes)
Add a small role switcher in the sidebar header area: a dropdown that lets you switch between "Engineer View" and "Administrator View". When switched, the sidebar nav items change accordingly and unauthorized pages redirect.

---

## PAGES AND FEATURES

---

### PAGE 1: LOGIN PAGE (`/login`)

Full-page centered login card with:
- Water droplet logo + "IMRAWS-NLP" title
- Subtitle: "Water Services Incident Management"
- Email input field
- Password input field
- "Sign In" button (primary, full width)
- A role selector below: two toggle buttons "Engineer" | "Administrator" — selecting one pre-fills demo credentials
- Demo credentials shown below as small helper text:
  - Engineer: `engineer@imraws.com` / `password`
  - Admin: `admin@imraws.com` / `password`
- On successful login, redirect to `/dashboard`
- Show a loading spinner on the button during "login"
- Background: subtle water-themed gradient (deep blue to slate)

---

### PAGE 2: DASHBOARD (`/dashboard`) — Both Roles

A summary overview page. Show the following:

#### Stats Row (4 KPI cards in a grid):
1. **Total Incidents** — number + small sparkline trend line
2. **Open / Pending** — count with a pulsing orange dot indicator
3. **Resolved Today** — count with green checkmark icon
4. **High Severity Active** — count with red flame icon + "Needs Attention" label if > 0

#### Incident Category Breakdown (Bar Chart):
- Horizontal bar chart showing count per category: Billing, Water Quality, Metering, Operations
- Use recharts `BarChart`
- Each bar colored by category color from design system

#### Severity Distribution (Donut Chart):
- Donut/pie chart showing High / Medium / Low split
- Use recharts `PieChart`
- Legend below the chart

#### Recent Incidents Table (last 5):
- Columns: Incident ID, Complaint Preview (truncated 60 chars), Category badge, Severity badge, Status, Submitted At
- "View All" button that navigates to `/incidents`

#### NLP Processing Queue Card:
- Shows: Queue Size, Results Cached, Service Status
- A "View Health" link

---

### PAGE 3: INCIDENT QUEUE (`/incidents`) — Engineer + Admin

Full incident management table page.

#### Filter Bar (above table):
- Search input: "Search complaints..." (searches complaint text)
- Severity filter dropdown: All / High / Medium / Low
- Category filter dropdown: All / Billing / Water Quality / Metering / Operations
- Status filter dropdown: All / Open / In Progress / Resolved / Flagged
- Date range picker (two date inputs: From / To)
- "Clear Filters" button
- Results count: "Showing X of Y incidents"

#### Incidents Table:
Columns:
1. **Incident ID** — `#INC-001` format, monospace font, clickable
2. **Complaint** — first 80 characters of complaint_text, italic gray, with a tooltip showing full text on hover
3. **Category** — colored badge pill (Billing/Water Quality/Metering/Operations)
4. **Sentiment** — icon + label (😠 NEGATIVE in red, 😐 NEUTRAL in gray, 🙂 POSITIVE in green) + score as small percentage
5. **Severity** — badge with dot indicator (🔴 High / 🟡 Medium / 🟢 Low)
6. **Days Pending** — number, colored red if >= 3, amber if >= 1
7. **Status** — badge: Open (blue), In Progress (amber), Resolved (green), Flagged (red outline)
8. **Assigned To** — Team Leader name or "Unassigned" in gray italic
9. **Actions** — three-dot dropdown menu with: View Details, Flag for Review, Reassign

Pagination at the bottom: Previous / Next + page numbers + "Rows per page" selector (10/25/50).

#### Incident Detail Drawer/Modal (opens on row click or "View Details"):
A right-side slide-over panel (Sheet component) showing:
- Full complaint text in a styled blockquote box
- NLP Analysis Results section:
  - Category badge + confidence score as a progress bar (e.g., "Water Quality — 92.3%")
  - Sentiment badge + score bar
  - Severity badge (large, prominent)
  - Days Pending
  - Processed At timestamp
- Assignment section:
  - Currently assigned Team Leader (name + department)
  - "Reassign" button → opens a small dropdown of available team leaders
- HITL Actions section (Engineer only):
  - "Accept NLP Decision" button (green, outlined)
  - "Correct Category" dropdown (Billing / Water Quality / Metering / Operations)
  - "Correct Severity" dropdown (High / Medium / Low)
  - "Rejection Reason" textarea
  - "Submit Correction" button (triggers mock feedback submission + success toast)
- Timeline/Activity Log at the bottom:
  - List of timestamped events: "Submitted", "NLP Processed", "Assigned to [name]", "Engineer reviewed", etc.

---

### PAGE 4: FLAGGED INCIDENTS (`/flagged`) — Engineer only

Same layout as incidents table but pre-filtered to show only incidents with status "Flagged" (Team Leader rejected or corrected).

#### Page header:
- Title: "Flagged for Review"
- Subtitle: "Incidents rejected or corrected by Team Leaders — awaiting Engineer adjudication"
- Count badge: "X pending review"

#### Table adds two extra columns:
- **Team Leader Action** — "Rejected" (red) or "Corrected" (amber) badge
- **Rejection Reason** — truncated text, tooltip on hover

#### Adjudication Panel (opens on row click):
Same as the Incident Detail drawer but with a prominent **"Adjudication Required"** banner at the top (red/amber alert box) showing:
- Team Leader's action (Rejected / Corrected)
- Their reason/note
- Original NLP decision vs. Team Leader's proposed correction (side-by-side comparison cards)

Then the Engineer's actions:
- "Approve Team Leader Correction" button (green)
- "Override — Keep Original NLP Decision" button (amber)  
- "Reassign to Different Team Leader" button (blue outline) → opens team leader selector
- "Engineer Notes" textarea
- "Submit Final Decision" button → mock API call + success toast + removes from flagged list

---

### PAGE 5: PERFORMANCE DASHBOARD (`/performance`) — Both Roles

Analytics and reporting page.

#### Top filter bar:
- Date range selector: Last 7 days / Last 30 days / Last 90 days / Custom
- Export button: "Export Report" → shows a toast "Report exported as CSV"

#### Row 1 — Resolution Metrics (3 cards):
1. **Average Resolution Time** — "2.4 days" with trend arrow
2. **Resolution Rate** — "87%" as a large percentage with a thin circular progress ring
3. **Misclassification Rate** — "6.2%" with trend arrow (lower is better, colored green if < 10%)

#### Row 2 — Charts (2 columns):
Left: **Incidents Over Time** — Line chart (recharts LineChart) showing daily incident count for the selected period, with separate lines for each category, togglable via legend.

Right: **Resolution Time by Category** — Grouped bar chart showing average days to resolve per category (Billing, Water Quality, Metering, Operations).

#### Row 3 — Team Performance Table:
Table with columns: Team Leader Name, Department, Assigned Count, Resolved Count, Avg Resolution Time, Correction Rate (%), Last Active.
- Sortable columns (click header to sort)
- Color code "Correction Rate": green < 5%, amber 5-15%, red > 15%

#### Row 4 — NLP Model Performance (2 cards):
Left card: **Classifier Accuracy** — large "87.4%" number, subtitle "Last retrained: 3 days ago", a "Retrain Now" button.
Right card: **Feedback Summary** — total feedback count, category corrections count, severity corrections count, "Ready for retrain" badge (green) or "X more corrections needed" (gray).

#### Top Misclassification Patterns Table:
- Columns: Original Category, Corrected To, Count, % of Total
- Small arrow icon showing the correction direction

---

### PAGE 6: FEEDBACK LOG (`/feedback`) — Both Roles

Table of all HITL feedback entries.

#### Filter bar:
- Search input
- Filter by Engineer dropdown
- Filter by Category dropdown
- "Used for Training" toggle filter: All / Used / Pending

#### Table columns:
1. Timestamp
2. Complaint Preview (truncated, tooltip)
3. Original Category badge
4. Corrected Category badge (with arrow → between them, highlighted amber if different)
5. Original Severity badge
6. Corrected Severity badge (highlighted if different)
7. Engineer ID
8. Final Decision (truncated)
9. Used for Training — green "✓ Used" badge or gray "Pending" badge

#### Summary cards above table:
- Total Feedback Entries
- Category Corrections
- Severity Corrections  
- Pending (not yet used for training)

---

### PAGE 7: NLP MODEL MANAGEMENT (`/model`) — Both Roles

#### Section 1: Model Status Card
- Large status indicator: "Model Loaded ✓" (green) or "Model Not Loaded ✗" (red)
- Current model info table: Model Type (SVM + TF-IDF), Last Trained date, Accuracy, Total Training Samples, Feedback Samples Used
- Two action buttons side by side:
  - "Trigger Retraining" (primary blue) — opens a confirmation dialog first, then shows a loading state "Retraining in progress..." with a progress bar animation, then a success card showing the retrain result (accuracy, samples used)
  - "View Training Log" (outlined) — opens a modal with a mock log output in a dark monospace terminal-style box

#### Section 2: Severity Configuration Card
Title: "Severity Scoring Configuration"
Subtitle: "Adjust thresholds and urgency keywords. Changes apply immediately without restart."

Form fields:
- **High Severity — Negative Sentiment Threshold**: Slider (0.0–1.0, step 0.05) + number display. Default: 0.80
- **Medium Severity — Negative Sentiment Threshold**: Slider (0.0–1.0, step 0.05) + number display. Default: 0.50
- **High Severity — Days Pending Threshold**: Number input (stepper). Default: 3
- **Medium Severity — Days Pending Threshold**: Number input (stepper). Default: 1

**Urgency Keywords Manager:**
- Display current keywords as removable tags/chips (X button on each)
- "Add Keyword" input + "Add" button
- Keywords grouped into two visual sections: "Tagalog / Filipino" and "English / Taglish" (visual label only)
- Show keyword count: "35 keywords configured"
- "Save Configuration" button (full-width, primary)
- "Reset to Defaults" button (outlined, red/destructive)
- Show a success toast on save: "Severity configuration updated successfully"

#### Section 3: NLP Service Health Card
Title: "Service Health"
Shows:
- Status: Online/Offline pill
- Queue Size: number
- Results Cached: number
- Uptime: mock "3d 14h 22m"
- Last health check timestamp
- "Refresh" icon button that re-fetches (mock)
- A simple 24-hour uptime bar (green blocks for each hour, red for any downtime)

---

### PAGE 8: USER MANAGEMENT (`/users`) — Administrator ONLY

Full user management table.

#### Page header with "Create User" button (opens a modal).

#### Stats row (3 cards):
- Total Users
- Active Users
- By Role breakdown (mini horizontal bar)

#### Users Table:
Columns:
1. Avatar (generated initials avatar, colored by role)
2. Full Name
3. Email
4. Role badge — Customer (blue), Engineer (violet), Offsite Staff (teal), Administrator (red)
5. Department/Team
6. Is Team Leader — checkmark or dash
7. Status — Active (green) / Inactive (gray)
8. Created At
9. Actions — Edit (pencil icon), Deactivate/Activate toggle, Delete (trash, red, with confirmation dialog)

#### Create/Edit User Modal (Dialog):
Fields:
- Full Name (input)
- Email (input)
- Password (input, only on create)
- Role (Select: Customer / Engineer / Offsite Staff / Administrator)
- Department/Team (input, shown only for Offsite Staff and Engineer)
- Is Team Leader (checkbox, shown only for Offsite Staff)
- Is Active (toggle switch)
- Cancel button + Save button

---

### PAGE 9: SYSTEM CONFIGURATION (`/settings`) — Administrator ONLY

Tabbed settings page with 3 tabs:

**Tab 1: General**
- System Name input (pre-filled "IMRAWS-NLP")
- Version display (read-only)
- NLP Service URL input
- Laravel API URL input
- "Save General Settings" button

**Tab 2: Routing Rules**
- A table of routing rules:
  - Columns: Category, Assigned Department, Team Leader Count, Active
  - Rows: Billing → Finance Dept, Water Quality → Technical Dept, Metering → Metering Dept, Operations → Operations Dept
  - Edit button per row (opens inline edit or modal)
- "Add Routing Rule" button

**Tab 3: Audit Log**
- A read-only table of system events:
  - Columns: Timestamp, User, Action, Table Affected, Record ID, Details
  - Mock data: login events, config changes, user creations, retrain triggers
  - No edit/delete — read only
  - Export as CSV button

---

## MOCK DATA REQUIREMENTS

Generate realistic mock data for everything:

**10-15 mock incidents** with varied:
- Complaint texts mixing Filipino (Tagalog/Taglish) and English. Examples:
  - "Walang tubig sa amin since kahapon, grabe na! Emergency na ito."
  - "Ang taas ng bill ko ngayong buwan kahit hindi naman namin nagamit ng ganon."
  - "May amoy ang tubig namin, parang yung kalawang o lupa."
  - "Hindi gumagalaw ang metro namin since last month na."
  - "Busted pipe sa may kanto ng Rizal St., baha na ang kalye."
  - "No water supply for 3 days already. Please send repair team ASAP."
  - "My bill has errors, double charge for October."
- Mix of categories, severities, sentiments, and statuses
- Confidence scores between 0.65–0.98
- days_pending between 0–7
- Various assigned team leaders

**5 mock users** (Engineers + Team Leaders + Admin)

**15 mock feedback entries** with corrections

**Mock retrain result:** accuracy 0.874, 320 samples, 20 feedback samples

---

## SIDEBAR NAVIGATION

### Engineer Sidebar:
- 📊 Dashboard (`/dashboard`)
- 🗂️ Incident Queue (`/incidents`) — with badge showing open count
- 🚩 Flagged for Review (`/flagged`) — with badge showing pending count
- 📈 Performance (`/performance`)
- 💬 Feedback Log (`/feedback`)
- 🤖 NLP Model (`/model`)

### Administrator Sidebar:
- 📊 Dashboard (`/dashboard`)
- 🗂️ Incident Queue (`/incidents`)
- 🚩 Flagged for Review (`/flagged`)
- 📈 Performance (`/performance`)
- 💬 Feedback Log (`/feedback`)
- 🤖 NLP Model (`/model`)
- 👥 User Management (`/users`)
- ⚙️ System Settings (`/settings`)

---

## INTERACTIONS AND UX DETAILS

- All table rows are clickable (opens detail panel/drawer)
- All badges have tooltips explaining what they mean
- All action buttons show a loading spinner while "processing"
- Success actions show a green toast notification (bottom right)
- Error states show a red toast
- Empty states: show a friendly illustration placeholder + message (e.g., "No flagged incidents — the NLP model is performing well!")
- Confirmation dialogs before destructive actions (deactivate user, delete, reset config)
- Smooth page transitions
- Responsive layout: sidebar collapses to a hamburger on smaller screens
- The Incident Detail side panel slides in from the right with a smooth animation
- Severity badges have a subtle pulse animation for "High" severity items
- The "NLP Service: Online" pill in the header should have a breathing green dot animation

---

## IMPORTANT NOTES FOR LOVABLE

- Do NOT connect to any real backend. All data is mock/hardcoded in React state or a mock data file.
- Keep all mock data in a single `src/data/mockData.ts` file.
- Use proper TypeScript interfaces for all data types matching these schemas:
  - `Incident`: id, complaint_text, category, category_confidence, sentiment, sentiment_score, severity, days_pending, status, assigned_to, processed_at, created_at
  - `FeedbackEntry`: id, timestamp, complaint_text, original_category, corrected_category, original_severity, corrected_severity, final_decision, engineer_id, used_for_training
  - `User`: id, full_name, email, role, department_team, is_team_leader, is_active, created_at
  - `RoutingRule`: category, department, team_leader_count, active
- Use React Context for auth state (current user + role)
- Use React Router v6 with protected routes (redirect to `/login` if not authenticated)
- The role switcher in the sidebar should update the auth context and re-render the nav

Start with the Login page and Dashboard, then build out all other pages.