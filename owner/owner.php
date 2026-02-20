<?php
session_start();
require_once '../db_connect.php';

if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'business_owner') {
    header("Location: ../login.php");
    exit();
}

// Fetch 2FA status
$stmt = $conn->prepare("SELECT two_factor_enabled FROM users WHERE id = ?");
$stmt->bind_param("i", $_SESSION['user_id']);
$stmt->execute();
$result = $stmt->get_result();
$user_data = $result->fetch_assoc();
$two_factor_enabled = $user_data['two_factor_enabled'] ?? 0;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Business Owner Dashboard - Tourist Guide System</title>
    <link rel="stylesheet" href="../style.css"> <!-- Global styles -->
    <link rel="stylesheet" href="owner.css">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.4.0/css/all.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Poppins:wght@300;400;500;600;700&display=swap" rel="stylesheet">
</head>
<body>
    <div class="main_box">
        <!-- Sidebar -->
        <aside class="sidebar_menu">
            <div class="logo">
                <i class="fas fa-briefcase"></i>
                <a href="#">Owner Panel</a>
            </div>

            <nav class="menu">
                <ul>
                    <li class="nav-item active" data-page="dashboard">
                        <i class="fas fa-chart-line"></i> <span>Dashboard</span>
                    </li>
                    <li class="nav-item" data-page="my-business">
                        <i class="fas fa-store"></i> <span>My Business</span>
                    </li>
                    <li class="nav-item" data-page="my-spots">
                        <i class="fas fa-map-marked-alt"></i> <span>My Spots</span>
                    </li>
                    <li class="nav-item" data-page="bookings">
                        <i class="fas fa-calendar-check"></i> <span>Bookings</span>
                    </li>
                    <li class="nav-item" data-page="reviews">
                        <i class="fas fa-star"></i> <span>Reviews</span>
                    </li>
                </ul>
            </nav>
        </aside>

        <!-- Main Content -->
        <main class="content">
            <!-- Top Bar -->
            <div class="top_bar">
                <div class="search_box">
                    <i class="fas fa-search"></i>
                    <input type="text" placeholder="Search...">
                </div>
                <div class="user_actions">
                    <div class="theme-toggle">
                        <input type="checkbox" id="theme-switch" class="theme-checkbox">
                        <label for="theme-switch" class="theme-label">
                            <i class="fas fa-moon"></i>
                            <i class="fas fa-sun"></i>
                            <div class="ball"></div>
                        </label>
                    </div>
                    
                    <div class="user_profile" id="profile-dropdown-trigger">
                        <img src="https://ui-avatars.com/api/?name=<?php echo urlencode($_SESSION['username']); ?>&background=random" alt="Profile">
                        <div class="dropdown_menu" id="profile-dropdown">
                            <div class="dropdown_header">
                                <span class="user_name"><?php echo htmlspecialchars($_SESSION['username']); ?></span>
                                <span class="user_role">Business Owner</span>
                            </div>
                            <ul>
                                <li onclick="window.showPage('my-business')"><i class="fas fa-store"></i> My Business</li>
                                <li onclick="window.showPage('settings')"><i class="fas fa-cog"></i> Settings</li>
                                <li onclick="openLogoutModal()"><i class="fas fa-sign-out-alt"></i> Logout</li>
                            </ul>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Dashboard Page -->
            <div id="dashboard" class="page active">
                <div class="header">
                    <div class="header_content">
                        <h1>Dashboard</h1>
                        <p>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>. Here's your business overview.</p>
                    </div>
                </div>
                
                <div class="stats_grid">
                    <div class="stat_card">
                        <div class="stat_header">
                            <div class="stat_icon icon-blue"><i class="fas fa-map-marked-alt"></i></div>
                            <span class="stat_trend up"><i class="fas fa-arrow-up"></i> Active</span>
                        </div>
                        <div>
                            <div class="stat_value" id="total-spots">0</div>
                            <div class="stat_label">Total Spots</div>
                        </div>
                    </div>
                    <div class="stat_card">
                        <div class="stat_header">
                            <div class="stat_icon icon-orange"><i class="fas fa-star"></i></div>
                            <span class="stat_trend up">Avg</span>
                        </div>
                        <div>
                            <div class="stat_value" id="avg-rating">0.0</div>
                            <div class="stat_label">Average Rating</div>
                        </div>
                    </div>
                    <div class="stat_card">
                        <div class="stat_header">
                            <div class="stat_icon icon-purple"><i class="fas fa-comments"></i></div>
                            <span class="stat_trend neutral">Total</span>
                        </div>
                        <div>
                            <div class="stat_value" id="total-reviews">0</div>
                            <div class="stat_label">Total Reviews</div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- My Business Page -->
            <div id="my-business" class="page">
                <div class="header">
                    <div class="header_content">
                        <h1>My Business Profile</h1>
                        <p>Manage your business information.</p>
                    </div>
                </div>
                <div class="setting_card">
                    <form id="business-profile-form">
                        <div class="form_group">
                            <label>Business Name</label>
                            <input type="text" id="business-name" name="business_name" required>
                        </div>
                        <div class="form_group">
                            <label>Business Address</label>
                            <textarea id="business-address" name="business_address" rows="3"></textarea>
                        </div>
                        <div class="form_group">
                            <label>Permit Number</label>
                            <input type="text" id="permit-number" name="permit_number">
                        </div>
                        <div class="form_group">
                            <label>Contact Number</label>
                            <input type="text" id="contact-number" name="contact_number">
                        </div>
                        <button type="submit" class="btn_primary" style="margin-top: 20px;">Save Changes</button>
                    </form>
                </div>
            </div>

            <!-- My Spots Page -->
            <div id="my-spots" class="page">
                <div class="header">
                    <div class="header_content">
                        <h1>My Tourist Spots</h1>
                        <p>Manage the spots you own.</p>
                    </div>
                    <button class="btn_primary" id="btn-add-spot"><i class="fas fa-plus"></i> Add New Spot</button>
                </div>
                <div class="table_section">
                    <div class="table_responsive">
                        <table class="data_table">
                            <thead>
                                <tr>
                                    <th>Image</th>
                                    <th>Name</th>
                                    <th>Type</th>
                                    <th>Rating</th>
                                    <th>Status</th>
                                    <th>Actions</th>
                                </tr>
                            </thead>
                            <tbody id="spots-table-body">
                                <!-- Spots will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>
            
            <!-- Bookings Page -->
            <div id="bookings" class="page">
                <div class="header">
                    <div class="header_content">
                        <h1>Bookings</h1>
                        <p>View and manage bookings for your spots.</p>
                    </div>
                </div>
                <div class="table_section">
                    <div class="table_responsive">
                        <table class="data_table">
                            <thead>
                                <tr>
                                    <th>Booking ID</th>
                                    <th>Guest Name</th>
                                    <th>Spot</th>
                                    <th>Date</th>
                                    <th>Contact</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="bookings-table-body">
                                <!-- Bookings will be loaded here -->
                            </tbody>
                        </table>
                    </div>
                </div>
            </div>

            <!-- Reviews Page -->
            <div id="reviews" class="page">
                <div class="header">
                    <div class="header_content">
                        <h1>Customer Reviews</h1>
                        <p>See what people are saying about your spots.</p>
                    </div>
                </div>
                <div class="reviews-container" id="reviews-list">
                    <!-- Reviews will be loaded here -->
                </div>
            </div>

            <!-- Settings Page -->
            <div id="settings" class="page">
                <div class="header">
                    <div class="header_content">
                        <h1>Account Settings</h1>
                        <p>Manage your security and preferences.</p>
                    </div>
                </div>
                
                <div class="settings_grid">
                    <!-- Account Information -->
                    <div class="setting_card">
                        <h3 style="margin-bottom: 20px; color: var(--text-dark);">Account Information</h3>
                        <form id="account-info-form">
                            <div class="form_group">
                                <label>Email Address</label>
                                <input type="email" name="email" value="<?php echo htmlspecialchars($_SESSION['email'] ?? ''); ?>" required style="margin-bottom: 10px;">
                            </div>
                            <div class="form_group">
                                <label>Confirm Password</label>
                                <div style="display: flex; gap: 10px;">
                                    <input type="password" name="password" placeholder="Enter password to confirm" required style="flex: 1;">
                                    <button type="submit" class="btn_primary" style="white-space: nowrap;">Update Email</button>
                                </div>
                                <small style="color: var(--text-light); margin-top: 5px; display: block;">Note: This will update your login email.</small>
                            </div>
                        </form>
                    </div>

                    <!-- Security Settings -->
                    <div class="setting_card">
                        <h3 style="margin-bottom: 20px; color: var(--text-dark);">Security Settings</h3>
                        
                        <div class="notification-item" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                            <div>
                                <h4 style="margin: 0; font-size: 16px; color: var(--text-dark);">Two-Factor Authentication</h4>
                                <p style="margin: 5px 0 0; font-size: 13px; color: var(--text-light);">Secure your account with 2FA.</p>
                            </div>
                            <label class="switch">
                                <input type="checkbox" id="2fa-toggle" <?php echo ($two_factor_enabled ? 'checked' : ''); ?> onchange="toggle2FA(this)">
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>

                    <!-- Change Password -->
                    <div class="setting_card">
                        <h3 style="margin-bottom: 20px; color: var(--text-dark);">Change Password</h3>
                        <form id="change-password-form">
                            <div class="form_group">
                                <label>Current Password</label>
                                <input type="password" name="current_password" required>
                            </div>
                            <div class="form_group">
                                <label>New Password</label>
                                <input type="password" name="new_password" required minlength="6">
                            </div>
                            <div class="form_group">
                                <label>Confirm New Password</label>
                                <input type="password" name="confirm_password" required minlength="6">
                            </div>
                            <button type="submit" class="btn_primary">Update Password</button>
                        </form>
                    </div>

                    <!-- Notification Preferences -->
                    <div class="setting_card">
                        <h3 style="margin-bottom: 20px; color: var(--text-dark);">Notification Preferences</h3>
                        <form id="notification-form">
                            <div class="notification-item" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <div>
                                    <h4 style="margin: 0; font-size: 16px; color: var(--text-dark);">Email Notifications</h4>
                                    <p style="margin: 5px 0 0; font-size: 13px; color: var(--text-light);">Receive emails about new bookings.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <div class="notification-item" style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
                                <div>
                                    <h4 style="margin: 0; font-size: 16px; color: var(--text-dark);">Review Alerts</h4>
                                    <p style="margin: 5px 0 0; font-size: 13px; color: var(--text-light);">Get notified when someone reviews your spot.</p>
                                </div>
                                <label class="switch">
                                    <input type="checkbox" checked>
                                    <span class="slider round"></span>
                                </label>
                            </div>
                            <button type="button" class="btn_primary" onclick="alert('Preferences saved!')">Save Preferences</button>
                        </form>
                    </div>
                </div>
            </div>

        </main>
    </div>

    <!-- Add/Edit Spot Modal -->
    <div id="spot-modal" class="modal">
        <div class="modal_content">
            <span class="close_modal">&times;</span>
            <h2 id="modal-title" style="margin-bottom: 20px;">Add New Spot</h2>
            <form id="spot-form" enctype="multipart/form-data">
                <input type="hidden" id="spot-id" name="spot_id">
                <div class="form_group">
                    <label>Spot Name</label>
                    <input type="text" id="spot-name" name="name" required>
                </div>
                <div class="form_group">
                    <label>Type</label>
                    <select id="spot-type" name="type" required>
                        <option value="Nature">Nature</option>
                        <option value="Historical">Historical</option>
                        <option value="Adventure">Adventure</option>
                        <option value="Cultural">Cultural</option>
                        <option value="Food">Food</option>
                    </select>
                </div>
                <div class="form_group">
                    <label>Description</label>
                    <textarea id="spot-description" name="description" rows="4" required></textarea>
                </div>
                
                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form_group">
                        <label>Opening Time</label>
                        <input type="time" id="spot-open" name="openTime">
                    </div>
                    <div class="form_group">
                        <label>Closing Time</label>
                        <input type="time" id="spot-close" name="closeTime">
                    </div>
                </div>

                <div style="display: grid; grid-template-columns: 1fr 1fr; gap: 20px;">
                    <div class="form_group">
                        <label>Entrance Fee</label>
                        <input type="number" id="spot-fee" name="entranceFee" min="0" step="0.01">
                    </div>
                    <div class="form_group">
                        <label>Contact Number</label>
                        <input type="text" id="spot-contact" name="contact">
                    </div>
                </div>

                <div class="form_group">
                    <label>Highlights (Comma separated)</label>
                    <input type="text" id="spot-highlights" name="highlights" placeholder="e.g. Free Wifi, Parking, Guided Tours">
                </div>

                <div class="form_group">
                    <label>Image</label>
                    <input type="file" id="spot-image" name="image" accept="image/*">
                </div>
                <div class="form_group">
                    <label>Location (Map URL)</label>
                    <input type="text" id="spot-location" name="location">
                </div>
                <button type="submit" class="btn_primary" style="width: 100%; margin-top: 15px;">Save Spot</button>
            </form>
        </div>
    </div>

    <!-- Enable 2FA Modal (Password Confirmation) -->
    <div id="enable-2fa-modal" class="modal">
        <div class="modal_content small">
            <span class="close_modal" onclick="close2FAModals()">&times;</span>
            <div style="text-align: center">
                <i class="fas fa-shield-alt" style="font-size: 48px; color: var(--accent-color); margin-bottom: 20px;"></i>
                <h2 style="margin-bottom: 10px;">Enable 2FA</h2>
                <p style="color: var(--text-light); margin-bottom: 20px;">Please enter your password to enable Two-Factor Authentication.</p>
                <form id="enable-2fa-form">
                    <div class="form_group">
                        <input type="password" id="enable-2fa-password" name="password" placeholder="Current Password" required style="width: 100%; box-sizing: border-box;">
                    </div>
                    <button type="submit" class="btn_primary" style="width: 100%;">Confirm & Enable</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Disable 2FA Modal (OTP Verification) -->
    <div id="disable-2fa-modal" class="modal">
        <div class="modal_content small">
            <span class="close_modal" onclick="close2FAModals()">&times;</span>
            <div style="text-align: center">
                <i class="fas fa-lock-open" style="font-size: 48px; color: var(--accent-color); margin-bottom: 20px;"></i>
                <h2 style="margin-bottom: 10px;">Disable 2FA</h2>
                <p style="color: var(--text-light); margin-bottom: 20px;">Enter the code sent to your email to disable 2FA.</p>
                <form id="disable-2fa-form">
                    <div class="form_group">
                        <input type="text" id="disable-2fa-code" name="code" placeholder="Enter 6-digit Code" required style="width: 100%; box-sizing: border-box; text-align: center; letter-spacing: 3px;" maxlength="6">
                    </div>
                    <button type="submit" class="btn_primary" style="width: 100%;">Verify & Disable</button>
                </form>
            </div>
        </div>
    </div>

    <!-- Logout Modal -->
    <div id="logout-modal" class="modal">
        <div class="modal_content small">
            <span class="close_modal" onclick="closeLogoutModal()">&times;</span>
            <div style="text-align: center;">
                <i class="fas fa-sign-out-alt" style="font-size: 48px; color: var(--accent-color); margin-bottom: 20px;"></i>
                <h2 style="margin-bottom: 10px;">Logout Confirmation</h2>
                <p style="color: var(--text-light); margin-bottom: 25px;">Are you sure you want to log out of your account?</p>
                <div class="modal_actions">
                    <button class="btn_secondary" onclick="closeLogoutModal()">Cancel</button>
                    <button class="btn_primary" onclick="location.href='../logout.php'" style="background: var(--accent-color);">Logout</button>
                </div>
            </div>
        </div>
    </div>

    <script src="owner.js?v=<?php echo time(); ?>"></script>
</body>
</html>
