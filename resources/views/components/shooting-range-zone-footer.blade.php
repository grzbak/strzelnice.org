<!--
  Baner CTA "Zarządzasz strzelnicą?" — dla właścicieli strzelnic
  Do wklejenia na stronie głównej strzelnice.org.
  Wymaga Bootstrap 5 wczytanego w <head> strony.
  Styl zgodny z banerem "Jak to działa".
-->

<style>
  .htd-cta-section {
    background: #000;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
    padding: 28px 0 0;
    width: 100%;
  }
  .htd-cta-inner {
    display: flex;
    align-items: center;
    justify-content: space-between;
    flex-wrap: wrap;
    gap: 8px 24px;
  }
  .htd-cta-text { flex: 1; }
  .htd-cta-eyebrow {
    font-size: 11px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #FF3131;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 8px;
  }
  .htd-cta-eyebrow::before {
    content: '';
    width: 24px;
    height: 1px;
    background: #FF3131;
    flex-shrink: 0;
  }
  .htd-cta-title {
    font-size: clamp(18px, 2.5vw, 26px);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #fff;
    margin: 0;
    line-height: 1.15;
  }
  .htd-cta-arrow {
    color: #FF3131;
    font-size: 22px;
    text-decoration: none;
    transition: color .15s;
    padding-bottom: 4px;
    flex-shrink: 0;
  }
  .htd-cta-arrow:hover { color: #FF5C5C; }
  .htd-cta-line {
    display: block;
    height: 1px;
    background: #333333;
    width: 100%;
    margin-top: 28px;
  }
</style>


<section class="htd-cta-section"
        onclick="window.location='{{ url('/strefa-strzelnic') }}'"
         style="cursor:pointer;">
  <div class="container">
    <div class="mx-auto htd-cta-inner col-lg-4 col-12">
      <div class="htd-cta-text">
        <div class="htd-cta-eyebrow">Zarządzasz strzelnicą?</div>
        <h2 class="htd-cta-title">Sprawdź naszą ofertę!</h2>
      </div>
      <a href="/strefa-strzelnic" class="htd-cta-arrow">→</a>
    </div>
  </div>
  <span class="htd-cta-line"></span>
</section>
