// --- 2FA Logic ---

let is2FAEnabled = false;

async function check2FAStatus() {
    try {
        const response = await fetch('../api/get_2fa_status.php');
        const result = await response.json();
        
        if (result.success) {
            is2FAEnabled = result.enabled;
            update2FAToggleUI();
        }
    } catch (error) {
        console.warn('Error checking 2FA status:', error);
    }
} 

function update2FAToggleUI() {
    const toggle = document.getElementById('2fa-toggle');
    if (!toggle) return;
    
    if (is2FAEnabled) {
        toggle.className = 'fas fa-toggle-on';
        toggle.style.color = 'var(--primary-color)';
    } else {
        toggle.className = 'fas fa-toggle-off';
        toggle.style.color = '#ccc';
    }
}

async function toggle2FA() {
    if (is2FAEnabled) {
        // Show Disable 2FA Modal instead of prompt
        const modal = document.getElementById('disable-2fa-modal');
        if (modal) {
            document.getElementById('disable-2fa-password').value = ''; // Clear password field
            modal.classList.add('show');
            modal.style.display = 'flex';
        }
    } else {
        // Enable 2FA (Start Setup)
        try {
            const response = await fetch('../api/init_2fa.php');
            const result = await response.json();
            
            if (result.success) {
                // Show modal
                document.getElementById('user-email-display').textContent = result.email;
                
                const modal = document.getElementById('2fa-modal');
                modal.classList.add('show');
                modal.style.display = 'flex';
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error initiating 2FA:', error);
            alert('An error occurred.');
        }
    }
}

async function confirmDisable2FA() {
    const password = document.getElementById('disable-2fa-password').value;
    if (!password) {
        alert("Please enter your password.");
        return;
    }
    
    try {
        const response = await fetch('../api/disable_2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ password })
        });
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            is2FAEnabled = false;
            update2FAToggleUI();
            closeDisable2FAModal();
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error disabling 2FA:', error);
        alert('An error occurred.');
    }
}

function closeDisable2FAModal() {
    const modal = document.getElementById('disable-2fa-modal');
    if (!modal) return;
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
    document.getElementById('disable-2fa-password').value = '';
}

window.resend2FACode = async function() {
    try {
        const response = await fetch('../api/init_2fa.php');
        const result = await response.json();
        
        if (result.success) {
            alert('Code resent successfully!');
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error resending 2FA code:', error);
        alert('An error occurred.');
    }
};

async function confirm2FA() {
    const code = document.getElementById('verify-2fa-code').value;
    if (!code) {
        alert('Please enter the code.');
        return;
    }
    
    try {
        const response = await fetch('../api/confirm_2fa.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ code })
        });
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            is2FAEnabled = true;
            update2FAToggleUI();
            close2FAModal();
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error confirming 2FA:', error);
        alert('An error occurred.');
    }
}

window.close2FAModal = function() {
    const modal = document.getElementById('2fa-modal');
    if (!modal) return;
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
    // Clear input
    document.getElementById('verify-2fa-code').value = '';
};

// --- Search Logic ---
window.performSearch = function() {
    const searchInput = document.getElementById('global-search');
    if (!searchInput) return;

    const term = searchInput.value.toLowerCase();
    const activePage = document.querySelector('.page.active');
    
    if (!activePage) return;

    // Search in tables
    const tables = activePage.querySelectorAll('table');
    tables.forEach(table => {
        const rows = table.querySelectorAll('tbody tr');
        rows.forEach(row => {
            const text = row.textContent.toLowerCase();
            row.style.display = text.includes(term) ? '' : 'none';
        });
    });

    // Search in grids (e.g. Content Cards)
    const cards = activePage.querySelectorAll('.feature_card, .location_card, .user_card, .activity_item'); 
    cards.forEach(card => {
        const text = card.textContent.toLowerCase();
        card.style.display = text.includes(term) ? '' : 'none';
    });
};

// --- Navigation Logic ---
window.showPage = function(pageId) {
    const navItems = document.querySelectorAll('.nav-item');
    const pages = document.querySelectorAll('.page');
    
    // Remove active class from all items and pages
    navItems.forEach(nav => {
        if (nav.dataset.page === pageId) {
            nav.classList.add('active');
        } else {
            nav.classList.remove('active');
        }
    });
    
    pages.forEach(page => page.classList.remove('active'));

    // Show corresponding page with a slight delay for animation
    const targetPage = document.getElementById(pageId);
    if (targetPage) {
        targetPage.classList.add('active');
        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
    }
};

function setSidebarCollapsed(collapsed) {
    document.body.classList.toggle('sidebar-collapsed', collapsed);
    const toggleBtn = document.getElementById('sidebar-toggle');
    if (toggleBtn) {
        const icon = toggleBtn.querySelector('i');
        if (icon) {
            icon.className = collapsed ? 'fas fa-angles-right' : 'fas fa-bars';
        }
        toggleBtn.setAttribute('aria-pressed', collapsed ? 'true' : 'false');
    }
    localStorage.setItem('adminSidebarCollapsed', collapsed ? '1' : '0');
}

