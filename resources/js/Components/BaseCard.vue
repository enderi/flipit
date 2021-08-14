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
.slide {
  animation-name: slide;
  animation-duration: 0.5s;
  backface-visibility: hidden;
  perspective: 1000px;
}

@keyframes slide {
    from { top: -10px; left: -100px; }
    to   { top: 0; left: 0; }
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
                return 'black'
            }
            if (suit === 'd') {
                return 'red'
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
