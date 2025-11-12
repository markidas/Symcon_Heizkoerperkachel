<?php
declare(strict_types=1);

class HeatingTile2 extends IPSModule
{
    public function Create()
    {
        parent::Create();

        // Konfigurations-Properties (per Objektbaum wählbar)
        $this->RegisterPropertyInteger('ActualTempVarID', 0);    // Float °C
        $this->RegisterPropertyInteger('SetpointVarID', 0);      // Float °C
        $this->RegisterPropertyInteger('ValvePercentVarID', 0);  // Float/Int 0..100 (nur Anzeige)
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

        // Auf Variablen-Updates hören (nur existierende)
        $watch = [
            $this->ReadPropertyInteger('ActualTempVarID'),
            $this->ReadPropertyInteger('SetpointVarID'),
            $this->ReadPropertyInteger('ValvePercentVarID'),
            $this->ReadPropertyInteger('ModeVarID'),
        ];
        foreach ($watch as $vid) {
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

    /* ===========================
       Rendering
       =========================== */

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

    /* ==========
       HTML/JS
       ========== */

    private function BuildHTML(float $A, float $S, int $V, int $M): string
    {
        $iid  = $this->InstanceID;
        $decimals = (int)$this->ReadPropertyInteger('Decimals');
        $step = (float)$this->ReadPropertyFloat('SetpointStep');

        // Variablen-IDs für RequestAction (nur Setpoint & Mode sind aktiv)
        $idS = (int)$this->ReadPropertyInteger('SetpointVarID');
        $idM = (int)$this->ReadPropertyInteger('ModeVarID');

        // --- Serverseitig Stellbogen & Knob berechnen (Anzeige-only) ---
        $cx = 150.0; $cy = 180.0; $r = 110.0;
        $startAng = -0.75 * M_PI;
        $endAng   = $this->angleForPercent($V);
        $startPt  = $this->polarToXY($cx, $cy, $r, $startAng);
        $endPt    = $this->polarToXY($cx, $cy, $r, $endAng);
        $largeArc = ($V > 50) ? 1 : 0;

        $arcPath = sprintf(
            "M %.2f %.2f A %.2f %.2f 0 %d 1 %.2f %.2f",
            $startPt['x'], $startPt['y'], $r, $r, $largeArc, $endPt['x'], $endPt['y']
        );
        $knobX = $endPt['x'];
        $knobY = $endPt['y'];

        // Hinweis: im HEREDOC keine JS-Template-Literals (`${...}`) verwenden.
        return <<<HTML
<style>
#ht-$iid { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
#ht-$iid { width: 100%; height: auto; color: var(--text-color, var(--foreground, #ffffff)); }
#ht-$iid .card { background: var(--background-color, var(--surface, #2f2f35)); border-radius: 12px; padding: clamp(8px, 2.2vw, 18px); }
#ht-$iid .row { display: flex; align-items: center; justify-content: center; gap: 1rem; }
#ht-$iid .big { font-size: clamp(18px, 6.8vw, 40px); font-weight: 600; }
#ht-$iid .mid { font-size: clamp(12px, 3.6vw, 22px); font-weight: 500; opacity: .9; }
#ht-$iid .muted { opacity: .7; }

/* Sichtbarkeit/Contrast des Reglers */
#ht-$iid .gAccent { stroke: var(--accent-color, #ff5a00); } /* fallback: Orange */
#ht-$iid .gBg { stroke: color-mix(in oklab, var(--accent-color, #ff5a00), #000 80%); opacity: .40; }
#ht-$iid .knob {
  fill: var(--accent-color, #ff5a00);
  stroke: color-mix(in oklab, var(--background-color, #0f172a), #000 80%);
  stroke-width: 4;
  filter: url(#glow-$iid);
  pointer-events: none; /* Anzeige: nicht klick-/draggable */
}

/* Buttons */
#ht-$iid button { border: 0; border-radius: 10px; padding: .4em .7em; font-size: clamp(12px, 3.5vw, 18px); cursor: pointer; background: transparent; color: inherit; }
#ht-$iid .pill { padding: .5em .6em; border-radius: 8px; }
#ht-$iid .active { background: var(--accent-color, #ff5a00); color: var(--on-accent, #000); }

#ht-$iid .status { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin-top: .5rem; }
#ht-$iid .status .item { text-align: center; border-radius: 10px; padding: .45rem .5rem; background: color-mix(in oklab, var(--background-color, #2f2f35), #fff 6%); }
#ht-$iid .status .item strong { display:block; }

#ht-$iid svg { width: 100%; height: auto; }
</style>

<div id="ht-$iid" class="card" style="--accent-color:#ff5a00; --on-accent:#000;">
  <div class="gauge">
    <svg viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet" aria-hidden="true">
      <defs>
        <!-- Glow für Knob -->
        <filter id="glow-$iid" x="-50%" y="-50%" width="200%" height="200%">
          <feGaussianBlur stdDeviation="3" result="b"/>
          <feMerge>
            <feMergeNode in="b"/>
            <feMergeNode in="SourceGraphic"/>
          </feMerge>
        </filter>
        <!-- Verlauf für Stellbogen -->
        <linearGradient id="grad-$iid" x1="0%" y1="0%" x2="100%" y2="0%">
          <stop offset="0%"   stop-color="var(--accent-color, #ff5a00)" stop-opacity="0.85"/>
          <stop offset="100%" stop-color="var(--accent-color, #ff5a00)" stop-opacity="1"/>
        </linearGradient>
      </defs>

      <!-- Hintergrundbogen (3/4-Kreis) -->
      <path id="bg-$iid" class="gBg" d="M 60 180 A 110 110 0 1 1 240 180"
            fill="none" stroke-width="14" stroke-linecap="round"/>

      <!-- Stellbogen (serverseitig fertig) -->
      <path id="fg-$iid" d="$arcPath" class="gAccent" fill="none"
            stroke="url(#grad-$iid)" stroke-width="20" stroke-linecap="round"/>

      <!-- Knopf (serverseitig gesetzt; Anzeige-only) -->
      <circle id="knob-$iid" class="knob" cx="{$knobX}" cy="{$knobY}" r="16"/>
      <!-- Keine Hit-Fläche, keine Pointer-Events -->
      
      <!-- Texte -->
      <foreignObject x="0" y="70" width="300" height="140">
        <div xmlns="http://www.w3.org/1999/xhtml" style="display:flex;flex-direction:column;align-items:center;gap:.4rem">
          <div class="big" id="tActual-$iid">{$A}°C</div>
          <div class="mid muted" id="tValve-$iid">{$V}%</div>
          <div class="row">
            <button class="pill" onclick="HT$iid.dec()">−</button>
            <div class="mid" id="tSet-$iid">{$S}°C</div>
            <button class="pill" onclick="HT$iid.inc()">+</button>
          </div>
        </div>
      </foreignObject>
    </svg>
  </div>

  <div class="status">
    <div class="item" id="mKomfort-$iid" onclick="HT$iid.setMode(2)">
      <span class="pill">Komfort</span>
      <strong id="mKomfortVal-$iid">{$S}°C</strong>
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
// Anzeige-only: kein Drag, kein RequestAction für Ventil
(function(){
  var st = { v: $V, s: $S, a: $A, m: $M };
  var VID = { setpoint: $idS, mode: $idM };
  var dec = $decimals, step = $step, iid = $iid;

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

    var ids = ['mKomfort','mStandby','mFrost'];
    for (var k=0;k<ids.length;k++){
      var pill = document.querySelector('#' + ids[k] + '-' + iid + ' .pill');
      var active = (st.m === (k===0?2:(k===1?1:0)));
      if (pill) pill.classList.toggle('active', active);
    }
  }

  // Buttons (nur Setpoint & Mode)
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

  updateTexts(); // nur Texte nachziehen (Bogen/Knob sind serverseitig vorgezeichnet)
})();
</script>
HTML;
    }

    /* ===========================
       Mathe-Helper (serverseitig)
       =========================== */
    private function angleForPercent(float $p): float
    {
        // 0..100% auf -135° .. +135° (in Radiant)
        return (-0.75 * M_PI) + (1.5 * M_PI * ($p / 100.0));
    }

    private function polarToXY(float $cx, float $cy, float $r, float $angle): array
    {
        return [
            'x' => $cx + $r * cos($angle),
            'y' => $cy + $r * sin($angle)
        ];
    }

    /* ===========================
       Konfigurationsformular
       =========================== */
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
                ['type' => 'Label',          'caption' => 'Hinweis: Stellbogen ist Anzeige-only. ± ändert den Sollwert, Status-Buttons schalten die Betriebsart.']
            ]
        ]);
    }

    /* ===========================
       Safe helpers
       =========================== */

    private function readVarFloatOrNull(int $varId): ?float
    {
        if ($varId <= 0 || !IPS_VariableExists($varId)) {
            return null;
        }
        $v = IPS_GetVariable($varId);
        $type = $v['VariableType']; // 0:Bool, 1:Int, 2:Float, 3:String
        $val = GetValue($varId);

        if ($type === 2 || $type === 1) { // Float ODER Int
            return is_numeric($val) ? floatval($val) : null;
        }
        $this->SendDebug('HeatingTile2', "Variablentyp (ID $varId) ist $type, erwartet Float/Int", 0);
        return null;
    }

    private function readVarIntOrNull(int $varId): ?int
    {
        if ($varId <= 0 || !IPS_VariableExists($varId)) {
            return null;
        }
        $v = IPS_GetVariable($varId);
        $type = $v['VariableType'];
        $val = GetValue($varId);

        if ($type === 1 || $type === 2) { // Int oder Float
            return is_numeric($val) ? intval($val) : null;
        }
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
