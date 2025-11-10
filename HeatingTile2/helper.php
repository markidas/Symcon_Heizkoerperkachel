<?php
declare(strict_types=1);

trait HT_WebHook
{
    // GUID des WebHook Control (Symcon)
    private $WEBHOOK_GUID = '{6EF13B1A-FC7F-4F7B-9A1A-6D0C7A3E46D4}';

    private function RegisterHook(string $Hook, int $TargetID): void
    {
        // 1) WebHook-Control-Instanz finden
        $ids = function_exists('IPS_GetInstanceListByModuleID') ? IPS_GetInstanceListByModuleID($this->WEBHOOK_GUID) : [];
        $hookID = $ids[0] ?? 0;

        // Falls nicht vorhanden: sauber abbrechen (kein Crash) und Hinweis loggen
        if ($hookID === 0) {
            $this->SendDebug('HeatingTile', 'WebHook Control nicht gefunden. Bitte Kerninstanz anlegen.', 0);
            return;
        }

        // 2) Hooks-JSON holen (leer wenn Property noch nie gesetzt wurde)
        $raw = IPS_GetProperty($hookID, 'Hooks');
        $list = $raw ? json_decode($raw, true) : [];
        if (!is_array($list)) {
            $list = [];
        }

        // 3) Eintrag aktualisieren oder hinzufügen
        $found = false;
        foreach ($list as &$entry) {
            if (($entry['Hook'] ?? '') === $Hook) {
                $entry['TargetID'] = $TargetID;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $list[] = ['Hook' => $Hook, 'TargetID' => $TargetID];
        }

        // 4) Schreiben & anwenden
        IPS_SetProperty($hookID, 'Hooks', json_encode($list, JSON_UNESCAPED_SLASHES));
        IPS_ApplyChanges($hookID);
    }
}
