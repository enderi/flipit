<template>
    <div>
        <!-- Page Content -->
        <div class="container text-center">
            <nav class="navbar navbar-expand-lg navbar-light bg-light">
                <div class="container">
                    <h1 class="text-center" href="#">Flipper</h1>
                </div>
            </nav>
            <main>
                <slot></slot>
            </main>
        </div>
    </div>
</template>

<script>
    
    export default {
        data() {
            return {
            }
        },
        methods: {
        }
    }
</script>
