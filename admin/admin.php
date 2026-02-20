<?php
session_start();
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'admin') {
    header('Location: ../login.php');
    exit;
}
?> 
<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Admin Dashboard - Tourist Guide System</title>
    <link rel="stylesheet" href="../style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="admin.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700;800&display=swap" rel="stylesheet">
    <script src="https://cdn.jsdelivr.net/npm/chart.js"></script>
</head>
<body>   
    <div class="sidebar-overlay" id="sidebar-overlay"></div>
<div class="main_box">

    <aside class="sidebar_menu">
        <div class="logo">
            <i class="fas fa-shield-alt"></i>
            <a href="#">Admin Panel</a>
        </div>

        <nav class="menu"> 
            <ul>
                <li class="nav-item active" data-page="dashboard">
                    <i class="fas fa-qrcode"></i> <span>Dashboard</span>
                </li>
                <li class="nav-item" data-page="content">
                    <i class="fas fa-layer-group"></i> <span>Content</span>
                </li>
                <li class="nav-item" data-page="locations">
                    <i class="fas fa-map-location-dot"></i> <span>Locations</span>
                </li>
                <li class="nav-item" data-page="users">
                    <i class="fas fa-users-cog"></i> <span>Users</span>
                </li>
                <li class="nav-item" data-page="approvals">
                    <i class="fas fa-user-check"></i> <span>Approvals</span>
                </li>
                <li class="nav-item" data-page="reports">
                    <i class="fas fa-chart-pie"></i> <span>Reports</span>
                </li>
                <li class="nav-item" data-page="media">
                    <i class="fas fa-photo-video"></i> <span>Media</span>
                </li>

                <li class="nav-item" data-page="logs">
                    <i class="fas fa-clipboard-list"></i> <span>Logs</span>
                </li>

            </ul>
        </nav>
    </aside>

    <main class="content">
        <div class="top_bar">
            <div class="top_left">
                <button class="sidebar_toggle" id="sidebar-toggle" type="button" aria-label="Toggle sidebar">
                    <i class="fas fa-bars"></i>
                </button>
                <div class="search_box">
                    <i class="fas fa-search"></i>
                    <input type="text" id="global-search" placeholder="Search for anything...">
                </div>
            </div>
            <div class="user_actions">
                <div class="icon_wrapper" id="notification-trigger">
                    <i class="fas fa-bell"></i>
                    <span class="badge" id="notification-count">0</span>
                    <div class="dropdown_menu notification_menu" id="notification-dropdown">
                        <div class="dropdown_header">
                            <span class="header_title">Notifications</span>
                            <span class="mark_read" onclick="markAllNotificationsRead()">Mark all as read</span>
                        </div>
                        <ul id="notification-list" class="notification_list">
                            <li class="empty_state">No new notifications</li>
                        </ul>
                        <div class="dropdown_footer">
                            <a href="#" onclick="showPage('logs')">View All Activity</a>
                        </div>
                    </div>
                </div>
                <div class="icon_wrapper" id="message-trigger">
                    <i class="fas fa-envelope"></i>
                    <span class="badge" id="message-count">0</span>
                    <div class="dropdown_menu notification_menu" id="message-dropdown">
                        <div class="dropdown_header">
                            <span class="header_title">Messages</span>
                            <span class="mark_read" onclick="markAllMessagesRead()">Mark all as read</span>
                        </div>
                        <ul id="message-list" class="notification_list">
                            <li class="empty_state">No new messages</li>
                        </ul>
                        <div class="dropdown_footer">
                            <a href="#" onclick="showPage('reports')">View All Messages</a>
                        </div>
                    </div>
                </div>
                <div class="user_profile" id="profile-dropdown-trigger">
                    <div class="avatar_circle">AD</div>
                    <div class="dropdown_menu" id="profile-dropdown">
                        <div class="dropdown_header">
                            <span class="user_name">Admin User</span>
                            <span class="user_role">Administrator</span>
                        </div>
                        <ul>
                            <li onclick="window.showPage('settings')"><i class="fas fa-cog"></i> Settings</li>
                            <li onclick="window.showPage('settings'); setTimeout(() => document.getElementById('2fa-toggle').scrollIntoView({behavior: 'smooth'}), 500);"><i class="fas fa-shield-alt"></i> 2FA Auth</li>
                            <li id="dropdown-logout-btn"><i class="fas fa-sign-out-alt"></i> Logout</li>
                        </ul>
                    </div>
                </div>
            </div>
        </div>

        <!-- DASHBOARD PAGE -->
        <div id="dashboard" class="page active">
            <div class="header">
                <div class="header_content">
                    <h1>Dashboard</h1>
                    <p>Welcome back, Admin. Here's what's happening today.</p>
                </div>
                <div style="position: relative;">
                    <button class="btn_primary" id="dashboard-create-btn"><i class="fas fa-plus"></i> Create New</button>
                    <div class="dropdown_menu" id="create-dropdown" style="top: 120%; right: 0; width: 220px;">
                        <ul>
                            <li onclick="openLocationModal()"><i class="fas fa-map-marker-alt"></i> New Location</li>
                            <li onclick="openFeatureModal()"><i class="fas fa-layer-group"></i> New Content</li>
                            <li onclick="openUserModal()"><i class="fas fa-user-plus"></i> New User</li>
                        </ul>
                    </div>
                </div>
            </div>
            
            <div class="stats_grid">
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-blue"><i class="fas fa-users"></i></div>
                        <span class="stat_trend up"><i class="fas fa-arrow-up"></i> 12%</span>
                    </div>
                    <div>
                        <div class="stat_value" id="total-users">...</div>
                        <div class="stat_label">Total Users</div>
                    </div>
                </div>
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-green"><i class="fas fa-map-marker-alt"></i></div>
                        <span class="stat_trend up"><i class="fas fa-arrow-up"></i> +2</span>
                    </div>
                    <div>
                        <div class="stat_value" id="total-destinations">...</div>
                        <div class="stat_label">Destinations</div>
                    </div>
                </div>
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-orange"><i class="fas fa-calendar-alt"></i></div>
                        <span class="stat_trend neutral"><i class="fas fa-minus"></i> Stable</span>
                    </div>
                    <div>
                        <div class="stat_value" id="total-events">0</div>
                        <div class="stat_label">Active Events</div>
                    </div>
                </div>
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-purple"><i class="fas fa-comments"></i></div>
                        <span class="stat_trend up"><i class="fas fa-arrow-up"></i> 5%</span>
                    </div>
                    <div>
                        <div class="stat_value" id="total-feedback">0</div>
                        <div class="stat_label">New Feedback</div>
                    </div>
                </div>
            </div>

            <div class="dashboard_split">
                <div class="chart_section">
                    <div class="section_header">
                        <h2>User Growth Statistics</h2>
                        <select class="time_filter" style="padding: 8px 12px; border-radius: 8px; border: 1px solid #eee;">
                            <option>This Week</option>
                            <option>This Month</option>
                            <option>This Year</option>
                        </select>
                    </div>
                    <div class="chart_wrapper">
                         <canvas id="userChart"></canvas>
                    </div>
                </div>

                <div class="recent_activity">
                    <div class="section_header">
                        <h2>Recent Activity</h2>
                    </div>
                    <div class="activity_list">
                         <div style="text-align: center; padding: 20px; color: var(--text-light);">
                            <i class="fas fa-spinner fa-spin"></i> Loading activity...
                        </div>
                    </div>
                </div>
            </div>

            <div class="table_section">
                <div class="section_header">
                    <h2>Recent Bookings</h2>
                    <a href="#" style="color: var(--primary-color); font-weight: 600; text-decoration: none;">View All</a>
                </div>
                <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Destination</th>
                                <th>Guests</th>
                                <th>Price</th>
                                <th>Date</th>
                                <th>Status</th>
                            </tr>
                        </thead>
                        <tbody id="bookings-table-body">
                            <!-- Dynamic Content -->
                            <tr>
                                <td colspan="5" style="text-align: center; color: var(--text-light); padding: 20px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading bookings...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- CONTENT PAGE -->
        <div id="content" class="page">
            <div class="header">
                <div class="header_content">
                    <h1>Content Management</h1>
                    <p>Manage website features, articles, and highlights.</p>
                </div>
                <button class="btn_primary" id="add-feature-btn"><i class="fas fa-plus"></i> Add Content</button>
            </div>

            <div class="features_grid" id="features-container">
                <!-- Features will be loaded dynamically -->
                <div class="stat_card" style="grid-column: 1 / -1; display: flex; align-items: center; justify-content: center; height: 200px;">
                    <p style="color: var(--text-light);"><i class="fas fa-spinner fa-spin"></i> Loading content...</p>
                </div>
            </div>
        </div>

        <!-- Add/Edit Feature Modal -->
        <div id="feature-modal" class="modal">
            <div class="modal_content">
                <span class="close_modal" id="close-feature-modal">&times;</span>
                <h2 style="margin-bottom: 20px; color: var(--text-dark);" id="modal-title">Add New Feature</h2>
                <form id="feature-form">
                    <input type="hidden" id="feature-id">
                    <div class="form_group">
                        <label>Title</label>
                        <input type="text" id="feature-title" placeholder="e.g. Local Cuisine" required>
                    </div>
                    <div class="form_group">
                        <label>Description</label>
                        <textarea id="feature-desc" rows="3" placeholder="Short description..." required></textarea>
                    </div>
                    <div class="form_group">
                        <label>Icon Class (FontAwesome)</label>
                        <div style="display: flex; gap: 10px;">
                             <input type="text" id="feature-icon" placeholder="e.g. fas fa-utensils" value="fas fa-star" required>
                             <div id="icon-preview" style="width: 50px; height: 50px; background: var(--bg-color); display: flex; align-items: center; justify-content: center; border-radius: 8px; font-size: 20px; color: var(--primary-color);">
                                <i class="fas fa-star"></i>
                             </div>
                        </div>
                    </div>
                    <button type="submit" class="btn_primary" style="width: 100%; justify-content: center;">Save Feature</button>
                </form>
            </div>
        </div>

        <!-- LOCATIONS PAGE -->
        <div id="locations" class="page">
            <div class="header">
                <div class="header_content">
                    <h1>Locations</h1>
                    <p>Manage travel destinations and categories.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn_outline" onclick="openCategoriesModal()">
                            <i class="fas fa-tags"></i> Categories
                        </button>
                    <button class="btn_primary" id="add-location-btn"><i class="fas fa-plus"></i> Add Location</button>
                </div>
            </div>

            <div class="stats_grid">
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-green"><i class="fas fa-tree"></i></div>
                        <span class="stat_trend up"><i class="fas fa-arrow-up"></i> 1 New</span>
                    </div>
                    <div>
                        <div class="stat_value" id="stat-nature">...</div>
                        <div class="stat_label">Nature Spots</div>
                    </div>
                </div>
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-blue"><i class="fas fa-landmark"></i></div>
                        <span class="stat_trend neutral">Stable</span>
                    </div>
                    <div>
                        <div class="stat_value" id="stat-historical">...</div>
                        <div class="stat_label">Historical</div>
                    </div>
                </div>
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-purple"><i class="fas fa-utensils"></i></div>
                        <span class="stat_trend up"><i class="fas fa-arrow-up"></i> 2 New</span>
                    </div>
                    <div>
                        <div class="stat_value" id="stat-food">...</div>
                        <div class="stat_label">Food & Dining</div>
                    </div>
                </div>
                <div class="stat_card">
                    <div class="stat_header">
                        <div class="stat_icon icon-orange"><i class="fas fa-hot-tub"></i></div>
                        <span class="stat_trend neutral">Popular</span>
                    </div>
                    <div>
                        <div class="stat_value" id="stat-springs">...</div>
                        <div class="stat_label">Hot Springs</div>
                    </div>
                </div>
            </div>

            <div class="table_section">
                <div class="section_header">
                    <h2>All Locations</h2>
                    <div style="display: flex; gap: 10px;">
                        <select style="padding: 8px 12px; border-radius: 8px; border: 1px solid #eee;">
                            <option>All Categories</option>
                            <option>Nature</option>
                            <option>Chill</option>
                            <option>Adventure</option>
                            <option>Food & Dining</option>
                            <option>Historical</option>
                            <option>Hot Springs</option>
                            <option>Parks</option>
                            <option>Museums</option>
                        </select>
                    </div>
                </div>
                <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>Location Name</th>
                                <th>Category</th>
                                <th>Featured</th>
                                <th>Rating</th>
                                <th>Visitors</th>
                                <th>Status</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="locations-table-body">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- USERS PAGE -->
        <div id="users" class="page">
            <div class="header">
                <div class="header_content">
                    <h1>User Management</h1>
                    <p>Manage system access and user roles.</p>
                </div>
                <div style="display: flex; gap: 10px;">
                    <button class="btn_outline" onclick="exportUsers()">
                        <i class="fas fa-download"></i> Export
                    </button>
                    <button class="btn_primary" onclick="openUserModal()"><i class="fas fa-plus"></i> Add User</button>
                </div>
            </div>

            <div class="table_section">
                <div class="section_header">
                    <h2>User Directory</h2>
                    <select style="padding: 8px 12px; border-radius: 8px; border: 1px solid #eee;">
                        <option>All Roles</option>
                        <option>Tourist</option>
                        <option>Admin</option>
                    </select>
                </div>
                <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>User</th>
                                <th>Role</th>
                                <th>Status</th>
                                <th>Join Date</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="users-table-body">
                            <!-- Users will be populated here dynamically -->
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- APPROVALS PAGE -->
        <div id="approvals" class="page">
            <div class="header">
                <div class="header_content">
                    <h1>Business Approvals</h1>
                    <p>Manage pending business owner registrations.</p>
                </div>
            </div>
            <div class="table_section">
                <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>Business Name</th>
                                <th>Owner</th>
                                <th>Address</th>
                                <th>Permit #</th>
                                <th>Contact</th>
                                <th>Actions</th>
                            </tr>
                        </thead>
                        <tbody id="approvals-table-body">
                            <!-- Dynamic -->
                            <tr>
                                <td colspan="6" style="text-align: center; color: var(--text-light); padding: 20px;">
                                    <i class="fas fa-spinner fa-spin"></i> Loading approvals...
                                </td>
                            </tr>
                        </tbody>
                    </table>
                </div>
            </div>
        </div>

        <!-- REPORTS PAGE -->
        <div id="reports" class="page">
             <div class="header">
                <div class="header_content">
                    <h1>Reports & Feedback</h1>
                    <p>View system reports and user feedback.</p>
                </div>
            </div>
            
            <div class="table_section">
                 <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>Date</th>
                                <th>Name</th>
                                <th>Email</th>
                                <th>Message</th>
                            </tr>
                        </thead>
                        <tbody id="messages-table-body">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                 </div>
            </div>
        </div>

        <!-- User Edit Modal -->
