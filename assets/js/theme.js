/**
 * Управление темной/светлой темой
 */

(function() {
    'use strict';
    
    const THEME_KEY = 'app_theme';
    const THEME_DARK = 'dark';
    const THEME_LIGHT = 'light';
    
    /**
     * Получить текущую тему
     */
    function getTheme() {
        return localStorage.getItem(THEME_KEY) || THEME_LIGHT;
    }
    
    /**
     * Установить тему
     */
    function setTheme(theme) {
        localStorage.setItem(THEME_KEY, theme);
        document.documentElement.setAttribute('data-theme', theme);
        updateToggleIcon(theme);
    }
    
    /**
     * Переключить тему
     */
    function toggleTheme() {
        const currentTheme = getTheme();
        const newTheme = currentTheme === THEME_DARK ? THEME_LIGHT : THEME_DARK;
        setTheme(newTheme);
    }
    
    /**
     * Обновить иконку и текст тоггла
     */
    function updateToggleIcon(theme) {
        const toggleIcon = document.getElementById('theme-toggle-icon');
        const toggleText = document.getElementById('theme-toggle-text');
        
        if (toggleIcon) {
            if (theme === THEME_DARK) {
                toggleIcon.className = 'bi bi-sun-fill';
            } else {
                toggleIcon.className = 'bi bi-moon-fill';
            }
        }
        
        if (toggleText) {
            toggleText.textContent = theme === THEME_DARK ? 'Светлая тема' : 'Темная тема';
        }
    }
    
    /**
     * Инициализация темы при загрузке страницы
     */
    function initTheme() {
        const theme = getTheme();
        setTheme(theme);
    }
    
    // Инициализация при загрузке DOM
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', initTheme);
    } else {
        initTheme();
    }
    
    // Экспорт функции переключения для использования в HTML
    window.toggleTheme = toggleTheme;
})();
