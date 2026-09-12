Version 1.28.30 - Cache/Nonce Stabilitätsfix

- Duftberater-Seite markiert sich als nicht cachebar, sobald der Shortcode gerendert wird.
- Neuer öffentlicher AJAX-Endpunkt liefert jederzeit eine frische WordPress-Nonce.
- Frontend erneuert eine abgelaufene Nonce automatisch und wiederholt die Ergebnis-/PDF-Anfrage einmal.
- Damit ist kein manuelles Cache-Leeren mehr nötig, wenn eine alte gecachte Seite ausgeliefert wurde.
