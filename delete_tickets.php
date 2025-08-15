<?php
// delete_tickets.php
// Permanently remove a ticket and any files it references.

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}
// Only Super Admins may access
if (
    empty($_SESSION['logged_in'])
    || $_SESSION['logged_in'] !== true
    || ($_SESSION['role'] ?? '') !== 'Super Admin'
) {
    header("Location: login.php");
    exit;
}

require_once 'header.php';
require_once __DIR__ . '/assets/database.php';

$message = '';
// Handle deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['delete_ticket'])) {
    $ticketId = (int)$_POST['delete_ticket'];

    // 1) Fetch ticket_number + file paths
    $fetch = $pdo->prepare("
      SELECT ticket_number, file_path
        FROM job_tickets
       WHERE id = :id
       LIMIT 1
    ");
    $fetch->execute([':id' => $ticketId]);
    $row = $fetch->fetch(PDO::FETCH_ASSOC);

    // 2) Delete the record
    $del = $pdo->prepare("DELETE FROM job_tickets WHERE id = :id");
    $del->execute([':id' => $ticketId]);

    if ($del->rowCount()) {
        // 3) Remove uploaded files
        if (!empty($row['file_path'])) {
            $uploadDir = __DIR__ . '/uploads/';
            foreach (explode(',', $row['file_path']) as $p) {
                $full = $uploadDir . basename(trim($p));
                if (file_exists($full)) {
                    @unlink($full);
                }
            }
        }

        // 4) Log the deletion in activity_log
        $log = $pdo->prepare("
          INSERT INTO activity_log (username, event, details)
          VALUES (:u, 'delete_ticket', :d)
        ");
        $details = "Ticket #{$row['ticket_number']}";
        $log->execute([
          ':u' => $_SESSION['username'],
          ':d' => $details,
        ]);

        $message = '<div class="alert alert-success">'
                 . 'Ticket deleted successfully.'
                 . '</div>';
    } else {
        $message = '<div class="alert alert-danger">'
                 . 'Could not delete ticket (not found?).'
                 . '</div>';
    }
}

// Handle bulk deletes
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['bulk_delete'], $_POST['ticket_ids'])) {
    $ticketIds = array_map('intval', $_POST['ticket_ids']);
    $deletedCount = 0;
    $ticketNumbers = [];

    foreach ($ticketIds as $ticketId) {
        // 1) Fetch ticket_number + file paths
        $fetch = $pdo->prepare("
          SELECT ticket_number, file_path
            FROM job_tickets
           WHERE id = :id
           LIMIT 1
        ");
        $fetch->execute([':id' => $ticketId]);
        $row = $fetch->fetch(PDO::FETCH_ASSOC);

        if (!$row) continue;

        // 2) Delete the record
        $del = $pdo->prepare("DELETE FROM job_tickets WHERE id = :id");
        $del->execute([':id' => $ticketId]);

        if ($del->rowCount()) {
            $deletedCount++;
            $ticketNumbers[] = $row['ticket_number'];
            
            // 3) Remove uploaded files
            if (!empty($row['file_path'])) {
                $uploadDir = __DIR__ . '/uploads/';
                foreach (explode(',', $row['file_path']) as $p) {
                    $full = $uploadDir . basename(trim($p));
                    if (file_exists($full)) {
                        @unlink($full);
                    }
                }
            }
        }
    }

    // 4) Log the deletion in activity_log
    if ($deletedCount > 0) {
        $log = $pdo->prepare("
          INSERT INTO activity_log (username, event, details)
          VALUES (:u, 'bulk_delete_tickets', :d)
        ");
        $details = "Deleted " . $deletedCount . " tickets: " . implode(', ', $ticketNumbers);
        $log->execute([
          ':u' => $_SESSION['username'],
          ':d' => $details,
        ]);

        $message = '<div class="alert alert-success">'
                 . 'Successfully deleted ' . $deletedCount . ' tickets.'
                 . '</div>';
    } else {
        $message = '<div class="alert alert-danger">'
                 . 'No tickets were deleted.'
                 . '</div>';
    }
}

// Get search term
$search = trim($_GET['search'] ?? '');

// Build query
$sql    = "SELECT id, ticket_number, created_at, job_title, first_name, last_name
             FROM job_tickets";
$params = [];

if ($search !== '') {
    $sql .= " WHERE ticket_number LIKE :search";
    $params[':search'] = "%{$search}%";
}

$sql .= " ORDER BY created_at DESC";

try {
    $stmt = $pdo->prepare($sql);
    $stmt->execute($params);
    $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
} catch (PDOException $e) {
    die("<div class=\"alert alert-danger\">DB Error: "
        . htmlspecialchars($e->getMessage()) . "</div>");
}
?>

<a href="settings.php" class="btn btn-secondary mb-3"><i class="bi bi-arrow-left"></i> Back to Settings</a>
<h1>Delete Tickets</h1>

<?= $message ?>

<!-- Search form -->
<form method="get" class="mb-3">
  <div class="input-group">
    <input type="text"
           name="search"
           class="form-control"
           placeholder="Search by Ticket #"
           value="<?= htmlspecialchars($search) ?>">
    <button class="btn btn-outline-primary" type="submit">Search</button>
    <?php if ($search !== ''): ?>
    <a href="delete_tickets.php" class="btn btn-outline-secondary">Clear</a>
    <?php endif; ?>
  </div>
</form>
<!-- Bulk delete form -->
<form id="bulkDeleteForm" method="POST" class="mb-3">
  <button type="button" id="bulkDeleteBtn" class="btn btn-danger mb-3" disabled>
    Delete Selected Tickets
  </button>
  <input type="hidden" name="bulk_delete" value="1">
  <div id="selectedTickets"></div>
</form>
<div class="table-responsive">
  <?php if (count($tickets)): ?>
    <table class="table table-bordered table-striped align-middle">
      <thead class="table-light">
  <tr>
    <th>
      <div class="form-check">
        <input class="form-check-input" type="checkbox" id="selectAll">
      </div>
    </th>
    <th>Ticket #</th>
    <th>Request Date</th>
    <th>Title</th>
    <th>Name</th>
    <th>Action</th>
  </tr>
</thead>
      <tbody>
        <?php foreach ($tickets as $t): ?>
<tr>
  <td>
    <div class="form-check">
      <input class="form-check-input ticket-checkbox" type="checkbox" name="ticket_ids[]" value="<?= (int)$t['id'] ?>" data-number="<?= htmlspecialchars($t['ticket_number']) ?>">
    </div>
  </td>
  <td><?= htmlspecialchars($t['ticket_number']) ?></td>
  <td><?= htmlspecialchars(toLA($t['created_at'], 'm/d/Y')) ?></td>
  <td><?= htmlspecialchars($t['job_title']) ?></td>
  <td><?= htmlspecialchars($t['first_name'] . ' ' . $t['last_name']) ?></td>
  <td>
    <form id="deleteForm-<?= $t['id'] ?>"
          method="POST"
          class="d-inline">
      <input type="hidden"
             name="delete_ticket"
             value="<?= (int)$t['id'] ?>">
    </form>
    <button type="button"
            class="btn btn-danger btn-sm"
            data-bs-toggle="modal"
            data-bs-target="#deleteConfirmModal"
            data-id="<?= $t['id'] ?>"
            data-number="<?= htmlspecialchars($t['ticket_number']) ?>">
      Delete
    </button>
  </td>
</tr>
<?php endforeach; ?>
      </tbody>
    </table>
  <?php else: ?>
    <p class="text-muted">
      No tickets found<?= $search!=='' ? " for “".htmlspecialchars($search)."”" : '' ?>.
    </p>
  <?php endif; ?>
</div>

<!-- Delete Confirmation Modal -->
<div class="modal fade" id="deleteConfirmModal" tabindex="-1" aria-labelledby="deleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="deleteConfirmLabel">Confirm Delete</h5>
        <button type="button" class="btn-close"
                data-bs-dismiss="modal"
                aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete ticket
        <strong id="modalTicketNumber"></strong>?
      </div>
      <div class="modal-footer">
        <button type="button"
                class="btn btn-secondary"
                data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="button"
                class="btn btn-danger"
                id="modalDeleteButton">
          Delete
        </button>
      </div>
    </div>
  </div>
</div>
<!-- Bulk Delete Confirmation Modal -->
<div class="modal fade" id="bulkDeleteConfirmModal" tabindex="-1" aria-labelledby="bulkDeleteConfirmLabel" aria-hidden="true">
  <div class="modal-dialog">
    <div class="modal-content">
      <div class="modal-header">
        <h5 class="modal-title" id="bulkDeleteConfirmLabel">Confirm Bulk Delete</h5>
        <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
      </div>
      <div class="modal-body">
        Are you sure you want to delete <span id="ticketCount">0</span> tickets?
        <div id="ticketsList" class="mt-3"></div>
      </div>
      <div class="modal-footer">
        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">
          Cancel
        </button>
        <button type="button" class="btn btn-danger" id="confirmBulkDeleteBtn">
          Delete All Selected
        </button>
      </div>
    </div>
  </div>
</div>
<script>
document.addEventListener('DOMContentLoaded', function(){
  var deleteModal      = document.getElementById('deleteConfirmModal');
  var ticketNumberEl   = deleteModal.querySelector('#modalTicketNumber');
  var confirmBtn       = deleteModal.querySelector('#modalDeleteButton');

  deleteModal.addEventListener('show.bs.modal', function(event) {
    var btn           = event.relatedTarget;
    var ticketId      = btn.getAttribute('data-id');
    var ticketNumber  = btn.getAttribute('data-number');

    ticketNumberEl.textContent = ticketNumber;
    confirmBtn.onclick = function() {
      document.getElementById('deleteForm-' + ticketId).submit();
    };
  });
});

// Bulk delete functionality
document.addEventListener('DOMContentLoaded', function() {
  const selectAll = document.getElementById('selectAll');
  const ticketCheckboxes = document.querySelectorAll('.ticket-checkbox');
  const bulkDeleteBtn = document.getElementById('bulkDeleteBtn');
  const bulkDeleteForm = document.getElementById('bulkDeleteForm');
  const selectedTicketsContainer = document.getElementById('selectedTickets');
  const ticketCount = document.getElementById('ticketCount');
  const ticketsList = document.getElementById('ticketsList');
  const bulkDeleteModal = new bootstrap.Modal(document.getElementById('bulkDeleteConfirmModal'));
  
  // Function to update the bulk delete button state
  function updateBulkDeleteButton() {
    const checkedCount = document.querySelectorAll('.ticket-checkbox:checked').length;
    bulkDeleteBtn.disabled = checkedCount === 0;
  }
  
  // Select/Deselect all tickets
  if (selectAll) {
    selectAll.addEventListener('change', function() {
      ticketCheckboxes.forEach(checkbox => {
        checkbox.checked = selectAll.checked;
      });
      updateBulkDeleteButton();
    });
  }
  
  // Update the bulk delete button when individual checkboxes change
  ticketCheckboxes.forEach(checkbox => {
    checkbox.addEventListener('change', function() {
      updateBulkDeleteButton();
      
      // Update "Select All" checkbox
      if (!this.checked) {
        selectAll.checked = false;
      } else {
        // Check if all checkboxes are checked
        const allChecked = Array.from(ticketCheckboxes).every(c => c.checked);
        selectAll.checked = allChecked;
      }
    });
  });
  
  // Show the bulk delete confirmation modal
  bulkDeleteBtn.addEventListener('click', function() {
    const checkedBoxes = document.querySelectorAll('.ticket-checkbox:checked');
    const ticketIds = [];
    const ticketNumbers = [];
    
    checkedBoxes.forEach(box => {
      ticketIds.push(box.value);
      ticketNumbers.push(box.getAttribute('data-number'));
    });
    
    // Update the modal content
    ticketCount.textContent = ticketIds.length;
    ticketsList.innerHTML = '<ul>' + 
      ticketNumbers.map(num => `<li>Ticket #${num}</li>`).join('') + 
      '</ul>';
    
    // Show the modal
    bulkDeleteModal.show();
  });
  
  // Handle form submission on confirmation
  document.getElementById('confirmBulkDeleteBtn').addEventListener('click', function() {
    // Create hidden inputs for each selected ticket
    selectedTicketsContainer.innerHTML = '';
    const checkedBoxes = document.querySelectorAll('.ticket-checkbox:checked');
    
    checkedBoxes.forEach(box => {
      const input = document.createElement('input');
      input.type = 'hidden';
      input.name = 'ticket_ids[]';
      input.value = box.value;
      selectedTicketsContainer.appendChild(input);
    });
    
    // Submit the form
    bulkDeleteForm.submit();
  });
});
</script>

<?php require_once 'footer.php'; ?>
