<template>
  <div class="row">
    <div class="col-2">
    </div>

    <div class="col-8 text-left">
        <div class="row">
            <div class="col-4"></div>
            <div class="col-4">
                <h3 class="font-weight-light text-center">
                    {{name}}
                </h3>  
            </div>
            <div class="col-4 text-end">
                <span class="text-muted" v-if="odds !== null">{{ odds }} %</span>
            </div>
        </div>
      <div class="text-center">
        <hand :items="cards" />
      </div>
      <span v-if="handValue && handValue.info">
        {{ handValue.info.name || "&nbsp;" }}<br />
        <span class="text-muted">{{ handValue.info.details }}</span>
      </span>
    </div>
  </div>
</template>

<script>
import Hand from "./Hand";
export default {
  components: {
    Hand,
  },
  props: ["handValue", "cards", "odds", "name"],
  mounted() {
      console.log('THIIIIIS', this.odds)
  },
 methods: {},
};
</script>
