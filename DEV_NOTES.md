# DEV_NOTES.md — Vessel Management System (VMS)

## System Overview
VMS is a PHP/MySQL web application for managing vessels, inspections (ICRs), equipment, crew, documents, and compliance workflows for commercial maritime operations.

Production environment:
- Server: Ubuntu 22.04 (DigitalOcean)
- Web root: /var/www/html/vessel_management_system
- DB: MySQL 8.0
- PHP: PDO-based
- PDF: TCPDF

---

# Application Architecture

## Entry Points (Primary Pages)

| Page | Purpose |
|------|--------|
dashboard.php | System dashboard / overview  
vessel_dashboard.php | Main vessel detail interface  
manage_vessels.php | Vessel list + management  
view_vessels.php | Vessel listing (legacy/alt view)  
tasks.php | Task management  
reports.php | Reporting interface  

---

# Vessel Dashboard Structure

vessel_dashboard.php loads tab content from /partials/:

| Tab | Partial |
|-----|--------|
Crew | partials/crew_tab.php  
Documents | partials/documents_tab.php  
Equipment | partials/equipment_tab.php  
ICRs | partials/icrs_tab.php  
ICR Upcoming | partials/icrs_upcoming.php  
ICR History | partials/icrs_history.php  
Drills | partials/drills_tab.php  
Tasks | partials/tasks_tab.php  

---

# Core Modules

## Vessels
Files:
- add_vessel.php
- edit_vessel.php
- update_vessel.php
- vessel_dashboard.php
- vessel_manage_action.php
- archive_vessel.php
- restore_vessel.php

Tables:
- vessels
- vessel_users
- vessel_owners
- linked_vessels
- vessel_audit_log

---

## Crew
Files:
- crew_members.php
- add_crew_member.php
- edit_crew_member.php
- assign_crew.php
- remove_crew.php

Tables:
- crew_members
- vessel_crew
- crew_drills

---

## Documents
Files:
- documents.php
- add_document.php
- upload_document.php
- archive_document.php
- view_document.php

Tables:
- documents
- vessel_documents
- media_attachments

---

## Equipment
Files:
- equipment_detail.php
- edit_equipment.php
- update_equipment.php

Tables:
- equipment
- equipment_category
- equipment_type
- equipment_subtype
- equipment_photos

---

# ICR System (Inspection & Drill Framework)

## Templates
Files:
- add_icr.php
- edit_icr.php
- icr_templates.php

Tables:
- icrs
- icr_steps
- icr_substeps
- icr_drill_templates

---

## Vessel Assignment
Files:
- add_vessel_icr.php
- edit_vessel_icr.php
- submit_vessel_icr.php
- remove_vessel_icr.php

Tables:
- vessel_icrs

Lifecycle:
- assigned → active  
- removed (is_removed=1) → hidden but preserved  

---

## Execution / Runs
Files:
- run_icr.php
- submit_icr_run.php
- view_icr_run.php
- print_icr_run.php

Tables:
- vessel_icr_runs
- vessel_icr_steps
- vessel_icr_substeps
- vessel_icr_step_status
- vessel_icr_substep_status
- icr_run_attachments

---

# Tasks

Files:
- add_task.php
- edit_task.php
- submit_task.php
- create_task_from_icr.php

Tables:
- tasks
- task_attachments

---

# Notifications

Files:
- config_notify.php
- scripts/notify_digest.php
- scripts/notify_docs_expiring.php

Tables:
- notification_rules
- notification_prefs
- notification_log
- email_sends

---

# Authentication & Permissions

Files:
- login.php
- logout.php
- session_check.php
- authenticate.php
- lib/acl.php

Tables:
- users
- roles
- login_attempts
- password_resets

Session keys:
- $_SESSION['user_id']
- $_SESSION['username']

---

# Logging

Files:
- log_create.php
- log_update.php
- log_view.php
- logs_list.php

Tables:
- vessel_logs
- vessel_log_crew
- vessel_log_media

---

# PDF System

Library:
- tcpdf/

Common:
- pdf_common.php

Outputs:
- vessel_profile_pdf.php
- print_icr.php
- print_icr_run.php
- print_documents.php
- print_equipment.php
- print_tasks.php

---

# Database

Connection:
db_connect.php

dbname: vessel_management_system  
user: vms_user  

Snapshot:
snapshots/2026-02-19/_snapshot_schema.sql

---

# Conventions

## Assignment soft-delete
Tables using archive pattern:
- vessel_icrs
- documents
- vessels

Fields:
- is_removed or is_archived
- removed_at
- removed_by

Rule:
Never hard-delete if dependent records exist.

---

## Partials
Location:
partials/

Loaded inside dashboards via include/AJAX.

---

## Submit Handlers
Pattern:
submit_*.php

Examples:
- submit_vessel_icr.php
- submit_icr_run.php
- submit_task.php

---

## Update Handlers
Pattern:
update_*.php

---

# Backup Tables
Prefix:
_bkp_*

Used for:
- migrations
- recovery

Do not use in active queries.

---

# Snapshots

Location:
snapshots/YYYY-MM-DD/

Contains:
- file manifest
- sha256 hashes
- schema dump

---

# Dev Workflow

When modifying schema:

1. ALTER TABLE
2. regenerate _snapshot_schema.sql
3. move to snapshots folder
4. update DEV_NOTES if module changes

---

# Known Risks / Notes

- update_vessel_icr_steps.php is empty
- duplicate files without extension exist
- backup tables present in prod DB

---

# Maintainer

Sean Keeman  
Marine Safety Consulting & Surveying (MSCS Hawaii)  
https://vms.mschawaii.org  

