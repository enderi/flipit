<template>
  <span>
    <base-card v-if="card !== ''" :backside="card === '??'" :card="card"></base-card>
  </span>
</template>
<style scoped>
.empty {
    text-indent: -4000px;
    border: 1px dashed #111;
    background-color: lightgray;
    -moz-box-shadow: none;
    -webkit-box-shadow: none;
    box-shadow: none;
}
</style>
<script>
import BaseCard from './BaseCard'
export default {
    components: {
        BaseCard
    },
    props: ['card'],
    methods: {
    }
}
</script>
