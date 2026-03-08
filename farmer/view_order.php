<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$farmer_id = $_SESSION['user_id'];
$order_id = isset($_GET['id']) ? intval($_GET['id']) : 0;

if (!$order_id) {
    header("Location: orders.php");
    exit();
}

// Get farmer data for sidebar
$farmer_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer = $farmer_query->get_result()->fetch_assoc();

// Get order details (only if farmer has products in it)
$order_query = $conn->prepare("
    SELECT 
        o.*,
        u.name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        u.address as customer_address
    FROM orders o
    JOIN users u ON o.customer_id = u.user_id
    WHERE o.order_id = ? AND EXISTS (
        SELECT 1 FROM order_items oi 
        JOIN products p ON oi.product_id = p.product_id 
        WHERE oi.order_id = o.order_id AND p.farmer_id = ?
    )
");
$order_query->bind_param("ii", $order_id, $farmer_id);
$order_query->execute();
$order_result = $order_query->get_result();
$order = $order_result->fetch_assoc();

if (!$order) {
    header("Location: orders.php");
    exit();
}

// Get my products in this order
$my_items_query = $conn->prepare("
    SELECT 
        oi.*,
        p.name as product_name,
        p.category,
        p.image
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    WHERE oi.order_id = ? AND p.farmer_id = ?
");
$my_items_query->bind_param("ii", $order_id, $farmer_id);
$my_items_query->execute();
$my_items = $my_items_query->get_result();

// Get other items in this order (from other farmers)
$other_items_query = $conn->prepare("
    SELECT 
        oi.*,
        p.name as product_name,
        p.category,
        u.name as farmer_name
    FROM order_items oi
    JOIN products p ON oi.product_id = p.product_id
    JOIN users u ON p.farmer_id = u.user_id
    WHERE oi.order_id = ? AND p.farmer_id != ?
");
$other_items_query->bind_param("ii", $order_id, $farmer_id);
$other_items_query->execute();
$other_items = $other_items_query->get_result();

// Get status history
$history_query = $conn->prepare("
    SELECT * FROM order_status_history 
    WHERE order_id = ? 
    ORDER BY changed_at DESC
");
$history_query->bind_param("i", $order_id);
$history_query->execute();
$history = $history_query->get_result();

// Calculate my total
$my_total = 0;
$my_items->data_seek(0);
while ($item = $my_items->fetch_assoc()) {
    $my_total += $item['total_price'];
}
$my_items->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Order Details - Farmer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --farmer-green: #27ae60;
            --farmer-dark: #2c3e50;
            --farmer-blue: #3498db;
            --pending: #f39c12;
            --processing: #3498db;
            --shipped: #9b59b6;
            --delivered: #27ae60;
            --cancelled: #e74c3c;
            --confirmed: #2ecc71;
            --completed: #27ae60;
        }
        
        .sidebar {
            background: linear-gradient(180deg, #2d6a4f 0%, #1b4332 100%);
            min-height: 100vh;
            box-shadow: 3px 0 15px rgba(0,0,0,0.2);
        }
        
        .sidebar .nav-link {
            color: #ecf0f1;
            padding: 14px 20px;
            margin: 4px 10px;
            border-radius: 10px;
            transition: all 0.3s ease;
            border-left: 4px solid transparent;
            font-size: 0.95rem;
        }
        
        .sidebar .nav-link:hover, .sidebar .nav-link.active {
            background: rgba(39, 174, 96, 0.2);
            border-left-color: var(--farmer-green);
            transform: translateX(5px);
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        
        .sidebar .brand {
            background: rgba(0,0,0,0.3);
            padding: 25px 20px;
            text-align: center;
            border-bottom: 1px solid rgba(255,255,255,0.1);
            background: linear-gradient(135deg, rgba(39, 174, 96, 0.3), rgba(139, 69, 19, 0.2));
        }
        
        .detail-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
        }
        
        .status-badge {
            padding: 8px 16px;
            border-radius: 30px;
            font-size: 0.9rem;
            font-weight: 600;
        }
        
        .badge-pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-processing { background: rgba(52, 152, 219, 0.15); color: var(--processing); }
        .badge-shipped { background: rgba(155, 89, 182, 0.15); color: var(--shipped); }
        .badge-delivered { background: rgba(39, 174, 96, 0.15); color: var(--delivered); }
        .badge-completed { background: rgba(39, 174, 96, 0.15); color: var(--completed); }
        .badge-confirmed { background: rgba(46, 204, 113, 0.15); color: var(--confirmed); }
        .badge-cancelled { background: rgba(231, 76, 60, 0.15); color: var(--cancelled); }
        
        .timeline-item {
            position: relative;
            padding-left: 30px;
            padding-bottom: 20px;
            border-left: 2px solid #e9ecef;
        }
        
        .timeline-item:last-child {
            border-left: none;
        }
        
        .timeline-item::before {
            content: '';
            position: absolute;
            left: -6px;
            top: 0;
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: var(--farmer-green);
        }
        
        .product-table {
            font-size: 0.9rem;
        }
        
        .product-table th {
            background: #f8f9fa;
            font-weight: 600;
        }
        
        .my-total-row {
            background: rgba(39, 174, 96, 0.1);
            font-weight: 600;
        }
        
        .back-btn {
            margin-bottom: 20px;
        }
        
        .dashboard-header {
            background: white;
            padding: 20px;
            border-radius: 12px;
            margin-bottom: 25px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
        }
        
        /* Popup Modal Styles */
        .popup-overlay {
            position: fixed;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(0,0,0,0.5);
            z-index: 9998;
            display: none;
        }
        
        .popup-modal {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            background: white;
            border-radius: 15px;
            box-shadow: 0 15px 40px rgba(0,0,0,0.2);
            padding: 30px 40px;
            text-align: center;
            z-index: 9999;
            display: none;
            max-width: 400px;
            width: 90%;
        }
        
        .popup-modal.show {
            display: block;
            animation: fadeIn 0.3s ease;
        }
        
        .popup-overlay.show {
            display: block;
        }
        
        .popup-modal.success { border-top: 5px solid var(--farmer-green); }
        .popup-modal.error { border-top: 5px solid #e74c3c; }
        
        .popup-modal i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .popup-modal.success i { color: var(--farmer-green); }
        .popup-modal.error i { color: #e74c3c; }
        
        .popup-modal h5 {
            font-size: 1.3rem;
            margin-bottom: 10px;
            color: var(--farmer-dark);
        }
        
        .popup-modal p {
            color: #7f8c8d;
            margin-bottom: 20px;
        }
        
        .popup-modal .btn {
            padding: 8px 30px;
            border-radius: 25px;
        }
        
        @keyframes fadeIn {
            from {
                opacity: 0;
                transform: translate(-50%, -60%);
            }
            to {
                opacity: 1;
                transform: translate(-50%, -50%);
            }
        }
        
        .info-box {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border-radius: 10px;
            padding: 20px;
            margin-bottom: 20px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include your actual sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header with Back Button -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--farmer-dark);">
                                <i class="fas fa-shopping-cart me-2" style="color: var(--farmer-green);"></i>
                                Order Details
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Viewing order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                            </p>
                        </div>
                        <a href="orders.php" class="btn btn-outline-secondary">
                            <i class="fas fa-arrow-left me-2"></i> Back to Orders
                        </a>
                    </div>
                </div>

                <!-- Status Header -->
                <div class="d-flex justify-content-between align-items-center mb-4">
                    <h4>
                        Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                        <small class="text-muted ms-3">Placed on <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?></small>
                    </h4>
                    <span class="status-badge badge-<?php echo strtolower($order['status']); ?>">
                        <i class="fas 
                            <?php 
                            switch(strtolower($order['status'])) {
                                case 'pending': echo 'fa-clock'; break;
                                case 'processing': echo 'fa-cogs'; break;
                                case 'shipped': echo 'fa-shipping-fast'; break;
                                case 'delivered': echo 'fa-check-circle'; break;
                                case 'completed': echo 'fa-check-double'; break;
                                case 'confirmed': echo 'fa-user-check'; break;
                                case 'cancelled': echo 'fa-times-circle'; break;
                                default: echo 'fa-circle';
                            }
                            ?> me-2"></i>
                        <?php echo $order['status']; ?>
                    </span>
                </div>

                <div class="row">
                    <!-- Left Column - Order Items -->
                    <div class="col-md-8">
                        <!-- My Products -->
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-leaf text-success me-2"></i>
                                Your Products in this Order
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-sm product-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Category</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-end">Unit Price</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($item = $my_items->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['category']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">Rs. <?php echo number_format($item['price'], 2); ?></td>
                                            <td class="text-end fw-bold">Rs. <?php echo number_format($item['total_price'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                        <tr class="my-total-row">
                                            <td colspan="4" class="text-end fw-bold">Your Subtotal:</td>
                                            <td class="text-end fw-bold">Rs. <?php echo number_format($my_total, 2); ?></td>
                                        </tr>
                                    </tbody>
                                </table>
                            </div>
                        </div>

                        <!-- Other Items -->
                        <?php if($other_items->num_rows > 0): ?>
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-store text-muted me-2"></i>
                                Other Items in this Order
                            </h5>
                            <div class="table-responsive">
                                <table class="table table-sm product-table">
                                    <thead>
                                        <tr>
                                            <th>Product</th>
                                            <th>Farmer</th>
                                            <th class="text-center">Quantity</th>
                                            <th class="text-end">Total</th>
                                        </tr>
                                    </thead>
                                    <tbody>
                                        <?php while($item = $other_items->fetch_assoc()): ?>
                                        <tr>
                                            <td><?php echo htmlspecialchars($item['product_name']); ?></td>
                                            <td><?php echo htmlspecialchars($item['farmer_name']); ?></td>
                                            <td class="text-center"><?php echo $item['quantity']; ?></td>
                                            <td class="text-end">Rs. <?php echo number_format($item['total_price'], 2); ?></td>
                                        </tr>
                                        <?php endwhile; ?>
                                    </tbody>
                                </table>
                            </div>
                        </div>
                        <?php endif; ?>

                        <!-- Status History -->
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-history text-info me-2"></i>
                                Status History
                            </h5>
                            <?php if($history->num_rows > 0): ?>
                                <?php while($event = $history->fetch_assoc()): ?>
                                <div class="timeline-item">
                                    <div class="fw-bold"><?php echo $event['status']; ?></div>
                                    <small class="text-muted">
                                        <?php echo date('M d, Y h:i A', strtotime($event['changed_at'])); ?>
                                    </small>
                                    <?php if(!empty($event['notes'])): ?>
                                    <p class="small text-muted mt-1 mb-0"><?php echo htmlspecialchars($event['notes']); ?></p>
                                    <?php endif; ?>
                                </div>
                                <?php endwhile; ?>
                            <?php else: ?>
                                <p class="text-muted text-center py-3">No status history available</p>
                            <?php endif; ?>
                        </div>
                    </div>

                    <!-- Right Column - Order Summary & Customer Details -->
                    <div class="col-md-4">
                        <!-- Order Summary -->
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-receipt text-warning me-2"></i>
                                Order Summary
                            </h5>
                            <table class="table table-sm">
                                <tr>
                                    <td>Total Amount:</td>
                                    <td class="text-end">Rs. <?php echo number_format($order['total_amount'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Shipping Fee:</td>
                                    <td class="text-end">Rs. <?php echo number_format($order['shipping_fee'], 2); ?></td>
                                </tr>
                                <tr class="fw-bold">
                                    <td>Final Total:</td>
                                    <td class="text-end text-success">Rs. <?php echo number_format($order['final_total'], 2); ?></td>
                                </tr>
                                <tr>
                                    <td>Payment Method:</td>
                                    <td class="text-end"><?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?></td>
                                </tr>
                            </table>
                        </div>

                        <!-- Customer Details -->
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-user text-primary me-2"></i>
                                Customer Details
                            </h5>
                            <p class="mb-2"><strong><?php echo htmlspecialchars($order['customer_name']); ?></strong></p>
                            <p class="mb-1 small">
                                <i class="fas fa-phone me-2 text-muted"></i>
                                <?php echo htmlspecialchars($order['customer_phone']); ?>
                            </p>
                            <p class="mb-1 small">
                                <i class="fas fa-envelope me-2 text-muted"></i>
                                <?php echo htmlspecialchars($order['customer_email']); ?>
                            </p>
                            <p class="mb-0 small">
                                <i class="fas fa-map-marker-alt me-2 text-muted"></i>
                                <?php echo htmlspecialchars($order['shipping_address']); ?>, <?php echo $order['shipping_city']; ?>
                            </p>
                        </div>

                        <!-- Customer Notes -->
                        <?php if(!empty($order['notes'])): ?>
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-sticky-note text-info me-2"></i>
                                Customer Notes
                            </h5>
                            <p class="mb-0"><?php echo nl2br(htmlspecialchars($order['notes'])); ?></p>
                        </div>
                        <?php endif; ?>

                        <!-- Action Buttons -->
                        <?php if(in_array(strtolower($order['status']), ['pending', 'processing', 'confirmed', 'shipped'])): ?>
                        <div class="detail-card">
                            <h5 class="mb-3">
                                <i class="fas fa-cogs text-success me-2"></i>
                                Actions
                            </h5>
                            <button class="btn btn-success w-100 mb-2" onclick="showUpdateModal(<?php echo $order_id; ?>, '<?php echo $order['status']; ?>')">
                                <i class="fas fa-edit me-2"></i> Update Status
                            </button>
                            <button class="btn btn-outline-primary w-100" onclick="window.print()">
                                <i class="fas fa-print me-2"></i> Print Details
                            </button>
                        </div>
                        <?php endif; ?>
                        
                        <!-- Info Box -->
                        <div class="info-box">
                            <div class="d-flex align-items-center">
                                <i class="fas fa-info-circle fa-2x me-3"></i>
                                <div>
                                    <h6 class="mb-1">Need Help?</h6>
                                    <p class="small mb-0">Contact admin if you have issues with this order.</p>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Update Status Modal -->
    <div class="modal fade" id="updateModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-success text-white">
                    <h5 class="modal-title"><i class="fas fa-edit me-2"></i> Update Order Status</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <form id="updateStatusForm">
                    <div class="modal-body">
                        <input type="hidden" name="order_id" id="order_id" value="<?php echo $order_id; ?>">
                        <div class="mb-3">
                            <label class="form-label fw-bold">New Status</label>
                            <select class="form-select" name="status" id="status_select" required>
                                <option value="Processing">Processing - Order is being prepared</option>
                                <option value="Shipped">Shipped - Order has been shipped</option>
                                <option value="Delivered">Delivered - Order delivered to customer</option>
                            </select>
                        </div>
                        <div class="mb-3">
                            <label class="form-label fw-bold">Notes (Optional)</label>
                            <textarea class="form-control" name="notes" rows="2" placeholder="Add any notes about this update..."></textarea>
                            <small class="text-muted">Customer will see these notes</small>
                        </div>
                    </div>
                    <div class="modal-footer">
                        <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                        <button type="submit" class="btn btn-success">Update Status</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Popup Overlay -->
    <div class="popup-overlay" id="popupOverlay"></div>
    
    <!-- Success Popup -->
    <div class="popup-modal success" id="successPopup">
        <i class="fas fa-check-circle"></i>
        <h5>Success!</h5>
        <p id="successMessage">Order status updated successfully.</p>
        <button class="btn btn-success" onclick="closePopup()">OK</button>
    </div>

    <!-- Error Popup -->
    <div class="popup-modal error" id="errorPopup">
        <i class="fas fa-exclamation-circle"></i>
        <h5>Error!</h5>
        <p id="errorMessage">Something went wrong.</p>
        <button class="btn btn-danger" onclick="closePopup()">OK</button>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        function showPopup(type, message) {
            closePopup();
            
            let popup;
            if (type === 'success') popup = document.getElementById('successPopup');
            else popup = document.getElementById('errorPopup');
            
            document.getElementById(type + 'Message').textContent = message;
            document.getElementById('popupOverlay').classList.add('show');
            popup.classList.add('show');
            
            setTimeout(closePopup, 3000);
        }
        
        function closePopup() {
            document.getElementById('popupOverlay').classList.remove('show');
            document.querySelectorAll('.popup-modal').forEach(p => p.classList.remove('show'));
        }

        function showUpdateModal(orderId, currentStatus) {
            document.getElementById('order_id').value = orderId;
            document.getElementById('status_select').value = currentStatus;
            new bootstrap.Modal(document.getElementById('updateModal')).show();
        }

        $('#updateStatusForm').submit(function(e) {
            e.preventDefault();
            
            const formData = new FormData(this);
            formData.append('ajax_action', 'update_order_status');
            
            $('#updateModal').modal('hide');
            
            fetch('update_order_status.php', {
                method: 'POST',
                body: new URLSearchParams(formData)
            })
            .then(response => response.json())
            .then(data => {
                if(data.success) {
                    showPopup('success', data.message);
                    setTimeout(() => location.reload(), 2000);
                } else {
                    showPopup('error', data.message);
                }
            })
            .catch(error => {
                showPopup('error', 'Error updating order status');
            });
        });
    </script>
</body>
</html>