document.addEventListener('DOMContentLoaded', () => {
    check2FAStatus();

    // --- Original Admin Logic ---

    // Initial Data Load
    fetchDashboardStats();
    fetchLocations();
    fetchUsers();
    fetchApprovals();
    fetchCurrentSettings();
    fetchFeatures();
    fetchRecentActivity();
    fetchRecentBookings();
    fetchMessages();
    fetchNotifications();
    if (typeof fetchLogs === 'function') {
        fetchLogs();
    } else {
        console.warn('fetchLogs function is missing');
    }

    const savedSidebarState = localStorage.getItem('adminSidebarCollapsed') === '1';
    // Only apply collapsed state on desktop
    if (window.innerWidth > 768) {
        setSidebarCollapsed(savedSidebarState);
    }

    const sidebarToggle = document.getElementById('sidebar-toggle');
    const sidebarMenu = document.querySelector('.sidebar_menu');
    const sidebarOverlay = document.getElementById('sidebar-overlay');

    if (sidebarToggle) {
        sidebarToggle.addEventListener('click', (e) => {
            e.stopPropagation();
            if (window.innerWidth <= 768) {
                // Mobile Toggle
                if (sidebarMenu) sidebarMenu.classList.toggle('active');
                if (sidebarOverlay) sidebarOverlay.classList.toggle('active');
            } else {
                // Desktop Toggle
                setSidebarCollapsed(!document.body.classList.contains('sidebar-collapsed'));
            }
        });
    }

    // Close sidebar when clicking overlay
    if (sidebarOverlay) {
        sidebarOverlay.addEventListener('click', () => {
            if (sidebarMenu) sidebarMenu.classList.remove('active');
            sidebarOverlay.classList.remove('active');
        });
    }

    // Close sidebar when clicking a menu item on mobile
    const menuItems = document.querySelectorAll('.menu li');
    menuItems.forEach(item => {
        item.addEventListener('click', () => {
            if (window.innerWidth <= 768) {
                if (sidebarMenu) sidebarMenu.classList.remove('active');
                if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            }
        });
    });

    // Handle resize
    window.addEventListener('resize', () => {
        if (window.innerWidth > 768) {
            if (sidebarMenu) sidebarMenu.classList.remove('active');
            if (sidebarOverlay) sidebarOverlay.classList.remove('active');
            // Restore desktop state
            setSidebarCollapsed(localStorage.getItem('adminSidebarCollapsed') === '1');
        } else {
            // Remove desktop class on mobile
            document.body.classList.remove('sidebar-collapsed');
        }
    });

    // Navigation Logic
    const navItems = document.querySelectorAll('.nav-item');
    
    navItems.forEach(item => {
        item.addEventListener('click', () => {
            const pageId = item.dataset.page;
            if (pageId) {
                window.showPage(pageId);
            }
        });
    });

    // Chart.js Configuration
    initChart();

    // Settings Save Button
    const saveBtn = document.getElementById('save-settings-btn');
    if (saveBtn) {
        saveBtn.addEventListener('click', updateSettings);
    }

    // Feature Modal Events
    const addFeatureBtn = document.getElementById('add-feature-btn');
    const featureModal = document.getElementById('feature-modal');
    const closeFeatureModal = document.getElementById('close-feature-modal');
    const featureForm = document.getElementById('feature-form');
    const featureIconInput = document.getElementById('feature-icon');

    if (addFeatureBtn) {
        addFeatureBtn.addEventListener('click', () => openFeatureModal());
    }

    if (closeFeatureModal) {
        closeFeatureModal.addEventListener('click', () => {
            featureModal.classList.remove('show');
            setTimeout(() => { featureModal.style.display = 'none'; }, 300);
        });
    }

    // Close feature modal on outside click
    window.addEventListener('click', (e) => {
        if (e.target === featureModal) {
            featureModal.classList.remove('show');
            setTimeout(() => { featureModal.style.display = 'none'; }, 300);
        }
    });

    if (featureForm) {
        featureForm.addEventListener('submit', saveFeature);
    }

    if (featureIconInput) {
        featureIconInput.addEventListener('input', (e) => {
            const iconPreview = document.getElementById('icon-preview');
            iconPreview.innerHTML = `<i class="${e.target.value}"></i>`;
        });
    }

    // --- Location Modal Events ---
    const addLocationBtn = document.getElementById('add-location-btn');
    const locationModal = document.getElementById('location-modal');
    const closeLocationModalBtn = document.getElementById('close-location-modal');
    const locationForm = document.getElementById('location-form');

    if (addLocationBtn) {
        addLocationBtn.addEventListener('click', () => openLocationModal());
    }

    if (closeLocationModalBtn) {
        closeLocationModalBtn.addEventListener('click', () => {
            locationModal.classList.remove('show');
            setTimeout(() => { locationModal.style.display = 'none'; }, 300);
        });
    }

    // Close location modal on outside click
    window.addEventListener('click', (e) => {
        if (e.target === locationModal) {
            locationModal.classList.remove('show');
            setTimeout(() => { locationModal.style.display = 'none'; }, 300);
        }
    });

    if (locationForm) {
        locationForm.addEventListener('submit', saveLocation);
    }

    // --- Global Search Logic ---
    const searchInput = document.getElementById('global-search');
    if (searchInput) {
        searchInput.addEventListener('input', () => {
            window.performSearch();
        });
    }

    // --- Dashboard Create New Dropdown ---
    const createBtn = document.getElementById('dashboard-create-btn');
    const createDropdown = document.getElementById('create-dropdown');

    if (createBtn && createDropdown) {
        createBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            createDropdown.classList.toggle('show');
        });

        window.addEventListener('click', (e) => {
            if (!createBtn.contains(e.target) && !createDropdown.contains(e.target)) {
                createDropdown.classList.remove('show');
            }
        });
    }
});

async function fetchApprovals() {
    const tbody = document.getElementById('approvals-table-body');
    if (!tbody) return;
    
    tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-light); padding: 20px;"><i class="fas fa-spinner fa-spin"></i> Loading approvals...</td></tr>';
    
    try {
        const response = await fetch('../api/get_pending_users.php');
        const users = await response.json();
        
        tbody.innerHTML = '';
        
        if (users.length === 0) {
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--text-light); padding: 20px;">No pending approvals found.</td></tr>';
            return;
        }
        
        users.forEach(user => {
            const tr = document.createElement('tr');
            tr.innerHTML = `
                <td><strong>${user.business_name || 'N/A'}</strong></td>
                <td>
                    <div style="display: flex; flex-direction: column;">
                        <span style="font-weight: 500;">${user.username}</span>
                        <span style="font-size: 12px; color: #888;">${user.email}</span>
                    </div>
                </td>
                <td>${user.business_address || 'N/A'}</td>
                <td>${user.permit_number || 'N/A'}</td>
                <td>${user.contact_number || 'N/A'}</td>
                <td>
                    <div style="display: flex; gap: 10px;">
                        <button class="btn_icon" onclick="handleApproval(${user.id}, 'approve')" style="color: #2ecc71; border: 1px solid #2ecc71; padding: 5px; border-radius: 4px;" title="Approve">
                            <i class="fas fa-check"></i>
                        </button>
                        <button class="btn_icon" onclick="handleApproval(${user.id}, 'reject')" style="color: #e74c3c; border: 1px solid #e74c3c; padding: 5px; border-radius: 4px;" title="Reject">
                            <i class="fas fa-times"></i>
                        </button>
                    </div>
                </td>
            `;
            tbody.appendChild(tr);
        });
    } catch (error) {
        console.error('Error fetching approvals:', error);
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: #e74c3c; padding: 20px;">Error loading data.</td></tr>';
    }
}

async function handleApproval(userId, action) {
    const actionText = action === 'approve' ? 'approve' : 'reject';
    if (!confirm(`Are you sure you want to ${actionText} this user?`)) return;
    
    try {
        const response = await fetch('../api/approve_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ user_id: userId, action: action })
        });
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            fetchApprovals(); // Refresh list
            if (typeof fetchUsers === 'function') fetchUsers(); // Refresh users list too if needed
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error processing approval:', error);
        alert('An error occurred.');
    }
}

let userGrowthChart = null;

async function initChart(filter = 'week') {
    const ctx = document.getElementById('userChart');
    if (ctx) {
        try {
            const response = await fetch(`../api/get_user_growth.php?filter=${filter}`);
            const result = await response.json();
            
            const labels = result.labels || ['Mon', 'Tue', 'Wed', 'Thu', 'Fri', 'Sat', 'Sun'];
            const data = result.data || [0, 0, 0, 0, 0, 0, 0];
            
            const gradient = ctx.getContext('2d').createLinearGradient(0, 0, 0, 400);
            gradient.addColorStop(0, 'rgba(255, 107, 107, 0.5)');
            gradient.addColorStop(1, 'rgba(255, 107, 107, 0.0)');

            // Destroy existing chart if it exists
            if (userGrowthChart) {
                userGrowthChart.destroy();
            }

            userGrowthChart = new Chart(ctx, {
                type: 'line',
                data: {
                    labels: labels,
                    datasets: [{
                        label: 'User Growth',
                        data: data,
                        backgroundColor: gradient,
                        borderColor: '#ff6b6b',
                        borderWidth: 3,
                        pointBackgroundColor: '#ffffff',
                        pointBorderColor: '#ff6b6b',
                        pointBorderWidth: 2,
                        pointRadius: 6,
                        pointHoverRadius: 8,
                        fill: true,
                        tension: 0.4
                    }]
                },
                options: {
                    responsive: true,
                    maintainAspectRatio: false,
                    plugins: {
                        legend: {
                            display: false
                        },
                        tooltip: {
                            backgroundColor: '#fff',
                            titleColor: '#2d3436',
                            bodyColor: '#636e72',
                            borderColor: '#f0f0f0',
                            borderWidth: 1,
                            padding: 12,
                            boxPadding: 6,
                            usePointStyle: true,
                            titleFont: {
                                family: 'Poppins',
                                size: 13
                            },
                            bodyFont: {
                                family: 'Poppins',
                                size: 13
                            }
                        }
                    },
                    scales: {
                        y: {
                            beginAtZero: true,
                            grid: {
                                color: '#f0f0f0',
                                borderDash: [5, 5],
                                drawBorder: false
                            },
                            ticks: {
                                color: '#b2bec3',
                                font: {
                                    family: 'Poppins'
                                },
                                stepSize: 1
                            }
                        },
                        x: {
                            grid: {
                                display: false
                            },
                            ticks: {
                                color: '#b2bec3',
                                font: {
                                    family: 'Poppins'
                                }
                            }
                        }
                    },
                    interaction: {
                        intersect: false,
                        mode: 'index',
                    },
                }
            });
        } catch (error) {
            console.warn('Error initializing chart:', error);
        }
    }
}

