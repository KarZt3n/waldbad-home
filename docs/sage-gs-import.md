# Sage GS Vereins importieren

```sh
php bin/console sage-gs:importer /Users/SassK/Downloads/mitglieder-export.XML
php bin/console sage-gs:importer /Users/SassK/Downloads/mitglieder-export.XML --mapping=/absoluter/pfad/korrekturen.json
php bin/console sage-gs:importer /Users/SassK/Downloads/mitglieder-export.XML --mapping=/absoluter/pfad/korrekturen.json --execute
```

Standard ist ein Prüflauf. Erst `--execute` schreibt nach vollständig erfolgreicher Prüfung in einer Transaktion. Bestehende Mitgliedsnummern werden übersprungen, nicht überschrieben. Ein erneuter Import erzeugt keine Dubletten. Die Mitgliedertabellen aus den vorhandenen Migrationen müssen installiert sein. Es werden keine Migrationen automatisch ausgeführt.

## Feldzuordnung zum aktuellen Entity-Schema

| Sage-Attribut | Zielfeld |
| --- | --- |
| MITNUM | member_number; unverändert inklusive Präfix und führender Nullen |
| FANUM | primary_member_number; leer bedeutet eigene Nummer |
| ANREDE | salutation: Herr → mr, Frau → ms; andere Werte korrigieren |
| NAME / VORNAME | last_name / first_name |
| GEBURT / MITSEIT / AUSTRITT | birth_date / joined_at / left_at; striktes DD.MM.YYYY |
| STRASSE / PLZ / ORT | street / postal_code / city |
| EMAIL | email |
| TELEFON, ersatzweise MOBIL | phone |
| AKTIV | active; ein vorhandenes Austrittsdatum deaktiviert |
| FUNKTION | function: Mitglied bzw. leer → member, Vorstand → board |
| KTOINHABER / IBAN / BANK | account_holder / iban / bank_name |
| MANDATSREFERENZ | mandate_reference; leer → Mitgliedsnummer |
| ZAHLART | payment_method: Bankeinzug, Überweisung, Bar |
| ZAHLWEISE | payment_interval: jährlich, halbjährlich, vierteljährlich, monatlich |
| ZAHLANFANG | payment_day: Wahr → first, Falsch → fifteenth |
| ZAHLFREMD | payer_type |
| ZAHLERNR | payer_member_id nach Auflösung über Mitgliedsnummer |
| FANUM bei fehlender ZAHLERNR | Ersatz-Zahler für Fremdzahler, vom Auftraggeber bestätigt |
| NM / NJ | next_booking_month / next_booking_year; zweistellige Jahre → 2000–2099 |

FANUM gleich MITNUM wird als Familienhauptmitglied behandelt. Bei abweichender FANUM muss `familyRole` explizit als `partner` oder `child` zugeordnet werden; Alter oder Familienstand allein beweisen die Familienrolle nicht. Leere Zahlart, fehlende Buchungstermine und unbekannte Codes werden nicht stillschweigend ersetzt. Die Interpretation von ZAHLANFANG entspricht der bestehenden Auswahl zum 1./15. und kann über `paymentDay` korrigiert werden.

Nach Anwendung der Korrekturdatei wird `payerType` erneut ausgewertet: Bei `self_payer` wird ein eventuell aus dem FANUM-Fallback stammendes `payerMemberNumber` verworfen (ein Mitglied kann nicht sein eigener abweichender Zahler sein), bei `other_member` werden Kontoinhaber/IBAN/Bank/Mandatsreferenz verworfen (ein abweichender Zahler benötigt keine eigenen Bankdaten). Eine Korrekturzeile, die nur `payerType` überschreibt, muss die jeweils andere Seite deshalb nicht selbst bereinigen.

## Korrekturdatei

Ein JSON-Objekt ordnet der **einsbasierten Position des ROW-Datensatzes** Zielfelder zu. Beispiel mit ausschließlich synthetischen Korrekturen:

```json
{
  "1": {"familyRole": "partner", "nextBookingMonth": 3, "nextBookingYear": 2027},
  "2": {"familyRole": "child", "paymentMethod": "sepa_direct_debit", "paymentInterval": "yearly"}
}
```

Die Feldnamen entsprechen `CreateMemberRequest`. Datums-Korrekturen verwenden YYYY-MM-DD. Die Korrekturdatei gehört zur exakt gleichen, unveränderten XML-Datei. Dateien mit Mitgliedsdaten nicht einchecken. Fehlerausgaben enthalten nur Datensatzpositionen und Validierungsgründe.

## Grenzen der Übernahme

Die vorhandene DB verlangt unter anderem Geburtsdatum, Eintritt, Anschrift, Buchungstermin und (nur bei Selbstzahlern mit SEPA-Lastschrift) gültige Bankdaten. Unvollständige historische Datensätze bleiben deshalb zunächst Fehler und müssen fachlich bereinigt werden. Ein Fehler verhindert sämtliche Schreibvorgänge.

Sage-Beitragscodes SATZ/TITEL*, JAHRESBEITRAG, Guthaben und Buchungshistorie werden nicht in aktuelle Beitragstarife umgedeutet. Neue Mitglieder erhalten weder Aufnahmegebühren noch automatisch berechnete Beiträge. Die Beitragszuordnung muss anschließend separat erfolgen. BEMERK, BIC, Mandatsgültigkeitsdaten, weitere Telefonnummern, Zusatzfelder und andere nicht aufgeführte Felder werden derzeit nicht übernommen; die Originaldatei ist dafür aufzubewahren. Bestehende Mitglieder behalten sämtliche Daten, Bemerkungen und Beiträge.

## Analyse des bereitgestellten Exports

996 ROW-Datensätze, 120 deklarierte Felder. Mit der bestätigten FANUM-Zahlerregel passieren zunächst 389 Datensätze die syntaktische Prüfung. Die ersten Fehler je Datensatz betreffen 582 ungeklärte Familienrollen, 15 fehlende Zahlerzuordnungen, 6 fehlende Buchungstermine und je einen Fall mit fehlender Anrede, fehlendem Eintrittsdatum, ungültiger E-Mail oder fehlender Zahlart. Nach Korrektur können weitere Validierungsfehler sichtbar werden. Dies ist keine Bestätigung, dass 389 Datensätze bereits fachlich oder gegen eine laufende Datenbank geprüft wurden.
