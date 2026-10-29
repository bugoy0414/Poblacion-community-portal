import './bootstrap';

const root = document.documentElement;
const themeToggle = document.querySelector('.theme-toggle');
const storedTheme = localStorage.getItem('community-theme');

if (storedTheme === 'dark') root.dataset.theme = 'dark';

themeToggle?.addEventListener('click', () => {
    const dark = root.dataset.theme !== 'dark';
    root.dataset.theme = dark ? 'dark' : 'light';
    localStorage.setItem('community-theme', dark ? 'dark' : 'light');
});

const menuToggle = document.querySelector('.menu-toggle');
const mainNav = document.querySelector('.main-nav');
menuToggle?.addEventListener('click', () => {
    const isOpen = mainNav?.classList.toggle('open') ?? false;
    menuToggle.setAttribute('aria-expanded', String(isOpen));
});

document.querySelectorAll('[data-status-step]').forEach((button) => {
    button.addEventListener('click', () => {
        const shouldOpen = button.getAttribute('aria-expanded') !== 'true';

        document.querySelectorAll('[data-status-step]').forEach((stepButton) => {
            const description = document.getElementById(stepButton.getAttribute('aria-controls'));
            const isOpen = stepButton === button && shouldOpen;

            stepButton.setAttribute('aria-expanded', String(isOpen));
            description.hidden = !isOpen;
            stepButton.closest('.status-step').classList.toggle('is-open', isOpen);
        });
    });
});

document.querySelector('[data-print-report]')?.addEventListener('click', () => window.print());

document.querySelectorAll('[data-auth-tab]').forEach((tab) => {
    tab.addEventListener('click', () => {
        const selected = tab.dataset.authTab;
        document.querySelectorAll('[data-auth-tab]').forEach((item) => item.classList.toggle('active', item === tab));
        document.querySelectorAll('[data-auth-form]').forEach((form) => form.classList.toggle('hidden', form.dataset.authForm !== selected));
    });
});

const locationButton = document.querySelector('#location-button');
locationButton?.addEventListener('click', () => {
    const setButtonMessage = (message) => {
        locationButton.textContent = message;
        window.setTimeout(() => { locationButton.textContent = '◎ Use my location'; }, 3500);
    };

    if (!navigator.geolocation) {
        setButtonMessage('Location is not supported');
        return;
    }

    locationButton.disabled = true;
    locationButton.textContent = 'Finding location…';
    navigator.geolocation.getCurrentPosition((position) => {
        const { latitude, longitude } = position.coords;
        document.querySelector('#latitude').value = latitude.toFixed(7);
        document.querySelector('#longitude').value = longitude.toFixed(7);
        const map = document.querySelector('#map-preview');
        map.href = `https://maps.google.com/?q=${latitude},${longitude}`;
        map.classList.remove('hidden');
        document.querySelector('#address').value ||= `${latitude.toFixed(5)}, ${longitude.toFixed(5)}`;
        locationButton.disabled = false;
        setButtonMessage('Location added ✓');
    }, () => {
        locationButton.disabled = false;
        setButtonMessage('Could not get location');
    }, { enableHighAccuracy: false, timeout: 10000, maximumAge: 60000 });
});

const reportForm = document.querySelector('#report-form');
const emergencyDialog = document.querySelector('#emergency-dialog');
let emergencyConfirmed = false;

const isPotentialEmergency = () => {
    const category = document.querySelector('#category')?.value;
    const text = `${document.querySelector('#title')?.value ?? ''} ${document.querySelector('#description')?.value ?? ''}`.toLocaleLowerCase();
    const keywords = window.communityPortal?.urgentKeywords ?? [];
    return category === 'Fire/Emergency' || keywords.some((keyword) => text.includes(String(keyword).toLocaleLowerCase()));
};

reportForm?.addEventListener('submit', (event) => {
    if (emergencyConfirmed || !isPotentialEmergency()) return;
    event.preventDefault();
    if (!emergencyDialog?.open) emergencyDialog?.showModal();
});

document.querySelector('#emergency-cancel')?.addEventListener('click', () => emergencyDialog?.close());
document.querySelector('#emergency-submit')?.addEventListener('click', () => {
    emergencyConfirmed = true;
    document.querySelector('#confirm-emergency').value = '1';
    emergencyDialog?.close();
    reportForm?.requestSubmit();
});

if (reportForm?.dataset.emergencyPrompt === 'true' && emergencyDialog) {
    emergencyDialog.showModal();
}

document.querySelectorAll('[data-copy]').forEach((button) => {
    button.addEventListener('click', async () => {
        try {
            await navigator.clipboard.writeText(button.dataset.copy);
            button.textContent = 'Copied ✓';
        } catch {
            button.textContent = button.dataset.copy;
        }
    });
});

const idleDialog = document.querySelector('#idle-dialog');
if (idleDialog) {
    const warningAt = 29 * 60 * 1000;
    const logoutAt = 30 * 60 * 1000;
    let warningTimer;
    let logoutTimer;

    const resetTimers = () => {
        window.clearTimeout(warningTimer);
        window.clearTimeout(logoutTimer);
        idleDialog.hidden = true;
        warningTimer = window.setTimeout(() => { idleDialog.hidden = false; }, warningAt);
        logoutTimer = window.setTimeout(() => {
            const form = document.createElement('form');
            form.method = 'post';
            form.action = '/logout';
            const token = document.createElement('input');
            token.type = 'hidden';
            token.name = '_token';
            token.value = document.querySelector('meta[name="csrf-token"]').content;
            form.append(token);
            document.body.append(form);
            form.submit();
        }, logoutAt);
    };

    ['pointerdown', 'keydown', 'touchstart', 'scroll'].forEach((event) => {
        window.addEventListener(event, resetTimers, { passive: true });
    });
    document.querySelector('#stay-signed-in')?.addEventListener('click', resetTimers);
    resetTimers();
}

document.querySelectorAll('.toast').forEach((toast) => {
    window.setTimeout(() => {
        toast.style.opacity = '0';
        toast.style.transition = 'opacity .25s ease';
        window.setTimeout(() => toast.remove(), 300);
    }, 6500);
});
