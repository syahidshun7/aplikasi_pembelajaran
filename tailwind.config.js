// tailwind.config.js
export default {
    content: [
        // ... (biarkan default breeze)
        './resources/js/**/*.vue',
    ],
    theme: {
        extend: {
            fontFamily: {
                // Tambahkan ini agar class 'font-pixel' bisa dipakai
                pixel: ['"Press Start 2P"', 'cursive'],
            },
        },
    },
    // ...
};