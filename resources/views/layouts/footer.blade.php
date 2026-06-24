

<footer class="py-3 mt-auto bg-dark text-light">
    <div class="container">
        <div class="row align-items-center">

            <!-- Left: copyright -->
            <div class="text-center col-md-6 text-md-start small">
                © {{ date('Y') }} strzelnice<span class="text-danger bold">.org</span> — Wszelkie prawa zastrzeżone
            </div>

            <!-- Right: menu -->
            <div class="text-center col-md-6 text-md-end">
                <ul class="nav justify-content-center justify-content-md-end">
                    <li class="nav-item">
                        <a href="/" class="px-2 nav-link text-secondary">O nas</a>
                    </li>
                    <li class="nav-item">
                        <a href="/regulamin" class="px-2 nav-link text-secondary">Mapa</a>
                    </li>
                    <li class="nav-item">
                        <a href="/polityka-prywatnosci" class="px-2 nav-link text-secondary">Strefa strzelnic</a>
                    </li>
                    <li class="nav-item">
                        <a href="/kontakt" class="px-2 nav-link text-secondary">Kontakt</a>
                    </li>
                </ul>
            </div>

        </div>
    </div>
</footer>

<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, {
            html: true
        });
    });
});
</script>
