<template>
    <div>
        <!-- Page Content -->
        <div>
            <nav class="navbar-light bg-light">
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
