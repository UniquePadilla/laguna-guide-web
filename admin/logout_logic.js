
// --- Logout Logic ---
document.addEventListener('DOMContentLoaded', () => {
    const logoutBtn = document.getElementById('logout-btn');
    const logoutModal = document.getElementById('logout-modal');
    const closeLogoutModal = document.getElementById('close-logout-modal');
    const cancelLogout = document.getElementById('cancel-logout');
    const confirmLogout = document.getElementById('confirm-logout');

    if (logoutBtn && logoutModal) {
        logoutBtn.addEventListener('click', (e) => {
            e.preventDefault();
            // Reuse logic from 2FA modal or manually show
            logoutModal.classList.add('show');
            logoutModal.style.display = 'flex';
        });

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
