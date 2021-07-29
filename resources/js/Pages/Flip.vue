<template>
    <app-layout :sub-header="params.game.game_type === 'OMAHA-FLIP' ? 'Omaha Flip' : 'Texas Flip'">
        <div class="container" v-if="!initializing">
            <div class="row text-center" v-if="handPhase === 'WAITING'">
                <div class="col-12">
                    Scan a code with app or send <a :href="params.invitationUrl" target="_blank">direct link</a><br/>
                    <vue-qr-code style="width: 80%" :value="params.invitationCode"/>
                    <br>
                </div>
            </div>
            <span v-if="handPhase !== 'WAITING'">
                <div class="row">
                    <div class="col-sm-6 offset-sm-3 col-xs-12">
                        <div class="row text-center">
                            <div
                                v-bind:class="{'winner': results.villain && results.villain.result === 'win', 'text-muted': results.villain && results.villain.result === 'lose'}"
                                class="col-12 pt-2">
                                <div class="row">
                                    <div class="col">
                                        <h3 class="text-left">'Villain'</h3>
                                    </div>
                                    <div class="col text-muted">
                                        {{stats[mySeat === 1?2:1] || 0}} wins
                                    </div>
                                </div>
                                <span v-for="pCard in handStatus.opponentCards">
                                <card :card="pCard"></card>
                                </span>
                                <p class="mt-0 mb-2">{{ handStatus.opponentHandValue.name || '&nbsp;' }}
                                </p>
                                <hr>
                            </div>
                            <div class="col-12 mb-4">
                                <h3>Table</h3>
                                <card v-for="cCard in handStatus.communityCards" :card="cCard"
                                ></card>
                            </div>
                            <hr>
                            <div
                                v-bind:class="{'winner': results.me && results.me.result === 'win', 'text-muted': results.me && results.me.result === 'lose'}"
                                class="col-12 pt-0">
                                <div class="row">
                                    <div class="col">
                                        <h3 class="text-left">You</h3>
                                    </div>
                                    <div class="col text-muted">
                                        {{stats[mySeat === 1?1:2] || 0}} wins
                                    </div>
                                </div>
                                <span v-for="pCard in handStatus.myCards">
                                    <card :card="pCard"></card>
                                </span>
                                <p class="mt-0">{{ handStatus.myHandValue.name || '&nbsp;' }}
                                </p>
                            </div>
                        </div>
                        
                    <div class="row fixed-bottom mb-2 me-2">
                        <div class="col-12 text-end">
                            <div v-if="options && options.length">
                                <action-button v-for="action in options" :action="action"
                                               v-on:action-made="acted"></action-button>
                            </div>
                            <div v-if="handPhase === 'HAND_ENDED' && !disableAll">
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

export default {
    components: {
        AppLayout,
        VueQrCode,
        ActionButton,
        Card,
        BaseCard
    },
    props: ["params"],
    data: function () {
        return {
            initializing: true,
            myCards: [],
            opponentCards: [],
            myHandValue: null,
            opponentHandValue: null,
            handStatus: null,
            result: null,
            handPhase: null,
            stats: null,
            mySeat: null,
            options: [],
            throttledStatus: _.throttle(this.getStatus, 10, {leading: false}),
            disableAll: false
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
        Echo.channel('game.' + this.params.game.uuid)
            .listen('GameStateChanged', (e) => {
                if (e.action === 'refresh') {
                    this.vibrate()
                    this.throttledStatus()
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
                .post("/api/hand-status", {
                    gameUuid: this.params.game.uuid,
                    playerUuid: this.params.playerUuid
                })
                .then(this.handleResponse)
        },
        handleResponse(resp) {
            this.initializing = false
            this.options = resp.data.options
            this.handStatus = resp.data.handStatus
            this.handPhase = resp.data.handPhase
            this.results = resp.data.results
            this.mySeat = resp.data.mySeat

            while (this.handStatus.communityCards.length < 5) {
                this.handStatus.communityCards.push({placeholder: true})
            }
            this.enableAllActions()
            this.getGameStats()
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
