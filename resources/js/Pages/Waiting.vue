<template>
    <div class="container">
>        <div class="row text-center">
            <div class="col-12" v-if="cpde">
                {{url}}
                Scan the below code with app or send <a :href="url" target="_blank">direct link</a><br/>
                <vue-qr-code style="width: 80%" :value="uuid"/>
            </div>
        </div>
    </div>
</template>

<script>
import VueQrCode from "vue3-qrcode";

export default {
    components: {
        VueQrCode
    },
    props: ["uuid", "url"],
    methods: {
    },
    mounted() {
        console.log('gii', this.url, this.uuid)
    }
};
</script>
