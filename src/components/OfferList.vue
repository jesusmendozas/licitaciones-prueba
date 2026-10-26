<template>
  <section class="pagina">
    <header class="pagina-encabezado">
      <div>
        <p class="ceja">Módulo de licitaciones</p>
        <h1 class="titulo-punto">Ofertas</h1>
        <p class="apagado">Administra y consulta las ofertas registradas.</p>
      </div>
      <a class="btn btn-primary" href="#/crear"><app-icon name="plus" :size="18" /> Nueva oferta</a>
    </header>
    <div v-if="mensaje" class="alert alert-danger mt-4">{{ mensaje }}</div>
    <section class="tarjeta tarjeta-tabla">
      <div class="tabla-cabeza">
        <div>
          <h2>Listado de ofertas</h2>
          <p>Consulta las licitaciones registradas y entra a su detalle.</p>
        </div>
        <button class="btn btn-outline-primary" type="button" :disabled="exportando" @click="exportExcel">
          <app-icon name="download" /> {{ exportando ? "Generando..." : "Exportar Excel" }}
        </button>
      </div>
      <form class="filtros" @submit.prevent="applyFilters">
        <label class="filtro">
          <span>Consecutivo</span>
          <span class="caja-busqueda">
            <app-icon name="search" />
            <input v-model="filtros.consecutivo" placeholder="Ej. PO-0001-26" maxlength="150">
          </span>
        </label>
        <label class="filtro">
          <span>Objeto</span>
          <span class="caja-busqueda">
            <app-icon name="search" />
            <input v-model="filtros.objeto" placeholder="Buscar por objeto" maxlength="150">
          </span>
        </label>
        <label class="filtro">
          <span>Descripción</span>
          <span class="caja-busqueda">
            <app-icon name="search" />
            <input v-model="filtros.descripcion" placeholder="Buscar por descripción" maxlength="150">
          </span>
        </label>
        <div class="filtros-acciones">
          <button class="btn btn-primary" type="submit">Buscar</button>
          <button class="btn btn-light" type="button" @click="clearFilters">Limpiar</button>
        </div>
      </form>
      <div v-if="cargando" class="vacio">Cargando ofertas...</div>
      <div v-else-if="filas.length === 0" class="vacio">
        <app-icon name="clipboard" :size="26" />
        <strong>No hay ofertas para mostrar.</strong>
        <span>Crea una oferta o ajusta los filtros.</span>
      </div>
      <div v-else class="table-responsive">
        <table class="table tabla-ofertas mb-0">
          <thead>
            <tr>
              <th>Consecutivo</th>
              <th>Objeto</th>
              <th>Descripción</th>
              <th>Fecha de inicio</th>
              <th>Fecha de cierre</th>
              <th>Estado</th>
              <th>Acciones</th>
            </tr>
          </thead>
          <tbody>
            <tr v-for="fila in filas" :key="fila.id">
              <td><a class="consecutivo" :href="'#/ofertas/' + fila.id">{{ fila.consecutivo }}</a></td>
              <td class="celda-objeto">{{ fila.objeto }}</td>
              <td class="celda-descripcion" :title="fila.descripcion">{{ fila.descripcion }}</td>
              <td>{{ fila.fecha_inicio }}</td>
              <td>{{ fila.fecha_cierre }}</td>
              <td><span class="estado" :class="statusClass(fila.estado)"><span class="estado-punto"></span>{{ statusLabel(fila.estado) }}</span></td>
              <td><a class="btn btn-sm btn-outline-primary" :href="'#/ofertas/' + fila.id"><app-icon name="eye" :size="15" /> Ver detalle</a></td>
            </tr>
          </tbody>
        </table>
      </div>
      <div v-if="!cargando && filas.length > 0" class="tabla-pie">
        <span>Mostrando <strong>{{ desde }}–{{ hasta }}</strong> de <strong>{{ meta.total }}</strong> ofertas</span>
        <nav class="paginacion" aria-label="Paginación">
          <button type="button" aria-label="Página anterior" :disabled="meta.pagina === 1" @click="goTo(meta.pagina - 1)"><app-icon name="chevronLeft" /></button>
          <button
            v-for="numero in paginasVisibles"
            :key="numero"
            type="button"
            :class="{ actual: numero === meta.pagina }"
            @click="goTo(numero)"
          >{{ numero }}</button>
          <button type="button" aria-label="Página siguiente" :disabled="meta.pagina === meta.paginas" @click="goTo(meta.pagina + 1)"><app-icon name="chevronRight" /></button>
        </nav>
      </div>
    </section>
  </section>
</template>