// Event Listener for Chart Filter
document.addEventListener('DOMContentLoaded', () => {
    const timeFilter = document.querySelector('.time_filter');
    if (timeFilter) {
        timeFilter.addEventListener('change', (e) => {
            const value = e.target.value;
            let filter = 'week';
            if (value === 'This Month') filter = 'month';
            if (value === 'This Year') filter = 'year';
            initChart(filter);
        });
    }
});

async function fetchRecentBookings() {
    try {
        const response = await fetch('../api/get_recent_bookings.php');
        const result = await response.json();
        const tbody = document.getElementById('bookings-table-body');
        
        if (!tbody) return;
        
        if (!result.success || !result.data || result.data.length === 0) {
            tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-light); padding: 20px;">No recent bookings found.</td></tr>`;
            return;
        }
        
        tbody.innerHTML = '';
        
        result.data.forEach(booking => {
            const row = document.createElement('tr');
            const date = new Date(booking.activity_date).toLocaleDateString();
            
            // Handle null values
            const username = booking.username || 'Unknown User';
            const spotName = booking.spot_name || 'Unknown Spot';
            
            // Generate initials
            const initials = username !== 'Unknown User' ? username.substring(0, 2).toUpperCase() : '??';
            const avatarColor = '#3498db'; // Can be randomized if needed

            let statusClass = 'pending';
            if (booking.status === 'Confirmed') statusClass = 'active';
            if (booking.status === 'Cancelled') statusClass = 'blocked';
            if (booking.status === 'Completed') statusClass = 'active'; // or another color

            row.innerHTML = `
                <td>#${booking.id}</td>
                <td>
                    <div class="user_cell">
                         <div style="background: ${avatarColor}; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 12px; margin-right: 10px;">${initials}</div>
                        <span>${username}</span>
                    </div>
                </td>
                <td>${spotName}</td>
                <td>${(parseInt(booking.num_adults) || 0) + (parseInt(booking.num_children) || 0)} (${booking.num_adults || 0}A, ${booking.num_children || 0}C)</td>
                <td>₱${parseFloat(booking.total_price || 0).toLocaleString(undefined, {minimumFractionDigits: 2})}</td>
                <td>${booking.display_date}</td>
                <td><span class="status-badge ${statusClass}">${booking.status}</span></td>
            `;
            tbody.appendChild(row);
        });
        
        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
        
    } catch (error) {
        console.warn('Error fetching bookings:', error);
        const tbody = document.getElementById('bookings-table-body');
        if (tbody) tbody.innerHTML = `<tr><td colspan="5" style="text-align: center; color: var(--text-light); padding: 20px;">Error loading data.</td></tr>`;
    }
}

// --- Messages / Reports Logic ---

window.markNotificationRead = function(element, type) {
    // Visual update
    if (element.classList.contains('unread')) {
        element.classList.remove('unread');
        
        // Update badge count
        const badgeId = type === 'message' ? 'message-count' : 'notification-count';
        const badge = document.getElementById(badgeId);
        if (badge) {
            let count = parseInt(badge.textContent) || 0;
            if (count > 0) {
                badge.textContent = count - 1;
            }
        }
    }
};

