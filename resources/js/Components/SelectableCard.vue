<template>
  <span>
    <span v-if="!card" class="playing-card back">
    </span>
      <span v-if="card && card === 'empty'" class="playing-card empty">
          <div>&nbsp;</div>
          <div>&nbsp;</div>
      </span>
    <span v-if="card && card !== 'empty'" 
        class="playing-card" 
        v-bind:class="[getColor(), {'selected': selected, 'best-hand': highlight, 'grayed': (downlightOthers && !highlight)}]">
      <div>{{getRank()}}</div>
      <div v-html="getSuit()"></div>
    </span>
  </span>
</template>
<style scoped>
.selected {
    border: 3px solid darkorange;
}


.back {
    text-indent: -4000px;
    background-color: green;
}

.empty {
    text-indent: -4000px;
    border: 1px dashed #111;
    background-color: lightgray;
    -moz-box-shadow: none;
    -webkit-box-shadow: none;
    box-shadow: none;
}

.best-hand {
    border: 3px solid orange;
}

.grayed {
    background-color: lightgray;
}

</style>
<script>
export default {
    props: ['card', 'highlight', 'downlightOthers', 'selected'],
    methods: {
        isdownlightOthersed() {
            if(this.downlightOthers && !this.highlight){
                return 'grayed'
            }
        },
        getColor() {
            var suit = this.card[1]
            if (suit === 'h') {
                return 'red'
            }
            if (suit === 'c') {
                return 'green'
            }
            if (suit === 'd') {
                return 'blue'
            }
            return 'black'
        },
        getRank() {
            return this.card[0] || '?'
        },
        getSuit() {
            var suit = this.card[1]
            if (suit === 'h') {
                return '&hearts;';  //'&#9829;'
            }
            if (suit === 'd') {
                return '&diams;'
            }
            if (suit === 's') {
                return '&spades;'
            }
            if (suit === 'c') {
                return '&clubs;'
            }
            return '?'
        }
    }
}
</script>
