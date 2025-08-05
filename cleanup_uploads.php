<?php
// cleanup_uploads.php

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
$stats = null;
$tickets = [];
$fromDate = '';
$toDate = '';
$totalSize = 0;
$fileCount = 0;

// Handle form submission for preview
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'preview') {
    $fromDate = trim($_POST['from_date'] ?? '');
    $toDate = trim($_POST['to_date'] ?? '');
    
    if (empty($fromDate) || empty($toDate)) {
        $message = '<div class="alert alert-danger">Please select both from and to dates.</div>';
    } else {
        // Get tickets with uploads in the date range
        $sql = "
            SELECT id, ticket_number, created_at, file_path, job_title
            FROM job_tickets 
            WHERE created_at BETWEEN :from_date AND :to_date
            AND file_path IS NOT NULL 
            AND file_path != ''
            ORDER BY created_at DESC
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':from_date' => $fromDate . ' 00:00:00',
            ':to_date' => $toDate . ' 23:59:59'
        ]);
        
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        
        // Calculate total size and file count
        $uploadDir = __DIR__ . '/uploads/';
        foreach ($tickets as &$ticket) {
            $ticket['files'] = [];
            $ticket['total_size'] = 0;
            
            $filePaths = explode(',', $ticket['file_path']);
            foreach ($filePaths as $path) {
                $path = trim($path);
                if (empty($path)) continue;
                
                $fullPath = $uploadDir . basename($path);
                if (file_exists($fullPath)) {
                    $size = filesize($fullPath);
                    $ticket['total_size'] += $size;
                    $totalSize += $size;
                    $fileCount++;
                    
                    $ticket['files'][] = [
                        'path' => $path,
                        'name' => basename($path),
                        'size' => $size
                    ];
                }
            }
            
            // Format size for display
            $ticket['size_formatted'] = formatFileSize($ticket['total_size']);
        }
        
        // Get summary statistics
        $stats = [
            'ticket_count' => count($tickets),
            'file_count' => $fileCount,
            'total_size' => $totalSize,
            'total_size_formatted' => formatFileSize($totalSize)
        ];
        
        if (count($tickets) === 0) {
            $message = '<div class="alert alert-info">No tickets with uploads found in the selected date range.</div>';
        }
    }
}

// Handle actual cleanup
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'cleanup') {
    $fromDate = trim($_POST['from_date'] ?? '');
    $toDate = trim($_POST['to_date'] ?? '');
    $confirm = isset($_POST['confirm_cleanup']) && $_POST['confirm_cleanup'] === '1';
    
    if (!$confirm) {
        $message = '<div class="alert alert-danger">Please confirm the cleanup operation by checking the confirmation box.</div>';
    } elseif (empty($fromDate) || empty($toDate)) {
        $message = '<div class="alert alert-danger">Please select both from and to dates.</div>';
    } else {
        // Get tickets with uploads in the date range
        $sql = "
            SELECT id, ticket_number, file_path
            FROM job_tickets 
            WHERE created_at BETWEEN :from_date AND :to_date
            AND file_path IS NOT NULL 
            AND file_path != ''
        ";
        
        $stmt = $pdo->prepare($sql);
        $stmt->execute([
            ':from_date' => $fromDate . ' 00:00:00',
            ':to_date' => $toDate . ' 23:59:59'
        ]);
        
        $tickets = $stmt->fetchAll(PDO::FETCH_ASSOC);
        $deletedFiles = 0;
        $freedSpace = 0;
        $uploadDir = __DIR__ . '/uploads/';
        $processedTickets = 0;
        
        // Begin transaction
        $pdo->beginTransaction();
        
        try {
            foreach ($tickets as $ticket) {
                $filePaths = explode(',', $ticket['file_path']);
                $deletedForTicket = [];
                
                foreach ($filePaths as $path) {
                    $path = trim($path);
                    if (empty($path)) continue;
                    
                    $fullPath = $uploadDir . basename($path);
                    if (file_exists($fullPath)) {
                        $size = filesize($fullPath);
                        if (unlink($fullPath)) {
                            $deletedFiles++;
                            $freedSpace += $size;
                            $deletedForTicket[] = $path;
                        }
                    }
                }
                
                // Update the ticket to remove file paths
                if (!empty($deletedForTicket)) {
                    $updateSql = "UPDATE job_tickets SET file_path = '' WHERE id = :id";
                    $updateStmt = $pdo->prepare($updateSql);
                    $updateStmt->execute([':id' => $ticket['id']]);
                    $processedTickets++;
                }
            }
            
            // Log the cleanup action
            $logSql = "
                INSERT INTO activity_log (username, event, details)
                VALUES (:username, 'cleanup_uploads', :details)
            ";
            $logStmt = $pdo->prepare($logSql);
            $logStmt->execute([
                ':username' => $_SESSION['username'],
                ':details' => "Cleaned up {$deletedFiles} files from {$processedTickets} tickets between {$fromDate} and {$toDate}, freed " . formatFileSize($freedSpace)
            ]);
            
            // Commit the transaction
            $pdo->commit();
            
            $message = '<div class="alert alert-success">
                Successfully removed ' . $deletedFiles . ' files from ' . $processedTickets . ' tickets, freeing up ' . formatFileSize($freedSpace) . ' of storage space.
            </div>';
        } catch (Exception $e) {
            // Rollback the transaction on error
            $pdo->rollBack();
            $message = '<div class="alert alert-danger">Error: ' . htmlspecialchars($e->getMessage()) . '</div>';
        }
    }
}

