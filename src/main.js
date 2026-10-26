/**
 * Arranque de Vue 2.7. Se usa 2.7, y no Vue 3, porque el enunciado pide Vue 2.6
 * o superior y 2.7 es el último de esa línea. Las pantallas las elige App.vue según el hash.
 */
import Vue from "vue";
import App from "./App.vue";
import "bootstrap/dist/css/bootstrap.min.css";
import "./styles/app.css";

Vue.config.productionTip = false;

new Vue({
  render: (h) => h(App),
}).$mount("#app");
