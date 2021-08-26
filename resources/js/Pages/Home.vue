<template>
    <app-layout :sub-header="gameCount + ' games played'">
        <div class="container">
            <h3>Choose game</h3>
            <ul class="list-group text-center">
                <a @click="requestGame('OMAHA-FLIP')" class="list-group-item text-primary">Omaha Flip</a>
                <a @click="requestGame('TEXAS-FLIP')" class="list-group-item text-primary">Texas Flip</a>
                <!-- <a @click="requestGame('CRAZY-PINEAPPLE')" class="list-group-item text-primary">Crazy Pineapple Semiflip</a> -->
            </ul>

            <!--<div class="text-center align-middle mt-4">
                <a @click="requestGame('LAST-TRICK')" class="btn btn-primary btn-lg">Last Trick Game</a>
            </div>-->

            <div class="text-center mb-4">
                or
            </div>
            <div class="text-center">
                <inertia-link :href="route('join')" class="btn btn-outline-primary btn-lg">Join game</inertia-link>
            </div>
            <!--<div class="card-body">
                <h5 class="card-title">Stats</h5>
                {{ gameCount }} games played
                {{ handCount }} hands dealt
            </div>-->
        </div>
    </app-layout>
</template>

<script>
    import AppLayout from '@/Layouts/AppLayout'

    export default {
        components: {
            AppLayout,
        },
        props: {
            // canLogin: Boolean,
            // canRegister: Boolean,
            handCount: Number,
            laravelVersion: String,
            phpVersion: String,
            gameCount: Number
        },
        methods: {
            requestGame(gameType){
                this.$inertia.post(this.route('game-create'), {gameType: gameType})
            }
        }
    }
</script>
