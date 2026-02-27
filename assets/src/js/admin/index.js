(() => {
  const root = document.querySelector('.be-admin-screen');
  if (!root) {
    return;
  }

  const navLinks = root.querySelectorAll('.be-admin-nav-link');

  navLinks.forEach((link) => {
    if (!(link instanceof HTMLElement)) {
      return;
    }

    if (link.classList.contains('is-active')) {
      link.setAttribute('aria-current', 'page');

      const icon = link.querySelector('.be-admin-nav-icon');
      if (icon instanceof HTMLElement) {
        icon.classList.add('is-filled');
      }
    }

    link.addEventListener('focus', () => {
      link.classList.add('is-focus');
    });

    link.addEventListener('blur', () => {
      link.classList.remove('is-focus');
    });
  });
})();