<div id="user-modal" class="modal">
    <div class="modal_content">
        <span class="close_modal" onclick="closeUserModal()">&times;</span>
        <div class="section_header">
            <h2 id="user-modal-title">Edit User</h2>
        </div>
        <form id="user-form" onsubmit="event.preventDefault(); saveUser();" style="margin-top: 20px;">
            <input type="hidden" id="user-id">
            
            <div class="form_group">
                <label>Username</label>
                <input type="text" id="user-username" required>
            </div>
            
            <div class="form_group">
                <label>Email</label>
                <input type="email" id="user-email" required>
            </div>
            
            <div class="form_group">
                <label>Role</label>
                <select id="user-role" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                    <option value="user">User</option>
                    <option value="admin">Admin</option>
                    <option value="owner">Owner</option>
                </select>
            </div>
            
            <div class="form_group">
                <label>New Password (leave blank to keep current)</label>
                <input type="password" id="user-password" placeholder="••••••••">
            </div>
            
            <div style="margin-top: 25px; text-align: right;">
                <button type="button" class="btn_secondary" onclick="closeUserModal()" style="margin-right: 10px;">Cancel</button>
                <button type="submit" class="btn_primary">Save User</button>
            </div>
        </form>
    </div>
</div>

<!-- Delete Confirmation Modal -->
<div id="delete-modal" class="modal">
    <div class="modal_content" style="max-width: 400px;">
        <span class="close_modal" onclick="closeDeleteModal()">&times;</span>
        <div class="section_header">
            <h2>Confirm Deletion</h2>
        </div>
        <div style="margin-top: 20px;">
            <p>Are you sure you want to delete this user? This action cannot be undone.</p>
            <input type="hidden" id="delete-user-id">
            <div style="margin-top: 25px; text-align: right;">
                <button type="button" class="btn_secondary" onclick="closeDeleteModal()" style="margin-right: 10px;">Cancel</button>
                <button type="button" class="btn_primary" onclick="confirmDeleteUser()" style="background-color: #e74c3c;">Delete</button>
            </div>
        </div>
    </div>
