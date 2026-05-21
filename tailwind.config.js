/** @type {import('tailwindcss').Config} */
export default {
  content: [
    "./resources/**/*.blade.php",
    "./resources/**/*.js",
    "./resources/**/*.vue",
    "./resources/forum/livewire-tailwind/**/*.blade.php",
    "./vendor/riari/laravel-forum/src/resources/views/**/*.blade.php"
  ],
  theme: {
    extend: {},
  },
  plugins: [],
}
