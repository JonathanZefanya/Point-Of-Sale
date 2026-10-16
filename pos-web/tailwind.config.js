// Design token POS — lihat CLAUDE.md §7.3.
// Palet & radius sengaja TIDAK di-extend: hanya token ini yang tersedia,
// sehingga warna/rounded di luar sistem tidak bisa dipakai.
export default {
  content: ['./index.html', './src/**/*.{vue,js}'],
  theme: {
    colors: {
      transparent: 'transparent',
      current: 'currentColor',
      white: '#FFFFFF',
      bg: '#F7F7F5',
      surface: '#FFFFFF',
      'surface-muted': '#F0F0EC', // baris hover, header tabel
      border: '#E4E4E0',
      'border-strong': '#CFCFC9',
      text: '#1C1C1A',
      muted: '#6B6B66',
      primary: '#1F5F4A',
      'primary-hover': '#174A3A',
      'primary-soft': '#E7F0EC', // latar item terpilih
      danger: '#B42318',
      'danger-soft': '#FEF3F2',
      warning: '#B54708',
      'warning-soft': '#FFFAEB',
      success: '#067647',
      'success-soft': '#ECFDF3',
    },
    borderRadius: {
      none: '0',
      sm: '4px',
      DEFAULT: '6px',
      lg: '8px',
      full: '9999px', // hanya untuk titik status & badge bulat
    },
    fontFamily: {
      sans: ['"IBM Plex Sans"', 'system-ui', 'sans-serif'],
      mono: ['"IBM Plex Mono"', 'monospace'],
    },
    extend: {
      boxShadow: {
        // hanya untuk elemen mengambang: modal, dropdown, toast
        float: '0 8px 24px rgba(28, 28, 26, 0.12), 0 2px 6px rgba(28, 28, 26, 0.08)',
      },
      minHeight: { touch: '44px' },
      minWidth: { touch: '44px' },
    },
  },
}
