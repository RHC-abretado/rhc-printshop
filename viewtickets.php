<?php
// 1) Start the session and check if logged_in is true
if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
if (!isset($_SESSION['logged_in']) || $_SESSION['logged_in'] !== true) {
    header('Location: login.php');
    exit;
}

// 2) If logged in, require the header (which also gives you $pdo)
require_once 'header.php';

// 3) Fetch tickets with role-based filtering
try {
    if (in_array($_SESSION['role'], ['Manager','Super Admin'], true)) {
        // Managers & Super Admins see everything
        $sql  = "SELECT * FROM job_tickets ORDER BY created_at DESC";
        $stmt = $pdo->query($sql);
    } else {
        // Plain Admins see only tickets assigned to themselves
        $sql  = "SELECT * 
                   FROM job_tickets 
                  WHERE assigned_to = :user 
                  ORDER BY created_at DESC";
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
          ':user' => $_SESSION['username']
        ]);
    }
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("Database error: " . htmlspecialchars($e->getMessage()));
}
?>


<h1>View Tickets</h1>


<div class="card">
  <div class="card-header">Tickets List</div>
  <div class="card-body">
    <?php if (!empty($tickets)) : ?>
      <div class="row mb-3">
  <div class="col-auto">
    <label for="statusFilter" class="form-label">Filter by Status:</label>
    <select id="statusFilter" class="form-select">
      <option value="All" selected>All</option>
      <option value="New">New</option>
      <option value="Processing">Processing</option>
      <option value="Hold">Hold</option>
      <option value="Complete">Complete</option>
      <option value="Canceled">Canceled</option>
    </select>
  </div>
  <div class="col-auto">
    <label for="searchFilter" class="form-label">Search Name/Location:</label>
    <input type="text" id="searchFilter" class="form-control" placeholder="Search by name or location...">
  </div>
  <div class="col-auto d-flex align-items-end">
    <button type="button" id="clearFilters" class="btn btn-outline-secondary">Clear</button>
  </div>
</div>

      <div class="table-responsive">
        <table id="ticketsTable" class="table table-bordered table-striped align-middle">
          <thead class="table-light">
            <tr>
              <!-- Rename to "Ticket #" and display the `ticket_number` -->
              <th>Ticket #</th>
              <th>Status</th>
              <th>Request Date</th>
              <th>Due Date</th>
              <th>Title</th>
              <th>Name</th>
              <th>Notes</th>
              <th>Assigned To</th>
              <th>View/Print</th>
            </tr>
          </thead>
          <tbody>
            <?php foreach ($tickets as $row): 
    $numericId  = $row['id'];  
    $ticketNum  = $row['ticket_number'] ?? ''; 
    $status     = $row['ticket_status'] ?? 'New';

    // copy into rowData for our modal payload
    $rowData = $row;

    // format those two timestamps on rowData, not $row
    $rowData['created_at']         = toLA($row['created_at'], 'm-d-Y H:i:s');


$rowData['created_at_display'] = toLA($row['created_at'], 'm-d-Y H:i:s');
$rowData['date_wanted']        = date('m-d-Y', strtotime($row['date_wanted']));
$rowData['date_wanted_display']= date('m-d-Y', strtotime($row['date_wanted']));


if (!empty($row['completed_at'])) {
    $rowData['completed_at_display'] = toLA($row['completed_at'], 'm-d-Y H:i:s');

    // keep raw *LA-time* strings so JS parses the right value
    $rowData['created_at_raw']   = toLA($row['created_at'], 'Y-m-d H:i:s');
    $rowData['completed_at_raw'] = toLA($row['completed_at'], 'Y-m-d H:i:s');
}

    // now encode the formatted rowData
    $ticketJson = htmlspecialchars(json_encode($rowData), ENT_QUOTES, 'UTF-8');

    // build your snippet as before...
    $notesSnippet = '';
    if (!empty($row['admin_notes'])) {
      $fullNotes = $row['admin_notes'];
      $notesSnippet = (strlen($fullNotes) > 50) 
                     ? substr($fullNotes, 0, 50) . '...' 
                     : $fullNotes;
    }
