<template>
  <span>
    <span v-for="card in cards" v-bind:key="card.card">
        <span v-if="card.placeHolder">
            <card-place-holder/>
        </span>
        <card v-if="!card.placeHolder"
            v-bind:key="card.card" 
            :card="card.card">
        </card>
    </span>
  </span>
</template>
<style scoped>

</style>
<script>
import Card from '../../../Components/Card'
import CardPlaceHolder from '../../../Components/CardPlaceHolder'
export default {
    components: {
        Card,
        CardPlaceHolder
    },
    props: ['cards'],
    methods: {
    }
}
</script>
