# Capstone Document — Sync with Actual System (2026-09-08, updated 2026-09-09)

Source: `IMRAWS-NLP-CAPSTONE-DOCUMENT-MAINFOR-CHECKING-ONLY (1).docx`
Output: `IMRAWS-NLP-CAPSTONE-DOCUMENT-MAINFOR-CHECKING-ONLY (1) - UPDATED.docx`
Backup of original: `IMRAWS-NLP-CAPSTONE-DOCUMENT-MAINFOR-CHECKING-ONLY (1) - ORIGINAL BACKUP.docx`

47 paragraph-level text edits applied to `word/document.xml`. The embedded diagrams (ERD, DFDs,
architecture figure) are images and were NOT changed — redraw them using the corrected specs below.

> **Deployment section policy (2026-09-09):** the current system is under development and runs
> locally, but per the researchers' decision the document KEEPS the original production/cloud
> architecture narrative (Oracle Cloud VPS + Render, HTTPS, serverless, Android/iOS push
> notifications). Deployment wording was reverted to the original and is intentionally NOT a
> description of the current local setup.

## Edits applied

### Feature claims corrected to implemented behavior
- Admin "configures routing rules / department categories" -> views the predefined
  category->department routing mappings (hardcoded in AiRoutingService)
- Engineer "manages availability of Offsite Staff" -> views availability; staff self-manage
- HITL feedback "retrains the NLP model" -> logged to feedback table as the training-feedback
  source for periodic retraining (note: Laravel -> NLP retrain loop is NOT wired end-to-end yet;
  see Recommended system fixes below)
- PDF/printable report export (DFD 6.8) -> downloadable CSV export
- "Customer Satisfaction Indicators" + "team efficiency" claims -> replaced with what the
  dashboard actually reports (by_status, by_category, avg resolution time per department,
  misclassification/correction trends, availability status)

### Naming / status / definitions
- Categories normalized everywhere to canonical: Billing, Water Quality, Metering, Operations
- Customer status tracking list: (Open, Assigned, In Progress, Resolved)
- Severity input definition: sentiment polarity + days pending + urgency keywords
  (removed "incident category" — category is NOT a severity input)

### ERD narrative (matches actual schema)
- users: `password` (was password_hash), added `updated_by`, primary key `id` (was user_id)
- tbl_incidents: added `composite_score`, + tbl_incident_attachments (photo proof, DFD 5.6)
- tbl_assignments: added `action_status`
- tbl_availability: status values `(available, on_duty, unavailable, on_break)`
- tbl_feedback: added `composite_score`
- ERD intro: seven -> eight core tables (+ tbl_incident_attachments)
- audit logs: "tamper-evident" -> "complete"

### Doc-internal fixes (typos / numbering)
- SYSTEM ARHCITECHTURE -> SYSTEM ARCHITECTURE
- DFD 2.11 spelling error: stores incident in D1 (Users) -> D2 (Incidents)
- DFD 6.3 source: D1 (Incidents) -> D2 (Incidents)
- DFD 5.9 data store: D6 (Notification) -> D6 (Notifications) only (push wording restored)
- Chapter 3 figure refs: "as shown in Figure 1" -> Figure 10; "Figure 10 follows..." -> Figure 11;
  ERD caption Figure 12 -> Figure 13 (Use Case kept as Figure 12)
- Offsite Staff login platform: web portal -> mobile application
- Administrator platform: "web portal and mobile" -> web portal

## Corrected specs for redrawing the figures

### ERD tables (8) — field lists for the diagrams
- users: id, full_name, email, password, contact_no, address, role (customer/administrator/
  engineer/offsite_staff), department_team (billing/metering/water_quality/operations),
  is_active, is_team_leader, updated_by (FK->users), created_at, updated_at
- tbl_incidents: id, customer_id (FK->users), description, location, category,
  severity (High/Medium/Low), composite_score, status (open/assigned/in_progress/resolved/
  rejected), submitted_at, resolved_at, created_by, updated_by, created_at, updated_at
