<?php
declare(strict_types=1);

class HeizungKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Eigenschaften (per Formular änderbar)
        $this->RegisterPropertyString('RoomName', 'Wohnzimmer');
        $this->RegisterPropertyInteger('VarIst', 0);     // Float
        $this->RegisterPropertyInteger('VarSoll', 0);    // Float (mit Variablenaktion)
        $this->RegisterPropertyInteger('VarStell', 0);   // Float/Int 0..100
        $this->RegisterPropertyInteger('VarMode', 0);    // String ODER Int (mit Profil/Assoziationen)
        $this->RegisterPropertyInteger('Decimals', 1);   // Nachkommastellen

        // HTML-SDK aktivieren (abwärtskompatibel)
        if (method_exists($this, 'SetVisualizationType')) {
            $this->SetVisualizationType(1); // 1 = HTML
        }

        // Merkliste für Message-Registrierungen (Fallback)
        $this->RegisterAttributeString('MsgIDs', '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // alte Registrierungen entfernen (Fallback statt UnregisterMessageAll)
        $old = json_decode($this->ReadAttributeString('MsgIDs'), true);
        if (!is_array($old)) { $old = []; }
        foreach ($old as $oid) {
            @ $this->UnregisterMessage((int)$oid, VM_UPDATE);
        }

        // neue Registrierungen + Referenzen
        $newMsgIDs = [];
        foreach (['VarIst','VarSoll','VarStell','VarMode'] as $prop) {
            $id = (int)$this->ReadPropertyInteger($prop);
            if ($id > 0) {
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterReference($id);
                $newMsgIDs[] = $id;
            }
        }
        $this->WriteAttributeString('MsgIDs', json_encode(array_values(array_unique($newMsgIDs))));

        // Status
        $this->SetStatus($this->isConfigured() ? 102 : 104);

        // Daten pushen
        $this->PushState();
    }

    public function GetConfigurationForm()
    {
        $form = [
            'elements' => [
                [
                    'type'   => 'ExpansionPanel',
                    'caption'=> 'Allgemein',
                    'items'  => [
                        [ 'type' => 'ValidationTextBox', 'name' => 'RoomName', 'caption' => 'Raumname' ],
                        [ 'type' => 'NumberSpinner',     'name' => 'Decimals', 'caption' => 'Nachkommastellen', 'minimum' => 0, 'maximum' => 2 ]
                    ]
                ],
                [
                    'type'   => 'ExpansionPanel',
                    'caption'=> 'Variablen',
                    'items'  => [
                        [ 'type' => 'SelectVariable', 'name' => 'VarIst',   'caption' => 'Ist-Temperatur (Float)',              'variableType' => 2 ],
                        [ 'type' => 'SelectVariable', 'name' => 'VarSoll',  'caption' => 'Soll-Temperatur (Float, mit Aktion)', 'variableType' => 2 ],
                        [ 'type' => 'SelectVariable', 'name' => 'VarStell', 'caption' => 'Stellgröße 0–100 % (Float/Int)' ],
                        // kein variableType -> erlaubt String ODER Integer
                        [ 'type' => 'SelectVariable', 'name' => 'VarMode',  'caption' => 'Betriebsart (String oder Int mit Profil)' ]
                    ]
                ]
            ],
            'actions' => [
                [ 'type' => 'Button', 'caption' => 'Jetzt aktualisieren', 'onClick' => "IPS_RequestAction(\$id, 'Refresh', 0);" ],
                [ 'type' => 'Label',  'caption' => 'Hinweis: Die Soll-Temperatur-Variable sollte eine Variablenaktion besitzen, damit +/- in der Kachel funktioniert.' ]
            ],
            'status' => [
                [ 'code' => 101, 'icon' => 'inactive', 'caption' => 'Wird erstellt' ],
                [ 'code' => 102, 'icon' => 'active',   'caption' => 'Aktiv' ],
                [ 'code' => 104, 'icon' => 'error',    'caption' => 'Bitte Variablen auswählen (mind. Ist & Soll).' ]
            ]
        ];
        return json_encode($form);
    }

    public function GetVisualizationTile(): string
    {
        return file_get_contents(__DIR__ . '/module.html');
    }

    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Setpoint':
                $var = (int)$this->ReadPropertyInteger('VarSoll');
                if ($var > 0) {
                    @RequestAction($var, (float)$Value);
                }
                $this->PushState();
                break;

            case 'Refresh':
                $this->PushState();
                break;
        }
    }

    private function PushState(): void
{
    $dec    = max(0, (int)$this->ReadPropertyInteger('Decimals'));
    $idIst  = (int)$this->ReadPropertyInteger('VarIst');
    $idSoll = (int)$this->ReadPropertyInteger('VarSoll');
    $idSt   = (int)$this->ReadPropertyInteger('VarStell');
    $idMode = (int)$this->ReadPropertyInteger('VarMode');

    $data = [
        'ist'   => $idIst  ? round((float)GetValue($idIst),  $dec) : null,
        'soll'  => $idSoll ? round((float)GetValue($idSoll), $dec) : null,
        'stell' => $idSt   ? (float)GetValue($idSt) : null,
        'mode'  => $idMode ? GetValueFormatted($idMode) : null
    ];
    $this->UpdateVisualizationValue(json_encode($data));
}



    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->PushState();
        }
    }

    private function isConfigured(): bool
    {
        // Mindestens Ist & Soll müssen gesetzt sein
        return ($this->ReadPropertyInteger('VarIst')  > 0) &&
               ($this->ReadPropertyInteger('VarSoll') > 0);
    }
}
