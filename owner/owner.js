document.addEventListener('DOMContentLoaded', () => {
    // Theme Toggle Logic
    const themeSwitch = document.getElementById('theme-switch');
    if (themeSwitch) {
        const currentTheme = localStorage.getItem('theme') ? localStorage.getItem('theme') : null;
        if (currentTheme) {
            document.documentElement.setAttribute('data-theme', currentTheme);
            if (currentTheme === 'dark') {
                themeSwitch.checked = true;
            }
        }
        themeSwitch.addEventListener('change', function(e) {
            if (e.target.checked) {
                document.documentElement.setAttribute('data-theme', 'dark');
                localStorage.setItem('theme', 'dark');
            } else {
                document.documentElement.setAttribute('data-theme', 'light');
                localStorage.setItem('theme', 'light');
            }
        });
    }

    // User Profile Dropdown Logic
    const profileTrigger = document.getElementById('profile-dropdown-trigger');
    const profileDropdown = document.getElementById('profile-dropdown');
    
    if (profileTrigger && profileDropdown) {
        profileTrigger.addEventListener('click', (e) => {
            e.stopPropagation();
            profileDropdown.classList.toggle('show');
        });

        // Close dropdown when clicking outside
        window.addEventListener('click', (e) => {
            if (!profileTrigger.contains(e.target) && !profileDropdown.contains(e.target)) {
                profileDropdown.classList.remove('show');
            }
        });
    }

    // Navigation Logic
    const navItems = document.querySelectorAll('.nav-item');
    
    // Global showPage function to be used by sidebar and other links
    window.showPage = function(pageId) {
        const pages = document.querySelectorAll('.page');
        const navItems = document.querySelectorAll('.nav-item');

        // Update Active State for ALL nav items with this pageId (syncs top nav and dropdown)
        navItems.forEach(nav => {
            if (nav.getAttribute('data-page') === pageId) {
                nav.classList.add('active');
            } else {
                nav.classList.remove('active');
            }
        });

        // Show relevant page
        pages.forEach(page => {
            page.classList.remove('active');
            if (page.id === pageId) {
                page.classList.add('active');
            }
        });

        // Load Data based on page
        if (pageId === 'dashboard') loadDashboardStats();
        if (pageId === 'my-business') loadBusinessProfile();
        if (pageId === 'my-spots') loadMySpots();
        if (pageId === 'bookings') loadBookings();
        if (pageId === 'reviews') loadReviews();
        // Settings page doesn't need to load external data for now
        
        // Re-apply search filter if search box has value
        if (typeof window.performSearch === 'function') {
            window.performSearch();
        }

        // Close dropdown if open
        if(profileDropdown && profileDropdown.classList.contains('show')) {
            profileDropdown.classList.remove('show');
        }
    };

    navItems.forEach(item => {
        item.addEventListener('click', (e) => {
            // Prevent default if it's a link with #
            if(item.tagName === 'A' && item.getAttribute('href') === '#') {
                e.preventDefault();
            }
            const pageId = item.getAttribute('data-page');
            if (pageId) {
                window.showPage(pageId);
            }
        });
    });

    // Search Logic
    const searchInput = document.querySelector('.search_box input');
    
    window.performSearch = function() {
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

        // Search in grids (e.g. Review Cards, Spot Cards if we had them)
        const cards = activePage.querySelectorAll('.review-card, .card'); 
        cards.forEach(card => {
            // Skip dashboard stat cards if we don't want to hide them, but usually searching dashboard is weird.
            // Let's only search if it's not the dashboard stats, or maybe we do want to filter stats? 
            // Usually dashboard stats are static summaries.
            // Let's filter review cards specifically.
            if (card.classList.contains('review-card')) {
                const text = card.textContent.toLowerCase();
                card.style.display = text.includes(term) ? 'flex' : 'none';
            }
        });
    };

    if (searchInput) {
        searchInput.addEventListener('input', () => {
            window.performSearch();
        });
    }

    // Initial Load
    loadDashboardStats();

    // Modal Logic
    const modal = document.getElementById('spot-modal');
    const btnAddSpot = document.getElementById('btn-add-spot');
    const closeModal = document.querySelector('.close_modal');
    const spotForm = document.getElementById('spot-form');

    if (btnAddSpot) {
        btnAddSpot.addEventListener('click', () => {
            document.getElementById('modal-title').innerText = 'Add New Spot';
            spotForm.reset();
            document.getElementById('spot-id').value = '';
            // Reset highlights and other dynamic fields if any
            document.getElementById('spot-highlights').value = '';
            
            // Use flex because our CSS sets .modal.show { display: flex }
            // But we need to add the class 'show' for animation
            modal.style.display = 'flex';
            // Small timeout to allow display:flex to apply before opacity transition
            setTimeout(() => modal.classList.add('show'), 10);
        });
    }

    if (closeModal) {
        closeModal.addEventListener('click', () => {
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        });
    }

    window.addEventListener('click', (e) => {
        if (e.target === modal) {
            modal.classList.remove('show');
            setTimeout(() => { modal.style.display = 'none'; }, 300);
        }
    });

    // Handle Spot Form Submit
    if (spotForm) {
        spotForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(spotForm);
            const spotId = document.getElementById('spot-id').value;
            const url = spotId ? '../api/owner_update_spot.php' : '../api/owner_add_spot.php';

            try {
                const response = await fetch(url, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();

                if (result.success) {
                    alert(result.message);
                    modal.classList.remove('show');
                    setTimeout(() => { modal.style.display = 'none'; }, 300);
                    loadMySpots();
                    loadDashboardStats(); // Update stats
                } else {
                    alert('Error: ' + result.message);
                }
            } catch (error) {
                console.error('Error saving spot:', error);
                alert('An error occurred while saving the spot.');
            }
        });
    }

    // Handle Business Profile Form Submit
    const businessForm = document.getElementById('business-profile-form');
    if (businessForm) {
        businessForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(businessForm);

            try {
                const response = await fetch('../api/owner_update_profile.php', {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                alert(result.message);
            } catch (error) {
                console.error('Error updating profile:', error);
                alert('An error occurred while updating profile.');
            }
        });
    }

    // --- API Functions ---

    async function loadDashboardStats() {
        try {
            const response = await fetch('../api/owner_get_stats.php');
            const data = await response.json();
            if (data.success) {
                // Check if elements exist before updating to avoid errors
                if(document.getElementById('total-spots')) document.getElementById('total-spots').innerText = data.stats.total_spots;
                if(document.getElementById('avg-rating')) document.getElementById('avg-rating').innerText = data.stats.avg_rating;
                if(document.getElementById('total-reviews')) document.getElementById('total-reviews').innerText = data.stats.total_reviews;
            }
        } catch (error) {
            console.error('Error loading stats:', error);
        }
    }

    async function loadBusinessProfile() {
        try {
            const response = await fetch('../api/owner_get_profile.php');
            const data = await response.json();
            if (data.success) {
                const p = data.profile;
                if(document.getElementById('business-name')) document.getElementById('business-name').value = p.business_name || '';
                if(document.getElementById('business-address')) document.getElementById('business-address').value = p.business_address || '';
                if(document.getElementById('permit-number')) document.getElementById('permit-number').value = p.permit_number || '';
                if(document.getElementById('contact-number')) document.getElementById('contact-number').value = p.contact_number || '';
            }
        } catch (error) {
            console.error('Error loading profile:', error);
        }
    }

    async function loadMySpots() {
        const tbody = document.getElementById('spots-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="5" style="text-align: center;">Loading...</td></tr>';

        try {
            const response = await fetch('../api/owner_get_spots.php');
            const data = await response.json();
            
            if (data.success && data.spots.length > 0) {
                tbody.innerHTML = data.spots.map(spot => {
                    const rating = spot.average_rating ? parseFloat(spot.average_rating).toFixed(1) : 'N/A';
                    const ratingDisplay = rating === 'N/A' 
                        ? '<span class="text-muted">No ratings</span>' 
                        : `<div class="rating-badge"><i class="fas fa-star text-warning"></i> ${rating}</div>`;

                    return `
                    <tr>
                        <td><img src="../${spot.image}" alt="${spot.name}" style="width: 50px; height: 50px; object-fit: cover; border-radius: 8px; box-shadow: 0 2px 5px rgba(0,0,0,0.1);"></td>
                        <td>${spot.name}</td>
                        <td>${spot.type}</td>
                        <td>${ratingDisplay}</td>
                        <td><span class="status_badge active">Active</span></td>
                        <td>
                            <button class="action_btn" onclick="editSpot(${spot.id})" title="Edit"><i class="fas fa-edit"></i></button>
                            <button class="action_btn delete" onclick="deleteSpot(${spot.id})" title="Delete"><i class="fas fa-trash"></i></button>
                        </td>
                    </tr>
                `}).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; padding: 20px;">No spots found. Add one!</td></tr>';
            }
        } catch (error) {
            console.error('Error loading spots:', error);
            tbody.innerHTML = '<tr><td colspan="5" style="text-align: center; color: var(--accent-color);">Error loading spots.</td></tr>';
        }
    }

    async function loadBookings() {
        const tbody = document.getElementById('bookings-table-body');
        if (!tbody) return;
        tbody.innerHTML = '<tr><td colspan="6" style="text-align: center;">Loading bookings...</td></tr>';

        try {
            const response = await fetch('../api/owner_get_bookings.php');
            const data = await response.json();
            
            if (data.success && data.bookings.length > 0) {
                tbody.innerHTML = data.bookings.map(booking => {
                    const statusClass = booking.status.toLowerCase() === 'confirmed' ? 'status_badge success' : 
                                      (booking.status.toLowerCase() === 'pending' ? 'status_badge warning' : 'status_badge danger');
                    
                    return `
                    <tr>
                        <td>#${booking.id}</td>
                        <td>
                            <div class="user-info">
                                <span class="user-name">${booking.user_name}</span>
                                <small class="user-email" style="display:block; font-size:0.8em; color:var(--text-light);">${booking.email}</small>
                            </div>
                        </td>
                        <td>${booking.spot_name}</td>
                        <td>${new Date(booking.booking_date).toLocaleDateString()}</td>
                        <td>${booking.contact_number || 'N/A'}</td>
                        <td><span class="${statusClass}">${booking.status}</span></td>
                    </tr>
                `}).join('');
            } else {
                tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; padding: 20px;">No bookings found yet.</td></tr>';
            }
        } catch (error) {
            console.error('Error loading bookings:', error);
            tbody.innerHTML = '<tr><td colspan="6" style="text-align: center; color: var(--accent-color);">Error loading bookings.</td></tr>';
        }
    }

    async function loadReviews() {
        const container = document.getElementById('reviews-list');
        if (!container) return;
        container.innerHTML = '<div class="loading-spinner"><i class="fas fa-spinner fa-spin"></i> Loading reviews...</div>';

        try {
            const response = await fetch('../api/owner_get_reviews.php');
            const data = await response.json();
            
            if (data.success && data.reviews.length > 0) {
                container.innerHTML = data.reviews.map(review => {
                    // Generate stars
                    let starsHtml = '';
                    for (let i = 1; i <= 5; i++) {
                        if (i <= review.rating) {
                            starsHtml += '<i class="fas fa-star filled"></i>';
                        } else {
                            starsHtml += '<i class="far fa-star"></i>';
                        }
                    }

                    const profilePic = review.profile_pic && review.profile_pic !== 'default-avatar.png' 
                        ? (review.profile_pic.startsWith('http') ? review.profile_pic : '../' + review.profile_pic)
                        : 'https://ui-avatars.com/api/?name=' + encodeURIComponent(review.reviewer_name) + '&background=random';

                    return `
                        <div class="review-card">
                            <div class="review-header">
                                <div class="reviewer-info">
                                    <img src="${profilePic}" alt="${review.reviewer_name}" class="reviewer-avatar">
                                    <div>
                                        <h4>${review.reviewer_name}</h4>
                                        <span class="review-date">${review.date_formatted}</span>
                                    </div>
                                </div>
                                <div class="review-rating">
                                    ${starsHtml}
                                </div>
                            </div>
                            <div class="review-spot">
                                <i class="fas fa-map-marker-alt"></i> ${review.spot_name}
                            </div>
                            <div class="review-content">
                                <p>${review.comment}</p>
                            </div>
                        </div>
                    `;
                }).join('');
            } else {
                container.innerHTML = `
                    <div class="empty-state">
                        <i class="fas fa-comment-slash"></i>
                        <h3>No reviews yet</h3>
                        <p>Reviews from your customers will appear here.</p>
                    </div>
                `;
            }
        } catch (error) {
            console.error('Error loading reviews:', error);
            container.innerHTML = '<p class="error-msg">Error loading reviews. Please try again.</p>';
        }
    }

    // Expose functions to global scope for onclick handlers
    window.editSpot = async (id) => {
        try {
            const response = await fetch(`../api/owner_get_spot.php?id=${id}`);
            const data = await response.json();
            if (data.success) {
                const spot = data.spot;
                document.getElementById('spot-id').value = spot.id;
                document.getElementById('spot-name').value = spot.name;
                document.getElementById('spot-type').value = spot.type;
                document.getElementById('spot-description').value = spot.description;
                document.getElementById('spot-location').value = spot.location;
                document.getElementById('spot-open').value = spot.openTime;
                document.getElementById('spot-close').value = spot.closeTime;
                document.getElementById('spot-fee').value = spot.entranceFee;
                document.getElementById('spot-contact').value = spot.contact || '';
                
                // Handle highlights (JSON to comma string)
                let highlights = '';
                try {
                    if (spot.highlights) {
                        const h = JSON.parse(spot.highlights);
                        if (Array.isArray(h)) highlights = h.join(', ');
                    }
                } catch (e) { console.error('Error parsing highlights', e); }
                document.getElementById('spot-highlights').value = highlights;
                
                document.getElementById('modal-title').innerText = 'Edit Spot';
                
                modal.style.display = 'flex';
                setTimeout(() => modal.classList.add('show'), 10);
            }
        } catch (error) {
            console.error('Error fetching spot details:', error);
        }
    };

    window.deleteSpot = async (id) => {
        if (!confirm('Are you sure you want to delete this spot?')) return;

        try {
            const response = await fetch('../api/owner_delete_spot.php', {
                method: 'POST',
                headers: {'Content-Type': 'application/json'},
                body: JSON.stringify({ id: id })
            });
            const result = await response.json();
            if (result.success) {
                alert(result.message);
                loadMySpots();
                loadDashboardStats();
            } else {
                alert('Error: ' + result.message);
            }
        } catch (error) {
            console.error('Error deleting spot:', error);
        }
    };
});

