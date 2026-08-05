// ============================================================
// GAIA TOURS & TRAVEL — Shared platform interactions
// (mobile menu, cross-sell, etc.)
// ============================================================
(function () {
    'use strict';

    document.addEventListener('DOMContentLoaded', function () {
        var burger = document.querySelector('.gaia-burger');
        var menu = document.querySelector('.gaia-mobile-menu');
        var backdrop = document.querySelector('.gaia-mobile-backdrop');
        var closeBtn = document.querySelector('.gaia-mobile-close');

        function openMenu() {
            if (menu) menu.classList.add('open');
            if (backdrop) backdrop.classList.add('open');
            if (burger) burger.setAttribute('aria-expanded', 'true');
            document.body.classList.add('gaia-no-scroll');
        }
        function closeMenu() {
            if (menu) menu.classList.remove('open');
            if (backdrop) backdrop.classList.remove('open');
            if (burger) burger.setAttribute('aria-expanded', 'false');
            document.body.classList.remove('gaia-no-scroll');
        }

        if (burger) burger.addEventListener('click', openMenu);
        if (closeBtn) closeBtn.addEventListener('click', closeMenu);
        if (backdrop) backdrop.addEventListener('click', closeMenu);

        // Close mobile menu when a link is tapped
        if (menu) {
            menu.querySelectorAll('a').forEach(function (a) {
                a.addEventListener('click', closeMenu);
            });
        }
    });
})();
