import { computed, onBeforeUnmount, ref } from 'vue'

/** Hitung mundur dalam detik, mis. untuk sisa waktu kunci PIN. */
export function useCountdown() {
  const remaining = ref(0)
  let timer = null

  function stop() {
    clearInterval(timer)
    timer = null
  }

  function start(seconds) {
    stop()
    const endsAt = Date.now() + seconds * 1000
    const tick = () => {
      remaining.value = Math.max(0, Math.ceil((endsAt - Date.now()) / 1000))
      if (remaining.value === 0) stop()
    }
    tick()
    timer = setInterval(tick, 1000)
  }

  /** "4:05" */
  const label = computed(() => {
    const m = Math.floor(remaining.value / 60)
    const s = String(remaining.value % 60).padStart(2, '0')
    return `${m}:${s}`
  })

  const active = computed(() => remaining.value > 0)

  onBeforeUnmount(stop)

  return { remaining, label, active, start, stop }
}