?>
<tr data-id="<?= $numericId ?>" data-status="<?= htmlspecialchars($status) ?>" 
    data-ticket="<?= $ticketJson ?>">
              <td><?= htmlspecialchars($ticketNum) ?></td>
              <td><?= htmlspecialchars($status) ?></td>
              <td><?= htmlspecialchars(toLA($row['created_at'], 'm/d/Y')) ?></td>
<td><?= htmlspecialchars(date('m/d/Y', strtotime($row['date_wanted']))) ?></td>

              <td><?= htmlspecialchars($row['job_title']) ?></td>
              <td><?= htmlspecialchars($row['first_name'] . ' ' . $row['last_name']) ?></td>
              <td><?= htmlspecialchars($notesSnippet) ?></td>
              <td><?= htmlspecialchars($row['assigned_to'] ?? '') ?></td>
              <td>
                <div class="btn-group" role="group" aria-label="View and Print">
                  <button type="button" class="btn btn-sm btn-info" 
                          data-bs-toggle="modal" 
                          data-bs-target="#ticketModal" 
                          data-ticket="<?= $ticketJson ?>">
                    View
                  </button>
                  <a href="print_preview.php?ticket=<?= urlencode($ticketNum) ?>" 
                     target="_blank" 
                     class="btn btn-sm btn-secondary">
                    Print
                  </a>
                </div>
              </td>
            </tr>
            <?php endforeach; ?>
          </tbody>
        </table>
        <nav>
          <ul class="pagination" id="paginationControls"></ul>
        </nav>
      </div>
    <?php else : ?>
      <p>No tickets found.</p>
    <?php endif; ?>
  </div>
</div>

<!-- TICKET MODAL -->
<div class="modal fade" id="ticketModal" tabindex="-1" aria-labelledby="ticketModalLabel" aria-hidden="true">
  <div class="modal-dialog modal-lg">
    <div class="modal-content">
      <!-- Modal Header -->
      <div class="modal-header">
        <h5 class="modal-title" id="ticketModalLabel">Ticket Details</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <!-- Modal Body -->
      <div class="modal-body">
        <table class="table table-bordered" id="ticketDetailsTable"><tbody></tbody></table>
        <div class="mb-3">
  <label for="totalCostInput" class="form-label"><strong>Total Cost ($)</strong></label>
  <div class="input-group">
    <span class="input-group-text">$</span>
    <input type="number" 
           id="totalCostInput" 
           class="form-control" 
           step="0.01" 
           min="0" 
           pattern="^\d+(\.\d{1,2})?$" 
           placeholder="0.00"
           onchange="formatCurrency(this)">
  </div>
</div>
        <div class="mb-3">
          <label for="adminNotesTextarea" class="form-label"><strong>Notes</strong></label>
          <textarea id="adminNotesTextarea" class="form-control" rows="4"></textarea>
        </div>
      </div>
      <!-- Modal Footer -->
      <div class="modal-footer">
          <button type="button" class="btn btn-primary" id="saveCostBtn">Save Cost</button>
<button type="button" class="btn btn-primary" id="saveNotesBtn">Save Notes</button>
<div class="btn-group" role="group">
  <button type="button" class="btn btn-outline-secondary dropdown-toggle" data-bs-toggle="dropdown" aria-expanded="false" id="statusDropdown">
    Update Status
  </button>
  <ul class="dropdown-menu">
    <li><a class="dropdown-item" href="#" data-status="Processing">Processing</a></li>
    <li><a class="dropdown-item" href="#" data-status="Complete" id="completeOption">Complete</a></li>
    <li><a class="dropdown-item" href="#" data-status="Hold">Hold</a></li>
    <li><hr class="dropdown-divider"></li>
    <li><a class="dropdown-item text-danger" href="#" data-status="Canceled">Cancel Ticket</a></li>
  </ul>