<script>
/**
 * Listado. Las peticiones van con Axios, como pide el enunciado para el consumo de la API.
 * Filtra por consecutivo, objeto o descripción, pagina de 10 en 10
 * y exporta el Excel del filtro completo. El Excel lo genera el servidor:
 * el navegador solo recibe el archivo.
 */
import axios from "axios";
import AppIcon from "./AppIcon.vue";
import { statusClass, statusLabel, errorMessage } from "../helpers";

export default {
  name: "OfferList",
  components: { AppIcon },
  data() {
    return {
      filtros: { consecutivo: "", objeto: "", descripcion: "" },
      aplicados: { consecutivo: "", objeto: "", descripcion: "" },
      filas: [],
      meta: { pagina: 1, por_pagina: 10, total: 0, paginas: 1 },
      cargando: false,
      exportando: false,
      mensaje: "",
    };
  },
  computed: {
    desde() {
      return (this.meta.pagina - 1) * this.meta.por_pagina + 1;
    },
    hasta() {
      return this.desde + this.filas.length - 1;
    },
    paginasVisibles() {
      const total = Math.max(1, this.meta.paginas);
      const inicio = Math.max(1, Math.min(this.meta.pagina - 2, total - 4));
      const fin = Math.min(total, inicio + 4);
      const paginas = [];
      for (let numero = inicio; numero <= fin; numero += 1) paginas.push(numero);
      return paginas;
    },
  },
  created() {
    this.load();
  },
  methods: {
    statusClass,
    statusLabel,
    load() {
      this.cargando = true;
      this.mensaje = "";
      axios.get("/api/offers", {
        params: {
          consecutivo: this.aplicados.consecutivo,
          objeto: this.aplicados.objeto,
          descripcion: this.aplicados.descripcion,
          pagina: this.meta.pagina,
        },
      }).then((respuesta) => {
        this.filas = respuesta.data.data || [];
        this.meta = respuesta.data.meta;
        this.cargando = false;
      }).catch((error) => {
        this.mensaje = errorMessage(error, "No se pudo cargar el listado.");
        this.cargando = false;
      });
    },
    filtersBlank(filtros) {
      return filtros.consecutivo.trim() === ""
        && filtros.objeto.trim() === ""
        && filtros.descripcion.trim() === "";
    },
    applyFilters() {
      if (this.filtersBlank(this.filtros)) return;
      this.aplicados = {
        consecutivo: this.filtros.consecutivo.trim(),
        objeto: this.filtros.objeto.trim(),
        descripcion: this.filtros.descripcion.trim(),
      };
      this.meta.pagina = 1;
      this.load();
    },
    clearFilters() {
      const escritos = !this.filtersBlank(this.filtros);
      const aplicados = !this.filtersBlank(this.aplicados);
      if (!escritos && !aplicados) return;
      this.filtros = { consecutivo: "", objeto: "", descripcion: "" };
      if (!aplicados) return;
      this.aplicados = { consecutivo: "", objeto: "", descripcion: "" };
      this.meta.pagina = 1;
      this.load();
    },
    goTo(pagina) {
      if (pagina < 1 || pagina > this.meta.paginas || pagina === this.meta.pagina) return;
      this.meta.pagina = pagina;
      this.load();
    },
    exportExcel() {
      this.exportando = true;
      axios.get("/api/offers/excel", { params: this.aplicados, responseType: "blob" }).then((respuesta) => {
        const tipo = respuesta.headers["content-type"] || "";
        if (tipo.indexOf("application/json") !== -1) {
          this.mensaje = "No se pudo generar el Excel.";
          this.exportando = false;
          return;
        }
        const url = window.URL.createObjectURL(respuesta.data);
        const enlace = document.createElement("a");
        enlace.href = url;
        enlace.download = "ofertas.xlsx";
        document.body.appendChild(enlace);
        enlace.click();
        enlace.remove();
        window.URL.revokeObjectURL(url);
        this.exportando = false;
      }).catch((error) => {
        const data = error.response && error.response.data;
        if (!(data instanceof Blob)) {
          this.mensaje = errorMessage(error, "No se pudo generar el Excel.");
          this.exportando = false;
          return;
        }
        data.text().then((texto) => {
          let mensaje = "No se pudo generar el Excel.";
          try {
            const json = JSON.parse(texto);
            if (json && json.mensaje) mensaje = json.mensaje;
          } catch (e) {
            mensaje = "No se pudo generar el Excel.";
          }
          this.mensaje = mensaje;
          this.exportando = false;
        }).catch(() => {
          this.mensaje = "No se pudo generar el Excel.";
          this.exportando = false;
        });
      });
    },
  },
};
</script>
