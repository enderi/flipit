<template>
    <div>
        <!-- Page Content -->
        <nav class="navbar navbar-expand-sm navbar-dark bg-dark">
            <div class="container">

                <inertia-link class="navbar-brand text-center" :href="route('home')">STACKED</inertia-link>
                <span class="navbar-text">
                    {{subHeader}}
                </span>
            </div>
        </nav>
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
