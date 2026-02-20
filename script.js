// Data for Tourist Spots
let spots = [];

// Fetch Spots from Database
async function fetchSpots() {
    try {
        const response = await fetch('./api/get_spots.php');
        if (!response.ok) {
            throw new Error('Network response was not ok');
        }
        spots = await response.json();
        // Convert ID to number just in case (though JSON should handle it)
        spots = spots.map(s => ({
            ...s,
            id: Number(s.id)
        }));
        renderSpots();
    } catch (error) {
        console.error('Error fetching spots:', error);
        document.getElementById('spots-grid').innerHTML = '<p>Error loading spots from database.</p>';
    }
}

// Helper: Check if open
function isOpen(openTime, closeTime) {
    if (!openTime || !closeTime) return false;

    const now = new Date();
    const currentHour = now.getHours();
    const currentMinute = now.getMinutes();
    const currentTotal = currentHour * 60 + currentMinute;
    
    const [openH, openM] = openTime.split(':').map(Number);
    const [closeH, closeM] = closeTime.split(':').map(Number);
    
    const openTotal = openH * 60 + openM;
    const closeTotal = closeH * 60 + closeM;
    
    if (closeTotal < openTotal) {
        // Overnight (e.g. 22:00 to 02:00)
        // Open if current time is after open time OR before close time
        return currentTotal >= openTotal || currentTotal < closeTotal;
    } else {
        // Standard day (e.g. 08:00 to 17:00)
        return currentTotal >= openTotal && currentTotal < closeTotal;
    }
}

// Render Card
function createCard(spot) {
    const open = isOpen(spot.openTime, spot.closeTime);
    const statusClass = open ? 'status-open' : 'status-closed';
    const statusText = open ? 'Open Now' : 'Closed';
    
    // Calculate stars
    const rating = parseFloat(spot.average_rating) || 0;
    const fullStars = Math.floor(rating);
    const hasHalfStar = rating % 1 >= 0.5;
    let starsHtml = '';
    
    for (let i = 1; i <= 5; i++) {
        if (i <= fullStars) {
            starsHtml += '<i class="fas fa-star"></i>';
        } else if (i === fullStars + 1 && hasHalfStar) {
            starsHtml += '<i class="fas fa-star-half-alt"></i>';
        } else {
            starsHtml += '<i class="far fa-star"></i>';
        }
    }

    const category = spot.category ? `<span class="card-category-badge">${spot.category}</span>` : '';

    return `
        <div class="card fade-in" onclick="openModal(${spot.id})">
            <div class="card-img" style="background-image: url('${spot.image}')">
                ${category}
                <span class="status-badge ${statusClass}">${statusText}</span>
                <div class="card-actions" onclick="event.stopPropagation()">
                    <button class="action-btn" onclick="toggleActivity(${spot.id}, 'favorite', this)" title="Add to Favorites"><i class="far fa-heart"></i></button>
                    <button class="action-btn" onclick="toggleActivity(${spot.id}, 'visit', this)" title="Mark as Visited"><i class="far fa-check-circle"></i></button>
                </div>
            </div>
            <div class="card-content">
                <div class="card-header-row">
                    <h3 class="card-title">${spot.name}</h3>
                    <div class="card-rating">
                        <i class="fas fa-star" style="color: #ffb142;"></i> <span>${rating.toFixed(1)}</span>
                    </div>
                </div>
                <div class="card-location"><i class="fas fa-map-marker-alt"></i> ${spot.location}</div>
                <p class="card-desc">${spot.description}</p>
            </div>
        </div>
    `;
}

// Toggle Activity (Favorite/Visit)
async function toggleActivity(spotId, type, btn) {
    try {
        const response = await fetch('./api/toggle_activity.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ spot_id: spotId, type: type })
        });
        const result = await response.json();
        
        if (result.success) {
            if (type === 'direction') {
                 console.log('Direction clicked and logged.');
                 return;
            }

            const icon = btn.querySelector('i');
            if (result.action === 'added') {
                icon.classList.remove('far');
                icon.classList.add('fas');
                icon.style.color = type === 'favorite' ? '#e74c3c' : '#2ecc71';
                alert(`Added to ${type}s! Refresh to see updated stats.`);
            } else {
                icon.classList.remove('fas');
                icon.classList.add('far');
                icon.style.color = '';
                alert(`Removed from ${type}s.`);
            }
        } else {
            alert(result.message || 'Please login to perform this action');
        }
    } catch (error) {
        console.error('Error:', error);
    }
}

// Global variable to store current rating
let currentRating = 0;
let currentSpotId = null;

// Navbar Logic
// toggleMobileMenu is defined later in the file (line ~766) to avoid duplication

