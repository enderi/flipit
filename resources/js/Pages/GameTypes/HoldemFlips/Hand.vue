<template>
  <span>
    <span v-for="item in items" :key="item.item && item.item.card">
        <span v-if="item.placeHolder">
            <card-place-holder/>
        </span>
        <card v-if="!item.placeHolder" 
            :card="item.item.card">
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
    props: ['items'],
    methods: {
    }
}
</script>
