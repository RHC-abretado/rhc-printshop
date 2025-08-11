<?php
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (empty($_SESSION['form_token'])) {
  $_SESSION['form_token'] = bin2hex(random_bytes(32));
}
?>

<?php require_once 'header.php'; ?>

<style>
  #estimatedCost {
    display: none;
  }
</style>

<h1>Submit a Print Request</h1>

  <div class="card" id="ticketForm">
    <div class="card-header">Ticket Information
      <button type="button" id="clearRequesterInfo" class="btn btn-sm btn-outline-secondary float-end">Clear saved info</button>
    </div>
    <div class="card-body">
    <form id="newTicketForm" method="post" action="submit_request.php" enctype="multipart/form-data" class="row g-3">

      <!-- NAME (First/Last) -->
      <div class="col-md-6">
        <label for="first_name" class="form-label">
          First Name <span class="text-danger">(Required)</span>
        </label>
        <input type="text" name="first_name" id="first_name" class="form-control" required>
      </div>
      <div class="col-md-6">
        <label for="last_name" class="form-label">
          Last Name <span class="text-danger">(Required)</span>
        </label>
        <input type="text" name="last_name" id="last_name" class="form-control" required>
      </div>

      <!-- Department Name -->
      <div class="col-md-6">
        <label for="department_name" class="form-label">
          Department Name <span class="text-danger">(Required)</span>
        </label>
        <input type="text" name="department_name" id="department_name" class="form-control" required>
      </div>

      <!-- Email -->
      <div class="col-md-6">
        <label for="email" class="form-label">
          Email <span class="text-danger">(Required)</span>
        </label>
        <input type="email" name="email" id="email" class="form-control" required>
      </div>

      <!-- Phone -->
      <div class="col-md-6">
        <label for="phone" class="form-label">Phone</label>
        <input type="text" name="phone" id="phone" class="form-control">
      </div>

      <!-- Location Code to be Charged -->
<div class="col-md-6">
  <label for="location_code_select" class="form-label">
    Location Code to be Charged <span class="text-danger">(Required)</span>
  </label>
  <select name="location_code_select" id="location_code_select" class="form-select" required>
    <option value="" selected>Select Option</option>
    <?php
    // Fetch location codes from database
