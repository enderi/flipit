<template>
    <app-layout :sub-header="params.game.game_type === 'OMAHA-FLIP' ? 'Omaha Flip' : 'Texas Flip'">
        <div class="container" v-if="!initializing">
            <waiting v-if="!gameStarted" :url="params.invitationUrl" :code="params.invitationCode"></waiting>
            <span v-if="gameStarted">
                <div class="row">
                    <div class="col-sm-6 offset-sm-3 col-xs-12">
                        <div class="row text-center">
                            <!--<div
                                v-bind:class="{'winner': results.opponent && results.opponent.result === 'win', 'text-muted': results.opponent && results.opponent.result === 'lose'}"
                                class="col-12 pt-2">-->
                            <div
                                class="col-12 pt-2">
                                <div class="row">
                                    <div class="col">
                                        <h3 class="text-left">'Villain'
                                            <span v-if="options && options[opponentSeat]" class="spinner-border text-primary" role="status">
                                            </span>
                                        </h3>
                                    </div>
                                    <!--<div class="col text-muted">
                                        {{stats[mySeat === 1?2:1] || 0}} wins
                                    </div>-->
                                </div>
                                <span v-if="hand.cards" v-for="pCard in (hand.cards[opponentSeat] || [])">
                                    <card :card="pCard"></card>
                                </span>
                                <p class="mt-0 mb-2" v-if="opponentHandValue">{{ opponentHandValue.name || '&nbsp;' }} - {{ opponentHandValue.value || '&nbsp;' }}
                                </p>
                                <hr>
                            </div>
                            <div class="col-12 mb-4">
                                <h3>Table</h3>
                                <card v-if="hand.cards" v-for="cCard in communityCards" :card="cCard"
                                ></card>
                            </div>
                            <hr>
                            <!--<div
                                v-bind:class="{'winner': results.me && results.me.result === 'win', 'text-muted': results.me && results.me.result === 'lose'}"
                                class="col-12 pt-0">-->
                            <div class="col-12 pt-0">
                                <div class="row">
                                    <div class="col">
                                        <h3 class="text-left">You</h3>
                                    </div>
                                    <!--<div class="col text-muted">
                                        {{stats[mySeat === 1?1:2] || 0}} wins
                                    </div>-->
                                </div>
                                <span v-if="hand.cards" v-for="pCard in (hand.cards[mySeat] || [])">
                                    <card :card="pCard"></card>
                                </span>
                                <p class="mt-0" v-if="myHandValue">{{ myHandValue.name || '&nbsp;' }} - {{ myHandValue.value || '&nbsp;' }}
                                </p>
                            </div>
                        </div>

                    <div class="row fixed-bottom mb-2 me-2">
                        <div class="col-12 text-end">
                            <div v-if="options && options.length">
                                <transition v-for="action in options" enter-active-class="animate__animated animate__fadeIn">
                                <action-button :action="action"
                                               v-on:action-made="acted"></action-button>
                                </transition>
                            </div>
                            <div v-if="gameStatus === 'HAND_ENDED' && !disableAll">
                                <button class="btn btn-link btn-lg" @click="quit">Exit</button>
                                <button class="btn btn-outline-primary btn-lg" @click="newHand">Next hand</button>
                            </div>
                        </div>
                    </div>
                    </div>
                </div>
            </span>


        </div>
    </app-layout>
</template>

<style scoped>
.bottom-row {
    position: absolute;
    bottom: 0;

}

.table {
    background-color: darkgreen;
}

.winner {
    background-image: linear-gradient(to right, white, lightgreen, lightgreen, lightgray, white);
}
</style>

<script>
import AppLayout from '@/Layouts/AppLayout'
import VueQrCode from "vue3-qrcode";
import ActionButton from '../Components/ActionButton'
import Card from '../Components/Card'
import BaseCard from '../Components/BaseCard'
import Waiting from './Waiting'


