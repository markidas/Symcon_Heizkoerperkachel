<?php
declare(strict_types=1);

class HeatingTile2 extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Variablen-Properties (per Objektbaum)
        $this->RegisterPropertyInteger('ActualTempVarID', 0);    // Float °C
        $this->RegisterPropertyInteger('SetpointVarID', 0);      // Float °C
        $this->RegisterPropertyInteger('ValvePercentVarID', 0);  // Float/Int 0..100 (Anzeige)
        $this->RegisterPropertyInteger('ModeVarID', 0);          // Int (Enum)

        // Anzeige-Parameter
        $this->RegisterPropertyFloat('SetpointStep', 0.5);
        $this->RegisterPropertyInteger('Decimals', 1);

        // HTMLBox für die Kachel
        if (!$this->GetIDForIdentSafely('Tile')) {
            $this->RegisterVariableString('Tile', 'Heizung', '~HTMLBox', 0);
            IPS_SetHidden($this->GetIDForIdent('Tile'), false);
        }
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        foreach ([
            $this->ReadPropertyInteger('ActualTempVarID'),
            $this->ReadPropertyInteger('SetpointVarID'),
            $this->ReadPropertyInteger('ValvePercentVarID'),
            $this->ReadPropertyInteger('ModeVarID'),
        ] as $vid) {
            if ($vid > 0 && IPS_VariableExists($vid)) {
                $this->RegisterMessage($vid, VM_UPDATE);
            }
        }

        $this->Render();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->Render();
        }
    }

    public function Render(): void
    {
        $dec = max(0, intval($this->ReadPropertyInteger('Decimals')));

        $A = $this->safeRound($this->readVarFloatOrNull($this->ReadPropertyInteger('ActualTempVarID')), $dec, 0.0);
        $S = $this->safeRound($this->readVarFloatOrNull($this->ReadPropertyInteger('SetpointVarID')), $dec, 0.0);

        $Vraw = $this->readVarFloatOrNull($this->ReadPropertyInteger('ValvePercentVarID'));
        $V = ($Vraw === null) ? 0 : max(0, min(100, intval(round($Vraw)))); // Anzeige 0..100 %

        $M = $this->readVarIntOrNull($this->ReadPropertyInteger('ModeVarID'));
        $M = ($M === null) ? 0 : $M;

        $html = $this->BuildHTML($A, $S, $V, $M);
        $tileId = $this->GetIDForIdent('Tile');
        if ($tileId) {
            SetValue($tileId, $html);
        }
    }

    private function BuildHTML(float $A, float $S, int $V, int $M): string
    {
        $iid      = $this->InstanceID;
        $decimals = (int)$this->ReadPropertyInteger('Decimals');
        $step     = (float)$this->ReadPropertyFloat('SetpointStep');

        // Aktive Actions: Sollwert/Mode
        $idS = (int)$this->ReadPropertyInteger('SetpointVarID');
        $idM = (int)$this->ReadPropertyInteger('ModeVarID');

        // --- Feste Farben (wie gewünscht) ---
        $COLOR_BG       = '#ffffff';   // Kartenhintergrund (hell, damit schwarze Schrift passt)
        $COLOR_TEXT     = '#000000';   // Primärtext SCHWARZ
        $COLOR_MUTED    = '#000000';   // Sekundärtext ebenfalls schwarz (leicht abgeschwächt via CSS-Opacity)
        $COLOR_ACCENT   = '#5DCAAC';   // Stellbogen + Knopf + aktiver Button
        $COLOR_ONACCENT = '#ffffff';   // Text auf aktivem Button = WEISS
        $COLOR_OUTLINE  = '#2b6d5f';   // leichte Kontur am Knopf
        $COLOR_BGARC    = '#386c64';   // Hintergrundbogen

        // --- Geometrie / gemeinsamer Mittelpunkt & Startwinkel mit globalem -90° Offset ---
        $cx = 150.0;
        $cy = 110.0; // so weit wie möglich nach oben (bei r=110 berührt unten die 220er ViewBox)
        $r  = 110.0;

        $angleOffset = -M_PI / 2; // -90° (CCW)

        // Hintergrundbogen: Start -135°, Ende +135°, beide mit Offset; 270° CW
        $startAng = -0.75 * M_PI + $angleOffset;
        $endAngBg =  0.75 * M_PI + $angleOffset;

        $bgStart = $this->polarToXY($cx, $cy, $r, $startAng);
        $bgEnd   = $this->polarToXY($cx, $cy, $r, $endAngBg);
        $bgLarge = 1; // 270°
        $bgSweep = 1; // clockwise

        $bgPath = sprintf(
            "M %.2f %.2f A %.2f %.2f 0 %d %d %.2f %.2f",
            $bgStart['x'], $bgStart['y'], $r, $r, $bgLarge, $bgSweep, $bgEnd['x'], $bgEnd['y']
        );

        // Stellbogen: gleicher Start, delta 0..270° CW ab Start (Offset bereits im startAng enthalten)
        $delta    = 1.5 * M_PI * ($V / 100.0);   // 0..1.5π
        $endAngFg = $startAng + $delta;
        $fgEnd    = $this->polarToXY($cx, $cy, $r, $endAngFg);
        $fgLarge  = ($V > 66.6667) ? 1 : 0;      // >180°
        $fgSweep  = 1;                           // clockwise

        $fgPath = sprintf(
            "M %.2f %.2f A %.2f %.2f 0 %d %d %.2f %.2f",
            $bgStart['x'], $bgStart['y'], $r, $r, $fgLarge, $fgSweep, $fgEnd['x'], $fgEnd['y']
        );

        // Knopf: exakt am Ende des Stellbogens (gleicher Kreis)
        $knobX = $fgEnd['x'];
        $knobY = $fgEnd['y'];

        return <<<HTML
<style>
/* --- Feste Farb-Variablen --- */
#ht-$iid {
  --bg: $COLOR_BG;
  --text: $COLOR_TEXT;
  --muted: $COLOR_MUTED;
  --accent: $COLOR_ACCENT;
  --onaccent: $COLOR_ONACCENT;
  --outline: $COLOR_OUTLINE;
  --bgarc: $COLOR_BGARC;

  /* Responsive Faktoren: werden per JS/ResizeObserver gesetzt */
  --scale: 1;
  --pad: 8px;
}

