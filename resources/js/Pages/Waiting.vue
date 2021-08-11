<template>
    <div class="container">
        <div class="row text-center">
            <div class="col-12">
                Scan a code with app or send <a :href="url" target="_blank">direct link</a><br/>
                <vue-qr-code style="width: 80%" :value="code"/>
                <br>
            </div>
        </div>
    </div>
</template>

<style scoped>
.bottom-row {
    position: absolute;
    bottom: 0;

}

.table {
    background-color: darkgreen;
}

.winner {
    background-image: linear-gradient(to right, white, lightgreen, lightgreen, lightgray, white);
}
</style>

<script>
import VueQrCode from "vue3-qrcode";

export default {
    components: {
        VueQrCode
    },
    props: ["code", "url"],
    methods: {

    },
};
</script>
