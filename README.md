# Symcon_Heizkoerperkachel

HTML-SDK Modul für IP-Symcon, das eine Heizungs-Kachel (Tile) mit Ist-/Soll-Temperatur,
Stellgröße (Ventil %) und Fensterstatus anzeigt – inkl. +/- Buttons zum Setzen des Sollwerts.

## Features
- IP-Symcon HTML-SDK (GetVisualizationTile / UpdateVisualizationValue / requestAction)
- Live-Update bei Variablenänderungen
- Solltemperatur aus der Kachel setzen (benötigt Variablenaktion der Zielvariable)
- Einfach je Raum als eigene Instanz nutzbar

## Installation (per Git, empfohlen)
1. Dieses Repository **öffentlich** auf GitHub anlegen und die Dateien hochladen (oder forken).
2. In der IP-Symcon **Konsole**: `Kern Instanzen` → **Module Control** → **Neue Bibliothek**.
3. **Git-URL** deines Repos eintragen, z. B. `https://github.com/markidas/Symcon_Heizkoerperkachel`.
4. Nach dem Import: **Instanz hinzufügen** → nach *HeizungKachel* suchen → anlegen.

### Alternativ: Lokal ohne Git
- Ordner nach `/var/lib/symcon/modules/Symcon_Heizkoerperkachel` (Linux) bzw. `C:\ProgramData\Symcon\modules\Symcon_Heizkoerperkachel` (Windows) kopieren.
- In der Konsole **Module aktualisieren** oder den Dienst neu starten.

## Konfiguration der Instanz
- **RoomName**: Anzeigename (z. B. Wohnzimmer)
- **VarIst**: Float (Ist-Temperatur)
- **VarSoll**: Float (Soll-Temperatur) – *mit* Variablenaktion, damit +/- funktioniert
- **VarStell**: Float/Int 0..100 (Ventil-Prozent)
- **VarFenster**: Bool (Fensterkontakt)
- **Decimals**: Nachkommastellen für Anzeige

## Hinweise
- Das Modul setzt `SetVisualizationType(1)` → die Instanz liefert eine HTML-Kachel.
- `module.html` enthält das Kachel-Layout. Änderungen sind ohne PHP-Anpassung möglich.
- Die Buttons rufen `requestAction('Setpoint', <Wert>)` auf; das Modul ruft dann `RequestAction(ID, Wert)` der Zielvariable auf.
- Mindestens **VarIst** & **VarSoll** müssen konfiguriert sein (Status 102 = Aktiv).

## Lizenz
MIT