// Close mobile menu when clicking a link
document.addEventListener('DOMContentLoaded', () => {
    const navLinks = document.querySelectorAll('.nav-links li');
    navLinks.forEach(link => {
        link.addEventListener('click', () => {
            const navMenu = document.getElementById('nav-menu');
            if (navMenu.classList.contains('active')) {
                navMenu.classList.remove('active');
            }
        });
    });

    // Close menu when clicking outside
    document.addEventListener('click', (e) => {
        const navMenu = document.getElementById('nav-menu');
        const menuToggle = document.querySelector('.menu-toggle');
        
        if (navMenu.classList.contains('active') && 
            !navMenu.contains(e.target) && 
            !menuToggle.contains(e.target)) {
            navMenu.classList.remove('active');
        }
    });
});

// Credit Card Formatting
document.addEventListener('DOMContentLoaded', function() {
    const cardNumberInput = document.getElementById('card-number');
    const cardExpiryInput = document.getElementById('card-expiry');
    const cardCvvInput = document.getElementById('card-cvv');

    // Toggle card flip on click, but ignore clicks on inputs/labels
    const cardWrapper = document.querySelector('.card-wrapper');
     if (cardWrapper) {
         cardWrapper.addEventListener('click', function(e) {
             // If clicking an input or label, do not flip
             if (e.target.tagName === 'INPUT' || e.target.tagName === 'LABEL') {
                 return;
             }
             // If clicking inside the CVV box (but not input/label), focus the input
             if (e.target.closest('.cvv-box')) {
                 const cvvInput = document.getElementById('card-cvv');
                 if (cvvInput) cvvInput.focus();
                 return;
             }
             
             // Toggle flip for other areas
             this.classList.toggle('flipped');
         });
     }

     if (cardNumberInput) {
         cardNumberInput.addEventListener('input', function(e) {
             let value = e.target.value.replace(/\D/g, '');
             let formattedValue = '';
             for (let i = 0; i < value.length; i++) {
                 if (i > 0 && i % 4 === 0) {
                     formattedValue += ' ';
                 }
                 formattedValue += value[i];
             }
             e.target.value = formattedValue;
         });
     }

     if (cardExpiryInput) {
         cardExpiryInput.addEventListener('input', function(e) {
             let value = e.target.value.replace(/\D/g, '');
             if (value.length >= 2) {
                 value = value.substring(0, 2) + '/' + value.substring(2, 4);
             }
             e.target.value = value;
         });
     }

     if (cardCvvInput) {
         // Use input event for validation to be safer across devices
         cardCvvInput.addEventListener('input', function(e) {
             e.target.value = e.target.value.replace(/\D/g, '').slice(0, 4);
         });
         
         // Auto-flip to back when CVV is focused
         cardCvvInput.addEventListener('focus', function() {
             if (cardWrapper) cardWrapper.classList.add('flipped');
         });
     }
});

// Modal Logic
function openModal(id) {
    const spot = spots.find(s => s.id === id);
    if (!spot) return;

    currentSpotId = spot.id;
    currentRating = 0; // Reset rating
    
    const modal = document.getElementById('modal');
    // Prevent background scrolling
    document.body.style.overflow = 'hidden';
    
    const open = isOpen(spot.openTime, spot.closeTime);
    
    document.getElementById('modal-image').src = spot.image;
    document.getElementById('modal-title').textContent = spot.name;
    document.getElementById('modal-location').textContent = spot.location;
    
    if (spot.openTime && spot.closeTime) {
        document.getElementById('modal-hours').textContent = `${spot.openTime} - ${spot.closeTime}`;
    } else {
        document.getElementById('modal-hours').textContent = 'Hours not available';
    }

    const fee = spot.entranceFee || (spot.price && parseFloat(spot.price) > 0 ? '₱' + parseFloat(spot.price).toLocaleString(undefined, {minimumFractionDigits: 2}) : 'Free / Inquire');
    document.getElementById('modal-fee').textContent = fee;
    document.getElementById('modal-contact').textContent = spot.contact || 'N/A';
    document.getElementById('modal-description').textContent = spot.description;

    // Render Highlights
    const highlightsContainer = document.getElementById('modal-highlights');
    if (spot.highlights && spot.highlights.length > 0) {
        highlightsContainer.innerHTML = spot.highlights.map(tag => `<span class="highlight-tag">${tag}</span>`).join('');
    } else {
        highlightsContainer.innerHTML = '';
    }
    
    // Update Direction Link
    const directionBtn = document.getElementById('modal-direction');
    directionBtn.href = `https://www.google.com/maps/search/?api=1&query=${encodeURIComponent(spot.name + ' ' + spot.location)}`;
    
    // Remove existing event listeners (by cloning)
    const newDirectionBtn = directionBtn.cloneNode(true);
    directionBtn.parentNode.replaceChild(newDirectionBtn, directionBtn);
    
    // Add click event to log activity
    newDirectionBtn.addEventListener('click', (e) => {
        toggleActivity(spot.id, 'direction', newDirectionBtn); 
    });

    // --- Book Button Logic ---
    const bookBtn = document.getElementById('modal-book');
    if (bookBtn) {
        // Remove existing listeners
        const newBookBtn = bookBtn.cloneNode(true);
        bookBtn.parentNode.replaceChild(newBookBtn, bookBtn);

        // Check if spot is free
        const feeText = spot.entranceFee ? spot.entranceFee.toString().toLowerCase().trim() : '';
        const isFree = (feeText === 'free' || feeText === 'free entry' || feeText === 'no entrance fee') || 
                       (spot.price !== undefined && parseFloat(spot.price) === 0);

        if (isFree) {
            newBookBtn.style.display = 'none';
        } else {
            newBookBtn.style.display = '';
            // Add Click Listener
            newBookBtn.addEventListener('click', () => {
                 bookSpot(spot.id);
            });
        }
    }

    const statusBadge = document.getElementById('modal-status');
    statusBadge.className = `status-badge ${open ? 'status-open' : 'status-closed'}`;
    statusBadge.textContent = open ? 'Open Now' : 'Closed';
    
    // Reset Star UI
    resetStars();
    const commentBox = document.getElementById('rating-comment');
    if(commentBox) commentBox.value = '';
    
    // Setup Star Click Events
    const stars = document.querySelectorAll('.star-rating i');
    stars.forEach(star => {
        star.onclick = function() {
            currentRating = this.getAttribute('data-rating');
            updateStars(currentRating);
        };
    });

    modal.classList.add('show');
}

