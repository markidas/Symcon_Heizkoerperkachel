<?php
declare(strict_types=1);

require_once __DIR__ . '/helper.php';

class HeatingTile extends IPSModule
{
    use HT_WebHook;

    public function Create()
    {
        parent::Create();

        // Konfigurations-Properties
        $this->RegisterPropertyInteger('ActualTempVarID', 0);   // Float °C
        $this->RegisterPropertyInteger('SetpointVarID', 0);     // Float °C
        $this->RegisterPropertyInteger('ValvePercentVarID', 0); // Float 0..100
        $this->RegisterPropertyInteger('ModeVarID', 0);         // Integer 0:Frost 1:Standby 2:Komfort
        $this->RegisterPropertyFloat('SetpointStep', 0.5);
        $this->RegisterPropertyInteger('Decimals', 1);
        $this->RegisterPropertyString('HookPath', '/hook/heatingtile'); // Basis-Hook

        // Visualisierung (HTMLBox)
        $this->RegisterVariableString('Tile', 'Heizung', '~HTMLBox', 0);
        IPS_SetHidden($this->GetIDForIdent('Tile'), false);
    }

    public function ApplyChanges()
    {
        parent::ApplyChanges();

        // Hook für diese Instanz registrieren
        $hook = rtrim($this->ReadPropertyString('HookPath'), '/').'/'.$this->InstanceID;
        $this->RegisterHook($hook, $this->RegisterHookScript($hook));

        // Auf Variablen-Updates hören
        $watch = [
            $this->ReadPropertyInteger('ActualTempVarID'),
            $this->ReadPropertyInteger('SetpointVarID'),
            $this->ReadPropertyInteger('ValvePercentVarID'),
            $this->ReadPropertyInteger('ModeVarID'),
        ];
        foreach ($watch as $vid) {
            if ($vid > 0) $this->RegisterMessage($vid, VM_UPDATE);
        }

        $this->Render();
    }

    public function MessageSink($TimeStamp, $SenderID, $Message, $Data)
    {
        if ($Message === VM_UPDATE) {
            $this->Render();
        }
    }

    private function RegisterHookScript(string $hook): int
    {
        // Ein (unsichtbares) PHP-Skript für den Hook erzeugen/finden
        $ident = 'HookScript';
        $sid = @$this->GetIDForIdent($ident);
        if (!$sid) {
            $sid = IPS_CreateScript(0);
            IPS_SetParent($sid, $this->InstanceID);
            IPS_SetName($sid, 'HeatingTile Hook');
            IPS_SetIdent($sid, $ident);
            IPS_SetScriptContent($sid, file_get_contents(__DIR__.'/hook.php'));
        }
        IPS_SetProperty($sid, 'Hook', $hook); // harmless but keeps info in place
        return $sid;
    }

    public function Render(): void
    {
        $vidA = $this->ReadPropertyInteger('ActualTempVarID');
        $vidS = $this->ReadPropertyInteger('SetpointVarID');
        $vidV = $this->ReadPropertyInteger('ValvePercentVarID');
        $vidM = $this->ReadPropertyInteger('ModeVarID');

        $dec  = $this->ReadPropertyInteger('Decimals');
        $A = $vidA ? round(GetValueFloat($vidA), $dec) : 0.0;
        $S = $vidS ? round(GetValueFloat($vidS), $dec) : 0.0;
        $V = $vidV ? round(GetValueFloat($vidV), 0)   : 0;
        $M = $vidM ? intval(GetValue($vidM))          : 0;

        $html = $this->BuildHTML($A, $S, $V, $M);
        SetValue($this->GetIDForIdent('Tile'), $html);
    }

