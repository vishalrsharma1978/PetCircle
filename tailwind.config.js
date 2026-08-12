/** @type {import('tailwindcss').Config} */
module.exports = {
  content: [
    "./**/*.{html,js,php}",
    "!./node_modules/**/*"
  ],
  darkMode: "class",
  theme: {
    extend: {
      colors: {
        brand: {
          50: "var(--brand-50, #fff5f5)",
          100: "var(--brand-100, #ffd45a)",
          200: "var(--brand-200, #ffa95a)",
          300: "var(--brand-300, #ff8b5a)",
          400: "var(--brand-400, #ff5a5a)",
          500: "var(--brand-500, #e04848)",
          900: "var(--brand-900, #7a2222)",
        },
      },
      fontFamily: {
        sans: ["Inter", "sans-serif"],
        serif: ["Poppins", "sans-serif"],
        heading: ["Poppins", "sans-serif"]
      },
    },
  },
  plugins: [],
}