function updateStars(rating) {
    const stars = document.querySelectorAll('.star-rating i');
    stars.forEach(star => {
        const r = star.getAttribute('data-rating');
        if (r <= rating) {
            star.classList.remove('far');
            star.classList.add('fas');
            star.classList.add('active');
        } else {
            star.classList.remove('fas');
            star.classList.add('far');
            star.classList.remove('active');
        }
        // Remove inline color styles to allow CSS to handle colors and transitions
        star.style.removeProperty('color');
    });
}

function resetStars() {
    currentRating = 0;
    const stars = document.querySelectorAll('.star-rating i');
    stars.forEach(star => {
        star.classList.remove('fas');
        star.classList.add('far');
        star.classList.remove('active');
        star.style.removeProperty('color');
    });
}

async function submitRating() {
    if (!currentSpotId) return;
    if (currentRating === 0) {
        alert('Please select a rating star.');
        return;
    }
    
    const comment = document.getElementById('rating-comment') ? document.getElementById('rating-comment').value : '';
    
    try {
        const response = await fetch('./api/submit_review.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                spot_id: currentSpotId,
                rating: currentRating,
                comment: comment
            })
        });
        
        const result = await response.json();
        
        if (result.success) {
            alert(result.message);
        } else {
            alert(result.message);
        }
    } catch (error) {
        console.error('Error submitting review:', error);
        alert('An error occurred. Please try again.');
    }
}

// Book a Spot - Opens Modal
function bookSpot(spotId) {
    const spot = spots.find(s => s.id === spotId);
    if (!spot) return;

    // Populate Modal
    document.getElementById('booking-spot-name').textContent = spot.name;
    document.getElementById('booking-spot-id').value = spot.id;
    
    // Price logic: use spot.price if available, otherwise 0
    let price = parseFloat(spot.price);
    if (isNaN(price)) price = 0;
    
    document.getElementById('booking-spot-price').value = price;
    document.getElementById('booking-price-display').textContent = price.toLocaleString();
    
    // Reset Form
    const dateInput = document.getElementById('booking-date');
    if (dateInput._flatpickr) {
        dateInput._flatpickr.clear();
        // Set minimum date to today
        dateInput._flatpickr.set('minDate', 'today');
    } else {
        dateInput.value = '';
    }
    
    document.getElementById('booking-adults').value = 1;
    document.getElementById('booking-children').value = 0;
    document.getElementById('booking-request').value = '';
    
    // Reset Button Text
    const submitBtn = document.querySelector('#booking-form .booking-submit-btn');
    if (price > 0) {
        submitBtn.innerHTML = 'Proceed to Payment <i class="fas fa-arrow-right"></i>';
    } else {
        submitBtn.innerHTML = 'Confirm Booking <i class="fas fa-arrow-right"></i>';
    }

    calculateTotal();
    
    // Show Modal
    document.getElementById('booking-modal').classList.add('show');
    document.body.style.overflow = 'hidden';
}

function closeBookingModal() {
    const modal = document.getElementById('booking-modal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = '';
    }
}

function calculateTotal() {
    const price = parseFloat(document.getElementById('booking-spot-price').value) || 0;
    const adults = parseInt(document.getElementById('booking-adults').value) || 0;
    const children = parseInt(document.getElementById('booking-children').value) || 0;
    
    const total = price * (adults + children);
    document.getElementById('booking-total-price').textContent = '₱' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
}

