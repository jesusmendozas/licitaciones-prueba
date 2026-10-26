<template>
  <div class="aplicacion">
    <header class="barra-superior">
      <a class="marca" href="#/">
        <span class="marca-titulo">Licitaciones</span>
      </a>
      <nav class="migas" aria-label="Ruta">
        <a v-if="ruta.nombre !== 'listado'" href="#/">Ofertas</a>
        <strong v-else>Ofertas</strong>
        <template v-if="miga">
          <app-icon name="chevronRight" :size="14" />
          <strong>{{ miga }}</strong>
        </template>
      </nav>
    </header>
    <main class="contenido">
      <offer-list v-if="ruta.nombre === 'listado'" />
      <offer-form v-if="ruta.nombre === 'crear'" modo="crear" />
      <offer-form
        v-if="ruta.nombre === 'editar'"
        modo="editar"
        :oferta-id="ruta.id"
        :key="'editar-' + ruta.id"
      />
      <offer-detail
        v-if="ruta.nombre === 'detalle'"
        :oferta-id="ruta.id"
        :key="'detalle-' + ruta.id"
      />
    </main>
  </div>
</template>

<script>
/**
 * Vista raíz. No pide datos: cambia de pantalla con el hash
 * (#/, #/crear, #/ofertas/1, #/ofertas/1/editar).
 */
import { currentRoute } from "./helpers";
import AppIcon from "./components/AppIcon.vue";
import OfferList from "./components/OfferList.vue";
import OfferForm from "./components/OfferForm.vue";
import OfferDetail from "./components/OfferDetail.vue";

export default {
  name: "App",
  components: {
    AppIcon,
    OfferList,
    OfferForm,
    OfferDetail,
  },
  data() {
    return { ruta: currentRoute() };
  },
  computed: {
    miga() {
      if (this.ruta.nombre === "crear") return "Nueva oferta";
      if (this.ruta.nombre === "editar") return "Editar oferta";
      if (this.ruta.nombre === "detalle") return "Detalle";
      return "";
    },
  },
  created() {
    window.addEventListener("hashchange", () => {
      this.ruta = currentRoute();
      window.scrollTo(0, 0);
    });
  },
};
</script>
