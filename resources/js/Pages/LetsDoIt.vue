<template>
  <div
    class="
      relative
      flex
      items-top
      justify-center
      min-h-screen
      bg-gray-100
      dark:bg-gray-900
      sm:items-center
      sm:pt-0
    "
  >
    <div class="max-w-6xl mx-auto sm:px-6 lg:px-8">
      <inertia-link :href="route('lets-do-it')"> Invite </inertia-link>
      <div>{{ inviteUuid }}</div>
      <div>{{ result }}</div>
      <form>
        <input v-model="inviteCode" placeholder="Invite code" type="text"/>
        <inertia-link :href="route('flip-create')" :data="{invitationCode: inviteCode}" method="post" as="button" type="button">Flip</inertia-link>
      </form>

      <!--<vue-qr-code :value="inviteUuid" />
      <qrcode-stream @decode="onDecode" @init="onInit"></qrcode-stream>
      -->
    </div>
  </div>
</template>

<script>
import VueQrCode from "vue3-qrcode";
import {
  QrcodeStream,
} from "qrcode-reader-vue3";

export default {
  components: {
    VueQrCode,
    QrcodeStream
  },
  data: function() {
      return {
        result: ''
    }
  },
  props: ["inviteUuid", 'inviteCode'],
  methods: {
      submit() {
          console.log('hehep');
      },
    onDecode(decodeString) {
      console.log("hii", decodeString);
      this.result = decodeString
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
