# FlutterFlow Page Prompts — IMRAWS-NLP Mobile App
# Water Services Incident Management System

---

## STEP 1 — THEME SETUP (Manual — NOT a page)
*(Do this in FlutterFlow → Theme Settings BEFORE generating any pages)*

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

## PAGE PROMPT 2 — LOGIN SCREEN

```
Create a Flutter login screen for IMRAWS-NLP water services app.
Layout:
- White background with subtle blue gradient at top
- Centered water droplet icon (blue) + "IMRAWS-NLP" bold title
- Subtitle: "Water Services Incident Management"
- Email text field with email icon
- Password text field with lock icon + show/hide toggle
- "Sign In" filled blue full-width button with loading state
- Below button: small gray text "Forgot password?"
- Error message text in red shown below button on failure
- Bottom of screen: app version "v1.0.0" in gray
No registration link. Credentials provided by Administrator only.
Use Inter font. Clean minimal design. White card centered on screen.
```

---

## PAGE PROMPT 3 — CUSTOMER HOME / DASHBOARD

```
Create a Flutter home dashboard screen for a Customer role.
Top: greeting header "Hello, [Name]!" + blue notification bell icon (top right) with red badge count.
Below header: 2x2 stats grid cards (white, rounded, shadow):
- Card 1: "My Complaints" - total count, blue icon
- Card 2: "Open" - count, orange pulsing dot
- Card 3: "In Progress" - count, amber icon
- Card 4: "Resolved" - count, green icon
Middle: "Recent Complaints" section title + "See All" link.
List of 3 recent complaint cards showing: truncated complaint text, category colored badge (Billing=violet, Water Quality=blue, Metering=orange, Operations=teal), severity badge (High=red, Medium=amber, Low=green), status chip, submitted date.
Bottom: floating blue "+" button to submit new complaint.
Bottom nav bar: Home (active), My Complaints, Profile.
```

---

## PAGE PROMPT 4 — CUSTOMER SUBMIT COMPLAINT

```
Create a Flutter submit complaint screen for water services customer app.
Top: back arrow + title "Submit Complaint" centered.
Form layout (scrollable):
- Section label "Describe your complaint"
- Large multiline text area (min 5 lines) placeholder: "Please describe your water service issue in detail..."
- Character counter bottom right of text area
- Section label "Location (optional)"
- Text input with location pin icon placeholder "e.g. 123 Rizal St., Barangay..."
- Section label "Attach Photo (optional)"
- Dashed border upload box with camera icon + "Tap to attach photo" text, shows thumbnail preview after selection
- Info box (light blue background): "Our AI will automatically classify and prioritize your complaint."
Bottom: full-width blue "Submit Complaint" button with loading spinner on press.
Show success bottom sheet on submit: checkmark animation + "Complaint submitted!" + reference number + "Track Status" button.
```

---

## PAGE PROMPT 5 — CUSTOMER MY COMPLAINTS LIST

```
Create a Flutter complaints list screen for customer role.
Top: back arrow + "My Complaints" title + filter icon (top right).
Filter chips row (horizontally scrollable): All, Open, In Progress, Resolved — active chip filled blue, inactive outlined.
Search bar below chips: "Search complaints..." with search icon.
Scrollable list of complaint cards (white, rounded-12, shadow-sm) each showing:
- Row 1: Complaint ID (#INC-001 monospace) + Status chip (right aligned): Open=blue, In Progress=amber, Resolved=green
- Row 2: Complaint text preview (2 lines, ellipsis overflow, gray)
- Row 3: Category badge + Severity badge side by side
- Row 4: "Submitted [date]" small gray text + chevron right icon
Tap card → navigate to Complaint Detail screen.
Empty state: blue water drop illustration + "No complaints yet" + "Submit your first complaint" button.
```

---

## PAGE PROMPT 6 — CUSTOMER COMPLAINT STATUS DETAIL

```
Create a Flutter complaint detail screen for customer.
Top: back arrow + "#INC-001" as title + share icon.
Status timeline (vertical stepper, top to bottom):
- Step 1: "Submitted" - green checkmark, date/time
- Step 2: "AI Processing" - green checkmark, "Classified as [Category]"
- Step 3: "Assigned" - green or gray, "Assigned to [Team Leader name]"
- Step 4: "In Progress" - active pulsing amber dot or gray
- Step 5: "Resolved" - green checkmark or gray, resolution date
White card: full complaint text in italic blockquote style.
White card "AI Analysis": Category badge + confidence %, Severity badge, Sentiment label.
White card "Resolution" (visible when resolved): resolution notes text + photo thumbnail if available + resolved date.
Blue outlined "Contact Support" button at bottom.
Pull-to-refresh gesture supported.
```