// Logout Modal Logic
const logoutModal = document.getElementById('logout-modal');

window.openLogoutModal = function() {
    if (logoutModal) {
        logoutModal.style.display = 'flex';
        setTimeout(() => logoutModal.classList.add('show'), 10);
    }
};

window.closeLogoutModal = function() {
    if (logoutModal) {
        logoutModal.classList.remove('show');
        setTimeout(() => { logoutModal.style.display = 'none'; }, 300);
    }
};

// Close modal when clicking outside
window.addEventListener('click', (e) => {
    if (e.target === logoutModal) {
        window.closeLogoutModal();
    }
});

// Handle Change Password Form Submit
const changePasswordForm = document.getElementById('change-password-form');
if (changePasswordForm) {
    changePasswordForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(changePasswordForm);
        const newPass = formData.get('new_password');
        const confirmPass = formData.get('confirm_password');

        if (newPass !== confirmPass) {
            alert('New passwords do not match!');
            return;
        }

        try {
            const response = await fetch('../api/owner_change_password.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                changePasswordForm.reset();
            }
        } catch (error) {
            console.error('Error changing password:', error);
            alert('An error occurred while changing password.');
        }
    });
}

// Handle Account Info Form Submit
const accountInfoForm = document.getElementById('account-info-form');
if (accountInfoForm) {
    accountInfoForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const formData = new FormData(accountInfoForm);
        const emailInput = accountInfoForm.querySelector('input[name=\'email\']');
        
        try {
            const response = await fetch('../api/owner_update_email.php', {
                method: 'POST',
                body: formData
            });
            const result = await response.json();
            alert(result.message);
            if (result.success) {
                // Optionally update the UI or reload
            }
        } catch (error) {
            console.error('Error updating email:', error);
            alert('An error occurred while updating email.');
        }
    });
}

