<?php
session_start();
include 'db_connect.php';

// Check if user is logged in
if (!isset($_SESSION['user_id']) || $_SESSION['role'] !== 'user') {
    header("Location: login.php");
    exit();
} 

$user_id = $_SESSION['user_id'];
$username = $_SESSION['username'];

// Fetch user details
$stmt = $conn->prepare("SELECT * FROM users WHERE id = ?");
$stmt->bind_param("i", $user_id);
$stmt->execute();
$user = $stmt->get_result()->fetch_assoc();
$stmt->close();

// Fetch Favorites with Average Rating
$fav_sql = "SELECT s.*, 
            COALESCE(AVG(r.rating), 0) as average_rating, 
            COUNT(r.id) as review_count 
            FROM spots s 
            JOIN user_activity ua ON s.id = ua.spot_id 
            LEFT JOIN reviews r ON s.id = r.spot_id 
            WHERE ua.user_id = ? AND ua.activity_type = 'favorite'
            GROUP BY s.id";
$stmt = $conn->prepare($fav_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$favorites = $stmt->get_result();
$stmt->close();

// Fetch My Reviews
$rev_sql = "SELECT r.*, s.name as spot_name, s.image as spot_image, s.category as spot_category 
            FROM reviews r 
            JOIN spots s ON r.spot_id = s.id 
            WHERE r.user_id = ? 
            ORDER BY r.created_at DESC";
$stmt = $conn->prepare($rev_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_reviews = $stmt->get_result();
$stmt->close();

// Fetch My Bookings
$book_sql = "SELECT b.*, s.name as spot_name, s.image as spot_image, s.category as spot_category
            FROM bookings b
            JOIN spots s ON b.spot_id = s.id
            WHERE b.user_id = ?
            ORDER BY b.booking_date DESC";
$stmt = $conn->prepare($book_sql);
$stmt->bind_param("i", $user_id);
$stmt->execute();
$my_bookings = $stmt->get_result();
$stmt->close();
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>My Dashboard - Laguna Guide</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="user_dashboard.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
</head>
<body>

    <!-- Navbar -->
    <header class="navbar">
        <div class="logo" onclick="window.location.href='index.php'">
            <i class="fas fa-leaf logo-icon"></i>
            <h2>Laguna Guide</h2>
        </div>
        <!-- Top Nav Removed for Dashboard Clarity -->
    </header>

    <div id="google_translate_element"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'en', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <!-- Settings Modal -->
    <div id="settings-modal" class="modal">
        <div class="modal-content settings-modal-content">
            <div class="close-modal" onclick="closeSettingsModal()">&times;</div>
            <div class="modal-header">
                <h2><i class="fas fa-cog"></i> Site Settings</h2>
            </div>
            <div class="modal-body settings-body" style="padding: 20px;">
                <h3 class="settings-section-title">Preferences</h3>
                
                <div class="settings-card">
                    <div class="settings-grid">
                        <!-- Language -->
                        <div class="setting-item-box">
                            <div class="setting-header">
                                <div class="icon-badge pink-badge">
                                    <i class="fas fa-globe"></i>
                                </div>
                                <span class="setting-name">Language</span>
                            </div>
                            <div class="setting-control" style="width: 100%;">
                                <select id="language-select" onchange="changeLanguage(this.value)" class="custom-select">
                                    <option value="en">English</option>
                                    <option value="tl">Tagalog</option>
                                    <option value="zh-CN">Chinese</option>
                                    <option value="ja">Japanese</option>
                                    <option value="th">Thai</option>
                                    <option value="hi">Hindi</option>
                                </select>
                            </div>
                        </div>

                        <!-- Text Size -->
                        <div class="setting-item-box">
                            <div class="setting-header">
                                <div class="icon-badge pink-badge">
                                    <i class="fas fa-font"></i>
                                </div>
                                <span class="setting-name">Text Size</span>
                            </div>
                            <div class="setting-control">
                                <div class="toggle-group-segmented">
                                    <button class="segment-btn active" id="text-normal" onclick="setTextSize('normal')">Normal</button>
                                    <button class="segment-btn" id="text-large" onclick="setTextSize('large')">Large</button>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>

                <h3 class="settings-section-title">Security</h3>

                <div class="settings-card">
                    <!-- Two Factor Auth -->
                    <div class="setting-row">
                        <div class="setting-info">
                            <div class="icon-badge orange-badge">
                                <i class="fas fa-shield-alt"></i>
                            </div>
                            <span class="setting-name">Two-Factor Auth</span>
                        </div>
                        <div class="setting-action">
                            <label class="switch">
                                <input type="checkbox" id="2fa-toggle" name="two_factor_enabled" <?php echo $user['two_factor_enabled'] ? 'checked' : ''; ?> onchange="toggle2FA()">
                                <span class="slider round"></span>
                            </label>
                        </div>
                    </div>

                    <div class="divider"></div>

                    <!-- Change Password -->
                    <div class="setting-row-vertical">
                        <div class="setting-info" style="margin-bottom: 10px;">
                            <div class="icon-badge orange-badge">
                                <i class="fas fa-key"></i>
                            </div>
                            <span class="setting-name">Change Password</span>
                        </div>
                        
                        <form id="security-form" onsubmit="updatePassword(event)" class="password-form">
                            <div class="input-row">
                                <input type="password" id="new-password" required placeholder="New Password" class="custom-input">
                                <input type="password" id="confirm-password" required placeholder="Confirm" class="custom-input">
                            </div>
                            <button type="submit" class="btn-update-password">Update Password</button>
                        </form>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <div class="dashboard-container">
        <!-- Sidebar -->
        <div class="dashboard-sidebar">
            <div class="user-card">
                <div class="user-avatar-large"><?php echo strtoupper(substr($username, 0, 1)); ?></div>
                <h3><?php echo htmlspecialchars($username); ?></h3>
                <span class="user-role-badge">Tourist</span>
            </div>
            
            <div class="dash-nav">
                <div class="dash-nav-item active" onclick="showDashSection('overview', this)">
                    <i class="fas fa-home"></i> Overview
                </div>
                <div class="dash-nav-item" onclick="showDashSection('profile', this)">
                    <i class="fas fa-user"></i> Profile
                </div>
                <div class="dash-nav-item" onclick="showDashSection('favorites', this)">
                    <i class="fas fa-heart"></i> My Favorites
                </div>
                <div class="dash-nav-item" onclick="showDashSection('bookings', this)">
                    <i class="fas fa-ticket-alt"></i> My Bookings
                </div>
                <div class="dash-nav-item" onclick="showDashSection('reviews', this)">
                    <i class="fas fa-star"></i> My Reviews
                </div>
                <div class="dash-nav-item" onclick="openSettingsModal()">
                    <i class="fas fa-cog"></i> Site Settings
                </div>
                
                <div class="nav-divider"></div>

                <div class="dash-nav-item" onclick="window.location.href='index.php'">
                    <i class="fas fa-arrow-left"></i> Back to Home
                </div>
            </div>
        </div>

        <!-- Content -->
        <div class="dashboard-content">

            <!-- Overview Section (Welcome Banner) -->
            <div id="overview" class="dash-section active">
                <div class="welcome-banner">
                    <div class="banner-content">
                        <h1>Welcome<br>back,<br><span><?php echo htmlspecialchars($username); ?>!</span></h1>
                        <p>Here is your exploration progress in Laguna.</p>
                        
                        <div class="stats-chips">
                            <div class="stat-chip">
                                <div class="chip-icon"><i class="fas fa-ticket-alt"></i></div>
                                <div class="chip-info">
                                    <strong><?php echo $my_bookings->num_rows; ?></strong>
                                    <span>Bookings</span>
                                </div>
                            </div>
                            <div class="stat-chip">
                                <div class="chip-icon"><i class="fas fa-heart"></i></div>
                                <div class="chip-info">
                                    <strong><?php echo $favorites->num_rows; ?></strong>
                                    <span>Favorites</span>
                                </div>
                            </div>
                            <div class="stat-chip">
                                <div class="chip-icon"><i class="fas fa-map-marker-alt"></i></div>
                                <div class="chip-info">
                                    <strong><?php echo $my_reviews->num_rows; ?></strong>
                                    <span>Visited</span>
                                </div>
                            </div>
                        </div>

                        <button class="btn-explore" onclick="window.location.href='index.php#destination'">EXPLORE NOW</button>
                    </div>
                    <div class="banner-image-container">
                        <img src="laguna-photos/lakes.jpg" alt="Laguna Lake" class="banner-image">
                    </div>
                </div>
            </div>
            
            <!-- Profile Section -->
            <div id="profile" class="dash-section">
                <div class="section-header">
                    <h2>My Profile</h2>
                </div>
                <form id="profile-form" onsubmit="updateProfile(event)">
                    <input type="hidden" id="user-id" value="<?php echo $user_id; ?>">
                    <div class="form-grid">
                        <div class="form-group">
                            <label>Username</label>
                            <input type="text" id="username" value="<?php echo htmlspecialchars($user['username']); ?>" readonly>
                        </div>
                        <div class="form-group">
                            <label>Email Address</label>
                            <input type="email" id="email" value="<?php echo htmlspecialchars($user['email'] ?? ''); ?>" placeholder="Enter your email">
                        </div>
                        <div class="form-group">
                            <label>Phone Number</label>
                            <input type="text" id="phone" value="<?php echo htmlspecialchars($user['contact_number'] ?? ''); ?>" placeholder="Enter your phone number">
                        </div>
                    </div>
                    <button type="submit" class="btn-primary">
                        <i class="fas fa-save"></i> Update Profile
                    </button>
                </form>
            </div>

            <!-- Favorites Section -->
            <div id="favorites" class="dash-section">
                <div class="section-header">
                    <h2>My Favorites</h2>
                </div>
                <?php if ($favorites->num_rows > 0): ?>
                    <div class="spots-grid">
                        <?php while($spot = $favorites->fetch_assoc()): ?>
                            <div class="dashboard-card">
                                <div style="position: relative;">
                                    <img src="<?php echo htmlspecialchars($spot['image']); ?>" class="card-image" alt="<?php echo htmlspecialchars($spot['name']); ?>">
                                    <?php if($spot['average_rating'] > 0): ?>
                                        <span class="card-badge">
                                            <i class="fas fa-star" style="color: #feca57;"></i>
                                            <?php echo number_format($spot['average_rating'], 1); ?>
                                        </span>
                                    <?php endif; ?>
                                </div>
                                <div class="card-content">
                                    <h4 class="card-title"><?php echo htmlspecialchars($spot['name']); ?></h4>
                                    <div class="card-meta">
                                        <i class="fas fa-map-marker-alt"></i> <?php echo htmlspecialchars($spot['location']); ?>
                                    </div>
                                    <div class="card-actions">
                                        <button class="btn-outline" onclick="window.location.href='index.php?view_spot=<?php echo $spot['id']; ?>'">View Details</button>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-heart"></i>
                        <p>You haven't added any favorites yet.</p>
                        <a href="index.php#destination" class="btn-primary">Explore Destinations</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- Bookings Section -->
            <div id="bookings" class="dash-section">
                <div class="section-header">
                    <h2>My Bookings</h2>
                </div>
                <?php if ($my_bookings->num_rows > 0): ?>
                    <div class="bookings-grid">
                        <?php 
                        while($booking = $my_bookings->fetch_assoc()): 
                            $status_class = strtolower($booking['status']);
                            $status_color = '#2ed573'; // Default success
                            if($status_class == 'pending') $status_color = '#ffa502';
                            if($status_class == 'cancelled') $status_color = '#ff4757';
                        ?>
                            <div class="dashboard-card">
                                <div style="position: relative;">
                                    <img src="<?php echo htmlspecialchars($booking['spot_image']); ?>" alt="<?php echo htmlspecialchars($booking['spot_name']); ?>" class="card-image">
                                    <span class="card-badge" style="background: <?php echo $status_color; ?>">
                                        <?php echo htmlspecialchars($booking['status']); ?>
                                    </span>
                                </div>
                                <div class="card-content">
                                    <h3 class="card-title"><?php echo htmlspecialchars($booking['spot_name']); ?></h3>
                                    
                                    <div class="card-meta">
                                        <i class="fas fa-calendar-alt"></i>
                                        <?php echo date('M j, Y', strtotime($booking['booking_date'])); ?>
                                    </div>
                                    <div class="card-meta">
                                        <i class="fas fa-users"></i>
                                        <?php echo $booking['num_adults']; ?> Adults
                                        <?php if($booking['num_children'] > 0) echo ', ' . $booking['num_children'] . ' Kids'; ?>
                                    </div>
                                    <div class="card-meta">
                                        <i class="fas fa-tag"></i>
                                        ₱<?php echo number_format($booking['total_price'], 2); ?>
                                    </div>
                                    
                                    <div class="card-actions">
                                        <a href="receipt.php?booking_id=<?php echo $booking['id']; ?>" target="_blank" class="btn-outline">
                                            <i class="fas fa-receipt"></i> Receipt
                                        </a>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="fas fa-ticket-alt"></i>
                        <p>You haven't booked any trips yet.</p>
                        <a href="index.php#destination" class="btn-primary">Explore Destinations</a>
                    </div>
                <?php endif; ?>
            </div>

            <!-- My Reviews Section -->
            <div id="reviews" class="dash-section">
                <div class="section-header">
                    <h2>My Reviews</h2>
                </div>
                <?php if ($my_reviews->num_rows > 0): ?>
                    <div class="reviews-grid">
                        <?php while($review = $my_reviews->fetch_assoc()): ?>
                            <div class="dashboard-card">
                                <div style="position: relative;">
                                    <img src="<?php echo htmlspecialchars($review['spot_image']); ?>" class="card-image" alt="<?php echo htmlspecialchars($review['spot_name']); ?>">
                                    <span class="card-badge">
                                        <i class="fas fa-star" style="color: #feca57;"></i>
                                        <?php echo number_format($review['rating'], 1); ?>
                                    </span>
                                </div>
                                <div class="card-content">
                                    <h4 class="card-title"><?php echo htmlspecialchars($review['spot_name']); ?></h4>
                                    <p style="color: var(--text-muted); font-style: italic; margin-bottom: 10px;">
                                        "<?php echo htmlspecialchars($review['comment'] ?: 'No comment provided'); ?>"
                                    </p>
                                    <div class="card-meta">
                                        Reviewed on <?php echo date('M d, Y', strtotime($review['created_at'])); ?>
                                    </div>
                                </div>
                            </div>
                        <?php endwhile; ?>
                    </div>
                <?php else: ?>
                    <div class="empty-state">
                        <i class="far fa-star"></i>
                        <p>You haven't written any reviews yet.</p>
                        <a href="index.php#destination" class="btn-primary">Review Places</a>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>

    <!-- Logout Confirmation Modal -->
    <div id="logout-modal" class="modal" style="z-index: 20000 !important;">
        <div class="modal-content logout-modal-content" style="max-width: 400px; text-align: center;">
            <div class="close-modal" onclick="closeLogoutModal()">&times;</div>
            <div class="modal-body">
                <i class="fas fa-sign-out-alt" style="font-size: 3rem; color: var(--primary-color); margin-bottom: 20px;"></i>
                <h2 style="margin-bottom: 10px;">Logout?</h2>
                <p style="margin-bottom: 30px; color: var(--text-light);">Are you sure you want to log out?</p>
                <div class="modal-actions" style="display: flex; justify-content: center; gap: 15px;">
                    <button class="cancel-btn" onclick="closeLogoutModal()">Cancel</button>
                    <a href="logout.php" class="confirm-btn" style="text-decoration: none; display: inline-flex; align-items: center; justify-content: center;">Logout</a>
                </div>
            </div>
        </div>
    </div>

    <script src="script.js?v=<?php echo time(); ?>"></script>
    <script src="user_dashboard.js"></script>
</body>
</html>
