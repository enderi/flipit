<template>
    <div>
        <button 
            v-bind:class="{disabled: isClicked}" 
            class="btn btn-primary btn-lg" 
            @click="act">
            {{action.text}}
        </button>
    </div>
</template>
<style scoped>
.shake {
  animation: shake 0.82s cubic-bezier(0.36, 0.07, 0.19, 0.97) both;
  transform: translate3d(0, 0, 0);
  backface-visibility: hidden;
  perspective: 1000px;
}

@keyframes shake {
  10%,
  90% {
    transform: translate3d(-1px, 0, 0);
  }

  20%,
  80% {
    transform: translate3d(2px, 0, 0);
  }

  30%,
  50%,
  70% {
    transform: translate3d(-4px, 0, 0);
  }

  40%,
  60% {
    transform: translate3d(4px, 0, 0);
  }
}
</style>

<script>
export default {
    props: ['action'],
    data: function(){
        return {
            isClicked: false
        }
    },
    methods: {
        act() {
            this.isClicked = true
            this.$emit('action-made', this.action)
        }
    }
}
</script>
