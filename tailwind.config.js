export default {
    content: [
        './vendor/laravel/framework/src/Illuminate/Pagination/resources/views/*.blade.php',
        './storage/framework/views/*.php',
        './resources/views/**/*.blade.php',
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Tambahkan kembali font pixel kamu di sini
                pixel: ['"Press Start 2P"', 'cursive'],
                vt: ['"VT323"', 'monospace'],
            },
            colors: {
                // Tambahkan warna neon RPG kamu jika ada yang hilang
                cyan: {
                    500: '#00ffff',
                    600: '#00cccc',
                },
            },
        },
    },
    plugins: [require('@tailwindcss/forms')],
};