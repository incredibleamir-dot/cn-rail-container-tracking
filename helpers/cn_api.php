<?php
/**
 * CN Rail API Helper - Auth, Tracking, GPS
 * Ported from reference config.php + track.php
 */

function curlSafeClose($ch) {
    if (PHP_MAJOR_VERSION < 8) {
        curl_close($ch);
    }
}

function cnCurlDefaults() {
    $defaults = [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 30,
        CURLOPT_FOLLOWLOCATION => true,
        CURLOPT_SSL_VERIFYPEER => false,
        CURLOPT_SSL_VERIFYHOST => 0,
    ];
    $caPath = __DIR__ . '/../../data/cacert.pem';
    if (file_exists($caPath)) {
        $defaults[CURLOPT_CAINFO] = $caPath;
    }
    return $defaults;
}

function getCNAuthToken($apiKey = null, $authKey = null) {
    Debug::log('API', 'Requesting CN Rail auth token');

    if (!$apiKey || !$authKey) {
        $apiKey = \App\Models\Settings::get('cn_api_key', DEFAULT_CN_API_KEY);
        $authKey = \App\Models\Settings::get('cn_auth_key', DEFAULT_CN_AUTH_KEY);
    }

    $opts = cnCurlDefaults();
    $opts[CURLOPT_URL] = CN_AUTH_URL;
    $opts[CURLOPT_POST] = true;
    $opts[CURLOPT_HTTPHEADER] = [
        'x-apikey: ' . $apiKey,
        'Authorization: Basic ' . base64_encode($apiKey . ':' . $authKey),
    ];

    $ch = curl_init();
    curl_setopt_array($ch, $opts);

    $start = microtime(true);
    $response = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    $duration = round((microtime(true) - $start) * 1000);
    $error = curl_error($ch);
    curlSafeClose($ch);

    Debug::logApiCall(CN_AUTH_URL, 'POST', $httpCode, $duration, strlen($response ?? ''));

    if ($error || $httpCode !== 200) {
        Debug::log('ERROR', 'CN Auth failed', ['http' => $httpCode, 'curl_error' => $error]);
        return null;
    }

    $data = json_decode($response, true);
    if (isset($data['access_token']) && !empty($data['access_token'])) {
        Debug::log('INFO', 'CN Auth token obtained');
        return $data['access_token'];
    }

    Debug::log('WARNING', 'No access_token in auth response');
    return null;
}

function fetchBatchTracking(array $containerIds, $token) {
    if (empty($containerIds) || !$token) return [];

    $results = [];
    $chunks = array_chunk($containerIds, API_TRACKING_BATCH_SIZE);

    foreach ($chunks as $chunkIndex => $chunk) {
        $apiIds = array_map('containerNormalizeForAPI', $chunk);
        $idsParam = implode(',', $apiIds);
        $url = CN_TRACKING_URL . '?equipmentIds=' . $idsParam;

        $opts = cnCurlDefaults();
        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_HTTPHEADER] = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $opts);

        $start = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = round((microtime(true) - $start) * 1000);
        curlSafeClose($ch);

        Debug::logApiCall($url, 'GET', $httpCode, $duration, strlen($response ?? ''));

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (isset($data['ThirdPartyIntermodalShipment']['Equipment']) &&
                is_array($data['ThirdPartyIntermodalShipment']['Equipment'])) {
                foreach ($data['ThirdPartyIntermodalShipment']['Equipment'] as $item) {
                    if (isset($item['EquipmentId'])) {
                        $results[$item['EquipmentId']] = $item;
                    }
                }
            }
        } else {
            Debug::log('WARNING', "Tracking batch {$chunkIndex} failed", ['http' => $httpCode]);
        }

        if (count($chunks) > 1 && $chunkIndex < count($chunks) - 1) {
            usleep(100000);
        }
    }

    return $results;
}

