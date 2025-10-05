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
        $this->RegisterPropertyInteger('VarFenster', 0); // Bool
        $this->RegisterPropertyInteger('Decimals', 1);   // Nachkommastellen

        // HTML-SDK aktivieren (nur aufrufen, wenn vorhanden – abwärtskompatibel)
        if (method_exists($this, 'SetVisualizationType')) {
            $this->SetVisualizationType(1); // 1 = HTML
        }

        // Merkliste für Message-Registrierungen (Fallback zur Deregistrierung)
        $this->RegisterAttributeString('MsgIDs', '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // 1) Vorherige Message-Registrierungen sauber entfernen (Fallback statt UnregisterMessageAll)
        $old = json_decode($this->ReadAttributeString('MsgIDs'), true);
        if (!is_array($old)) {
            $old = [];
        }
        foreach ($old as $oid) {
            @ $this->UnregisterMessage((int)$oid, VM_UPDATE);
        }

        // 2) Neue Registrierungen + Referenzen setzen
        $newMsgIDs = [];
        foreach (['VarIst','VarSoll','VarStell','VarFenster'] as $prop) {
            $id = (int)$this->ReadPropertyInteger($prop);
            if ($id > 0) {
                $this->RegisterMessage($id, VM_UPDATE);
                $this->RegisterReference($id);
                $newMsgIDs[] = $id;
            }
        }
        // Merkliste speichern (für nächste ApplyChanges-Runde)
        $this->WriteAttributeString('MsgIDs', json_encode(array_values(array_unique($newMsgIDs))));

        // 3) Status setzen (sichtbar in der Konsole)
        if (!$this->isConfigured()) {
            $this->SetStatus(104); // Konfiguration unvollständig
        } else {
            $this->SetStatus(102); // Aktiv
        }

        // 4) Initiale Daten an die Kachel pushen
        $this->PushState();
    }

    /** Formular für die Instanzeigenschaften (Konsole) */
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
                        [ 'type' => 'SelectVariable', 'name' => 'VarIst',    'caption' => 'Ist-Temperatur (Float)',            'variableType' => 2 ],
                        [ 'type' => 'SelectVariable', 'name' => 'VarSoll',   'caption' => 'Soll-Temperatur (Float, mit Aktion)','variableType' => 2 ],
                        [ 'type' => 'SelectVariable', 'name' => 'VarStell',  'caption' => 'Stellgröße 0–100 % (Float/Int)',     'variableType' => 2 ],
                        [ 'type' => 'SelectVariable', 'name' => 'VarFenster','caption' => 'Fensterkontakt (Bool)',             'variableType' => 0 ]
                    ]
                ]
            ],
            'actions' => [
                [ 'type' => 'Button', 'caption' => 'Jetzt aktualisieren', 'onClick' => "IPS_RequestAction(\$id, 'Refresh', null);" ],
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

    /** Initiales HTML für die Kachel */
    public function GetVisualizationTile(): string
    {
        return file_get_contents(__DIR__ . '/module.html');
    }

    /** Frontend -> Modul (Buttons/Events in module.html) */
    public function RequestAction($Ident, $Value)
    {
        switch ($Ident) {
            case 'Setpoint': // Solltemperatur setzen
                $var = (int)$this->ReadPropertyInteger('VarSoll');
                if ($var > 0) {
                    @RequestAction($var, (float)$Value); // nutzt ggf. die Variablenaktion der Zielvariable
                }
                $this->PushState();
                break;

            case 'Refresh':  // Daten neu senden
                $this->PushState();
                break;
        }
    }

    /** Modul -> Frontend: aktuelle Werte senden */
    private function PushState(): void
    {
        $room   = $this->ReadPropertyString('RoomName');
        $dec    = max(0, (int)$this->ReadPropertyInteger('Decimals'));
        $idIst  = (int)$this->ReadPropertyInteger('VarIst');
        $idSoll = (int)$this->ReadPropertyInteger('VarSoll');
        $idSt   = (int)$this->ReadPropertyInteger('VarStell');
        $idFen  = (int)$this->ReadPropertyInteger('VarFenster');

        $data = [
            'room'    => $room,
            'ist'     => $idIst  ? round((float)GetValue($idIst),  $dec) : null,
            'soll'    => $idSoll ? round((float)GetValue($idSoll), $dec) : null,
            'stell'   => $idSt   ? (float)GetValue($idSt) : null,
            'fenster' => $idFen  ? (bool)GetValue($idFen) : null,
            'updated' => date('d.m.Y H:i:s')
        ];

        $this->UpdateVisualizationValue(json_encode($data));
    }

    /** Variablenänderungen aus Symcon */
    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->PushState();
        }
    }

    private function isConfigured(): bool
    {
        // Mindestens Ist & Soll sollten gesetzt sein
        return ($this->ReadPropertyInteger('VarIst')  > 0) &&
               ($this->ReadPropertyInteger('VarSoll') > 0);
    }
}

