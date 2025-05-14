<?php

function generateAttendanceMatrix($datos, $events, $fecha_inicio, $fecha_termino, $eventColors) {
    $attendance = [];
    $dates = [];
    $months = [];
    $unlinkedEvents = [];

    // Generar todas las fechas en el rango
    $currentDate = $fecha_inicio;
    while ($currentDate <= $fecha_termino) {
        $dates[$currentDate] = true;
        $months[getMonthName($currentDate)][] = $currentDate;
        $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
    }

    foreach ($datos as $fila) {
        $employee = $fila['nombre'];
        $rut = $fila['identificador'];
        $program = $fila['contrato'];
        $entryDate = formatExcelDate($fila['fecha_entrada']);
        $exitDate = isset($fila['fecha_salida']) ? formatExcelDate($fila['fecha_salida']) : null;
        $entryDateOnly = getDateOnly($entryDate);
        $exitDateOnly = $exitDate ? getDateOnly($exitDate) : null;
        $manualEntry = $fila['manual_entry'];
        
        // Crear una clave única combinando empleado y proyecto
        $key = $employee . '|' . $program;

        if (!isset($attendance[$key])) {
            $attendance[$key] = [
                'nombre' => $employee,
                'rut' => $rut,
                'program' => $program,
                'days' => [],
                'countX' => 0
            ];
        }

        if ($entryDateOnly) {
            $currentDate = $entryDateOnly;
            while ($currentDate <= $exitDateOnly || ($exitDateOnly === null && $currentDate === $entryDateOnly)) {
                if (isset($dates[$currentDate])) {
                    $attendance[$key]['days'][$currentDate] = [
                        'entry' => $currentDate === $entryDateOnly ? $entryDate : null,
                        'exit' => $currentDate === $exitDateOnly ? $exitDate : null,
                        'noExit' => $exitDate === null,
                        'event' => null,
                        'eventColor' => '',
                        'manualEntry' => $manualEntry
                    ];
                    $attendance[$key]['countX']++;
                }
                $currentDate = date('Y-m-d', strtotime($currentDate . ' +1 day'));
            }
        }
    }

    foreach ($events as $event) {
        $employee = $event['nombre'];
        $date = getDateOnly($event['fecha']);
        $eventType = $event['event_type'];
        
        $foundMatch = false;
        foreach ($attendance as $key => $info) {
            list($empName, $empProgram) = explode('|', $key);
            if ($empName === $employee) {
                if (isset($info['days'][$date])) {
                    $attendance[$key]['days'][$date]['event'] = $eventType;
                    $attendance[$key]['days'][$date]['eventColor'] = getEventColor($eventType, $eventColors);
                    $foundMatch = true;
                }
            }
        }
        
        if (!$foundMatch) {
            $unlinkedEvents[] = $event;
        }
    }

    ksort($dates);
    ksort($attendance);
    return [$attendance, array_keys($dates), $months, $unlinkedEvents];
}

function formatExcelDate($excelDate) {
    if (is_numeric($excelDate)) {
        $unixDate = ($excelDate - 25569) * 86400;
        return gmdate("Y-m-d H:i:s", (int)$unixDate);
    }
    return $excelDate;
}

function getDateOnly($dateTime) {
    return explode(' ', $dateTime)[0];
}

function getMonthName($date) {
    return date('F', strtotime($date));
}

function getEventColor($event_type, $eventColors) {
    return $eventColors[$event_type] ?? "#FFFFFF";
}

function getDayName($date) {
    return date('D', strtotime($date));
}
