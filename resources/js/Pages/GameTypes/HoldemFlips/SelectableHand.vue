<template>
  <span>
    <span v-for="item in items" :key="item.item && item.item.card">
        <span v-if="item.placeHolder">
            <card-place-holder/>
        </span>
        <selectable-card v-if="!item.placeHolder" @click="clicked(item)" 
            :card="item.item.card"
            :selected="item.selected">
        </selectable-card>
    </span>
  </span>
</template>
<style scoped>
</style>
<script>
import SelectableCard from '../../../Components/SelectableCard'
import CardPlaceHolder from '../../../Components/CardPlaceHolder'
export default {
    components: {
        SelectableCard,
        CardPlaceHolder
    },
    props: ['items'],
    methods: {
        clicked(c) {
            console.log('clicked', c);
            this.$emit('card-clicked', c)
        }
    }
}
</script>
