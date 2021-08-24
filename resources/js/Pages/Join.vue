<template>
    <app-layout>
        <div class="container">
            <div class="row">
                <div class="col-12 mb-3">
                    <div v-if="!code">Scan a code</div>
                    <qrcode-stream v-if="!code" @decode="onDecode" @init="onInit"></qrcode-stream>
                    <label class="form-label">Invite code</label>
                    <input type="text" v-model="code" class="form-control" id="code" placeholder="Code here...">
                    <button class="mt-2 btn btn-primary" @click="join">Join with code</button>
                    <div v-if="error" class="text-danger">{{ error }}</div>
                </div>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from '@/Layouts/AppLayout'
import VueQrCode from "vue3-qrcode";
import {
    QrcodeStream,
} from "qrcode-reader-vue3";

export default {
    components: {
        AppLayout,
        VueQrCode,
        QrcodeStream
    },
    props: ['error', 'code'],
    methods: {
        join() {
            this.$inertia.post('/join', {code: this.code})
        },
        onDecode(decodeString) {
            this.$inertia.post('/join', {code: decodeString})
        },
        async onInit(promise) {
            try {
                await promise;
            } catch (error) {
                if (error.name === "NotAllowedError") {
                    this.error = "ERROR: you need to grant camera access permisson";
                } else if (error.name === "NotFoundError") {
                    this.error = "ERROR: no camera on this device";
                } else if (error.name === "NotSupportedError") {
                    this.error = "ERROR: secure context required (HTTPS, localhost)";
                } else if (error.name === "NotReadableError") {
                    this.error = "ERROR: is the camera already in use?";
                } else if (error.name === "OverconstrainedError") {
                    this.error = "ERROR: installed cameras are not suitable";
                } else if (error.name === "StreamApiNotSupportedError") {
                    this.error = "ERROR: Stream API is not supported in this browser";
                }
            }
        },
    },
};
</script>
