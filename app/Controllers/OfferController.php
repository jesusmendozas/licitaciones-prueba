<?php

declare(strict_types=1);

namespace App\Controllers;

use App\Core\Request;
use App\Core\Response;
use App\Export\OfferExporter;
use App\Models\Activity;
use App\Models\Offer;
use App\Models\OfferDocument;
use App\Services\DocumentStorage;
use App\Support\Clock;
use DateTime;
use DateTimeInterface;
use DateTimeZone;
use Illuminate\Database\Capsule\Manager as Capsule;
use Illuminate\Database\QueryException;
use RuntimeException;

/**
 * Reglas de las ofertas. Viven en el controlador, que es quien valida
 * según el enunciado. El cliente no puede elegir el consecutivo ni el estado:
 * el primero se reserva con un bloqueo para que dos altas no choquen,
 * y el segundo sale del cronograma.
 */
class OfferController
{
    private const POR_PAGINA = 10;

    /** @var DocumentStorage */
    private $files;

    /** @var OfferExporter */
    private $exporter;

    /** @var Clock */
    private $clock;

    public function __construct(DocumentStorage $files, OfferExporter $exporter, Clock $clock)
    {
        $this->files = $files;
        $this->exporter = $exporter;
        $this->clock = $clock;
    }

    /** Listado filtrado, de 10 en 10, con el estado recalculado al momento de la consulta. */
    public function list(Request $request): void
    {
        Offer::syncStates($this->clock->timestamp());
        $page = $this->pageNumber($request);

        $resultado = Offer::listPage($this->filters($request), $page, self::POR_PAGINA);
        $paginas = $resultado['total'] === 0 ? 1 : (int) ceil($resultado['total'] / self::POR_PAGINA);
        $data = [];
        foreach ($resultado['filas'] as $offer) {
            $data[] = $this->summary($offer);
        }

        Response::json([
            'data' => $data,
            'meta' => [
                'pagina' => $page,
                'por_pagina' => self::POR_PAGINA,
                'total' => $resultado['total'],
                'paginas' => $paginas,
            ],
        ]);
    }

    /** Excel de las ofertas filtradas, sin recortar por la página. */
    public function excel(Request $request): void
    {
        Offer::syncStates($this->clock->timestamp());
        $rows = [];
        foreach (Offer::listForExcel($this->filters($request)) as $offer) {
            $rows[] = $this->summary($offer);
        }

        $this->exporter->download($rows);
    }

    /** Detalle de una oferta. Responde 404 si no existe. */
    public function detail(int $id): void
    {
        Offer::syncStates($this->clock->timestamp());
        $offer = Offer::findWithDetails($id);
        if ($offer === null) {
            Response::json(['mensaje' => 'La oferta no existe.'], 404);
            return;
        }

        Response::json($this->detailPayload($offer));
    }

    /** Crea la oferta. El consecutivo y el estado los asigna el servidor. */
    public function create(Request $request): void
    {
        if ($request->hasInvalidJson()) {
            Response::json(['mensaje' => 'El cuerpo de la petición no es JSON válido.'], 400);
            return;
        }

        $data = $this->dataFrom($request);
        $errors = $this->validateData($data);
        if ($errors !== []) {
            Response::json(['errores' => $errors], 422);
            return;
        }

        try {
            $offer = $this->storeNew($data);
        } catch (QueryException $e) {
            if ($this->isMissingActivity($e)) {
                Response::json(['errores' => ['actividad_id' => 'La actividad seleccionada no existe.']], 422);
                return;
            }
            throw $e;
        }

        Response::json($this->detailPayload($this->findOrFail((int) $offer->id)), 201);
    }

