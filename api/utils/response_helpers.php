<?php

function requestId()
{
    static $id = null;
    if ($id === null) {
        $id = bin2hex(random_bytes(8));
    }
    return $id;
}

function jsonSuccess($data = [])
{
    echo json_encode(array_merge(["status" => "success", "request_id" => requestId()], $data));
}

function jsonError($message, $code = 400, $extra = [])
{
    http_response_code($code);
    echo json_encode(array_merge([
        "status" => "error",
        "message" => $message,
        "request_id" => requestId(),
    ], $extra));
}

function supabaseFailed($res)
{
    return !is_array($res) || ($res['code'] ?? 500) >= 400;
}

function requireUuid($value, $fieldName = 'id')
{
    $value = strtolower(trim((string) $value));
    if (!preg_match('/^[0-9a-f]{8}-[0-9a-f]{4}-[1-5][0-9a-f]{3}-[89ab][0-9a-f]{3}-[0-9a-f]{12}$/', $value)) {
        jsonError("Invalid {$fieldName}.", 400);
        exit();
    }
    return $value;
}

function nowIsoUtc()
{
    return gmdate('c');
}
