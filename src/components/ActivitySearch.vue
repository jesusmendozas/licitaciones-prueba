<template>
  <div class="buscador">
    <div v-if="seleccion && !editando" ref="elegida" class="actividad-elegida">
      <span class="actividad-codigo">{{ seleccion.codigo_producto }}</span>
      <span class="actividad-nombre">{{ seleccion.producto }}</span>
      <button class="actividad-cambiar" type="button" @click="startEdit">Cambiar</button>
    </div>
    <div v-else class="selector-actividad">
      <input
        ref="entrada"
        class="form-control"
        type="text"
        :value="texto"
        placeholder="Selecciona una actividad"
        autocomplete="off"
        role="combobox"
        aria-autocomplete="list"
        :aria-expanded="listaVisible ? 'true' : 'false'"
        @input="onType"
        @focus="onFocus"
        @blur="onBlur"
        @keydown.esc.prevent="close"
        @keydown.down.prevent="move(1)"
        @keydown.up.prevent="move(-1)"
        @keydown.enter.prevent="confirmActive"
      >
      <span class="selector-flecha" aria-hidden="true"></span>
    </div>
    <ul v-if="listaVisible" class="resultados-actividad" :style="estilo" role="listbox" @mousedown="keepFocus">
      <li v-if="buscando" class="resultado-estado">Buscando...</li>
      <li v-else-if="texto.trim().length === 1" class="resultado-estado">Escribe al menos 2 caracteres.</li>
      <li v-else-if="fallo" class="resultado-estado">No se pudo buscar las actividades.</li>
      <li v-else-if="resultados.length === 0" class="resultado-estado">Sin coincidencias.</li>
      <template v-else>
        <li class="resultado-encabezado">
          <span>Código</span>
          <span>Producto</span>
          <span>Familia</span>
        </li>
        <li v-for="(item, indice) in resultados" :key="item.id">
          <button
            type="button"
            class="resultado-item"
            :class="{ activo: indice === resaltado }"
            @mousedown.prevent="choose(item)"
          >
            <span class="actividad-codigo">{{ item.codigo_producto }}</span>
            <strong>{{ item.producto }}</strong>
            <span>{{ item.familia }}</span>
          </button>
        </li>
      </template>
    </ul>
  </div>
</template>

<script>
/**
 * Buscador de la actividad UNSPSC. Espera a que la persona deje de escribir
 * y pide como máximo 20 coincidencias.
 */
import axios from "axios";
import { debounce } from "../helpers";