    /** Edita solo una oferta activa y exige al menos un documento PDF o ZIP. */
    public function update(Request $request, int $id): void
    {
        if ($request->hasInvalidJson()) {
            Response::json(['mensaje' => 'El cuerpo de la petición no es JSON válido.'], 400);
            return;
        }

        Offer::syncStates($this->clock->timestamp());
        $offer = Offer::findWithDetails($id);
        if ($offer === null) {
            Response::json(['mensaje' => 'La oferta no existe.'], 404);
            return;
        }

        if ($this->resolveState(
            $this->date($offer->fecha_inicio),
            $this->time($offer->hora_inicio),
            $this->date($offer->fecha_cierre),
            $this->time($offer->hora_cierre)
        ) !== 'activo') {
            Response::json(['mensaje' => 'Solo se puede editar una oferta activa.'], 422);
            return;
        }

        $data = $this->dataFrom($request);
        $errors = $this->validateData($data);
        $files = $request->files('archivos');
        $titles = $this->stringList($request->input('titulos', []));
        $descriptions = $this->stringList($request->input('descripciones', []));
        $errors = array_merge($errors, $this->validateDocuments($offer->id, $files, $titles, $descriptions));

        if ($errors !== []) {
            Response::json(['errores' => $errors], 422);
            return;
        }

        $paths = [];
        try {
            $paths = $this->files->store($files);
            Capsule::connection()->transaction(function () use ($offer, $data, $paths, $titles, $descriptions) {
                $offer->fill($this->row($data));
                $offer->save();
                foreach ($paths as $index => $path) {
                    OfferDocument::createFor(
                        (int) $offer->id,
                        trim((string) $titles[$index]),
                        trim((string) $descriptions[$index]),
                        $path
                    );
                }
            });
        } catch (QueryException $e) {
            $this->files->delete($paths);
            if ($this->isMissingActivity($e)) {
                Response::json(['errores' => ['actividad_id' => 'La actividad seleccionada no existe.']], 422);
                return;
            }
            throw $e;
        } catch (\Throwable $e) {
            $this->files->delete($paths);
            throw $e;
        }

        Response::json($this->detailPayload($this->findOrFail((int) $offer->id)));
    }

    /** Descarga un documento si pertenece a la oferta indicada. */
    public function download(int $id, int $documentId): void
    {
        $file = $this->storedDocument($id, $documentId);
        if ($file === null) {
            Response::json(['mensaje' => 'El documento no existe.'], 404);
            return;
        }

        Response::download($file['path'], $file['name']);
    }

    /** Muestra un PDF en el navegador. Un ZIP no se visualiza. */
    public function view(int $id, int $documentId): void
    {
        $file = $this->storedDocument($id, $documentId);
        if ($file === null) {
            Response::json(['mensaje' => 'El documento no existe.'], 404);
            return;
        }

        if (strtolower(pathinfo($file['name'], PATHINFO_EXTENSION)) !== 'pdf') {
            Response::json(['mensaje' => 'Este archivo no se puede visualizar.'], 415);
            return;
        }

        Response::inline($file['path'], $file['name'], 'application/pdf');
    }

    /**
     * Inserta la oferta dentro de una transacción. Si dos altas coinciden,
     * el índice único del consecutivo provoca un reintento.
     *
     * @param array<string, mixed> $data
     */
    private function storeNew(array $data): Offer
    {
        $lastError = null;
        for ($attempt = 1; $attempt <= 3; $attempt++) {
            try {
                return Capsule::connection()->transaction(function () use ($data) {
                    $number = $this->nextConsecutive(Offer::lastConsecutiveForUpdate());
                    $row = $this->row($data);
                    $row['consecutivo'] = $this->formatConsecutive($number);

                    return Offer::query()->create($row);
                });
            } catch (QueryException $e) {
                $lastError = $e;
                if (!$this->isDuplicateConsecutive($e) || $attempt === 3) {
                    throw $e;
                }
            }
        }

        throw $lastError instanceof QueryException
            ? $lastError
            : new RuntimeException('No se pudo generar el consecutivo.');
    }

    /**
     * @param array<string, mixed> $data
     * @return array<string, mixed>
     */
    private function row(array $data): array
    {
        $startDate = trim((string) $data['fecha_inicio']);
        $startTime = trim((string) $data['hora_inicio']);
        $endDate = trim((string) $data['fecha_cierre']);
        $endTime = trim((string) $data['hora_cierre']);

        return [
            'objeto' => trim((string) $data['objeto']),
            'descripcion' => trim((string) $data['descripcion']),
            'moneda' => strtoupper(trim((string) $data['moneda'])),
            'presupuesto' => number_format((float) $data['presupuesto'], 2, '.', ''),
            'actividad_id' => (int) $data['actividad_id'],
            'fecha_inicio' => $startDate,
            'hora_inicio' => $startTime . ':00',
            'fecha_cierre' => $endDate,
            'hora_cierre' => $endTime . ':00',
            'estado' => $this->resolveState($startDate, $startTime, $endDate, $endTime),
        ];
    }

    /**
     * @return array<string, string>
     */
    private function filters(Request $request): array
    {
        $filters = [];
        foreach (['consecutivo', 'objeto', 'descripcion'] as $campo) {
            $value = $this->textQuery($request, $campo);
            if (mb_strlen($value) > 150) {
                $value = mb_substr($value, 0, 150);
            }
            $filters[$campo] = $value;
        }

        return $filters;
    }

