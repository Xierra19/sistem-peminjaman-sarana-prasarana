import { ref, watchEffect } from 'vue';

/**
 * Composable untuk mengelola status mode gelap (dark mode) aplikasi.
 * Menyimpan preferensi pengguna di localStorage dan mendeteksi preferensi sistem operasi.
 */
export function useDarkMode() {
    // Cek preferensi yang tersimpan di localStorage
    const storedTheme = localStorage.getItem('theme');
    
    // Inisialisasi status dark mode
    let initialDark = false;
    if (storedTheme) {
        initialDark = storedTheme === 'dark';
    } else {
        // Jika tidak ada preferensi tersimpan, gunakan preferensi sistem
        initialDark = window.matchMedia('(prefers-color-scheme: dark)').matches;
    }

    const isDark = ref(initialDark);

    // Pantau perubahan status dark mode untuk update class pada elemen <html> dan simpan preferensi
    watchEffect(() => {
        const htmlElement = document.documentElement;
        if (isDark.value) {
            htmlElement.classList.add('dark');
            localStorage.setItem('theme', 'dark');
        } else {
            htmlElement.classList.remove('dark');
            localStorage.setItem('theme', 'light');
        }
    });

    /**
     * Fungsi untuk mengalihkan antara mode gelap dan terang
     */
    const toggleDarkMode = () => {
        isDark.value = !isDark.value;
    };

    return {
        isDark,
        toggleDarkMode,
    };
}