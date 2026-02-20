<?php
session_start();
include 'db_connect.php'; 

$user_stats = null;
if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
    $user_id = $_SESSION['user_id'];
    
    // Fetch personal stats
    $stats_sql = "SELECT 
        COUNT(*) as total_activities,
        SUM(CASE WHEN activity_type = 'booking' THEN 1 ELSE 0 END) as bookings,
        SUM(CASE WHEN activity_type = 'favorite' THEN 1 ELSE 0 END) as favorites,
        SUM(CASE WHEN activity_type = 'visit' THEN 1 ELSE 0 END) as visits
        FROM user_activity WHERE user_id = ?";
    $stmt = $conn->prepare($stats_sql);
    $stmt->bind_param("i", $user_id);
    $stmt->execute();
    $user_stats = $stmt->get_result()->fetch_assoc();
    $stmt->close();
}
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laguna Tourist Guide</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link href="https://fonts.googleapis.com/css2?family=Share+Tech+Mono&family=Dancing+Script:wght@400;700&family=Poppins:wght@400;500;700&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="style_toast.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="events_style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <!-- Flatpickr (Calendar) -->
    <link rel="stylesheet" href="https://cdn.jsdelivr.net/npm/flatpickr/dist/flatpickr.min.css">
</head>
<body>
    <header class="navbar">
        <div class="logo">
            <i class="fas fa-leaf logo-icon"></i>
            <h2>Laguna Guide</h2>
        </div> 
        <nav class="nav-menu" id="nav-menu">
            <ul class="nav-links">
                <li class="active" onclick="showSection('home')">Home</li>
                <li onclick="showSection('destination')">Destination</li>
                <li onclick="showSection('events')">Events</li>
                <li onclick="showSection('maps')">Maps</li>
                <li onclick="showSection('tips')">Tips</li>
                <li onclick="showSection('about')">About</li>
            </ul>
        </nav>
        <div class="nav-actions">

            <div class="theme-toggle">
                <input type="checkbox" id="theme-switch" class="theme-checkbox">
                <label for="theme-switch" class="theme-label">
                    <i class="fas fa-moon"></i>
                    <i class="fas fa-sun"></i>
                    <div class="ball"></div>
                </label>
            </div>
            <?php
            if (isset($_SESSION['user_id']) && $_SESSION['role'] === 'user') {
                $initial = strtoupper(substr($_SESSION['username'], 0, 1));
                echo '
                <div class="user-profile-mini glass-panel" onclick="toggleProfileDropdown()">
                    <div class="avatar-circle">' . $initial . '</div>
                    <span class="profile-name">' . htmlspecialchars($_SESSION['username']) . '</span>
                    <i class="fas fa-chevron-down"></i>
                    <div class="profile-dropdown" id="profile-dropdown">
                        <div class="dropdown-header">
                            <strong>' . htmlspecialchars($_SESSION['username']) . '</strong>
                            <span>User</span>
                        </div>
                        <a href="user_dashboard.php"><i class="fas fa-tachometer-alt"></i> Dashboard</a>
                        <a href="#" onclick="openSettingsModal()"><i class="fas fa-cog"></i> Settings</a>
                        <div class="dropdown-divider"></div>
                        <a href="logout.php" onclick="confirmLogout(event)" class="logout-item"><i class="fas fa-sign-out-alt"></i> Logout</a>
                    </div>
                </div>
                ';
            } else {
                echo '<a href="login.php" class="cta-btn-small login-btn-link">Login</a>';
            }
            ?>
            <button class="cta-btn-small" onclick="showSection('contact')">Contact Us</button>
        </div>
    </header>

    <div class="container">
        <!-- Main Content -->
        <main class="main-content">
            <!-- Home Section -->
            <section id="home" class="section active-section">
                <div class="hero">
                    <div class="hero-content">
                        <?php if ($user_stats): ?>
                            <h1>Welcome back, <?php echo htmlspecialchars($_SESSION['username']); ?>!</h1>
                            <p>Here is your exploration progress in Laguna.</p>
                            
                            <div class="user-stats-bar">
                                <div class="stat-item-mini">
                                    <i class="fas fa-ticket-alt"></i>
                                    <span><?php echo $user_stats['bookings']; ?> Bookings</span>
                                </div>
                                <div class="stat-item-mini">
                                    <i class="fas fa-heart"></i>
                                    <span><?php echo $user_stats['favorites']; ?> Favorites</span>
                                </div>
                                <div class="stat-item-mini">
                                    <i class="fas fa-map-marked-alt"></i>
                                    <span><?php echo $user_stats['visits']; ?> Visited</span>
                                </div>
                            </div>
                        <?php else: ?>
                            <h1>One website for all your Laguna needs</h1>
                            <p>Discover the beauty, culture, and flavors of the Philippines' resort capital. Your ultimate guide to exploring Laguna.</p>
                        <?php endif; ?>
                        <button class="cta-btn" onclick="showSection('destination')">Explore Now</button>
                    </div>
                    <div class="hero-image">
                        <div class="hero-circle">
                            <img src="laguna-photos/laguna-province-1.jpg" alt="Laguna Beauty">
                        </div>
                    </div>
                </div>
                <div class="featured">
                    <h3>Featured Spots</h3>
                    <div class="featured-grid" id="featured-grid">
                        <!-- Dynamic content -->
                    </div>
                </div>

                <div class="featured">
                    <h3>Top Rated by Travelers</h3>
                    <div class="featured-grid" id="top-rated-grid">
                        <!-- Dynamic content -->
                    </div>
                </div>
            </section>

            <!-- Destination Section -->
            <section id="destination" class="section">
                <h2>Top Destinations</h2>
                <div class="filter-bar">
                    <div class="search-box">
                        <i class="fas fa-search"></i>
                        <input type="text" id="search-input" placeholder="Search destinations..." onkeyup="filterSpots()">
                    </div>
                    <div class="filter-controls">
                        <select id="category-filter" onchange="filterSpots()">
                            <option value="all">All Categories</option>
                            <option value="nature">Nature</option>
                            <option value="chill">Chill</option>
                            <option value="adventure">Adventure</option>
                            <option value="food & dining">Food & Dining</option>
                            <option value="historical">Historical</option>
                            <option value="springs">Hot Springs</option>
                            <option value="parks">Parks</option>
                            <option value="museums">Museums</option>
                        </select>
                        <select id="status-filter" onchange="filterSpots()">
                            <option value="all">All Status</option>
                            <option value="open">Open Now</option>
                            <option value="closed">Closed</option>
                        </select>
                    </div>
                </div>
                <div class="spots-grid" id="spots-grid">
                    <!-- Dynamic content -->
                </div>
            </section>

            <!-- Event & Festival Section -->
            <section id="events" class="section">
                <h2>Events & Festivals</h2>
                <div class="event-list">
                    <div class="event-card">
                        <div class="date">
                            <span>Mar</span>
                            <span>Apr</span>
                        </div>
                        <div class="details" data-id="001234">
                            <h3>Anilag Festival</h3>
                            <p>The "Ani ng Laguna" festival showcases the province's bountiful harvest, culture, and arts.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">
                            <span>May</span>
                            <span>15</span>
                        </div>
                        <div class="details" data-id="001235">
                            <h3>Pinya Festival</h3>
                            <p>Held in Calauan, celebrating their sweet pineapples with street dancing and float parades.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">
                            <span>Apr</span>
                            <span>May</span>
                        </div>
                        <div class="details" data-id="001236">
                            <h3>Turumba Festival</h3>
                            <p>A religious festival in Pakil honoring Our Lady of Sorrows, famous for the "turumba" dance.</p>
                        </div>
                    </div>
                     <div class="event-card">
                        <div class="date">
                            <span>Sep</span>
                        </div>
                        <div class="details" data-id="001237">
                            <h3>Tsinelas Festival</h3>
                            <p>Celebrated in Liliw, Laguna, showcasing their footwear industry.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">
                            <span>Feb</span>
                        </div>
                        <div class="details" data-id="001238">
                            <h3>Sampaguita Festival</h3>
                            <p>San Pedro City's celebration of the national flower, featuring cultural shows and trade fairs.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">
                            <span>Dec</span>
                        </div>
                        <div class="details" data-id="001239">
                            <h3>Paskuhan</h3>
                            <p>Province-wide Christmas celebrations featuring giant lanterns and food bazaars.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Maps & Directions Section -->
            <section id="maps" class="section">
                <h2>Maps & Directions</h2>
                <div class="map-container">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d247348.6521528641!2d121.26787684606686!3d14.23733201449741!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397e1c07335682b%3A0x62957777174621c1!2sLaguna!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" width="100%" height="450" class="iframe-map" allowfullscreen="" loading="lazy"></iframe>
                </div>
                <div class="directions">
                    <h3>How to get there</h3>
                    <p><strong>By Bus:</strong> Take a bus from Buendia or Cubao terminals heading to Sta. Cruz, Calamba, or San Pablo.</p>
                    <p><strong>By Private Car:</strong> Take SLEX (South Luzon Expressway) and exit at Calamba, Sta. Rosa, or other Laguna exits.</p>
                </div>
            </section>

            <!-- Travel Tips Section -->
            <section id="tips" class="section">
                <h2>Travel Tips</h2>
                <ul class="tips-list">
                    <li><i class="fas fa-sun"></i> Best time to visit is during the dry season (December to May).</li>
                    <li><i class="fas fa-tshirt"></i> Wear comfortable clothes and walking shoes.</li>
                    <li><i class="fas fa-tint"></i> Bring water and stay hydrated.</li>
                    <li><i class="fas fa-camera"></i> Don't forget your camera!</li>
                    <li><i class="fas fa-money-bill-wave"></i> Bring cash, as some smaller shops might not accept cards.</li>
                </ul>
            </section>

            <!-- About Section -->
            <section id="about" class="section">
                <h2>About the Locality</h2>
                <div class="content-card about-card">
                    <p class="about-intro">Laguna is a province in the Philippines, located in the Calabarzon region in Luzon. It hugs the southern shores of Laguna de Bay, the largest lake in the country. It is famous for its waterfalls, hot springs, and historical sites.</p>
                    
                    <div class="about-stats">
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-landmark"></i></div>
                            <div class="stat-info">
                                <span class="stat-label">Capital</span>
                                <span class="stat-value">Santa Cruz</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-ruler-combined"></i></div>
                            <div class="stat-info">
                                <span class="stat-label">Area</span>
                                <span class="stat-value">1,917.85 km²</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-users"></i></div>
                            <div class="stat-info">
                                <span class="stat-label">Population</span>
                                <span class="stat-value">~3.4 Million</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-language"></i></div>
                            <div class="stat-info">
                                <span class="stat-label">Languages</span>
                                <span class="stat-value">Tagalog, English</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-cloud-sun"></i></div>
                            <div class="stat-info">
                                <span class="stat-label">Climate</span>
                                <span class="stat-value">Tropical (Nov-May)</span>
                            </div>
                        </div>
                        <div class="stat-item">
                            <div class="stat-icon"><i class="fas fa-industry"></i></div>
                            <div class="stat-info">
                                <span class="stat-label">Industry</span>
                                <span class="stat-value">Agri, Tourism, Mfg</span>
                            </div>
                        </div>
                    </div>

                    <div class="about-divider"></div>

                    <div class="about-history">
                        <h3><i class="fas fa-history"></i> History & Culture</h3>
                        <p>Laguna is named after <em>Laguna de Bay</em>, the body of water that forms its northern boundary. It is historically significant as the birthplace of the Philippines' national hero, <strong>Jose Rizal</strong> (born in Calamba). The province played a key role in the Philippine Revolution against Spain and the American-Filipino War.</p>
                        <p>Today, it is a thriving hub of culture, known for its wood carving in Paete, footwear in Liliw, and embroidery in Lumban.</p>
                    </div>
                </div>
            </section>

            <!-- Contact Section -->
            <section id="contact" class="section">
                <div class="contact-container">
                    <div class="contact-header">
                        <h2>Get in Touch</h2>
                        <p>We'd love to hear from you. Send us a message or reach out to our emergency hotlines.</p>
                    </div>

                    <div class="contact-content">
                        <div class="contact-form-wrapper">
                            <form id="contact-form">
                                <div class="form-group">
                                    <label><i class="fas fa-user"></i> Name</label>
                                    <input type="text" id="contact-name" placeholder="Your Name">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-envelope"></i> Email</label>
                                    <input type="email" id="contact-email" placeholder="Your Email">
                                </div>
                                <div class="form-group">
                                    <label><i class="fas fa-comment-alt"></i> Message</label>
                                    <textarea id="contact-message" placeholder="How can we help?"></textarea>
                                </div>
                                <button type="button" class="submit-btn" onclick="submitMessage()">
                                    <span>Send Message</span>
                                    <i class="fas fa-paper-plane"></i>
                                </button>
                            </form>
                        </div>

                        <div class="contact-info-wrapper">
                            <div class="emergency-card">
                                <div class="icon-box police">
                                    <i class="fas fa-shield-alt"></i>
                                </div>
                                <div class="info-text">
                                    <h3>Police Emergency</h3>
                                    <p class="hotline">67</p>
                                    <span class="sub-text">24/7 Response</span>
                                </div>
                            </div>

                            <div class="emergency-card">
                                <div class="icon-box tourism">
                                    <i class="fas fa-info-circle"></i>
                                </div>
                                <div class="info-text">
                                    <h3>Tourism Office</h3>
                                    <p class="hotline">0985 807 2562</p>
                                    <span class="sub-text">Mon-Fri, 8AM-5PM</span>
                                </div>
                            </div>
                            
                            <div class="social-connect">
                                <h3>Follow Us</h3>
                                <div class="social-icons">
                                    <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                                    <a href="#" class="social-icon"><i class="fab fa-instagram"></i></a>
                                    <a href="#" class="social-icon"><i class="fab fa-twitter"></i></a>
                                </div>
                            </div>
                        </div>
                    </div>
                </div>
            </section>
        </main>
    </div>
    <!-- Modal -->
    <div id="modal" class="modal">
        <div class="modal-content">
            <div class="close-modal" onclick="closeModal()">&times;</div>
            <div class="modal-header-badges">
                <span id="modal-status" class="status-badge"></span>
            </div>
            <div class="modal-body">
                <img id="modal-image" src="" alt="Spot Image">
                <div class="modal-info">
                    <h2 id="modal-title"></h2>
                    
                    <div class="modal-details-grid">
                        <div class="modal-meta-item">
                            <div class="meta-icon"><i class="fas fa-map-marker-alt"></i></div>
                            <span id="modal-location"></span>
                        </div>
                        <div class="modal-meta-item">
                            <div class="meta-icon"><i class="far fa-clock"></i></div>
                            <span id="modal-hours"></span>
                        </div>
                        <div class="modal-meta-item">
                            <div class="meta-icon"><i class="fas fa-money-bill-wave"></i></div>
                            <span id="modal-fee"></span>
                        </div>
                        <div class="modal-meta-item">
                            <div class="meta-icon"><i class="fas fa-phone"></i></div>
                            <span id="modal-contact"></span>
                        </div>
                    </div>
                    
                    <div id="modal-highlights" class="modal-highlights"></div>
                    
                    <div class="modal-actions">
                        <button class="action-btn-large primary" id="modal-book">
                            <i class="fas fa-calendar-check"></i>
                            <span>Book This Spot</span>
                        </button>
                        <a href="#" target="_blank" id="modal-direction" class="action-btn-large secondary">
                            <i class="fas fa-location-arrow"></i>
                            <span>Get Directions</span>
                        </a>
                    </div>

                    <p id="modal-description"></p>

                    <!-- Rating Section -->
                    <div class="modal-rating-section">
                        <h3>Rate this Spot</h3>
                        <div class="star-rating" id="star-rating">
                            <i class="far fa-star" data-rating="1"></i>
                            <i class="far fa-star" data-rating="2"></i>
                            <i class="far fa-star" data-rating="3"></i>
                            <i class="far fa-star" data-rating="4"></i>
                            <i class="far fa-star" data-rating="5"></i>
                        </div>
                        <textarea id="rating-comment" placeholder="Write a review (optional)..."></textarea>
                        <button class="cta-btn-small full-width" onclick="submitRating()">Submit Review</button>
                    </div>
                </div>
            </div>
        </div>
    </div>
    <!-- Booking Modal -->
    <div id="booking-modal" class="modal">
        <div class="modal-content booking-modal-content">
            <div class="modal-header">
                <h2>Book <span id="booking-spot-name"></span></h2>
                <div class="close-modal" onclick="closeBookingModal()">&times;</div>
            </div>
            <div class="modal-body booking-body">
                <form id="booking-form" onsubmit="event.preventDefault(); submitBooking();">
                    <input type="hidden" id="booking-spot-id">
                    <input type="hidden" id="booking-spot-price">
                    
                    <div class="form-group">
                        <label class="input-label" for="booking-date"><i class="far fa-calendar-alt"></i> Date of Visit</label>
                        <div class="date-input-wrapper">
                            <input type="text" id="booking-date" required class="form-control" placeholder="Select your visit date...">
                            <i class="fas fa-calendar-day calendar-icon"></i>
                        </div>
                    </div>

                    <div class="form-row">
                        <div class="form-group half-width">
                            <label class="input-label"><i class="fas fa-user"></i> Adults</label>
                            <div class="stepper-control">
                                <button type="button" class="stepper-btn" onclick="updateStepper('booking-adults', -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="booking-adults" min="1" value="1" required class="form-control stepper-input" readonly>
                                <button type="button" class="stepper-btn" onclick="updateStepper('booking-adults', 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                        <div class="form-group half-width">
                            <label class="input-label"><i class="fas fa-child"></i> Children</label>
                            <div class="stepper-control">
                                <button type="button" class="stepper-btn" onclick="updateStepper('booking-children', -1)">
                                    <i class="fas fa-minus"></i>
                                </button>
                                <input type="number" id="booking-children" min="0" value="0" class="form-control stepper-input" readonly>
                                <button type="button" class="stepper-btn" onclick="updateStepper('booking-children', 1)">
                                    <i class="fas fa-plus"></i>
                                </button>
                            </div>
                        </div>
                    </div>

                    <div class="price-summary-card">
                        <div class="price-row total">
                            <span>Total Price</span>
                            <span id="booking-total-price">₱0.00</span>
                        </div>
                        <div class="price-row detail">
                            <span>Price per person</span>
                            <span>₱<span id="booking-price-display">0</span></span>
                        </div>
                    </div>

                    <div class="form-group">
                        <label for="booking-request"><i class="far fa-comment-alt"></i> Special Requests</label>
                        <textarea id="booking-request" class="form-control" rows="3" placeholder="Any dietary restrictions or special needs?"></textarea>
                    </div>

                    <button type="submit" class="booking-submit-btn">
                        Proceed to Payment <i class="fas fa-arrow-right"></i>
                    </button>
                </form>
            </div>
        </div>
    </div>

    <!-- Payment Modal -->
    <div id="payment-modal" class="modal">
        <div class="modal-content payment-modal-content">
            <div class="close-modal-floating" onclick="closePaymentModal()">&times;</div>
            
            <div style="text-align: center; margin-bottom: 30px; color: white; font-size: 1.5rem; text-shadow: 0 2px 4px rgba(0,0,0,0.5);">
                Total to Pay: <span id="payment-amount" style="font-weight: 800;">₱0.00</span>
            </div>

            <div class="card-wrapper">
                <div class="card-inner">
                    <!-- Front Card -->
                    <div class="card-front">
                        <div class="card-bg-texture"></div>
                        <div class="card-top-row">
                            <div class="card-chip">
                                <div class="chip-line"></div>
                                <div class="chip-line"></div>
                                <div class="chip-line"></div>
                                <div class="chip-line"></div>
                            </div>
                            <div class="card-network-logo-front">
                                <span class="visa-badge">VISA</span>
                            </div>
                        </div>
                        
                        <div class="card-middle-row">
                            <label class="card-label">CARD NUMBER</label>
                            <input type="text" id="card-number" placeholder="0000 0000 0000 0000" maxlength="19" class="card-input-number" required>
                        </div>
                        
                        <div class="card-bottom-row">
                            <div class="card-group-left">
                                <label class="card-label">EXPIRES</label>
                                <input type="text" id="card-expiry" placeholder="MM/YY" maxlength="5" class="card-input-small" required>
                            </div>
                            <div class="card-group-center">
                                <label class="card-label">CARD HOLDER</label>
                                <input type="text" id="card-holder-input" placeholder="NAME ON CARD" class="card-input-name" required value="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>" data-default-name="<?php echo isset($_SESSION['username']) ? htmlspecialchars($_SESSION['username']) : ''; ?>">
                            </div>
                            <div class="card-group-right">
                                <i class="fas fa-wifi contactless-icon"></i>
                            </div>
                        </div>
                    </div>
                
                    <!-- Back Card (CVV) -->
                    <div class="card-back">
                        <div class="magnetic-strip"></div>
                        <div class="card-back-content">
                            <div class="signature-row">
                                <div class="signature-strip">Authorized Signature</div>
                                <div class="cvv-box">
                                    <input type="text" id="card-cvv" placeholder="123" maxlength="4" class="cvv-input" required>
                                </div>
                                <div class="secure-badge">SECURE</div>
                            </div>
                            <div class="card-legal-text">
                                This card is property of the issuing bank and must be returned upon request.
                                Use of this card is governed by the cardholder agreement.
                                Not valid unless signed.
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <div style="text-align: center; margin-top: -20px; margin-bottom: 25px; color: rgba(255,255,255,0.7); font-size: 0.85rem; cursor: pointer;" onclick="document.querySelector('.card-wrapper').classList.toggle('flipped')">
                <i class="fas fa-sync-alt" style="margin-right: 5px;"></i> Click card to flip
            </div>

            <div class="payment-footer">
                <div class="trust-badges">
                    <div class="trust-item">
                        <i class="fas fa-shield-alt"></i>
                        <div class="trust-text">
                            <strong>PCI</strong>
                            <span>DSS</span>
                        </div>
                    </div>
                    <div class="trust-item">
                        <i class="fab fa-cc-visa"></i>
                        <div class="trust-text">
                            <strong>Verified</strong>
                            <span>by VISA</span>
                        </div>
                    </div>
                    <div class="trust-item">
                        <i class="fas fa-lock"></i>
                        <div class="trust-text">
                            <strong>MasterCard</strong>
                            <span>SecureCode</span>
                        </div>
                    </div>
                </div>
                <button type="button" id="pay-button" class="pay-now-btn" onclick="submitPayment()">
                    <span>PAY NOW</span>
                    <i class="fas fa-arrow-right"></i>
                </button>
            </div>
        </div>
    </div>

    <!-- Settings Modal -->
    <div id="settings-modal" class="modal">
        <div class="modal-content settings-modal-content">
            <div class="close-modal" onclick="closeSettingsModal()">&times;</div>
            <div class="modal-header">
                <h2><i class="fas fa-cog"></i> Settings</h2>
            </div>
            <div class="modal-body settings-body">
                
                

                <div class="setting-item">
                    <div class="setting-label">
                        <i class="fas fa-globe"></i>
                        <span>Language</span>
                    </div>
                    <div class="setting-control">
                        <select id="language-select" onchange="changeLanguage(this.value)">
                            <option value="en">English</option>
                            <option value="tl">Tagalog</option>
                            <option value="zh-CN">Chinese (Simplified)</option>
                            <option value="ja">Japanese</option>
                            <option value="th">Thai</option>
                            <option value="hi">Hindi (Indian)</option>
                        </select>
                    </div>
                </div>

                <div class="setting-item">
                    <div class="setting-label">
                        <i class="fas fa-font"></i>
                        <span>Text Size</span>
                    </div>
                    <div class="setting-control">
                        <div class="toggle-group">
                            <button class="toggle-btn active" id="text-normal" onclick="setTextSize('normal')">Aa</button>
                            <button class="toggle-btn" id="text-large" onclick="setTextSize('large')">Aa</button>
                        </div>
                    </div>
                </div>

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
    <!-- Chat AI Widget -->
    <div id="chat-widget" class="chat-widget">
        <!-- Sidebar -->
        <div class="chat-sidebar" id="chat-sidebar" style="display: none;">
            <div class="sidebar-header">
                <h3>History</h3>
                <button onclick="toggleSidebar()" class="close-sidebar-btn">&times;</button>
            </div>
            <button class="new-chat-btn" onclick="startNewChat()"><i class="fas fa-plus"></i> New Chat</button>
            <div class="history-list" id="history-list">
                <!-- Dynamic History Items -->
            </div>
        </div>

        <div class="chat-main-content">
            <div class="chat-header">
                <button class="sidebar-toggle" onclick="toggleSidebar()" style="margin-right: 10px; background: none; border: none; color: white; cursor: pointer;"><i class="fas fa-bars"></i></button>
                <div class="chat-title">
                    <i class="fas fa-robot"></i> Doquerainee
                </div>
                <button class="close-chat" onclick="toggleChat()">&times;</button>
            </div>
            <div class="chat-messages" id="chat-messages">
                <div class="message bot-message">
                    Hello! I'm Doquerainee, your friendly local guide. I can answer questions about Laguna!
                </div>
            </div>
            
            <!-- Quick Suggestions -->
            <div class="chat-suggestions">
                <button class="suggestion-chip" onclick="sendChatMessage('What are the top tourist spots?')">Top Spots 🏆</button>
                <button class="suggestion-chip" onclick="sendChatMessage('Where to eat in Laguna?')">Best Food 🍲</button>
                <button class="suggestion-chip" onclick="sendChatMessage('Any upcoming festivals?')">Events 🎉</button>
                <button class="suggestion-chip" onclick="sendChatMessage('How to get to Laguna?')">Transport 🚌</button>
            </div>

            <div class="chat-input-area">
                <input type="text" id="chat-input" placeholder="Type your question..." onkeypress="handleChatInput(event)">
                <button id="send-btn" onclick="sendChatMessage()"><i class="fas fa-paper-plane"></i></button>
                <button id="stop-btn" onclick="stopChatProcessing()" style="display: none; background-color: #e74c3c;"><i class="fas fa-stop"></i></button>
            </div>
        </div>
    </div>
    <button class="chat-fab" onclick="toggleChat()"><i class="fas fa-robot"></i></button>

    <div id="google_translate_element" style="display:none"></div>
    <script type="text/javascript">
        function googleTranslateElementInit() {
            new google.translate.TranslateElement({pageLanguage: 'en', autoDisplay: false}, 'google_translate_element');
        }
    </script>
    <script type="text/javascript" src="//translate.google.com/translate_a/element.js?cb=googleTranslateElementInit"></script>

    <!-- Toast Notification -->
    <div id="toast-container" class="toast-container">
        <div id="toast" class="toast">
            <i class="fas fa-check-circle toast-icon"></i>
            <span id="toast-message">Notification Message</span>
        </div>
    </div>

    <!-- Flatpickr (Calendar Logic) -->
    <script src="https://cdn.jsdelivr.net/npm/flatpickr"></script>
    <script src="script.js?v=<?php echo time(); ?>"></script>
</body>
</html>
