<template>
  <div class="row">
    <div class="col-xs-12 offset-sm-1 col-sm-10 text-left">
        <div class="row text-center"> 
          <div class="col-12 text-center">
            <hand :items="cards" /><br>
          </div>
          <div class="col-12">
              <span v-if="handValue" style="font-size: 0.8rem">
                <h5 style="margin-bottom: 1px;">{{ handValue.name || "&nbsp;" }}</h5>
                <span v-if="handValue.cards" class="font-weight-light text-secondary">({{ handValue.cards.join(', ') }})</span><br>
              </span>
          </div>
          <div class="row-12">
            <div class="font-weight-light mb-1">
            </div>
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
  props: ["handValue", "cards", "odds", "name", "position"],

 methods: {},
};
</script>