// 2FA Modal Logic
const enable2FAModal = document.getElementById('enable-2fa-modal');
const disable2FAModal = document.getElementById('disable-2fa-modal');
const enable2FAForm = document.getElementById('enable-2fa-form');
const disable2FAForm = document.getElementById('disable-2fa-form');
let twoFAToggleCheckbox = null;

window.close2FAModals = function() {
    if (enable2FAModal) {
        enable2FAModal.style.display = 'none';
        enable2FAModal.classList.remove('show');
    }
    if (disable2FAModal) {
        disable2FAModal.style.display = 'none';
        disable2FAModal.classList.remove('show');
    }
    if (twoFAToggleCheckbox) {
        // Revert checkbox state if closed without action
        twoFAToggleCheckbox.checked = !twoFAToggleCheckbox.checked;
        twoFAToggleCheckbox = null;
    }
};

// Toggle 2FA (with Modal Confirmation)
window.toggle2FA = async function(checkbox) {
    twoFAToggleCheckbox = checkbox;
    const enabling = checkbox.checked;
    
    if (enabling) {
        // Show Password Modal
                if (enable2FAModal) {
                    enable2FAModal.style.display = 'flex';
                    setTimeout(() => enable2FAModal.classList.add('show'), 10);
                    const pwdInput = document.getElementById('enable-2fa-password');
                    if (pwdInput) {
                        pwdInput.value = '';
                        pwdInput.focus();
                    }
                } else {
                    console.error('Enable 2FA Modal not found');
                    alert('Error: Feature not available. Please refresh the page.');
                    checkbox.checked = false;
                }
            } else {
                // Show OTP Modal & Send Code
                if (disable2FAModal) {
                    try {
                        const response = await fetch('../api/owner_send_disable_2fa_code.php');
                        const result = await response.json();
                        if (result.success) {
                            disable2FAModal.style.display = 'flex';
                            setTimeout(() => disable2FAModal.classList.add('show'), 10);
                            const codeInput = document.getElementById('disable-2fa-code');
                            if (codeInput) {
                                codeInput.value = '';
                                codeInput.focus();
                            }
                            alert('Verification code sent to your email.');
                        } else {
                    alert(result.message);
                    checkbox.checked = true; // Revert
                    twoFAToggleCheckbox = null;
                }
            } catch (error) {
                console.error('Error sending code:', error);
                alert('Error sending verification code.');
                checkbox.checked = true; // Revert
                twoFAToggleCheckbox = null;
            }
        } else {
            console.error('Disable 2FA Modal not found');
            alert('Error: Feature not available. Please refresh the page.');
            checkbox.checked = true;
        }
    }
};

