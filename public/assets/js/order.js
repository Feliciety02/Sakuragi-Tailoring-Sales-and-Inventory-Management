function viewOrder(orderId) {
    document.getElementById('modalOrderId').textContent = orderId;
    const content = document.getElementById('orderModalContent');

    // Show loading indicator
    content.innerHTML = `
        <div class="loading-indicator">
            <div class="spinner"></div>
            <p>Loading order details...</p>
        </div>
    `;

    fetch(`/dashboards/customer/fetch_order_details.php?id=${orderId}`)
        .then(response => {
            if (!response.ok) throw new Error('Failed to fetch');

            return response.json();
        })
        .then(data => {
            if (data.error) throw new Error(data.error);

            if (data.order_details.length === 0) {
                content.innerHTML = `<p>No details found for this order.</p>`;
                return;
            }

            // Build HTML table for order details
            let html = `<table class="order-details-table">
                <thead>
                    <tr>
                        <th>Product</th>
                        <th>Category</th>
                        <th>Quantity</th>
                        <th>Unit Price</th>
                        <th>Subtotal</th>
                    </tr>
                </thead>
                <tbody>`;

            data.order_details.forEach(item => {
                html += `<tr>
                    <td>${item.product_name}</td>
                    <td>${item.category}</td>
                    <td>${item.quantity}</td>
                    <td>₱${parseFloat(item.unit_price).toFixed(2)}</td>
                    <td>₱${parseFloat(item.subtotal).toFixed(2)}</td>
                </tr>`;
            });

            html += `</tbody></table>`;
            content.innerHTML = html;

        })
        .catch(error => {
            content.innerHTML = `<p class="error">Error loading order details: ${error.message}</p>`;
        });

    // Show modal
    document.getElementById('orderModal').style.display = 'block';
}

// Function to close modal
function closeModal() {
    document.getElementById('orderModal').style.display = 'none';
}

// Close modal when clicking outside of it
window.onclick = function(event) {
    const modal = document.getElementById('orderModal');
    if (event.target === modal) {
        closeModal();
    }
}

// Helper function to format dates
function formatDate(dateString) {
    const date = new Date(dateString);
    return date.toLocaleDateString('en-US', { 
        year: 'numeric', 
        month: 'long', 
        day: 'numeric' 
    });
}

// Helper function to get appropriate icon for status
function getStatusIcon(status) {
    switch(status) {
        case 'Pending':
            return '<i class="fas fa-clock"></i>';
        case 'In Progress':
            return '<i class="fas fa-cog fa-spin"></i>';
        case 'Completed':
            return '<i class="fas fa-check-circle"></i>';
        case 'Cancelled':
            return '<i class="fas fa-times-circle"></i>';
        default:
            return '<i class="fas fa-info-circle"></i>';
    }
}