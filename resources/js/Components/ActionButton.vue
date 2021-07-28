<template>
    <div>
        <button v-bind:class="{disabled: isClicked}" class="btn btn-primary btn-lg" @click="act">{{action.text}}</button>
    </div>
</template>

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
