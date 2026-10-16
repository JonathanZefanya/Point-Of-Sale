import { defineStore } from 'pinia'
import { ref } from 'vue'

let nextId = 1

export const useToastStore = defineStore('toast', () => {
  const items = ref([])
  const timers = new Map()

  /**
   * @param {object} options
   * @param {string} options.message
   * @param {'info'|'success'|'error'} [options.type]
   * @param {{label: string, onClick: Function}} [options.action] mis. { label: 'Batalkan', onClick: undo }
   * @param {number} [options.duration] ms; toast dengan aksi diberi waktu lebih lama
   */
  function show({ message, type = 'info', action = null, duration }) {
    const id = nextId++
    items.value.push({ id, message, type, action })
    const ms = duration ?? (action ? 6000 : type === 'error' ? 6000 : 3500)
    timers.set(id, setTimeout(() => dismiss(id), ms))
    return id
  }

  function dismiss(id) {
    clearTimeout(timers.get(id))
    timers.delete(id)
    items.value = items.value.filter((t) => t.id !== id)
  }

  function runAction(toast) {
    toast.action?.onClick()
    dismiss(toast.id)
  }

  const success = (message, opts = {}) => show({ ...opts, message, type: 'success' })
  const error = (message, opts = {}) => show({ ...opts, message, type: 'error' })
  const info = (message, opts = {}) => show({ ...opts, message, type: 'info' })

  return { items, show, dismiss, runAction, success, error, info }
})
