<!DOCTYPE html>
<html lang="en">
<head> 
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.0.0/css/all.min.css">
    <link rel="stylesheet" href="style.css?v=<?php echo time(); ?>">
    <link rel="stylesheet" href="login.css?v=<?php echo time(); ?>">
    <title>Sign in & Sign up Form</title>
</head>
<body> 
    <div class="auth-container">
        <ul class="circles">
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
            <li></li>
        </ul>
        <div class="forms-container">
            <div class="signin-signup">
                <form id="signin-form" class="sign-in-form">
                    <div id="login-fields">
                        <h2 class="title">Sign in</h2>
                        <div class="input-field">
                            <i class="fas fa-user"></i>
                            <input type="text" id="signin-username" placeholder="Username" required>
                        </div>
                        <div class="input-field password-field">
                            <i class="fas fa-lock"></i>
                            <input type="password" id="signin-password" placeholder="Password" required>
                            <i class="fas fa-eye toggle-password" onclick="togglePassword('signin-password', this)"></i>
                        </div>
                        <input type="submit" value="Login" class="btn solid">
                        
                        <div class="divider">
                            <span>Or continue with</span>
                        </div>
                        <div class="social-media">
                            <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                            <a href="#" class="social-icon"><i class="fab fa-google"></i></a>
                        </div>
                    </div>

                    <div id="2fa-fields" style="display: none; width: 100%; text-align: center;">
                        <h2 class="title">Verification</h2>
                        <p style="margin-bottom: 20px;">Enter the 6-digit code sent to your email.</p>
                        <div class="input-field" style="margin: 0 auto 20px auto;">
                            <i class="fas fa-envelope"></i>
                            <input type="text" id="2fa-code" placeholder="------" pattern="[0-9]*" inputmode="numeric" maxlength="6" style="text-align: center; letter-spacing: 5px;">
                        </div>
                        <input type="submit" value="Verify" class="btn solid">
                        <div style="margin-top: 15px; display: flex; justify-content: center; gap: 10px;">
                            <button type="button" class="btn secondary" onclick="resendLoginCode()" style="font-size: 0.9em;">Resend Code</button>
                            <button type="button" class="btn secondary" onclick="cancel2FA()" style="font-size: 0.9em;">Cancel</button>
                        </div>
                    </div>
                </form>

                <form id="signup-form" class="sign-up-form">
                    <h2 class="title">Sign up</h2>
                    
                    <div class="input-field">
                        <i class="fas fa-user-tag"></i>
                        <select id="signup-role" required onchange="toggleBusinessFields()">
                            <option value="user">User</option>
                            <option value="business_owner">Business Owner</option>
                        </select>
                    </div>

                    <div class="input-field">
                        <i class="fas fa-user"></i>
                        <input type="text" id="signup-username" placeholder="Username" required>
                    </div>
                    <div class="input-field">
                        <i class="fas fa-envelope"></i>
                        <input type="email" id="signup-email" placeholder="Email" required>
                    </div>
                    <div class="input-field password-field">
                        <i class="fas fa-lock"></i>
                        <input type="password" id="signup-password" placeholder="Password" required>
                        <i class="fas fa-eye toggle-password" onclick="togglePassword('signup-password', this)"></i>
                    </div>

                    <!-- Business Fields (Hidden by default) -->
                    <div id="business-fields" style="display: none; width: 100%;">
                        <div class="input-field">
                            <i class="fas fa-building"></i>
                            <input type="text" id="business-name" placeholder="Business Name">
                        </div>
                        <div class="input-field">
                            <i class="fas fa-map-marker-alt"></i>
                            <input type="text" id="business-address" placeholder="Business Address">
                        </div>
                        <div class="input-field">
                            <i class="fas fa-id-card"></i>
                            <input type="text" id="permit-number" placeholder="Permit Number">
                        </div>
                        <div class="input-field">
                            <i class="fas fa-phone"></i>
                            <input type="text" id="contact-number" placeholder="Contact Number">
                        </div>
                    </div>

                    <input type="submit" value="Sign up" class="btn">
                    
                    <div class="divider">
                        <span>Or join with</span>
                    </div>
                    <div class="social-media">
                        <a href="#" class="social-icon"><i class="fab fa-facebook-f"></i></a>
                        <a href="#" class="social-icon"><i class="fab fa-google"></i></a>
                    </div>
                </form>
            </div>
        </div>

        <div class="panels-container">
            <div class="panel left-panel">
                <div class="content">
                    <h3>New here?</h3>
                    <p>Discover the beauty, culture, and flavors of the Philippines' resort capital.</p>
                    <button class="btn transparent" id="sign-up-btn">Sign up</button>
                    <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> Back to Home</a>
                </div>
                <img src="laguna-photos/laguna-province-1.jpg" class="image" alt="">
            </div>

            <div class="panel right-panel">
                <div class="content">
                    <h3>One of us?</h3>
                    <p>Welcome back! Continue your journey through the wonders of Laguna.</p>
                    <button class="btn transparent" id="sign-in-btn">Sign in</button>
                    <a href="index.php" class="back-home"><i class="fas fa-arrow-left"></i> Back to Home</a>
                </div>
                <img src="laguna-photos/rizalshrine.webp" class="image" alt="">
            </div>
        </div>
    </div>

    <!-- Custom Modal -->
    <div id="custom-modal" class="modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <p id="modal-message"></p>
            <button id="modal-ok-btn" class="btn">OK</button>
        </div>
    </div>

    <script>
        // Modal Logic
        const modal = document.getElementById("custom-modal");
        const modalMessage = document.getElementById("modal-message");
        const closeModal = document.querySelector(".close-modal");
        const modalOkBtn = document.getElementById("modal-ok-btn");

        function showModal(message) {
            modalMessage.textContent = message;
            modal.style.display = "flex";
        }

        function hideModal() {
            modal.style.display = "none";
        }

        closeModal.onclick = hideModal;
        modalOkBtn.onclick = hideModal;
        
        window.onclick = function(event) {
            if (event.target == modal) {
                hideModal();
            }
        }

        function togglePassword(inputId, icon) {
            const input = document.getElementById(inputId);
            if (input.type === "password") {
                input.type = "text";
                icon.classList.remove("fa-eye");
                icon.classList.add("fa-eye-slash");
            } else {
                input.type = "password";
                icon.classList.remove("fa-eye-slash");
                icon.classList.add("fa-eye");
            }
        }

        const sign_in_btn = document.querySelector("#sign-in-btn");
        const sign_up_btn = document.querySelector("#sign-up-btn");
        const container = document.querySelector(".auth-container");

        sign_up_btn.addEventListener("click", () => {
            container.classList.add("sign-up-mode");
        });

        sign_in_btn.addEventListener("click", () => {
            container.classList.remove("sign-up-mode");
        });

        function cancel2FA() {
            document.getElementById('2fa-fields').style.display = 'none';
            document.getElementById('login-fields').style.display = 'block';
            document.getElementById('2fa-code').value = '';
            document.getElementById('signin-password').value = '';
        }

        async function resendLoginCode() {
            try {
                const response = await fetch('api/send_login_code.php');
                const result = await response.json();
                showModal(result.message);
            } catch (error) {
                console.error('Error resending code:', error);
                showModal('An error occurred.');
            }
        }

        // Sign In Logic
        document.getElementById('signin-form').addEventListener('submit', async (e) => {
            e.preventDefault();

            // Check if 2FA is visible
            const is2FA = document.getElementById('2fa-fields').style.display !== 'none';
            
            if (is2FA) {
                // Verify 2FA
                const code = document.getElementById('2fa-code').value;
                try {
                    const response = await fetch('api/verify_2fa_login.php', {
                        method: 'POST',
                        headers: { 'Content-Type': 'application/json' },
                        body: JSON.stringify({ code })
                    });
                    const result = await response.json();
                    if (result.success) {
                        window.location.href = result.redirect || 'index.php';
                    } else {
                        showModal(result.message);
                    }
                } catch (error) {
                    showModal('Error verifying code.');
                }
                return;
            }

            // Normal Login
            const username = document.getElementById('signin-username').value;
            const password = document.getElementById('signin-password').value;

            try {
                const response = await fetch('api/login.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify({ username, password })
                });
                
                const result = await response.json();
                
                if (result.success) {
                    if (result.requires_2fa) {
                        document.getElementById('login-fields').style.display = 'none';
                        document.getElementById('2fa-fields').style.display = 'block';
                        showModal(result.message);
                    } else {
                        window.location.href = result.redirect || 'index.php';
                    }
                } else {
                    showModal(result.message);
                }
            } catch (error) {
                console.error('Error:', error);
                showModal('An error occurred.');
            }
        });

        // Toggle Business Fields
        function toggleBusinessFields() {
            const role = document.getElementById('signup-role').value;
            const businessFields = document.getElementById('business-fields');
            if (role === 'business_owner') {
                businessFields.style.display = 'block';
            } else {
                businessFields.style.display = 'none';
            }
        }

        // Sign Up Logic
        document.getElementById('signup-form').addEventListener('submit', async (e) => {
            e.preventDefault();
            
            const username = document.getElementById('signup-username').value;
            const email = document.getElementById('signup-email').value;
            const password = document.getElementById('signup-password').value;
            const role = document.getElementById('signup-role').value;
            
            const data = { username, email, password, role };
            
            if (role === 'business_owner') {
                data.business_name = document.getElementById('business-name').value;
                data.business_address = document.getElementById('business-address').value;
                data.permit_number = document.getElementById('permit-number').value;
                data.contact_number = document.getElementById('contact-number').value;
                
                if (!data.business_name || !data.business_address || !data.permit_number || !data.contact_number) {
                    showModal("Please fill in all business fields.");
                    return;
                }
            }

            try {
                const response = await fetch('api/signup.php', {
                    method: 'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body: JSON.stringify(data)
                });
                
                const text = await response.text();
                try {
                    const result = JSON.parse(text);
                    if (result.success) {
                        if (role === 'business_owner') {
                            showModal('Account created successfully! Please wait for admin approval.');
                        } else {
                            showModal('Account created successfully! Please sign in.');
                        }
                        container.classList.remove("sign-up-mode");
                    } else {
                        showModal(result.message);
                    }
                } catch (e) {
                    console.error('Server Response:', text);
                    showModal('Server error. Check console for details.');
                }
            } catch (error) {
                console.error('Error:', error);
                showModal('An error occurred. Please try again.');
            }
        });
    </script>
</body>
</html>