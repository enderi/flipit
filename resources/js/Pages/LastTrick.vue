<template>
    <app-layout :sub-header="'Last Trick'">
        <div class="container" v-if="!initializing">
            <div class="row text-center" v-if="handPhase === 'WAITING'">
                <div class="col-12">
                    Scan a code with app or send <a :href="params.invitationUrl" target="_blank">direct link</a><br/>
                    <vue-qr-code style="width: 80%" :value="params.invitationCode" /> <br>
                </div>
            </div>
            <span v-if="handPhase !== 'WAITING'">
                <div class="row mt-4" >
                    <div class="col-12 text-center" v-for="seat in otherSeats">
                        <span v-for="pCard in cardsPerSeat[seat]">
                          <card :card="revealedCards[pCard.card_uuid]" :highlight="pCard && bestHand[pCard.card_uuid]" :downlightOthers="handResult"></card>
                        </span>
                    </div>

                    <div class="col-12 text-center">
                        <div class="row mb-4">
                            <div class="col">
                                <card :card="'empty'"></card>
                            </div>
                        </div>
                        <div class="row mt-4">
                            <div class="col">
                                <card :card="'empty'"></card>
                            </div>
                        </div>
                    </div>
                    <div class="col-12 text-center">
                        <span v-for="pCard in cardsPerSeat[mySeat]">
                          <card :card="revealedCards[pCard.card_uuid]" :highlight="pCard && bestHand[pCard.card_uuid]"></card>
                        </span>
                    </div>
                </div>
                <div class="row fixed-bottom mb-2 me-2">
                    <div class="col-12 text-end">
                        <div v-if="options && options.length">
                            <action-button v-for="action in options" :action="action" v-on:action-made="acted"></action-button>
                        </div>
                        <div v-if="handPhase === 'HAND_ENDED' && !disableAll    " >
                            <button class="btn btn-link btn-lg" @click="quit">Exit</button>
                            <button class="btn btn-outline-primary btn-lg" @click="newHand">Next hand</button>
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
</style>

<script>
import AppLayout from '@/Layouts/AppLayout'
import VueQrCode from "vue3-qrcode";
import ActionButton from '../Components/ActionButton'
import Card from '../Components/Card'

export default {
    components: {
        AppLayout,
        VueQrCode,
        ActionButton,
        Card
    },
    props: ["params"],
    data: function () {
        return {
            initializing: true,
            players: 0,
            game: null,
            hand: null,
            player: null,
            seats: [],
            cardsPerSeat: {},
            revealedCards: [],
            bestHand: {},
            mySeat: null,
            throttledStatus: _.throttle(this.getStatus, 0, {leading: false}),

            handPhase: null,
            disableAll: false,
        };
    },
    computed: {
        playerCount() {
            return this.players ? this.players.length : 0;
        },
        otherSeats() {
            return _.filter(_.keys(this.cardsPerSeat), (s) => {return +s !== +this.mySeat})
        }
    },
    mounted() {
        Echo.channel('game.' + this.params.game.uuid)
            .listen('GameStateChanged', (e) => {
                if (e.action === 'refresh') {
                    this.vibrate()
                    this.throttledStatus()
                }
            });
        this.getStatus();
    },
    unmounted() {
        Echo.leave('game.' + this.params.game.uuid);
    },
    methods: {
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
                .post("/api/hand-status", {
                    gameUuid: this.params.game.uuid,
                    playerUuid: this.params.playerUuid
                })
                .then(this.handleResponse)
        },
        handleResponse(resp) {
            this.initializing = false
            this.revealedCards = resp.data.revealedCards
            this.cardsPerSeat = resp.data.cardsPerSeat
            this.mySeat = resp.data.mySeat

            this.handPhase = resp.data.handPhase

            this.enableAllActions()
        },
        acted(action) {
            axios.post('/api/hand-status/action', {
                gameUuid: this.params.game.uuid,
                playerUuid: this.params.playerUuid,
                actionUuid: action.uuid,
                action: action.key
            }).then(() => this.options = [])
        },
        disableAllActions() {
            this.disableAll = true
        },
        enableAllActions() {
            this.disableAll = false
        },
        quit() {
            this.$inertia.get('/')
        }
    },
};
</script>
