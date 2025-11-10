<?php
declare(strict_types=1);

trait HT_WebHook
{
    private function RegisterHook(string $Hook, int $TargetID): void
    {
        $ids = IPS_GetInstanceListByModuleID('{6EF13B1A-FC7F-4F7B-9A1A-6D0C7A3E46D4}'); // WebHook Control
        $hookID = $ids[0] ?? 0;
        if ($hookID === 0) {
            $hookID = IPS_CreateInstance('{6EF13B1A-FC7F-4F7B-9A1A-6D0C7A3E46D4}');
            IPS_SetName($hookID, 'WebHook Control');
        }

        $list = json_decode(IPS_GetProperty($hookID, 'Hooks'), true) ?: [];
        $found = false;
        foreach ($list as &$entry) {
            if ($entry['Hook'] === $Hook) {
                $entry['TargetID'] = $TargetID;
                $found = true;
                break;
            }
        }
        if (!$found) {
            $list[] = ['Hook' => $Hook, 'TargetID' => $TargetID];
        }
        IPS_SetProperty($hookID, 'Hooks', json_encode($list));
        IPS_ApplyChanges($hookID);
    }
}
