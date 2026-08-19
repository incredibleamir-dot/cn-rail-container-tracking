<?php
/**
 * Google Sheets Data Source Helper
 * Fetches tracking data from a deployed Google Apps Script web app.
 * Maps sheet fields to the same format as parseAPIResponse().
 */

function fetchGoogleSheetData($gasUrl, $containerNumbers = []) {
    Debug::log('API', 'Fetching from Google Sheet: ' . $gasUrl);

    $url = rtrim($gasUrl, '/');

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_TIMEOUT => 60,
        CURLOPT_SSL_VERIFYPEER => true,
    ]);

    $start = microtime(true);
    $response = curl_exec($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = round((microtime(true) - $start) * 1000);

    Debug::logApiCall($url, 'GET', $code, $duration, strlen($response ?? ''));

    if ($code !== 200 || empty($response)) {
        Debug::log('ERROR', "Google Sheet fetch failed: HTTP $code");
        return [];
    }

    $data = json_decode($response, true);
    if (!$data) {
        Debug::log('ERROR', 'Google Sheet: invalid JSON response');
        return [];
    }

    $lookup = $data['containers'] ?? $data['lookup'] ?? [];
    $results = [];
    foreach ($lookup as $id => $row) {
        $clean = strtoupper(trim($id));
        $parsed = mapGoogleSheetRow($row);
        // Store under full key (TGBU6417342) and normalized key (TGBU641734)
        $results[$clean] = $parsed;
        $norm = containerNormalizeForAPI($clean);
        if ($norm !== $clean) {
            $results[$norm] = $parsed;
        }
    }

    Debug::log('INFO', 'Google Sheet: loaded ' . count($results) . ' containers');
    return $results;
}

function mapGoogleSheetRow($row) {
    $etaRaw = $row['etaTime'] ?? '';
    $etaParsed = parseCNRailDateTime($etaRaw);

    $eventRaw = $row['lastEventTime'] ?? '';
    $eventParsed = parseCNRailDateTime($eventRaw);

    $lfdRaw = $row['lastFreeDay'] ?? '';
    $lfd = null;
    if ($lfdRaw) {
        if (is_numeric($lfdRaw)) {
            $lfd = date('Y-m-d', ($lfdRaw - 25569) * 86400);
        } else {
            $lfd = convertCNRailDate($lfdRaw);
        }
    }

    $customsDesc = $row['customsDescription'] ?? null;
    $customsTs = $row['customsTimestamp'] ?? null;
    if ($customsTs && is_numeric($customsTs)) {
        $customsTs = date('Y-m-d H:i', ($customsTs - 25569) * 86400);
    }

    return [
        'waybillStatus'       => $row['waybillStatus'] ?? 'Unknown',
        'loadEmpty'           => 'Unknown',
        'lastEvent'           => $row['lastEvent'] ?? null,
        'lastEventTimeLocal'  => $eventParsed['local'] ?? null,
        'lastEventTimezone'   => $eventParsed['timezone'] ?? null,
        'lastEventLocation'   => $row['lastEventLocation'] ?? null,
        'etaLocal'            => $etaParsed['local'] ?? null,
        'etaTimezone'         => $etaParsed['timezone'] ?? null,
        'etaStation'          => $row['etaStation'] ?? null,
        'lastFreeDay'         => $lfd,
        'customsStatus'       => $customsDesc,
        'customsTimestamp'    => $customsTs,
        'customsHoldRaw'      => $customsDesc,
        'customsHolds'        => $customsDesc ? [['Description' => $customsDesc, 'Timestamp' => $customsTs, 'Direction' => '']] : [],
        'isCustomsHold'       => (customsStatus($customsDesc) === 'hold'),
        'flatcarEquipmentId'  => null,
        'chassisEquipmentId'  => null,
        'carKindDescription'  => null,
        'lotLocation'         => null,
        'badOrder'            => null,
        'weight'              => [],
        'source'              => 'google_sheet',
    ];
}