</div>
        <?php if (isset($_SESSION['logged_in']) && $_SESSION['logged_in'] === true 
       && in_array($_SESSION['role'], ['Manager', 'Super Admin'])): ?> | 
         <!-- Assign To button appears only if logged in as Manager or Super Admin -->
  <button type="button" class="btn btn-secondary" id="btnAssignTo">Assign Ticket</button>
  
  <!-- Hidden container for assignment: shows a dropdown list and confirmation button -->
  <div id="assignContainer" style="display:none; margin-top: 10px;">
    <label for="assigneeSelect" class="form-label">Assign ticket to:</label>
    <select id="assigneeSelect" class="form-select">
      <?php
      // Query accounts with role Admin or Manager (excluding Super Admins)
      require_once 'assets/database.php';
      $stmt = $pdo->prepare("SELECT username FROM users WHERE role IN ('Admin','Manager')");
      $stmt->execute();
      while($row = $stmt->fetch(PDO::FETCH_ASSOC)){
          echo '<option value="' . htmlspecialchars($row['username']) . '">' . htmlspecialchars($row['username']) . '</option>';
      }
      ?>
    </select>
    <button type="button" class="btn btn-primary mt-2" id="btnConfirmAssign">Confirm Assignment</button>
  </div>
<?php endif; ?>
       </div>
       
    </div>
  </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
  // --- 1) State & Elements ---
  let sortDir = 0; // 0 = off, 1 = asc, -1 = desc
  const dueHeader    = document.querySelector('#ticketsTable thead th:nth-child(4)');
  const statusFilter = document.getElementById('statusFilter');
  const tbody        = document.querySelector('#ticketsTable tbody');
  const pagination   = document.getElementById('paginationControls');
  const allRows      = Array.from(tbody.querySelectorAll('tr'));
  const pageSize     = 30;

  // --- 2) Setup the Due Date header toggle ---
  if (dueHeader) {
    dueHeader.style.cursor = 'pointer';
    const updateArrow = () => {
      if (sortDir === 0)       dueHeader.textContent = 'Due Date';
      else if (sortDir === 1)  dueHeader.textContent = 'Due Date ↑';
      else                     dueHeader.textContent = 'Due Date ↓';
    };
    // On click: if off turn on asc; otherwise flip
    dueHeader.addEventListener('click', () => {
      sortDir = sortDir === 0 ? 1 : -sortDir;
      updateArrow();
      applyFilterAndPaginate();
    });
    updateArrow();
  }

  // --- 3) Core: filter, sort, paginate, render ---
  function parseDateCell(cell) {
    // expects MM/DD/YYYY
    const [m, d, y] = cell.innerText.split('/');
    return new Date(y, m - 1, d);
  }

  function renderPage(rows, page) {
    // clear existing rows
    tbody.innerHTML = '';
    // show only the slice for this page
    const start = (page - 1) * pageSize;
    rows.slice(start, start + pageSize).forEach(r => tbody.appendChild(r));
    // build pagination controls
    const totalPages = Math.max(1, Math.ceil(rows.length / pageSize));
    pagination.innerHTML = '';
    for (let p = 1; p <= totalPages; p++) {
      const li = document.createElement('li');
      li.className = 'page-item' + (p === page ? ' active' : '');
      const a = document.createElement('a');
      a.className = 'page-link';
      a.href = '#';
      a.textContent = p;
      a.addEventListener('click', ev => {
        ev.preventDefault();
        renderPage(rows, p);
      });
      li.appendChild(a);
      pagination.appendChild(li);
    }
  }

function applyFilterAndPaginate() {
  // 1) filter by status
  const sel = statusFilter.value;
  const searchTerm = document.getElementById('searchFilter').value.toLowerCase().trim();
  
  let filtered = allRows.filter(r => {
    // Status filter
    const statusMatch = sel === 'All' || r.getAttribute('data-status') === sel;
    
    // Search filter - if no search term, return true
    if (!searchTerm) return statusMatch;
    
    // Get the text content from relevant columns
    const name = (r.children[5].textContent || '').toLowerCase(); // Name column
    const locationCode = (r.children[7].textContent || '').toLowerCase(); // Assigned To column
    
    // Also search in the JSON data for department_name and location_code
    try {
      const ticketData = JSON.parse(r.getAttribute('data-ticket'));
      const departmentName = (ticketData.department_name || '').toLowerCase();
      const actualLocationCode = (ticketData.location_code || '').toLowerCase();
      
      const searchMatch = name.includes(searchTerm) || 
                         locationCode.includes(searchTerm) ||
                         departmentName.includes(searchTerm) ||
                         actualLocationCode.includes(searchTerm);
      
      return statusMatch && searchMatch;
    } catch (e) {
      // Fallback if JSON parsing fails
      const searchMatch = name.includes(searchTerm) || locationCode.includes(searchTerm);
      return statusMatch && searchMatch;
    }
  });
  
  // 2) sort by Due Date cell (4th <td>)
  filtered.sort((a, b) => {
    const da = parseDateCell(a.children[3]);
    const db = parseDateCell(b.children[3]);
    return sortDir * (da - db);
  });
  // 3) render page #1
  renderPage(filtered, 1);
}