---

## PAGE PROMPT 7 — OFFSITE STAFF INCIDENT QUEUE

```
Create a Flutter incident queue screen for Offsite Staff / Team Leader role.
Top header: "Incident Queue" title + availability toggle switch (right): green "Available" / red "Unavailable" pill.
Summary row: 3 small stat chips: "Assigned [n]", "In Progress [n]", "Resolved Today [n]".
Scrollable list of incident cards (white, rounded-12, shadow) each showing:
- Row 1: Incident ID (monospace) + Severity badge (High=red with pulse, Medium=amber, Low=green) right aligned
- Row 2: Complaint text preview 2 lines gray italic
- Row 3: Category colored badge + Days Pending chip (red if >=3 days, amber if >=1)
- Row 4: "Assigned [time ago]" small gray text + right arrow
Sort: High severity first, then by days pending descending.
Empty state: checkmark illustration + "No incidents assigned" message.
Bottom nav: Queue (active), Resolved, Availability, Profile.
Pull to refresh. Tap card → Incident Detail screen.
```

---

## PAGE PROMPT 8 — OFFSITE STAFF INCIDENT DETAIL + HITL ACTIONS

```
Create a Flutter incident detail screen for Offsite Staff Team Leader.
Top: back arrow + Incident ID title + overflow menu (3 dots).
Scrollable content:
White card "Complaint": full complaint text in styled blockquote, submitted date, customer location.
White card "AI Analysis" (blue left border accent):
- Category: colored badge + confidence percentage bar
- Severity: large colored badge + explanation text
- Sentiment: icon + label + score
White card "Assignment": your name + department + assigned timestamp.
Section "Your Decision" (amber background card):
- 3 large action buttons stacked:
  1. Green filled: "✓ Accept & Start Work"
  2. Amber outlined: "✎ Correct Classification"
  3. Red outlined: "✗ Reject Assignment"
Tapping Correct → shows dropdown selectors for Category and Severity + notes field.
Tapping Reject → shows required reason text field.
Both show "Submit Decision" blue button.
Show confirmation dialog before submitting any decision.
```

---

## PAGE PROMPT 9 — OFFSITE STAFF AVAILABILITY SCREEN

```
Create a Flutter availability management screen for Offsite Staff.
Top: back arrow + "My Availability" title.
Center card (large, white, rounded-16, shadow-md):
- Large status icon top center (green circle checkmark or red circle X)
- Large status text: "Available" or "Unavailable" bold 24sp
- Subtitle: "Last updated: [time]"
- Large toggle switch below (green/red)
Status options section below card — 4 tappable option tiles in 2x2 grid:
- "Available" (green) — ready for assignments
- "On Duty" (blue) — currently working on incident
- "On Break" (amber) — temporarily unavailable
- "Unavailable" (red) — off duty
Active option tile has filled colored background, others outlined.
Info card below: "Your availability status is visible to Engineers for scheduling and incident assignment."
"Update Status" blue full-width button at bottom.
Show success snackbar on update: "Availability updated successfully".
```

---

## PAGE PROMPT 10 — OFFSITE STAFF RESOLUTION UPDATE

```
Create a Flutter resolution update screen for Offsite Staff.
Top: back arrow + "Update Resolution" title + Incident ID subtitle.
Incident summary card (light blue bg): complaint preview + category badge + severity badge.
Form (scrollable):
Status selector — 2 large toggle cards side by side:
- "In Progress" (amber border when selected) + wrench icon
- "Resolved" (green border when selected) + checkmark icon
Resolution Notes: multiline text field 4 lines, placeholder "Describe work performed and resolution...", character counter.
Photo Evidence section: "Attach proof of completion (optional)" label. Row of 3 dashed tap-to-add photo boxes, shows thumbnails with X remove button.
Estimated Completion: date+time picker row, visible only when In Progress selected.
Full-width blue "Submit Update" button, disabled until status selected and notes filled.
On submit: success bottom sheet with green checkmark + "Status updated!" + "View Incident" button.
```

---

## CHARACTER COUNTS (all under 1000 ✅)
- Page Prompt 2 — Login: 681
- Page Prompt 3 — Customer Home: 820
- Page Prompt 4 — Submit Complaint: 938
- Page Prompt 5 — My Complaints List: 850
- Page Prompt 6 — Complaint Detail: 828
- Page Prompt 7 — Staff Incident Queue: 911
- Page Prompt 8 — Incident Detail + HITL: 962
- Page Prompt 9 — Availability: 912
- Page Prompt 10 — Resolution Update: 956