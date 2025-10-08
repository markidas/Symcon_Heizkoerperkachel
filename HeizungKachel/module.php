<?php
declare(strict_types=1);

class HeizungKachel extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Daten-Eigenschaften
        $this->RegisterPropertyString('RoomName', 'Wohnzimmer');
        $this->RegisterPropertyInteger('VarIst', 0);
        $this->RegisterPropertyInteger('VarSoll', 0);   // mit Variablenaktion
        $this->RegisterPropertyInteger('VarStell', 0);
        $this->RegisterPropertyInteger('VarMode', 0);
        $this->RegisterPropertyInteger('Decimals', 1);

        // Design-Eigenschaften (NEU)
        $this->RegisterPropertyInteger('ArcWidth', 8);
        $this->RegisterPropertyString('ArcColorStart', '#3182CE'); // FG-Start
        $this->RegisterPropertyString('ArcColorEnd',   '#3182CE'); // FG-Ende (gleich = einfarbig)
        $this->RegisterPropertyString('ArcBgColor',    '#E2E8F0'); // Hintergrundbogen

        // HTML-SDK aktivieren (abwärtskompatibel)
        if (method_exists($this, 'SetVisualizationType')) {
            $this->SetVisualizationType(1);
        }

        // Fallback für Message-Deregistrierung
        $this->RegisterAttributeString('MsgIDs', '[]');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Alte Registrierungen entfernen
        $old = json_decode($this->ReadAttributeString('MsgIDs'), true);
        if (!is_array($old)) { $old = []; }
        foreach ($old as $oid) {
            @ $this->UnregisterMessage((int)$oid, VM_UPDATE);
        }

        // Neue Registrierungen
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

        // HTML initial befüllen
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
                        [ 'type' => 'SelectVariable', 'name' => 'VarMode',  'caption' => 'Betriebsart (String/Int mit Profil)' ]
                    ]
                ],
                [
                    'type'   => 'ExpansionPanel',
                    'caption'=> 'Design',
                    'items'  => [
                        [ 'type' => 'NumberSpinner',     'name' => 'ArcWidth',      'caption' => 'Bogenbreite (px)', 'minimum' => 2, 'maximum' => 24 ],
                        [ 'type' => 'ValidationTextBox', 'name' => 'ArcColorStart', 'caption' => 'Vordergrund Startfarbe (#RRGGBB)' ],
                        [ 'type' => 'ValidationTextBox', 'name' => 'ArcColorEnd',   'caption' => 'Vordergrund Endfarbe (#RRGGBB)' ],
                        [ 'type' => 'ValidationTextBox', 'name' => 'ArcBgColor',    'caption' => 'Hintergrundfarbe (#RRGGBB)' ]
                    ]
                ]
            ],
            'actions' => [
                [ 'type' => 'Button', 'caption' => 'Jetzt aktualisieren', 'onClick' => "IPS_RequestAction(\$id, 'Refresh', 0);" ],
                [ 'type' => 'Label',  'caption' => 'Hinweis: Die Soll-Temperatur-Variable braucht eine Variablenaktion, damit der Slider schreiben kann.' ]
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

        // Designwerte aus der Konfiguration
        $style = [
            'aw'  => max(2, (int)$this->ReadPropertyInteger('ArcWidth')),
            'fg1' => $this->sanitizeColor($this->ReadPropertyString('ArcColorStart')),
            'fg2' => $this->sanitizeColor($this->ReadPropertyString('ArcColorEnd')),
            'bg'  => $this->sanitizeColor($this->ReadPropertyString('ArcBgColor'))
        ];

        $data = [
            'ist'   => $idIst  ? round((float)GetValue($idIst),  $dec) : null,
            'soll'  => $idSoll ? round((float)GetValue($idSoll), $dec) : null,
            'stell' => $idSt   ? (float)GetValue($idSt) : null,
            'mode'  => $idMode ? GetValueFormatted($idMode) : null,
            'style' => $style
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
        return ($this->ReadPropertyInteger('VarIst')  > 0) &&
               ($this->ReadPropertyInteger('VarSoll') > 0);
    }

    private function sanitizeColor(string $c): string
    {
        $c = trim($c);
        if ($c === '') return '#000000';
        // akzeptiere #RGB, #RRGGBB, rgb(), rgba(), hsl() grundsätzlich
        if ($c[0] === '#') return strtoupper($c);
        return $c; // für rgb(...)/hsl(...) etc.
    }
}