statusFilter.addEventListener('change', applyFilterAndPaginate);
document.getElementById('searchFilter').addEventListener('input', applyFilterAndPaginate);
document.getElementById('clearFilters').addEventListener('click', () => {
  statusFilter.value = 'All';
  document.getElementById('searchFilter').value = '';
  applyFilterAndPaginate();
});
applyFilterAndPaginate();;

  // --- 4) Modal / details logic (unchanged) ---
  let currentTicketId = null;
  const ticketModalEl = document.getElementById('ticketModal');
  const displayNames = {
    ticket_number:"Ticket #", ticket_status:"Status",
    created_at:"Request Date", date_wanted:"Due Date",
    first_name:"First Name", last_name:"Last Name",
    department_name:"Department", email:"Email",
    phone:"Phone", location_code:"Location",
    other_location_code:"Other Location", delivery_method:"Delivery",
    job_title:"Title", description:"Instructions/Notes",
    pages_in_original:"Pages", number_of_sets:"Sets",
    page_layout:"Layout", print_copies_in:"Print In",
    other_print_copies:"Other Copies", page_type:"Page Type",
    other_page_type:"Other Type", paper_color:"Color",
    other_paper_color:"Other Color", color_requirement:"Color Req",
    paper_size:"Size", other_paper_size:"Other Size",
    other_options:"Options", cut_paper:"Cut Paper",
    separator_color:"Separator", staple_location:"Staple",
    fold_type:"Fold", binding_type:"Binding",
    file_path:"File", assigned_to:"Assigned To", admin_notes:"Notes", total_cost:"Total Cost"
  };
  const keysOrder = [
    "ticket_number","ticket_status","first_name","last_name","department_name",
    "email","phone","location_code","other_location_code","date_wanted",
    "delivery_method","job_title","description","pages_in_original",
    "number_of_sets","page_layout","print_copies_in","other_print_copies",
    "page_type","other_page_type","paper_color","other_paper_color",
    "color_requirement","paper_size","other_paper_size","other_options",
    "cut_paper","separator_color","staple_location","fold_type","binding_type",
    "created_at","file_path","assigned_to","admin_notes","total_cost"
  ];
  
    function parseLocalDatetime(dtstr) {
    const [datePart, timePart] = dtstr.split(' ');
    const [year, month, day]   = datePart.split('-').map(n => +n);
    const [hour, min, sec]     = timePart.split(':').map(n => +n);
    return new Date(year, month - 1, day, hour, min, sec);
  }
  
  ticketModalEl.addEventListener('show.bs.modal', ev => {
    const btn  = ev.relatedTarget;
    const data = JSON.parse(btn.getAttribute('data-ticket'));
    currentTicketId = data.id;
    
    fetch('activity.php', {
      method: 'POST',
      headers: { 'Content-Type': 'application/json' },
      body: JSON.stringify({ ticket_number: data.ticket_number })
    }).catch(() => {
      // Silently fail if logging doesn't work - don't interrupt user experience
    });
    
    let html = '';
    keysOrder.forEach(k => {
      if (!data[k]) return;
      if (k === 'file_path' && data.file_path) {
  // split on commas
  const files = data.file_path.split(',');
  // map each URL to a <a> containing only its filename
  const links = files.map(fp => {
    const name = fp.substring(fp.lastIndexOf('/') + 1);
    return `<a href="${fp}" target="_blank">${name}</a>`;
  }).join('<br>');
  html += `<tr>
    <th>${displayNames[k]}</th>
    <td>${links}</td>
  </tr>`;
}
 else {
        html += `<tr><th>${displayNames[k]}</th><td>${data[k]}</td></tr>`;
      }
    });
      // --- if ticket’s been completed, show Completed On + Turnaround Time ---
  if (data.completed_at_raw) {
    // Completed On row
    html += `
      <tr>
        <th>Completed On</th>
        <td>${data.completed_at_display}</td>
      </tr>`;

    // Compute difference
    const start = parseLocalDatetime(data.created_at_raw);
      const end   = parseLocalDatetime(data.completed_at_raw);
      let delta   = end - start; // always ms ≥ 0

    // Break into days/hours/minutes
    const days    = Math.floor(delta / 86400000);
    delta %= 86400000;
    const hours   = Math.floor(delta / 3600000);
    delta %= 3600000;
    const minutes = Math.floor(delta / 60000);

    // Turnaround Time row
    html += `
      <tr>
        <th>Turnaround Time</th>
        <td>${days}d ${hours}h ${minutes}m</td>
      </tr>`;
  }


    document.querySelector('#ticketDetailsTable tbody').innerHTML = html;
    document.getElementById('totalCostInput').value = data.total_cost || '';
    document.getElementById('adminNotesTextarea').value = data.admin_notes || '';
    
    // show/hide Complete option
const completeOption = document.getElementById('completeOption');
if (completeOption) {
  completeOption.style.display = data.ticket_status.toLowerCase() === 'processing' ? '' : 'none';
}
  });

