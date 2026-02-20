<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Laguna Tourist Guide - Guest View</title>
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
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
            
            <a href="login.php" class="cta-btn-small" style="text-decoration: none; margin-right: 10px;">Login</a>
            
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
                        <h1>One website for all your Laguna needs</h1>
                        <p>Discover the beauty, culture, and flavors of the Philippines' resort capital. Your ultimate guide to exploring Laguna.</p>
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
                        <div class="date">Mar-Apr</div>
                        <div class="details">
                            <h3>Anilag Festival</h3>
                            <p>The "Ani ng Laguna" festival showcases the province's bountiful harvest, culture, and arts.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">May 15</div>
                        <div class="details">
                            <h3>Pinya Festival</h3>
                            <p>Held in Calauan, celebrating their sweet pineapples with street dancing and float parades.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">Apr-May</div>
                        <div class="details">
                            <h3>Turumba Festival</h3>
                            <p>A religious festival in Pakil honoring Our Lady of Sorrows, famous for the "turumba" dance.</p>
                        </div>
                    </div>
                     <div class="event-card">
                        <div class="date">Sep</div>
                        <div class="details">
                            <h3>Tsinelas Festival</h3>
                            <p>Celebrated in Liliw, Laguna, showcasing their footwear industry.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">Feb</div>
                        <div class="details">
                            <h3>Sampaguita Festival</h3>
                            <p>San Pedro City's celebration of the national flower, featuring cultural shows and trade fairs.</p>
                        </div>
                    </div>
                    <div class="event-card">
                        <div class="date">Dec</div>
                        <div class="details">
                            <h3>Paskuhan sa Laguna</h3>
                            <p>Province-wide Christmas celebrations featuring giant lanterns and food bazaars.</p>
                        </div>
                    </div>
                </div>
            </section>

            <!-- Maps & Directions Section -->
            <section id="maps" class="section">
                <h2>Maps & Directions</h2>
                <div class="map-container">
                    <iframe src="https://www.google.com/maps/embed?pb=!1m18!1m12!1m3!1d247348.6521528641!2d121.26787684606686!3d14.23733201449741!2m3!1f0!2f0!3f0!3m2!1i1024!2i768!4f13.1!3m3!1m2!1s0x3397e1c07335682b%3A0x62957777174621c1!2sLaguna!5e0!3m2!1sen!2sph!4v1700000000000!5m2!1sen!2sph" width="100%" height="450" style="border:0;" allowfullscreen="" loading="lazy"></iframe>
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
            <div class="modal-body">
                <img id="modal-image" src="" alt="Spot Image">
                <div class="modal-info">
                    <div class="modal-header-badges">
                        <span id="modal-status" class="status-badge"></span>
                    </div>
                    <h2 id="modal-title"></h2>
                    <div class="modal-meta">
                        <i class="fas fa-map-marker-alt"></i> <span id="modal-location"></span>
                    </div>
                    <div class="modal-meta">
                        <i class="far fa-clock"></i> <span id="modal-hours"></span>
                    </div>
                    <div class="modal-meta">
                        <i class="fas fa-money-bill-wave"></i> <span id="modal-fee"></span>
                    </div>
                    <div class="modal-meta">
                        <i class="fas fa-phone"></i> <span id="modal-contact"></span>
                    </div>
                    
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

                    <div id="modal-highlights" class="modal-highlights"></div>
                    <p id="modal-description"></p>
                    <div class="modal-actions">
                        <button class="action-btn-large primary" id="modal-book">
                            <i class="fas fa-calendar-check"></i> Book This Spot
                        </button>
                        <a href="#" target="_blank" id="modal-direction" class="action-btn-large secondary">
                            <i class="fas fa-location-arrow"></i> Get Directions
                        </a>
                    </div>
                </div>
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

    <script src="script.js"></script>
    
    <!-- Guest View Specific Overrides -->
    <script>
        // Override toggleActivity for guest users
        window.toggleActivity = function(spotId, type, btn) {
            if (confirm("You need to login to perform this action. Go to login page?")) {
                window.location.href = 'login.php';
            }
        };
        
        // Override submitRating for guest users
        window.submitRating = function() {
            if (confirm("You need to login to submit a review. Go to login page?")) {
                window.location.href = 'login.php';
            }
        };
    </script>
</body>
</html>