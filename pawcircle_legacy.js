// NOTE: This file is a legacy frontend helper and is not currently linked by pawcircle_frontend.html.
// It has been made defensive so accidental inclusion does not break the page.

document.addEventListener('DOMContentLoaded', () => {
    // Initialise Lucide icons
    if (typeof lucide !== 'undefined') lucide.createIcons();

    const attach = (id, event, handler) => {
        const el = document.getElementById(id);
        if (el) el.addEventListener(event, handler);
    };

    // Setup Navigation Hooks
    attach('btn-go-to-signup', 'click', () => switchView('view-signup'));
    attach('btn-go-to-admin-login', 'click', () => switchView('view-admin-login'));
    attach('btn-admin-to-public', 'click', () => switchView('view-public-login'));
    attach('btn-signup-to-public', 'click', () => switchView('view-public-login'));

    // Handle logout action
    document.querySelectorAll('.btn-logout').forEach(btn => {
        btn.addEventListener('click', () => logout());
    });

    // Multi-step registration logic
    attach('btn-next-signup', 'click', nextSignupStep);
    attach('btn-back-signup', 'click', prevSignupStep);

    // Form Submissions
    attach('public-login-form', 'submit', handlePublicLogin);
    attach('admin-login-form', 'submit', handleAdminLogin);
    // attach('signup-step-1', 'submit', handleSignupSubmit);
    // attach('signup-step-2', 'submit', handleSignupSubmit);

    // Initialize Local Database Mock (only used if pawcircle_api.php cannot be reached)
    const DEMO_ADMIN = { email: 'admin@esamaj.com', password: 'admin123', role: 'admin' };
    if (!localStorage.getItem('esamaj_mock_db')) {
        localStorage.setItem('esamaj_mock_db', JSON.stringify([]));
    }
});

function switchView(viewId) {
    document.querySelectorAll('.view-section').forEach(el => el.classList.remove('active'));
    document.getElementById(viewId).classList.add('active');
    clearErrors();
}

function clearErrors() {
    document.querySelectorAll('[id$="-error"]').forEach(el => {
        el.classList.add('hidden');
        el.innerText = '';
    });
}

function showError(type, message) {
    const el = document.getElementById(`${type}-error`);
    if (el) {
        el.innerText = message;
        el.classList.remove('hidden');
    }
}

function nextSignupStep() {
    const name = document.getElementById('reg-name').value.trim();
    const email = document.getElementById('reg-email').value.trim();
    const password = document.getElementById('reg-password').value;
    const religion = document.getElementById('reg-religion').value;
    const community = document.getElementById('reg-community').value;

    if (!name || !email || !password || !religion || !community) {
        showError('signup', 'Please fill out all basic credentials and select your community.');
        return;
    }
    if (password.length < 6) {
        showError('signup', 'Password must be at least 6 characters.');
        return;
    }

    clearErrors();
    document.getElementById('signup-step-1').classList.add('hidden');
    document.getElementById('signup-step-2').classList.remove('hidden');
}

function prevSignupStep() {
    document.getElementById('signup-step-2').classList.add('hidden');
    document.getElementById('signup-step-1').classList.remove('hidden');
}

async function handlePublicLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('public-submit-btn');
    const email = document.getElementById('public-email').value.trim();
    const password = document.getElementById('public-password').value;

    btn.disabled = true;
    btn.innerText = "Connecting secure login...";

    try {
        // Attempt to connect to pawcircle_api.php
        const response = await fetch('pawcircle_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'public_login',
                email: email,
                password: password
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            populateMemberDashboard(data.user);
            switchView('view-member-dashboard');
        } else {
            showError('public', data.message);
        }
    } catch (err) {
        console.warn("Backend API not reachable. Falling back to local simulated DB mode.");
        // Simulated local database fallback for instant developer previews
        const db = JSON.parse(localStorage.getItem('esamaj_mock_db'));
        const foundUser = db.find(u => u.email === email && u.password === password);

        if (foundUser) {
            populateMemberDashboard(foundUser);
            switchView('view-member-dashboard');
        } else {
            showError('public', 'Invalid email or password (Using Local Preview Db).');
        }
    } finally {
        btn.disabled = false;
        btn.innerText = "Sign in";
    }
}

