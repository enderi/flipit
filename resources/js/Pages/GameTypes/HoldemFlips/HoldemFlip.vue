<template>
  <app-layout
    :sub-header="
      params.game.game_type === 'OMAHA-FLIP' ? 'Omaha Flip' : 'Texas Flip'
    "
  >
    <div v-if="!initializing">
      <waiting
        v-if="!gameStarted"
        :url="params.invitationUrl"
        :code="params.invitationCode"
      ></waiting>
      <span v-if="gameStarted">
        <div class="border-success mb-3">
          <div>
            <div class="">
              <div class="row text-center">
                <div class="col-xs-12">
                  <!--- villain -->
                  <h4 class="text-left">Villain <span class="text-muted" v-if="odds !== null">{{odds[opponentSeat]}} %</span></h4>
                  <hand :items="placeHolders.target[opponentSeat]" />
                  <br />
                  <span
                    v-bind:class="{
                      bold: myHandValue.value > opponentHandValue.value,
                    }"
                  >
                    {{ opponentHandValue.name || "&nbsp;" }}
                  </span>
                  <hr />

                  <!-- Table -->
                  <div class="mt-4 mb-4">
                    <hand :items="placeHolders.target.community" />
                  </div>

                  <hr />
                  <h4 class="text-left">Hero <span class="text-muted" v-if="odds !== null">{{odds[mySeat]}} %</span></h4>
                  <!-- My -->
                  <hand :items="placeHolders.target[mySeat]" />
                  <br />
                  <span
                    v-bind:class="{
                      bold: myHandValue.value < opponentHandValue.value,
                    }"
                  >
                    {{ myHandValue.name || "&nbsp;" }}
                  </span>
                </div>
              </div>
            </div>
          </div>

            <div class="fixed-bottom text-center" style="min-height: 65px">
              <action-button class="me-1 ms-1" v-for="action in options" v-bind:key="action" :action="action" v-on:action-made="acted" />
              <!--<div v-if="options && options.length">
                <span v-for="action in options" v-bind:key="action">
                  <action-button :action="action" v-on:action-made="acted" />
                </span>
              </div>-->
            </div>
        </div>
      </span>
    </div>
  </app-layout>
</template>

<style scoped>
.bottom-navigation {
  position: absolute;
  bottom: 65px;;
  width: 100%;
}


/****/
.navbar-nav.navbar-center {
    position: absolute;
    left: 50%;
    transform: translatex(-50%);
}

.card.card-footer {
  align-self: flex-end;
  flex: 1 1 auto;
}

.bold {
  font-weight: bold;
}

.bottom-row {
  position: absolute;
  bottom: 0;
}

.winner {
  background-image: linear-gradient(
    to right,
    white,
    lightgreen,
    lightgreen,
    lightgray,
    white
  );
}
</style>

<script>
import AppLayout from "@/Layouts/AppLayout";
import VueQrCode from "vue3-qrcode";
import ActionButton from "../../../Components/ActionButton";
import CardPlaceHolder from "../../../Components/CardPlaceHolder";
import Hand from "./Hand";
import Waiting from "../../../Pages/Waiting";

