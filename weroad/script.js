// ============================================
// WeRoad — shared interactions
// ============================================

document.addEventListener('DOMContentLoaded', () => {

  // Mobile nav toggle
  const navToggle = document.querySelector('.nav-toggle');
  const mainNav = document.querySelector('.main-nav');
  if (navToggle && mainNav) {
    navToggle.addEventListener('click', () => {
      const open = mainNav.classList.toggle('nav-open');
      navToggle.setAttribute('aria-expanded', open);
      mainNav.style.cssText = open
        ? 'display:flex;flex-direction:column;position:absolute;top:76px;left:0;right:0;background:#fff;padding:20px 24px;border-bottom:1px solid #E9E7E2;gap:16px;'
        : '';
    });
  }

  // Guest counters (booking page)
  document.querySelectorAll('.counter').forEach(counter => {
    const numEl = counter.querySelector('.num');
    const [minus, plus] = counter.querySelectorAll('button');
    let val = parseInt(numEl.textContent, 10) || 0;
    const sync = () => {
      numEl.textContent = val;
      minus.disabled = val <= 0;
      document.dispatchEvent(new CustomEvent('guests-changed'));
    };
    plus.addEventListener('click', () => { val++; sync(); });
    minus.addEventListener('click', () => { if (val > 0) { val--; sync(); } });
    sync();
  });

  // Enable "Continue" button on booking page once at least one traveller selected
  const continueBtn = document.querySelector('#booking-continue');
  if (continueBtn) {
    const checkGuests = () => {
      const total = Array.from(document.querySelectorAll('.counter .num'))
        .reduce((sum, el) => sum + (parseInt(el.textContent, 10) || 0), 0);
      continueBtn.disabled = total === 0;
    };
    document.addEventListener('guests-changed', checkGuests);
    checkGuests();
  }

  // Loved-adventures tab switching (homepage) — purely visual
  document.querySelectorAll('.loved-tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.loved-tabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
    });
  });

  // FAQ tab switching (trip detail page)
  document.querySelectorAll('.faq-tabs button').forEach(btn => {
    btn.addEventListener('click', () => {
      document.querySelectorAll('.faq-tabs button').forEach(b => b.classList.remove('active'));
      btn.classList.add('active');
      document.querySelectorAll('.faq-group').forEach(g => g.style.display = 'none');
      const target = document.getElementById(btn.dataset.target);
      if (target) target.style.display = 'block';
    });
  });

  // Favourite / heart toggle
  document.querySelectorAll('.fav, .icon-btn[data-fav]').forEach(el => {
    el.addEventListener('click', () => el.classList.toggle('is-fav'));
  });

});