// Format Card Inputs
document.addEventListener('DOMContentLoaded', () => {
    const cardNum = document.getElementById('card-number');
    if(cardNum) {
        cardNum.addEventListener('input', function (e) {
             e.target.value = e.target.value.replace(/\D/g, '').replace(/(.{4})/g, '$1 ').trim();
        });
    }
    const cardExp = document.getElementById('card-expiry');
    if(cardExp) {
        cardExp.addEventListener('input', function (e) {
             e.target.value = e.target.value.replace(/\D/g, '').replace(/^(\d{2})(\d{0,2})/, '$1/$2').trim();
        });
    }
});

function closePaymentModal() {
    const modal = document.getElementById('payment-modal');
    if (modal) {
        modal.classList.remove('show');
    }
}

// Payment Flow Logic
function proceedToPayment() {
    // 1. Validate Booking Form
    const date = document.getElementById('booking-date').value;
    if (!date) {
        alert('Please select a date.');
        return;
    }

    // 2. Check Price
    const price = parseFloat(document.getElementById('booking-spot-price').value) || 0;
    const adults = parseInt(document.getElementById('booking-adults').value) || 0;
    const children = parseInt(document.getElementById('booking-children').value) || 0;
    const total = price * (adults + children);

    if (total > 0) {
        // Show Payment Modal
        document.getElementById('booking-modal').classList.remove('show');
        document.getElementById('payment-modal').classList.add('show');
        document.getElementById('payment-amount').textContent = '₱' + total.toLocaleString(undefined, {minimumFractionDigits: 2});
        
        // Reset Payment Form
        document.getElementById('card-number').value = '';
        document.getElementById('card-expiry').value = '';
        document.getElementById('card-cvv').value = '';
        const holderInput = document.getElementById('card-holder-input');
        holderInput.value = holderInput.getAttribute('data-default-name') || '';
        // Ensure card is front-facing
        document.querySelector('.card-wrapper').classList.remove('flipped');
    } else {
        // Free -> Submit Directly
        submitBookingFinal();
    }
}

// Replaces original submitBooking
async function submitBooking() {
    // This is called by the Booking Modal form submit
    proceedToPayment();
}

async function submitPayment() {
    // This is called by the Payment Modal form submit
    
    // Payment Details
    const cardNumber = document.getElementById('card-number').value.replace(/\s/g, '');
    const cardExpiry = document.getElementById('card-expiry').value;
    const cardCvv = document.getElementById('card-cvv').value;
    const cardHolder = document.getElementById('card-holder-input').value.trim();

    // Validation
    if (cardNumber.length < 13 || cardNumber.length > 19) {
        alert('Please enter a valid card number.');
        return;
    }
    if (!cardHolder) {
        alert('Please enter the cardholder name.');
        return;
    }
    if (!/^\d{2}\/\d{2}$/.test(cardExpiry)) {
        alert('Please enter a valid expiry date (MM/YY).');
        return;
    }
    if (cardCvv.length < 3) {
        alert('Please enter a valid CVV.');
        return;
    }

    // Submit with Payment Data
    await submitBookingFinal({
        card_number: cardNumber,
        expiry: cardExpiry,
        cvv: cardCvv,
        name: cardHolder
    });
}

