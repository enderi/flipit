<template>
  <div class="row">
    <div class="col-xs-12 offset-sm-2 col-sm-8 text-left">
        <div class="row">
            <div class="col-5">
              
                <h4 class="font-weight-light mb-1">
                    {{name}}
                </h4>
            
                <span v-if="handValue" style="font-size: 0.8rem">
                  {{ handValue.name || "&nbsp;" }}<br>
                  <span v-if="handValue.cards" class="font-weight-light text-secondary">({{ handValue.cards.join(', ') }})</span><br>
                  <h5 v-if="odds !== null" class="mt-2 text-primary font-weight-bold">{{ odds }} %</h5>
                </span>
            </div>
            <div class="col-7 text-center">
              <hand :items="cards" /><br>               
            </div>
        </div>
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
