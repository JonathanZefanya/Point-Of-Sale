import { onBeforeUnmount, onMounted } from 'vue'

/**
 * Shortcut keyboard layar kasir (CLAUDE.md §7.1 no. 5). Shortcut juga DITAMPILKAN di tombolnya
 * (mis. "Bayar F9"), bukan disembunyikan di halaman bantuan.
 *
 * @param {Record<string, (event: KeyboardEvent) => void>} handlers mis. { F9: pay, F4: hold }
 * @param {() => boolean} [enabled] nonaktifkan sementara (mis. saat modal terbuka)
 */
export function useShortcuts(handlers, enabled = () => true) {
  function onKeydown(event) {
    if (!enabled()) return
    const handler = handlers[event.key]
    if (!handler) return
    event.preventDefault() // F4/F9 punya fungsi bawaan browser
    handler(event)
  }

  onMounted(() => window.addEventListener('keydown', onKeydown))
  onBeforeUnmount(() => window.removeEventListener('keydown', onKeydown))
}