// --- 5) Status updates ---
function updateStatus(st) {
  if (!currentTicketId) return;
  fetch('update_status.php', {
    method: 'POST',
    headers: { 'Content-Type': 'application/json' },
    body: JSON.stringify({ ticket_id: currentTicketId, ticket_status: st })
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) return alert('Error: ' + d.error);

    // ← reload page if backend told us to
    if (d.refresh) {
      window.location.reload();
      return;
    }
    
    updateTicketBadges(); // Refresh badge count

    // otherwise do your normal UI update
    bootstrap.Modal.getInstance(ticketModalEl).hide();
    const row = document.querySelector(`tr[data-id="${currentTicketId}"]`);
    if (row) {
      row.setAttribute('data-status', st);
      row.children[1].innerText = st;
      // re-render current page to re-apply filter/sort
      applyFilterAndPaginate();
      updateTicketBadges(); // Refresh badge count
    }
  })
  .catch(() => alert('Ajax error'));
}

// Handle status dropdown clicks
document.querySelectorAll('.dropdown-item[data-status]').forEach(item => {
  item.addEventListener('click', function(e) {
    e.preventDefault();
    const status = this.getAttribute('data-status');
    updateStatus(status);
  });
});

  // --- 6) Save admin notes ---
  document.getElementById('saveNotesBtn').addEventListener('click', () => {
    if (!currentTicketId) return;
    const notes = document.getElementById('adminNotesTextarea').value.trim();
    fetch('update_notes.php', {
      method:'POST', headers:{'Content-Type':'application/json'},
      body: JSON.stringify({ticket_id:currentTicketId,admin_notes:notes})
    })
    .then(r=>r.json()).then(d=>{
      if (!d.success) return alert('Error: '+d.error);
      const row = document.querySelector(`tr[data-id="${currentTicketId}"]`);
      if (row) {
        row.children[6].textContent = notes.length>50 ? notes.slice(0,50)+'…' : notes;
      }
      alert('Notes saved');
    })
    .catch(()=>alert('Ajax error'));
  });
  
  // --- 8) Total Cost handling ---