    /**
     * @return array<string, mixed>
     */
    private function dataFrom(Request $request): array
    {
        return [
            'objeto' => $this->textInput($request, 'objeto'),
            'descripcion' => $this->textInput($request, 'descripcion'),
            'moneda' => $this->textInput($request, 'moneda'),
            'presupuesto' => $this->textInput($request, 'presupuesto'),
            'actividad_id' => $this->textInput($request, 'actividad_id'),
            'fecha_inicio' => $this->textInput($request, 'fecha_inicio'),
            'hora_inicio' => $this->normalizeTime($this->textInput($request, 'hora_inicio')),
            'fecha_cierre' => $this->textInput($request, 'fecha_cierre'),
            'hora_cierre' => $this->normalizeTime($this->textInput($request, 'hora_cierre')),
        ];
    }

    /** Acepta texto o número. Un arreglo u otro tipo queda vacío y lo rechaza la validación. */
    private function textInput(Request $request, string $key): string
    {
        return $this->asText($request->input($key, ''));
    }

    private function textQuery(Request $request, string $key): string
    {
        return $this->asText($request->query($key, ''));
    }

    private function pageNumber(Request $request): int
    {
        $value = $request->query('pagina', 1);
        if (!is_string($value) && !is_int($value) && !is_float($value)) {
            return 1;
        }
        if (!preg_match('/^\d+$/', trim((string) $value))) {
            return 1;
        }

        $page = (int) $value;

        return $page < 1 ? 1 : $page;
    }

    /**
     * @param mixed $value
     */
    private function asText($value): string
    {
        if (is_int($value)) {
            return (string) $value;
        }
        if (is_float($value)) {
            if (!is_finite($value)) {
                return '';
            }
            $text = rtrim(rtrim(sprintf('%.2F', $value), '0'), '.');

            return $text === '-0' ? '0' : $text;
        }
        if (!is_string($value)) {
            return '';
        }

        return $value;
    }

    private function findOrFail(int $id): Offer
    {
        $offer = Offer::findWithDetails($id);
        if ($offer === null) {
            throw new RuntimeException('No se pudo recuperar la oferta.');
        }

        return $offer;
    }

    /**
     * @param mixed $value
     * @return array<int, string>
     */
    private function stringList($value): array
    {
        if ($value === null || $value === '') {
            return [];
        }
        if (!is_array($value)) {
            return [(string) $value];
        }

        $lista = [];
        foreach ($value as $item) {
            $lista[] = (string) $item;
        }

        return $lista;
    }

    private function isDuplicateConsecutive(QueryException $e): bool
    {
        $info = $e->errorInfo;

        return isset($info[1]) && (int) $info[1] === 1062;
    }

    private function isMissingActivity(QueryException $e): bool
    {
        $info = $e->errorInfo;

        return isset($info[1]) && (int) $info[1] === 1452;
    }

