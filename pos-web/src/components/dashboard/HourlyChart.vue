<script setup>
import { computed, ref } from 'vue'
import { formatNumber, formatRupiah } from '@/lib/format'

/**
 * Omzet per jam: hari ini = batang (warna aksen), pembanding = garis tick abu-abu.
 * Dua seri dibedakan BENTUK mark + legenda + tooltip, bukan warna saja (palet divalidasi:
 * #1F5F4A vs #909089 lolos CVD, normal-vision ΔE 22, kontras ≥ 3:1).
 */
const props = defineProps({
  current: { type: Array, required: true }, // [{ hour, revenue, transactions }] × 24
  previous: { type: Array, required: true },
  currentLabel: { type: String, default: 'Hari ini' },
  previousLabel: { type: String, default: 'Kemarin' },
})

const W = 720
const H = 220
const PAD = { left: 56, right: 8, top: 12, bottom: 26 }
const plotW = W - PAD.left - PAD.right
const plotH = H - PAD.top - PAD.bottom
const slot = plotW / 24
const barW = Math.min(18, slot - 6)
const COMPARE = '#909089'

const compact = new Intl.NumberFormat('id-ID', { notation: 'compact', maximumFractionDigits: 1 })

const max = computed(() => {
  const top = Math.max(...props.current.map((d) => d.revenue), ...props.previous.map((d) => d.revenue), 0)
  if (top === 0) return 100000
  const step = 10 ** Math.floor(Math.log10(top))
  return Math.ceil(top / step) * step
})
const y = (v) => PAD.top + plotH - (v / max.value) * plotH
const x = (hour) => PAD.left + hour * slot + slot / 2
const ticks = computed(() => [0, 0.25, 0.5, 0.75, 1].map((f) => f * max.value))

/** Batang dengan ujung atas membulat 4px, dasar persegi di baseline. */
function barPath(hour, value) {
  if (value <= 0) return ''
  const left = x(hour) - barW / 2
  const top = y(value)
  const bottom = PAD.top + plotH
  const r = Math.min(4, bottom - top, barW / 2)
  return `M${left},${bottom} V${top + r} Q${left},${top} ${left + r},${top} H${left + barW - r} Q${left + barW},${top} ${left + barW},${top + r} V${bottom} Z`
}

const hover = ref(null)
const showTable = ref(false)
const tooltipLeft = computed(() => (hover.value === null ? 0 : (x(hover.value) / W) * 100))
const hourLabel = (h) => `${String(h).padStart(2, '0')}.00`
</script>

<template>
  <div>
    <div class="mb-2 flex flex-wrap items-center justify-between gap-3">
      <div class="flex items-center gap-4 text-sm" aria-hidden="true">
        <span class="inline-flex items-center gap-1.5"><span class="h-3 w-2.5 rounded-t-sm bg-primary" />{{ currentLabel }}</span>
        <span class="inline-flex items-center gap-1.5"><span class="h-0.5 w-3.5" :style="{ background: COMPARE }" />{{ previousLabel }}</span>
      </div>
      <button type="button" class="text-sm text-muted underline-offset-2 hover:text-text hover:underline" @click="showTable = !showTable">
        {{ showTable ? 'Lihat sebagai grafik' : 'Lihat sebagai tabel' }}
      </button>
    </div>

    <div v-if="!showTable" class="relative">
      <svg :viewBox="`0 0 ${W} ${H}`" class="h-auto w-full" role="img" :aria-label="`Grafik omzet per jam, ${currentLabel} dibanding ${previousLabel}`" @mouseleave="hover = null">
        <g>
          <template v-for="t in ticks" :key="t">
            <line :x1="PAD.left" :x2="W - PAD.right" :y1="y(t)" :y2="y(t)" stroke="#E4E4E0" stroke-width="1" />
            <text :x="PAD.left - 8" :y="y(t) + 4" text-anchor="end" class="fill-muted text-[11px]">{{ compact.format(t) }}</text>
          </template>
        </g>
        <text v-for="h in [0, 3, 6, 9, 12, 15, 18, 21]" :key="h" :x="x(h)" :y="H - 8" text-anchor="middle" class="fill-muted text-[11px]">{{ hourLabel(h) }}</text>

        <g v-for="d in current" :key="d.hour">
          <rect v-if="hover === d.hour" :x="x(d.hour) - slot / 2" :y="PAD.top" :width="slot" :height="plotH" fill="#F0F0EC" />
          <path :d="barPath(d.hour, d.revenue)" fill="#1F5F4A" />
          <line
            v-if="previous[d.hour]?.revenue > 0"
            :x1="x(d.hour) - barW / 2 - 2"
            :x2="x(d.hour) + barW / 2 + 2"
            :y1="y(previous[d.hour].revenue)"
            :y2="y(previous[d.hour].revenue)"
            :stroke="COMPARE"
            stroke-width="2"
            stroke-linecap="round"
          />
          <!-- Area hover selebar slot (lebih besar dari batang) -->
          <rect :x="x(d.hour) - slot / 2" :y="PAD.top" :width="slot" :height="plotH" fill="transparent" @mouseenter="hover = d.hour" />
        </g>
      </svg>

      <div
        v-if="hover !== null"
        class="pointer-events-none absolute top-0 z-10 w-48 -translate-x-1/2 rounded border border-border bg-surface px-3 py-2 text-sm shadow-float"
        :style="{ left: `${tooltipLeft}%` }"
      >
        <p class="font-medium">{{ hourLabel(hover) }}–{{ hourLabel(hover + 1) }}</p>
        <p class="flex justify-between gap-2"><span class="text-muted">{{ currentLabel }}</span><span class="tabular-nums">{{ formatRupiah(current[hover].revenue) }}</span></p>
        <p class="flex justify-between gap-2"><span class="text-muted">Transaksi</span><span class="tabular-nums">{{ formatNumber(current[hover].transactions) }}</span></p>
        <p class="flex justify-between gap-2"><span class="text-muted">{{ previousLabel }}</span><span class="tabular-nums">{{ formatRupiah(previous[hover]?.revenue ?? 0) }}</span></p>
      </div>
    </div>

    <table v-else class="w-full text-sm">
      <thead class="text-muted">
        <tr><th class="py-1 text-left font-medium">Jam</th><th class="num font-medium">{{ currentLabel }}</th><th class="num font-medium">Transaksi</th><th class="num font-medium">{{ previousLabel }}</th></tr>
      </thead>
      <tbody>
        <tr v-for="d in current.filter((r) => r.revenue || previous[r.hour]?.revenue)" :key="d.hour" class="border-t border-border">
          <td class="py-1">{{ hourLabel(d.hour) }}</td>
          <td class="num">{{ formatRupiah(d.revenue) }}</td>
          <td class="num">{{ formatNumber(d.transactions) }}</td>
          <td class="num">{{ formatRupiah(previous[d.hour]?.revenue ?? 0) }}</td>
        </tr>
      </tbody>
    </table>
  </div>
</template>
