<template>
  <div class="ventana-fondo" @click.self="$emit('cerrar')">
    <div class="ventana visor" role="dialog" aria-modal="true" aria-labelledby="visor-titulo">
      <div class="ventana-cabeza visor-cabeza">
        <div>
          <h2 id="visor-titulo">{{ titulo }}</h2>
          <p>{{ nombre }}</p>
        </div>
        <button type="button" data-cerrar aria-label="Cerrar" @click="$emit('cerrar')"><app-icon name="close" :size="19" /></button>
      </div>
      <div class="visor-marco">
        <iframe v-if="pdf && vista" :src="vista" :title="titulo"></iframe>
        <div v-else class="visor-aviso">
          <app-icon name="file" :size="28" />
          <strong>Este archivo no se puede mostrar aquí.</strong>
          <span>Los ZIP se descargan para abrirlos en el equipo.</span>
        </div>
      </div>
      <div class="visor-acciones">
        <button class="btn btn-light" type="button" @click="$emit('cerrar')">Cerrar</button>
        <a v-if="!pdf" class="btn btn-primary" :href="descarga" :download="nombre"><app-icon name="download" /> Descargar</a>
      </div>
    </div>
  </div>
</template>

<script>
/**
 * Ventana para ver un PDF o descargar un ZIP. El archivo local, todavía no guardado,
 * llega como una URL del navegador; el ya guardado llega como ruta de la API.
 */
import AppIcon from "./AppIcon.vue";

export default {
  name: "DocumentViewer",
  components: { AppIcon },
  props: {
    titulo: { type: String, required: true },
    nombre: { type: String, required: true },
    vista: { type: String, default: "" },
    descarga: { type: String, required: true },
    pdf: { type: Boolean, default: false },
  },
  mounted() {
    this.alTecla = (evento) => {
      if (evento.key === "Escape") this.$emit("cerrar");
    };
    document.addEventListener("keydown", this.alTecla);
    this.$nextTick(() => {
      const boton = this.$el.querySelector("[data-cerrar]");
      if (boton) boton.focus();
    });
  },
  beforeDestroy() {
    document.removeEventListener("keydown", this.alTecla);
  },
};
</script>
