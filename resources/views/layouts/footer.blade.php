<style>
  .stz-footer-main {
    background: #000;
    padding: 32px 0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
  }
  .stz-footer-main .stz-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    gap: 32px;
    flex-wrap: wrap;
  }
  .stz-footer-links {
    display: grid;
    grid-template-columns: 1fr 1fr;
    gap: 8px 48px;
  }
  .stz-footer-links a {
    color: #6c757d;
    font-size: 13px;
    text-decoration: none;
    transition: color .15s;
  }
  .stz-footer-links a:hover { color: #fff; }
  .stz-footer-cta {
    text-align: right;
    text-decoration: none;
    display: block;
    flex-shrink: 0;
  }
  .stz-footer-cta-eyebrow {
    font-size: 11px;
    color: #FF3131;
    letter-spacing: 2px;
    text-transform: uppercase;
    margin-bottom: 8px;
    display: flex;
    align-items: center;
    justify-content: flex-end;
    gap: 10px;
  }
  .stz-footer-cta-eyebrow::after {
    content: '';
    display: inline-block;
    width: 24px;
    height: 1px;
    background: #FF3131;
    flex-shrink: 0;
  }
  .stz-footer-cta-title {
    font-size: clamp(16px, 2vw, 22px);
    font-weight: 600;
    text-transform: uppercase;
    color: #fff;
    letter-spacing: 0.5px;
    margin: 0;
    transition: color .15s;
  }
  .stz-footer-cta:hover .stz-footer-cta-title { color: #FF3131; }
  .stz-footer-copyright {
    background: #000;
    border-top: 1px solid #1a1a1a;
    padding: 14px 0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
  }
  .stz-footer-copyright p {
    font-size: 12px;
    color: #fff;
    text-align: center;
    margin: 0;
  }
  .stz-footer-copyright span { color: #FF3131; }
</style>

<footer class="mt-auto bg-black text-light stz-footer-main">
  <div class="container">
    <div class="stz-inner">

      <nav class="stz-footer-links">
        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isEmployee())
                <a href="/regulamin_partnera">Regulamin partnera</a>
            @endif
        @endauth
        <a href="/regulamin_serwisu_dla_uzytkownikow">Regulamin</a>
        <a href="/mapa-strzelnic">Mapa strzelnic</a>
        <a href="/polityka_prywatnosci">Polityka prywatności</a>
        @auth
            @if (auth()->user()->isAdmin() || auth()->user()->isEmployee())
                <a href="/dpa">DPA</a>
            @endif
        @endauth
        <a href="/kontakt">Kontakt</a>
      </nav>

      <a href="/oferta" class="stz-footer-cta">
        <div class="stz-footer-cta-eyebrow">Zarządzasz strzelnicą?</div>
        <h2 class="stz-footer-cta-title">Sprawdź naszą ofertę! →</h2>
      </a>

    </div>
  </div>
</footer>

<div class="stz-footer-copyright">
  <div class="container">
    <p>© 2026 strzelnice<span>.org</span> — Wszelkie prawa zastrzeżone</p>
  </div>
</div>



<script>
document.addEventListener("DOMContentLoaded", function () {
    document.querySelectorAll('[data-bs-toggle="tooltip"]').forEach(function (el) {
        new bootstrap.Tooltip(el, {
            html: true
        });
    });
});
</script>