try {
    // This query will keep "Other" at the bottom
    $locStmt = $pdo->query("
        SELECT department_name, code 
        FROM location_codes 
        ORDER BY 
            CASE WHEN code = 'Other (00000)' THEN 2 ELSE 1 END, 
            department_name ASC
    ");
    $locationCodes = $locStmt->fetchAll(PDO::FETCH_ASSOC);
    
    foreach ($locationCodes as $loc) {
        echo '<option value="' . htmlspecialchars($loc['code']) . '">' 
            . htmlspecialchars($loc['code']) . '</option>';
    }
} catch (PDOException $e) {
    // If error, add a default "Other" option
    echo '<option value="Other (00000)">Other (00000)</option>';
    // Optionally log the error
    error_log('Error fetching location codes: ' . $e->getMessage());
}
    ?>
  </select>
</div>

      <!-- Other Location Code (hidden until "Other" is selected) -->
      <div class="col-md-6" id="other_location_code_container" style="display:none;">
        <label for="other_location_code" class="form-label">Other Location Code</label>
        <input type="text" name="other_location_code" id="other_location_code" class="form-control">
      </div>

<!-- Date Wanted -->
<div class="col-md-6">
  <label for="date_wanted" class="form-label">
    Date Wanted <span class="text-danger">(Required)</span>
  </label>
  <input type="date" name="date_wanted" id="date_wanted" class="form-control" required>
  <small class="text-muted">Please note: Orders require at least 3 business days processing time. Weekends are not counted.</small>
</div>
      <!-- Delivery Method -->
      <div class="col-md-6">
        <label class="form-label d-block">
          Delivery Method <span class="text-danger">(Required)</span>
        </label>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="delivery_method" value="Pickup in Print Shop" id="dm_pickup" required>
          <label class="form-check-label" for="dm_pickup">Pickup in Print Shop</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="delivery_method" value="Arrange for Delivery" id="dm_delivery">
          <label class="form-check-label" for="dm_delivery">Arrange for Delivery</label>
        </div>
      </div>

      <!-- Title of Print Job -->
      <div class="col-md-6">
        <label for="job_title" class="form-label">
          Title of Print Job <span class="text-danger">(Required)</span>
        </label>
        <input type="text" name="job_title" id="job_title" class="form-control" required>
      </div>

      <!-- Instructions / Notes -->
      <div class="col-12">
        <label for="description" class="form-label">Instructions / Notes</label>
        <textarea name="description" id="description" class="form-control" rows="3"></textarea>
      </div>

      <!-- Number of Pages in Original Sets -->
      <div class="col-md-4">
        <label for="pages_in_original" class="form-label">
          Number of Pages in Original Sets <span class="text-danger">(Required)</span>
        </label>
        <input type="text" name="pages_in_original" id="pages_in_original" class="form-control">
      </div>

      <!-- Number of Sets to Be Made -->
      <div class="col-md-6">
        <label for="number_of_sets" class="form-label">
          Number of Sets to Be Made <span class="text-danger">(Required)</span>
        </label>
        <input type="number" name="number_of_sets" id="number_of_sets" class="form-control" required>
      </div>

      <!-- File Upload -->
<div class="col-md-6">
  <label for="job_file" class="form-label">
    Upload File(s) <span class="text-danger">(Required)</span>
  </label>
  <div class="drag-drop-area" id="dragDropArea">
    <i class="bi bi-cloud-arrow-up upload-icon"></i>
    <div class="upload-text">
      <strong>Drag and drop files here</strong><br>
      or <a href="#" id="browseLink">click to browse</a>
    </div>
    <input type="file" name="job_file[]" id="job_file" class="file-input-hidden" 
     accept=".doc,.docx,.pdf,.png,.jpg,.jpeg,.ppt,.pptx,.pub" multiple required>
  </div>
  <ul class="file-list" id="fileList"></ul>
        <small class="text-muted">Max 100 MB total, up to five files (.doc, .docx, .pdf, .png, .jpg, .jpeg, .ppt, .pptx, and .pub only). For more than five files, submit additional Print Request forms.</small>
      </div>

      <!-- Page Layout -->
      <div class="col-md-4">
        <label for="page_layout" class="form-label">
          Page Layout <span class="text-danger">(Required)</span>
        </label>
        <select name="page_layout" id="page_layout" class="form-select">
          <option value="Single Sided">Single Sided</option>
          <option value="Double Sided Top to Top">Double Sided Top to Top</option>
        </select>
      </div>

      <!-- Print Copies in -->
      <div class="col-md-4">
        <label for="print_copies_in" class="form-label">
          Print Copies in <span class="text-danger">(Required)</span>
        </label>
        <select name="print_copies_in" id="print_copies_in" class="form-select" required>
          <option value="Black &amp; White">Black &amp; White</option>
          <option value="Color">Color</option>
          <option value="Other">Other</option>
        </select>
      </div>

      <!-- Other Print Copies (hidden until "Other" is selected) -->
      <div class="col-md-4" id="other_print_copies_container" style="display:none;">
        <label for="other_print_copies" class="form-label">Other Print Copies:</label>
        <input type="text" name="other_print_copies" id="other_print_copies" class="form-control">
        <small class="text-muted">Please describe</small>
      </div>

      <!-- Page Type / Weight -->
      <div class="col-md-4">
        <label for="page_type" class="form-label">Page Type / Weight</label>
        <select name="page_type" id="page_type" class="form-select">
          <option value="Standard 20#">Standard 20#</option>
          <option value="Card Stock">Card Stock</option>
          <option value="NCR 2-Part">NCR 2-Part</option>
          <option value="NCR 3-Part">NCR 3-Part</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <!-- Other Page Type (hidden until "Other" is selected) -->
      <div class="col-md-4" id="other_page_type_container" style="display:none;">
        <label for="other_page_type" class="form-label">Other Page Type:</label>
        <input type="text" name="other_page_type" id="other_page_type" class="form-control">
      </div>
      
      <!-- Paper Color -->
      <div class="col-md-4">
        <label for="paper_color_select" class="form-label">Paper Color</label>
        <select name="paper_color_select" id="paper_color_select" class="form-select">
          <option value="White">White</option>
          <option value="Grey">Grey</option>
          <option value="Blue">Blue</option>
          <option value="Yellow">Yellow</option>
          <option value="Green">Green</option>
          <option value="Astro-Bright">Astro-Bright</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <!-- Other Paper Color (hidden) -->
      <div class="col-md-4" id="other_paper_color_container" style="display:none;">
        <label for="other_paper_color" class="form-label">Other Paper Color:</label>
        <input type="text" name="other_paper_color" id="other_paper_color" class="form-control">
      </div>
      
      <!-- Describe Astro-Bright / Color Requirement -->
      <div class="col-md-8">
        <label for="color_requirement" class="form-label">Describe Astro-Bright or Color Requirement:</label>
        <input type="text" name="color_requirement" id="color_requirement" class="form-control">
      </div>
      
      <!-- Paper Size -->
      <div class="col-md-4">
        <label for="paper_size_select" class="form-label">Paper Sizes <span class="text-danger">(Required)</span></label>
        <select name="paper_size_select" id="paper_size_select" class="form-select" required>
  <option value="Standard: 8.5&quot;x11&quot;">Standard: 8.5" x 11"</option>
  <option value="Legal: 8.5&quot;x14&quot;">Legal: 8.5" x 14"</option>
  <option value="Ledger: 11&quot;x17&quot;">Ledger: 11" x 17"</option>
  <option value="12&quot;x18&quot;">12" x 18" (cardstock only)</option>
  <option value="13&quot;x19&quot; (cardstock only)">13" x 19" (cardstock only)</option>
  <option value="Other">Other</option>
</select>
      </div>
      
      <!-- Other Paper Size (hidden) -->
      <div class="col-md-4" id="other_paper_size_container" style="display:none;">
        <label for="other_paper_size" class="form-label">Other Paper Size:</label>
        <input type="text" name="other_paper_size" id="other_paper_size" class="form-control">
      </div>
      
      <!-- Other Options (checkboxes) -->
      <div class="col-12">
        <label class="form-label d-block">Other Options</label>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="other_options[]" value="Staple" id="option_staple">
          <label class="form-check-label" for="option_staple">Staple</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="other_options[]" value="3-Hole Punch" id="option_3hole">
          <label class="form-check-label" for="option_3hole">3-Hole Punch</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="other_options[]" value="Fold" id="option_fold">
          <label class="form-check-label" for="option_fold">Fold</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="other_options[]" value="Binding" id="option_binding">
          <label class="form-check-label" for="option_binding">Binding</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="other_options[]" value="Tabs" id="option_tabs">
          <label class="form-check-label" for="option_tabs">Tabs</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="checkbox" name="other_options[]" value="Cut Paper" id="option_cutpaper">
          <label class="form-check-label" for="option_cutpaper">Cut Paper</label>
        </div>
        <div class="form-check form-check-inline">
          <!-- Displayed as "Uncollated" but sent as "Collate" -->
          <input class="form-check-input" type="checkbox" name="other_options[]" value="Uncollated" id="option_collate">
          <label class="form-check-label" for="option_collate">Uncollated</label>
        </div>
      </div>
      
      <!-- Staple Location (show if "Staple" checked) -->
      <div class="col-md-6" id="staple_location_container" style="display:none;">
        <label for="staple_location" class="form-label">Staple Location</label>
        <select name="staple_location" id="staple_location" class="form-select">
          <option value="Left Corner">Left Corner</option>
          <option value="Right Corner">Right Corner</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <!-- Other Staple Location (if "Other" chosen) -->
      <div class="col-md-6" id="other_staple_location_container" style="display:none;">
        <label for="other_staple_location" class="form-label">Other Staple Location</label>
        <input type="text" name="other_staple_location" id="other_staple_location" class="form-control">
        <small>Please describe</small>
      </div>
      
      <!-- Fold Type (show if "Fold" checked) -->
      <div class="col-md-6" id="fold_type_container" style="display:none;">
        <label for="fold_type" class="form-label">Fold Type</label>
        <select name="fold_type" id="fold_type" class="form-select">
          <option value="Half (Fold)">Half (Fold)</option>
          <option value="Tri-Fold">Tri-Fold</option>
          <option value="Z-Fold">Z-Fold</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <!-- Other Fold Type -->
      <div class="col-md-6" id="other_fold_type_container" style="display:none;">
        <label for="other_fold_type" class="form-label">Other Fold Type</label>
        <input type="text" name="other_fold_type" id="other_fold_type" class="form-control">
        <small>Please describe</small>
      </div>
      
      <!-- Binding Type (show if "Binding" checked) -->
      <div class="col-md-6" id="binding_type_container" style="display:none;">
        <label for="binding_type" class="form-label">Binding Type</label>
        <select name="binding_type" id="binding_type" class="form-select">
          <option value="Tape">Tape</option>
          <option value="Comb">Comb</option>
          <option value="Coil">Coil</option>
          <option value="Other">Other</option>
        </select>
      </div>
      
      <!-- Other Binding Type -->
      <div class="col-md-6" id="other_binding_type_container" style="display:none;">
        <label for="other_binding_type" class="form-label">Other Binding Type</label>
        <input type="text" name="other_binding_type" id="other_binding_type" class="form-control">
        <small>Please describe</small>
      </div>
      
      <!-- Cut Paper (radio) - shown if "Cut Paper" checkbox is checked -->
      <div class="col-md-6" id="cut_paper_container" style="display:none;">
        <label class="form-label d-block">Cut Paper</label>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="cut_paper" value="1/4 Sheet" id="cut_quarter">
          <label class="form-check-label" for="cut_quarter">1/4 Sheet</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="cut_paper" value="1/3 Sheet" id="cut_third">
          <label class="form-check-label" for="cut_third">1/3 Sheet</label>
        </div>
        <div class="form-check form-check-inline">
          <input class="form-check-input" type="radio" name="cut_paper" value="1/2 Sheet" id="cut_half">
          <label class="form-check-label" for="cut_half">1/2 Sheet</label>
        </div>
      </div>
      
      <!-- CSRF token -->
      <input type="hidden" name="form_token" value="<?php echo $_SESSION['form_token']; ?>" />
      <!-- Estimated Cost (hidden) -->
<input type="hidden" name="estimated_cost" id="estimated_cost" value="0">
      
    </form>
    <br />
    <div class="col-lg-6 col-md-12 col-sm-12 col-xs-12">
  <div id="estimatedCost" class="alert alert-info" style="display:none;"></div>
</div>
  <div class="progress mt-3 d-none" id="submitProgress">
    <div
      id="submitProgressBar"
      class="progress-bar"
      role="progressbar"
      style="width: 0%"
      aria-valuemin="0"
      aria-valuemax="100"
    >0%</div>
  </div>
    <button type="submit" form="newTicketForm" class="btn btn-primary mt-3">Submit Request</button>
    
  </div>
</div>

<!-- This div displays any success/error message after the form is submitted via AJAX -->
<div id="formMessage" class="mt-4"></div>

</div>
<script>
document.addEventListener('DOMContentLoaded', function() {
  const form    = document.getElementById('newTicketForm');
  const wrapper = document.getElementById('submitProgress');
  const bar     = document.getElementById('submitProgressBar');
  const msgDiv  = document.getElementById('formMessage');
  const card    = document.getElementById('ticketForm');
  const clearBtn = document.getElementById('clearRequesterInfo');

  // Populate form fields from saved info, if available
  const savedInfo = localStorage.getItem('ticketRequesterInfo');
  if (savedInfo) {
    try {
      const info = JSON.parse(savedInfo);
      document.getElementById('first_name').value = info.first_name || '';
      document.getElementById('last_name').value = info.last_name || '';
      document.getElementById('department_name').value = info.department_name || '';
      document.getElementById('email').value = info.email || '';
      document.getElementById('phone').value = info.phone || '';
      document.getElementById('location_code_select').value = info.location_code_select || '';
      document.getElementById('other_location_code').value = info.other_location_code || '';
      document.getElementById('location_code_select').dispatchEvent(new Event('change'));
    } catch (e) {
      // If parsing fails, remove the bad data
      localStorage.removeItem('ticketRequesterInfo');
    }
  }

  // Clear saved info handler
  if (clearBtn) {
    clearBtn.addEventListener('click', function() {
      localStorage.removeItem('ticketRequesterInfo');
      ['first_name','last_name','department_name','email','phone','location_code_select','other_location_code'].forEach(id => {
        const el = document.getElementById(id);
        if (el) el.value = '';
      });
      document.getElementById('location_code_select').dispatchEvent(new Event('change'));
    });
  }

  form.addEventListener('submit', function(e) {
    e.preventDefault();

    // reset UI
    wrapper.classList.remove('d-none');
    bar.style.width   = '0%';
    bar.textContent   = '0%';
    msgDiv.innerHTML  = '';

    // Save requester info to localStorage
    const requesterInfo = {
      first_name: document.getElementById('first_name').value,
      last_name: document.getElementById('last_name').value,
      department_name: document.getElementById('department_name').value,
      email: document.getElementById('email').value,
      phone: document.getElementById('phone').value,
      location_code_select: document.getElementById('location_code_select').value,
      other_location_code: document.getElementById('other_location_code').value
    };
    try {
      localStorage.setItem('ticketRequesterInfo', JSON.stringify(requesterInfo));
    } catch (e) {
      // Ignore storage errors
    }

    // build FormData (includes your hidden form_token)
    const data = new FormData(form);

    // set up XHR
    const xhr = new XMLHttpRequest();
    xhr.open('POST', form.action, true);
    xhr.setRequestHeader('Accept', 'application/json');

    xhr.upload.onprogress = function(evt) {
      if (!evt.lengthComputable) return;
      const pct = Math.round(evt.loaded/evt.total * 100);
      bar.style.width = pct + '%';
      bar.textContent = pct + '%';
    };

    xhr.onload = function() {
      wrapper.classList.add('d-none');

      let resp;
      try {
        resp = JSON.parse(xhr.responseText);
      } catch {
        alert('Unexpected server response.');
        return;
      }

      // hide the form card
      card.style.display = 'none';

      // build the same success/error markup you had
      let html = '';
      if (resp.success) {
        html = `
          <div class="alert alert-success">
            <h4 class="alert-heading">Success!</h4>
            <p>${resp.message}</p>
          </div>
          <a href="newticket.php" class="btn btn-primary">Add Another Request</a>
        `;
      } else {
        html = `
          <div class="alert alert-danger">
            <h4 class="alert-heading">Error</h4>
            <p>${resp.message}</p>
          </div>
          <button class="btn btn-secondary" onclick="history.back()">Go Back</button>
        `;
      }
      msgDiv.innerHTML = html;
    };

    xhr.onerror = function() {
      wrapper.classList.add('d-none');
      alert('Network error. Please try again.');
    };

    // send it
    xhr.send(data);
  });
});
</script>

<script>
document.addEventListener('DOMContentLoaded', function() {
  const dateWantedInput = document.getElementById('date_wanted');
  
  // Calculate and set the earliest available date
  const minDate = calculateMinimumDate();
  dateWantedInput.min = minDate.formatted;
  dateWantedInput.value = minDate.formatted;
  
  // Function to calculate minimum date based on current time
  function calculateMinimumDate() {
    // Get today's date and time in local timezone
    const now = new Date();
    const currentHour = now.getHours();
    const isAfterNoon = currentHour >= 12;
    
    // If it's after noon, add an extra business day
    const requiredBusinessDays = isAfterNoon ? 3 : 2;
    
    // Start with today
    let date = new Date(now);
    let businessDaysAdded = 0;
    
    // Add days until we reach the required business days
    while (businessDaysAdded < requiredBusinessDays) {
      date.setDate(date.getDate() + 1);
      // Skip weekends (0 = Sunday, 6 = Saturday)
      const dayOfWeek = date.getDay();
      if (dayOfWeek !== 0 && dayOfWeek !== 6) {
        businessDaysAdded++;
      }
    }
    
    // Format date as YYYY-MM-DD
    const year = date.getFullYear();
    const month = String(date.getMonth() + 1).padStart(2, '0');
    const day = String(date.getDate()).padStart(2, '0');
    const formatted = `${year}-${month}-${day}`;
    
    return {
      date: date,
      formatted: formatted
    };
  }
  
  // Validate date selection
  dateWantedInput.addEventListener('change', function() {
    const selectedValue = this.value;
    if (!selectedValue) return;
    
    // Create a date object from the selected value (in local time)
    const selectedDate = new Date(selectedValue + 'T00:00:00');
    const minDateObj = new Date(this.min + 'T00:00:00');
    
    // Check if weekend (0 = Sunday, 6 = Saturday)
    const dayOfWeek = selectedDate.getDay();
    const isWeekendSelected = (dayOfWeek === 0 || dayOfWeek === 6);
    
    // Check if before minimum date
    const isTooEarly = selectedDate < minDateObj;
    
    // Handle validations
    if (isWeekendSelected || isTooEarly) {
      let errorMessage = '';
      
      if (isWeekendSelected) {
        errorMessage = 'Weekend dates are not available for order pickup/delivery. Please select a weekday.';
      } else if (isTooEarly) {
        // Create a more specific message based on current time
        const now = new Date();
        const isAfterNoon = now.getHours() >= 12;
        
        if (isAfterNoon) {
          errorMessage = 'Orders placed after 12pm require 3 business days processing time.';
        } else {
          errorMessage = 'Orders require at least 2 business days processing time.';
        }
      }
      
      // Show error message
      Swal.fire({
        title: 'Invalid Date',
        text: errorMessage,
        icon: 'error',
        confirmButtonText: 'OK'
      });
      
      // Set back to minimum date
      this.value = this.min;
    }
  });
});

// ========= DRAG AND DROP FILE UPLOAD =========
document.addEventListener('DOMContentLoaded', function() {
    const dragDropArea = document.getElementById('dragDropArea');
    const fileInput = document.getElementById('job_file');
    const browseLink = document.getElementById('browseLink');
    const fileList = document.getElementById('fileList');
    
    const allowedTypes = ['application/msword', 'application/vnd.openxmlformats-officedocument.wordprocessingml.document', 'application/pdf', 'image/png', 'image/jpeg', 'image/jpg'];
    const allowedExtensions = ['.doc', '.docx', '.pdf', '.png', '.jpg', '.jpeg', '.ppt', '.pptx', '.pub'];
    const maxFiles = 5;
    const maxTotalSize = 100 * 1024 * 1024; // 100MB
    
    let selectedFiles = [];

    // Browse link click
    browseLink.addEventListener('click', function(e) {
        e.preventDefault();
        fileInput.click();
    });

    // Drag drop area click
    dragDropArea.addEventListener('click', function(e) {
        if (e.target === dragDropArea || e.target.closest('.upload-icon') || e.target.closest('.upload-text')) {
            fileInput.click();
        }
    });

    // File input change
    fileInput.addEventListener('change', function(e) {
        handleFiles(e.target.files);
    });

    // Drag events
    ['dragenter', 'dragover', 'dragleave', 'drop'].forEach(eventName => {
        dragDropArea.addEventListener(eventName, preventDefaults, false);
        document.body.addEventListener(eventName, preventDefaults, false);
    });

    ['dragenter', 'dragover'].forEach(eventName => {
        dragDropArea.addEventListener(eventName, highlight, false);
    });

    ['dragleave', 'drop'].forEach(eventName => {
        dragDropArea.addEventListener(eventName, unhighlight, false);
    });

    dragDropArea.addEventListener('drop', handleDrop, false);

    function preventDefaults(e) {
        e.preventDefault();
        e.stopPropagation();
    }

    function highlight(e) {
        dragDropArea.classList.add('dragover');
    }

    function unhighlight(e) {
        dragDropArea.classList.remove('dragover');
    }

    function handleDrop(e) {
        const dt = e.dataTransfer;
        const files = dt.files;
        handleFiles(files);
    }

    function handleFiles(files) {
        const validFiles = [];
        let totalSize = 0;

        // Calculate current total size
        selectedFiles.forEach(file => totalSize += file.size);

        for (let i = 0; i < files.length; i++) {
            const file = files[i];
            
            // Check file count
            if (selectedFiles.length + validFiles.length >= maxFiles) {
                alert(`Maximum ${maxFiles} files allowed.`);
                break;
            }

            // Check file type
            const fileExtension = '.' + file.name.split('.').pop().toLowerCase();
            if (!allowedExtensions.includes(fileExtension)) {
                alert(`File "${file.name}" is not allowed. Only doc, docx, pdf, png, jpg, jpeg, ppt, pptx, pub files are permitted.`);
                continue;
            }

            // Check total size
            if (totalSize + file.size > maxTotalSize) {
                alert(`Total file size would exceed 100MB limit.`);
                break;
            }

            validFiles.push(file);
            totalSize += file.size;
        }

        // Add valid files to selected files
        selectedFiles = selectedFiles.concat(validFiles);
        
        updateFileInput();
        updateFileList();
        updateDragDropArea();
    }

    function updateFileInput() {
        // Create a new DataTransfer object to update the file input
        const dt = new DataTransfer();
        selectedFiles.forEach(file => dt.items.add(file));
        fileInput.files = dt.files;
    }

    function updateFileList() {
        fileList.innerHTML = '';
        
        selectedFiles.forEach((file, index) => {
            const li = document.createElement('li');
            li.className = 'file-item';
            li.innerHTML = `
                <div class="file-info">
                    <div class="file-name">${file.name}</div>
                    <div class="file-size">${formatFileSize(file.size)}</div>
                </div>
                <button type="button" class="remove-file" data-index="${index}" title="Remove file">
                    <i class="bi bi-x-lg"></i>
                </button>
            `;
            fileList.appendChild(li);
        });

        // Add click events to remove buttons
        fileList.addEventListener('click', function(e) {
            if (e.target.closest('.remove-file')) {
                const index = parseInt(e.target.closest('.remove-file').dataset.index);
                removeFile(index);
            }
        });
    }

    function removeFile(index) {
        selectedFiles.splice(index, 1);
        updateFileInput();
        updateFileList();
        updateDragDropArea();
    }

    function updateDragDropArea() {
        if (selectedFiles.length > 0) {
            dragDropArea.classList.add('has-files');
            dragDropArea.querySelector('.upload-text').innerHTML = `
                <strong>${selectedFiles.length} file(s) selected</strong><br>
                <a href="#" id="browseLink">Add more files</a> or drag additional files here
            `;
        } else {
            dragDropArea.classList.remove('has-files');
            dragDropArea.querySelector('.upload-text').innerHTML = `
                <strong>Drag and drop files here</strong><br>
                or <a href="#" id="browseLink">click to browse</a>
            `;
        }
        
        // Re-attach browse link event
        const newBrowseLink = dragDropArea.querySelector('#browseLink');
        if (newBrowseLink) {
            newBrowseLink.addEventListener('click', function(e) {
                e.preventDefault();
                fileInput.click();
            });
        }
    }

    function formatFileSize(bytes) {
        if (bytes === 0) return '0 Bytes';
        const k = 1024;
        const sizes = ['Bytes', 'KB', 'MB', 'GB'];
        const i = Math.floor(Math.log(bytes) / Math.log(k));
        return parseFloat((bytes / Math.pow(k, i)).toFixed(2)) + ' ' + sizes[i];
    }
});
</script>

<script>



// ========= CONDITIONAL LOGIC (show/hide "Other" fields) =========

// 1) Location Code: show/hide Other Location Code
const locationSelect = document.getElementById('location_code_select');
const otherLocationContainer = document.getElementById('other_location_code_container');
locationSelect.addEventListener('change', function() {
  if (this.value.includes('Other')) {
    otherLocationContainer.style.display = 'block';
  } else {
    otherLocationContainer.style.display = 'none';
    document.getElementById('other_location_code').value = '';
  }
});

// 2) Print Copies in: show/hide Other Print Copies
const printCopiesSelect = document.getElementById('print_copies_in');
const otherPrintCopiesContainer = document.getElementById('other_print_copies_container');
printCopiesSelect.addEventListener('change', function() {
  if (this.value === 'Other') {
    otherPrintCopiesContainer.style.display = 'block';
  } else {
    otherPrintCopiesContainer.style.display = 'none';
    document.getElementById('other_print_copies').value = '';
  }
});

// 3) Page Type: show/hide Other Page Type
const pageTypeSelect = document.getElementById('page_type');
const otherPageTypeContainer = document.getElementById('other_page_type_container');
pageTypeSelect.addEventListener('change', function() {
  if (this.value === 'Other') {
    otherPageTypeContainer.style.display = 'block';
  } else {
    otherPageTypeContainer.style.display = 'none';
    document.getElementById('other_page_type').value = '';
  }
});

// 4) Paper Color: show/hide Other Paper Color
const paperColorSelect = document.getElementById('paper_color_select');
const otherPaperColorContainer = document.getElementById('other_paper_color_container');
paperColorSelect.addEventListener('change', function() {
  if (this.value === 'Other') {
    otherPaperColorContainer.style.display = 'block';
  } else {
    otherPaperColorContainer.style.display = 'none';
    document.getElementById('other_paper_color').value = '';
  }
});

// 5) Paper Size: show/hide Other Paper Size
const paperSizeSelect = document.getElementById('paper_size_select');
const otherPaperSizeContainer = document.getElementById('other_paper_size_container');
paperSizeSelect.addEventListener('change', function() {
  if (this.value === 'Other') {
    otherPaperSizeContainer.style.display = 'block';
  } else {
    otherPaperSizeContainer.style.display = 'none';
    document.getElementById('other_paper_size').value = '';
  }
});

// 5.5) Paper Size: Auto-select Card Stock for cardstock-only sizes
paperSizeSelect.addEventListener('change', function() {
  const pageTypeSelect = document.getElementById('page_type');
  
  // Check if cardstock-only sizes are selected
  if (this.value.includes('12"x18"') || this.value.includes('13"x19"')) {
    // Automatically set page type to Card Stock
    pageTypeSelect.value = 'Card Stock';
    
    // Trigger change event to recalculate estimate
    pageTypeSelect.dispatchEvent(new Event('change'));
  }
});

// 6) Other Options: Attach event listeners for options that reveal extra fields

// For Staple option
const stapleCheckbox = document.getElementById('option_staple');
const stapleLocContainer = document.getElementById('staple_location_container');
const otherStapleLocCtnr = document.getElementById('other_staple_location_container');
stapleCheckbox.addEventListener('change', function() {
  if (this.checked) {
    stapleLocContainer.style.display = 'block';
  } else {
    stapleLocContainer.style.display = 'none';
    document.getElementById('staple_location').selectedIndex = 0;
    otherStapleLocCtnr.style.display = 'none';
    document.getElementById('other_staple_location').value = '';
  }
});

// For Fold option
const foldCheckbox = document.getElementById('option_fold');
const foldTypeContainer = document.getElementById('fold_type_container');
const otherFoldTypeContainer = document.getElementById('other_fold_type_container');
foldCheckbox.addEventListener('change', function() {
  if (this.checked) {
    foldTypeContainer.style.display = 'block';
  } else {
    foldTypeContainer.style.display = 'none';
    document.getElementById('fold_type').selectedIndex = 0;
    otherFoldTypeContainer.style.display = 'none';
    document.getElementById('other_fold_type').value = '';
  }
});

// For Binding option
const bindingCheckbox = document.getElementById('option_binding');
const bindingTypeContainer = document.getElementById('binding_type_container');
const otherBindingTypeCtnr = document.getElementById('other_binding_type_container');
bindingCheckbox.addEventListener('change', function() {
  if (this.checked) {
    bindingTypeContainer.style.display = 'block';
  } else {
    bindingTypeContainer.style.display = 'none';
    document.getElementById('binding_type').selectedIndex = 0;
    otherBindingTypeCtnr.style.display = 'none';
    document.getElementById('other_binding_type').value = '';
  }
});

// For Cut Paper option
const cutpaperCheckbox   = document.getElementById('option_cutpaper');
const cutPaperContainer  = document.getElementById('cut_paper_container');

cutpaperCheckbox.addEventListener('change', function() {
  if (this.checked) {
    cutPaperContainer.style.display = 'block';
  } else {
    cutPaperContainer.style.display = 'none';
    // Uncheck all cut_paper radios
    document
      .querySelectorAll('input[name="cut_paper"]')
      .forEach(radio => radio.checked = false);
  }
});


// Function to calculate and display the estimated cost.
function calculateEstimate() {
  // grab values
  const pages       = parseFloat(document.getElementById('pages_in_original').value) || 0;
  const sets        = parseFloat(document.getElementById('number_of_sets').value)    || 0;
  const printCopies = document.getElementById('print_copies_in').value;
  const pageType    = document.getElementById('page_type').value;
  const paperSize   = document.getElementById('paper_size_select').value;
  const layout      = document.getElementById('page_layout').value;
  // need at least pages & sets
  if (pages <= 0 || sets <= 0) {
    document.getElementById('estimated_cost').value = '0';
    return document.getElementById('estimatedCost').style.display = 'none';
  }
  let baseCost = 0;
  let isPerSet = false; // Flag for NCR forms which are priced per set, not per sheet
  // Handle NCR Forms first (priced per set)
  if (pageType === 'NCR 2-Part') {
    baseCost = 0.75;
    isPerSet = true;
  } else if (pageType === 'NCR 3-Part') {
    baseCost = 0.90;
    isPerSet = true;
  } else if (printCopies === 'Black & White') {
    // Black & White pricing
    if (pageType === 'Card Stock') {
      baseCost = 0.25; // Cardstock 65lb - all sizes same price for B&W
    } else if (paperSize.includes('Ledger')) {
      baseCost = 0.10; // Ledger (White)
    } else {
      baseCost = 0.05; // Standard (White, Blue, etc.) and Legal
    }
  } else if (printCopies === 'Color') {
    // Color pricing - more complex based on paper type AND size
    if (pageType === 'Card Stock') {
      if (paperSize.includes('12"x18"') || paperSize.includes('13"x19"')) {
        baseCost = 0.60; // Cardstock 12x18 or 13x19
      } else if (paperSize.includes('Ledger: 11"x17"')) {
        baseCost = 0.50; // Cardstock Ledger
      } else {
        baseCost = 0.25; // Cardstock Letter/Legal
      }
    } else {
      // Standard paper for color
      if (paperSize.includes('Ledger: 11"x17"')) {
        baseCost = 0.50; // Color Ledger
      } else {
        baseCost = 0.25; // Color Letter/Legal
      }
    }
  } else {
    // Unknown print type
    document.getElementById('estimated_cost').value = '0';
    return document.getElementById('estimatedCost').style.display = 'none';
  }
  // Calculate core cost
  let coreCost;
  if (isPerSet) {
    // NCR forms are priced per set
    coreCost = baseCost * sets;
  } else {
    // Regular prints
        coreCost = pages * sets * baseCost;
  }
  // Add-on services
  let addon = 0;
  if (document.getElementById('option_tabs').checked)     addon += 1.00 * Math.ceil(sets/5); // Tabs @ $1/set of 5
  if (document.getElementById('option_binding').checked)  addon += 1.00 * sets; // Binding @ $1/job
  const total = coreCost + addon;
  if (total > 0) {
    const el = document.getElementById('estimatedCost');
    el.innerHTML = `<strong>Estimated Cost: $${total.toFixed(2)}</strong><br>
                    <small>Pricing breakdown: ${isPerSet ? 'Per set' : 'Per impression'} $${baseCost.toFixed(2)} × ${isPerSet ? sets : (pages + ' pages × ' + sets + ' sets')} + add-ons $${addon.toFixed(2)}</small><br>
                    <small>To get exact cost, please email printing@riohondo.edu.</small>`;
    el.style.display = 'block';
    
    // Update the hidden field with the calculated cost
    document.getElementById('estimated_cost').value = total.toFixed(2);
    
  } else {
    document.getElementById('estimatedCost').style.display = 'none';
    document.getElementById('estimated_cost').value = '0';
  }
}

// wire it up
[
  'pages_in_original',
  'number_of_sets',
  'print_copies_in',
  'page_type',
  'paper_size_select',
  'page_layout',
  'option_tabs',
  'option_binding'
].forEach(id => {
  const el = document.getElementById(id);
  if (el) el.addEventListener(el.tagName === 'SELECT' || el.type === 'checkbox' ? 'change' : 'input', calculateEstimate);
});


</script>

<?php require_once 'footer.php'; ?>