async function submitBookingFinal(paymentData = null) {
    const spotId = document.getElementById('booking-spot-id').value;
    const date = document.getElementById('booking-date').value;
    const adults = document.getElementById('booking-adults').value;
    const children = document.getElementById('booking-children').value;
    const request = document.getElementById('booking-request').value;

    try {
        // Show processing state
        let btn;
        let originalText;
        
        if (paymentData) {
            btn = document.getElementById('pay-button');
            if (btn) {
                originalText = btn.innerHTML;
                btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Processing...';
                btn.disabled = true;
            }
        } else {
            // Free booking button
            btn = document.querySelector('#booking-form .booking-submit-btn');
            originalText = btn.innerHTML;
            btn.innerHTML = '<i class="fas fa-spinner fa-spin"></i> Booking...';
            btn.disabled = true;
        }

        const payload = {
            spot_id: spotId,
            booking_date: date,
            num_adults: adults,
            num_children: children,
            special_request: request
        };

        if (paymentData) {
            payload.payment = paymentData;
        }

        const response = await fetch('./api/book_spot.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });
        
        const result = await response.json();
        
        if (result.success) {
            showToast(result.message);
            closeBookingModal();
            // closePaymentModal(); // Removed as requested to keep payment card open
        } else {
            // Still simulate success as requested
            console.warn('Backend Error (Simulating Success):', result.message);
            showToast('Booking confirmed! Payment successful.');
            closeBookingModal();
            // closePaymentModal(); // Removed as requested
        }

        // Restore button state
        if (btn) {
            // If it was the payment button, update text to show success
            if (btn.id === 'pay-button') {
                btn.innerHTML = 'Paid <i class="fas fa-check"></i>';
                btn.disabled = true; // Keep disabled to prevent double payment
            } else {
                btn.innerHTML = originalText;
                btn.disabled = false;
            }
        }

    } catch (error) {
        console.error('Error booking spot (Simulating Success):', error);
        showToast('Booking confirmed! Payment successful.');
        closeBookingModal();
        // closePaymentModal(); // Removed as requested

        if (btn) {
             if (btn.id === 'pay-button') {
                 btn.innerHTML = 'Paid <i class="fas fa-check"></i>';
                 btn.disabled = true;
             } else {
                 btn.disabled = false;
                 if (originalText) btn.innerHTML = originalText;
             }
        }
    }
}

// Toast Notification Logic
function showToast(message) {
    const toast = document.getElementById('toast');
    const toastMessage = document.getElementById('toast-message');
    
    if (toast && toastMessage) {
        toastMessage.textContent = message;
        toast.classList.add('show');
        
        // Hide after 3 seconds
        setTimeout(() => {
            toast.classList.remove('show');
        }, 3000);
    }
}

function closeModal() {
    const modal = document.getElementById('modal');
    if (modal) {
        modal.classList.remove('show');
        document.body.style.overflow = ''; // Restore scrolling
    }
}

/* Settings Modal Logic */
function openSettingsModal() {
    const modal = document.getElementById('settings-modal');
    modal.classList.add('show');
}

function closeSettingsModal() {
    const modal = document.getElementById('settings-modal');
    modal.classList.remove('show');
}

function changeLanguage(lang) {
    console.log(`Language switched to ${lang}`);
    const selectField = document.querySelector('#google_translate_element select');
    
    // Set the cookie for Google Translate
    // Format: /source_lang/target_lang
    const value = `/en/${lang}`;
    document.cookie = `googtrans=${value}; path=/; domain=${window.location.hostname}`;
    document.cookie = `googtrans=${value}; path=/;`; // Fallback
    
    // Reload to apply
    window.location.reload();
}

// Check for saved language on load to set dropdown
document.addEventListener('DOMContentLoaded', () => {
    const cookies = document.cookie.split(';');
    const googtrans = cookies.find(c => c.trim().startsWith('googtrans='));
    if (googtrans) {
        const lang = googtrans.split('/').pop();
        const select = document.getElementById('language-select');
        if (select && lang) {
            select.value = lang;
        }
    }
});

function setTextSize(size) {
    const body = document.body;
    const btnNormal = document.getElementById('text-normal');
    const btnLarge = document.getElementById('text-large');
    const html = document.documentElement;

    if (size === 'large') {
        html.classList.add('text-large');
        btnNormal.classList.remove('active');
        btnLarge.classList.add('active');
        localStorage.setItem('textSize', 'large');
    } else {
        html.classList.remove('text-large');
        btnNormal.classList.add('active');
        btnLarge.classList.remove('active');
        localStorage.setItem('textSize', 'normal');
    }
}

// Close modal when clicking outside
window.onclick = function(event) {
    const modal = document.getElementById('modal');
    const settingsModal = document.getElementById('settings-modal');
    const bookingModal = document.getElementById('booking-modal');
    const logoutModal = document.getElementById('logout-modal');
    
    if (event.target == modal) {
        closeModal();
    }
    if (event.target == settingsModal) {
        closeSettingsModal();
    }
    if (event.target == bookingModal) {
        closeBookingModal();
    }
    if (event.target == logoutModal) {
        closeLogoutModal();
    }
    
    // Close profile dropdown if clicked outside
    const profileMini = document.querySelector('.user-profile-mini');
    const dropdown = document.getElementById('profile-dropdown');
    
    if (profileMini && dropdown && !profileMini.contains(event.target)) {
        dropdown.classList.remove('show');
    }
}

/* Profile Dropdown Logic */
function toggleProfileDropdown() {
    const dropdown = document.getElementById('profile-dropdown');
    dropdown.classList.toggle('show');
}

/* Logout Modal Logic */
function confirmLogout(event) {
    if(event) event.preventDefault();
    const modal = document.getElementById('logout-modal');
    if (modal) {
        modal.classList.add('show');
    } else {
        // Fallback if modal is missing or error
        window.location.href = 'logout.php';
    }
}

function closeLogoutModal() {
    const modal = document.getElementById('logout-modal');
    modal.classList.remove('show');
}

function performLogout() {
    window.location.href = 'logout.php';
}

// Render Sections
function renderSpots() {
    const destinationContainer = document.getElementById('spots-grid');
    const featuredContainer = document.getElementById('featured-grid');
    const topRatedContainer = document.getElementById('top-rated-grid');

    // All spots go to the main destination grid now
    if(destinationContainer) destinationContainer.innerHTML = spots.map(createCard).join('');

    // Featured (Admin Selected)
    const featuredSpots = spots.filter(s => s.featured == 1);
    if(featuredContainer) {
        if (featuredSpots.length > 0) {
            featuredContainer.innerHTML = featuredSpots.slice(0, 3).map(createCard).join('');
        } else {
            // Fallback: Random 3 if no featured spots set
            const random = [...spots].sort(() => 0.5 - Math.random()).slice(0, 3);
            featuredContainer.innerHTML = random.map(createCard).join('');
        }
    }

    // Top Rated by Travelers (Based on Rating)
    if(topRatedContainer) {
        // Sort by average_rating descending
        const topRated = [...spots].sort((a, b) => {
            const ratingA = parseFloat(a.average_rating) || 0;
            const ratingB = parseFloat(b.average_rating) || 0;
            return ratingB - ratingA;
        }).slice(0, 3);
        
        topRatedContainer.innerHTML = topRated.map(createCard).join('');
    }
}

// Filter Function
function filterSpots() {
    const searchText = document.getElementById('search-input').value.toLowerCase();
    const statusFilter = document.getElementById('status-filter').value;
    const categoryFilter = document.getElementById('category-filter').value.toLowerCase();
    const container = document.getElementById('spots-grid');
    
    const filtered = spots.filter(spot => {
        const matchesText = spot.name.toLowerCase().includes(searchText) || 
                          spot.location.toLowerCase().includes(searchText) ||
                          spot.description.toLowerCase().includes(searchText);

        let matchesStatus = true;
        const open = isOpen(spot.openTime, spot.closeTime);
        if (statusFilter === 'open') matchesStatus = open;
        if (statusFilter === 'closed') matchesStatus = !open;

        let matchesCategory = true;
        if (categoryFilter !== 'all') {
            // Check if spot category matches or contains the filter
            matchesCategory = spot.category && spot.category.toLowerCase().includes(categoryFilter);
            
            // Special mappings for specific categories if needed
            if (!matchesCategory) {
                if (categoryFilter === 'food & dining') matchesCategory = spot.category && (spot.category.toLowerCase().includes('food') || spot.category.toLowerCase().includes('restaurant') || spot.category.toLowerCase().includes('delicacy') || spot.category.toLowerCase().includes('dining'));
                if (categoryFilter === 'springs') matchesCategory = spot.category && (spot.category.toLowerCase().includes('hot spring') || spot.category.toLowerCase().includes('spring'));
                if (categoryFilter === 'parks') matchesCategory = spot.category && (spot.category.toLowerCase().includes('park') || spot.category.toLowerCase().includes('plaza'));
                if (categoryFilter === 'museums') matchesCategory = spot.category && (spot.category.toLowerCase().includes('museum') || spot.category.toLowerCase().includes('shrine'));
            }
        }

        return matchesText && matchesStatus && matchesCategory;
    });

    if (filtered.length > 0) {
        container.innerHTML = filtered.map(createCard).join('');
    } else {
        container.innerHTML = '<p>No spots found matching your criteria.</p>';
    }
}

// Navigation Logic
function showSection(sectionId) {
    // Hide all sections
    document.querySelectorAll('.section').forEach(sec => {
        sec.classList.remove('active-section');
    });
    

    const target = document.getElementById(sectionId);
    if (target) {
        target.classList.add('active-section');
    }


    document.querySelectorAll('.nav-links li').forEach(li => {
        li.classList.remove('active');

        if (li.getAttribute('onclick').includes(sectionId)) {
            li.classList.add('active');
        }
    });

    if (window.innerWidth <= 768) {
        closeMobileMenu();
    }
}

// Mobile Menu Toggle
function toggleMobileMenu() {
    const navMenu = document.getElementById('nav-menu');
    if (navMenu) {
        navMenu.classList.toggle('active');
    }
}

function closeMobileMenu() {
    const navMenu = document.getElementById('nav-menu');
    if (navMenu && navMenu.classList.contains('active')) {
        navMenu.classList.remove('active');
    }
}




document.addEventListener('DOMContentLoaded', () => {
    fetchSpots();

    // Restore Text Size
    const savedSize = localStorage.getItem('textSize');
    if (savedSize === 'large') {
        setTextSize('large');
    }

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

    // Check Login State
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    updateAccountUI(isLoggedIn);

    // Initialize Flatpickr (Calendar)
    const dateInput = document.getElementById('booking-date');
    if (dateInput) {
        flatpickr(dateInput, {
            dateFormat: "Y-m-d",
            altInput: true,
            altFormat: "F j, Y",
            minDate: "today",
            animate: true,
            disableMobile: "true", // Use custom theme even on mobile
            monthSelectorType: "static", // Cleaner header
            locale: {
                firstDayOfWeek: 1 // Start week on Monday
            },
            onChange: function(selectedDates, dateStr, instance) {
                // Could trigger validation here
            }
        });
    }
});

function toggleLogin() {
    const isLoggedIn = localStorage.getItem('isLoggedIn') === 'true';
    
    if (isLoggedIn) {
        // Logout
        localStorage.setItem('isLoggedIn', 'false');
        updateAccountUI(false);
    } else {
        // Login (Simulate)
        localStorage.setItem('isLoggedIn', 'true');
        updateAccountUI(true);
    }
}

function updateAccountUI(isLoggedIn) {
    const loginBtn = document.getElementById('login-btn');
    const userInfo = document.getElementById('user-info');
    
    if (!loginBtn || !userInfo) return;

    if (isLoggedIn) {
        loginBtn.style.display = 'none';
        userInfo.style.display = 'flex';
    } else {
        loginBtn.style.display = 'block';
        userInfo.style.display = 'none';
    }
}

async function submitMessage() {
    const name = document.getElementById('contact-name').value;
    const email = document.getElementById('contact-email').value;
    const message = document.getElementById('contact-message').value;

    if (!name || !email || !message) {
        alert('Please fill in all fields');
        return;
    }

    try {
        const response = await fetch('./api/send_message.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ name, email, message })
        });
        const result = await response.json();

        if (result.success) {
            alert('Message sent successfully!');
            document.getElementById('contact-form').reset();
        } else {
            alert('Error sending message: ' + result.message);
        }
    } catch (error) {
        console.error('Error:', error);
        alert('An error occurred. Please try again.');
    }
}

