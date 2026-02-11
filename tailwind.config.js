/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
  ],
  theme: {
    extend: {
      colors: {
        // Berdasarkan gambar Design System yang kamu kirim
        'rpg-dark': '#202020',      // Raisin Black (Primary 60%)
        'rpg-secondary': '#F7F7F7', // Cultured (Secondary 30%)
        'rpg-accent': '#009999',    // Viridian Green (Accent 10%)
      },
      fontFamily: {
        pixel: ['"Press Start 2P"', 'cursive'],
      },
    },
  },
  plugins: [],
}