    /**
     * Valida objeto, descripción, moneda, presupuesto, actividad y cronograma.
     * El cierre tiene que ser posterior al inicio.
     *
     * @param array<string, mixed> $data
     * @return array<string, string>
     */
    private function validateData(array $data): array
    {
        $errors = [];

        $objectName = trim((string) ($data['objeto'] ?? ''));
        if ($objectName === '') {
            $errors['objeto'] = 'El objeto es obligatorio.';
        } elseif (mb_strlen($objectName) > 150) {
            $errors['objeto'] = 'El objeto no puede superar 150 caracteres.';
        }

        $description = trim((string) ($data['descripcion'] ?? ''));
        if ($description === '') {
            $errors['descripcion'] = 'La descripción es obligatoria.';
        } elseif (mb_strlen($description) > 400) {
            $errors['descripcion'] = 'La descripción no puede superar 400 caracteres.';
        }

        $currency = strtoupper(trim((string) ($data['moneda'] ?? '')));
        if (!in_array($currency, ['COP', 'USD', 'EUR'], true)) {
            $errors['moneda'] = 'La moneda debe ser COP, USD o EUR.';
        }

        $budget = trim((string) ($data['presupuesto'] ?? ''));
        if ($budget === '') {
            $errors['presupuesto'] = 'El presupuesto es obligatorio.';
        } elseif (!preg_match('/^\d{1,13}(\.\d{1,2})?$/', $budget) || (float) $budget <= 0) {
            $errors['presupuesto'] = 'El presupuesto debe ser mayor a 0 y tener como máximo dos decimales.';
        }

        $activity = trim((string) ($data['actividad_id'] ?? ''));
        if ($activity === '' || !ctype_digit($activity)) {
            $errors['actividad_id'] = 'La actividad es obligatoria.';
        } elseif (!Activity::existsWithId((int) $activity)) {
            $errors['actividad_id'] = 'La actividad seleccionada no existe.';
        }

        $startDate = trim((string) ($data['fecha_inicio'] ?? ''));
        $startTime = trim((string) ($data['hora_inicio'] ?? ''));
        $endDate = trim((string) ($data['fecha_cierre'] ?? ''));
        $endTime = trim((string) ($data['hora_cierre'] ?? ''));

        if (!$this->dateIsValid($startDate)) {
            $errors['fecha_inicio'] = 'La fecha de inicio es obligatoria y debe tener el formato AAAA-MM-DD.';
        }
        if (!$this->timeIsValid($startTime)) {
            $errors['hora_inicio'] = 'La hora de inicio es obligatoria y debe tener el formato HH:mm.';
        }
        if (!$this->dateIsValid($endDate)) {
            $errors['fecha_cierre'] = 'La fecha de cierre es obligatoria y debe tener el formato AAAA-MM-DD.';
        }
        if (!$this->timeIsValid($endTime)) {
            $errors['hora_cierre'] = 'La hora de cierre es obligatoria y debe tener el formato HH:mm.';
        }

        $scheduleComplete = !isset($errors['fecha_inicio'])
            && !isset($errors['hora_inicio'])
            && !isset($errors['fecha_cierre'])
            && !isset($errors['hora_cierre']);
        if ($scheduleComplete && ($endDate . ' ' . $endTime) <= ($startDate . ' ' . $startTime)) {
            $errors['hora_cierre'] = 'La fecha y hora de cierre deben ser posteriores a la fecha y hora de inicio.';
        }

        return $errors;
    }

    /**
     * @param array<int, array<string, mixed>> $files
     * @param array<int, string> $titles
     * @param array<int, string> $descriptions
     * @return array<string, string>
     */
    private function validateDocuments(int $offerId, array $files, array $titles, array $descriptions): array
    {
        if (count($files) !== count($titles) || count($files) !== count($descriptions)) {
            return ['documentos' => 'Cada documento debe incluir título, descripción y archivo.'];
        }

        foreach ($files as $index => $file) {
            $title = trim((string) $titles[$index]);
            $description = trim((string) $descriptions[$index]);
            if ($title === '') {
                return ['documentos' => 'El título del documento es obligatorio.'];
            }
            if (mb_strlen($title) > 100) {
                return ['documentos' => 'El título del documento no puede superar 100 caracteres.'];
            }
            if ($description === '') {
                return ['documentos' => 'La descripción del documento es obligatoria.'];
            }
            if (mb_strlen($description) > 200) {
                return ['documentos' => 'La descripción del documento no puede superar 200 caracteres.'];
            }

            $message = $this->files->validateFile($file);
            if ($message !== null) {
                return ['documentos' => $message];
            }
        }

        if (OfferDocument::countForOffer($offerId) + count($files) < 1) {
            return ['documentos' => 'Debes adjuntar al menos un documento.'];
        }

        return [];
    }

    private function normalizeTime(string $time): string
    {
        $time = trim($time);
        if (preg_match('/^([01]\d|2[0-3]):[0-5]\d:[0-5]\d$/', $time) === 1) {
            return substr($time, 0, 5);
        }

        return $time;
    }

    private function dateIsValid(string $date): bool
    {
        $parts = explode('-', $date);
        if (count($parts) !== 3 || !ctype_digit($parts[0]) || !ctype_digit($parts[1]) || !ctype_digit($parts[2])) {
            return false;
        }

        return checkdate((int) $parts[1], (int) $parts[2], (int) $parts[0]);
    }

    private function timeIsValid(string $time): bool
    {
        return preg_match('/^([01]\d|2[0-3]):[0-5]\d$/', $time) === 1;
    }

    /**
     * activo antes del inicio, publicada desde el inicio y en evaluacion desde el cierre.
     */
    private function resolveState(string $startDate, string $startTime, string $endDate, string $endTime): string
    {
        $now = $this->clock->now();
        if ($now >= $this->moment($endDate, $endTime)) {
            return 'en evaluacion';
        }
        if ($now >= $this->moment($startDate, $startTime)) {
            return 'publicada';
        }

        return 'activo';
    }