#ht-$iid { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial; color: var(--text); }
#ht-$iid .card { background: var(--bg); border-radius: calc(10px * var(--scale)); padding: var(--pad); }

/* Typo skalierend */
#ht-$iid .row { display: flex; align-items: center; justify-content: center; gap: calc(12px * var(--scale)); }
#ht-$iid .big { font-size: calc(26px * var(--scale)); font-weight: 700; }
#ht-$iid .mid { font-size: calc(16px * var(--scale)); font-weight: 600; opacity: .8; color: var(--text); }

/* Bogen & Knopf (Farben wie gewünscht) */
#ht-$iid .gBg    { stroke: var(--bgarc);  opacity: 1; }
#ht-$iid .gAccent{ stroke: var(--accent); }
#ht-$iid .knob   { fill: var(--accent); stroke: var(--outline); stroke-width: calc(3px * var(--scale)); filter: url(#glow-$iid); pointer-events: none; }

/* Buttons skalierend */
#ht-$iid button { 
  border: 0; 
  border-radius: calc(10px * var(--scale)); 
  padding: calc(6px * var(--scale)) calc(10px * var(--scale)); 
  font-size: calc(16px * var(--scale)); 
  cursor: pointer; 
  background: transparent; 
  color: var(--text); 
  font-weight: 700;
}
#ht-$iid .pill { padding: calc(6px * var(--scale)) calc(10px * var(--scale)); border-radius: calc(8px * var(--scale)); }

/* Status-Zeile skalierend */
#ht-$iid .status { display: grid; grid-template-columns: repeat(3, 1fr); gap: calc(10px * var(--scale)); margin-top: calc(6px * var(--scale)); }
#ht-$iid .status .item { 
  text-align: center; 
  border-radius: calc(10px * var(--scale)); 
  padding: calc(10px * var(--scale)) calc(8px * var(--scale)); 
  background: rgba(0,0,0,0.06);
}
#ht-$iid .status .item .pill { font-size: calc(14px * var(--scale)); }
#ht-$iid .status .item strong { display:block; font-size: calc(16px * var(--scale)); color: var(--text); }

/* Aktiver Status-Button: #5DCAAC + weiße Schrift */
#ht-$iid .status .item.active,
#ht-$iid .status .item .pill.active {
  background: var(--accent) !important; 
  color: var(--onaccent) !important;
}

