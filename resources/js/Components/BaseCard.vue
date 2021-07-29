<template>
  <span>
    <span v-if="backside" class="playing-card back">
    </span>
    <span 
        v-if="!backside" 
        class="playing-card" 
        v-bind:class="[getColor(), {'mini-card': minicard}]">
      <div>{{getRank()}}</div>
      <div v-html="getSuit()"></div>
    </span>
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
    font-size: 0.9em; /* @change: adjust this card to make bigger or smaller cards */
    font-weight: bolder;
    position: relative;
    background-color: #fff;
    -moz-box-shadow: .1em .1em .3em #333;
    -webkit-box-shadow: .1em .1em .3em #333;
    box-shadow: .1em .1em .3em #333;
}
.playing-card.mini-card {
    font-size: 0.5em;
}
.clickable-card:hover {
    background-color: lightgray;
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
    props: ['backside', 'card', 'minicard'],
    methods: {
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
