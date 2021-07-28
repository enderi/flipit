<template>
    <app-layout :sub-header="gameCount + ' games played'">
        <div class="container">

            <dl class="row">
                <dt class="col-xs-12 text-center mt-4 mb-4">Flips</dt>
                <dd class="col-xs-12 text-center">
                    <p>
                        <a @click="requestGame('OMAHA-FLIP')" class="btn btn-link btn-lg">Omaha</a>
                    </p>
                    <p>
                        <a @click="requestGame('TEXAS-FLIP')" class="btn btn-link btn-lg">Texas</a>
                    </p>
                </dd>
            </dl>
            <!--
            <div class="text-center align-middle mt-4">
                <a @click="requestGame('LAST-TRICK')" class="btn btn-primary btn-lg">Last Trick Game</a>
            </div>-->

            <div class="text-center mb-4">
                or
            </div>
            <div class="text-center">
                <inertia-link :href="route('join')" class="btn btn-outline-primary btn-lg">Join game</inertia-link>
            </div>
<!--
            <div class="card-body">
                <h5 class="card-title">Stats</h5>
                {{ gameCount }} games played
                {{ handCount }} hands dealt
            </div>-->
        </div>
        <!--<footer>
            <div class="text-center">
                Laravel v{{ laravelVersion }} (PHP v{{ phpVersion }})
            </div>
        </footer>-->
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
