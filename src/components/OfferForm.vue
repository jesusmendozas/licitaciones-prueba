<template>
  <section class="pagina">
    <div class="formulario-encabezado">
      <a class="volver" :href="volverA"><app-icon name="arrowLeft" :size="18" /> {{ modo === "crear" ? "Volver al listado" : "Volver al detalle" }}</a>
      <div class="encabezado-fila">
        <div>
          <p class="ceja">{{ modo === "crear" ? "Nueva oferta" : "Editar oferta" }}</p>
          <h1>{{ modo === "crear" ? "Crear licitación" : "Editar licitación" }}</h1>
          <p class="apagado">{{ modo === "crear" ? "Completa la información para registrar una nueva oferta." : "Actualiza la información y adjunta los documentos de soporte." }}</p>
        </div>
        <div class="encabezado-acciones">
          <a class="btn btn-light" :href="volverA">Cancelar</a>
          <button class="btn btn-primary" type="submit" form="formulario-oferta" :disabled="guardando || cargando">
            <app-icon name="plus" :size="17" /> {{ guardando ? "Guardando..." : "Guardar oferta" }}
          </button>
        </div>
      </div>
    </div>
    <div v-if="mensaje" class="alert alert-danger">{{ mensaje }}</div>
    <div v-if="cargando" class="tarjeta vacio">Cargando oferta...</div>
    <form v-else id="formulario-oferta" novalidate @submit.prevent="save">
      <div class="formulario-principal">
        <div v-if="!editable" class="alert alert-warning mb-0">Solo se puede editar una oferta activa. El estado actual es {{ statusLabel(estado) }}.</div>
        <fieldset :disabled="!editable" class="formulario-campos">
          <section class="tarjeta">
            <div class="tarjeta-titulo">
              <span class="tarjeta-icono"><app-icon name="clipboard" :size="17" /></span>
              <div>
                <h2>Información básica</h2>
                <p>Describe el objeto y el alcance de la licitación.</p>
              </div>
            </div>
            <div class="row g-3">
              <div class="col-12 campo">
                <label class="form-label" for="campo-objeto">Objeto *</label>
                <input id="campo-objeto" v-model="form.objeto" class="form-control" maxlength="150" placeholder="Ej. Suministro de equipos de cómputo">
                <div class="campo-pie">
                  <span class="campo-error">{{ errores.objeto }}</span>
                  <small>{{ form.objeto.length }} / 150 caracteres</small>
                </div>
              </div>
              <div class="col-12 campo">
                <label class="form-label" for="campo-descripcion">Descripción / alcance *</label>
                <textarea id="campo-descripcion" v-model="form.descripcion" class="form-control" rows="4" maxlength="400" placeholder="Describe brevemente los bienes o servicios requeridos..."></textarea>
                <div class="campo-pie">
                  <span class="campo-error">{{ errores.descripcion }}</span>
                  <small>{{ form.descripcion.length }} / 400 caracteres</small>
                </div>
              </div>
              <div class="col-md-6 campo">
                <label class="form-label" for="campo-moneda">Moneda *</label>
                <select id="campo-moneda" v-model="form.moneda" class="form-select">
                  <option value="">Selecciona</option>
                  <option value="COP">COP — Peso colombiano</option>
                  <option value="USD">USD — Dólar estadounidense</option>
                  <option value="EUR">EUR — Euro</option>
                </select>
                <span class="campo-error">{{ errores.moneda }}</span>
              </div>
              <div class="col-md-6 campo">
                <label class="form-label" for="campo-presupuesto">Presupuesto *</label>
                <div class="con-prefijo">
                  <b>{{ simboloMoneda }}</b>
                  <input id="campo-presupuesto" class="form-control" inputmode="decimal" placeholder="0,00" :value="presupuestoTexto" @input="filterBudget" @blur="finishBudget">
                </div>
                <span class="campo-error">{{ errores.presupuesto }}</span>
              </div>
              <div class="col-12 campo">
                <label class="form-label">Actividad / clasificación UNSPSC *</label>
                <activity-search v-model="form.actividad_id" :inicial="actividadInicial" />
                <span class="campo-error">{{ errores.actividad_id }}</span>
              </div>
            </div>
          </section>

          <section class="tarjeta">
            <div class="tarjeta-titulo">
              <span class="tarjeta-icono"><app-icon name="calendar" :size="17" /></span>
              <div>
                <h2>Cronograma</h2>
                <p>Define las fechas y horas en las que estará vigente la oferta.</p>
              </div>
            </div>
            <div class="cronograma">
              <div>
                <p class="subtitulo">Inicio de la oferta</p>
                <div class="row g-2">
                  <div class="col-sm-6 campo">
                    <label class="form-label" for="campo-fecha-inicio">Fecha de inicio *</label>
                    <input id="campo-fecha-inicio" v-model="form.fecha_inicio" class="form-control" type="date">
                    <span class="campo-error">{{ errores.fecha_inicio }}</span>
                  </div>
                  <div class="col-sm-6 campo">
                    <label class="form-label" for="campo-hora-inicio">Hora de inicio *</label>
                    <input id="campo-hora-inicio" v-model="form.hora_inicio" class="form-control" type="time" step="60">
                    <span class="campo-error">{{ errores.hora_inicio }}</span>
                  </div>
                </div>
              </div>
              <div class="cronograma-linea" aria-hidden="true"></div>
              <div>
                <p class="subtitulo">Cierre de la oferta</p>
                <div class="row g-2">
                  <div class="col-sm-6 campo">
                    <label class="form-label" for="campo-fecha-cierre">Fecha de cierre *</label>
                    <input id="campo-fecha-cierre" v-model="form.fecha_cierre" class="form-control" type="date">
                    <span class="campo-error">{{ errores.fecha_cierre }}</span>
                  </div>
                  <div class="col-sm-6 campo">
                    <label class="form-label" for="campo-hora-cierre">Hora de cierre *</label>
                    <input id="campo-hora-cierre" v-model="form.hora_cierre" class="form-control" type="time" step="60">
                    <span class="campo-error">{{ errores.hora_cierre }}</span>
                  </div>
                </div>
              </div>
            </div>
          </section>

          <section v-if="modo === 'editar'" class="tarjeta">
            <div class="tarjeta-titulo">
              <span class="tarjeta-icono"><app-icon name="file" :size="17" /></span>
              <div>
                <h2>Documentos</h2>
                <p>Adjunta documentos de soporte en formato PDF o ZIP.</p>
              </div>
              <button class="btn btn-outline-primary btn-sm ms-auto" type="button" @click="openModal"><app-icon name="plus" /> Agregar documento</button>
            </div>
            <button v-if="documentosExistentes.length + documentosNuevos.length === 0" class="zona-carga" type="button" @click="openModal">
              <app-icon name="upload" :size="25" />
              <strong>Agrega el primer documento</strong>
              <span>Haz clic para diligenciarlo · PDF o ZIP hasta 10 MB</span>
            </button>
            <div v-else class="lista-documentos">
              <div v-for="documento in documentosExistentes" :key="'g-' + documento.id" class="documento documento-abrir" @click="abrirDocumento(documento)">
                <app-icon name="file" :size="21" />
                <div>
                  <strong>{{ documento.titulo }}</strong>
                  <small>{{ documento.descripcion }}</small>
                  <button type="button" class="documento-ver" @click.stop="abrirDocumento(documento)">{{ documento.nombre }}</button>
                </div>
              </div>
              <div v-for="(documento, indice) in documentosNuevos" :key="'n-' + indice" class="documento documento-nuevo documento-abrir" @click="abrirDocumento(documento)">
                <app-icon name="file" :size="21" />
                <div>
                  <strong>{{ documento.titulo }}</strong>
                  <small>{{ documento.descripcion }}</small>
                  <button type="button" class="documento-ver" @click.stop="abrirDocumento(documento)">{{ documento.nombre }}</button>
                  <span class="documento-archivo">se sube al guardar</span>
                </div>
                <button type="button" aria-label="Quitar documento" @click.stop="removeNew(indice)"><app-icon name="close" /></button>
              </div>
            </div>
            <span class="campo-error d-block mt-2">{{ errores.documentos }}</span>
          </section>
        </fieldset>
      </div>
    </form>

    <document-viewer
      v-if="visor"
      :titulo="visor.titulo"
      :nombre="visor.nombre"
      :vista="visor.vista"
      :descarga="visor.descarga"
      :pdf="visor.pdf"
      @cerrar="cerrarVisor"
    />

    <div v-if="modal.abierto" class="ventana-fondo" @click.self="closeModal">
      <div class="ventana" role="dialog" aria-modal="true" aria-labelledby="titulo-documento">
        <div class="ventana-cabeza">
          <div>
            <h2 id="titulo-documento">Agregar documento</h2>
            <p>Adjunta un soporte a la licitación.</p>
          </div>
          <button type="button" aria-label="Cerrar" @click="closeModal"><app-icon name="close" :size="19" /></button>
        </div>
        <div class="campo">
          <label class="form-label" for="documento-titulo">Título del documento *</label>
          <input id="documento-titulo" v-model="modal.titulo" class="form-control" maxlength="100" placeholder="Ej. Pliego de condiciones">
          <div class="campo-pie"><span></span><small>{{ modal.titulo.length }} / 100 caracteres</small></div>
        </div>
        <div class="campo">
          <label class="form-label" for="documento-descripcion">Descripción *</label>
          <textarea id="documento-descripcion" v-model="modal.descripcion" class="form-control" rows="3" maxlength="200" placeholder="Describe el contenido del documento..."></textarea>
          <div class="campo-pie"><span></span><small>{{ modal.descripcion.length }} / 200 caracteres</small></div>
        </div>
        <label class="selector-archivo">
          <app-icon name="upload" :size="22" />
          <span>
            <strong>{{ modal.nombre || "Seleccionar archivo" }}</strong>
            <small>PDF o ZIP · máximo 10 MB</small>
          </span>
          <input type="file" accept=".pdf,.zip,application/pdf,application/zip" @change="chooseFile">
        </label>
        <span v-if="errorArchivo" class="campo-error">{{ errorArchivo }}</span>
        <div class="ventana-acciones">
          <button class="btn btn-light" type="button" @click="closeModal">Cancelar</button>
          <button class="btn btn-primary" type="button" :disabled="!modalValido" @click="addDocument">Agregar</button>
        </div>
      </div>
    </div>
  </section>
