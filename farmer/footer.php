                <!-- Footer -->
                <div class="row mt-4">
                    <div class="col-12">
                        <div class="text-center text-muted small">
                            <hr class="my-3">
                            <p class="mb-0">
                                <i class="fas fa-seedling me-1"></i> 
                                SpiceCeylon Farmer Panel v1.0 • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-tractor me-1"></i> 
                                Farm Status: <span class="text-success">Active</span> • 
                                <span class="mx-2">|</span> 
                                <i class="fas fa-box me-1"></i> 
                                Products: <?php echo $total_products; ?> listed, <?php echo $approved_products; ?> active
                            </p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <script src="https://code.jquery.com/jquery-3.6.0.min.js"></script>
    <script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.0/dist/js/bootstrap.bundle.min.js"></script>
    <script>
        // Auto-update time every minute
        function updateTime() {
            const now = new Date();
            const options = { 
                weekday: 'long', 
                year: 'numeric', 
                month: 'long', 
                day: 'numeric' 
            };
            const dateStr = now.toLocaleDateString('en-US', options);
            const timeStr = now.toLocaleTimeString('en-US', { 
                hour: '2-digit', 
                minute: '2-digit',
                hour12: true 
            });
            
            $('.time-display').html(`
                <i class="fas fa-calendar-alt me-1"></i> ${dateStr}
                <span class="mx-2">|</span>
                <i class="fas fa-clock me-1"></i> ${timeStr}
            `);
        }
        
        // Update time every minute
        setInterval(updateTime, 60000);
        
        // Initialize on page load
        $(document).ready(function() {
            updateTime();
            
            // Auto-dismiss alerts after 5 seconds
            setTimeout(function() {
                $('.alert').alert('close');
            }, 5000);
        });
    </script>
</body>
</html>