</div>

<!-- Message View Modal -->
<div id="message-modal" class="modal">
            <div class="modal_content">
                <span class="close_modal" onclick="closeMessageModal()">&times;</span>
                <div class="section_header">
                    <h2>Message Details</h2>
                </div>
                <div id="message-modal-body" style="margin-top: 20px;">
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-light); font-size: 12px; font-weight: 600; text-transform: uppercase;">From</label>
                        <p id="msg-modal-name" style="font-weight: 600; color: var(--text-dark); margin-top: 5px;"></p>
                        <p id="msg-modal-email" style="color: var(--primary-color); font-size: 14px;"></p>
                    </div>
                    <div style="margin-bottom: 15px;">
                        <label style="color: var(--text-light); font-size: 12px; font-weight: 600; text-transform: uppercase;">Date</label>
                        <p id="msg-modal-date" style="color: var(--text-dark); margin-top: 5px;"></p>
                    </div>
                    <div>
                        <label style="color: var(--text-light); font-size: 12px; font-weight: 600; text-transform: uppercase;">Message</label>
                        <div id="msg-modal-content" style="background: var(--bg-color); padding: 15px; border-radius: 8px; margin-top: 5px; color: var(--text-dark); line-height: 1.6; max-height: 300px; overflow-y: auto;"></div>
                    </div>
                </div>
                <div style="margin-top: 25px; text-align: right;">
                    <button class="btn_primary" onclick="closeMessageModal()" style="padding: 10px 25px;">Close</button>
                </div>
            </div>
        </div>
        
        <div id="media" class="page">
             <div class="header">
                <div class="header_content">
                    <h1>Media Management</h1>
                    <p>Manage uploaded images and videos.</p>
                </div>
            </div>
             <div class="stat_card" style="height: 300px; display: flex; align-items: center; justify-content: center;">
                <p style="color: var(--text-light);">Media gallery coming soon.</p>
            </div>
        </div>

        <!-- SETTINGS PAGE -->
        <div id="settings" class="page">
            <div class="header">
                <div class="header_content">
                    <h1>Settings</h1>
                    <p>Manage your account security and system preferences.</p>
                </div>
                <button class="btn_primary" id="save-settings-btn">Save Changes</button>
            </div>

            <div class="settings_grid">
                <!-- Profile Settings -->
                <div class="setting_card">
                    <h3 style="margin-bottom: 25px; font-size: 18px;">Profile Information</h3>
                    <form onsubmit="event.preventDefault();">
                        <div class="form_group">
                            <label>Full Name</label>
                            <input type="text" id="settings-username" placeholder="Loading...">
                        </div>
                        <div class="form_group">
                            <label>Email Address</label>
                            <input type="email" id="settings-email" placeholder="Loading...">
                        </div>
                        <div class="form_group">
                            <label>Bio</label>
                            <textarea rows="4" id="settings-bio" placeholder="Bio not supported in this version..."></textarea>
                        </div>
                    </form>
                </div>

                <!-- Security Settings -->
                <div class="setting_card">
                    <h3 style="margin-bottom: 25px; font-size: 18px;">Security</h3>
                     <div class="form_group">
                        <label>Current Password</label>
                        <input type="password" id="settings-current-password" placeholder="••••••••">
                    </div>
                    <div class="form_group">
                        <label>New Password</label>
                        <input type="password" id="settings-new-password" placeholder="••••••••">
                    </div>
                    
                    <div style="margin-top: 30px; border-top: 1px solid #eee; padding-top: 20px;">
                        <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h4 style="font-size: 15px; margin-bottom: 5px;">Two-Factor Authentication</h4>
                                <p style="font-size: 13px; color: var(--text-light);">Add an extra layer of security</p>
                            </div>
                            <i id="2fa-toggle" class="fas fa-toggle-off" style="font-size: 24px; color: #ccc; cursor: pointer;" onclick="toggle2FA()"></i>
                        </div>
                         <div style="display: flex; justify-content: space-between; align-items: center;">
                            <div>
                                <h4 style="font-size: 15px; margin-bottom: 5px;">Email Notifications</h4>
                                <p style="font-size: 13px; color: var(--text-light);">Receive daily summary emails</p>
                            </div>
                            <i class="fas fa-toggle-off" style="font-size: 24px; color: #ccc; cursor: pointer;"></i>
                        </div>
                    </div>
                </div>
            </div>
        </div>

        <div id="logs" class="page">
            <div class="header">
                <div class="header_content">
                    <h1>Activity Logs</h1>
                    <p>View system activity and audit logs.</p>
                </div>
                <div class="log-tabs">
                    <button class="log-tab active" onclick="showLogType('activity')" id="btn-activity-logs">Activity Logs</button>
                    <button class="log-tab" onclick="showLogType('login')" id="btn-login-logs">Login History</button>
                </div>
            </div>

            <!-- Activity Logs Table -->
            <div class="table_section" id="activity-logs-section">
                 <div class="section_header">
                    <h2>User Activities</h2>
                 </div>
                 <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>Spot</th>
                                <th>Activity Type</th>
                                <th>Date</th>
                            </tr>
                        </thead>
                        <tbody id="logs-table-body">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                 </div>
            </div>

            <!-- Login Logs Table (Hidden by default) -->
            <div class="table_section" id="login-logs-section" style="display: none;">
                 <div class="section_header">
                    <h2>Login Monitoring</h2>
                 </div>
                 <div class="table_responsive">
                    <table class="data_table">
                        <thead>
                            <tr>
                                <th>ID</th>
                                <th>User</th>
                                <th>IP Address</th>
                                <th>Browser / Device</th>
                                <th>Login Time</th>
                            </tr>
                        </thead>
                        <tbody id="login-logs-table-body">
                            <!-- Dynamic Content -->
                        </tbody>
                    </table>
                 </div>
            </div>
        </div>

        <!-- Categories Modal -->
        <div id="categories-modal" class="modal">
            <div class="modal_content">
                <span class="close_modal" onclick="closeCategoriesModal()">&times;</span>
                <h2>Manage Categories</h2>
                <div style="margin-top: 20px;">
                    <div class="form_group" style="display: flex; gap: 10px;">
                        <input type="text" id="new-category-name" placeholder="New Category Name">
                        <button class="btn_primary" onclick="addCategory()" style="padding: 10px 20px; width: auto;">Add</button>
                    </div>
                    <div style="max-height: 300px; overflow-y: auto; margin-top: 15px;">
                        <table class="data_table">
                            <thead>
                                <tr>
                                    <th>Name</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="categories-table-body">
                                <!-- Categories will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
        </div>

        <!-- Location Modal -->
        <div id="location-modal" class="modal">
            <div class="modal_content" style="max-width: 800px;">
                <span class="close_modal" id="close-location-modal">&times;</span>
                <h2 style="margin-bottom: 20px; color: var(--text-dark);" id="location-modal-title">Add New Location</h2>
                <form id="location-form">
                    <input type="hidden" id="location-id">
                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form_group">
                            <label>Name</label>
                            <input type="text" id="location-name" required>
                        </div>
                        <div class="form_group">
                            <label>Category</label>
                            <select id="location-category" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                <option value="Nature">Nature</option>
                                <option value="Chill">Chill</option>
                                <option value="Adventure">Adventure</option>
                                <option value="Food & Dining">Food & Dining</option>
                                <option value="Historical">Historical</option>
                                <option value="Hot Springs">Hot Springs</option>
                                <option value="Parks">Parks</option>
                                <option value="Museums">Museums</option>
                                <option value="Other">Other</option>
                            </select>
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form_group">
                            <label>Type</label>
                            <select id="location-type" required style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
                                <option value="destination">Destination</option>
                                <option value="other">Other</option>
                            </select>
                        </div>
                        <div class="form_group">
                            <label>Location/Address</label>
                            <input type="text" id="location-address" required>
                        </div>
                    </div>

                    <div class="form_group">
                        <label>Description</label>
                        <textarea id="location-desc" rows="3" required></textarea>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                        <div class="form_group">
                            <label>Contact Info</label>
                            <input type="text" id="location-contact">
                        </div>
                        <div class="form_group">
                            <label>Highlights (comma separated)</label>
                            <input type="text" id="location-highlights" placeholder="e.g. Hiking, Swimming, Sightseeing">
                        </div>
                    </div>

                    <div style="display: grid; grid-template-columns: 1fr 1fr 1fr; gap: 20px;">
                        <div class="form_group">
                            <label>Open Time</label>
                            <input type="time" id="location-open">
                        </div>
                        <div class="form_group">
                            <label>Close Time</label>
                            <input type="time" id="location-close">
                        </div>
                        <div class="form_group">
                            <label>Entrance Fee</label>
                            <input type="number" id="location-fee" min="0">
                        </div>
                    </div>

                    <div class="form_group">
                        <label>Image</label>
                        <input type="file" id="location-image" accept="image/*">
                        <div id="current-image-preview" style="margin-top: 10px; display: none;">
                            <p style="font-size: 12px; margin-bottom: 5px;">Current Image:</p>
                            <img id="preview-img" src="" style="max-height: 100px; border-radius: 8px;">
                        </div>
                    </div>

                    <button type="submit" class="btn_primary" style="width: 100%; justify-content: center; margin-top: 10px;">Save Location</button>
                </form>
            </div>
        </div>

    </main>
