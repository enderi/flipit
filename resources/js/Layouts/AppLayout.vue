<template>
    <div>
        <!-- Page Content -->
        <!--<nav class="navbar navbar-expand-sm navbar-dark bg-dark">
            <div class="container">

                <inertia-link class="navbar-brand text-center" :href="route('home')">STACKED</inertia-link>
                <span class="navbar-text">
                    {{subHeader}}
                </span>
            </div>
        </nav>-->
        <div class="container">
            <div class="row">
                <div class="col-xs-12 col-sm-6 text-Left">
                    <h1><inertia-link class="navbar-brand text-center" :href="route('home')">STACKED</inertia-link></h1>
                </div>
            </div>
        </div>
        <main>
            <slot></slot>
        </main>
    </div>
</template>

<script>

    export default {
        props: ['subHeader'],
        data() {
            return {
            }
        },
        methods: {
        }
    }
</script>
