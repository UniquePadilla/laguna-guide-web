function showDashSection(sectionId, element) {
    // Update Nav
    document.querySelectorAll('.dash-nav-item').forEach(item => item.classList.remove('active'));
    if(element) element.classList.add('active');
    
    // Update Content
    document.querySelectorAll('.dash-section').forEach(section => section.classList.remove('active'));
    document.getElementById(sectionId).classList.add('active');
}

async function updateProfile(e) {
    e.preventDefault();
    const id = document.getElementById('user-id').value;
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const contact_number = document.getElementById('phone').value;
    
    try {
        const response = await fetch('api/update_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id, username, email, contact_number })
        });
        const result = await response.json();
        if(result.success) {
            alert('Profile updated successfully!');
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred while updating profile.');
    }
}

async function updatePassword(e) {
    e.preventDefault();
    const id = document.getElementById('user-id').value;
    const username = document.getElementById('username').value;
    const email = document.getElementById('email').value;
    const contact_number = document.getElementById('phone').value;
    const newPass = document.getElementById('new-password').value;
    const confirmPass = document.getElementById('confirm-password').value;
    
    if(newPass !== confirmPass) {
        alert('Passwords do not match!');
        return;
    }
    
    try {
        const response = await fetch('api/update_user.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ id, username, email, contact_number, password: newPass })
        });
        const result = await response.json();
        if(result.success) {
            alert('Password updated successfully!');
            document.getElementById('security-form').reset();
        } else {
            alert('Error: ' + result.message);
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred while updating password.');
    }
}

async function toggle2FA() {
    const enabled = document.getElementById('2fa-toggle').checked;
    try {
        const response = await fetch('api/toggle_2fa.php', {
            method: 'POST',
            headers: {'Content-Type': 'application/json'},
            body: JSON.stringify({ enabled })
        });
        const result = await response.json();
        if(result.success) {
            alert(enabled ? '2FA Enabled!' : '2FA Disabled!');
        } else {
            alert('Error: ' + result.message);
            document.getElementById('2fa-toggle').checked = !enabled; // Revert
        }
    } catch (err) {
        console.error(err);
        alert('An error occurred.');
        document.getElementById('2fa-toggle').checked = !enabled; // Revert
    }
}
