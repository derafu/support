<?php

declare(strict_types=1);

/**
 * Derafu: Support - Essential PHP Utilities.
 *
 * Copyright (c) 2026 Esteban De La Fuente Rubio / Derafu <https://www.derafu.dev>
 * Licensed under the MIT License.
 * See LICENSE file for more details.
 */

return [
    // Arr.
    'Row {index} must have exactly 2 columns.' =>
        'La fila {index} debe tener exactamente 2 columnas.',

    // Csv.
    'Failed to parse CSV content: {error}' =>
        'No se pudo interpretar el contenido CSV: {error}',
    'Cannot read file: {file}' =>
        'No se puede leer el archivo: {file}',
    'Failed to read file: {file}' =>
        'No se pudo leer el archivo: {file}',
    'Failed to read CSV file {file}: {error}' =>
        'No se pudo leer el archivo CSV {file}: {error}',
    'Cannot use directory: {directory}' =>
        'No se puede usar el directorio: {directory}',
    'Failed to write CSV file: {file}' =>
        'No se pudo escribir el archivo CSV: {file}',
    'Failed to generate CSV: {error}' =>
        'No se pudo generar el CSV: {error}',
    'Headers have already been sent.' =>
        'Los encabezados ya fueron enviados.',

    // Date.
    'Invalid month in period: {period}' =>
        'Mes inválido en el período: {period}',
    'Invalid date string: {date}. {error}' =>
        'Texto de fecha inválido: {date}. {error}',
    'Invalid time unit: {unit}' =>
        'Unidad de tiempo inválida: {unit}',

    // Factory and Hydrator.
    'Created instance of {class} does not match expected type {expectedType}.' =>
        'La instancia creada de {class} no coincide con el tipo esperado {expectedType}.',
    'Cannot assign attribute "{attribute}" to class {class}. No property or suitable setter method found.' =>
        'No se puede asignar el atributo "{attribute}" a la clase {class}. No se encontró una propiedad ni un método setter adecuado.',
    'Failed to create instance of {class}: {error}' =>
        'No se pudo crear una instancia de {class}: {error}',

    // File.
    'Unable to create directory ({directory}).' =>
        'No se pudo crear el directorio ({directory}).',
    'Unable to create temporary file in directory ({directory}).' =>
        'No se pudo crear el archivo temporal en el directorio ({directory}).',
    'Unable to write content to temporary file ({file}).' =>
        'No se pudo escribir el contenido en el archivo temporal ({file}).',
    'Unable to set permissions on temporary file ({file}).' =>
        'No se pudieron establecer los permisos del archivo temporal ({file}).',
    'Unable to move temporary file to target location ({file}).' =>
        'No se pudo mover el archivo temporal a su ubicación de destino ({file}).',
    'Failed to remove directory {directory}: {error}' =>
        'No se pudo eliminar el directorio {directory}: {error}',
    'Cannot read source file or directory: {source}' =>
        'No se puede leer el archivo o directorio de origen: {source}',
    'Cannot open destination file for writing: {destination}' =>
        'No se puede abrir el archivo de destino para escribir: {destination}',
    'Failed to create ZIP file: {error}' =>
        'No se pudo crear el archivo ZIP: {error}',
    'ZIP extension is not available' =>
        'La extensión ZIP no está disponible',
    'ZIP file does not exist: {file}' =>
        'El archivo ZIP no existe: {file}',
    'Failed to open ZIP file: {file} (Error code: {code})' =>
        'No se pudo abrir el archivo ZIP: {file} (código de error: {code})',
    'File already exists: {file}' =>
        'El archivo ya existe: {file}',
    'Failed to extract ZIP file to: {destination}' =>
        'No se pudo extraer el archivo ZIP en: {destination}',
    'File does not exist: {file}' =>
        'El archivo no existe: {file}',
    'Failed to send file: {file}' =>
        'No se pudo enviar el archivo: {file}',

    // Str.
    'Unsupported placeholder style: {style}.' =>
        'Estilo de marcador no soportado: {style}.',
    'Length must be at least 1.' =>
        'El largo debe ser al menos 1.',
];
