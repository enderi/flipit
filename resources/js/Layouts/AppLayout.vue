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
        <div class="container-fluid">
            <div class="row">
                <div class="col-lg-6 offset-lg-3 col-xs-6">
                    <h1>
                        <inertia-link class="navbar-brand text-center" :href="route('home')">STACKED</inertia-link>
                    </h1>
                </div>
            </div>
        </div>
        <hr>
        <main>
            <div class="container-fluid">
                <div class="row">
                    <div class="col-lg-6 offset-lg-3 col-xs-6">
                        <slot></slot>
                    </div>
                </div>
            </div>
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
