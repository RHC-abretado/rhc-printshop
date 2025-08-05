<?php
// pricing.php – public page with admin editing capability
require_once 'header.php';

// Check if user is logged in and has permission to edit
$canEdit = !empty($_SESSION['logged_in']) && 
           in_array($_SESSION['role'], ['Manager', 'Super Admin'], true);

// Fetch pricing data and headers from database
try {
    $stmt = $pdo->query("
        SELECT category, item_name, price, id, display_order
        FROM pricing_items 
        ORDER BY category, display_order ASC, item_name ASC
    ");
    $pricingData = $stmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Fetch headers for each category
    $headerStmt = $pdo->query("
        SELECT category, header1, header2 
        FROM pricing_headers 
        ORDER BY category
    ");
    $headersData = $headerStmt->fetchAll(PDO::FETCH_ASSOC);
    
    // Create headers lookup
    $headers = [];
    foreach ($headersData as $header) {
        $headers[$header['category']] = [$header['header1'], $header['header2']];
    }
    
    // Group pricing data by category
    $categories = [];
    foreach ($pricingData as $item) {
        $categories[$item['category']][] = $item;
    }
} catch (PDOException $e) {
    $categories = [];
    $headers = [];
    error_log("Error fetching pricing data: " . $e->getMessage());
}

// helper to render a card with edit capability
function render_editable_card($title, $headers, $items, $canEdit) {
    echo '<div class="card mb-4">';
    echo '<div class="card-header d-flex justify-content-between align-items-center">';
    echo '<div class="d-flex align-items-center">';
    echo '<span>' . htmlspecialchars($title) . '</span>';
    if ($canEdit) {
        echo '<button class="btn btn-sm btn-outline-secondary ms-2" onclick="editCategory(\'' . htmlspecialchars($title, ENT_QUOTES) . '\')" title="Edit category name">';
        echo '<i class="bi bi-pencil"></i></button>';
    }
    echo '</div>';
    if ($canEdit) {
        echo '<div>';
        echo '<button class="btn btn-sm btn-success me-2" onclick="addNewItem(\'' . htmlspecialchars($title, ENT_QUOTES) . '\')">';
        echo '<i class="bi bi-plus-circle"></i> Add Item</button>';
        echo '<button class="btn btn-sm btn-danger" onclick="deleteCategory(\'' . htmlspecialchars($title, ENT_QUOTES) . '\', ' . count($items) . ')" title="Delete category">';
        echo '<i class="bi bi-trash"></i></button>';
        echo '</div>';
    }
    echo '</div>';
    echo '<div class="card-body p-0">';
    echo '<table class="table table-fixed mb-0">';
    echo '<colgroup><col style="width:' . ($canEdit ? '60%' : '70%') . '"><col style="width:' . ($canEdit ? '25%' : '30%') . '">';
    if ($canEdit) echo '<col style="width:15%">';
    echo '</colgroup>';
    echo '<thead class="table-light"><tr>';
    foreach ($headers as $h) {
        echo '<th class="position-relative">';
        echo htmlspecialchars($h);
        if ($canEdit) {
            echo '<button class="btn btn-sm btn-outline-secondary position-absolute top-0 end-0 me-1 mt-1" ';
            echo 'onclick="editHeaders(\'' . htmlspecialchars($title, ENT_QUOTES) . '\', \'' . 
                 htmlspecialchars($headers[0], ENT_QUOTES) . '\', \'' . 
                 htmlspecialchars($headers[1], ENT_QUOTES) . '\')" ';
            echo 'title="Edit column headers" style="font-size: 0.7rem; padding: 0.1rem 0.3rem;">';
            echo '<i class="bi bi-pencil" style="font-size: 0.6rem;"></i></button>';
        }
        echo '</th>';
    }
    if ($canEdit) echo '<th>Actions</th>';
    echo '</tr></thead><tbody>';
    
    foreach ($items as $item) {
        echo '<tr>';
        echo '<td>' . htmlspecialchars($item['item_name']) . '</td>';
        echo '<td>' . htmlspecialchars($item['price']) . '</td>';
        if ($canEdit) {
            echo '<td>';
            echo '<button class="btn btn-sm btn-primary me-1" onclick="editItem(' . $item['id'] . ', \'' . 
                 htmlspecialchars($item['item_name'], ENT_QUOTES) . '\', \'' . 
                 htmlspecialchars($item['price'], ENT_QUOTES) . '\', \'' . 
                 htmlspecialchars($item['category'], ENT_QUOTES) . '\')">';
            echo '<i class="bi bi-pencil"></i></button>';
            echo '<button class="btn btn-sm btn-danger" onclick="deleteItem(' . $item['id'] . ', \'' . 
                 htmlspecialchars($item['item_name'], ENT_QUOTES) . '\')">';
            echo '<i class="bi bi-trash"></i></button>';
            echo '</td>';
        }
        echo '</tr>';
    }
    echo '</tbody></table></div></div>';
}
?>

<div class="d-flex justify-content-between align-items-center mb-3">
    <h1>Printshop Pricing</h1>
    <?php if ($canEdit): ?>
    <button class="btn btn-success" onclick="addNewCategory()">
        <i class="bi bi-plus-circle"></i> Add New Category
    </button>
    <?php endif; ?>
</div>

<?php
// Note about individual reams
echo '<div class="alert alert-info mb-4">';
echo '<strong>Note:</strong> Individual paper reams can be requested in person at the Printshop (Room A116).';
echo '</div>';

// Render each category
foreach ($categories as $categoryName => $items) {
    $categoryHeaders = $headers[$categoryName] ?? ['Item', 'Price'];
    render_editable_card(
        $categoryName,
        $categoryHeaders,
        $items,
        $canEdit
    );
}
?>

<?php if ($canEdit): ?>
<!-- Edit Item Modal -->
<div class="modal fade" id="editItemModal" tabindex="-1" aria-labelledby="editItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editItemModalLabel">Edit Pricing Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editItemForm">
                <div class="modal-body">
                    <input type="hidden" id="editItemId">
                    <div class="mb-3">
                        <label for="editCategory" class="form-label">Category</label>
                        <select class="form-select" id="editCategory" required>
                            <?php foreach (array_keys($categories) as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <div class="mb-3">
                        <label for="editItemName" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="editItemName" required>
                    </div>
                    <div class="mb-3">
                        <label for="editPrice" class="form-label">Price</label>
                        <input type="text" class="form-control" id="editPrice" required placeholder="e.g., $0.05 or $1.00 (Flat fee)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Save Changes</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add Item Modal -->
<div class="modal fade" id="addItemModal" tabindex="-1" aria-labelledby="addItemModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addItemModalLabel">Add New Pricing Item</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addItemForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="addCategory" class="form-label">Category</label>
                        <select class="form-select" id="addCategory" required>
                            <?php foreach (array_keys($categories) as $cat): ?>
                            <option value="<?= htmlspecialchars($cat) ?>"><?= htmlspecialchars($cat) ?></option>
                            <?php endforeach; ?>
                            <option value="new">Create New Category...</option>
                        </select>
                    </div>
                    <div class="mb-3" id="newCategoryGroup" style="display:none;">
                        <label for="newCategoryName" class="form-label">New Category Name</label>
                        <input type="text" class="form-control" id="newCategoryName">
                    </div>
                    <div class="mb-3">
                        <label for="addItemName" class="form-label">Item Name</label>
                        <input type="text" class="form-control" id="addItemName" required>
                    </div>
                    <div class="mb-3">
                        <label for="addPrice" class="form-label">Price</label>
                        <input type="text" class="form-control" id="addPrice" required placeholder="e.g., $0.05 or $1.00 (Flat fee)">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Add Item</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Category Modal -->
<div class="modal fade" id="editCategoryModal" tabindex="-1" aria-labelledby="editCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editCategoryModalLabel">Edit Category Name</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editCategoryForm">
                <div class="modal-body">
                    <input type="hidden" id="editCategoryOldName">
                    <div class="mb-3">
                        <label for="editCategoryNewName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="editCategoryNewName" required>
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> This will update the category name for all items in this category.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Category</button>
                </div>
            </form>
        </div>
    </div>
</div>

<!-- Add New Category Modal -->
<div class="modal fade" id="addCategoryModal" tabindex="-1" aria-labelledby="addCategoryModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="addCategoryModalLabel">Add New Category</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="addCategoryForm">
                <div class="modal-body">
                    <div class="mb-3">
                        <label for="newCategoryName" class="form-label">Category Name</label>
                        <input type="text" class="form-control" id="newCategoryName" required placeholder="e.g., Special Services">
                    </div>
                    <div class="mb-3">
                        <label for="firstItemName" class="form-label">First Item Name (Optional)</label>
                        <input type="text" class="form-control" id="firstItemName" placeholder="e.g., Custom Design">
                    </div>
                    <div class="mb-3">
                        <label for="firstItemPrice" class="form-label">First Item Price (Optional)</label>
                        <input type="text" class="form-control" id="firstItemPrice" placeholder="e.g., $25.00">
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-success">Create Category</button>
                </div>
            </form>
        </div>
    </div>
</div>
<!-- Edit Headers Modal -->
<div class="modal fade" id="editHeadersModal" tabindex="-1" aria-labelledby="editHeadersModalLabel" aria-hidden="true">
    <div class="modal-dialog">
        <div class="modal-content">
            <div class="modal-header">
                <h5 class="modal-title" id="editHeadersModalLabel">Edit Column Headers</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <form id="editHeadersForm">
                <div class="modal-body">
                    <input type="hidden" id="editHeadersCategory">
                    <div class="mb-3">
                        <label class="form-label">Category: <strong><span id="editHeadersCategoryDisplay"></span></strong></label>
                    </div>
                    <div class="mb-3">
                        <label for="editHeader1" class="form-label">First Column Header</label>
                        <input type="text" class="form-control" id="editHeader1" required placeholder="e.g., Paper Type, Service, Item">
                    </div>
                    <div class="mb-3">
                        <label for="editHeader2" class="form-label">Second Column Header</label>
                        <input type="text" class="form-control" id="editHeader2" required placeholder="e.g., Price (per sheet), Price, Cost">
                    </div>
                    <div class="alert alert-info">
                        <i class="bi bi-info-circle"></i> These headers will be displayed for all items in this category.
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn btn-primary">Update Headers</button>
                </div>
            </form>
        </div>
    </div>
</div>
<script>
// Show/hide new category input in add item modal
document.getElementById('addCategory').addEventListener('change', function() {
    const newCategoryGroup = document.getElementById('newCategoryGroup');
    if (this.value === 'new') {
        newCategoryGroup.style.display = 'block';
        document.getElementById('newCategoryName').required = true;
    } else {
        newCategoryGroup.style.display = 'none';
        document.getElementById('newCategoryName').required = false;
    }
});

// Edit item function
function editItem(id, name, price, category) {
    document.getElementById('editItemId').value = id;
    document.getElementById('editItemName').value = name;
    document.getElementById('editPrice').value = price;
    document.getElementById('editCategory').value = category;
    new bootstrap.Modal(document.getElementById('editItemModal')).show();
}

// Add new item function
function addNewItem(category) {
    document.getElementById('addCategory').value = category;
    new bootstrap.Modal(document.getElementById('addItemModal')).show();
}

// Edit category function
function editCategory(categoryName) {
    document.getElementById('editCategoryOldName').value = categoryName;
    document.getElementById('editCategoryNewName').value = categoryName;
    new bootstrap.Modal(document.getElementById('editCategoryModal')).show();
}

// Add new category function
function addNewCategory() {
    document.getElementById('newCategoryName').value = '';
    document.getElementById('firstItemName').value = '';
    document.getElementById('firstItemPrice').value = '';
    new bootstrap.Modal(document.getElementById('addCategoryModal')).show();
}

// Edit headers function
function editHeaders(categoryName, header1, header2) {
    document.getElementById('editHeadersCategory').value = categoryName;
    document.getElementById('editHeadersCategoryDisplay').textContent = categoryName;
    document.getElementById('editHeader1').value = header1;
    document.getElementById('editHeader2').value = header2;
    new bootstrap.Modal(document.getElementById('editHeadersModal')).show();
}

// Handle edit headers form submission
document.getElementById('editHeadersForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const data = {
        action: 'edit_headers',
        category: document.getElementById('editHeadersCategory').value,
        header1: document.getElementById('editHeader1').value,
        header2: document.getElementById('editHeader2').value
    };
    
    fetch('manage_pricing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});

// Delete category function
function deleteCategory(categoryName, itemCount) {
    let message = 'Are you sure you want to delete the "' + categoryName + '" category?';
    if (itemCount > 0) {
        message += '\n\nThis will also delete all ' + itemCount + ' items in this category.';
    }
    
    if (confirm(message)) {
        fetch('manage_pricing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ 
                action: 'delete_category', 
                category: categoryName 
            })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Delete item function
function deleteItem(id, name) {
    if (confirm('Are you sure you want to delete "' + name + '"?')) {
        fetch('manage_pricing.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ action: 'delete', id: id })
        })
        .then(response => response.json())
        .then(data => {
            if (data.success) {
                location.reload();
            } else {
                alert('Error: ' + data.message);
            }
        })
        .catch(error => {
            console.error('Error:', error);
            alert('An error occurred');
        });
    }
}

// Handle edit category form submission
document.getElementById('editCategoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const data = {
        action: 'edit_category',
        old_name: document.getElementById('editCategoryOldName').value,
        new_name: document.getElementById('editCategoryNewName').value
    };
    
    fetch('manage_pricing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});

// Handle add category form submission
document.getElementById('addCategoryForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const data = {
        action: 'add_category',
        category_name: document.getElementById('newCategoryName').value,
        first_item_name: document.getElementById('firstItemName').value,
        first_item_price: document.getElementById('firstItemPrice').value
    };
    
    fetch('manage_pricing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});

// Handle edit item form submission
document.getElementById('editItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const data = {
        action: 'edit',
        id: document.getElementById('editItemId').value,
        category: document.getElementById('editCategory').value,
        item_name: document.getElementById('editItemName').value,
        price: document.getElementById('editPrice').value
    };
    
    fetch('manage_pricing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});

// Handle add item form submission
document.getElementById('addItemForm').addEventListener('submit', function(e) {
    e.preventDefault();
    
    const categorySelect = document.getElementById('addCategory');
    const category = categorySelect.value === 'new' 
        ? document.getElementById('newCategoryName').value 
        : categorySelect.value;
    
    const data = {
        action: 'add',
        category: category,
        item_name: document.getElementById('addItemName').value,
        price: document.getElementById('addPrice').value
    };
    
    fetch('manage_pricing.php', {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify(data)
    })
    .then(response => response.json())
    .then(data => {
        if (data.success) {
            location.reload();
        } else {
            alert('Error: ' + data.message);
        }
    })
    .catch(error => {
        console.error('Error:', error);
        alert('An error occurred');
    });
});
</script>
<?php endif; ?>

<?php require_once 'footer.php'; ?>