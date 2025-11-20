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
