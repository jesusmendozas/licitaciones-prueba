# Módulo de licitaciones

## Tecnologías

El proyecto sigue el patrón **MVC**. El controlador valida y aplica las reglas, el modelo habla con la base y la vista es cada pantalla Vue.

**Backend.** PHP 8, sin framework. Los modelos usan Eloquent en su versión independiente (`illuminate/database`) y la base es MySQL 8. El Excel se genera con PhpSpreadsheet.

**Frontend.** Vue 2.7, con componentes `.vue`, Axios y Bootstrap 5.3 . Vite compila esas pantallas antes de servirlas.

## Puesta en marcha

1. Instalar dependencias y compilar el frontend Vue:

```bash
composer install
npm install
npm run build
```

2. Crear el archivo de entorno:

```bash
cp .env.example .env
```

Completa `DB_PASSWORD` si el usuario de MySQL la tiene. La base configurada es `licitaciones`.

3. Crear las tablas y cargar las actividades desde el Excel. El script SQL borra las tablas si ya existen. El PHP lee `bd/maestra-actividades.xlsx` e inserta los productos; la aplicación no vuelve a abrir ese archivo.

```bash
mysql -u root -p < bd/01_estructura.sql
php bd/load_activities.php
```

La carga trae el clasificador completo y puede tardar un momento. Cada producto se guarda por su código: si se vuelve a cargar el Excel, se actualizan los nombres y se conserva el id que ya usan las ofertas. Si el Excel deja fuera una actividad que está en uso, la carga se detiene y no modifica la maestra.

4. Levantar la aplicación:

```bash
php -S localhost:8000 -t public public/index.php
```

5. Abrir http://localhost:8000

## Uso

- Crear una oferta desde **Nueva oferta**. El consecutivo lo asigna el servidor, con el formato `PO-0001-26`.
- La actividad se busca al dejar de escribir. Hacen falta al menos 2 caracteres y la lista muestra hasta 20 resultados.
- El estado queda en `activo` mientras no llegue la fecha y hora de inicio. En ese estado se puede editar.
- Al cumplirse el inicio pasa a `publicada`. Al cumplirse el cierre pasa a `en evaluacion`. Esas dos no se editan.
- En la edición hay que adjuntar al menos un documento PDF o ZIP.
- El listado filtra por consecutivo, objeto o descripción, pagina de 10 en 10 y exporta a Excel las filas filtradas, sin recortar por la página.

La hora del estado usa la zona `America/Bogota`.

## Decisiones

**Consecutivo y estado los calcula el servidor.** El formato es `PO-0001-26`. El ejemplo `O-0001-25` del enunciado se toma como un error de redacción, porque la fórmula indica el prefijo `PO`. El número se reserva dentro de una transacción, con bloqueo de la última fila y un índice único, para que dos altas al mismo tiempo no repitan el consecutivo. El estado no se elige en el formulario: queda `activo` antes del inicio, pasa a `publicada` cuando llega el inicio y a `en evaluacion` cuando llega el cierre. Solo `activo` se edita. La hora de esa comparación es `America/Bogota`, no la zona del servidor.

## Estructura

```
app/Controllers     validación, reglas de negocio y respuesta HTTP
app/Models          comunicación con la base mediante Eloquent suelto
app/Services        guardado de archivos en disco
app/Export          archivo Excel
app/Core            enrutador, petición, respuesta y conexión
bd                  estructura SQL, Excel de actividades y su carga
src/components      vistas Vue (.vue), una por pantalla
storage/uploads     documentos cargados, fuera de la web pública
```
