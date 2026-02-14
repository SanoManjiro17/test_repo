// Sidebar Toggle Functionality
document.addEventListener('DOMContentLoaded', function() {
    // Initialize Popovers
    var popoverTriggerList = [].slice.call(document.querySelectorAll('[data-bs-toggle="popover"]'))
    var popoverList = popoverTriggerList.map(function (popoverTriggerEl) {
        return new bootstrap.Popover(popoverTriggerEl)
    })

    const sidebarToggle = document.getElementById('sidebarToggle');
    const sidebar = document.getElementById('sidebar');
    const mainContent = document.getElementById('mainContent');
    
    // Check initial state for mobile
    if (window.innerWidth <= 768) {
        if(sidebarToggle) {
            sidebarToggle.classList.add('collapsed');
            const icon = sidebarToggle.querySelector('i');
            if(icon) {
                icon.classList.remove('fa-arrow-left');
                icon.classList.add('fa-bars');
            }
        }
    }

    if(sidebarToggle) {
        sidebarToggle.addEventListener('click', function() {
            if (window.innerWidth <= 768) {
                sidebar.classList.toggle('active');
                // Icon logic for mobile
                const icon = sidebarToggle.querySelector('i');
                if (sidebar.classList.contains('active')) {
                    icon.classList.remove('fa-bars');
                    icon.classList.add('fa-times');
                    sidebarToggle.style.left = '270px'; // Move button when sidebar is open
                } else {
                    icon.classList.remove('fa-times');
                    icon.classList.add('fa-bars');
                    sidebarToggle.style.left = '20px'; // Reset button position
                }
            } else {
                sidebar.classList.toggle('hidden');
                mainContent.classList.toggle('expanded');
                sidebarToggle.classList.toggle('collapsed');
                
                // Change icon based on state
                const icon = sidebarToggle.querySelector('i');
                if (sidebar.classList.contains('hidden')) {
                    icon.classList.remove('fa-arrow-left');
                    icon.classList.add('fa-arrow-right');
                } else {
                    icon.classList.remove('fa-arrow-right');
                    icon.classList.add('fa-arrow-left');
                }
            }
        });
    }
});