export default {
  name: "ActivitySearch",
  props: {
    value: { type: [String, Number], default: "" },
    inicial: { type: Object, default: null },
  },
  data() {
    return {
      texto: "",
      resultados: [],
      seleccion: null,
      editando: false,
      abierto: false,
      buscando: false,
      resaltado: -1,
      fallo: false,
      estilo: {},
      token: 0,
      cierre: null,
    };
  },
  computed: {
    listaVisible() {
      return this.abierto;
    },
  },
  watch: {
    inicial: {
      immediate: true,
      handler(valor) {
        if (!valor || !valor.id) return;
        this.seleccion = valor;
        this.editando = false;
        this.texto = "";
        this.$emit("input", String(valor.id));
      },
    },
    listaVisible(visible) {
      if (visible) this.$nextTick(this.place);
    },
  },
  created() {
    this.searchDebounced = debounce(() => this.runSearch(), 300);
    this.alPulsarFuera = (evento) => {
      if (!this.abierto) return;
      if (this.$el.contains(evento.target)) return;
      this.close();
    };
    this.alMover = () => {
      if (this.listaVisible) this.place();
    };
  },
  mounted() {
    document.addEventListener("pointerdown", this.alPulsarFuera);
    window.addEventListener("resize", this.alMover);
    window.addEventListener("scroll", this.alMover, true);
    if (this.value && !this.seleccion) this.$emit("input", "");
  },
  beforeDestroy() {
    document.removeEventListener("pointerdown", this.alPulsarFuera);
    window.removeEventListener("resize", this.alMover);
    window.removeEventListener("scroll", this.alMover, true);
    clearTimeout(this.cierre);
  },
  methods: {
    onFocus() {
      clearTimeout(this.cierre);
      this.abierto = true;
      const termino = this.texto.trim();
      if (termino.length === 1) return;
      if (this.resultados.length === 0) this.buscando = true;
      if (termino.length === 0) {
        this.runSearch();
        return;
      }
      this.searchDebounced();
    },
    onBlur() {
      clearTimeout(this.cierre);
      this.cierre = setTimeout(() => {
        if (this.$el.contains(document.activeElement)) return;
        this.close();
      }, 120);
    },
    onType(evento) {
      this.texto = evento.target.value;
      this.resaltado = -1;
      this.abierto = true;
      this.$emit("input", "");
      const termino = this.texto.trim();
      if (termino.length === 1) {
        this.token += 1;
        this.resultados = [];
        this.buscando = false;
        return;
      }
      this.buscando = true;
      if (termino.length === 0) {
        this.runSearch();
        return;
      }
      this.searchDebounced();
    },
    runSearch() {
      const termino = this.texto.trim();
      if (termino.length === 1) {
        this.resultados = [];
        this.buscando = false;
        return;
      }
      this.token += 1;
      const token = this.token;
      this.buscando = true;
      this.fallo = false;
      axios.get("/api/activities", { params: termino === "" ? {} : { q: termino } }).then((respuesta) => {
        if (token !== this.token) return;
        this.resultados = respuesta.data.data || [];
        this.fallo = false;
        this.buscando = false;
        this.resaltado = this.resultados.length ? 0 : -1;
        this.$nextTick(this.place);
      }).catch(() => {
        if (token !== this.token) return;
        this.resultados = [];
        this.fallo = true;
        this.buscando = false;
        this.resaltado = -1;
      });
    },
    choose(item) {
      clearTimeout(this.cierre);
      this.seleccion = item;
      this.texto = "";
      this.resultados = [];
      this.editando = false;
      this.abierto = false;
      this.resaltado = -1;
      this.$emit("input", String(item.id));
    },
    startEdit() {
      this.editando = true;
      this.texto = "";
      this.resultados = [];
      this.abierto = true;
      this.$nextTick(() => {
        if (this.$refs.entrada) this.$refs.entrada.focus();
      });
    },
    close() {
      clearTimeout(this.cierre);
      this.abierto = false;
      this.resaltado = -1;
      this.buscando = false;
      if (this.texto.trim() !== "") return;
      this.resultados = [];
      if (!this.seleccion) return;
      this.editando = false;
      this.$emit("input", String(this.seleccion.id));
    },
    keepFocus(evento) {
      if (evento.target.closest("button")) evento.preventDefault();
    },
    move(delta) {
      if (!this.resultados.length) return;
      this.abierto = true;
      const total = this.resultados.length;
      this.resaltado = (this.resaltado + delta + total) % total;
      this.$nextTick(() => {
        const activo = this.$el.querySelector(".resultado-item.activo");
        if (activo && activo.scrollIntoView) activo.scrollIntoView({ block: "nearest" });
      });
    },
    confirmActive() {
      if (this.resaltado < 0 || !this.resultados[this.resaltado]) return;
      this.choose(this.resultados[this.resaltado]);
    },
    place() {
      const ancla = this.$refs.entrada || this.$refs.elegida;
      if (!ancla) return;
      const rect = ancla.getBoundingClientRect();
      const margen = 12;
      const ancho = Math.min(Math.max(rect.width, 220), window.innerWidth - margen * 2);
      let left = rect.left;
      if (left + ancho > window.innerWidth - margen) left = window.innerWidth - margen - ancho;
      if (left < margen) left = margen;
      const abajo = window.innerHeight - rect.bottom - margen;
      const arriba = rect.top - margen;
      const abrirArriba = abajo < 180 && arriba > abajo;
      const alto = Math.max(96, Math.min(320, (abrirArriba ? arriba : abajo) - 8));
      this.estilo = {
        left: left + "px",
        width: ancho + "px",
        maxHeight: alto + "px",
        top: abrirArriba ? "auto" : rect.bottom + 6 + "px",
        bottom: abrirArriba ? window.innerHeight - rect.top + 6 + "px" : "auto",
      };
    },
  },
};
</script>