- tbl_assignments: id, incident_id (FK->tbl_incidents), team_leader_id (FK->users),
  engineer_review_id (FK->users), assigned_at, action_status (assigned/accept/reject/correct/
  override/reassign/in_progress/resolved/pending), resolution_notes, created_by, updated_by,
  created_at, updated_at
- tbl_notifications: id, incident_id (FK), user_id (FK), message, is_read, created_by,
  updated_by, created_at, updated_at
- tbl_availability: id, staff_id (FK->users, UNIQUE), status (available/on_duty/unavailable/
  on_break), created_by, updated_by, created_at, updated_at
- tbl_feedback: id, incident_id (FK), team_leader_id (FK), engineer_id (FK), original_category,
  original_severity, composite_score, action_taken (accept/reject/correct/override/reassign),
  corrected_category, corrected_severity, rejection_reason, final_decision,
  feedback_timestamp, review_timestamp, used_for_training, created_by, updated_by,
  created_at, updated_at
- tbl_audit_logs: id, user_id (FK), action, table_name, record_id, old_value, new_value,
  created_at
- tbl_incident_attachments: id, incident_id (FK), uploaded_by (FK), file_path, original_name,
  mime_type, file_size, caption, created_at, updated_at

### DFD data-store labels (consistent)
D1 Users, D2 Incidents, D3 Assignments, D4 Availability, D5 Feedback, D6 Notifications,
D7 Audit Logs (Incidents create/store in D2; Report Analytics reads D2, D3, D5, D7).

### Statuses used in figures
Incident: open -> assigned -> in_progress -> resolved / rejected. TD Leader actions: accept /
reject (with reason) / correct. Engineer adjudication: approve / override / reassign.

## Recommended system fixes (NOT part of this doc edit, per discussion)
- Wire `NlpService::submitFeedback()` (app/Services/NlpService.php:121) after engineer
  adjudication so tbl_feedback corrections actually reach the FastAPI retraining pipeline.
- Optional: engineer "manage availability" endpoint, admin routing-rules UI, PDF export,
  satisfaction indicators — currently documented as absent.

## Chapter 3 additions — `IMRAWS-NLP-CAPSTONE-9.9.26.docx` (2026-09-09)

Per the adviser template ("ADDITIONAL TO CHAPTER 3"), appended to the end of Chapter 3 in the
9.9.26 working export. Headings match the document's existing convention (e.g. "3.6Roles and
Responsibilities ", number + tab + title). Manual TOC updated.
Backup: `IMRAWS-NLP-CAPSTONE-9.9.26 - ORIGINAL BACKUP.docx`.

- 3.6 Roles and Responsibilities
  - Intro + 10 functional roles with duties across the Agile SDLC: Project Manager; Systems
    Analyst; Database Designer; Backend Developer (Laravel); Mobile Developer (Flutter); Web
    Portal Developer; AI and NLP Developer (Python/FastAPI); Quality Assurance Tester;
    Statistical Analyst; Capstone Adviser.
- 3.7 Evaluation Procedure
  - Intro + 7-step protocol: prototype/evaluation-material preparation (ISO/IEC 25010 Project
    Evaluation Tool); orientation of the three (3) College of Computer Studies faculty
    panelists; end-to-end demonstration/walkthrough; hands-on evaluation; questionnaire
    administration (5-point Likert); weighted-mean scoring (WM formula in 3.5) mapped to the
    interpretation table; pass criterion overall weighted mean >= 3.41 ("Agree").
- 3.8 Ethical Standards and Considerations
  - Sample introduction paragraph (verbatim from the adviser's template) + 6 standards:
    informed consent/voluntary participation; privacy and confidentiality (RBAC-limited);
    Data Privacy Act of 2012 (RA 10173) compliance; non-maleficence; integrity of findings;
    acknowledgement of sources.
- TOC: 3 manual `TOCHeading` rows appended after "Likert Scale74" —
  3.6 (p. 77), 3.7 (p. 78), 3.8 (p. 80). Page numbers are ESTIMATES — verify/update in Word.