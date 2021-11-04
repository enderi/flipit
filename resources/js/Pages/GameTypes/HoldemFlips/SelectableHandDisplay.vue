<template>
  <div class="row">
    <div class="col-xs-12 offset-sm-1 col-sm-10 text-left">
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
              <selectable-hand :items="cards" @card-clicked="cardClicked" /><br>
            </div>
        </div>
    </div>
  </div>
</template>

<script>
import SelectableHand from "./SelectableHand";
export default {
  components: {
    SelectableHand,
  },
  props: ["handValue", "cards", "odds", "name", "maxSelections"],

 methods: {
   cardClicked(item){
     var nowSelected = _.filter(this.cards, c => c.selected)
     .length

     if (item.selected) {
       item.selected = false
     } else {
       if(this.maxSelections === 1) {
        _.each(this.cards, c => {
          c.selected = false
        })
        item.selected = true
       } else if(nowSelected < this.maxSelections){
         item.selected = true
       }
     }

   }
 },
};
</script>
