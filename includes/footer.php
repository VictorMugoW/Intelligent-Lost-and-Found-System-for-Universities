<footer class="footer-custom">
    <div class="container">
        <p class="mb-1">
            <strong>Intelligent Lost and Found System for Universities</strong>
        </p>
        <p class="mb-0">© <?= date('Y') ?> — All Rights Reserved</p>
    </div>
</footer>

<!-- Global Toast Notification Logic (Samsung Style) -->
<script>
function showToast(message, type = 'success') {
    const container = document.getElementById('toastContainer');
    if (!container) return;
    
    // Determine icon based on type
    let icon = 'bi-check-circle-fill';
    if (type === 'danger') icon = 'bi-x-circle-fill';
    else if (type === 'warning') icon = 'bi-exclamation-triangle-fill';
    else if (type === 'info') icon = 'bi-info-circle-fill';

    // Create toast element
    const toastEl = document.createElement('div');
    toastEl.className = `custom-toast toast-${type}`;
    toastEl.innerHTML = `
        <i class="bi ${icon} fs-5"></i>
        <span>${message}</span>
    `;
    
    // Add to container
    container.appendChild(toastEl);
    
    // Remove after 2 seconds (2000ms)
    setTimeout(() => {
        toastEl.classList.add('hiding');
        // Wait for the slideUp animation to finish before removing from DOM
        setTimeout(() => {
            toastEl.remove();
        }, 300); 
    }, 2000);
}
</script>