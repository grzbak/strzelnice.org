{{-- COOKIE BUTTON (lewy dolny róg) --}}
<div id="cookie-fab" style="
    position:fixed;
    bottom:20px;
    left:20px;
    z-index:99999;
    cursor:pointer;
">
    <!--img src="/images/cookie.png" width="60" alt="Cookies" onclick="openCookieModal()"-->
     <span onclick="openCookieModal()"
        title="Zarządzaj plikami cookie"
        aria-label="Cookies"
    >
        <i class="text-white bi bi-link-45deg display-6" aria-hidden="true"></i>
     </span>
</div>

{{-- MODAL --}}
<div class="modal fade" id="cookieModal" tabindex="-1">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content">

            <div class="modal-header">
                <h5 class="modal-title">Ustawienia cookies 🍪</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal"></button>
            </div>

            <div class="modal-body">
                <p>
                    Używamy cookies w celach funkcjonalnych, analitycznych i marketingowych.
                </p>

                <hr>

                <div>
                    <label>
                        <input type="checkbox" checked disabled> Niezbędne (zawsze aktywne)
                    </label>
                </div>

                <div>
                    <label>
                        <input type="checkbox" id="analytics"> Analityczne
                    </label>
                </div>

                <div>
                    <label>
                        <input type="checkbox" id="marketing"> Marketingowe
                    </label>
                </div>
            </div>

            <div class="modal-footer">
                <button class="btn btn-success" onclick="acceptAll()">Akceptuj wszystko</button>
                <button class="btn btn-danger" onclick="rejectAll()">Odrzuć</button>
                <button class="btn btn-primary" onclick="saveCustom()">Zapisz wybór</button>
            </div>

        </div>
    </div>
</div>

{{-- GOOGLE CONSENT MODE V2 --}}
<script>
window.dataLayer = window.dataLayer || [];
function gtag(){dataLayer.push(arguments);}

/**
 * DEFAULT (brak zgody)
 */
gtag('consent', 'default', {
    ad_storage: 'denied',
    analytics_storage: 'denied',
    ad_user_data: 'denied',
    ad_personalization: 'denied',
    wait_for_update: 500
});
</script>

<script>
function setCookie(name, value, days = 365) {
    const maxAge = days * 24 * 60 * 60;
    document.cookie = name + "=" + encodeURIComponent(value) + "; path=/; max-age=" + maxAge;
}

function getCookie(name) {
    return document.cookie.split('; ').find(row => row.startsWith(name+'='))?.split('=')[1];
}

function openCookieModal() {
    var modal = new bootstrap.Modal(document.getElementById('cookieModal'));
    modal.show();
}

/**
 * MAPOWANIE DO GOOGLE CONSENT MODE V2
 */
function updateConsent(settings) {
    gtag('consent', 'update', {
        ad_storage: settings.marketing ? 'granted' : 'denied',
        analytics_storage: settings.analytics ? 'granted' : 'denied',
        ad_user_data: settings.marketing ? 'granted' : 'denied',
        ad_personalization: settings.marketing ? 'granted' : 'denied'
    });
}

function saveSettings(settings) {
    setCookie('cookie_settings', JSON.stringify(settings));
    updateConsent(settings);
}

function acceptAll() {
    const settings = {
        necessary: true,
        analytics: true,
        marketing: true
    };
    saveSettings(settings);
    closeModal();
}

function rejectAll() {
    const settings = {
        necessary: true,
        analytics: false,
        marketing: false
    };
    saveSettings(settings);
    closeModal();
}

function saveCustom() {
    const settings = {
        necessary: true,
        analytics: document.getElementById('analytics').checked,
        marketing: document.getElementById('marketing').checked
    };
    saveSettings(settings);
    closeModal();
}

function closeModal() {
    const modalEl = document.getElementById('cookieModal');
    const modal = bootstrap.Modal.getInstance(modalEl);
    modal.hide();
}

/**
 * INIT
 */
document.addEventListener('DOMContentLoaded', function () {
    const saved = getCookie('cookie_settings');

    if (saved) {
        const settings = JSON.parse(decodeURIComponent(saved));
        updateConsent(settings);

        // ustaw checkboxy
        document.getElementById('analytics').checked = settings.analytics;
        document.getElementById('marketing').checked = settings.marketing;
    } else {
        openCookieModal(); // pokaż przy pierwszym wejściu
    }
});
</script>