    private function BuildHTML(float $A, float $S, int $V, int $M): string
    {
        $iid  = $this->InstanceID;
        $hook = rtrim($this->ReadPropertyString('HookPath'), '/').'/'.$iid;

        // SVG mit 270°-Bogen, knob-drag, responsiv (viewBox) + CSS Theme Variablen
        return <<<HTML
<style>
#ht-$iid { font-family: ui-sans-serif, system-ui, -apple-system, "Segoe UI", Roboto, Arial; }
#ht-$iid { width: 100%; height: auto; color: var(--text-color, var(--foreground, #ffffff)); }
#ht-$iid .card { background: var(--background-color, var(--surface, #2f2f35)); border-radius: 12px; padding: clamp(8px, 2.2vw, 18px); }
#ht-$iid .row { display: flex; align-items: center; justify-content: center; gap: 1rem; }
#ht-$iid .big { font-size: clamp(18px, 6.8vw, 40px); font-weight: 600; }
#ht-$iid .mid { font-size: clamp(12px, 3.6vw, 22px); font-weight: 500; opacity: .9; }
#ht-$iid .muted { opacity: .7; }
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
      <!-- Hintergrundbogen -->
      <path id="bg-$iid" d="M 60 180 A 110 110 0 1 1 240 180"
            fill="none" stroke="color-mix(in oklab, var(--accent-color, #1fd1b2), #000 70%)"
            stroke-width="14" stroke-linecap="round"/>
      <!-- Vordergrundbogen (Stellgröße) -->
      <path id="fg-$iid" d="" fill="none"
            stroke="var(--accent-color, var(--primary, #1fd1b2))"
            stroke-width="14" stroke-linecap="round"/>
      <!-- Drag-Knob -->
      <circle id="knob-$iid" cx="240" cy="180" r="12"
              fill="var(--accent-color, var(--primary, #1fd1b2))" />
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
      <span class="pill \${2==HT$iid._state.m?'active':''}">Komfort</span>
      <strong id="mKomfortVal-$iid">{$S}°C</strong>
    </div>
    <div class="item" id="mStandby-$iid" onclick="HT$iid.setMode(1)">
      <span class="pill \${1==HT$iid._state.m?'active':''}">Standby</span>
      <strong>20,5°C</strong>
    </div>
    <div class="item" id="mFrost-$iid" onclick="HT$iid.setMode(0)">
      <span class="pill \${0==HT$iid._state.m?'active':''}">Frost</span>
      <strong>5,0°C</strong>
    </div>
  </div>
</div>

<script>
(function(){
  const st = {
    v: {$V}, // valve %
    s: {$S}, // setpoint
    a: {$A}, // actual
    m: {$M}  // mode
  };
  const hook = '{$hook}';

  const PI = Math.PI;
  const start = {x:60,y:180}, end={x:240,y:180}, cx=150, cy=180, r=110; // 270° arc

  function polarToXY(angle){
    return { x: cx + r * Math.cos(angle), y: cy + r * Math.sin(angle) };
  }
  function angleForPercent(p){ // map 0..100 => -3/4*PI .. PI
    return (-0.75*PI) + ( (1.5*PI) * (p/100) );
  }
  function arcPath(pct){
    const a = angleForPercent(pct);
    const p1 = polarToXY(-0.75*PI);
    const p2 = polarToXY(a);
    const large = (pct > 50) ? 1 : 0;
    return \`M \${p1.x} \${p1.y} A \${r} \${r} 0 \${large} 1 \${p2.x} \${p2.y}\`;
  }
  function updateGauge(){
    document.getElementById('fg-{$iid}').setAttribute('d', arcPath(st.v));
    const a = angleForPercent(st.v);
    const p = polarToXY(a);
    const knob = document.getElementById('knob-{$iid}');
    knob.setAttribute('cx', p.x); knob.setAttribute('cy', p.y);
    document.getElementById('tValve-{$iid}').textContent = Math.round(st.v) + '%';
    document.getElementById('tActual-{$iid}').textContent = st.a.toFixed({$this->ReadPropertyInteger('Decimals')}) + '°C';
    document.getElementById('tSet-{$iid}').textContent = st.s.toFixed({$this->ReadPropertyInteger('Decimals')}) + '°C';
    // Status highlight
    ['mKomfort','mStandby','mFrost'].forEach((id, idx)=>{
      const pill = document.querySelector('#'+id+'-{$iid} .pill');
      pill.classList.toggle('active', st.m === (idx===0?2:(idx===1?1:0)));
    });
  }

  async function post(action, payload){
    const res = await fetch(hook+'?iid={$iid}', {
      method: 'POST',
      headers: {'Content-Type':'application/json'},
      body: JSON.stringify(Object.assign({action}, payload||{}))
    });
    if (!res.ok) return;
    return await res.json();
  }

  const elKnob = document.getElementById('knob-{$iid}');
  const svg = elKnob.closest('svg');

  let dragging = false;
  function setFromXY(x, y){
    const ang = Math.atan2(y-cy, x-cx);
    // clamp to arc range (-0.75PI .. 0.75PI)
    const cl = Math.max(-0.75*PI, Math.min(0.75*PI, ang));
    const pct = ((cl + 0.75*PI) / (1.5*PI)) * 100;
    st.v = Math.max(0, Math.min(100, pct));
    updateGauge();
  }
  svg.addEventListener('pointerdown', (e)=>{ dragging=true; svg.setPointerCapture(e.pointerId); setFromXY(e.offsetX, e.offsetY); });
  svg.addEventListener('pointermove', (e)=>{ if(dragging) setFromXY(e.offsetX, e.offsetY); });
  svg.addEventListener('pointerup', async (e)=>{ 
    if(!dragging) return; dragging=false; 
    const r = await post('setValve', {value: st.v}); 
    if (r && r.value !== undefined) st.v = r.value; 
    updateGauge();
  });

  window.HT{$iid} = {
    _state: st,
    inc: async ()=>{ const r = await post('incSetpoint'); if(r && r.value!==undefined){ st.s=r.value; updateGauge(); } },
    dec: async ()=>{ const r = await post('decSetpoint'); if(r && r.value!==undefined){ st.s=r.value; updateGauge(); } },
    setMode: async (m)=>{ st.m=m; updateGauge(); await post('setMode',{value:m}); }
  };

  updateGauge();
})();
</script>
HTML;
    }

    public function GetConfigurationForm()
    {
        return json_encode([
            'elements' => [
                ['type'=>'Label', 'caption'=>'Variablen-IDs (siehe Objektbaum)'],
                ['type'=>'NumberSpinner','name'=>'ActualTempVarID','caption'=>'Ist-Temperatur (°C) [Float]'],
                ['type'=>'NumberSpinner','name'=>'SetpointVarID','caption'=>'Sollwert (°C) [Float]'],
                ['type'=>'NumberSpinner','name'=>'ValvePercentVarID','caption'=>'Stellgröße Ventil (%) [Float 0..100]'],
                ['type'=>'NumberSpinner','name'=>'ModeVarID','caption'=>'Betriebsart [Integer: 0=Frost,1=Standby,2=Komfort]'],
                ['type'=>'NumberSpinner','name'=>'SetpointStep','caption'=>'Schrittweite Sollwert (°C)', 'digits'=>1, 'minimum'=>0.1],
                ['type'=>'NumberSpinner','name'=>'Decimals','caption'=>'Nachkommastellen Temperatur', 'minimum'=>0, 'maximum'=>2],
                ['type'=>'ValidationTextBox','name'=>'HookPath','caption'=>'WebHook-Basis (normal: /hook/heatingtile)'],
                ['type'=>'Label', 'caption'=>'Die Kachel erscheint als Stringvariable "~HTMLBox" innerhalb der Instanz. In WebFront einfach verlinken.']
            ]
        ]);
    }
}