async function handleAdminLogin(e) {
    e.preventDefault();
    const btn = document.getElementById('admin-submit-btn');
    const email = document.getElementById('admin-email').value.trim();
    const password = document.getElementById('admin-password').value;

    btn.disabled = true;
    btn.innerText = "Authenticating admin terminal...";

    try {
        const response = await fetch('pawcircle_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify({
                action: 'admin_login',
                email: email,
                password: password
            })
        });

        const data = await response.json();

        if (data.status === 'success') {
            document.getElementById('admin-dash-email').innerText = data.user.email;
            document.getElementById('admin-dash-users').innerText = data.stats?.totalUsers ?? 0;
            switchView('view-admin-dashboard');
        } else {
            showError('admin', data.message);
        }
    } catch (err) {
        console.warn("Backend API offline. Authenticating using Local Preview Admin settings.");
        if (email === 'admin@esamaj.com' && password === 'admin123') {
            const db = JSON.parse(localStorage.getItem('esamaj_mock_db'));
            document.getElementById('admin-dash-email').innerText = email;
            document.getElementById('admin-dash-users').innerText = db.length;
            switchView('view-admin-dashboard');
        } else {
            showError('admin', 'Invalid Admin credentials. (Use admin@esamaj.com / admin123 for Local Preview)');
        }
    } finally {
        btn.disabled = false;
        btn.innerText = "Secure Sign In";
    }
}

async function handleSignupSubmit(e) {
    e.preventDefault();
    const btn = document.getElementById('signup-submit-btn');

    const petName = document.getElementById('reg-name') ? document.getElementById('reg-name').value.trim() : '';
    const parentName = document.getElementById('reg-parent-name') ? document.getElementById('reg-parent-name').value.trim() : '';
    const email = document.getElementById('reg-email') ? document.getElementById('reg-email').value.trim() : '';
    const password = document.getElementById('reg-password') ? document.getElementById('reg-password').value : '';
    
    // Fallback if the user chooses mobile
    const method = document.getElementById('reg-contact-method') ? document.getElementById('reg-contact-method').value : 'email';
    const phone = document.getElementById('reg-phone') ? document.getElementById('reg-phone').value.trim() : '';
    
    // Pet details
    const petType = document.getElementById('reg-religion') ? document.getElementById('reg-religion').value : 'Dog';
    let breed = document.getElementById('reg-breed') ? document.getElementById('reg-breed').value : '';
    if (breed === 'other') {
        const custom = document.getElementById('reg-custom-breed');
        if (custom) breed = custom.value.trim();
    }

    if (!petName || !parentName || (!email && !phone) || !password) {
        showError('signup', 'Please fill in all required fields.');
        return;
    }

    btn.disabled = true;
    btn.innerHTML = "Creating account... <i data-lucide='loader' class='w-5 h-5 ml-2 animate-spin'></i>";
    if (window.lucide) lucide.createIcons();

    const payload = {
        action: 'signup',
        pet_name: petName,
        parent_name: parentName,
        email: email,
        mobile_number: phone,
        password: password,
        pet_type: petType,
        breed: breed
    };

    try {
        const response = await fetch('pawcircle_api.php', {
            method: 'POST',
            headers: { 'Content-Type': 'application/json' },
            body: JSON.stringify(payload)
        });

        const data = await response.json();

        if (data.status === 'success') {
            if (typeof populateMemberDashboard === 'function') populateMemberDashboard(data.user);
            switchView('view-member-dashboard');
            if (typeof resetSignupForms === 'function') resetSignupForms();
        } else {
            showError('signup', data.message);
        }
    } catch (err) {
        console.error("Signup error:", err);
        showError('signup', 'An unexpected error occurred. Please try again.');
    } finally {
        btn.disabled = false;
        btn.innerHTML = `
            <span id="submitLabel">Complete Signup</span>
            <svg id="submitIcon" viewBox="0 0 24 24" style="stroke: #FFF8EC;">
              <path d="M12 22c5.523 0 10-4.477 10-10S17.523 2 12 2 2 6.477 2 12s4.477 10 10 10z" />
              <path d="M9 12l2 2 4-4" />
            </svg>
        `;
    }
}


function populateMemberDashboard(user) {
    document.getElementById('dash-name').innerText = user.name ?? '';
    document.getElementById('dash-email').innerText = user.email ?? '';
    // API returns interests/skills nested under user.personalization;
    // fall back to flat keys for local mock DB compatibility
    const p = user.personalization ?? {};
    document.getElementById('dash-interests').innerText = p.interests ?? user.interests ?? '';
    document.getElementById('dash-skills').innerText = p.skills ?? user.skills ?? '';
}

function resetSignupForms() {
    document.getElementById('reg-name').value = '';
    document.getElementById('reg-email').value = '';
    document.getElementById('reg-password').value = '';
    document.getElementById('reg-religion').value = '';
    document.getElementById('reg-community').value = '';
    document.getElementById('reg-interests').value = '';
    document.getElementById('reg-skills').value = '';
    document.getElementById('reg-age').value = '';
    prevSignupStep();
}

function logout() {
    switchView('view-public-login');
    document.getElementById('public-login-form').reset();
    document.getElementById('admin-login-form').reset();
}