<template>
    <div>
        <!-- Page Content -->
        <nav v-if="!mini" class="navbar navbar-light mb-3" style="border-bottom: 1px solid black;">
            <div class="container">
                <inertia-link class="navbar-brand" :href="route('home')">STACKED</inertia-link>
                <span class="navbar-text text-lg text-primary">
                    {{subHeader}}
                </span>
            </div>
        </nav>
        <div class="navi-row mb-2 container" v-if="mini">
            <div class="row">
                <div class="col-4">
                    <a :href="route('home')" class="btn btn-link mt-1 mb-1 px-1">
                        <!-- <i fill="currentColor"
                         class="bi bi-arrow-left-circle"></i> -->
                         Home
                    </a>
                </div>
                <div class="col-8 text-primary text-end">
                    {{subHeader}}
                </div>
            </div>
        </div>
        <main>
            <div class="container-fluid">
                <div class="row">
                    <div class="col-12">
                        <slot></slot>
                    </div>
                </div>
            </div>
        </main>
    </div>
</template>

<script>

    export default {
        props: ['subHeader', 'mini'],
        data() {
            return {
            }
        },
        methods: {
        }
    }
</script>