// Handle Enable 2FA Form
if (enable2FAForm) {
    enable2FAForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const password = document.getElementById('enable-2fa-password').value;
        
        try {
            const response = await fetch('../api/owner_enable_2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ password })
            });
            const result = await response.json();
            
            if (result.success) {
                alert('Two-Factor Authentication Enabled!');
                enable2FAModal.style.display = 'none';
                enable2FAModal.classList.remove('show');
                twoFAToggleCheckbox = null; // Success, keep checked
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error enabling 2FA:', error);
            alert('An error occurred.');
        }
    });
}

// Handle Disable 2FA Form
if (disable2FAForm) {
    disable2FAForm.addEventListener('submit', async (e) => {
        e.preventDefault();
        const code = document.getElementById('disable-2fa-code').value;
        
        try {
            const response = await fetch('../api/owner_disable_2fa.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json' },
                body: JSON.stringify({ code })
            });
            const result = await response.json();
            
            if (result.success) {
                alert('Two-Factor Authentication Disabled.');
                disable2FAModal.style.display = 'none';
                disable2FAModal.classList.remove('show');
                twoFAToggleCheckbox = null; // Success, keep unchecked
            } else {
                alert(result.message);
            }
        } catch (error) {
            console.error('Error disabling 2FA:', error);
            alert('An error occurred.');
        }
    });
}
