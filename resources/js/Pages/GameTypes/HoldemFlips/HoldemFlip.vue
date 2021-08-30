<template>
    <app-layout
        :mini="true"
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
          <div class="row">
            <div class="col-12">
              <hand-display style="height: 22vh" name="VILLAIN"
                            :hand-value="handValues[opponentSeat]"
                            :cards="placeHolders.target[opponentSeat]"
                            :odds="odds && odds[opponentSeat]">
              </hand-display>

              <hr/>
                <!-- Table -->
              <div class="mt-3 mb-3 text-center">
                <hand :items="placeHolders.target.community"/>
              </div>

              <hr/>
                <!-- My -->
              <hand-display style="height: 22vh" name="HERO"
                            :hand-value="handValues[mySeat]"
                            :cards="placeHolders.target[mySeat]"
                            :odds="odds && odds[mySeat]">
              </hand-display>
            </div>
          </div>

          <div class="fixed-bottom text-end"
               style=" max-width: 720px; margin-left: auto; margin-right: auto; min-height: 38px">
            <action-button class="me-1 ms-1" v-for="action in options" v-bind:key="action" :action="action"
                           v-on:action-made="optionSelected"/>
            <action-button class="me-1 ms-1" v-for="action in actions" v-bind:key="action" :action="action"
                           v-on:action-made="acted"/>
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

import HandDisplay from "./HandDisplay";

export default {
    components: {
        AppLayout,
        VueQrCode,
        ActionButton,
        CardPlaceHolder,
        Waiting,
        Hand,
        HandDisplay,
    },
    props: ["params"],
    data: function () {
        return {
            actions: null,
            options: null,
            gameStatus: null,
            initializing: true,
            mySeat: this.params.seatNumber,
            opponentSeat: +this.params.seatNumber === 1 ? 2 : 1,
            handValues: null,
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
        console.log('ppppp', this.opponentSeat)
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
            axios
                .get("/api/hand-status/" + this.params.uuid)
                .then((resp) => this.handleResponse(resp.data));
        },
        dealNextCard(item) {
            this.addCardToFirstFreeSlot(this.placeHolders.target[item.target], item);
            this.dealtCardArray[item.card_index] = item;
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
            console.log('NEW DATA: ', this.cardsDealt, data)
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
                        return !ii.placeHolder && ii.item.card_index === source.card_index;
                    });

                    if (curr && curr.item.card !== source.card) {
                        curr.item.card = source.card;
                    }
                    curr = this.dealtCardArray[source.card_index];
                    if (curr && curr.card !== source.card) {
                        this.dealtCardArray[source.card_index] = source;
                    }
                }
                while (this.cardsDealt < data.cardsInDealOrder.length) {
                    var currCard = data.cardsInDealOrder[this.cardsDealt];
                    _.delay(this.dealNextCard, delay, currCard);
                    delay = delay + 150;
                    this.cardsDealt++;
                }
            }
            this.actions = data.actions && this.mapOptions(data.actions[this.mySeat])
            this.options = data.options && this.mapOptions(data.options[this.mySeat])
            this.handValues = data.handValues
            if (data.odds) {
                this.odds = {
                    1: Math.round((data.odds[1] / data.odds['total']) * 100),
                    2: Math.round((data.odds[2] / data.odds['total']) * 100),
                }
            } else {
                this.odds = null
            }
        },
        mapOptions(optionKeys) {
          var labels = {
            'confirm': 'Continue',
            'show_cards': 'Show your cards',
              'new_hand': 'New hand'
          }
          return _.map(optionKeys, (key) => {
            return {
              text: labels[key],
              key: key
            }
          })
        },
        optionSelected(option) {
          console.log('option', option);
            this.disableAllActions();
            axios
                .post("/api/hand-status/option", {
                    uuid: this.params.uuid,
                    option: option.key,
                })
                .then((resp) => this.handleResponse(resp.data))
                .finally(this.enableAllActions);        },
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
