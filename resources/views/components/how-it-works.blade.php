<!--
  Baner "Jak to działa" — dla klienta końcowego
  Do wklejenia na stronie głównej strzelnice.org pod wynikami wyszukiwania.
  Wymaga Bootstrap 5 (CSS) wczytanego w <head> strony.
  Wygląd 1:1 zgodny z sekcją "Jak to działa" na podstronie Strefa Strzelnic.
-->

<style>
  .htd-section {
    background: #000;
    color: #ECEDEE;
    padding: 64px 0;
    font-family: system-ui, -apple-system, "Segoe UI", Roboto, "Helvetica Neue", Arial, "Noto Sans", "Liberation Sans", sans-serif;
  }
  .htd-eyebrow {
    font-size: 11px;
    letter-spacing: 3px;
    text-transform: uppercase;
    color: #FF3131;
    font-weight: 600;
    display: flex;
    align-items: center;
    gap: 10px;
    margin-bottom: 16px;
  }
  .htd-eyebrow::before {
    content: '';
    width: 24px;
    height: 1px;
    background: #FF3131;
    flex-shrink: 0;
  }
  .htd-section h2 {
    font-size: clamp(22px, 3vw, 32px);
    font-weight: 600;
    text-transform: uppercase;
    letter-spacing: 0.5px;
    color: #fff;
    margin-bottom: 40px;
    line-height: 1.15;
  }
  .htd-steps {
    display: grid;
    grid-template-columns: repeat(3, 1fr);
    gap: 1px;
    background: #2B3036;
    border: 1px solid #2B3036;
  }
  @media (max-width: 768px) {
    .htd-steps { grid-template-columns: 1fr; }
  }
  .htd-step {
    background: #000;
    padding: 40px 34px;
  }
  .htd-step-index {
    font-size: 13px;
    color: #FF3131;
    letter-spacing: 1px;
    margin-bottom: 22px;
  }
  .htd-step h3 {
    font-size: 19px;
    font-weight: 600;
    color: #ECEDEE;
    margin-bottom: 12px;
    text-transform: none;
    letter-spacing: 0;
  }
  .htd-step p {
    font-size: 14.5px;
    color: #8B9097;
    margin: 0;
    line-height: 1.6;
  }
</style>

<section class="htd-section">
  <div class="container">
    <div class="htd-eyebrow">Jak to działa</div>
    <h2>Zarezerwuj stanowisko w 3 prostych krokach</h2>
    <div class="htd-steps">

      <div class="htd-step">
        <div class="htd-step-index">01 / Sprawdzasz</div>
        <h3>Znajdź swoją strzelnicę</h3>
        <p>Wybierz miasto, województwo lub konkretną strzelnicę i sprawdź wolne terminy w czasie rzeczywistym.</p>
      </div>

      <div class="htd-step">
        <div class="htd-step-index">02 / Klikasz</div>
        <h3>Wybierz termin i stanowisko</h3>
        <p>Zaloguj się, wybierz stanowisko i termin. Kliknij zarezerwuj.</p>
      </div>

      <div class="htd-step">
        <div class="htd-step-index">03 / Strzelasz</div>
        <h3>Przychodź i strzelaj</h3>
        <p>Gotowe. Przychodź o wybranej godzinie — stanowisko czeka na Ciebie.</p>
      </div>

    </div>
  </div>
</section>
