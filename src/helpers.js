/** Espera a que la persona deje de escribir antes de buscar actividades. */
export function debounce(fn, espera) {
  let timer = null;
  return function (...args) {
    const contexto = this;
    clearTimeout(timer);
    timer = setTimeout(() => fn.apply(contexto, args), espera);
  };
}

/** Texto obligatorio, sin quedar vacío al quitar espacios, y dentro del máximo. */
export function textIsValid(valor, maximo) {
  const texto = (valor || "").trim();
  return texto.length > 0 && texto.length <= maximo;
}

/** Separa la parte entera y los decimales, aceptando coma o punto. */
function budgetParts(valor) {
  const texto = String(valor || "").replace(/[^\d.,]/g, "");
  let entero = "";
  let decimal = null;

  if (texto.includes(",")) {
    const corte = texto.lastIndexOf(",");
    entero = texto.slice(0, corte).replace(/\D/g, "");
    decimal = texto.slice(corte + 1).replace(/\D/g, "").slice(0, 2);
  } else {
    const punto = texto.lastIndexOf(".");
    const unico = punto !== -1 && texto.indexOf(".") === punto;
    const despues = punto === -1 ? "" : texto.slice(punto + 1);
    if (unico && despues.length <= 2 && (despues.length > 0 || texto.endsWith("."))) {
      entero = texto.slice(0, punto).replace(/\D/g, "");
      decimal = despues;
    } else {
      entero = texto.replace(/\D/g, "");
    }
  }

  entero = entero.replace(/^0+(?=\d)/, "");
  if (entero.length > 13) entero = entero.slice(0, 13);
  if (!entero) {
    if (decimal === null || !/\d/.test(texto)) return { entero: "", decimal: null };
    entero = "0";
  }
  return { entero, decimal };
}

/** Muestra el presupuesto con puntos de miles y coma decimal. */
export function formatBudget(valor) {
  const partes = budgetParts(valor);
  if (!partes.entero) return "";
  const grupos = partes.entero.replace(/\B(?=(\d{3})+(?!\d))/g, ".");
  if (partes.decimal === null) return grupos;
  return grupos + "," + partes.decimal;
}

/** Deja el presupuesto como lo espera el servidor: punto decimal y sin separadores de miles. */
export function parseBudget(valor) {
  const partes = budgetParts(valor);
  if (!partes.entero) return "";
  if (!partes.decimal) return partes.entero;
  return partes.entero + "." + partes.decimal;
}

/** Presupuesto mayor a 0, con hasta 13 enteros y 2 decimales. */
export function budgetIsValid(valor) {
  const canon = parseBudget(valor);
  return /^\d{1,13}(\.\d{1,2})?$/.test(canon) && parseFloat(canon) > 0;
}

/** Hora en 24 horas, HH:mm o HH:mm:ss. */
export function timeIsValid(valor) {
  return /^([01]\d|2[0-3]):[0-5]\d(:[0-5]\d)?$/.test(valor || "");
}

/** El cierre del formulario tiene que ser posterior al inicio. */
export function scheduleIsValid(form) {
  const inicio = timeIsValid(form.hora_inicio) ? form.hora_inicio.slice(0, 5) : "";
  const cierre = timeIsValid(form.hora_cierre) ? form.hora_cierre.slice(0, 5) : "";
  if (!form.fecha_inicio || !inicio || !form.fecha_cierre || !cierre) {
    return false;
  }
  return (form.fecha_inicio + " " + inicio) < (form.fecha_cierre + " " + cierre);
}

/** Clase CSS del estado para el listado y el detalle. */
export function statusClass(estado) {
  if (estado === "activo") return "estado-activo";
  if (estado === "publicada") return "estado-publicada";
  return "estado-evaluacion";
}

/** Texto visible de activo, publicada y en evaluacion. */
export function statusLabel(estado) {
  if (estado === "activo") return "Activo";
  if (estado === "publicada") return "Publicada";
  if (estado === "en evaluacion") return "En evaluación";
  return estado;
}

/** Mensaje que devolvió el servidor, o el texto por defecto si la respuesta no trae uno. */
export function errorMessage(error, defecto) {
  const data = error && error.response && error.response.data;
  if (data && data.mensaje) return data.mensaje;
  return defecto;
}

/** Lee el hash y decide qué pantalla mostrar: listado, crear, editar o detalle. */
export function currentRoute() {
  const hash = window.location.hash.replace(/^#/, "");
  if (hash === "" || hash === "/") return { nombre: "listado", id: "" };
  if (hash === "/crear") return { nombre: "crear", id: "" };
  const editar = hash.match(/^\/ofertas\/(\d+)\/editar$/);
  if (editar) return { nombre: "editar", id: editar[1] };
  const detalle = hash.match(/^\/ofertas\/(\d+)$/);
  if (detalle) return { nombre: "detalle", id: detalle[1] };
  return { nombre: "listado", id: "" };
}

/** Valores iniciales del formulario de oferta. */
export function emptyForm() {
  return {
    objeto: "",
    descripcion: "",
    moneda: "",
    presupuesto: "",
    actividad_id: "",
    fecha_inicio: "",
    hora_inicio: "",
    fecha_cierre: "",
    hora_cierre: "",
  };
}