/* SVG responsive */
#ht-$iid svg { width: 100%; height: auto; display:block; }
</style>

<div id="ht-$iid" class="card">
  <div class="gauge">
    <svg viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
      <defs>
        <!-- Glow für Knob -->
        <filter id="glow-$iid" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="3" result="b"/>
          <feMerge><feMergeNode in="b"/><feMergeNode in="SourceGraphic"/></feMerge>
        </filter>
        <!-- fester Verlauf (selbe Akzentfarbe, optisch minimaler Boost) -->
        <linearGradient id="grad-$iid" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%"   stop-color="$COLOR_ACCENT" stop-opacity="0.90"/>
          <stop offset="100%" stop-color="$COLOR_ACCENT" stop-opacity="1"/>
        </linearGradient>
      </defs>

      <!-- Hintergrundbogen (serverseitig berechnet, 270° CW) -->
      <path id="bg-$iid" class="gBg" d="$bgPath"
            fill="none" stroke-width="5" stroke-linecap="round"/>

      <!-- Stellbogen (serverseitig berechnet; deckungsgleich) -->
      <path id="fg-$iid" d="$fgPath" class="gAccent" fill="none"
            stroke="url(#grad-$iid)" stroke-width="10" stroke-linecap="round"/>

      <!-- Knopf (serverseitig gesetzt; Anzeige-only) -->
      <circle id="knob-$iid" class="knob" cx="{$knobX}" cy="{$knobY}" r="10"/>

      <!-- Texte: höher positioniert, dichter unterm Titel -->
      <foreignObject x="0" y="20" width="300" height="120">
        <div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;flex-direction:column;align-items:center;gap:calc(6px * var(--scale))">
          <div class="big" id="tActual-$iid" style="color:var(--text)">{$A}°C</div>
          <div class="mid" id="tValve-$iid">{$V}%</div>
          <div class="row">
            <button class="pill" onclick="HT$iid.dec()">−</button>
            <div class="mid" id="tSet-$iid" style="color:var(--text)">$S°C</div>
            <button class="pill" onclick="HT$iid.inc()">+</button>
          </div>
        </div>
      </foreignObject>
    </svg>
  </div>

  <div class="status" id="status-$iid">
    <div class="item" id="mKomfort-$iid" onclick="HT$iid.setMode(2)">
      <span class="pill">Komfort</span>
      <strong id="mKomfortVal-$iid">$S°C</strong>
    </div>
    <div class="item" id="mStandby-$iid" onclick="HT$iid.setMode(1)">
      <span class="pill">Standby</span>
      <strong>20,5°C</strong>
    </div>
    <div class="item" id="mFrost-$iid" onclick="HT$iid.setMode(0)">
      <span class="pill">Frost</span>
      <strong>5,0°C</strong>
    </div>
  </div>
</div>

