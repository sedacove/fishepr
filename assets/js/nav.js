(function () {
    const submenuWrapper = document.getElementById('navbarSubmenuWrapper');
    if (!submenuWrapper) {
        return;
    }

    const DESKTOP_WIDTH = 992;
    let activeSubmenu = null;

    const panels = Array.from(submenuWrapper.querySelectorAll('[data-submenu-panel]'));
    const triggers = Array.from(document.querySelectorAll('[data-submenu-trigger]'));
    const dropdownToggles = Array.from(submenuWrapper.querySelectorAll('[data-submenu-dropdown-toggle]'));

    function showSubmenu(key) {
        if (!key) {
            return;
        }

        activeSubmenu = key;
        submenuWrapper.classList.add('active');
        panels.forEach(panel => {
            panel.classList.toggle('active', panel.dataset.submenuPanel === key);
        });
    }

    function hideSubmenu() {
        activeSubmenu = null;
        submenuWrapper.classList.remove('active');
        panels.forEach(panel => panel.classList.remove('active'));
        closeAllDropdowns();
    }

    function closeAllDropdowns() {
        dropdownToggles.forEach(btn => btn.classList.remove('open'));
        submenuWrapper.querySelectorAll('[data-submenu-dropdown]').forEach(dropdown => {
            dropdown.classList.remove('open');
        });
    }

    function isDesktop() {
        return window.innerWidth >= DESKTOP_WIDTH;
    }

    triggers.forEach(trigger => {
        trigger.addEventListener('click', event => {
            if (!isDesktop()) {
                return;
            }
            event.preventDefault();
            const key = trigger.dataset.submenuTrigger;
            if (activeSubmenu === key) {
                hideSubmenu();
            } else {
                showSubmenu(key);
            }
        });
    });

    dropdownToggles.forEach(toggle => {
        toggle.addEventListener('click', event => {
            event.preventDefault();
            event.stopPropagation();
            const key = toggle.dataset.submenuDropdownToggle;
            const dropdown = submenuWrapper.querySelector(`[data-submenu-dropdown="${key}"]`);
            if (!dropdown) {
                return;
            }
            const willOpen = !dropdown.classList.contains('open');
            closeAllDropdowns();
            if (willOpen) {
                dropdown.classList.add('open');
                toggle.classList.add('open');
            }
        });
    });

    document.addEventListener('click', event => {
        if (!submenuWrapper.classList.contains('active')) {
            return;
        }
        if (event.target.closest('[data-submenu-trigger]') || submenuWrapper.contains(event.target)) {
            return;
        }
        hideSubmenu();
    });

    window.addEventListener('resize', () => {
        if (!isDesktop()) {
            hideSubmenu();
        }
    });

    document.addEventListener('keydown', event => {
        if (event.key === 'Escape') {
            hideSubmenu();
        }
    });
})();

