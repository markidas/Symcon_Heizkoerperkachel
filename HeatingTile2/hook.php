<?php
// Wird vom WebHook Control aufgerufen
declare(strict_types=1);

if (!defined('IPS_BASE')) { include_once __DIR__ . '/../../../../scripts/IPSLibrary/app/core/IPSUtils/IPSUtils.inc.php'; } // harmless if missing

$instanceId = intval($_GET['iid'] ?? 0);
if ($instanceId === 0) {
    http_response_code(400);
    echo 'Missing iid';
    return;
}

$instance = IPS_GetInstance($instanceId); // basic check
$raw = file_get_contents('php://input') ?: '{}';
$data = json_decode($raw, true) ?: [];

$action = $data['action'] ?? '';

function _ok($arr = []) { header('Content-Type: application/json'); echo json_encode(['ok'=>true]+$arr); exit; }
function _bad($msg) { http_response_code(400); echo $msg; exit; }

switch ($action) {
    case 'setValve':
        $var = intval(IPS_GetProperty($instanceId, 'ValvePercentVarID'));
        $val = max(0, min(100, floatval($data['value'] ?? 0)));
        if ($var > 0) { RequestAction($var, $val); }
        _ok(['value'=>$val]);
        break;

    case 'incSetpoint':
    case 'decSetpoint':
    case 'setSetpoint':
        $var = intval(IPS_GetProperty($instanceId, 'SetpointVarID'));
        $step = floatval(IPS_GetProperty($instanceId, 'SetpointStep'));
        $dec = intval(IPS_GetProperty($instanceId, 'Decimals'));
        $cur = GetValueFloat($var);
        if ($action === 'incSetpoint') $cur += $step;
        elseif ($action === 'decSetpoint') $cur -= $step;
        else $cur = floatval($data['value'] ?? $cur);
        $cur = round($cur, $dec);
        RequestAction($var, $cur);
        _ok(['value'=>$cur]);
        break;

    case 'setMode':
        $var = intval(IPS_GetProperty($instanceId, 'ModeVarID'));
        $val = intval($data['value'] ?? 0); // 0:Frost,1:Standby,2:Komfort
        if ($var > 0) { RequestAction($var, $val); }
        _ok(['value'=>$val]);
        break;

    default:
        _bad('unknown action');
}
