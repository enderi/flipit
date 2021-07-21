<template>
  <app-layout>
    <div class="container">
      <div class="row">
        <div class="col-12 mb-3">
          <label for="input1" class="form-label">Invite code</label>
          <input type="text" v-model="code" class="form-control" id="code" placeholder="Insert code here...">
          <button class="mt-1 btn btn-primary" @click="join">Join with code</button>
        </div>
      </div>
      <!--<qrcode-stream v-if="!code" @decode="onDecode" @init="onInit"></qrcode-stream>-->
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
  data: function() {
      return {
        code: ''
    }
  },
  props: ["inviteUuid", 'inviteCode'],
  methods: {
      join() {
        this.$inertia.post('/join/' + this.code)
      },
    onDecode(decodeString) {
      this.code = decodeString
    },
    async onInit(promise) {
        console.log('initializing')
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
