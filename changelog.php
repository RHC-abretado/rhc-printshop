<?php require_once 'header.php'; ?>

<div class="container py-5">
  <h1>Change Log</h1>
  
  <!-- Summary Section -->
  <section id="summary" class="mb-4">
    <h2>Summary</h2>
    <p>
      This Printshop Ticket System has evolved from a basic prototype into a modular application with robust security measures,
      role-based access controls, ticket assignment functionality, and enhanced login/account management.
      This change log tracks the key improvements made during development by Albert Bretado.
    </p>
    <p>
        Special thank you to Maria Galvan and Mike Garabedian for testing and providing feedback/recommendations to improve this system.
    </p>
  </section>
  
  <!-- Versions Section -->
    <section id="changelog">
      <h2>Versions</h2>

<div class="card mb-3">
  <div class="card-header">
    Version 2.2 – Expanded Uploads, Revenue Insights &amp; Request Tracking (8/15/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Expanded Upload Capacity:</strong> Raised limits to five files totaling 100&nbsp;MB and added PowerPoint (<code>.ppt/.pptx</code>) and Publisher (<code>.pub</code>) support.</li>
      <li><strong>Accurate Cost Estimates:</strong> Refined pricing calculations for more reliable project quotes.</li>
      <li><strong>Self-Service Request Tracking:</strong> Introduced token-secured <em>Check Status</em> and <em>My Requests</em> pages so requesters can review ticket details and history via emailed links.</li>
      <li><strong>Environment-Based Configuration:</strong> Migrated database credentials to environment variables using Dotenv for safer, more flexible deployments.</li>
      <li><strong>Revenue Analytics:</strong> Added monthly and quarterly revenue summaries in Settings with per-ticket averages.</li>
      <li><strong>Enhanced Ticket Search:</strong> View Tickets page now filters by requester name, department, and location code.</li>
      <li><strong>Maintenance Mode:</strong> Added toggleable maintenance mode with Super Admin bypass and customizable downtime message.</li>
      <li><strong>Core Performance &amp; Security:</strong> Optimized core files, tightened input validation, and expanded activity logging for improved reliability.</li>
    </ul>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    Version 2.1 – Advanced Security, Auto-Assignment &amp; Database-Driven Pricing Management (7/10/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Database-Driven Pricing Management:</strong> Complete overhaul of pricing system with full admin control - add, edit, delete pricing items and categories through web interface, custom column headers per category, and role-based editing controls for Managers and Super Admins.</li>
      <li><strong>Auto-Assignment System:</strong> Implemented automated ticket assignment functionality for emergency coverage situations, with Super Admin controls, user selection dropdown, and comprehensive activity logging of all automatic assignments.</li>
      <li><strong>Enhanced Security &amp; Bot Protection:</strong> Added User-Agent tracking for all status checks, improved honeypot bot detection with detailed logging, and direct access protection for status pages with automatic redirection safeguards.</li>
      <li><strong>Hold Status &amp; Improved UI:</strong> Added new "Hold" ticket status with configurable email notifications, replaced multiple status buttons with streamlined dropdown menu, and enhanced visual status indicators across all system interfaces.</li>
      <li><strong>Advanced Cost Estimation:</strong> Fixed binding cost calculations to properly multiply by sets, improved NCR form pricing logic, enhanced cost breakdown display, and added estimated cost capture in form submissions.</li>
      <li><strong>Standalone Status Page &amp; File Handling:</strong> Created dedicated `status.php` for direct URL access with clean email linking, increased file upload limit from 2 to 5 files maximum, and standardized date formatting across all system pages.</li>
      <li><strong>Activity Log &amp; Export Enhancements:</strong> Added multi-line activity details formatting, filter result counters, new bot-related event types, Hold status integration in exports, and improved completion date filtering functionality.</li>
    </ul>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    Version 2.0 – Assignment Notifications, Activity Tracking &amp; Performance Enhancements (6/16/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Ticket Assignment Email Notifications:</strong> Added automated email notifications when tickets are assigned to staff members, keeping assignees informed of new work assignments with detailed ticket information.</li>
      <li><strong>New/Unread Ticket Counter:</strong> Implemented dynamic badge counters in navigation menu showing real-time counts of new and unread tickets for improved workflow awareness.</li>
      <li><strong>Enhanced Activity Logging:</strong> Expanded activity tracking system to capture ticket assignments, user management actions, and system events with comprehensive audit trails.</li>
      <li><strong>Cache Clearing &amp; Version Control:</strong> Added automatic CSS/JS version incrementing to prevent browser caching issues, ensuring users always see the latest interface updates without manual cache clearing.</li>
      <li><strong>Code Optimization &amp; Cleanup:</strong> Removed unused functions, redundant database connections, and deprecated code blocks to improve system performance and reduce server load.</li>
    </ul>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    Version 1.9 – Multi-Device Sessions, Enhanced Account Management &amp; Date Validation Fixes (6/10/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Multi-Device Session Management:</strong> Users can now stay logged in on multiple devices simultaneously with separate session tokens for each device.</li>
      <li><strong>Session Management Dashboard:</strong> Added "Active Sessions" section to My Account page allowing users to view all logged-in devices and selectively logout from individual devices or all devices at once.</li>
      <li><strong>Date Wanted Timezone Fix:</strong> Resolved timezone calculation issues in the ticket submission form to ensure accurate business day calculations based on Pacific Time.</li>
      <li><strong>Form Validation Improvements:</strong> Fixed array handling errors that were causing stale request issues during form submissions, improving overall form reliability.</li>
      <li><strong>Enhanced Security:</strong> Session tokens are now device-specific and properly managed across multiple concurrent logins while maintaining security standards.</li>
      <li><strong>User Experience Improvements:</strong> Cleaner session management with better feedback when logging out from specific devices or clearing all sessions.</li>
      <li><strong>Quick Access Navigation:</strong> Added "General Policies & Procedures" quick link to the dashboard for easy access to printing guidelines and policies.</li>
    </ul>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    Version 1.8 – Bulk Ticket Management, Activity Audit, Error Handling, &amp; Storage Management (5/02/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Bulk Delete Functionality:</strong> Added the ability to select and delete multiple tickets at once.</li>
      <li><strong>Activity Audit System:</strong> Comprehensive logging of all user actions including ticket submissions, status changes, and administrative operations.</li>
      <li><strong>Performance Optimization:</strong> Database query improvements, caching mechanisms, and reduced page load times.</li>
      <li><strong>Staff Role Restrictions:</strong> Enhanced security by preventing StaffUser accounts from accessing specific account management pages.</li>
      <li><strong>Security Improvements:</strong> Added additional CSRF protections and input validation on all forms.</li>
      <li><strong>Due Date Validation:</strong> Improved form validation for date_wanted field with business day calculations and weekend restrictions.</li>
      <li><strong>Request Tracking:</strong> Added logging for ticket status check requests to monitor system usage.</li>
      <li><strong>Error Handling:</strong> Improved error messages and logging for easier troubleshooting and better user experience.</li>
      <li><strong>Storage Cleanup:</strong> Added dedicated page with ability to purge file uploads by date range while preserving ticket records for analytics and reporting. Includes storage size visualization, file previews, and confirmation safeguards.</li>
    </ul>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    Version 1.7 – Universal Pacific-Time Support (fixes), Security Gates &amp; Export Fixes (4/26/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Global <code>toLA()</code> helper:</strong> Centralized UTC → America/Los_Angeles conversion in <code>assets/database.php</code>; every view, export, email, and print page now calls it for consistent Pacific-time display.</li>
      <li><strong>Accurate timestamp storage:</strong> <code>created_at</code> and <code>completed_at</code> are inserted with <code>UTC_TIMESTAMP()</code>/<code>gmdate()</code>, fixing the double-conversion bug and Turnaround-Time math.</li>
      <li><strong>Auto-refresh on Complete:</strong> Ticket list now reloads automatically when a ticket is marked <em>Complete</em>, matching Processing behaviour.</li>
      <li><strong>CSV export realignment:</strong> Added missing <code>department_name</code>, <code>phone</code>, and <code>separator_color</code> fields; all exported dates now use <code>toLA()</code>.</li>
      <li><strong>Print-ticket view:</strong> Shows <em>Completed On</em> (Pacific) and preserves line breaks in Admin Notes.</li>
      <li><strong>Analytics precision:</strong> Monthly chart groups by Pacific month via <code>CONVERT_TZ()</code>, preventing UTC rollover errors.</li>
      <li><strong>Role security gates:</strong> <em>StaffUser</em> accounts cannot access My Account or reset their passwords, even with a valid token.</li>
      <li><strong>Cloudflare Turnstile integration:</strong> Added Turnstile widget to the login form and auth middleware on every page in <code>/printing/</code> to block automated submissions and bots.</li>
      <li><strong>Code hygiene:</strong> Removed redundant PDO connections; all pages reuse the instance from <code>assets/database.php</code>.</li>
      <li><strong>LA-time clean-up pass:</strong> Delete-tickets list, CSV preview, and miscellaneous outputs converted to <code>toLA()</code>.</li>
    </ul>
  </div>
</div>

<div class="card mb-3">
  <div class="card-header">
    Version 1.6 – Debug Mode, Due Date Sorting & Processing Notifications (4/22/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Status List Enhancements:</strong> Added client‑side pagination (25 tickets per page) and a “Filter by Status” dropdown to the Tickets list.</li>
      <li><strong>Due Date Sorting:</strong> Made the “Due Date” column header clickable to toggle ascending/descending sort without a page reload, with default order by newest tickets first.</li>
      <li><strong>Processing Notification Toggle:</strong> Added a “Send email when ticket is marked <em>Processing</em>” checkbox in Settings, with personalized Processing‑status emails sent via SMTP to the requester.</li>
      <li><strong>Debug Settings Card:</strong> Introduced a separate Debug Settings card allowing Super Admins to toggle on/off in‑UI display of the server’s <code>error_log</code> file contents for easier troubleshooting.</li>
      <li><strong>LA Timezone Display:</strong> All timestamps in both the UI and outgoing notifications are now converted and shown in America/Los_Angeles time.</li>
    </ul>
  </div>
</div>

    <div class="card mb-3">
  <div class="card-header">
    Version 1.5 – SMTP Email Notifications & Settings Toggle (4/21/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>SMTP Configuration:</strong> Added a new settings section to specify SMTP Host, Port, Encryption (SSL/TLS), Username, and Password (stored encrypted with AES‑256‑CBC) to override the default PHP mailer.</li>
      <li><strong>PHPMailer Integration:</strong> Implemented the PHPMailer library for robust SMTP support, including authentication, encryption, and HTML email formatting.</li>
      <li><strong>Test Email:</strong> Introduced a “Send Test Email” button on the settings page for in‑UI verification of SMTP connectivity and credentials.</li>
      <li><strong>Notification Toggle:</strong> Added a “Send email when ticket is marked <em>Complete</em>” checkbox in settings so admins can enable or disable automatic completion notifications.</li>
      <li><strong>Personalized Completion Emails:</strong> When a ticket is marked Complete, an HTML email (UTF‑8, with plaintext fallback) is sent via SMTP to the requester, greeting them by first name and including ticket details.</li>
      <li><strong>Charset & HTML Support:</strong> Ensured all outgoing emails use UTF‑8 encoding and HTML formatting to preserve accented characters and rich markup.</li>
    </ul>
  </div>
</div>

    <div class="card mb-3">
  <div class="card-header">
    Version 1.4 – Estimates, Public Pricing & Cleanup (4/16/2025)
  </div>
  <div class="card-body">
    <ul>
      <li><strong>Live Cost Estimate:</strong> Added an “Estimated Cost” calculator on the ticket form—calculates based on pages, sets, print type, and paper weight before submission.</li>
      <li><strong>Public Pricing Page:</strong> Launched a new “Print Shop Pricing” page (no login required) detailing all per‑impression and per‑sheet rates.</li>
      <li><strong>Ticket Deletion:</strong> Super Admins can now delete tickets by ticket number—review key details, confirm, and remove both the database record and any uploaded files.</li>
      <li><strong>Export Enhancements:</strong> Updated export page and CSV so it uses ticket numbers (not IDs), shows “Assigned To” instead of notes, and includes all database fields behind the scenes.</li>
      <li><strong>Secure File Upload:</strong> Only allows doc/docx/pdf/png/jpg; total upload ≤15 MB; filenames are sanitized, slugified (date + job title), and auto‑incremented if duplicates.</li>
    </ul>
  </div>
</div>

    <div class="card mb-3">
      <div class="card-header">
       Version 1.3 – Enhanced Login and Account Management (4/15/2025)
      </div>
      <div class="card-body">
        <ul>
          <li><strong>Remember Me Functionality:</strong> Added a "Remember Me" checkbox that stores a secure token (in the database and in an HttpOnly, Secure cookie) for auto‑login.</li>
          <li><strong>Forgot Password Flow:</strong> Implemented forgot password and reset password pages with secure token validation.</li>
          <li><strong>My Account Page:</strong> Created a page to display the username (read‑only) and allow users to update their email and password.</li>
          
        </ul>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        Version 1.2 – Role-Based Access and Ticket Assignment
      </div>
      <div class="card-body">
        <ul>
          <li><strong>Role-Based Navigation:</strong> Updated navigation so that only logged‑in users with proper roles see assignment-related pages and controls.</li>
          <li><strong>Ticket Assignment:</strong> Added an "Assign To" button in the ticket modal (visible to Managers and Super Admins) with an AJAX‑based endpoint for assigning tickets to Admins and Managers.</li>
          
        </ul>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        Version 1.1 – Email and Conditional UI Enhancements
      </div>
      <div class="card-body">
        <ul>
          <li><strong>Email Notification:</strong> Implemented email notification upon form submission. Email notification settings page created</li>
          <li><strong>Conditional Form Fields:</strong> Implemented JavaScript logic to show/hide extra fields based on user choices (e.g., "Other" options).</li>
          <li><strong>Responsive Design:</strong> Improved mobile responsiveness with Bootstrap’s offcanvas sidebar and updated layouts.</li>
        </ul>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        Version 1.0 – Modularization and Basic Security
      </div>
      <div class="card-body">
        <ul>
          <li><strong>Separation of Concerns:</strong> Divided functionality into multiple files (dashboard.php, newticket.php, viewtickets.php, export.php) and introduced common header.php and footer.php.</li>
          <li><strong>Security Enhancements:</strong> Implemented prepared statements and CSRF tokens for form submissions.</li>
          <li><strong>Custom Ticket Numbering:</strong> Created a ticket numbering scheme in the format YYYYMMxxx.</li>
        </ul>
      </div>
    </div>

    <div class="card mb-3">
      <div class="card-header">
        Version 0.9 – Prototype
      </div>
      <div class="card-body">
        <ul>
          <li>Initial proof-of-concept with all functionality in a single file.</li>
          <li>Basic ticket submission, viewing, and file uploads.</li>
          <li>Minimal validation and security at this stage.</li>
        </ul>
      </div>
    </div>

  </section>
</div>

<?php require_once 'footer.php'; ?>
