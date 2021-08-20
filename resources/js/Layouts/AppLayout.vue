<template>
    <div>
        <!-- Page Content -->
        <nav class="navbar navbar-dark bg-primary">
            <div class="container">
                <inertia-link class="navbar-brand" :href="route('home')">STACKED</inertia-link>
                <span class="navbar-text text-lg">
                    {{subHeader}}
                </span>
            </div>
        </nav>
        <main>
            <div class="container-fluid mt-3">
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