async function fetchMessages() {
    try {
        const response = await fetch('../api/get_messages.php');
        const result = await response.json();
        
        const tbody = document.getElementById('messages-table-body');
        const dropdownList = document.getElementById('message-list');
        const badge = document.getElementById('message-count');
        
        if (!result.success || !result.data) {
            if (tbody) tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--text-light);">No messages found.</td></tr>`;
            if (dropdownList) dropdownList.innerHTML = '<li class="empty_state">No new messages</li>';
            if (badge) badge.textContent = '0';
            return;
        }
        
        // Update Badge
        if (badge) badge.textContent = result.data.length;
        
        // Update Dropdown
        if (dropdownList) {
            dropdownList.innerHTML = '';
            const recent = result.data.slice(0, 5);
            
            if (recent.length === 0) {
                 dropdownList.innerHTML = '<li class="empty_state">No new messages</li>';
            } else {
                recent.forEach(msg => {
                    const li = document.createElement('li');
                    li.className = 'unread';
                    
                    // Escape for onclick
                    const msgJson = JSON.stringify(msg).replace(/"/g, '&quot;');
                    
                    // Add click handler to mark as read
                    li.onclick = function() {
                        markNotificationRead(this, 'message');
                        openMessageModal(msg); // Pass the object directly since we're inside the loop scope
                    };
                    
                    li.innerHTML = `
                        <div class="notification_icon" style="background: rgba(52, 152, 219, 0.1); color: #3498db;">
                            <i class="fas fa-envelope"></i>
                        </div>
                        <div class="notification_content">
                            <span class="notification_title">${msg.name}</span>
                            <span class="notification_desc">${msg.message.substring(0, 40)}${msg.message.length > 40 ? '...' : ''}</span>
                            <span class="notification_time">${new Date(msg.created_at).toLocaleTimeString()}</span>
                        </div>
                    `;
                    dropdownList.appendChild(li);
                });
            }
        }
        
        // Update Table
        if (tbody) {
            if (result.data.length === 0) {
                tbody.innerHTML = `<tr><td colspan="4" style="text-align: center; color: var(--text-light);">No messages found.</td></tr>`;
            } else {
                tbody.innerHTML = '';
                result.data.forEach(msg => {
                    const row = document.createElement('tr');
                    const date = new Date(msg.created_at).toLocaleDateString();
                    const preview = msg.message.length > 50 ? msg.message.substring(0, 50) + '...' : msg.message;
                    const msgJson = JSON.stringify(msg).replace(/"/g, '&quot;');
                    
                    row.innerHTML = `
                        <td>${date}</td>
                        <td><span style="font-weight: 600;">${msg.name}</span></td>
                        <td>${msg.email}</td>
                        <td>
                            <div style="display: flex; justify-content: space-between; align-items: center;">
                                <span style="color: var(--text-light);">${preview}</span>
                                <button class="action-btn" onclick="openMessageModal(${msgJson})">
                                    <i class="fas fa-eye"></i>
                                </button>
                            </div>
                        </td>
                    `;
                    tbody.appendChild(row);
                });
            }
        }
        
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
        
    } catch (error) {
        console.warn('Error fetching messages:', error);
    }
}

async function fetchNotifications() {
    try {
        // We use activity logs as notifications for now
        const response = await fetch('../api/get_activity_logs.php');
        const result = await response.json();
        const list = document.getElementById('notification-list');
        const badge = document.getElementById('notification-count');
        
        if (!list || !result.success || !result.data) return;

        // Filter for recent "important" logs or just take top 5
        const recent = result.data.slice(0, 5);
        
        if (badge) badge.textContent = recent.length;

        list.innerHTML = '';
        if (recent.length === 0) {
            list.innerHTML = '<li class="empty_state">No new notifications</li>';
            if (badge) badge.textContent = '0';
            return;
        }

        recent.forEach(log => {
            const li = document.createElement('li');
            li.className = 'unread'; 
            
            let icon = 'fas fa-info';
            let color = '#95a5a6';
            let bg = 'rgba(149, 165, 166, 0.1)';
            
            const type = log.activity_type.toLowerCase();
            if (type.includes('login')) { icon = 'fas fa-sign-in-alt'; color = '#3498db'; bg = 'rgba(52, 152, 219, 0.1)'; }
            else if (type.includes('create') || type.includes('add')) { icon = 'fas fa-plus'; color = '#2ecc71'; bg = 'rgba(46, 204, 113, 0.1)'; }
            else if (type.includes('delete') || type.includes('remove')) { icon = 'fas fa-trash'; color = '#e74c3c'; bg = 'rgba(231, 76, 60, 0.1)'; }
            else if (type.includes('update') || type.includes('edit')) { icon = 'fas fa-pen'; color = '#f39c12'; bg = 'rgba(243, 156, 18, 0.1)'; }

            // Add click handler to mark as read
            li.onclick = function() {
                markNotificationRead(this, 'notification');
                // Optional: navigate to relevant page based on notification type
                // showPage('logs');
            };

            li.innerHTML = `
                <div class="notification_icon" style="color: ${color}; background: ${bg};">
                    <i class="${icon}"></i>
                </div>
                <div class="notification_content">
                    <span class="notification_title">${log.activity_type}</span>
                    <span class="notification_desc">${log.username} - ${log.spot_name || 'System'}</span>
                    <span class="notification_time">${new Date(log.activity_date).toLocaleTimeString()}</span>
                </div>
            `;
            list.appendChild(li);
        });

    } catch (e) {
        console.warn('Error fetching notifications', e);
    }
}

window.markAllNotificationsRead = function() {
    const badge = document.getElementById('notification-count');
    const list = document.getElementById('notification-list');
    if (badge) badge.textContent = '0';
    if (list) {
        const items = list.querySelectorAll('li.unread');
        items.forEach(item => {
            item.classList.remove('unread');
            item.style.opacity = '0.6';
        });
    }
};

window.markAllMessagesRead = function() {
    const badge = document.getElementById('message-count');
    const list = document.getElementById('message-list');
    if (badge) badge.textContent = '0';
    if (list) {
        const items = list.querySelectorAll('li.unread');
        items.forEach(item => {
            item.classList.remove('unread');
            item.style.opacity = '0.6';
        });
    }
};

window.openMessageModal = function(msg) {
    const modal = document.getElementById('message-modal');
    if (!modal) return;
    
    document.getElementById('msg-modal-name').textContent = msg.name;
    document.getElementById('msg-modal-email').textContent = msg.email;
    document.getElementById('msg-modal-date').textContent = new Date(msg.created_at).toLocaleString();
    document.getElementById('msg-modal-content').textContent = msg.message;
    
    modal.classList.add('show');
    modal.style.display = 'flex';
};

window.closeMessageModal = function() {
    const modal = document.getElementById('message-modal');
    if (!modal) return;
    
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
};

// Close message modal on outside click
window.addEventListener('click', (e) => {
    const modal = document.getElementById('message-modal');
    if (modal && e.target === modal) {
        window.closeMessageModal();
    }
});

// --- Dashboard Logic ---
let categories = [];

function openCategoriesModal() {
    const modal = document.getElementById('categories-modal');
    if (modal) {
        modal.classList.add('show');
        modal.style.display = 'flex';
        fetchCategories();
    }
}

function closeCategoriesModal() {
    const modal = document.getElementById('categories-modal');
    if (modal) {
        modal.classList.remove('show');
        setTimeout(() => { modal.style.display = 'none'; }, 300);
    }
}

async function fetchCategories() {
    try {
        const response = await fetch('../api/get_categories.php');
        const result = await response.json();
        
        if (result.success) {
            categories = result.data;
            renderCategoriesTable();
        }
    } catch (error) {
        console.warn('Error fetching categories:', error);
        // Fallback if API fails, render empty or use mock data
        renderCategoriesTable();
    }
}

function renderCategoriesTable() {
    const tbody = document.getElementById('categories-table-body');
    if (!tbody) return;
    
    tbody.innerHTML = '';
    
    if (!categories || categories.length === 0) {
        tbody.innerHTML = '<tr><td colspan="2" style="text-align: center; color: var(--text-light); padding: 20px;">No categories found</td></tr>';
        return;
    }
    
    categories.forEach(cat => {
        const row = document.createElement('tr');
        row.innerHTML = `
            <td><strong>${cat.name}</strong></td>
            <td>
                <button class="action_btn delete" onclick="deleteCategory(${cat.id})" title="Delete Category">
                    <i class="fas fa-trash"></i>
                </button>
            </td>
        `;
        tbody.appendChild(row);
    });
}

async function addCategory() {
    const input = document.getElementById('new-category-name');
    const name = input.value.trim();
    
    if (!name) return;
    
    try {
        const response = await fetch('../api/add_category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name })
        });
        const result = await response.json();
        
        if (result.success) {
            input.value = '';
            fetchCategories();
            // Refresh locations dropdown if needed (optional)
        } else {
            alert(result.message || 'Failed to add category');
        }
    } catch (error) {
        console.error('Error adding category:', error);
    }
}

async function deleteCategory(id) {
    if (!confirm('Are you sure you want to delete this category?')) return;
    
    try {
        const response = await fetch('../api/delete_category.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id })
        });
        const result = await response.json();
        
        if (result.success) {
            fetchCategories();
        } else {
            alert(result.message || 'Failed to delete category');
        }
    } catch (error) {
        console.error('Error deleting category:', error);
    }
}

// --- Dynamic Data Functions ---

