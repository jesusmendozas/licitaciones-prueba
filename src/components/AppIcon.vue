<script>
/** Iconos en línea. No carga una librería externa. */
const ICONS = {
  plus: [["path", { d: "M12 5v14M5 12h14" }]],
  search: [["circle", { cx: 11, cy: 11, r: 7 }], ["path", { d: "m20 20-3.5-3.5" }]],
  download: [["path", { d: "M12 3v12M7 10l5 5 5-5M5 21h14" }]],
  eye: [["path", { d: "M2 12s3.5-7 10-7 10 7 10 7-3.5 7-10 7S2 12 2 12z" }], ["circle", { cx: 12, cy: 12, r: 3 }]],
  pencil: [["path", { d: "M17 3l4 4L8 20H4v-4L17 3z" }]],
  arrowLeft: [["path", { d: "M19 12H5M12 19l-7-7 7-7" }]],
  chevronLeft: [["path", { d: "M15 18l-6-6 6-6" }]],
  chevronRight: [["path", { d: "M9 18l6-6-6-6" }]],
  calendar: [["rect", { x: 3, y: 4, width: 18, height: 18, rx: 2 }], ["path", { d: "M16 2v4M8 2v4M3 10h18" }]],
  clock: [["circle", { cx: 12, cy: 12, r: 9 }], ["path", { d: "M12 7v5l3 2" }]],
  file: [["path", { d: "M14 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V8z" }], ["path", { d: "M14 2v6h6M8 13h8M8 17h6" }]],
  upload: [["path", { d: "M12 16V4M7 9l5-5 5 5M5 20h14" }]],
  close: [["path", { d: "M18 6 6 18M6 6l12 12" }]],
  help: [["circle", { cx: 12, cy: 12, r: 9 }], ["path", { d: "M9.5 9a2.5 2.5 0 0 1 5 0c0 2-2.5 2-2.5 4M12 17h.01" }]],
  clipboard: [["rect", { x: 8, y: 2, width: 8, height: 4, rx: 1 }], ["path", { d: "M16 4h2a2 2 0 0 1 2 2v14a2 2 0 0 1-2 2H6a2 2 0 0 1-2-2V6a2 2 0 0 1 2-2h2" }]],
  info: [["circle", { cx: 12, cy: 12, r: 9 }], ["path", { d: "M12 16v-4M12 8h.01" }]],
};

export default {
  name: "AppIcon",
  props: {
    name: { type: String, required: true },
    size: { type: [Number, String], default: 16 },
  },
  render(h) {
    const figuras = ICONS[this.name] || [];
    return h("svg", {
      staticClass: "icono",
      attrs: {
        width: this.size,
        height: this.size,
        viewBox: "0 0 24 24",
        fill: "none",
        stroke: "currentColor",
        "stroke-width": 2,
        "stroke-linecap": "round",
        "stroke-linejoin": "round",
        "aria-hidden": "true",
        focusable: "false",
      },
    }, figuras.map((figura) => h(figura[0], { attrs: figura[1] })));
  },
};
</script>
