<template>
  <span>
    <span v-if="card === ''" class="playing-card empty">
        <div>&nbsp;</div>
        <div>&nbsp;</div>
    </span>
    <base-card v-if="card !== ''" :backside="card === '??'" :card="card"></base-card>
  </span>
</template>
<style scoped>
.playing-card {
    display: inline-block;
    width: 3.3em;
    height: 4.6em;
    border: 1px solid #666;
    border-radius: .3em;
    -moz-border-radius: .3em;
    -webkit-border-radius: .3em;
    -khtml-border-radius: .3em;
    padding: .5em;
    margin: 0 .5em .5em 0;
    text-align: center;
    font-size: 0.9em; /* @change: adjust this value to make bigger or smaller cards */
    font-weight: bolder;
    position: relative;
    background-color: #fff;
    -moz-box-shadow: .1em .1em .3em #333;
    -webkit-box-shadow: .1em .1em .3em #333;
    box-shadow: .1em .1em .3em #333;
}
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
