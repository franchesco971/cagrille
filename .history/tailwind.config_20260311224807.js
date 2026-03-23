/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    './themes/CagrilleTheme/templates/**/*.html.twig',
    './templates/**/*.html.twig',
    './assets/shop/**/*.js',
  ],
  theme: {
    extend: {
      colors: {
        coal: {
          600: '#3d3533',
          700: '#2a2420',
          800: '#1a1410',
          900: '#0d0a07',
        },
        ash: {
          100: '#f5f0eb',
          200: '#e8dfd6',
          300: '#d4c8bc',
          400: '#b8a89a',
          500: '#9a887a',
        },
        ember: {
          400: '#ff9940',
          500: '#ff7a1a',
          600: '#e05500',
        },
        flame: {
          500: '#ff4d00',
          600: '#cc3d00',
        },
        gold: {
          400: '#ffd700',
          500: '#f0b800',
        },
      },
      fontFamily: {
        display: ['"Bebas Neue"', 'sans-serif'],
        body: ['Inter', 'sans-serif'],
      },
      boxShadow: {
        fire: '0 0 20px rgba(255, 122, 26, 0.4)',
        ember: '0 0 30px rgba(255, 153, 64, 0.5)',
        coal: '0 4px 20px rgba(13, 10, 7, 0.6)',
      },
      animation: {
        flicker: 'flicker 2s ease-in-out infinite alternate',
      },
      keyframes: {
        flicker: {
          '0%, 100%': { opacity: 1, transform: 'scaleY(1)' },
          '50%': { opacity: 0.6, transform: 'scaleY(0.85)' },
        },
      },
    },
  },
  plugins: [],
};
