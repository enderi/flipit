<template>
    <app-layout>

        <div class="row mt-4 " style="height: 100%;">
            <div class="col-12 text-center align-middle mt-4">
                <a @click="requestGame('OMAHA-FLIP')" class="btn btn-primary btn-lg">Omaha Flip</a>
            </div>
    
            <div class="col-12 text-center align-middle mt-4">
                <a @click="requestGame('TEXAS-FLIP')" class="btn btn-primary btn-lg">Texas Flip</a>
            </div>

            <div class="col-12 text-center mt-4 mb-4">
                or
            </div>
            <div class="col-12 text-center">
                <inertia-link :href="route('join')" class="btn btn-outline-primary btn-lg">Join game</inertia-link>
            </div>
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
            laravelVersion: String,
            phpVersion: String,
        },
        methods: {
            requestGame(gameType){
                this.$inertia.post(this.route('flip-create'), {gameType: gameType})
            }
        }
    }
</script>
