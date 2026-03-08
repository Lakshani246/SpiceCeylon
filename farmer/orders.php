<?php
session_start();

if (!isset($_SESSION['user_id']) || $_SESSION['role'] != 'farmer') {
    header("Location: ../auth/login.php");
    exit();
}

include '../config/db.php';
$farmer_id = $_SESSION['user_id'];

// Get farmer data
$farmer_query = $conn->prepare("SELECT * FROM users WHERE user_id = ?");
$farmer_query->bind_param("i", $farmer_id);
$farmer_query->execute();
$farmer = $farmer_query->get_result()->fetch_assoc();

// Get orders containing farmer's products
$orders_query = $conn->prepare("
    SELECT DISTINCT 
        o.order_id,
        o.order_date,
        o.created_at,
        o.status,
        o.total_amount,
        o.shipping_fee,
        o.final_total,
        o.payment_method,
        o.shipping_name,
        o.shipping_address,
        o.shipping_city,
        o.notes,
        u.name as customer_name,
        u.email as customer_email,
        u.phone as customer_phone,
        (SELECT COUNT(*) FROM order_items oi2 WHERE oi2.order_id = o.order_id) as total_items,
        (SELECT SUM(CASE WHEN p2.farmer_id = ? THEN oi2.quantity ELSE 0 END) 
         FROM order_items oi2 
         JOIN products p2 ON oi2.product_id = p2.product_id 
         WHERE oi2.order_id = o.order_id) as my_items_count
    FROM orders o
    JOIN order_items oi ON o.order_id = oi.order_id
    JOIN products p ON oi.product_id = p.product_id
    JOIN users u ON o.customer_id = u.user_id
    WHERE p.farmer_id = ?
    ORDER BY o.created_at DESC
");

$orders_query->bind_param("ii", $farmer_id, $farmer_id);
$orders_query->execute();
$orders_result = $orders_query->get_result();

// Get order counts
$status_counts = [
    'pending' => 0,
    'processing' => 0,
    'shipped' => 0,
    'delivered' => 0,
    'cancelled' => 0,
    'confirmed' => 0,
    'completed' => 0
];

$orders_result->data_seek(0);
while ($order = $orders_result->fetch_assoc()) {
    $status = strtolower($order['status']);
    if (isset($status_counts[$status])) {
        $status_counts[$status]++;
    }
}
$orders_result->data_seek(0);
?>

<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Orders - Farmer Dashboard</title>
    <link href="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/css/bootstrap.min.css" rel="stylesheet">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <style>
        :root {
            --farmer-green: #27ae60;
            --farmer-dark: #2c3e50;
            --farmer-gold: #f39c12;
            --farmer-blue: #3498db;
            --farmer-brown: #8b4513;
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
        
        .dashboard-header {
            background: white;
            padding: 25px;
            border-radius: 12px;
            margin-bottom: 30px;
            box-shadow: 0 4px 12px rgba(0,0,0,0.06);
            border-left: 5px solid var(--farmer-green);
        }
        
        .order-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
            border: 1px solid #e9ecef;
            transition: all 0.3s;
            border-left: 4px solid transparent;
        }
        
        .order-card:hover {
            transform: translateY(-3px);
            box-shadow: 0 8px 25px rgba(0,0,0,0.12);
        }
        
        .order-card.pending { border-left-color: var(--pending); }
        .order-card.processing { border-left-color: var(--processing); }
        .order-card.shipped { border-left-color: var(--shipped); }
        .order-card.delivered { border-left-color: var(--delivered); }
        .order-card.cancelled { border-left-color: var(--cancelled); }
        .order-card.confirmed { border-left-color: var(--confirmed); }
        .order-card.completed { border-left-color: var(--completed); }
        
        .status-badge {
            padding: 5px 12px;
            border-radius: 20px;
            font-size: 0.85rem;
            font-weight: 600;
        }
        
        .badge-pending { background: rgba(243, 156, 18, 0.15); color: var(--pending); }
        .badge-processing { background: rgba(52, 152, 219, 0.15); color: var(--processing); }
        .badge-shipped { background: rgba(155, 89, 182, 0.15); color: var(--shipped); }
        .badge-delivered { background: rgba(39, 174, 96, 0.15); color: var(--delivered); }
        .badge-cancelled { background: rgba(231, 76, 60, 0.15); color: var(--cancelled); }
        .badge-confirmed { background: rgba(46, 204, 113, 0.15); color: var(--confirmed); }
        .badge-completed { background: rgba(39, 174, 96, 0.15); color: var(--completed); }
        
        .stat-card {
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 6px 20px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
            cursor: pointer;
            color: white;
            height: 100%;
            border: none;
        }
        
        .stat-card.sales { background: linear-gradient(135deg, var(--farmer-green), #219653); }
        .stat-card.products { background: linear-gradient(135deg, var(--farmer-brown), #a0522d); }
        .stat-card.orders { background: linear-gradient(135deg, var(--farmer-blue), #2980b9); }
        .stat-card.requests { background: linear-gradient(135deg, var(--farmer-gold), #e67e22); }
        .stat-card.pending { background: linear-gradient(135deg, #f39c12, #e67e22); }
        .stat-card.processing { background: linear-gradient(135deg, #3498db, #2980b9); }
        .stat-card.shipped { background: linear-gradient(135deg, #9b59b6, #8e44ad); }
        .stat-card.delivered { background: linear-gradient(135deg, #27ae60, #219653); }
        .stat-card.cancelled { background: linear-gradient(135deg, #e74c3c, #c0392b); }
        
        .stat-card:hover {
            transform: translateY(-5px);
        }
        
        .stat-card .stat-value {
            font-size: 2rem;
            font-weight: 700;
            margin-bottom: 5px;
        }
        
        .stat-card .stat-label {
            font-size: 0.9rem;
            opacity: 0.9;
        }
        
        .stat-card.active {
            box-shadow: 0 0 0 3px white, 0 0 0 6px var(--farmer-green);
        }
        
        .filter-card {
            background: white;
            border-radius: 12px;
            padding: 20px;
            margin-bottom: 25px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.08);
        }
        
        .my-items-badge {
            background: var(--farmer-green);
            color: white;
            padding: 3px 10px;
            border-radius: 20px;
            font-size: 0.75rem;
        }
        
        .empty-state {
            padding: 60px 20px;
            text-align: center;
            background: white;
            border-radius: 12px;
            border: 2px dashed #e9ecef;
        }
        
        .empty-state-icon {
            font-size: 4rem;
            color: #e9ecef;
            margin-bottom: 20px;
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
        .popup-modal.info { border-top: 5px solid var(--farmer-blue); }
        .popup-modal.warning { border-top: 5px solid var(--farmer-gold); }
        
        .popup-modal i {
            font-size: 4rem;
            margin-bottom: 15px;
        }
        
        .popup-modal.success i { color: var(--farmer-green); }
        .popup-modal.error i { color: #e74c3c; }
        .popup-modal.info i { color: var(--farmer-blue); }
        .popup-modal.warning i { color: var(--farmer-gold); }
        
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
            font-weight: 500;
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
        
        /* Confirmation Modal */
        .confirm-modal .modal-content {
            border-radius: 15px;
            border: none;
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }
        
        .confirm-modal .modal-header {
            background: linear-gradient(135deg, #e74c3c, #c0392b);
            color: white;
            border-radius: 15px 15px 0 0;
            border-bottom: none;
            padding: 20px;
        }
        
        .confirm-modal .modal-body {
            padding: 30px;
            text-align: center;
        }
        
        .confirm-modal .modal-body i {
            font-size: 4rem;
            color: #e74c3c;
            margin-bottom: 15px;
        }
        
        .confirm-modal .modal-footer {
            border-top: 1px solid #e9ecef;
            padding: 15px 30px;
            justify-content: center;
        }
        
        .confirm-modal .btn {
            padding: 8px 25px;
            border-radius: 25px;
        }
    </style>
</head>
<body>
    <div class="container-fluid">
        <div class="row">
            <!-- Include your existing sidebar -->
            <?php include 'sidebar.php'; ?>

            <!-- Main Content -->
            <div class="col-md-10 p-4" style="background: #f8f9fa; min-height: 100vh;">
                <!-- Header -->
                <div class="dashboard-header">
                    <div class="d-flex justify-content-between align-items-center">
                        <div>
                            <h2 class="mb-2" style="color: var(--farmer-dark);">
                                <i class="fas fa-shopping-cart me-2" style="color: var(--farmer-green);"></i>
                                My Orders
                            </h2>
                            <p class="text-muted mb-0">
                                <i class="fas fa-info-circle me-1"></i> 
                                Orders containing your products - Update status to notify customers
                            </p>
                        </div>
                        <div class="bg-success text-white p-3 rounded">
                            <i class="fas fa-box me-1"></i> 
                            <?php echo $orders_result->num_rows; ?> Orders
                        </div>
                    </div>
                </div>

                <!-- Status Stats - Same colors as dashboard -->
                <div class="row mb-4" id="statusFilter">
                    <div class="col-md-2">
                        <div class="stat-card sales" onclick="filterOrders('all')">
                            <div class="stat-value"><?php echo $orders_result->num_rows; ?></div>
                            <div class="stat-label">All Orders</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card pending" onclick="filterOrders('pending')">
                            <div class="stat-value"><?php echo $status_counts['pending']; ?></div>
                            <div class="stat-label">Pending</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card processing" onclick="filterOrders('processing')">
                            <div class="stat-value"><?php echo $status_counts['processing']; ?></div>
                            <div class="stat-label">Processing</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card shipped" onclick="filterOrders('shipped')">
                            <div class="stat-value"><?php echo $status_counts['shipped']; ?></div>
                            <div class="stat-label">Shipped</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card delivered" onclick="filterOrders('delivered')">
                            <div class="stat-value"><?php echo $status_counts['delivered']; ?></div>
                            <div class="stat-label">Delivered</div>
                        </div>
                    </div>
                    <div class="col-md-2">
                        <div class="stat-card cancelled" onclick="filterOrders('cancelled')">
                            <div class="stat-value"><?php echo $status_counts['cancelled']; ?></div>
                            <div class="stat-label">Cancelled</div>
                        </div>
                    </div>
                </div>

                <!-- Orders List -->
                <?php if($orders_result->num_rows == 0): ?>
                    <div class="empty-state">
                        <div class="empty-state-icon">
                            <i class="fas fa-shopping-cart"></i>
                        </div>
                        <h5 class="text-muted mb-3">No Orders Yet</h5>
                        <p class="text-muted mb-4">When customers order your products, they'll appear here.</p>
                        <a href="manage_products.php" class="btn btn-outline-primary">
                            <i class="fas fa-leaf me-2"></i> View My Products
                        </a>
                    </div>
                <?php else: ?>
                    <?php while($order = $orders_result->fetch_assoc()): 
                        $status_class = strtolower($order['status']);
                    ?>
                    <div class="order-card <?php echo $status_class; ?>" data-status="<?php echo $status_class; ?>">
                        <div class="row">
                            <div class="col-md-8">
                                <div class="d-flex justify-content-between align-items-start mb-3">
                                    <div>
                                        <h5 class="mb-1">
                                            Order #<?php echo str_pad($order['order_id'], 6, '0', STR_PAD_LEFT); ?>
                                            <?php if($order['my_items_count'] < $order['total_items']): ?>
                                                <span class="my-items-badge ms-2">
                                                    <i class="fas fa-check-circle"></i> 
                                                    <?php echo $order['my_items_count']; ?>/<?php echo $order['total_items']; ?> your items
                                                </span>
                                            <?php endif; ?>
                                        </h5>
                                        <p class="text-muted mb-2">
                                            <i class="fas fa-user me-1"></i> <?php echo htmlspecialchars($order['customer_name']); ?> |
                                            <i class="fas fa-calendar me-1"></i> <?php echo date('M d, Y h:i A', strtotime($order['created_at'])); ?>
                                        </p>
                                    </div>
                                    <span class="status-badge badge-<?php echo $status_class; ?>">
                                        <?php echo ucfirst($order['status']); ?>
                                    </span>
                                </div>
                                
                                <div class="row">
                                    <div class="col-md-6">
                                        <p class="small mb-1">
                                            <i class="fas fa-map-marker-alt text-muted me-1"></i>
                                            <?php echo htmlspecialchars($order['shipping_address'] . ', ' . $order['shipping_city']); ?>
                                        </p>
                                        <p class="small mb-1">
                                            <i class="fas fa-phone text-muted me-1"></i>
                                            <?php echo htmlspecialchars($order['customer_phone']); ?>
                                        </p>
                                    </div>
                                    <div class="col-md-6">
                                        <p class="small mb-1">
                                            <i class="fas fa-credit-card text-muted me-1"></i>
                                            <?php echo ucfirst(str_replace('_', ' ', $order['payment_method'])); ?>
                                        </p>
                                        <p class="small mb-1">
                                            <i class="fas fa-tag text-muted me-1"></i>
                                            Total: <strong>Rs. <?php echo number_format($order['final_total'], 2); ?></strong>
                                        </p>
                                    </div>
                                </div>
                                
                                <?php if(!empty($order['notes'])): ?>
                                <div class="mt-2 p-2 bg-light rounded">
                                    <small><i class="fas fa-sticky-note me-1"></i> <?php echo htmlspecialchars($order['notes']); ?></small>
                                </div>
                                <?php endif; ?>
                            </div>
                            
                            <div class="col-md-4 d-flex align-items-center justify-content-end">
                                <a href="view_order.php?id=<?php echo $order['order_id']; ?>" class="btn btn-outline-primary me-2">
                                    <i class="fas fa-eye"></i> View
                                </a>
                                <?php if(in_array($order['status'], ['Pending', 'Processing', 'Confirmed', 'Shipped'])): ?>
                                <button class="btn btn-outline-success" onclick="showUpdateModal(<?php echo $order['order_id']; ?>, '<?php echo $order['status']; ?>')">
                                    <i class="fas fa-edit"></i> Update
                                </button>
                                <?php endif; ?>
                            </div>
                        </div>
                    </div>
                    <?php endwhile; ?>
                <?php endif; ?>
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
                        <input type="hidden" name="order_id" id="order_id">
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

    <!-- Info Popup -->
    <div class="popup-modal info" id="infoPopup">
        <i class="fas fa-info-circle"></i>
        <h5>Information</h5>
        <p id="infoMessage"></p>
        <button class="btn btn-primary" onclick="closePopup()">OK</button>
    </div>

    <!-- Confirmation Modal -->
    <div class="modal fade confirm-modal" id="confirmModal" tabindex="-1">
        <div class="modal-dialog">
            <div class="modal-content">
                <div class="modal-header bg-danger text-white">
                    <h5 class="modal-title"><i class="fas fa-exclamation-triangle me-2"></i> Confirm Action</h5>
                    <button type="button" class="btn-close btn-close-white" data-bs-dismiss="modal"></button>
                </div>
                <div class="modal-body">
                    <i class="fas fa-question-circle"></i>
                    <h5 id="confirmMessage">Are you sure?</h5>
                    <p id="confirmDetail" class="text-muted">This action cannot be undone.</p>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn btn-secondary" data-bs-dismiss="modal">Cancel</button>
                    <button type="button" class="btn btn-danger" id="confirmActionBtn">Confirm</button>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        let currentAction = null;
        let currentId = null;
        
        function showPopup(type, message) {
            // Hide all popups first
            closePopup();
            
            let popup;
            if (type === 'success') popup = document.getElementById('successPopup');
            else if (type === 'error') popup = document.getElementById('errorPopup');
            else popup = document.getElementById('infoPopup');
            
            document.getElementById(type + 'Message').textContent = message;
            document.getElementById('popupOverlay').classList.add('show');
            popup.classList.add('show');
            
            setTimeout(closePopup, 3000);
        }
        
        function closePopup() {
            document.getElementById('popupOverlay').classList.remove('show');
            document.querySelectorAll('.popup-modal').forEach(p => p.classList.remove('show'));
        }
        
        function showConfirm(message, detail, action, id) {
            currentAction = action;
            currentId = id;
            document.getElementById('confirmMessage').textContent = message;
            document.getElementById('confirmDetail').textContent = detail;
            new bootstrap.Modal(document.getElementById('confirmModal')).show();
        }
        
        function filterOrders(status) {
            document.querySelectorAll('.stat-card').forEach(c => c.classList.remove('active'));
            if(status === 'all') {
                document.querySelectorAll('.order-card').forEach(c => c.style.display = 'block');
                document.querySelector('.stat-card.sales').classList.add('active');
            } else {
                document.querySelectorAll('.order-card').forEach(card => {
                    if(card.dataset.status === status) {
                        card.style.display = 'block';
                    } else {
                        card.style.display = 'none';
                    }
                });
                event.currentTarget.classList.add('active');
            }
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

        // Add hover effect to cards
        $(document).ready(function() {
            $('.order-card').hover(
                function() { $(this).css('transform', 'translateY(-3px)'); },
                function() { $(this).css('transform', 'translateY(0)'); }
            );
            
            // Set first stat card as active
            $('.stat-card.sales').addClass('active');
        });
    </script>
</body>
</html>