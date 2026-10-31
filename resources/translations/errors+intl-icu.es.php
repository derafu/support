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
    // Csv.
    'Failed to convert the CSV content from {encoding} to UTF-8.' =>
        'No se pudo convertir el contenido CSV de {encoding} a UTF-8.',
    'Failed to parse CSV content: {error}' =>
        'No se pudo interpretar el contenido CSV: {error}',
    'Cannot read file: {file}' =>
        'No se puede leer el archivo: {file}',
    'Failed to read file: {file}' =>
        'No se pudo leer el archivo: {file}',
    'Cannot use directory: {directory}' =>
        'No se puede usar el directorio: {directory}',
    'Failed to write CSV file: {file}' =>
        'No se pudo escribir el archivo CSV: {file}',
    'Failed to generate CSV: {error}' =>
        'No se pudo generar el CSV: {error}',
    'Headers have already been sent.' =>
        'Los encabezados ya fueron enviados.',

    // Date.
    'Invalid date: {year}-{month}-{day}.' =>
        'Fecha inválida: {year}-{month}-{day}.',
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

    // Encoding.
    'Cannot convert the property "{property}" of {class}: it is readonly.' =>
        'No se puede convertir la propiedad "{property}" de {class}: es de solo lectura.',

    // Ip.
    'The range "{range}" is not valid.' =>
        'El rango "{range}" no es válido.',
    'The prefix {prefix} is not valid for an IPv{version} address.' =>
        'El prefijo {prefix} no es válido para una dirección IPv{version}.',
    'The value "{ip}" is not an IP address.' =>
        'El valor "{ip}" no es una dirección IP.',

    // Str.
    'Regular expression error: {error}.' =>
        'Error de expresión regular: {error}.',
    'The value of "{key}" can not be written as text, {given} given.' =>
        'El valor de "{key}" no se puede escribir como texto, se entregó {given}.',
    'Unsupported placeholder style: {style}.' =>
        'Estilo de marcador no soportado: {style}.',
    'Length must be at least 1.' =>
        'El largo debe ser al menos 1.',
];