/* Chat AI Logic */
function toggleChat() {
    const chatWidget = document.getElementById('chat-widget');
    chatWidget.classList.toggle('active');
    
    // Focus input when opened
    if (chatWidget.classList.contains('active')) {
        document.getElementById('chat-input').focus();
    }
}

function handleChatInput(event) {
    if (event.key === 'Enter') {
        sendChatMessage();
    }
}

let chatAbortController = null;
let isChatProcessing = false;

// --- Helper for Cross-Device API Calls ---
function getApiUrl(endpoint) {
    const basePath = window.location.pathname.substring(0, window.location.pathname.lastIndexOf('/') + 1);
    return basePath + endpoint;
}

async function sendChatMessage(text = null) {
    if (isChatProcessing) return;

    // 1. Check Internet Connection
    if (!navigator.onLine) {
        addMessageToChat("I need an internet connection to think! Please check your network.", 'bot');
        return;
    }

    const input = document.getElementById('chat-input');
    const message = text || input.value.trim();
    
    if (!message) return;
    
    // Add user message
    addMessageToChat(message, 'user');
    if (!text) input.value = ''; // Only clear input if typed manually
    
    // Set processing state
    isChatProcessing = true;
    const sendBtn = document.getElementById('send-btn');
    const stopBtn = document.getElementById('stop-btn');
    if(sendBtn) sendBtn.style.display = 'none';
    if(stopBtn) stopBtn.style.display = 'flex';
    input.disabled = true;

    // Initialize AbortController
    chatAbortController = new AbortController();
    
    // Show typing placeholder
    const loadingId = addMessageToChat("Thinking...", 'bot', true);
    
    try {
        const response = await fetch(getApiUrl('api/chat_ai.php'), {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({ message: message }),
            signal: chatAbortController.signal
        });
        
        const result = await response.json();
        
        // Remove loading message
        const loadingMsg = document.getElementById(loadingId);
        if (loadingMsg) loadingMsg.remove();
        
        if (result.success) {
            addMessageToChat(result.reply, 'bot');
        } else {
            addMessageToChat(result.reply || "I'm having trouble connecting right now.", 'bot');
        }
    } catch (error) {
        // Remove loading message
        const loadingMsg = document.getElementById(loadingId);
        if (loadingMsg) loadingMsg.remove();

        if (error.name === 'AbortError') {
            addMessageToChat("<i>Stopped by user.</i>", 'bot');
        } else {
            console.error('Chat Error:', error);
            addMessageToChat("Sorry, I can't reach my brain (server error). Please try again later.", 'bot');
        }
    } finally {
        isChatProcessing = false;
        chatAbortController = null;
        if(sendBtn) sendBtn.style.display = 'flex';
        if(stopBtn) stopBtn.style.display = 'none';
        input.disabled = false;
        input.focus();
    }
}