// Helper function to format file size
function formatFileSize($bytes) {
    $units = ['B', 'KB', 'MB', 'GB', 'TB'];
    $i = 0;
    while ($bytes >= 1024 && $i < count($units) - 1) {
        $bytes /= 1024;
        $i++;
    }
    return round($bytes, 2) . ' ' . $units[$i];
}
?>

<h1>Semester Cleanup - Manage Uploads</h1>

<?= $message ?>

<div class="card mb-4">
    <div class="card-header">Select Date Range</div>
    <div class="card-body">
        <form method="POST" class="row g-3">
            <div class="col-md-4">
                <label for="from_date" class="form-label">From Date:</label>
                <input type="date" id="from_date" name="from_date" class="form-control" value="<?= htmlspecialchars($fromDate) ?>" required>
            </div>
            <div class="col-md-4">
                <label for="to_date" class="form-label">To Date:</label>
                <input type="date" id="to_date" name="to_date" class="form-control" value="<?= htmlspecialchars($toDate) ?>" required>
            </div>
            <div class="col-md-4 d-flex align-items-end">
                <input type="hidden" name="action" value="preview">
                <button type="submit" class="btn btn-primary">Preview Cleanup</button>
            </div>
        </form>
    </div>
</div>

<?php if ($stats): ?>
<div class="card mb-4">
    <div class="card-header">Cleanup Summary</div>
    <div class="card-body">
        <p>Tickets Found: <strong><?= $stats['ticket_count'] ?></strong></p>
        <p>Total files: <strong><?= $stats['file_count'] ?></strong></p>
        <p>Total storage space: <strong><?= $stats['total_size_formatted'] ?></strong></p>
        
        <form method="POST" class="mt-4">
            <input type="hidden" name="from_date" value="<?= htmlspecialchars($fromDate) ?>">
            <input type="hidden" name="to_date" value="<?= htmlspecialchars($toDate) ?>">
            <input type="hidden" name="action" value="cleanup">
            
            <div class="form-check mb-3">
                <input class="form-check-input" type="checkbox" name="confirm_cleanup" id="confirm_cleanup" value="1">
                <label class="form-check-label" for="confirm_cleanup">
                    I understand that this will permanently delete all file uploads from the selected period while preserving the ticket records. This action cannot be undone.
                </label>
            </div>
            
            <button type="submit" class="btn btn-danger" id="cleanupBtn" disabled>
                Perform Cleanup
            </button>
        </form>
    </div>
</div>

<div class="card">
    <div class="card-header">Tickets with Uploads (<?= count($tickets) ?>)</div>
    <div class="card-body">
        <div class="table-responsive">
            <table class="table table-striped table-bordered align-middle">
                <thead class="table-light">
                    <tr>
                        <th>Ticket #</th>
                        <th>Created</th>
                        <th>Title</th>
                        <th>Files</th>
                        <th>Size</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($tickets as $ticket): ?>
                    <tr>
                        <td><?= htmlspecialchars($ticket['ticket_number']) ?></td>
                        <td><?= htmlspecialchars(toLA($ticket['created_at'], 'm/d/Y')) ?></td>
                        <td><?= htmlspecialchars($ticket['job_title']) ?></td>
                        <td>
                            <ul class="list-unstyled mb-0">
                                <?php foreach ($ticket['files'] as $file): ?>
                                <li>
                                    <small>
                                        <a href="<?= htmlspecialchars($file['path']) ?>" target="_blank">
                                            <?= htmlspecialchars($file['name']) ?>
                                        </a>
                                        (<?= formatFileSize($file['size']) ?>)
                                    </small>
                                </li>
                                <?php endforeach; ?>
                            </ul>
                        </td>
                        <td><?= $ticket['size_formatted'] ?></td>
                    </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', function() {
    const confirmCheckbox = document.getElementById('confirm_cleanup');
    const cleanupBtn = document.getElementById('cleanupBtn');
    
    if (confirmCheckbox && cleanupBtn) {
        confirmCheckbox.addEventListener('change', function() {
            cleanupBtn.disabled = !this.checked;
        });
    }
});
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>