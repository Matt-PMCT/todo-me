/** @type {import('tailwindcss').Config} */
module.exports = {
  darkMode: 'class',
  content: [
    './templates/**/*.html.twig',
    './assets/**/*.js',
  ],
  safelist: [
    // Dynamic highlight classes used in quick-add.html.twig getHighlightClass()
    // Light mode
    'bg-blue-100', 'bg-teal-100', 'bg-gray-200', 'bg-yellow-100',
    'border-b-2', 'border-red-400',
    // Dark mode (applied via JS detection, not dark: prefix)
    'bg-blue-500/30', 'bg-teal-500/30', 'bg-gray-500/40', 'bg-yellow-500/30',
    'border-red-500',
    'rounded-sm',
  ],
  theme: {
    extend: {
      colors: {
        primary: {
          50: '#f0fdfa',
          100: '#ccfbf1',
          200: '#99f6e4',
          300: '#5eead4',
          400: '#2dd4bf',
          500: '#14b8a6',
          600: '#0d9488',
          700: '#0f766e',
          800: '#115e59',
          900: '#134e4a',
          950: '#042f2e',
        },
      },
    },
  },
  plugins: [],
}
