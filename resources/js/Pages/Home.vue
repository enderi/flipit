<template>
    <app-layout>
        <div class="row mt-4">
            <div class="col-12 text-center">
                <inertia-link :href="route('flip-create')" method="post" class="btn btn-primary btn-lg">Omaha Flip</inertia-link>
            </div>
        </div>
        <div class="row mt-2">
            <div class="col-12 text-center">
                or
            </div>
        </div>
        <div class="row mt-2">
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
        }
    }
</script>
