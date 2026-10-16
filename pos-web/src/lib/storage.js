/**
 * Pembungkus localStorage yang aman: bisa diblokir (mode privat, kebijakan browser),
 * jadi setiap akses dibungkus try/catch dan aplikasi tetap jalan tanpa penyimpanan.
 */
export function readStorage(key) {
  try {
    return localStorage.getItem(key)
  } catch {
    return null
  }
}

export function writeStorage(key, value) {
  try {
    if (value === null || value === undefined) localStorage.removeItem(key)
    else localStorage.setItem(key, value)
  } catch {
    // diabaikan: nilai tetap hidup di memori selama tab terbuka
  }
}