</div>

<!-- 2FA Setup Modal -->
<div id="2fa-modal" class="modal">
    <div class="modal_content" style="max-width: 400px; text-align: center;">
        <span class="close_btn" onclick="close2FAModal()">&times;</span>
        <h2>Setup Two-Factor Auth</h2>
        <div id="2fa-step-1">
            <p>A verification code has been sent to your email address: <strong id="user-email-display"></strong></p>
            <div style="margin: 20px 0;">
                <i class="fas fa-envelope" style="font-size: 48px; color: var(--primary-color);"></i>
            </div>
            
            <div class="form_group" style="margin-top: 20px;">
                <label>Enter Verification Code</label>
                <input type="text" id="verify-2fa-code" placeholder="123456" style="text-align: center; font-size: 18px; letter-spacing: 5px;" maxlength="6">
            </div>
            
            <button class="btn_primary" onclick="confirm2FA()" style="width: 100%; margin-top: 10px;">Verify & Enable</button>
            <p style="margin-top: 10px; font-size: 12px; color: #777;">Didn't receive the code? <a href="#" onclick="resend2FACode()">Resend</a></p>
        </div>
    </div>
</div>

<!-- Disable 2FA Modal -->
<div id="disable-2fa-modal" class="modal">
    <div class="modal_content" style="max-width: 400px; text-align: center;">
        <span class="close_btn" onclick="closeDisable2FAModal()">&times;</span>
        <h3 style="margin-bottom: 15px; color: var(--text-dark);">Disable Two-Factor Auth</h3>
        <p style="margin-bottom: 20px; color: var(--text-light);">Please enter your password to confirm disabling 2FA.</p>
        
        <div class="form_group">
            <input type="password" id="disable-2fa-password" placeholder="Current Password" style="width: 100%; padding: 10px; border: 1px solid #ddd; border-radius: 8px;">
        </div>
        
        <div style="display: flex; gap: 10px; margin-top: 20px;">
            <button class="btn_primary" onclick="confirmDisable2FA()" style="width: 100%; background: var(--accent-color);">Disable 2FA</button>
        </div>
    </div>
</div>

<!-- LOGOUT CONFIRMATION MODAL -->
    <div id="logout-modal" class="modal">
        <div class="modal_content" style="max-width: 400px;">
            <span class="close_modal" id="close-logout-modal">&times;</span>
            <h3 style="margin-bottom: 15px; color: var(--text-dark);">Confirm Logout</h3>
            <p style="margin-bottom: 25px; color: var(--text-light);">Are you sure you want to log out?</p>
            <div style="display: flex; gap: 15px; justify-content: flex-end;">
                <button id="cancel-logout" style="padding: 10px 24px; border-radius: 50px; border: 1px solid #ddd; background: transparent; cursor: pointer; font-weight: 500; transition: 0.3s;">No</button>
                <button class="btn_primary" id="confirm-logout" style="background: var(--accent-color); box-shadow: 0 4px 15px rgba(255, 71, 87, 0.3);">Yes</button>
            </div>
        </div>
    </div>

    <script src="admin.js"></script>
</body>
</html>