function stopChatProcessing() {
    if (chatAbortController) {
        chatAbortController.abort();
    }
}

function parseMarkdown(text) {
    if (!text) return '';
    
    // Escape HTML to prevent XSS (basic)
    let html = text.replace(/&/g, "&amp;")
                   .replace(/</g, "&lt;")
                   .replace(/>/g, "&gt;");

    // Bold (**text**)
    html = html.replace(/\*\*(.*?)\*\*/g, '<strong>$1</strong>');
    
    // Italic (*text*)
    html = html.replace(/\*(.*?)\*/g, '<em>$1</em>');
    
    // Lists (- item)
    html = html.replace(/^\s*-\s+(.*)$/gm, '<li>$1</li>');
    
    // Wrap lists in <ul> (simple heuristic: if we have <li>, wrap lines with <li> in <ul>)
    // Note: This is a simple regex approach, a full parser would be better but this suffices for simple lists
    if (html.includes('<li>')) {
        // Group consecutive <li> items
        html = html.replace(/(<li>.*<\/li>(\s*<li>.*<\/li>)*)/g, '<ul>$1</ul>');
    }

    // Line breaks
    html = html.replace(/\n/g, '<br>');
    
    return html;
}

// Stepper Logic for Booking Modal
function updateStepper(id, change) {
    const input = document.getElementById(id);
    if (!input) return;
    
    let currentVal = parseInt(input.value) || 0;
    let newVal = currentVal + change;
    
    // Get min value (default 0)
    const min = parseInt(input.getAttribute('min')) || 0;
    
    if (newVal < min) newVal = min;
    
    input.value = newVal;
    
    // Trigger calculation
    calculateTotal();
}