    private function moment(string $date, string $time): DateTime
    {
        $moment = DateTime::createFromFormat(
            'Y-m-d H:i',
            $date . ' ' . substr($time, 0, 5),
            new DateTimeZone(Clock::ZONA)
        );
        if ($moment === false) {
            throw new RuntimeException('El cronograma no es válido.');
        }

        return $moment;
    }

    /** Siguiente número a partir del último consecutivo, o 1 si todavía no hay ofertas. */
    private function nextConsecutive(?string $last): int
    {
        if ($last === null || $last === '') {
            return 1;
        }

        $parts = explode('-', $last);

        return ((int) ($parts[1] ?? 0)) + 1;
    }

    /** Arma PO-0001-26: cuatro dígitos y el año en dos cifras. */
    private function formatConsecutive(int $number): string
    {
        $sequence = str_pad((string) $number, 4, '0', STR_PAD_LEFT);
        $year = $this->clock->now()->format('y');

        return 'PO-' . $sequence . '-' . $year;
    }

    /**
     * @return array<string, mixed>
     */
    private function summary(Offer $offer): array
    {
        return [
            'id' => (int) $offer->id,
            'consecutivo' => (string) $offer->consecutivo,
            'objeto' => (string) $offer->objeto,
            'descripcion' => (string) $offer->descripcion,
            'fecha_inicio' => $this->date($offer->fecha_inicio),
            'fecha_cierre' => $this->date($offer->fecha_cierre),
            'estado' => (string) $offer->estado,
        ];
    }

    /**
     * @return array{path: string, name: string}|null
     */
    private function storedDocument(int $id, int $documentId): ?array
    {
        $document = OfferDocument::findForOffer($id, $documentId);
        if ($document === null) {
            return null;
        }

        $absolute = $this->files->absolutePath((string) $document->archivo);
        if (!$this->files->isInsideUploads($absolute)) {
            return null;
        }

        return [
            'path' => $absolute,
            'name' => $this->files->visibleName((string) $document->archivo),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function detailPayload(Offer $offer): array
    {
        $activity = $offer->activity;
        $documents = [];
        foreach ($offer->documents as $document) {
            $documents[] = [
                'id' => (int) $document->id,
                'titulo' => (string) $document->titulo,
                'descripcion' => (string) $document->descripcion,
                'nombre' => $this->files->visibleName((string) $document->archivo),
                'creado_en' => $this->dateTime($document->creado_en),
            ];
        }

        return [
            'id' => (int) $offer->id,
            'consecutivo' => (string) $offer->consecutivo,
            'objeto' => (string) $offer->objeto,
            'descripcion' => (string) $offer->descripcion,
            'moneda' => (string) $offer->moneda,
            'presupuesto' => number_format((float) $offer->presupuesto, 2, '.', ''),
            'actividad_id' => (int) $offer->actividad_id,
            'actividad' => $activity === null ? null : [
                'id' => (int) $activity->id,
                'codigo_segmento' => (int) $activity->codigo_segmento,
                'segmento' => (string) $activity->segmento,
                'codigo_familia' => (int) $activity->codigo_familia,
                'familia' => (string) $activity->familia,
                'codigo_clase' => (int) $activity->codigo_clase,
                'clase' => (string) $activity->clase,
                'codigo_producto' => (int) $activity->codigo_producto,
                'producto' => (string) $activity->producto,
            ],
            'fecha_inicio' => $this->date($offer->fecha_inicio),
            'hora_inicio' => $this->time($offer->hora_inicio),
            'fecha_cierre' => $this->date($offer->fecha_cierre),
            'hora_cierre' => $this->time($offer->hora_cierre),
            'estado' => (string) $offer->estado,
            'creado_en' => $this->dateTime($offer->creado_en),
            'actualizado_en' => $this->dateTime($offer->actualizado_en),
            'documentos' => $documents,
        ];
    }

    /**
     * @param mixed $value
     */
    private function date($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d');
        }

        return substr((string) $value, 0, 10);
    }

    /**
     * @param mixed $value
     */
    private function time($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('H:i');
        }

        $text = str_replace('T', ' ', (string) $value);
        $space = strpos($text, ' ');
        if ($space !== false) {
            return substr($text, $space + 1, 5);
        }

        return substr($text, 0, 5);
    }

    /**
     * @param mixed $value
     */
    private function dateTime($value): string
    {
        if ($value instanceof DateTimeInterface) {
            return $value->format('Y-m-d H:i');
        }

        return substr(str_replace('T', ' ', (string) $value), 0, 16);
    }
}
