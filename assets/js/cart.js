// Cart functionality
document.addEventListener('DOMContentLoaded', function() {
    // Quantity button handlers
    const quantityBtns = document.querySelectorAll('.quantity-btn');
    
    quantityBtns.forEach(btn => {
        btn.addEventListener('click', function() {
            const isPlus = this.classList.contains('plus');
            const input = this.closest('.input-group').querySelector('.quantity-input');
            let value = parseInt(input.value);
            
            if (isPlus) {
                value = Math.min(value + 1, parseInt(input.max));
            } else {
                value = Math.max(value - 1, parseInt(input.min));
            }
            
            input.value = value;
            
            // Auto-update if value is 0 (remove item)
            if (value === 0) {
                this.closest('form').submit();
            }
        });
    });

    // Quantity input validation
    const quantityInputs = document.querySelectorAll('.quantity-input');
    quantityInputs.forEach(input => {
        input.addEventListener('change', function() {
            let value = parseInt(this.value);
            const max = parseInt(this.max);
            const min = parseInt(this.min);
            
            if (isNaN(value) || value < min) {
                this.value = min;
            } else if (value > max) {
                this.value = max;
                showAlert(`Only ${max} items available in stock`, 'warning');
            }
        });
    });

    // Auto-update on input change (optional)
    const quantityForms = document.querySelectorAll('.quantity-form');
    quantityForms.forEach(form => {
        const input = form.querySelector('.quantity-input');
        let timeout;
        
        input.addEventListener('input', function() {
            clearTimeout(timeout);
            timeout = setTimeout(() => {
                if (this.value !== this.defaultValue) {
                    form.submit();
                }
            }, 1000);
        });
    });

    // Remove item confirmation
    const removeBtns = document.querySelectorAll('.remove-btn');
    removeBtns.forEach(btn => {
        btn.addEventListener('click', function(e) {
            if (!confirm('Are you sure you want to remove this item from your cart?')) {
                e.preventDefault();
            }
        });
    });

    // Show alert function
    function showAlert(message, type = 'info') {
        // Remove existing alerts
        const existingAlert = document.querySelector('.custom-alert');
        if (existingAlert) {
            existingAlert.remove();
        }

        const alert = document.createElement('div');
        alert.className = `alert alert-${type} custom-alert alert-dismissible fade show`;
        alert.style.cssText = `
            position: fixed;
            top: 100px;
            right: 20px;
            z-index: 9999;
            min-width: 300px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        `;
        
        alert.innerHTML = `
            ${message}
            <button type="button" class="btn-close" data-bs-dismiss="alert"></button>
        `;
        
        document.body.appendChild(alert);
        
        // Auto remove after 5 seconds
        setTimeout(() => {
            if (alert.parentNode) {
                alert.remove();
            }
        }, 5000);
    }

    // Update item totals in real-time (if needed)
    function updateItemTotal(input) {
        const form = input.closest('.quantity-form');
        const price = parseFloat(form.closest('.cart-item').querySelector('.product-price').textContent.replace('Rs. ', ''));
        const quantity = parseInt(input.value);
        const totalElement = form.closest('.row').querySelector('.item-total');
        
        if (totalElement) {
            totalElement.textContent = 'Rs. ' + (price * quantity).toFixed(2);
        }
    }

    // Initialize item totals
    quantityInputs.forEach(input => {
        updateItemTotal(input);
        
        input.addEventListener('input', function() {
            updateItemTotal(this);
        });
    });
});

// Export functions for potential reuse
window.cartFunctions = {
    updateQuantity: function(cartId, quantity) {
        // AJAX implementation can be added here later
        console.log(`Updating cart item ${cartId} to quantity ${quantity}`);
    },
    
    removeItem: function(cartId) {
        // AJAX implementation can be added here later
        console.log(`Removing cart item ${cartId}`);
    }
};