function fetchBatchGps(array $containerIds, $token) {
    if (empty($containerIds) || !$token) return [];

    $results = [];
    $chunks = array_chunk($containerIds, API_GPS_BATCH_SIZE);

    foreach ($chunks as $chunkIndex => $chunk) {
        $url = CN_GPS_URL;
        $payload = json_encode(['Id' => $chunk]);

        $opts = cnCurlDefaults();
        $opts[CURLOPT_URL] = $url;
        $opts[CURLOPT_POST] = true;
        $opts[CURLOPT_POSTFIELDS] = $payload;
        $opts[CURLOPT_TIMEOUT] = 15;
        $opts[CURLOPT_HTTPHEADER] = [
            'Authorization: Bearer ' . $token,
            'Content-Type: application/json',
            'X-Limit: ' . API_GPS_BATCH_SIZE,
        ];

        $ch = curl_init();
        curl_setopt_array($ch, $opts);

        $start = microtime(true);
        $response = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $duration = round((microtime(true) - $start) * 1000);
        curlSafeClose($ch);

        Debug::logApiCall($url, 'POST', $httpCode, $duration, strlen($response ?? ''));

        if ($httpCode === 200) {
            $data = json_decode($response, true);
            if (!empty($data['GpsLocation']['Equipment'])) {
                foreach ($data['GpsLocation']['Equipment'] as $eq) {
                    $results[$eq['EquipmentId']] = $eq;
                }
            }
        }

        if (count($chunks) > 1 && $chunkIndex < count($chunks) - 1) {
            usleep(100000);
        }
    }

    return $results;
}

function parseAPIResponse($apiData, $fullContainerNumber) {
    $event   = $apiData['Event'] ?? [];
    $eta     = $apiData['ETA'] ?? [];
    $storage = $apiData['StorageCharge'] ?? [];
    $customs = $apiData['CustomsHold'] ?? [];
    $customsHolds = $apiData['CustomsHolds'] ?? [];

    $lastEvent = null;
    $lastEventTimeLocal = null;
    $lastEventTimezone = null;
    $lastEventLocation = null;

    if (is_array($event) && !empty($event)) {
        $lastEvent = $event['Description'] ?? null;
        if (!empty($event['Time'])) {
            $parsed = parseCNRailDateTime($event['Time']);
            $lastEventTimeLocal = $parsed['local'];
            $lastEventTimezone  = $parsed['timezone'];
        }
        $lastEventLocation = $event['Location']['Station'] ?? null;
    }

    $etaLocal = null;
    $etaTimezone = null;
    $etaStation = null;

    if (is_array($eta) && !empty($eta)) {
        if (!empty($eta['Time'])) {
            $parsed = parseCNRailDateTime($eta['Time']);
            $etaLocal    = $parsed['local'];
            $etaTimezone = $parsed['timezone'];
        }
        $etaStation = $eta['Location']['Station'] ?? null;
    }

    $lastFreeDay = null;
    if (is_array($storage) && !empty($storage)) {
        $lastFreeDay = convertCNRailDate($storage['LastFreeDay'] ?? null);
    }

    $customsStatus = null;
    $customsHoldRaw = null;
    $customsTimestamp = null;
    $isCustomsHold = false;

    // CustomsHolds (plural) is an array sorted newest-first; pick entry with a timestamp
    if (!empty($customsHolds) && is_array($customsHolds)) {
        $latestHold = null;
        foreach ($customsHolds as $h) {
            if (!empty($h['Timestamp'])) {
                $latestHold = $h;
                break;
            }
        }
        // If none have timestamp, take the first entry
        if (!$latestHold) $latestHold = $customsHolds[0] ?? null;
        if ($latestHold) {
            $customsHoldRaw = $latestHold['Description'] ?? null;
            $customsTimestamp = $latestHold['Timestamp'] ?? null;
            $customsStatus = $customsHoldRaw;
            if ($customsHoldRaw) {
                $isCustomsHold = (customsStatus($customsHoldRaw) === 'hold');
            }
        }
    }
    // Fallback to CustomsHold (singular) only if CustomsHolds was empty
    if (!$customsHoldRaw && is_array($customs) && !empty($customs)) {
        $customsHoldRaw = $customs['Description'] ?? null;
        $customsTimestamp = $customs['Timestamp'] ?? null;
        $customsStatus = $customsHoldRaw;
        if ($customsHoldRaw) {
            $isCustomsHold = (customsStatus($customsHoldRaw) === 'hold');
        }
    }

    $flatcarId   = $apiData['FlatcarEquipmentId'] ?? null;
    $chassisId   = $apiData['ChassisEquipmentId'] ?? null;
    $carKind     = $apiData['CarKindDescription'] ?? null;
    $lotLocation = $apiData['LotLocation'] ?? null;
    $badOrder    = $apiData['BadOrder'] ?? null;
    $weights     = $apiData['Weight'] ?? [];

    return [
        'container'             => $fullContainerNumber,
        'waybillStatus'         => $apiData['WaybillStatus'] ?? 'Unknown',
        'loadEmpty'             => $apiData['LoadEmpty'] ?? 'Unknown',
        'lastEvent'             => $lastEvent,
        'lastEventTimeLocal'    => $lastEventTimeLocal,
        'lastEventTimezone'     => $lastEventTimezone,
        'lastEventLocation'     => $lastEventLocation,
        'etaLocal'              => $etaLocal,
        'etaTimezone'           => $etaTimezone,
        'etaStation'            => $etaStation,
        'lastFreeDay'           => $lastFreeDay,
        'customsStatus'         => $customsStatus,
        'customsTimestamp'      => $customsTimestamp,
        'customsHoldRaw'        => $customsHoldRaw,
        'customsHolds'          => $customsHolds,
        'isCustomsHold'         => $isCustomsHold,
        'flatcarEquipmentId'    => $flatcarId,
        'chassisEquipmentId'    => $chassisId,
        'carKindDescription'    => $carKind,
        'lotLocation'           => $lotLocation,
        'badOrder'              => $badOrder,
        'weight'                => $weights,
    ];
}

