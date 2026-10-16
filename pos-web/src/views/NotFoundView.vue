<script setup>
import Button from '@/components/ui/Button.vue'
import EmptyState from '@/components/ui/EmptyState.vue'
</script>

<template>
  <div class="mx-auto mt-24 max-w-md rounded border border-border bg-surface">
    <EmptyState
      title="Halaman tidak ditemukan."
      description="Alamat yang Anda buka salah atau halaman sudah dipindahkan."
    >
      <Button variant="primary" @click="$router.push('/')">Kembali ke Beranda</Button>
    </EmptyState>
  </div>
</template>
