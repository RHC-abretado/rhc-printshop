<?php require_once 'header.php'; ?>

<h1>Printshop Management System</h1>

<div class="row">
  <!-- Main Information Card -->
  <div class="col-md-7 mb-4">
    <div class="card h-100">
      <div class="card-header">
        Submit Your Print Request Here
      </div>
      <div class="card-body">
        <p style="font-weight:900;">Click the green "Print Request" button to jump to the online form.</p>
        <p class="card-text">
            <ul><li>Please provide as much detailed information as possible.
                </li>
                <li>Your request will be reviewed by staff and processed accordingly. 
                </li>
                <li>Most orders submitted by 12 p.m. will be completed within three (3) working days (i.e., excluding weekends and holidays). 
                </li>
                <li>For orders submitted after 12 p.m., the “turnaround time” of three (3) working days will begin the following day.
                </li>
                <li>Larger projects may require more time. For more information, or a turnaround time estimate, email <a href="mailto:printing@riohondo.edu">printing@riohondo.edu</a>.</li></p>
        
        <div class="d-grid gap-2 mt-4">
          <a href="newticket.php" class="btn btn-success btn-lg">
            <i class="bi bi-plus-circle"></i> Print Request
          </a>
        </div>
      </div>
    </div>
  </div>
  
  <!-- Quick Links -->
  <div class="col-md-5 mb-4">
    <div class="card mb-4">
      <div class="card-header">
        Quick Links
      </div>
      <div class="card-body">
        <div class="list-group">
          <a href="#" class="list-group-item list-group-item-action d-flex align-items-center" data-bs-toggle="modal" data-bs-target="#checkStatusModal">
            <i class="bi bi-search me-3 fs-4"></i>
            <div>
             <p class="mb-0">Check Status</p>
              <small class="text-muted">Track the progress of your print job</small>
            </div>
          </a>
          <a href="pricing.php" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="bi bi-tag me-3 fs-4"></i>
            <div>
              <p class="mb-0">Pricing Information</p>
              <small class="text-muted">View our service rates</small>
            </div>
          </a>
          <a href="https://docs.google.com/document/d/1lp_PdDOUO9V5QMcFikHElBUXDGPkP7wjlQKjn7T9LLc/edit?tab=t.0" target="_blank" class="list-group-item list-group-item-action d-flex align-items-center">
  <i class="bi bi-file-text me-3 fs-4"></i>
  <div>
    <p class="mb-0">General Policies & Procedures</p>
    <small class="text-muted">View our printing policies and guidelines</small>
  </div>
</a>
          <a href="mailto:printing@riohondo.edu" class="list-group-item list-group-item-action d-flex align-items-center">
            <i class="bi bi-envelope me-3 fs-4"></i>
            <div>
              <p class="mb-0">Contact Print Shop</p>
              <small class="text-muted">Email us with questions</small>
            </div>
          </a>
        </div>
      </div>
    </div>
  </div>
</div>

<!-- Copyright Information -->
<div class="card">
  <div class="card-header bg-dark text-white">
    Copyright Laws
  </div>
  <div class="card-body">
    <h6>Summary of Civil and Criminal Penalties for Violation of Federal Copyright Laws</h6>
    <p>Copyright infringement is the act of exercising, without permission or legal authority, one or more of the exclusive rights granted to the copyright owner under section 106 of the Copyright Act (Title 17 of the United States Code). These rights include the right to reproduce or distribute a copyrighted work. In the file sharing context, downloading or uploading substantial parts of a copyrighted work without authority constitutes an infringement.</p>
    
    <p>Penalties for copyright infringement include civil and criminal penalties. In general, anyone found liable for civil copyright infringement may be ordered to pay either actual damages or "statutory" damages affixed at not less than $750 and not more than $30,000 per work infringed. For "willful" infringement, a court may award up to $150,000 per work infringed. A court can, in its discretion, also assess costs and attorneys' fees.</p>
    
    <p>For details, see Title 17, United States Code, Sections 504, 505. Willful copyright infringement can also result in criminal penalties, including imprisonment of up to five years and fines of up to $250,000 per offense. For more information, please see the website of the U.S. Copyright Office at <a href="https://www.copyright.gov" target="_blank">www.copyright.gov</a>.</p>
  </div>
</div>

<?php require_once 'footer.php'; ?>