<script>
(function(){
  var st  = { v: $V, s: $S, a: $A, m: $M };
  var VID = { setpoint: $idS, mode: $idM };
  var dec = $decimals, step = $step, iid = $iid;

  // Responsive: skaliere Schrift/Buttons anhand der Kachelbreite
  var host = document.getElementById('ht-' + iid);
  if (host && 'ResizeObserver' in window){
    var ro = new ResizeObserver(function(entries){
      for (const e of entries){
        var w = e.contentRect.width || host.clientWidth || 300;
        var scale = Math.max(0.7, Math.min(3.0, w / 300)); // Basis: 300px Breite
        var pad = Math.max(6, Math.round(w * 0.02));       // 2% Padding
        host.style.setProperty('--scale', scale);
        host.style.setProperty('--pad', pad + 'px');
      }
    });
    ro.observe(host);
  }

  async function rpc(method, params){
    try{
      var res = await fetch('/api/', {
        method: 'POST',
        headers: {'Content-Type':'application/json'},
        body: JSON.stringify({jsonrpc:'2.0', id:'ht-'+iid+'-'+Date.now(), method: method, params: params})
      });
      if(!res.ok) return null;
      var j = await res.json();
      return (j && j.result !== undefined) ? j.result : null;
    }catch(e){ return null; }
  }
  async function requestAction(varId, value){
    if(!varId || varId <= 0) return null;
    return await rpc('RequestAction', [varId, value]);
  }

  function updateTexts(){
    var ta = document.getElementById('tActual-' + iid);
    if (ta) ta.textContent = st.a.toFixed(dec) + '°C';
    var ts = document.getElementById('tSet-' + iid);
    if (ts) ts.textContent = st.s.toFixed(dec) + '°C';
    var tv = document.getElementById('tValve-' + iid);
    if (tv) tv.textContent = Math.round(st.v) + '%';

    var ids = [
      {id:'mKomfort', val:2},
      {id:'mStandby', val:1},
      {id:'mFrost',   val:0}
    ];
    ids.forEach(function(x){
      var el = document.getElementById(x.id + '-' + iid);
      if (el) el.classList.toggle('active', st.m === x.val);
      var pill = el ? el.querySelector('.pill') : null;
      if (pill) pill.classList.toggle('active', st.m === x.val);
    });
  }

  window['HT' + iid] = {
    _state: st,
    inc: async function(){
      var v = +(st.s + step).toFixed(dec);
      st.s = v; updateTexts();
      await requestAction(VID.setpoint, v);
    },
    dec: async function(){
      var v = +(st.s - step).toFixed(dec);
      st.s = v; updateTexts();
      await requestAction(VID.setpoint, v);
    },
    setMode: async function(m){
      st.m = m; updateTexts();
      await requestAction(VID.mode, m);
    }
  };

  updateTexts();
})();
</script>
HTML;
    }

    private function polarToXY(float $cx, float $cy, float $r, float $angle): array
    {
        return ['x' => $cx + $r * cos($angle), 'y' => $cy + $r * sin($angle)];
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type' => 'Label', 'caption' => 'Variablen aus dem Objektbaum auswählen'],
                // variableType: 2 = Float, 1 = Integer
                ['type' => 'SelectVariable', 'name' => 'ActualTempVarID',   'caption' => 'Ist-Temperatur (Float °C)',            'variableType' => 2],
                ['type' => 'SelectVariable', 'name' => 'SetpointVarID',     'caption' => 'Sollwert (Float °C)',                  'variableType' => 2],
                ['type' => 'SelectVariable', 'name' => 'ValvePercentVarID', 'caption' => 'Stellgröße/Valve (Float/Int 0..100%)', 'variableType' => 2],
                ['type' => 'SelectVariable', 'name' => 'ModeVarID',         'caption' => 'Betriebsart (Integer/Enum)',           'variableType' => 1],
                ['type' => 'NumberSpinner',  'name' => 'SetpointStep',      'caption' => 'Schrittweite Sollwert (°C)', 'digits' => 1, 'minimum' => 0.1],
                ['type' => 'NumberSpinner',  'name' => 'Decimals',          'caption' => 'Nachkommastellen Temperatur', 'minimum' => 0, 'maximum' => 2],
                ['type' => 'Label',          'caption' => 'Stellbogen ist Anzeige-only. ± ändert den Sollwert, Status-Buttons schalten die Betriebsart.']
            ]
        ]);
    }

    // --- Safe helpers ---
    private function readVarFloatOrNull(int $varId): ?float
    {
        if ($varId <= 0 || !IPS_VariableExists($varId)) return null;
        $v = IPS_GetVariable($varId);
        $type = $v['VariableType']; // 0:Bool, 1:Int, 2:Float, 3:String
        $val = GetValue($varId);
        if ($type === 2 || $type === 1) return is_numeric($val) ? floatval($val) : null;
        $this->SendDebug('HeatingTile2', "Variablentyp (ID $varId) ist $type, erwartet Float/Int", 0);
        return null;
    }

    private function readVarIntOrNull(int $varId): ?int
    {
        if ($varId <= 0 || !IPS_VariableExists($varId)) return null;
        $v = IPS_GetVariable($varId);
        $type = $v['VariableType'];
        $val = GetValue($varId);
        if ($type === 1 || $type === 2) return is_numeric($val) ? intval($val) : null;
        $this->SendDebug('HeatingTile2', "Variablentyp (ID $varId) ist $type, erwartet Int/Float", 0);
        return null;
    }

    private function safeRound(?float $num, int $dec, float $fallback = 0.0): float
    {
        if ($num === null) return $fallback;
        return round($num, $dec);
    }

    private function GetIDForIdentSafely(string $ident): int
    {
        $id = @$this->GetIDForIdent($ident);
        return is_int($id) ? $id : 0;
    }
}
