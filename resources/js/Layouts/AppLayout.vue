<template>
    <div>
        <!-- Page Content -->
        <div class="container">
            <nav class="navbar navbar-expand-lg navbar-light bg-light text-center">
                <div class="container-fluid text-center">
                    <a class="navbar-brand" href="#">Flipper</a>
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
