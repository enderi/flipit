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
      Flippppiin
      <div>stuff: {{ params }}</div>
      <button @click="getStatus">Get status</button>
      <div>Players: {{ players }}</div>
      <div>Actions: {{ actions }}</div>
      <div v-for="action in actions">{{action}}</div>
    </div>
  </div>
</template>

<script>
export default {
  props: ["params"],
  data: function () {
    return {
      status: "",
      players: 0,
      actions: []
    };
  },
  methods: {
    getStatus() {
      axios
        .post("/api/hand-status", {
          gameUuid: this.params.game.uuid,
          playerUuid: this.params.player.uuid,
        })
        .then((resp) => {
          this.players = resp.data.players;
          this.actions = resp.data.actions
          console.log("response", resp.data);
        });
    },
  },
};
</script>
