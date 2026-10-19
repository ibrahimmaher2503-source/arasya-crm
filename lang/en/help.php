<?php

declare(strict_types=1);

$notApplicable = 'Not applicable to this page.';
$makePage = static function (string $title, string $summary, string $purpose, array $information, array $filters = [], array $actions = [], array $forms = [], array $transitions = [], array $errors = [], array $conditions = [], string $nextStep = '') use ($notApplicable): array {
    return [
        'title' => $title,
        'summary' => $summary,
        'purpose' => $purpose,
        'information' => $information,
        'filters' => $filters === [] ? [$notApplicable] : $filters,
        'actions' => $actions,
        'forms' => $forms === [] ? [$notApplicable] : $forms,
        'transitions' => $transitions === [] ? [$notApplicable] : $transitions,
        'errors' => $errors === [] ? ['Validation errors appear beside fields; record access and scope remain server-enforced.'] : $errors,
        'conditions' => $conditions === [] ? ['The server rechecks access and record scope on every request.'] : $conditions,
        'next_step' => $nextStep === '' ? $notApplicable : $nextStep,
    ];
};

return [
    'ui' => [
        'title' => 'Help Center',
        'subtitle' => 'Guidance for screens and permissions that currently exist.',
        'search' => 'Search help',
        'search_placeholder' => 'Enter a word or topic',
        'search_button' => 'Search',
        'clear_search' => 'Clear search',
        'language' => 'Language',
        'arabic' => 'العربية',
        'english' => 'English',
        'pages' => 'Screens',
        'workflows' => 'Workflows',
        'articles' => 'Guides',
        'faqs' => 'FAQs',
        'glossary' => 'Glossary',
        'tours' => 'Guided tours',
        'pending_tours' => 'Tours waiting for page anchors',
        'open_page' => 'Open screen',
        'read_guide' => 'Read guide',
        'available' => 'Available',
        'pending' => 'Pending',
        'testing_only' => 'Testing only',
        'development_only' => 'Development only',
        'technical' => 'Technical screen',
        'no_results' => 'No matching results are available for your permissions.',
        'not_applicable' => 'Not applicable to this page.',
        'no_actions' => 'No additional action guidance is available for your current permissions.',
        'restricted_content' => 'These details are shown only when the required permission is available.',
        'labels' => [
            'purpose' => 'Purpose',
            'information' => 'What the screen shows',
            'filters' => 'Search and filters',
            'actions' => 'Available actions',
            'forms' => 'Forms and fields',
            'transitions' => 'States and transitions',
            'errors' => 'Expected errors',
            'conditions' => 'Access scope and limits',
            'next_step' => 'Next step',
            'sections' => 'Details',
        ],
        'tour_start' => 'Start tour',
        'tour_resume' => 'Resume tour',
        'tour_restart' => 'Restart tour',
        'tour_previous' => 'Previous',
        'tour_next' => 'Next',
        'tour_finish' => 'Finish tour',
        'tour_skip' => 'Skip step',
        'tour_pause' => 'Pause',
        'tour_close' => 'Close tour',
        'tour_status' => 'Tour status',
        'tour_loading' => 'Loading status…',
        'status_not_started' => 'Not started',
        'status_in_progress' => 'In progress',
        'status_paused' => 'Paused',
        'status_completed' => 'Completed',
        'status_skipped' => 'Needs review',
        'tour_done' => 'Tour finished. No system action was performed.',
        'tour_missing_anchors' => 'Some tour targets were not found. The tour was not marked complete.',
        'tour_save_failed' => 'Tour progress could not be saved. You can continue, but progress is not saved now.',
        'tour_status_failed' => 'Tour status could not be loaded. You can start a new tour.',
        'tour_step_count' => 'Step :current of :total',
    ],
    'availability' => [
        'pending' => 'This workflow is not currently registered as an active route; no screen or contextual coverage is confirmed.',
        'gated' => 'This screen is visible only when its specified testing gate is open. It is not a production feature.',
    ],
    'errors' => [
        'unauthenticated' => 'A valid sign-in is required to access tour progress.',
        'forbidden' => 'The permission required for this content or progress is no longer available.',
        'not_found' => 'This content or tour is not available.',
        'stale_version' => 'The tour version changed. Reload it and resume from the current state.',
        'invalid_progress' => 'Progress data is invalid or contains unsupported fields.',
        'progress_unavailable' => 'Tour progress cannot be read or saved right now.',
    ],
    'pages' => [
        'dashboard-work-center' => $makePage('Dashboard and work center', 'A summary and navigation to available modules.', 'An entry point for sample summaries and modules permitted by the current access grants.', ['Some dashboard metrics are presentation samples, not approved financial or operational figures.', 'Module links follow the account’s effective permissions.'], [], [
            'open-employees' => 'Open the employee directory when employees.read is available.',
            'open-tasks' => 'Open the task list when tasks.read is available.',
            'open-crm' => 'Open CRM when crm.read is available.',
        ], [], [], ['This screen has no input form or state transition.'], [], 'Choose an authorized module from the main navigation.'),
        'access-overview' => $makePage('Access overview', 'A summary of available access-management sections.', 'Navigate to organization, users, roles, and audit pages according to current grants.', ['Only sections the account may read are shown.'], [], [
            'open-organization' => 'Open organization settings with organization.read.',
            'open-users' => 'Open users with users.read.',
            'open-roles' => 'Open roles with roles.read.',
            'open-audit' => 'Open the audit log with audit.read.',
        ], [], [], [], [], 'Open the section you are authorized to review.'),
        'access-organization' => $makePage('Organization, branches, and departments', 'Manage the current company structure.', 'Review the company, branches, departments, and default department-role assignments.', ['Descriptive values, active states, and scoped counts may be shown.', 'Editing department role assignments also requires roles.write.'], ['Name, status, branch, department, role, and separate searches for branch, department, and role options.'], [
            'edit' => 'Editing company, branch, or department data requires organization.write.',
        ], ['Name, code, and status fields; default department-role assignments require roles.write and a matching version.'], [], ['Duplicate codes, invalid input, or stale versions prevent saving.'], ['Branches and departments are limited to the current company; the server enforces scope on save.'], 'Review organization permissions before saving changes.'),
        'access-users' => $makePage('Users list', 'Find permitted sign-in accounts.', 'Locate an account and review its state and organizational scope.', ['The list shows access-related fields; it does not grant access.', 'Role assignment requires a separate permission.'], ['Name or email search, status, branch, department, role, and bounded page sizes.'], [
            'create' => 'Creating a user requires users.write.',
            'edit' => 'Editing user details requires users.write.',
            'assign' => 'Assigning roles or permissions requires users.write and roles.write.',
        ], ['Account, organization, role, and permission controls depend on current effective access.'], [], ['Duplicate email, an out-of-scope role, or a stale version prevents saving.'], ['Users cannot edit their own access; editors cannot grant permissions they do not hold.'], 'Open an authorized user record to review details.'),
        'access-user-detail' => $makePage('User details', 'Review one account within the visible scope.', 'Understand the account state, organization, and effective assignments.', ['Account details and role assignments are shown only when authorized.'], [], [
            'edit' => 'Editing the account requires users.write.',
            'assign' => 'Changing roles or permissions requires users.write and roles.write.',
        ], ['The edit form requires write access and uses the account version to prevent stale updates.'], [], ['Invisible users appear not found; self-assignment and out-of-scope changes are denied.'], ['Effective access is read again on each request; a role label alone is not authority.'], 'Return to the user list when review is complete.'),
        'access-roles' => $makePage('Roles list', 'Browse company roles.', 'Find a role and understand its attached permission names.', ['The list contains roles in the current company and permitted scope.'], ['Text search and bounded page sizes when there are many roles.'], [
            'create' => 'Creating a role requires roles.write.',
            'edit' => 'Editing a role requires roles.write.',
        ], [], [], ['Duplicate names or unknown permission values are rejected.'], ['Displayed role names and permissions do not grant access by themselves.'], 'Open a role to review its permission list.'),
        'access-role-detail' => $makePage('Role details', 'Review the permissions attached to one role.', 'Read the role name and the application’s recognized permission set.', ['Only permission names defined by the application are displayed.'], [], [
            'edit' => 'Editing the role requires roles.write.',
        ], ['Edit controls require write access; changing a role can affect every assigned user.'], [], ['Stale versions and unknown permission names prevent saving.'], ['This page cannot create new permissions or elevate the editor.'], 'Review the role’s impact before editing it.'),
        'access-audit' => $makePage('Audit log', 'Read recorded access and change events.', 'Trace available audit events without modifying them.', ['Results may show actor, action, subject, time, and safe metadata.'], ['Date, actor, action, and record filters with bounded pagination.'], [], [], [], ['Invalid dates or out-of-scope filters do not reveal more records.'], ['Requires audit.read; secrets and protected contact values are not displayed.'], 'Use date and actor filters to narrow the results.'),
        'employees-directory' => $makePage('Employee directory', 'Search employee records within your scope.', 'Review the employee file, status, branch, department, and manager.', ['Private fields remain hidden without employee.private.', 'Counts are limited to the authorized scope.'], ['Search by name, code, or role; branch, department, manager, status, sort, and page size 10, 25, or 50.'], [
            'create' => 'Creating an employee file requires employees.write.',
            'private-fields' => 'Viewing private fields requires employee.private.',
        ], [], [], ['Out-of-scope records appear not found; invalid input or versions prevent saving.'], ['The server limits results to the user’s company and permitted branches.'], 'Open an authorized employee file or refine the directory filters.'),
        'employees-workers' => $makePage('Workers list', 'Browse worker profiles within the supervisor scope.', 'List worker files without login accounts in the current supervisory scope.', ['Results may be limited to direct reports.', 'employees.manage_all broadens permitted scope but never crosses company boundaries.'], ['Directory filters: name or code, branch, department, manager, status, and sort.'], [
            'create-worker' => 'Creating a worker requires employees.write.',
            'manage-all-workers' => 'Managing all workers requires employees.manage_all.',
        ], [], [], ['Profiles outside supervisory scope or linked to a login account may not appear.'], ['The server determines the effective supervisor scope; client-supplied manager IDs do not grant access.'], 'Review supervisor scope before creating a worker profile.'),
        'employee-create' => $makePage('Create employee file', 'Form for a new employee profile.', 'Add an employee file after validating branch, department, and manager scope.', ['Available fields depend on the account scope and employee.private for sensitive values.'], [], [
            'save' => 'Saving requires employees.write.',
            'manage-all-fields' => 'Selecting a broader management scope requires employees.manage_all.',
        ], ['Name, code, role, state, branch, department, and manager; some choices are limited by the current user scope.'], [], ['Missing required fields, duplicate codes, or out-of-scope references prevent saving.'], ['This form does not connect to the unregistered /employees/work route.'], 'After saving, open the employee file from the directory.'),
        'employee-edit' => $makePage('Edit employee file', 'Update an existing employee profile.', 'Change permitted file details without exceeding organizational scope.', ['Available profile values are shown; private fields may be hidden.'], [], [
            'save' => 'Saving changes requires employees.write.',
            'manage-all-fields' => 'Broader field scope requires employees.manage_all.',
        ], ['The current profile version prevents overwriting a newer change.'], [], ['Out-of-scope profiles, stale versions, or unsupported fields are rejected.'], ['The server rechecks file permission and scope on every request.'], 'Reload the profile if a version conflict appears.'),
        'employee-profile' => $makePage('Employee profile', 'Profile summary and related tabs.', 'Review profile data, workers, evidence, and audit according to current permissions.', ['Tabs include overview, workers, evidence, and audit.', 'Task lookup in the evidence tab requires tasks.read.', 'Private fields require employee.private.'], [], [
            'edit' => 'Editing the profile requires employees.write.',
            'record-evidence' => 'Recording work evidence requires employees.evidence.write.',
            'view-private-fields' => 'Viewing private fields requires employee.private.',
            'look-up-task' => 'Looking up a task requires tasks.read.',
        ], [], [], ['Records may be unavailable because of scope or a missing reference.'], ['Audit data does not grant permission to edit the profile.'], 'Choose an available tab to continue the review.'),
        'employee-work' => $makePage('Employee work area', 'A possible workflow that is not yet registered.', 'This entry is pending because no active route exists in routes/employees.php.', ['A view or controller file alone does not prove a reachable screen or stable route.'], [], [], [], [], ['No registered screen is available to open.'], ['No contextual coverage or readiness claim is made for this route.'], 'Use the registered employee directory and workflows for now.'),
        'tasks-list' => $makePage('Tasks list', 'Review your tasks, states, and due dates.', 'Find a task and review summaries and related records.', ['Summary values include total, due today, overdue, completed this week, today/upcoming lists, and recent evidence.'], ['Title search, status, priority, due date range, today/overdue/upcoming presets, sort, and page size.'], [
            'create' => 'Creating a task requires tasks.write.',
        ], [], [], ['A task is shown only within its owner/assignee scope.'], ['Invalid filters are rejected; searching does not reveal another user’s records.'], 'Open an authorized task from the results.'),
        'task-create' => $makePage('Create task', 'Create a task for yourself or an eligible colleague.', 'Create a task with a title, priority, and due date.', ['The server generates an idempotency key to prevent duplicate submission.'], [], [
            'save' => 'Saving requires tasks.write.',
        ], ['Title up to 200 characters, low/normal/high priority, due date and time; assigning another eligible user requires tasks.assign.'], [], ['Title, priority, and due date are required; another assignee requires tasks.assign and must be eligible in the same scope.'], ['The assignee picker appears with tasks.assign; tasks.write alone assigns the task to you.'], 'Open the new task from the task list.'),
        'task-detail' => $makePage('Task details', 'Read task fields, history, and evidence.', 'Review task data, state, due date, and related evidence.', ['The screen shows state, priority, owner, assignee, version, and audit history. Reassigning an open task requires tasks.write and tasks.assign.'], [], [
            'start' => 'Starting a pending task requires tasks.write.',
            'attach-evidence' => 'Attaching evidence requires tasks.write.',
            'complete' => 'Completing a task requires tasks.write.',
        ], ['PDF, PNG, JPEG, or TXT evidence up to 5 MB requires tasks.write.'], ['A task starts pending; work may be started and then completed when evidence requirements are met.'], ['Stale versions, unsupported files, or out-of-scope tasks are rejected.'], ['Transition and form guidance is shown only to accounts with tasks.write.'], 'Review the task history after an authorized change.'),
        'crm-overview' => $makePage('CRM overview', 'A summary of leads and follow-ups.', 'Review lead totals, new leads, overdue follow-ups, and conversion share.', ['Metrics use CRM records visible to the current account.', 'The transfer inbox summary is limited to authorized users and is not a standalone screen.'], [], [
            'open-transfers' => 'Viewing the transfer summary requires crm.write.',
        ], [], [], ['Records outside the company or branch scope may not appear.'], ['The summary does not reveal protected contact details.'], 'Open the leads list to review individual records.'),
        'crm-leads-list' => $makePage('CRM leads list', 'Search customer-lead records.', 'Find a lead and review its state, source, stage, and priority.', ['Only non-protected fields appear in the list; contacts stay hidden until separately authorized and audited.'], ['Search, status, source, stage, priority, archive state, assignee, and page size.'], [
            'create' => 'Opening lead creation requires crm.manage.',
            'edit' => 'Opening lead edit requires crm.manage.',
        ], [], [], ['Invalid filters or out-of-scope records do not return additional data.'], ['crm.read and company/branch scope are reapplied on every request.'], 'Open an authorized lead record.'),
        'crm-lead-create' => $makePage('Create CRM lead', 'Add a new lead record.', 'Record the lead details, consent, and communication preferences.', ['Optional contact values are protected; the tour never exposes or fills them.'], [], [
            'save' => 'Saving requires crm.manage and crm.write.',
        ], ['Name, organization, and source are required; stage is optional; consent and email/phone/WhatsApp preferences are recorded where allowed.'], [], ['Missing fields, invalid stage, or an out-of-scope reference prevents saving.'], ['Do not enter real customer data through a tutorial.'], 'Open the lead details after saving.'),
        'crm-lead-edit' => $makePage('Edit CRM lead', 'Update an existing lead record.', 'Change permitted lead details while preserving consent and record scope.', ['Contact values may remain masked; reveal requires a separate permission and audited reason.'], [], [
            'save' => 'Saving requires crm.manage and crm.write.',
        ], ['The current record version is checked; stage options follow the permitted catalog.'], [], ['Archived leads, stale versions, or out-of-scope values prevent saving.'], ['CRM permission and record scope are checked on read and write.'], 'Reload the lead after a version conflict.'),
        'crm-lead-detail' => $makePage('CRM lead details', 'Lead summary, activity, and protected contacts.', 'Review source, stage, assignment, notes, consent, and recorded history.', ['Email, phone, and WhatsApp values are hidden by default.', 'Transfer, conversion, and assignment are separate permission-gated actions.'], [], [
            'edit' => 'Editing requires crm.manage.',
            'activity' => 'Recording activity requires crm.write.',
            'assign' => 'Assignment requires crm.manage and crm.assign.',
            'transfer' => 'Requesting a transfer requires crm.transfer.',
            'convert' => 'Converting a lead requires crm.convert.',
            'reveal-contact' => 'Revealing contacts requires contact.reveal and an audited reason.',
        ], [], [], ['Archived or out-of-scope leads and stale versions may block an action.'], ['The tour does not reveal contacts or perform transfers, conversions, or edits.'], 'Review the authorized activity or file tabs.'),
        'crm-activities' => $makePage('CRM lead activities', 'Review calls, messages, meetings, and other activity.', 'Follow activity history, outcomes, and next steps.', ['Rows show kind, outcome, author, date, and next step.'], ['Text search and activity kind.'], [
            'record-activity' => 'Adding activity requires crm.write and an active lead.',
        ], ['The add form is shown only with crm.write for a non-archived lead.'], [], ['Invisible leads or invalid fields are rejected.'], ['Activity details remain limited to the authorized lead scope.'], 'Review the next step before recording an update.'),
        'crm-notes' => $makePage('CRM lead notes', 'Read notes attached to one lead.', 'Open a selected note and review its text, time, and author.', ['Selection is limited to the currently authorized results.'], ['Text search across available records.'], [
            'record-note' => 'Adding a note requires crm.write and a non-archived lead.',
        ], ['The note form appears only to authorized writers.'], [], ['An out-of-scope lead remains unavailable even when its ID is known.'], ['Notes are private work content and are not sent to a customer or tutorial.'], 'Return to the lead summary after review.'),
        'crm-follow-ups' => $makePage('CRM follow-ups', 'Review next steps associated with a lead.', 'View follow-up dates and linked tasks in the lead workspace.', ['There is no separate follow-up inbox page; follow-ups appear within CRM record workspaces.'], ['Text search; activity kind on activity/follow-up pages.'], [
            'record-follow-up' => 'Recording a follow-up requires crm.write and tasks.write; task scope also applies.',
        ], ['The add form depends on write permissions and lead state.'], [], ['The service may reject an invalid date or out-of-scope record.'], ['The tour never creates a task or follow-up automatically.'], 'Open an authorized CRM lead and review its follow-up tab.'),
        'crm-files' => $makePage('CRM lead files', 'Browse attachments associated with a CRM lead.', 'Search files and download an authorized attachment or upload one when permitted.', ['Rows show file name, description, category, size, and upload date.'], ['File search, category, and paginated results.'], [
            'upload' => 'Uploading requires crm.write and a non-archived lead.',
            'download' => 'Downloading requires crm.read.',
        ], ['PDF, images, text, DOCX, or XLSX up to 50 MB; optional description and category. Form details require crm.write.'], [], ['Unsupported type, size, or stale lead version is rejected; the tour never opens files.'], ['Files are private and scoped to the CRM record and current access.'], 'Download only when needed for authorized work.'),
        'crm-pipeline' => $makePage('Synthetic sales pipeline', 'Synthetic opportunity board for testing only.', 'Group sample opportunities by stage; it is not approval or commercial sending.', ['Stages include prospect, contact, proposal, negotiation, internal review, and closed.', 'Displayed amounts and dates are synthetic.'], ['Search, currency, stage, minimum probability, and expected-close date range.'], [
            'save-opportunity' => 'Saving a synthetic opportunity requires crm.write.',
        ], ['Title, amount, currency, stage, probability, and expected-close date; form details require write access.'], ['Selecting a stage changes synthetic data only; internal review is not approval.'], ['Archived leads, stale versions, or invalid amounts prevent saving.'], ['Available only in testing with sales.synthetic_drafts=true; not a production feature.'], 'Use the real leads list for operational work; do not send these samples.'),
        'quotation-list' => $makePage('Synthetic quotation drafts', 'Non-binding drafts behind the testing gate.', 'Review synthetic drafts, totals, and print previews.', ['The displayed state is draft; approval and sending are disabled.'], ['Text search and pagination.'], [
            'create-draft' => 'Creating a draft requires crm.write.',
        ], ['Create-form details are shown only to authorized writers.'], [], ['Archived or invisible leads may prevent access.'], ['Available only in testing with sales.synthetic_drafts=true; never send these drafts to a customer.'], 'Use print preview for testing only.'),
        'quotation-create' => $makePage('Create synthetic quotation draft', 'Create a non-binding testing draft.', 'Enter sample lines and review displayed totals without financial approval.', ['Enter the tax rate explicitly; approved rounding rules are not applied.'], [], [
            'save-draft' => 'Saving requires crm.write.',
        ], ['Title and three-letter currency code; each line has description, quantity, unit price, discount, and tax, up to 50 lines.'], [], ['Negative values, invalid currency, or archived lead prevent saving.'], ['Requires testing and sales.synthetic_drafts=true; no sending or approval is available.'], 'Review the draft in the quotations list.'),
        'quotation-edit' => $makePage('Edit synthetic quotation draft', 'Update a non-binding draft.', 'Edit its title and lines using the current draft version.', ['Displayed totals are calculated without approved financial rounding.'], [], [
            'save-draft' => 'Saving requires crm.write.',
        ], ['The draft version is required; lines include description, quantity, price, discount, and tax.'], [], ['Stale versions, archived leads, or invalid values prevent saving.'], ['Requires testing and sales.synthetic_drafts=true; no sending or approval is available.'], 'Reload the draft after a version conflict.'),
        'quotation-preview' => $makePage('Synthetic quotation preview', 'Print or PDF preview for an unapproved draft.', 'Review sample lines and totals before local printing.', ['The preview states that it is not approved and must not be sent to a customer.', 'Use the browser print command to save a local PDF.'], [], [], [], [], ['Values are synthetic; production pricing and rounding rules are not applied.'], ['Requires testing and sales.synthetic_drafts=true.'], 'Return to the draft; do not treat it as a real quotation.'),
        'vertical-cycle-records' => $makePage('Technical cycle records', 'A technical route separate from customer and employee screens.', 'Classify the current operations-cycle surface as technical, not a customer workflow.', ['Access relies on the application’s persisted identity; contextual tour coverage is not confirmed.'], [], [], [], [], ['This is not a customer list or work queue.'], ['Do not enter customer or confidential business data in this technical surface.'], 'Return to the registered work screen relevant to your task.'),
        'design-system-showcase' => $makePage('Design system showcase', 'Component examples backed by synthetic data.', 'Inspect presentation components, direction, and theme without reading business data.', ['Screen and row examples are synthetic; forms and attachments do not save.'], ['The sample orders screen has search, status, sort, and page controls.'], [], ['Forms and files are examples only and do not submit a business operation.'], [], ['The route returns 404 outside local and testing.'], ['Never treat sample values as real metrics or records.'], 'Use the actual authorized screen for operational work.'),
    ],
    'workflows' => [
        'create-employee' => ['title' => 'Create employee', 'summary' => 'Open the form from the directory, enter permitted fields, and save with employees.write.'],
        'employee-work-evidence' => ['title' => 'Record employee work evidence', 'summary' => 'Use the evidence tab on an authorized profile; employees.evidence.write and valid scope are required.'],
        'create-task' => ['title' => 'Create a task', 'summary' => 'Enter a title, priority, and due date; tasks.write permits self-assignment; tasks.assign also permits selecting an eligible colleague.'],
        'complete-task' => ['title' => 'Complete a task', 'summary' => 'Review the task, start it when needed, attach supported evidence if required, then complete it with tasks.write.'],
        'create-lead' => ['title' => 'Create CRM lead', 'summary' => 'Open the CRM form, record name, organization, source, and consent, then save with the required permissions. Contact values are optional and protected.'],
        'record-follow-up' => ['title' => 'Record CRM follow-up', 'summary' => 'Open an authorized lead and its follow-up tab; review the next date before adding. crm.write and tasks.write are required.'],
        'convert-lead' => ['title' => 'Convert a lead', 'summary' => 'The conversion action appears on lead details with crm.convert; conversion is recorded without a separate customer list screen.'],
        'manage-access' => ['title' => 'Review user access', 'summary' => 'Open a user, inspect effective roles and permissions, and never grant a permission you do not hold. users.read, users.write, and roles.write are required.'],
        'synthetic-quotation' => ['title' => 'Synthetic quotation draft', 'summary' => 'Available only in testing with sales.synthetic_drafts=true; it creates a non-binding draft with no approval or sending.'],
    ],
    'articles' => [
        'transfer-inbox' => ['title' => 'CRM transfer inbox', 'summary' => 'The current transfer inbox is a JSON API route, not a standalone HTML screen.', 'sections' => [['title' => 'Where to work', 'text' => 'Review a transfer from the CRM summary or lead details, and use only the decisions visible to an authorized user. Current read/write permissions apply. Do not call APIs manually from a tour.']]],
        'lead-conversion' => ['title' => 'Lead conversion', 'summary' => 'Understand the effect without exposing a record or executing the operation.', 'sections' => [['title' => 'What changes', 'text' => 'Conversion is an explicit action on lead details requiring crm.convert; it records customer_id on the lead. No standalone customer list route exists in the current routes.']]],
        'contact-privacy' => ['title' => 'Contact privacy', 'summary' => 'Contact details are protected and hidden by default.', 'sections' => [['title' => 'Audited reveal', 'text' => 'Revealing email, phone, or WhatsApp requires contact.reveal and an audited reason. A tour never reads, saves, or reveals contact values.']]],
        'record-scope' => ['title' => 'Record scope', 'summary' => 'Session identity, permission, and company scope are rechecked by the server.', 'sections' => [['title' => 'What to expect', 'text' => 'The application may treat an out-of-scope record as not found. Knowing or seeing an identifier does not grant access.']]],
    ],
    'faqs' => [
        'transfer-inbox' => ['question' => 'Is the transfer inbox a separate screen?', 'answer' => 'No. The registered route is a JSON API; authorized users work through the CRM summary or lead details.'],
        'customer-screen' => ['question' => 'Where is the customer screen after conversion?', 'answer' => 'There is no standalone customer list screen in the current routes. Lead conversion requires crm.convert.'],
        'permissions' => ['question' => 'Does showing an action in Help grant permission?', 'answer' => 'No. Help describes known effective permissions, and the server checks them again on every request.'],
        'tutorial-safety' => ['question' => 'Does a tour fill or submit anything?', 'answer' => 'No. A tour displays text and a spotlight only; it never clicks, fills, submits, or reveals business data.'],
    ],
    'glossary' => [
        'effective-permission' => ['term' => 'Effective permission', 'definition' => 'The current result of roles and allowed overrides after explicit denies are applied.'],
        'lead' => ['term' => 'Lead', 'definition' => 'A CRM record before conversion, not a record in a standalone customer list screen.'],
        'follow-up' => ['term' => 'Follow-up', 'definition' => 'A next step associated with a CRM record or task; its scope remains permission-controlled.'],
        'synthetic' => ['term' => 'Synthetic data', 'definition' => 'Testing values that do not represent real business records or metrics.'],
        'tour-anchor' => ['term' => 'Tour anchor', 'definition' => 'A stable marker owned by a screen; a missing target is skipped and never marks the tour complete.'],
        'version' => ['term' => 'Tour version', 'definition' => 'A stable content version; changing it resets older progress to the default state.'],
    ],
    'tours' => [
        'help-center' => ['title' => 'Explore the Help Center', 'steps' => [
            'welcome' => ['title' => 'Help Center', 'body' => 'Find guidance for registered screens and workflows; content you cannot access is omitted.'],
            'search' => ['title' => 'Permission-filtered search', 'body' => 'Search only the content available to you. Search does not call business systems.'],
            'collections' => ['title' => 'Guides and workflows', 'body' => 'Open an article or workflow. Screen links are fixed and their access is checked again when opened.'],
            'tour-controls' => ['title' => 'Tour status', 'body' => 'Pause, resume, or restart. A missing target is never recorded as a completed tour.'],
        ]],
        'dashboard' => ['title' => 'Dashboard tour', 'steps' => [
            'welcome' => ['title' => 'Welcome to your dashboard', 'body' => 'An overview of the work modules available to you. The tour shows no real data and performs no action.'],
            'summary' => ['title' => 'Quick indicators', 'body' => 'These cards are illustrative; actual values are resolved inside each module by your permissions.'],
            'records' => ['title' => 'Follow-up data', 'body' => 'The tables below are display samples. The tour never fills a field or sends a request.'],
        ]],
        'access-overview' => ['title' => 'Access overview tour', 'steps' => [
            'sections' => ['title' => 'Access sections', 'body' => 'Only sections you may read appear: organization, users, roles, and the audit log.'],
        ]],
        'access-users' => ['title' => 'Users tour', 'steps' => [
            'filters' => ['title' => 'Search and filters', 'body' => 'Search by name or email and filter by status, branch, and department. Search never widens your allowed scope.'],
            'results' => ['title' => 'User list', 'body' => 'Accounts in your scope are shown. Open a user to review their assigned role and effective permissions.'],
        ]],
        'access-organization' => ['title' => 'Organization tour', 'steps' => [
            'filters' => ['title' => 'Filter branches and departments', 'body' => 'Use the search fields to filter branches and departments within the current company.'],
            'results' => ['title' => 'Branches and departments', 'body' => 'The structure is shown within your scope. Editing requires organization.write and is not performed by the tour.'],
        ]],
        'access-roles' => ['title' => 'Roles tour', 'steps' => [
            'filters' => ['title' => 'Role editor', 'body' => 'Create a role or edit its permission set. Saving requires roles.write and the tour grants no permission.'],
            'results' => ['title' => 'Role list', 'body' => 'Company roles are listed. Open a role to review its approved permissions.'],
        ]],
        'access-audit' => ['title' => 'Audit log tour', 'steps' => [
            'filters' => ['title' => 'Filter the audit log', 'body' => 'Filter events by date, actor, and action to reach what you need to review.'],
            'results' => ['title' => 'Audit events', 'body' => 'The log is read-only; the tour never modifies an event.'],
        ]],
        'employees-directory' => ['title' => 'Employee directory tour', 'steps' => [
            'filters' => ['title' => 'Search employees', 'body' => 'Search by name, code, or role and filter by branch, department, and status.'],
            'results' => ['title' => 'Employee list', 'body' => 'Open an authorized profile. Private fields appear only with employee.private.'],
        ]],
        'employees-workers' => ['title' => 'Workers tour', 'steps' => [
            'filters' => ['title' => 'Filter workers', 'body' => 'This directory lists workers within your effective supervision scope.'],
            'results' => ['title' => 'Worker list', 'body' => 'Profiles outside your supervisor scope may be hidden. The tour never creates or modifies a worker profile.'],
        ]],
        'crm-overview' => ['title' => 'CRM overview tour', 'steps' => [
            'nav' => ['title' => 'CRM navigation', 'body' => 'Use this bar to move between the CRM overview, leads, and other authorized pages.'],
            'summary' => ['title' => 'Leads summary', 'body' => 'Cards show lead and follow-up counts within your scope. The tour exposes no contact data.'],
        ]],
        'crm-leads-list' => ['title' => 'CRM leads tour', 'steps' => [
            'filters' => ['title' => 'Search leads', 'body' => 'Filter by status, source, stage, priority, and owner. Search never widens your scope.'],
            'results' => ['title' => 'Lead list', 'body' => 'Only unmasked data is shown. Open an authorized record to review its details.'],
        ]],
        'tasks-list' => ['title' => 'Tasks tour, waiting for page anchors'],
        'crm-pipeline' => ['title' => 'Synthetic pipeline tour, waiting for anchors'],
        'quotation-list' => ['title' => 'Synthetic drafts tour, waiting for anchors'],
    ],
];
