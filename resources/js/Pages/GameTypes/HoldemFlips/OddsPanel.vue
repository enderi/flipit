<template>
    <span>
        <span v-if="odds !== null && odds !== 100 && odds !== 0" class="text-right text-primary font-weight-bold">{{ odds }} %</span>
        <span v-if="odds !== null && odds === 100" class="text-right text-primary font-weight-bold">Winner</span>
        <span v-if="odds !== null && odds === 0" class="text-right text-primary font-weight-bold">Loser</span>
    </span>
</template>

<script>
export default {
    props: ['odds'],
};
</script>