async function fetchDashboardStats() {
    try {
        const response = await fetch('../api/get_dashboard_stats.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const data = await response.json();
        
        // Helper to animate numbers
        const animateValue = (id, start, end, duration) => {
            const obj = document.getElementById(id);
            if (!obj) return;
            let startTimestamp = null;
            const step = (timestamp) => {
                if (!startTimestamp) startTimestamp = timestamp;
                const progress = Math.min((timestamp - startTimestamp) / duration, 1);
                obj.innerHTML = Math.floor(progress * (end - start) + start);
                if (progress < 1) {
                    window.requestAnimationFrame(step);
                } else {
                    obj.innerHTML = end; // Ensure final value is exact
                }
            };
            window.requestAnimationFrame(step);
        };

        // Update Dashboard Main Stats with animation
        animateValue('total-destinations', 0, data.total_destinations || 0, 1000);
        animateValue('total-users', 0, data.total_users || 0, 1000);
        animateValue('total-events', 0, data.active_events || 0, 1000);
        animateValue('total-feedback', 0, data.new_feedback || 0, 1000);
        
        // Update Locations Tab Stats
        const statNature = document.getElementById('stat-nature');
        const statHistorical = document.getElementById('stat-historical');
        const statFood = document.getElementById('stat-food');
        const statSprings = document.getElementById('stat-springs');
        
        if (statNature) statNature.textContent = data.by_category?.nature || 0;
        if (statHistorical) statHistorical.textContent = data.by_category?.historical || 0;
        if (statFood) statFood.textContent = data.by_category?.food || 0;
        if (statSprings) statSprings.textContent = data.by_category?.springs || 0;

    } catch (error) {
        console.warn('Could not fetch dashboard stats (API might be missing):', error);
    }
}

// --- Location Logic ---

function openLocationModal(spot = null) {
    const modal = document.getElementById('location-modal');
    const title = document.getElementById('location-modal-title');
    const form = document.getElementById('location-form');
    
    // Reset form
    form.reset();
    document.getElementById('location-id').value = '';
    document.getElementById('current-image-preview').style.display = 'none';

    if (spot) {
        // Edit Mode
        title.textContent = 'Edit Location';
        document.getElementById('location-id').value = spot.id;
        document.getElementById('location-name').value = spot.name;
        document.getElementById('location-type').value = spot.type || 'destination';
        document.getElementById('location-category').value = spot.category;
        document.getElementById('location-desc').value = spot.description;
        document.getElementById('location-address').value = spot.location;
        document.getElementById('location-contact').value = spot.contact || '';
        document.getElementById('location-open').value = spot.openTime || '';
        document.getElementById('location-close').value = spot.closeTime || '';
        document.getElementById('location-fee').value = spot.entranceFee || '';
        
        // Handle highlights (array or string)
        let highlightsStr = '';
        if (Array.isArray(spot.highlights)) {
            highlightsStr = spot.highlights.join(', ');
        } else if (typeof spot.highlights === 'string') {
            // It might be a JSON string or just a string
            try {
                const parsed = JSON.parse(spot.highlights);
                if (Array.isArray(parsed)) highlightsStr = parsed.join(', ');
                else highlightsStr = spot.highlights;
            } catch (e) {
                highlightsStr = spot.highlights;
            }
        }
        document.getElementById('location-highlights').value = highlightsStr;

        if (spot.image) {
            const preview = document.getElementById('current-image-preview');
            const img = document.getElementById('preview-img');
            preview.style.display = 'block';
            img.src = '../' + spot.image;
        }
    } else {
        // Add Mode
        title.textContent = 'Add New Location';
    }

    modal.classList.add('show');
    modal.style.display = 'flex';
}

async function saveLocation(e) {
    e.preventDefault();
    
    const id = document.getElementById('location-id').value;
    const formData = new FormData();
    
    formData.append('name', document.getElementById('location-name').value);
    formData.append('type', document.getElementById('location-type').value);
    formData.append('category', document.getElementById('location-category').value);
    formData.append('description', document.getElementById('location-desc').value);
    formData.append('location', document.getElementById('location-address').value);
    formData.append('contact', document.getElementById('location-contact').value);
    formData.append('openTime', document.getElementById('location-open').value);
    formData.append('closeTime', document.getElementById('location-close').value);
    formData.append('entranceFee', document.getElementById('location-fee').value);
    formData.append('highlights', document.getElementById('location-highlights').value);
    
    const imageFile = document.getElementById('location-image').files[0];
    if (imageFile) {
        formData.append('image', imageFile);
    }

    const endpoint = id ? '../api/update_spot.php' : '../api/add_spot.php';
    if (id) formData.append('id', id);

    try {
        const response = await fetch(endpoint, {
            method: 'POST',
            body: formData
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            document.getElementById('location-modal').classList.remove('show');
            setTimeout(() => { document.getElementById('location-modal').style.display = 'none'; }, 300);
            fetchLocations(); // Refresh list
            fetchDashboardStats(); // Refresh stats
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving location:', error);
        alert('An error occurred while saving.');
    }
}

async function fetchLocations() {
    try {
        const response = await fetch('../api/get_spots.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const spots = await response.json();
        const tbody = document.getElementById('locations-table-body');
        
        if (!tbody) return;
        
        tbody.innerHTML = ''; // Clear existing rows
        
        spots.forEach(spot => {
            const row = document.createElement('tr');
            
            // Determine category badge color
            let badgeClass = 'neutral';
            let cat = spot.category.toLowerCase();
            
            if (cat.includes('nature')) badgeClass = 'active'; // Green
            else if (cat.includes('history') || cat.includes('historical')) badgeClass = 'pending'; // Yellow
            else if (cat.includes('spring')) badgeClass = 'blocked'; // Red
            else if (cat.includes('food')) badgeClass = 'pending'; // Yellow for food

            // Escape spot data for onclick
            const spotJson = JSON.stringify(spot).replace(/"/g, '&quot;').replace(/'/g, '&#39;');

            // Format rating
            const rating = spot.average_rating ? parseFloat(spot.average_rating).toFixed(1) : '0.0';

            // Featured Status
            const isFeatured = spot.featured == 1;
            const starClass = isFeatured ? 'fas fa-star' : 'far fa-star';
            const starColor = isFeatured ? '#f1c40f' : '#bdc3c7';

            row.innerHTML = `
                <td>
                    <div class="user_cell">
                        <img src="../${spot.image}" alt="${spot.name}" onerror="this.src='https://via.placeholder.com/40'">
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <span style="font-weight: 600;">${spot.name}</span>
                            <small style="color: var(--text-light); font-size: 11px;">${spot.location}</small>
                        </div>
                    </div>
                </td>
                <td><span class="status-badge ${badgeClass}">${spot.category}</span></td>
                <td style="text-align: center;">
                    <button class="action-btn" onclick="toggleFeatured(${spot.id}, ${spot.featured || 0})" title="Toggle Featured" style="background: none; border: none; cursor: pointer;">
                        <i class="${starClass}" style="color: ${starColor}; font-size: 18px;"></i>
                    </button>
                </td>
                <td><div style="display: flex; align-items: center; gap: 5px;"><i class="fas fa-star" style="color: #f1c40f;"></i> ${rating}</div></td>
                <td>--</td>
                <td><span class="status-badge active">Active</span></td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <button class="action-btn" onclick="openLocationModal(${spotJson})"><i class="fas fa-pen"></i></button>
                        <button class="action-btn delete" onclick="deleteSpot(${spot.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            `;
            
            tbody.appendChild(row);
        });
        
        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
        
    } catch (error) {
        console.warn('Could not fetch locations:', error);
    }
}

async function deleteSpot(id) {
    if (!confirm('Are you sure you want to delete this spot?')) return;
    
    try {
        const response = await fetch('../api/delete_spot.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            // Show toast or alert
            alert('Spot deleted successfully');
            fetchLocations(); // Refresh list
            fetchDashboardStats(); // Refresh stats
        } else {
            alert('Error deleting spot: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting spot:', error);
        alert('An error occurred');
    }
}

async function toggleFeatured(id, currentStatus) {
    const newStatus = currentStatus == 1 ? 0 : 1;
    
    try {
        const response = await fetch('../api/toggle_featured.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id, featured: newStatus })
        });
        
        const result = await response.json();
        
        if (result.success) {
            fetchLocations(); // Refresh list
        } else {
            alert('Error toggling featured status: ' + result.message);
        }
    } catch (error) {
        console.error('Error toggling featured status:', error);
        alert('An error occurred.');
    }
}

async function fetchUsers() {
    try {
        const response = await fetch('../api/get_users.php');
        if (!response.ok) throw new Error('Network response was not ok');
        const users = await response.json();
        const tbody = document.getElementById('users-table-body');

        if (!tbody) return;

        tbody.innerHTML = '';

        users.forEach(user => {
            const row = document.createElement('tr');
            
            // Generate initials
            const initials = user.username ? user.username.substring(0, 2).toUpperCase() : '??';
            
            // Format date
            let joinDate = 'N/A';
            if (user.reg_date) {
                joinDate = new Date(user.reg_date).toLocaleDateString('en-US', {
                    year: 'numeric',
                    month: 'short',
                    day: 'numeric'
                });
            }

            // Role badge
            const role = user.role ? user.role.toLowerCase() : 'user';
            let badgeClass = 'active'; // Default green
            if (role === 'admin') badgeClass = 'blocked'; // Red for admin
            else if (role === 'owner') badgeClass = 'pending'; // Yellow for owner

            // Random background color for avatar based on ID
            const colors = ['#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#2ecc71', '#34495e'];
            const avatarColor = colors[(user.id || 0) % colors.length];

            const userJson = JSON.stringify(user).replace(/"/g, '&quot;');

            row.innerHTML = `
                <td>
                    <div class="user_cell">
                        <div style="background: ${avatarColor}; color: white; width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 14px;">${initials}</div>
                        <div style="display: flex; flex-direction: column; gap: 2px;">
                            <span style="font-weight: 600;">${user.username || 'Unknown'}</span>
                            <small style="color: var(--text-light); font-size: 11px;">${user.email || ''}</small>
                        </div>
                    </div>
                </td>
                <td><span class="status-badge ${badgeClass}">${role.charAt(0).toUpperCase() + role.slice(1)}</span></td>
                <td><span class="status-badge active">Active</span></td>
                <td>${joinDate}</td>
                <td>
                    <div style="display: flex; gap: 8px;">
                        <button class="action-btn" onclick="openUserModal(${userJson})"><i class="fas fa-pen"></i></button>
                        <button class="action-btn delete" onclick="openDeleteModal(${user.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </td>
            `;
            tbody.appendChild(row);
        });

        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }

    } catch (error) {
        console.warn('Could not fetch users:', error);
    }
}

// --- User Management Logic ---

async function exportUsers() {
    try {
        const response = await fetch('../api/get_users.php');
        const users = await response.json();
        
        if (!users || users.length === 0) {
            alert('No users to export.');
            return;
        }

        // CSV Header
        let csvContent = "data:text/csv;charset=utf-8,";
        csvContent += "ID,Username,Email,Role,Registration Date,Status\n";

        // CSV Rows
        users.forEach(user => {
            const row = [
                user.id,
                `"${user.username}"`, // Quote strings to handle commas
                `"${user.email}"`,
                user.role,
                user.reg_date,
                'Active' // Assuming active as default status logic
            ].join(",");
            csvContent += row + "\n";
        });

        // Download Trigger
        const encodedUri = encodeURI(csvContent);
        const link = document.createElement("a");
        link.setAttribute("href", encodedUri);
        link.setAttribute("download", "users_export_" + new Date().toISOString().slice(0,10) + ".csv");
        document.body.appendChild(link); // Required for FF
        link.click();
        document.body.removeChild(link);

    } catch (error) {
        console.error('Error exporting users:', error);
        alert('Failed to export users.');
    }
}

function openUserModal(user = null) {
    const modal = document.getElementById('user-modal');
    const title = document.getElementById('user-modal-title');
    const form = document.getElementById('user-form');
    
    // Reset form
    form.reset();
    document.getElementById('user-id').value = '';
    
    if (user) {
        // Edit Mode
        title.textContent = 'Edit User';
        document.getElementById('user-id').value = user.id;
        document.getElementById('user-username').value = user.username;
        document.getElementById('user-email').value = user.email;
        document.getElementById('user-role').value = user.role || 'user';
    } else {
        // Add Mode (if we were to support adding users directly here)
        title.textContent = 'Add User';
    }
    
    modal.classList.add('show');
    modal.style.display = 'flex';
}

function closeUserModal() {
    const modal = document.getElementById('user-modal');
    if (!modal) return;
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

async function saveUser() {
    const id = document.getElementById('user-id').value;
    const username = document.getElementById('user-username').value;
    const email = document.getElementById('user-email').value;
    const role = document.getElementById('user-role').value;
    const password = document.getElementById('user-password').value;
    
    // Validation for new user
    if (!id && !password) {
        alert("Password is required for new users.");
        return;
    }

    const url = id ? '../api/update_user.php' : '../api/signup.php'; 
    
    const data = {
        username: username,
        email: email,
        role: role,
        password: password
    };
    if (id) data.id = id;

    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeUserModal();
            fetchUsers();
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error saving user:', error);
        alert('An error occurred.');
    }
}

function openDeleteModal(id) {
    document.getElementById('delete-user-id').value = id;
    const modal = document.getElementById('delete-modal');
    modal.classList.add('show');
    modal.style.display = 'flex';
}

function closeDeleteModal() {
    const modal = document.getElementById('delete-modal');
    if (!modal) return;
    modal.classList.remove('show');
    setTimeout(() => { modal.style.display = 'none'; }, 300);
}

async function confirmDeleteUser() {
    const id = document.getElementById('delete-user-id').value;
    if (!id) return;

    try {
        const response = await fetch('../api/delete_user.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            closeDeleteModal();
            fetchUsers();
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error deleting user:', error);
        alert('An error occurred.');
    }
}

async function fetchCurrentSettings() {
    try {
        const response = await fetch('../api/get_current_user.php');
        if (!response.ok) return; // Might be logged out or error
        const data = await response.json();
        
        if (data.success) {
            const user = data.user;
            const usernameInput = document.getElementById('settings-username');
            const emailInput = document.getElementById('settings-email');
            
            if (usernameInput) usernameInput.value = user.username || '';
            if (emailInput) emailInput.value = user.email || '';
        }
    } catch (error) {
        console.warn('Could not fetch settings:', error);
    }
}

async function updateSettings() {
    const saveBtn = document.getElementById('save-settings-btn');
    const originalText = saveBtn.innerHTML;
    saveBtn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Saving...';
    saveBtn.disabled = true;

    const username = document.getElementById('settings-username').value;
    const email = document.getElementById('settings-email').value;
    const currentPassword = document.getElementById('settings-current-password').value;
    const newPassword = document.getElementById('settings-new-password').value;

    try {
        const response = await fetch('../api/update_settings.php', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
            },
            body: JSON.stringify({
                username: username,
                email: email,
                current_password: currentPassword,
                new_password: newPassword
            })
        });

        const result = await response.json();

        if (result.success) {
            alert(result.message);
            // Clear password fields
            document.getElementById('settings-current-password').value = '';
            document.getElementById('settings-new-password').value = '';
        } else {
            alert('Error: ' + result.message);
        }

    } catch (error) {
        console.error('Error updating settings:', error);
        alert('An unexpected error occurred.');
    } finally {
        saveBtn.innerHTML = originalText;
        saveBtn.disabled = false;
    }
}

// --- Features Management ---

async function fetchFeatures() {
    try {
        const response = await fetch('../api/get_features.php');
        const features = await response.json();
        const container = document.getElementById('features-container');
        
        if (!container) return;
        
        // Handle error or non-array response
        if (!Array.isArray(features)) {
            console.warn('Features response is not an array:', features);
            container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px;">No content available or error loading.</div>';
            return;
        }
        
        container.innerHTML = '';
        
        if (features.length === 0) {
            container.innerHTML = '<div style="grid-column: 1/-1; text-align: center; padding: 20px;">No features found. Click "Add Content" to start.</div>';
            return;
        }
        
        features.forEach(feature => {
            const card = document.createElement('div');
            card.className = 'feature_card';
            
            // Random color class for variety (or based on id)
            const colors = ['icon-red', 'icon-green', 'icon-blue', 'icon-purple', 'icon-orange'];
            const colorClass = colors[(feature.id || 0) % colors.length];
            
            // Escape special chars for JSON in onclick
            const featureJson = JSON.stringify(feature).replace(/"/g, '&quot;');
            
            card.innerHTML = `
                <div class="feature_header">
                    <div class="feature_icon ${colorClass}">
                        <i class="${feature.icon}"></i>
                    </div>
                    <div class="feature_actions">
                        <button class="action-btn" onclick="openFeatureModal(${featureJson})"><i class="fas fa-pen"></i></button>
                        <button class="action-btn delete" onclick="deleteFeature(${feature.id})"><i class="fas fa-trash"></i></button>
                    </div>
                </div>
                <div class="feature_content">
                    <h3>${feature.title}</h3>
                    <p style="color: var(--text-light); line-height: 1.6;">${feature.description}</p>
                    <div style="margin-top: 15px; display: flex; justify-content: space-between; align-items: center;">
                        <span class="status-badge active">Active</span>
                        <span style="font-size: 12px; color: var(--text-lighter);">Updated recently</span>
                    </div>
                </div>
            `;
            container.appendChild(card);
        });
        
        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
        
    } catch (error) {
        console.error('Error fetching features:', error);
    }
}

function openFeatureModal(feature = null) {
    const modal = document.getElementById('feature-modal');
    const modalTitle = document.getElementById('modal-title');
    const form = document.getElementById('feature-form');
    
    // Reset form
    form.reset();
    document.getElementById('icon-preview').innerHTML = '<i class="fas fa-star"></i>';
    
    if (feature) {
        modalTitle.textContent = 'Edit Feature';
        document.getElementById('feature-id').value = feature.id;
        document.getElementById('feature-title').value = feature.title;
        document.getElementById('feature-desc').value = feature.description;
        document.getElementById('feature-icon').value = feature.icon;
        document.getElementById('icon-preview').innerHTML = `<i class="${feature.icon}"></i>`;
    } else {
        modalTitle.textContent = 'Add New Feature';
        document.getElementById('feature-id').value = '';
    }
    
    modal.style.display = 'flex';
    // Trigger reflow
    void modal.offsetWidth;
    modal.classList.add('show');
}

async function saveFeature(e) {
    e.preventDefault();
    
    const id = document.getElementById('feature-id').value;
    const title = document.getElementById('feature-title').value;
    const description = document.getElementById('feature-desc').value;
    const icon = document.getElementById('feature-icon').value;
    
    const url = id ? '../api/update_feature.php' : '../api/add_feature.php';
    const data = { title, description, icon };
    if (id) data.id = id;
    
    try {
        const response = await fetch(url, {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(data)
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
            // Close modal
            const modal = document.getElementById('feature-modal');
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
            
            // Refresh list
            fetchFeatures();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error saving feature:', error);
    }
}

async function deleteFeature(id) {
    if (!confirm('Are you sure you want to delete this feature?')) return;
    
    try {
        const response = await fetch('../api/delete_feature.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ id: id })
        });
        
        const result = await response.json();
        
        if (result.success) {
            fetchFeatures();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (error) {
        console.error('Error deleting feature:', error);
    }
}

// Expose to window for HTML onclick attributes
window.showLogType = function(type) {
    const activitySection = document.getElementById('activity-logs-section');
    const loginSection = document.getElementById('login-logs-section');
    const btnActivity = document.getElementById('btn-activity-logs');
    const btnLogin = document.getElementById('btn-login-logs');

    if (type === 'activity') {
        activitySection.style.display = 'block';
        loginSection.style.display = 'none';
        btnActivity.classList.add('active');
        btnLogin.classList.remove('active');
    } else {
        activitySection.style.display = 'none';
        loginSection.style.display = 'block';
        btnActivity.classList.remove('active');
        btnLogin.classList.add('active');
    }
};

async function fetchLogs() {
    fetchActivityLogs();
    fetchLoginLogs();
}

async function fetchActivityLogs() {
    try {
        const response = await fetch('../api/get_activity_logs.php');
        const result = await response.json();
        
        if (!result.success) return;
        
        const tbody = document.getElementById('logs-table-body');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        result.data.forEach(log => {
            const row = document.createElement('tr');
            
            // Badge Logic
            let badgeClass = 'other';
            const type = log.activity_type.toLowerCase();
            if (type.includes('login') || type.includes('logout')) badgeClass = 'login';
            else if (type.includes('create') || type.includes('add')) badgeClass = 'create';
            else if (type.includes('update') || type.includes('edit')) badgeClass = 'update';
            else if (type.includes('delete') || type.includes('remove')) badgeClass = 'delete';

            // User Avatar Logic
            const username = log.username || 'Guest';
            const initials = username.substring(0, 2).toUpperCase();
            const colors = ['#3498db', '#9b59b6', '#e74c3c', '#f1c40f', '#2ecc71', '#34495e'];
            const avatarColor = colors[(log.id || 0) % colors.length];

            row.innerHTML = `
                <td><span style="color: var(--text-light); font-size: 13px;">#${log.id}</span></td>
                <td>
                    <div class="user_cell">
                        <div style="background: ${avatarColor}; color: white; width: 32px; height: 32px; border-radius: 50%; display: flex; align-items: center; justify-content: center; font-weight: 700; font-size: 11px;">${initials}</div>
                        <span style="font-weight: 600; font-size: 13px;">${username}</span>
                    </div>
                </td>
                <td><span style="color: var(--text-dark);">${log.spot_name || 'N/A'}</span></td>
                <td><span class="log-badge ${badgeClass}">${log.activity_type}</span></td>
                <td><span style="color: var(--text-light); font-size: 13px;">${new Date(log.activity_date).toLocaleString()}</span></td>
            `;
            tbody.appendChild(row);
        });

        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }

    } catch (error) {
        console.warn('Error fetching activity logs:', error);
    }
}

async function fetchLoginLogs() {
    try {
        const response = await fetch('../api/get_login_logs.php');
        const result = await response.json();
        
        if (!result.success) return;
        
        const tbody = document.getElementById('login-logs-table-body');
        if (!tbody) return;
        
        tbody.innerHTML = '';
        
        result.data.forEach(log => {
            const row = document.createElement('tr');
            // Simplified user agent
            let device = 'Unknown';
            const ua = log.user_agent || '';
            if (ua.includes('Windows')) device = 'Windows PC';
            else if (ua.includes('Macintosh')) device = 'Mac';
            else if (ua.includes('Android')) device = 'Android';
            else if (ua.includes('iPhone')) device = 'iPhone';
            else if (ua.length > 20) device = 'Other Device';
            else device = ua;
            
            row.innerHTML = `
                <td>#${log.id}</td>
                <td>
                    <div class="user_cell">
                        <span style="font-weight: 600;">${log.username || 'Unknown'}</span>
                    </div>
                </td>
                <td>${log.ip_address || 'N/A'}</td>
                <td>${device}</td>
                <td>${new Date(log.login_time).toLocaleString()}</td>
            `;
            tbody.appendChild(row);
        });

        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }

    } catch (error) {
        console.warn('Error fetching login logs:', error);
    }
}

async function fetchRecentActivity() {
    try {
        const response = await fetch('../api/get_recent_activity.php');
        const result = await response.json();
        const activityList = document.querySelector('.activity_list');
        
        if (!activityList) return;
        
        if (!result.success || !result.data || result.data.length === 0) {
            activityList.innerHTML = `
                <div style="text-align: center; padding: 20px; color: var(--text-light);">
                    <i class="fas fa-info-circle"></i> No recent activity found.
                </div>
            `;
            return;
        }
        
        activityList.innerHTML = '';
        
        result.data.forEach(item => {
            const activityItem = document.createElement('div');
            activityItem.className = 'activity_item';
            
            // Icon based on activity type
            let iconClass = 'fas fa-circle';
            let iconColor = 'var(--text-light)';
            
            if (item.activity_type.includes('login')) {
                iconClass = 'fas fa-sign-in-alt';
                iconColor = '#3498db';
            } else if (item.activity_type.includes('review')) {
                iconClass = 'fas fa-star';
                iconColor = '#f1c40f';
            } else if (item.activity_type.includes('booking')) {
                iconClass = 'fas fa-ticket-alt';
                iconColor = '#e67e22';
            }
            
            // Format time ago
            const date = new Date(item.activity_date);
            const now = new Date();
            const diffInSeconds = Math.floor((now - date) / 1000);
            let timeAgo = '';
            
            if (diffInSeconds < 60) timeAgo = 'Just now';
            else if (diffInSeconds < 3600) timeAgo = `${Math.floor(diffInSeconds / 60)}m ago`;
            else if (diffInSeconds < 86400) timeAgo = `${Math.floor(diffInSeconds / 3600)}h ago`;
            else timeAgo = `${Math.floor(diffInSeconds / 86400)}d ago`;
            
            activityItem.innerHTML = `
                <div class="activity_icon" style="background: rgba(0,0,0,0.05); width: 40px; height: 40px; border-radius: 50%; display: flex; align-items: center; justify-content: center; color: ${iconColor};">
                    <i class="${iconClass}"></i>
                </div>
                <div class="activity_details" style="flex: 1; margin-left: 15px;">
                    <p style="margin: 0; font-weight: 500; font-size: 14px;">
                        <span style="font-weight: 600;">${item.username || 'User'}</span> 
                        ${item.activity_type} 
                        <span style="font-weight: 600;">${item.spot_name || ''}</span>
                    </p>
                    <small style="color: var(--text-light); font-size: 12px;">${timeAgo}</small>
                </div>
            `;
            
            // Add some basic styling for the item if not present in CSS
            activityItem.style.display = 'flex';
            activityItem.style.alignItems = 'center';
            activityItem.style.padding = '15px 0';
            activityItem.style.borderBottom = '1px solid #f0f0f0';
            
            activityList.appendChild(activityItem);
        });
        
        // Re-apply search filter
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }
        
    } catch (error) {
        console.warn('Error fetching recent activity:', error);
        const activityList = document.querySelector('.activity_list');
        if (activityList) {
            activityList.innerHTML = `
                <div style="text-align: center; padding: 20px; color: var(--text-light);">
                    <i class="fas fa-exclamation-triangle"></i> Error loading activity.
                </div>
            `;
        }
    }
}

// --- Logout Logic ---
document.addEventListener('DOMContentLoaded', () => {
    // Shared Logout Modal Logic
    const logoutModal = document.getElementById('logout-modal');
    const closeLogoutModal = document.getElementById('close-logout-modal');
    const cancelLogout = document.getElementById('cancel-logout');
    const confirmLogout = document.getElementById('confirm-logout');

    if (logoutModal) {
        // Close functions
        const closeModal = () => {
            logoutModal.classList.remove('show');
            setTimeout(() => {
                logoutModal.style.display = 'none';
            }, 300); // Wait for transition
        };

        if (closeLogoutModal) closeLogoutModal.addEventListener('click', closeModal);
        if (cancelLogout) cancelLogout.addEventListener('click', closeModal);

        // Click outside to close
        window.addEventListener('click', (e) => {
            if (e.target === logoutModal) {
                closeModal();
            }
        });

        if (confirmLogout) {
            confirmLogout.addEventListener('click', () => {
                window.location.href = '../logout.php';
            });
        }
    }
});

// --- Dropdown Management (Profile, Notifications, Messages) ---
function setupDropdowns() {
    const triggers = [
        { trigger: 'notification-trigger', dropdown: 'notification-dropdown' },
        { trigger: 'message-trigger', dropdown: 'message-dropdown' },
        { trigger: 'profile-dropdown-trigger', dropdown: 'profile-dropdown' }
    ];

    triggers.forEach(({ trigger, dropdown }) => {
        const triggerEl = document.getElementById(trigger);
        const dropdownEl = document.getElementById(dropdown);

        if (triggerEl && dropdownEl) {
            triggerEl.addEventListener('click', (e) => {
                e.stopPropagation();
                // Close others
                triggers.forEach(t => {
                    if (t.dropdown !== dropdown) {
                        const d = document.getElementById(t.dropdown);
                        if (d) d.classList.remove('show');
                    }
                });
                dropdownEl.classList.toggle('show');
            });
            
            // Prevent dropdown from closing when clicking inside it
            dropdownEl.addEventListener('click', (e) => {
                e.stopPropagation();
            });
        }
    });

    // Close all on outside click
    window.addEventListener('click', () => {
        triggers.forEach(({ dropdown }) => {
            const d = document.getElementById(dropdown);
            if (d) d.classList.remove('show');
        });
    });
}

document.addEventListener('DOMContentLoaded', () => {
    setupDropdowns();

    // Handle Dropdown Logout
    const dropdownLogoutBtn = document.getElementById('dropdown-logout-btn');
    const logoutModal = document.getElementById('logout-modal');
    const profileDropdown = document.getElementById('profile-dropdown');

    if (dropdownLogoutBtn && logoutModal) {
        dropdownLogoutBtn.addEventListener('click', (e) => {
            e.stopPropagation();
            if (profileDropdown) profileDropdown.classList.remove('show');
            
            logoutModal.classList.add('show');
            logoutModal.style.display = 'flex';
        });
    }
});
