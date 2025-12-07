// ===== Size Selection =====
let sizeButtons = document.querySelectorAll('.size-buttons button');
let quantityInput = document.getElementById('quantity');
let priceDisplay = document.getElementById('display-price');

let selectedSize = '250g'; // default — same for all spices

// Prices passed from PHP into script tag
// const prices = {}; // already injected in each page

function updatePrice() {
    let qty = parseInt(quantityInput.value) || 1;
    let price = prices[selectedSize] * qty;
    priceDisplay.textContent = "Rs. " + price.toLocaleString() + ".00";
}

// When clicking size buttons
sizeButtons.forEach(btn => {
    btn.addEventListener('click', () => {
        sizeButtons.forEach(b => b.classList.remove('selected'));
        btn.classList.add('selected');
        selectedSize = btn.dataset.size;
        updatePrice();
    });
});

// Quantity change
quantityInput.addEventListener('input', updatePrice);

// Initialize
updatePrice();

// ===== Add to Cart =====
document.querySelector('.btn-add-cart').addEventListener('click', () => {
    const spiceId = document.querySelector('.btn-add-cart').dataset.id;
    const qty = parseInt(quantityInput.value) || 1;

    alert(`Added ${qty} x ${selectedSize} of ${spiceId} to your cart!`);
    // TODO: replace with AJAX/PHP code
});

document.querySelectorAll('.size-option').forEach(btn => {
    btn.addEventListener('click', function () {
        let weight = this.getAttribute('data-weight');
        let price = this.getAttribute('data-price');

        document.getElementById('selected_weight').value = weight;
        document.getElementById('selected_price').value = price;

        document.getElementById('display-price').innerHTML = "Rs. " + price;
    });
});