</template>

<script>
/**
 * Alta y edición. Valida en la vista y vuelve a enviar todo con Axios.
 * Los documentos solo se piden al editar, y el servidor rechaza la oferta si no está activa.
 */
import axios from "axios";
import ActivitySearch from "./ActivitySearch.vue";
import AppIcon from "./AppIcon.vue";
import DocumentViewer from "./DocumentViewer.vue";
import { scheduleIsValid, emptyForm, errorMessage, budgetIsValid, formatBudget, parseBudget, textIsValid, statusLabel, timeIsValid } from "../helpers";

export default {
  name: "OfferForm",
  components: { ActivitySearch, AppIcon, DocumentViewer },
  props: {
    modo: { type: String, required: true },
    ofertaId: { type: [String, Number], default: "" },
  },
  data() {
    return {
      form: emptyForm(),
      presupuestoTexto: "",
      actividadInicial: null,
      documentosExistentes: [],
      documentosNuevos: [],
      modal: { abierto: false, titulo: "", descripcion: "", archivo: null, nombre: "" },
      errorArchivo: "",
      errores: {},
      erroresServidor: {},
      valoresServidor: {},
      mensaje: "",
      estado: "activo",
      cargando: this.modo === "editar",
      guardando: false,
      visor: null,
    };
  },
  computed: {
    volverA() {
      return this.modo === "crear" ? "#/" : "#/ofertas/" + this.ofertaId;
    },
    simboloMoneda() {
      return this.form.moneda === "EUR" ? "€" : "$";
    },
    editable() {
      return this.modo === "crear" || this.estado === "activo";
    },
    documentosCompletos() {
      if (this.modo !== "editar") return true;
      return this.documentosExistentes.length + this.documentosNuevos.length >= 1;
    },
    modalValido() {
      return textIsValid(this.modal.titulo, 100) && textIsValid(this.modal.descripcion, 200) && !!this.modal.archivo;
    },
  },
  watch: {
    form: {
      deep: true,
      handler() {
        this.refreshErrors();
      },
    },
  },
  created() {
    if (this.modo === "editar") this.load();
  },
  beforeDestroy() {
    this.cerrarVisor();
  },
  methods: {
    statusLabel,
    load() {
      axios.get("/api/offers/" + this.ofertaId).then((respuesta) => {
        const oferta = respuesta.data;
        this.estado = oferta.estado;
        this.form = {
          objeto: oferta.objeto,
          descripcion: oferta.descripcion,
          moneda: oferta.moneda,
          presupuesto: parseBudget(oferta.presupuesto),
          actividad_id: String(oferta.actividad_id),
          fecha_inicio: oferta.fecha_inicio,
          hora_inicio: oferta.hora_inicio,
          fecha_cierre: oferta.fecha_cierre,
          hora_cierre: oferta.hora_cierre,
        };
        this.presupuestoTexto = formatBudget(oferta.presupuesto);
        this.actividadInicial = oferta.actividad;
        this.documentosExistentes = oferta.documentos || [];
        this.cargando = false;
      }).catch((error) => {
        this.mensaje = errorMessage(error, "No se pudo cargar la oferta.");
        this.cargando = false;
      });
    },
    filterBudget(evento) {
      const input = evento.target;
      const digitosAntes = input.value.slice(0, input.selectionStart || 0).replace(/\D/g, "").length;
      const visible = formatBudget(input.value);
      input.value = visible;
      this.presupuestoTexto = visible;
      this.form.presupuesto = parseBudget(visible);
      let vistos = 0;
      let posicion = visible.length;
      if (digitosAntes === 0) {
        posicion = 0;
      } else {
        for (let i = 0; i < visible.length; i += 1) {
          if (/\d/.test(visible[i])) vistos += 1;
          if (vistos === digitosAntes) {
            posicion = i + 1;
            break;
          }
        }
      }
      this.$nextTick(() => input.setSelectionRange(posicion, posicion));
    },
    finishBudget() {
      const canon = parseBudget(this.presupuestoTexto);
      if (!canon) {
        this.presupuestoTexto = "";
        this.form.presupuesto = "";
        return;
      }
      const partes = canon.split(".");
      const fijo = partes[0] + "." + (partes[1] || "").padEnd(2, "0");
      this.form.presupuesto = fijo;
      this.presupuestoTexto = formatBudget(fijo);
    },
    openModal() {
      this.modal = { abierto: true, titulo: "", descripcion: "", archivo: null, nombre: "" };
      this.errorArchivo = "";
    },
    closeModal() {
      this.modal.abierto = false;
    },
    chooseFile(evento) {
      const archivo = evento.target.files && evento.target.files[0];
      this.modal.archivo = archivo || null;
      this.modal.nombre = archivo ? archivo.name : "";
      this.errorArchivo = "";
    },
    addDocument() {
      if (!this.modalValido) return;
      const nombre = this.modal.archivo.name.toLowerCase();
      if (!/\.(pdf|zip)$/.test(nombre)) {
        this.errorArchivo = "Solo se permiten archivos PDF o ZIP.";
        return;
      }
      this.documentosNuevos.push({
        titulo: this.modal.titulo.trim(),
        descripcion: this.modal.descripcion.trim(),
        archivo: this.modal.archivo,
        nombre: this.modal.archivo.name,
      });
      this.closeModal();
    },
    removeNew(indice) {
      this.documentosNuevos.splice(indice, 1);
    },
    abrirDocumento(documento) {
      this.cerrarVisor();
      if (documento.archivo instanceof File) {
        const url = URL.createObjectURL(documento.archivo);
        const pdf = documento.archivo.type === "application/pdf" || /\.pdf$/i.test(documento.nombre);
        this.visor = {
          titulo: documento.titulo,
          nombre: documento.nombre,
          pdf,
          vista: pdf ? url : "",
          descarga: url,
          local: true,
        };
        return;
      }
      const base = "/api/offers/" + this.ofertaId + "/documents/" + documento.id;
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
    save() {
      if (this.guardando) return;
      this.finishBudget();
      const errores = this.validationErrors();
      this.errores = errores;
      this.erroresServidor = {};
      if (Object.keys(errores).length > 0) {
        this.mensaje = "";
        return;
      }
      this.guardando = true;
      this.mensaje = "";
      const peticion = this.modo === "crear"
        ? axios.post("/api/offers", this.form)
        : axios.post("/api/offers/" + this.ofertaId, this.editPayload());
      peticion.then((respuesta) => {
        if (this.modo === "crear") {
          sessionStorage.setItem("ofertaCreada", respuesta.data.consecutivo);
        }
        window.location.hash = "/ofertas/" + respuesta.data.id;
      }).catch((error) => {
        const data = error.response && error.response.data;
        this.errores = (data && data.errores) || {};
        this.erroresServidor = this.errores;
        this.valoresServidor = Object.assign({}, this.form);
        this.mensaje = Object.keys(this.errores).length
          ? ""
          : ((data && data.mensaje) || "No se pudo guardar la oferta.");
        this.guardando = false;
      });
    },
    refreshErrors() {
      if (Object.keys(this.errores).length === 0) return;
      const actuales = this.validationErrors();
      const siguientes = {};
      Object.keys(this.errores).forEach((campo) => {
        if (actuales[campo]) {
          siguientes[campo] = actuales[campo];
        } else if (this.erroresServidor[campo] && this.form[campo] === this.valoresServidor[campo]) {
          siguientes[campo] = this.erroresServidor[campo];
        }
      });
      this.errores = siguientes;
    },
    validationErrors() {
      const form = this.form;
      const errores = {};
      if (!this.editable) {
        errores.estado = "Solo se puede editar una oferta activa.";
      }
      if (!textIsValid(form.objeto, 150)) {
        errores.objeto = "El objeto es obligatorio y no puede superar 150 caracteres.";
      }
      if (!textIsValid(form.descripcion, 400)) {
        errores.descripcion = "La descripción es obligatoria y no puede superar 400 caracteres.";
      }
      if (["COP", "USD", "EUR"].indexOf(form.moneda) === -1) {
        errores.moneda = "La moneda debe ser COP, USD o EUR.";
      }
      if (!budgetIsValid(form.presupuesto)) {
        errores.presupuesto = "El presupuesto debe ser mayor a 0 y tener como máximo dos decimales.";
      }
      if (!/^\d+$/.test(String(form.actividad_id || ""))) {
        errores.actividad_id = "Selecciona una actividad de la lista.";
      }
      if (!form.fecha_inicio) errores.fecha_inicio = "La fecha de inicio es obligatoria.";
      const horaInicio = this.hourError(form.hora_inicio, "hora de inicio");
      if (horaInicio) errores.hora_inicio = horaInicio;
      if (!form.fecha_cierre) errores.fecha_cierre = "La fecha de cierre es obligatoria.";
      const horaCierre = this.hourError(form.hora_cierre, "hora de cierre");
      if (horaCierre) errores.hora_cierre = horaCierre;
      if (!errores.fecha_inicio && !errores.hora_inicio && !errores.fecha_cierre && !errores.hora_cierre && !scheduleIsValid(form)) {
        errores.hora_cierre = "La fecha y hora de cierre deben ser posteriores a la fecha y hora de inicio.";
      }
      if (!this.documentosCompletos) {
        errores.documentos = "Debes agregar al menos un documento PDF o ZIP.";
      }
      return errores;
    },
    hourError(valor, etiqueta) {
      if (!valor) return "La " + etiqueta + " es obligatoria.";
      if (!timeIsValid(valor)) return "La " + etiqueta + " no es permitida. Escoja una hora válida.";
      return "";
    },
    editPayload() {
      const datos = new FormData();
      ["objeto", "descripcion", "moneda", "presupuesto", "actividad_id", "fecha_inicio", "hora_inicio", "fecha_cierre", "hora_cierre"].forEach((campo) => {
        datos.append(campo, this.form[campo]);
      });
      this.documentosNuevos.forEach((documento) => {
        datos.append("titulos[]", documento.titulo);
        datos.append("descripciones[]", documento.descripcion);
        datos.append("archivos[]", documento.archivo);
      });
      return datos;
    },
  },
};
</script>