export default {
    components: {
        AppLayout,
        VueQrCode,
        ActionButton,
        Card,
        BaseCard,
        Waiting
    },
    props: ["params"],
    data: function () {
        return {
            throttledStatus: _.throttle(this.getStatus, 10, {leading: false}),
            hand: {
                cards: {}
            },
            options: null,
            gameStatus: null,
            initializing: true,
            mySeat: this.params.seatNumber,
            opponentSeat: +this.params.seatNumber === 1 ? 2: 1,
            myHandValue: {},
            opponentHandValue: {},
            results: {},
            cardsDealt: 0,
            communityCards: [],
            gameStarted: false
        };
    },
    computed: {
        playerCount() {
            return this.players ? this.players.length : 0;
        },
        otherSeats() {
            return _.filter(_.keys(this.cardsPerSeat), (s) => {
                return +s !== +this.mySeat
            })
        }
    },
    mounted() {
        console.log('=====' , this.params)
        Echo.channel('game.' + this.params.uuid)
            .listen('GameStateChanged', (e) => {
                console.log('new event received 1', e.action.action);
                console.log('new event received 2', e.action.status);
                if (e.action.action === 'new-status') {
                    this.vibrate()

                    this.handleResponse(e.action.status)
                }
                if(e.action === 'opponent-left') {
                    this.vibrate()
                    confirm('Your opponent has left, I\'ll guide you home');
                    this.quit();
                }
            });
        this.getStatus();
    },
    unmounted() {
        this.unSubscribeEcho()
    },
    methods: {
        unSubscribeEcho() {
            Echo.leave('game.' + this.params.game.uuid)
        },
        vibrate() {
            if ("vibrate" in navigator) {
                navigator.vibrate = navigator.vibrate || navigator.webkitVibrate || navigator.mozVibrate || navigator.msVibrate;
                if (navigator.vibrate) {
                    navigator.vibrate(100);
                }
            }
        },
        getStatus() {
            axios
                .get("/api/hand-status/" + this.params.uuid)
                .then(resp => this.handleResponse(resp.data))
        },
        dealNextCard(item) {
            if(item.target === 'community'){
                this.communityCards.push(item.card)
            } else {
                this.hand.cards[item.target] = this.hand.cards[item.target] || []
                this.hand.cards[item.target].push(item.card)
            }
            this.$forceUpdate()
        },
        handleResponse(data) {
            if(data.handStatus === 'waiting_for_opponent'){
                this.gameStarted = false
                this.initializing = false
                return
            } else {
                this.gameStarted = true
                this.initializing = false
            }

            var delay = 0
            if(this.cardsDealt < data.cardsInDealOrder.length) {
                console.log('hephe', data.cardsInDealOrder.length)
                while(this.cardsDealt < data.cardsInDealOrder.length){
                    var currCard = data.cardsInDealOrder[this.cardsDealt]
                    _.delay(this.dealNextCard, delay, currCard)
                    delay = delay + 150
                    this.cardsDealt++;
                }
            }
            this.options = data.options && data.options[this.mySeat]
            
            this.myHandValue = data.myHandValue
            this.opponentHandValue = data.opponentHandValue
            return
        },
        updateCommunityCards(cards) {
            if(cards) {
                this.communityCards = cards
            } else {
                this.communityCards = ['','','','','',]
            }
            while(this.communityCards.length < 5) {
                this.communityCards.push('')
            }
        },
        acted(action) {
            this.options = []
            axios.post('/api/hand-status/action', {
                uuid: this.params.uuid,
                action: action.key
            })
            .then((resp) => this.handleResponse(resp.data))
        },
        disableAllActions() {
            this.disableAll = true
        },
        enableAllActions() {
            this.disableAll = false
        },
        quit() {
            this.unSubscribeEcho()
            this.$inertia.post(this.route('exit-game'), {gameUuid: this.params.game.uuid});
        },
        newHand() {
            this.disableAllActions()
            axios.post('/api/hand-status/new', {
                gameUuid: this.params.game.uuid,
                playerUuid: this.params.playerUuid,
            });
        },
        getGameStats() {
            axios.get('/api/game/stats/' + this.params.game.uuid)
            .then((resp)=>{
                this.stats = resp.data.winsBySeat
            });
        }
    },
};
</script>
