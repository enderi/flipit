<template>
  <div class="row">
    <div class="col-xs-12 offset-sm-2 col-sm-8 text-left">
        <div class="row">
            <div class="col-7 text-center">
                <h3 class="font-weight-light mb-1">
                    {{name}}
                </h3>
                <hand :items="cards" />
            </div>
            <div class="col-5 text-end">
                <span v-if="odds !== null">
                    <span class="text-primary font-weight-bold">{{ odds }} %</span><br>
                    {{ handValue.name || "&nbsp;" }}<br>
                    <span class="font-weight-light text-secondary" style="font-size: 0.8rem">{{ handValue.details }}</span>
                </span>
            </div>
        </div>
      <!--<span v-if="handValue">
        {{ handValue.name || "&nbsp;" }}<br />
        <span class="text-muted">{{ handValue.details }}</span>
      </span>-->
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

 methods: {},
};
</script>
