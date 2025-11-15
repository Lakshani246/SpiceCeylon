document.addEventListener("DOMContentLoaded", () => {
    const buttons = document.querySelectorAll(".btn-add-cart");
    buttons.forEach(btn => {
        btn.addEventListener("click", () => {
            const productId = btn.getAttribute("data-id");
            // Show alert or later integrate AJAX for DB
            alert("Product ID " + productId + " added to cart!");
        });
    });
});

// Placeholder for future interactivity, e.g., Add to Cart
document.addEventListener("DOMContentLoaded", () => {
    console.log("Customer home page loaded.");
});

document.addEventListener("DOMContentLoaded", () => {
    const buttons = document.querySelectorAll(".btn-add-cart");

    buttons.forEach(btn => {
        btn.addEventListener("click", () => {
            const productId = btn.getAttribute("data-id");
            const quantityInput = document.querySelector(`.quantity[data-id='${productId}']`);
            const quantity = quantityInput.value;

            // Demo alert
            alert("Product ID " + productId + " added to cart! Quantity: " + quantity);

            // TODO: Implement AJAX POST to cart.php
        });
    });
});