// ─── Timezone Helpers ───

function parseCNRailDateTime($datetime) {
    $result = ['utc' => null, 'local' => null, 'timezone' => null];
    if (!$datetime) return $result;

    try {
        if (preg_match('/^(\d{4}-\d{2}-\d{2} \d{2}:\d{2}(?::\d{2})?)\s+([A-Z]{2,3})$/', $datetime, $m)) {
            $tzCode = $m[2];
            $phpTz = cnTimezoneMap($tzCode);
            $dt = new DateTime($m[1], new DateTimeZone($phpTz));
            $result['local']    = $dt->format('Y-m-d H:i:s');
            $result['timezone'] = $tzCode;
            $dt->setTimezone(new DateTimeZone('UTC'));
            $result['utc'] = $dt->format('Y-m-d H:i:s');
            return $result;
        }

        if (preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}/', $datetime)) {
            $dt = new DateTime($datetime);
            if (!preg_match('/[+-]\d{2}:?\d{2}|Z$/', $datetime)) {
                $dt = new DateTime($datetime, new DateTimeZone('America/Toronto'));
                $result['local']    = $dt->format('Y-m-d H:i:s');
                $result['timezone'] = 'ET';
                $dt->setTimezone(new DateTimeZone('UTC'));
                $result['utc'] = $dt->format('Y-m-d H:i:s');
            } else {
                $dt->setTimezone(new DateTimeZone('UTC'));
                $result['utc'] = $dt->format('Y-m-d H:i:s');
                $dt->setTimezone(new DateTimeZone('America/Toronto'));
                $result['local']    = $dt->format('Y-m-d H:i:s');
                $result['timezone'] = 'ET';
            }
            return $result;
        }

        $dt = new DateTime($datetime, new DateTimeZone('America/Toronto'));
        $result['local']    = $dt->format('Y-m-d H:i:s');
        $result['timezone'] = 'ET';
        $dt->setTimezone(new DateTimeZone('UTC'));
        $result['utc'] = $dt->format('Y-m-d H:i:s');
        return $result;
    } catch (Exception $e) {
        Debug::log('WARNING', 'DateTime parse failed: ' . $e->getMessage(), ['input' => $datetime]);
        return $result;
    }
}

function cnTimezoneMap($code) {
    $map = [
        'PT'=>'America/Vancouver', 'PDT'=>'America/Vancouver', 'PST'=>'America/Vancouver',
        'MT'=>'America/Edmonton',   'MDT'=>'America/Edmonton',   'MST'=>'America/Edmonton',
        'CT'=>'America/Winnipeg',   'CDT'=>'America/Winnipeg',   'CST'=>'America/Winnipeg',
        'ET'=>'America/Toronto',    'EDT'=>'America/Toronto',    'EST'=>'America/Toronto',
        'AT'=>'America/Halifax',    'ADT'=>'America/Halifax',    'AST'=>'America/Halifax',
        'NT'=>'America/St_Johns',   'NDT'=>'America/St_Johns',   'NST'=>'America/St_Johns',
    ];
    return $map[$code] ?? 'America/Toronto';
}

function convertCNRailDate($date) {
    if (!$date) return null;
    try {
        $dt = new DateTime($date);
        return $dt->format('Y-m-d');
    } catch (Exception $e) {
        return null;
    }
}

function formatLocalTime($utc_time, $timezone, $format = 'M j, g:i A') {
    if (!$utc_time || !$timezone) return null;
    try {
        $phpTz = cnTimezoneMap($timezone);
        $dt = new DateTime($utc_time, new DateTimeZone('UTC'));
        $dt->setTimezone(new DateTimeZone($phpTz));
        return $dt->format($format) . ' ' . $timezone;
    } catch (Exception $e) {
        return $utc_time;
    }
}
