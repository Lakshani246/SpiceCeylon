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
