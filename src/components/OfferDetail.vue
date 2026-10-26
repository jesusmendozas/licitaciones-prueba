<template>
  <section class="pagina">
    <a class="volver" href="#/"><app-icon name="arrowLeft" :size="18" /> Volver al listado</a>
    <div v-if="aviso" class="alert alert-success">{{ aviso }}</div>
    <div v-if="mensaje" class="alert alert-danger">{{ mensaje }}</div>
    <div v-if="!oferta && !mensaje" class="tarjeta vacio">Cargando oferta...</div>
    <div v-if="oferta">
      <header class="formulario-encabezado detalle-encabezado">
        <p class="ceja">Detalle de la oferta</p>
        <h1 class="detalle-titulo">
          {{ oferta.consecutivo }}
          <span class="estado" :class="statusClass(oferta.estado)"><span class="estado-punto"></span>{{ statusLabel(oferta.estado) }}</span>
        </h1>
      </header>
      <section class="tarjeta tarjeta-pestanas">
        <div class="pestanas-barra">
          <div class="pestanas" role="tablist">
            <button type="button" role="tab" :aria-selected="tab === 'basica' ? 'true' : 'false'" :class="{ activa: tab === 'basica' }" @click="tab = 'basica'">
              <app-icon name="clipboard" /> Información básica
            </button>
            <button type="button" role="tab" :aria-selected="tab === 'cronograma' ? 'true' : 'false'" :class="{ activa: tab === 'cronograma' }" @click="tab = 'cronograma'">
              <app-icon name="calendar" /> Cronograma
            </button>
            <button type="button" role="tab" :aria-selected="tab === 'documentos' ? 'true' : 'false'" :class="{ activa: tab === 'documentos' }" @click="tab = 'documentos'">
              <app-icon name="file" /> Documentos <span class="pestana-cuenta">{{ oferta.documentos.length }}</span>
            </button>
          </div>
          <a class="btn btn-primary pestana-editar" :href="'#/ofertas/' + oferta.id + '/editar'"><app-icon name="pencil" /> Editar</a>
        </div>
        <div v-show="tab === 'basica'" class="ficha">
          <div class="dato dato-ancho"><span>Objeto</span><strong>{{ oferta.objeto }}</strong></div>
          <div class="dato dato-ancho"><span>Descripción / alcance</span><strong>{{ oferta.descripcion }}</strong></div>
          <div class="dato"><span>Moneda</span><strong>{{ oferta.moneda }}</strong></div>
          <div class="dato"><span>Presupuesto</span><strong>{{ simboloMoneda(oferta.moneda) }} {{ formatBudget(oferta.presupuesto) }}</strong></div>
          <div v-if="oferta.actividad" class="dato dato-ancho">
            <span>Actividad</span>
            <strong><span class="actividad-codigo">{{ oferta.actividad.codigo_producto }}</span> {{ oferta.actividad.producto }}</strong>
            <small>{{ oferta.actividad.segmento }} / {{ oferta.actividad.familia }} / {{ oferta.actividad.clase }}</small>
          </div>
        </div>
        <div v-show="tab === 'cronograma'" class="cronograma cronograma-detalle">
          <div class="dato">
            <span>Inicio de la oferta</span>
            <strong><app-icon name="calendar" /> {{ oferta.fecha_inicio }}</strong>
            <strong><app-icon name="clock" /> {{ oferta.hora_inicio }}</strong>
          </div>
          <div class="cronograma-linea" aria-hidden="true"></div>
          <div class="dato">
            <span>Cierre de la oferta</span>
            <strong><app-icon name="calendar" /> {{ oferta.fecha_cierre }}</strong>
            <strong><app-icon name="clock" /> {{ oferta.hora_cierre }}</strong>
          </div>
        </div>
        <div v-show="tab === 'documentos'">
          <div v-if="oferta.documentos.length === 0" class="vacio">
            <app-icon name="file" :size="26" />
            <strong>Esta oferta no tiene documentos.</strong>
            <span>Los documentos se agregan desde Editar.</span>
          </div>
          <div v-else class="lista-documentos">
            <div v-for="documento in oferta.documentos" :key="documento.id" class="documento documento-abrir" @click="abrirDocumento(documento)">
              <app-icon name="file" :size="21" />
              <div>
                <strong>{{ documento.titulo }}</strong>
                <small>{{ documento.descripcion }}</small>
                <button type="button" class="documento-ver" @click.stop="abrirDocumento(documento)">{{ documento.nombre }}</button>
              </div>
              <span class="documento-fecha">{{ documento.creado_en }}</span>
            </div>
          </div>
        </div>
      </section>
    </div>
    <document-viewer
      v-if="visor"
      :titulo="visor.titulo"
      :nombre="visor.nombre"
      :vista="visor.vista"
      :descarga="visor.descarga"
      :pdf="visor.pdf"
      @cerrar="cerrarVisor"
    />
  </section>
</template>

<script>
/**
 * Detalle en pestañas: datos, cronograma y documentos.
 * El PDF se abre en un iframe; el ZIP se descarga con un enlace.
 */
import axios from "axios";
import AppIcon from "./AppIcon.vue";
import DocumentViewer from "./DocumentViewer.vue";
import { statusClass, statusLabel, errorMessage, formatBudget } from "../helpers";

export default {
  name: "OfferDetail",
  components: { AppIcon, DocumentViewer },
  props: {
    ofertaId: { type: [String, Number], required: true },
  },
  data() {
    return { oferta: null, tab: "basica", mensaje: "", aviso: "", visor: null };
  },
  created() {
    const creada = sessionStorage.getItem("ofertaCreada");
    if (creada) {
      this.aviso = "Oferta creada con consecutivo " + creada + ".";
      sessionStorage.removeItem("ofertaCreada");
    }
    this.load();
  },
  methods: {
    statusClass,
    statusLabel,
    formatBudget,
    simboloMoneda(moneda) {
      return moneda === "EUR" ? "€" : "$";
    },
    abrirDocumento(documento) {
      this.cerrarVisor();
      const base = "/api/offers/" + this.oferta.id + "/documents/" + documento.id;
      const pdf = /\.pdf$/i.test(documento.nombre);
      this.visor = {
        titulo: documento.titulo,
        nombre: documento.nombre,
        pdf,
        vista: pdf ? base + "/" + encodeURIComponent(documento.nombre) : "",
        descarga: base + "/download",
        local: false,
      };
    },
    cerrarVisor() {
      if (this.visor && this.visor.local) URL.revokeObjectURL(this.visor.descarga);
      this.visor = null;
    },
    load() {
      axios.get("/api/offers/" + this.ofertaId).then((respuesta) => {
        this.oferta = respuesta.data;
      }).catch((error) => {
        this.mensaje = errorMessage(error, "No se pudo cargar la oferta.");
      });
    },
  },
};
</script>
