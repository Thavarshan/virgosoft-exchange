import { ref } from 'vue';

const isDark = ref(false);

export function initializeTheme(): void {
    isDark.value = document.documentElement.classList.contains('dark');
}

export function useTheme() {
    function toggleTheme(): void {
        isDark.value = !isDark.value;
        document.documentElement.classList.toggle('dark', isDark.value);
        localStorage.theme = isDark.value ? 'dark' : 'light';
    }

    return { isDark, toggleTheme };
}
