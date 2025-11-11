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
        $this->RegisterPropertyInteger('ValvePercentVarID', 0);  // Float/Int 0..100
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
        $V = ($Vraw === null) ? 0 : max(0, min(100, intval(round($Vraw))));

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
        $iid  = $this->InstanceID;
        $decimals = (int)$this->ReadPropertyInteger('Decimals');
        $step = (float)$this->ReadPropertyFloat('SetpointStep');

        // Variablen-IDs für RequestAction
        $idA = (int)$this->ReadPropertyInteger('ActualTempVarID');
        $idS = (int)$this->ReadPropertyInteger('SetpointVarID');
        $idV = (int)$this->ReadPropertyInteger('ValvePercentVarID');
        $idM = (int)$this->ReadPropertyInteger('ModeVarID');

        // Wichtig: im HEREDOC keine JS-Template-Literals (`${...}`) verwenden.
        return <<<HTML
<style>
#ht-$iid { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
#ht-$iid { width: 100%; height: auto; color: var(--text-color, var(--foreground, #ffffff)); }
#ht-$iid .card { background: var(--background-color, var(--surface, #2f2f35)); border-radius: 12px; padding: clamp(8px, 2.2vw, 18px); }
#ht-$iid .row { display: flex; align-items: center; justify-content: center; gap: 1rem; }
#ht-$iid .big { font-size: clamp(18px, 6.8vw, 40px); font-weight: 600; }
#ht-$iid .mid { font-size: clamp(12px, 3.6vw, 22px); font-weight: 500; opacity: .9; }
#ht-$iid .muted { opacity: .7; }

