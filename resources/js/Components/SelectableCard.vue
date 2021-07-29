<template>
  <span>
    <span v-if="!card" class="playing-card back">
    </span>
      <span v-if="card && card === 'empty'" class="playing-card empty">
          <div>&nbsp;</div>
          <div>&nbsp;</div>
      </span>
    <span v-if="card && card !== 'empty'" class="playing-card" v-bind:class="[getColor(), {'best-hand': highlight, 'grayed': (downlightOthers && !highlight)}]">
      <div>{{getRank()}}</div>
      <div v-html="getSuit()"></div>
    </span>
  </span>
</template>
<style scoped>
.playing-card {
    font-weight: bold;
    display: inline-block;
    width: 3.3em;
    height: 4.6em;
    border: 1px solid #666;
    border-radius: .3em;
    -moz-border-radius: .3em;
    -webkit-border-radius: .3em;
    -khtml-border-radius: .3em;
    padding: .25em;
    margin: 0 .5em .5em 0;
    text-align: center;
    font-size: 0.9em; /* @change: adjust this value to make bigger or smaller cards */
    font-weight: normal;
    font-family: Arial, sans-serif;
    position: relative;
    background-color: #fff;
    -moz-box-shadow: .1em .1em .3em #333;
    -webkit-box-shadow: .1em .1em .3em #333;
    box-shadow: .1em .1em .3em #333;
}

.red {
    color: red
}

.blue {
    color: blue;
}

.green {
    color: green;
}

.black {
    color: black;
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
    props: ['card', 'highlight', 'downlightOthers'],
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
