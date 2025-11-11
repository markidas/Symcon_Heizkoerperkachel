<?php
declare(strict_types=1);

class HeatingTile2 extends IPSModule
{
    public function Create()
    {
        parent::Create();
        // Properties für die Variablen
        $this->RegisterPropertyInteger('VarID_TempIst', 0);
        $this->RegisterPropertyInteger('VarID_TempSoll', 0);
        $this->RegisterPropertyInteger('VarID_ValvePercent', 0);
        $this->RegisterPropertyInteger('VarID_Mode', 0);

        // Ausgabe-Buffer
        $this->RegisterBuffer('HTML', '');
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Auf Variablen-Änderungen reagieren
        $this->MaintainVariable('HTML', 'Anzeige', vtString, '', 1, true);

        $ids = [
            'ist'   => $this->ReadPropertyInteger('VarID_TempIst'),
            'soll'  => $this->ReadPropertyInteger('VarID_TempSoll'),
            'vent'  => $this->ReadPropertyInteger('VarID_ValvePercent'),
            'mode'  => $this->ReadPropertyInteger('VarID_Mode')
        ];

        $values = $this->SafeRead($ids);
        $html   = $this->BuildHTML($values);
        SetValueString($this->GetIDForIdent('HTML'), $html);

        // Ereignisse zuordnen
        $this->RegisterMessage($ids['ist'], VM_UPDATE);
        $this->RegisterMessage($ids['soll'], VM_UPDATE);
        $this->RegisterMessage($ids['vent'], VM_UPDATE);
        $this->RegisterMessage($ids['mode'], VM_UPDATE);
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            // Neu rendern
            $ids = [
                'ist'   => $this->ReadPropertyInteger('VarID_TempIst'),
                'soll'  => $this->ReadPropertyInteger('VarID_TempSoll'),
                'vent'  => $this->ReadPropertyInteger('VarID_ValvePercent'),
                'mode'  => $this->ReadPropertyInteger('VarID_Mode')
            ];
            $values = $this->SafeRead($ids);
            $html   = $this->BuildHTML($values);
            SetValueString($this->GetIDForIdent('HTML'), $html);
        }
    }

    private function SafeRead(array $ids): array
    {
        $out = ['ist' => null, 'soll' => null, 'vent' => null, 'mode' => null];

        foreach ($ids as $k => $id) {
            if ($id > 0 && IPS_VariableExists($id)) {
                $v = GetValue($id);
                // Typ-sicher runden/konvertieren
                if (in_array($k, ['ist', 'soll'])) {
                    $out[$k] = is_numeric($v) ? round((float)$v, 1) : null;
                } elseif ($k === 'vent') {
                    $out[$k] = is_numeric($v) ? max(0, min(100, (int)$v)) : null;
                } else {
                    $out[$k] = is_numeric($v) ? (int)$v : null;
                }
            }
        }
        return $out;
    }

    private function BuildHTML(array $v): string
    {
        // System-/Skinfarben aus Symcon-Theme via CSS-Variablen:
        // --theme-fg, --theme-bg, --theme-accent (Fallbacks definiert)
        $css = <<<CSS
<style>
:root{
  --fg: var(--theme-fg, #e5e7eb);
  --bg: var(--theme-bg, #111827);
  --muted: var(--theme-muted, #9ca3af);
  --accent: var(--theme-accent, #3b82f6);
  --ok: var(--theme-ok, #10b981);
  --warn: var(--theme-warn, #f59e0b);
}
.ht2 { font-family: system-ui, -apple-system, Segoe UI, Roboto, Arial; color: var(--fg); background: transparent; width: 100%; height: 100%; }
.ht2 * { box-sizing: border-box; }
.ht2 .wrap { width: 100%; height: 100%; display: grid; place-items: center; padding: 4%; }
.ht2 .tile { width: 100%; height: 100%; aspect-ratio: 1 / 1; position: relative; }
.ht2 .t-istsoll { position: absolute; top: 10%; width: 100%; text-align: center; }
.ht2 .t-istsoll .ist { font-weight: 600; }
.ht2 .t-istsoll .soll { margin-top: 0.2em; }
.ht2 .ring { position: absolute; inset: 10% 10% 20% 10%; }
.ht2 .statuses { position: absolute; bottom: 6%; width: 100%; display: flex; gap: 0.6em; justify-content: center; flex-wrap: wrap; }
.ht2 .status { padding: .35em .6em; border-radius: .8em; background: rgba(255,255,255,.06); color: var(--muted); cursor: pointer; user-select: none; }
.ht2 .status.active { background: var(--accent); color: white; }
@container size (min-width:160px){
  .ht2 .ist { font-size: clamp(16px, 14cqi, 36px); }
  .ht2 .soll { font-size: clamp(12px, 9cqi, 20px); }
  .ht2 .statuses { font-size: clamp(10px, 6cqi, 16px); }
}
</style>
CSS;

        // Werte absichern
        $ist  = $v['ist']  ?? 0.0;
        $soll = $v['soll'] ?? 0.0;
        $vent = $v['vent'] ?? 0; // 0..100
        // 3/4-Kreis + dicker Abschnitt + Punkt
        $svg = $this->BuildGaugeSVG($vent);

        $html = <<<HTML
<div class="ht2">
  <div class="wrap">
    <div class="tile">
      <div class="t-istsoll">
        <div class="ist">{$ist}°C</div>
        <div class="soll">{$soll}°C</div>
      </div>
      <div class="ring">{$svg}</div>
      <div class="statuses">
        <div class="status">Auto</div>
        <div class="status">Manuell</div>
        <div class="status">Aus</div>
      </div>
    </div>
  </div>
</div>
HTML;

        return $css . $html;
    }

    private function BuildGaugeSVG(int $percent): string
    {
        // 3/4-Kreis (270°), Start bei 225°, Ende bei 135° (oben offen)
        $size = 100; $stroke = 10;
        $r = ($size - $stroke) / 2;
        $cx = $cy = $size / 2;
        $circ = 2 * M_PI * $r * 0.75; // 3/4 Umfang
        $filled = $circ * ($percent / 100);
        $gap = $circ - $filled;

        return sprintf(
            '<svg viewBox="0 0 %1$d %1$d" preserveAspectRatio="xMidYMid meet" style="width:100%%;height:100%%;display:block;">
  <g transform="rotate(225 %2$f %2$f)">
    <circle cx="%2$f" cy="%2$f" r="%3$f" fill="none" stroke="rgba(255,255,255,.15)" stroke-width="%4$f"
            stroke-dasharray="%5$f %5$f" stroke-dashoffset="0" pathLength="1"/>
    <circle cx="%2$f" cy="%2$f" r="%3$f" fill="none" stroke="var(--accent)" stroke-linecap="round" stroke-width="%4$f"
            stroke-dasharray="%6$f %7$f" stroke-dashoffset="0" pathLength="%8$f"/>
    <circle cx="%2$f" cy="%2$f" r="%3$f" fill="none" stroke="var(--accent)" stroke-width="%4$f"
            stroke-dasharray="0.001 %8$f" stroke-dashoffset="%9$f" pathLength="%8$f"/>
  </g>
</svg>',
            $size, $cx, $r, $stroke,
            0.75,                // Hintergrund-Kreis auf 3/4
            $filled, $gap, $circ,
            max(0.0, $circ - 0.001) // Punkt am Ende
        );
    }
}