document.getElementById('saveCostBtn').addEventListener('click', function() {
  if (!currentTicketId) {
    console.error('No ticket ID available');
    alert('Error: No ticket selected');
    return;
  }
  
  const cost = parseFloat(document.getElementById('totalCostInput').value) || 0;
  
  fetch('update_cost.php', {
    method: 'POST',
    headers: {'Content-Type': 'application/json'},
    body: JSON.stringify({ticket_id: currentTicketId, total_cost: cost})
  })
  .then(r => r.json())
  .then(d => {
    if (!d.success) return alert('Error: ' + d.error);
    alert('Cost updated successfully');
  })
  .catch(err => {
    console.error('Ajax error:', err);
    alert('Ajax error');
  });
});

  // --- 7) Assignment logic ---
  const btnA = document.getElementById('btnAssignTo');
  if (btnA) {
    btnA.addEventListener('click', () => {
      const c = document.getElementById('assignContainer');
      c.style.display = c.style.display==='block' ? 'none' : 'block';
    });
    document.getElementById('btnConfirmAssign').addEventListener('click', () => {
      const assignee = document.getElementById('assigneeSelect').value;
      if (!currentTicketId) return alert('No ticket selected');
      fetch('assign_ticket.php', {
        method:'POST', headers:{'Content-Type':'application/json'},
        body: JSON.stringify({ ticket_id:currentTicketId, assigned_to:assignee })
      })
      .then(r=>r.json()).then(d=>{
        if (!d.success) return alert('Error: '+d.error);
        alert('Assigned to '+assignee);
        applyFilterAndPaginate();
      })
      .catch(()=>alert('Ajax error'));
    });
  }
});


// Format currency to always show two decimal places
function formatCurrency(input) {
  // Get the value as a number
  let value = parseFloat(input.value);
  
  // If it's NaN (not a number), set to 0
  if (isNaN(value)) {
    value = 0;
  }
  
  // Format to two decimal places
  input.value = value.toFixed(2);
}

</script>
<script>
// Mark tickets as viewed when page loads
document.addEventListener('DOMContentLoaded', function() {
    // Mark tickets as viewed on the server
    fetch('mark_tickets_viewed.php', { method: 'POST' })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                // Update the badges immediately
                if (typeof updateTicketBadges === 'function') {
                    updateTicketBadges();
                }
            }
        })
        .catch(error => {
            console.error('Error marking tickets as viewed:', error);
        });
});

// Enhanced mobile table scrolling
document.addEventListener('DOMContentLoaded', function() {
    const tableResponsive = document.querySelector('.table-responsive');
    if (tableResponsive && window.innerWidth <= 768) {
        let isScrolling = false;
        let startX, scrollLeft;
        
        // Add touch event listeners for better mobile scrolling
        tableResponsive.addEventListener('touchstart', function(e) {
            isScrolling = true;
            startX = e.touches[0].pageX - tableResponsive.offsetLeft;
            scrollLeft = tableResponsive.scrollLeft;
        });
        
        tableResponsive.addEventListener('touchmove', function(e) {
            if (!isScrolling) return;
            e.preventDefault();
            const x = e.touches[0].pageX - tableResponsive.offsetLeft;
            const walk = (x - startX) * 2; // Adjust scroll speed
            tableResponsive.scrollLeft = scrollLeft - walk;
        });
        
        tableResponsive.addEventListener('touchend', function() {
            isScrolling = false;
        });
        
        // Hide scroll hint after first scroll
        tableResponsive.addEventListener('scroll', function() {
            this.classList.add('scrolled');
        }, { once: true });
        
        // Add mouse events for desktop touch devices
        tableResponsive.addEventListener('mousedown', function(e) {
            isScrolling = true;
            startX = e.pageX - tableResponsive.offsetLeft;
            scrollLeft = tableResponsive.scrollLeft;
            this.style.cursor = 'grabbing';
        });
        
        document.addEventListener('mousemove', function(e) {
            if (!isScrolling) return;
            e.preventDefault();
            const x = e.pageX - tableResponsive.offsetLeft;
            const walk = (x - startX) * 1.5;
            tableResponsive.scrollLeft = scrollLeft - walk;
        });
        
        document.addEventListener('mouseup', function() {
            isScrolling = false;
            if (tableResponsive) {
                tableResponsive.style.cursor = 'grab';
            }
        });
    }
});
</script>

<?php require_once 'footer.php'; ?>
