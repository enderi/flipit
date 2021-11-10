<template>
    <app-layout
        :mini="true">
        <div class="row text-center">
            <div class="col-12">
                Scan the code with this app <br>or send <a :href="invitationUrl" target="_blank">direct link</a><br/>
                <button @click="alone" class="btn btn-link">Or play alone..</button><br/>
                <vue-qr-code style="max-width: 300px; width: 90%" :value="invitationUrl"/>
            </div>
        </div>
    </app-layout>
</template>

<script>
import AppLayout from "@/Layouts/AppLayout";
import VueQrCode from "vue3-qrcode";

export default {
    components: {
        AppLayout,
        VueQrCode
    },
    data: function() {
        return {
            sharing: true
        }
    },
    props: ["gameUuid", "playerUuid", "invitationUrl"],
    mounted() {
        console.log(this.invitationUrl)
        Echo.channel("game." + this.gameUuid)
            .listen("PlayerJoined", (e) => {
                if(e && e.message === 'ALL_JOINED'){
                    this.$inertia.get('/game/' + this.gameUuid + '/player/' + this.playerUuid)
                }
                if(e && e.message === 'IN_THE_LOBBY') {
                    this.$wkToast('Player in the lobby..', {
                        horizontalPosition: 'center',
                        verticalPosition: 'bottom'
                    });
                    this.sharing = false
                }
        });
    },
    unmounted() {
        Echo.leave('/game/' + this.gameUuid + '/player/' + this.playerUuid);
    },
    methods: {
        initialize() {
            this.placeHolders = this.buildPlaceHolders();
            this.cardsDealt = 0;
        },
        alone() {
            this.$inertia.post('/game/' + this.gameUuid + '/player/' + this.playerUuid + '/play-alone');
        }
    }
};
</script>