/* Farben/Stile für Schieberegler & Bogen */
#ht-$iid .gAccent { stroke: var(--accent-color, var(--primary, #1fd1b2)); }
#ht-$iid .gBg { stroke: color-mix(in oklab, var(--accent-color, #1fd1b2), #000 80%); opacity: .35; }
#ht-$iid .knob {
  fill: var(--accent-color, var(--primary, #1fd1b2));
  stroke: var(--tile-bg-contrast, #000);
  stroke-width: 3;
  filter: url(#glow-$iid);
}

/* Buttons */
#ht-$iid button { border: 0; border-radius: 10px; padding: .4em .7em; font-size: clamp(12px, 3.5vw, 18px); cursor: pointer; background: transparent; color: inherit; }
#ht-$iid .pill { padding: .5em .6em; border-radius: 8px; }
#ht-$iid .active { background: var(--accent-color, var(--primary, #1fd1b2)); color: var(--on-accent, #000); }

#ht-$iid .status { display: grid; grid-template-columns: repeat(3, 1fr); gap: .6rem; margin-top: .5rem; }
#ht-$iid .status .item { text-align: center; border-radius: 10px; padding: .45rem .5rem; background: color-mix(in oklab, var(--background-color, #2f2f35), #fff 6%); }
#ht-$iid .status .item strong { display:block; }

#ht-$iid svg { width: 100%; height: auto; }
</style>

<div id="ht-$iid" class="card">
  <div class="gauge">
    <svg viewBox="0 0 300 220" preserveAspectRatio="xMidYMid meet">
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
          <stop offset="0%"   stop-color="var(--accent-color, #1fd1b2)" stop-opacity="0.85"/>
          <stop offset="100%" stop-color="var(--accent-color, #1fd1b2)" stop-opacity="1"/>
        </linearGradient>
      </defs>

      <!-- Hintergrundbogen -->
      <path id="bg-$iid" class="gBg" d="M 60 180 A 110 110 0 1 1 240 180"
            fill="none" stroke-width="12" stroke-linecap="round"/>
      <!-- Stellbogen (wird per JS gesetzt) -->
      <path id="fg-$iid" d="" class="gAccent" fill="none"
            stroke="url(#grad-$iid)" stroke-width="16" stroke-linecap="round"/>
      <!-- Drag-Knob (sichtbar) -->
      <circle id="knob-$iid" class="knob" cx="240" cy="180" r="13"/>
      <!-- Unsichtbare Trefferfläche für leichteres Draggen -->
      <circle id="knobHit-$iid" cx="240" cy="180" r="22" fill="transparent" pointer-events="all"/>
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
(function(){
  // Zustand
  var st = { v: $V, s: $S, a: $A, m: $M };

  // Variablen-IDs (RequestAction)
  var VID = { actual: $idA, setpoint: $idS, valve: $idV, mode: $idM };

  var dec = $decimals;
  var step = $step;
  var iid = $iid;

  // JSON-RPC auf /api/
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

  // Geometrie
  var PI = Math.PI;
  var cx = 150, cy = 180, r = 110;

  function polarToXY(angle){ return { x: cx + r * Math.cos(angle), y: cy + r * Math.sin(angle) }; }
  function angleForPercent(p){ return (-0.75*PI) + ((1.5*PI) * (p/100)); }
  function arcPath(pct){
    var a = angleForPercent(pct);
    var p1 = polarToXY(-0.75*PI);
    var p2 = polarToXY(a);
    var large = (pct > 50) ? 1 : 0;
    return 'M ' + p1.x + ' ' + p1.y + ' A ' + r + ' ' + r + ' 0 ' + large + ' 1 ' + p2.x + ' ' + p2.y;
  }

  function updateGauge(){
    // Bogen + Knob
    document.getElementById('fg-' + iid).setAttribute('d', arcPath(st.v));
    var a = angleForPercent(st.v);
    var p = polarToXY(a);
    var knob = document.getElementById('knob-' + iid);
    var knobHit = document.getElementById('knobHit-' + iid);
    knob.setAttribute('cx', p.x); knob.setAttribute('cy', p.y);
    knobHit.setAttribute('cx', p.x); knobHit.setAttribute('cy', p.y);

    // Texte
    document.getElementById('tValve-' + iid).textContent = Math.round(st.v) + '%';
    document.getElementById('tActual-' + iid).textContent = st.a.toFixed(dec) + '°C';
    document.getElementById('tSet-' + iid).textContent = st.s.toFixed(dec) + '°C';

    // Status highlight
    var ids = ['mKomfort','mStandby','mFrost'];
    for (var k=0;k<ids.length;k++){
      var pill = document.querySelector('#' + ids[k] + '-' + iid + ' .pill');
      var active = (st.m === (k===0?2:(k===1?1:0)));
      if (pill) pill.classList.toggle('active', active);
    }
  }

  // Drag – wir erlauben Drag auf gesamter SVG-Fläche + KnobHit
  var svg = document.querySelector('#ht-' + iid + ' svg');
  var dragging = false;

  function setFromEvent(evt){
    var rect = svg.getBoundingClientRect();
    var x = evt.clientX - rect.left;
    var y = evt.clientY - rect.top;
    var ang = Math.atan2(y - cy, x - cx);
    var cl = Math.max(-0.75*PI, Math.min(0.75*PI, ang));
    var pct = ((cl + 0.75*PI) / (1.5*PI)) * 100;
    st.v = Math.max(0, Math.min(100, pct));
    updateGauge();
  }

  svg.addEventListener('pointerdown', function(e){ dragging = true; svg.setPointerCapture(e.pointerId); setFromEvent(e); });
  svg.addEventListener('pointermove', function(e){ if(dragging) setFromEvent(e); });
  svg.addEventListener('pointerup', async function(e){
    if(!dragging) return; dragging = false;
    var newVal = Math.round(st.v);
    await requestAction(VID.valve, newVal);
  });

  // Buttons
  window['HT' + iid] = {
    _state: st,
    inc: async function(){
      var v = +(st.s + step).toFixed(dec);
      st.s = v; updateGauge();
      await requestAction(VID.setpoint, v);
    },
    dec: async function(){
      var v = +(st.s - step).toFixed(dec);
      st.s = v; updateGauge();
      await requestAction(VID.setpoint, v);
    },
    setMode: async function(m){
      st.m = m; updateGauge();
      await requestAction(VID.mode, m);
    }
  };

  updateGauge();
})();
</script>
HTML;
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
                ['type' => 'Label',          'caption' => 'Die Kachel erscheint als Variable "~HTMLBox" in der Instanz und kann im WebFront verlinkt werden.']
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
