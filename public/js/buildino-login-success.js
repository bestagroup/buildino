(() => {
    'use strict';
    const confirmation = document.querySelector('[data-login-success]');
    if (!confirmation) return;
    const remove = () => confirmation.remove();
    confirmation.addEventListener('animationend', (event) => {
        if (event.target === confirmation) remove();
    });
    // A cached dashboard must not replay the login confirmation when going back.
    window.addEventListener('pagehide', remove, { once: true });
    window.setTimeout(remove, 1600);
})();