function addMessageToChat(text, sender, isLoading = false) {
    const chatMessages = document.getElementById('chat-messages');
    const messageDiv = document.createElement('div');
    messageDiv.classList.add('message');
    messageDiv.classList.add(sender === 'user' ? 'user-message' : 'bot-message');
    
    // Use innerHTML for bot messages to support markdown, textContent for user to be safe
    if (sender === 'bot') {
        messageDiv.innerHTML = parseMarkdown(text);
    } else {
        messageDiv.textContent = text;
    }
    
    if (isLoading) {
        messageDiv.id = 'loading-' + Date.now();
        messageDiv.style.fontStyle = 'italic';
        messageDiv.style.opacity = '0.7';
    }
    
    chatMessages.appendChild(messageDiv);
    
    // Scroll to bottom
    chatMessages.scrollTop = chatMessages.scrollHeight;
    
    return messageDiv.id;
}

/* Sidebar & History Logic */

function toggleSidebar() {
    const sidebar = document.getElementById('chat-sidebar');
    const widget = document.getElementById('chat-widget');
    const toggleBtn = document.querySelector('.sidebar-toggle');
    
    // Toggle active state classes
    // Note: We use requestAnimationFrame to ensure transitions trigger if we were using display:none logic
    // But since we switched to width/opacity transition, we don't need complex display logic anymore.
    // However, to keep it clean, we check class presence.
    
    if (!sidebar.classList.contains('active')) {
        // Open Sidebar
        sidebar.style.display = 'flex'; // Ensure it's part of layout
        // Trigger reflow
        void sidebar.offsetWidth; 
        
        sidebar.classList.add('active');
        toggleBtn.classList.add('active');
        
        if (window.innerWidth > 480) {
             widget.classList.add('expanded');
        }
        
        loadHistoryList();
    } else {
        // Close Sidebar
        sidebar.classList.remove('active');
        toggleBtn.classList.remove('active');
        widget.classList.remove('expanded');
        
        // Wait for transition to finish before hiding (optional, but good for performance)
        setTimeout(() => {
            if (!sidebar.classList.contains('active')) {
                sidebar.style.display = 'none';
            }
        }, 400); // Match CSS transition duration
    }
}

function loadHistoryList() {
    fetch(getApiUrl('api/chat_ai.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'get_conversations' })
    })
    .then(res => res.json())
    .then(data => {
        const list = document.getElementById('history-list');
        list.innerHTML = '';
        if (data.success && data.conversations && data.conversations.length > 0) {
            data.conversations.forEach(conv => {
                const btn = document.createElement('button');
                btn.className = 'history-item';
                btn.textContent = conv.title || 'Untitled Chat';
                btn.onclick = () => loadConversation(conv.id);
                list.appendChild(btn);
            });
        } else if (data.message === 'Not logged in') {
             list.innerHTML = '<div style="padding:10px; font-size:12px; color:#666;">Log in to save history</div>';
        } else {
             list.innerHTML = '<div style="padding:10px; font-size:12px; color:#666;">No history yet</div>';
        }
    })
    .catch(err => console.error(err));
}

function startNewChat() {
    fetch(getApiUrl('api/chat_ai.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'new_chat' })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            document.getElementById('chat-messages').innerHTML = `
                <div class="message bot-message">
                    Hello! I'm Doquerainee, your friendly local guide. I can answer questions about Laguna!
                </div>`;
            // Refresh history list (to show the just-closed chat?)
            loadHistoryList();
        }
    });
}

function loadConversation(id) {
    // Show loading?
    const msgs = document.getElementById('chat-messages');
    msgs.innerHTML = '<div style="text-align:center; padding:20px; color:#666;">Loading...</div>';
    
    fetch(getApiUrl('api/chat_ai.php'), {
        method: 'POST',
        headers: { 'Content-Type': 'application/json' },
        body: JSON.stringify({ action: 'load_conversation', id: id })
    })
    .then(res => res.json())
    .then(data => {
        if (data.success) {
            msgs.innerHTML = ''; // Clear loading
            data.history.forEach(item => {
                const role = item.role === 'model' ? 'bot' : 'user';
                const text = item.parts[0].text;
                addMessageToChat(text, role);
            });
        }
    });
}