export default {
  components: {
    AppLayout,
    VueQrCode,
    ActionButton,
    CardPlaceHolder,
    Waiting,
    Hand,
  },
  props: ["params"],
  data: function () {
    return {
      options: null,
      gameStatus: null,
      initializing: true,
      mySeat: this.params.seatNumber,
      opponentSeat: +this.params.seatNumber === 1 ? 2 : 1,
      myHandValue: {},
      opponentHandValue: {},
      results: {},
      cardsDealt: 0,
      communityCards: [],
      gameStarted: false,
      placeHolders: this.buildPlaceHolders(),
      dealtCardArray: {},
      dealtCards: [],
      odds: null,
    };
  },
  mounted() {
    console.log("=====", this.params);
    Echo.channel("game." + this.params.uuid).listen("GameStateChanged", (e) => {
      if (e.action.action === "new-status") {
        this.vibrate();
        this.handleResponse(e.action.status);
      } else if (e.action === "opponent-left") {
        this.vibrate();
        confirm("Your opponent has left, I'll guide you home");
        this.quit();
      } else if (e.action === "info") {
        this.$wkToast(e.action.status);
      }
    });
    this.getStatus();
  },
  unmounted() {
    this.unSubscribeEcho();
  },
  methods: {
    unSubscribeEcho() {
      Echo.leave("game." + this.params.game.uuid);
    },
    vibrate() {
      if ("vibrate" in navigator) {
        navigator.vibrate =
          navigator.vibrate ||
          navigator.webkitVibrate ||
          navigator.mozVibrate ||
          navigator.msVibrate;
        if (navigator.vibrate) {
          navigator.vibrate(100);
        }
      }
    },
    unSubscribeEcho() {
      Echo.leave("game." + this.params.game.uuid);
    },
    vibrate() {
      if ("vibrate" in navigator) {
        navigator.vibrate =
          navigator.vibrate ||
          navigator.webkitVibrate ||
          navigator.mozVibrate ||
          navigator.msVibrate;
        if (navigator.vibrate) {
          navigator.vibrate(100);
        }
      }
    },
    getStatus() {
      console.log("uuid", this.params);
      axios
        .get("/api/hand-status/" + this.params.uuid)
        .then((resp) => this.handleResponse(resp.data));
    },
    dealNextCard(item) {
      this.addCardToFirstFreeSlot(this.placeHolders.target[item.target], item);
      this.dealtCardArray[item.index] = item;
      this.$forceUpdate();
    },
    addCardToFirstFreeSlot(items, item) {
      for (var i = 0; i < items.length; i++) {
        var curr = items[i];
        if (curr.placeHolder) {
          curr.placeHolder = false;
          curr.item = item;
          break;
        }
      }
    },
    initialize() {
      this.placeHolders = this.buildPlaceHolders();
      this.cardsDealt = 0;
    },
    handleResponse(data) {
      console.log("status", data);
      if (data.handStatus === "WAITING_PLAYERS") {
        this.gameStarted = false;
        this.initializing = false;
        return;
      } else {
        this.gameStarted = true;
        this.initializing = false;
      }

      var delay = 0;
      if (this.cardsDealt > data.cardsInDealOrder.length) {
        this.initialize();
      }
      if (this.cardsDealt <= data.cardsInDealOrder.length) {
        for (var i = 0; i < this.cardsDealt; i++) {
          var source = data.cardsInDealOrder[i];
          var curr = _.find(this.placeHolders.target[source.target], (ii) => {
            return !ii.placeHolder && ii.item.index === source.index;
          });

          if (curr && curr.item.card !== source.card) {
            curr.item.card = source.card;
          }
          var curr = this.dealtCardArray[source.index];
          if (curr && curr.card !== source.card) {
            this.dealtCardArray[source.index] = source;
          }
        }
        while (this.cardsDealt < data.cardsInDealOrder.length) {
          var currCard = data.cardsInDealOrder[this.cardsDealt];
          _.delay(this.dealNextCard, delay, currCard);
          delay = delay + 150;
          this.cardsDealt++;
        }
      }
      this.options = data.options && data.options[this.mySeat];
      this.myHandValue = data.myHandValue;
      this.opponentHandValue = data.opponentHandValue;
      if(data.odds) {
        this.odds = {
          1: Math.round((data.odds[1] / data.odds['total']) *100),
          2: Math.round((data.odds[2] / data.odds['total']) *100),
        }
      } else {
        this.odds = null
      }
  },
    acted(action) {
      this.disableAllActions();
      axios
        .post("/api/hand-status/action", {
          uuid: this.params.uuid,
          action: action.key,
        })
        .then((resp) => this.handleResponse(resp.data))
        .finally(this.enableAllActions);
    },
    disableAllActions() {
      this.disableAll = true;
    },
    enableAllActions() {
      this.disableAll = false;
    },
    quit() {
      this.unSubscribeEcho();
      this.$inertia.post(this.route("exit-game"), {
        gameUuid: this.params.game.uuid,
      });
    },
    newHand() {
      this.disableAllActions();
      axios.post("/api/hand-status/new", {
        gameUuid: this.params.game.uuid,
        playerUuid: this.params.playerUuid,
      });
    },
    getGameStats() {
      axios.get("/api/game/stats/" + this.params.game.uuid).then((resp) => {
        this.stats = resp.data.winsBySeat;
      });
    },
    buildPlaceHolders() {
      var cardCount = this.params.game.game_type === "OMAHA-FLIP" ? 4 : 2;
      var placeHolders = {
        target: {
          1: [],
          2: [],
          community: [],
        },
      };
      for (var i = 0; i < 5; i++) {
        placeHolders.target.community.push({
          placeHolder: true,
          item: null,
        });
      }
      for (var i = 0; i < cardCount; i++) {
        placeHolders.target[1].push({
          placeHolder: true,
          item: null,
        });
        placeHolders.target[2].push({
          placeHolder: true,
          item: null,
        });
      }
      this.dealtCardArray = {};
      return placeHolders;
    },
  },
};
</script>
