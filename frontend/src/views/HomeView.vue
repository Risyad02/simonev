<template>
  <div class="home">
    <h1>SIMONEV</h1>
    <p>Sistem Monitoring dan Evaluasi Kinerja Perangkat Daerah</p>
    <p class="status">Frontend Vue 3 (Options API) + Vue Router + Pinia — berjalan.</p>
  </div>
</template>

<script>
export default {
  name: 'HomeView'
}
</script>

<style scoped>
.home {
  text-align: center;
  margin-top: 4rem;
  font-family: sans-serif;
}
.status {
  color: green;
  font-weight: bold;
}
</style>