# AGENTS.md

Diese Datei definiert die verbindlichen Regeln für alle AI-Agents, die in diesem Repository arbeiten.

## Verbindliche Regelquellen und Priorität

Vor jeder Analyse oder Änderung am Code müssen die folgenden Dokumente vollständig gelesen und beachtet werden:

1. [`architektur.md`](./architektur.md)
2. [`architektur-patterns.md`](./architektur-patterns.md)
3. [`projekt-umsetzung.md`](./projekt-umsetzung.md) als fachliche Umsetzungsgrundlage
4. die ergänzenden Regeln in dieser Datei

Die beiden Architekturdokumente sind die maßgebliche Grundlage dieses Projekts und gelten immer. Die fachliche Umsetzungsgrundlage und die Regeln dieser Datei ergänzen sie, dürfen ihnen aber nicht widersprechen. Bei Unklarheiten gilt die spezifischere Architekturregel; `architektur-patterns.md` konkretisiert die allgemeinen Leitlinien aus `architektur.md`.

Bestehender Legacy-Code hebt diese Vorgaben nicht auf. Neue Features und wesentliche Erweiterungen müssen der beschriebenen Architektur folgen. Bei kleinen Änderungen an Legacy-Code ist die neue Architektur so weit anzuwenden, wie es ohne unverhältnismäßige, sachfremde Umbauten möglich ist.

## Zugriffs- und Sicherheitsregeln

- AI-Agents dürfen `src/config/secrets/` und dessen Inhalte weder lesen noch verändern.
- AI-Agents dürfen keine `.env`-Datei lesen oder verändern. Das gilt auch für alle Varianten wie `.env.local`, `.env.test` und generell `.env*`.
- Geheimnisse, Zugangsdaten und personenbezogene Daten dürfen nicht in Code, Tests, Logs, Dokumentation oder Tool-Ausgaben offengelegt werden.

## Codequalität und Typisierung

- Der Code muss produktionsreif sein und zu den Konventionen des umgebenden Codes passen.
- Es gilt PHPStan Level 10.
- PHPStan ist immer mit `--memory-limit=1G` auszuführen.
- Eindeutige Typen sind mit nativen PHP-Typen auszudrücken.
- Niemals einen Typ lediglich durch `mixed` ersetzen oder ergänzen, um die statische Analyse zufriedenzustellen.
- Wenn ein Rückgabetyp nicht sicher bestimmt werden kann, darf kein spekulativer Typ ergänzt werden. Stattdessen ist zuerst der tatsächliche Vertrag zu klären.
- Inline-`@var`-Annotationen sind verboten.
- PHPDoc ist nur zulässig, wenn es für statische Analyse, Generics, komplexe Array-Shapes oder notwendige IDE-/Tool-Unterstützung gebraucht wird. Native PHP-Typen haben Vorrang.
- Kommentare sind nur zu verwenden, wenn sie einen nicht offensichtlichen Grund, eine Randbedingung oder eine technische Einschränkung erklären. Sie sollen nicht wiederholen, was der Code bereits ausdrückt.
- Die vorhandenen Benennungs- und Sprachkonventionen sind beizubehalten. Deutsche und englische Bezeichner dürfen nebeneinander bestehen; vorhandene Fachbegriffe werden nicht allein zur sprachlichen Vereinheitlichung umbenannt.

## Prüfung und Tests

- Tests folgen verbindlich der Teststrategie und Verzeichnisstruktur aus den Architekturdokumenten.
- Logic-Unit-Tests bleiben frameworkfrei, booten keinen Symfony-Kernel und ersetzen Data-Layer-Abhängigkeiten durch Mocks oder Test-Doubles ihrer Interfaces.
- Integrationstests prüfen Data-Layer, Mapping und Infrastruktur mit realistischen Infrastrukturkomponenten.
- Funktionale UI- und Szenariotests verwenden den Symfony-Test-Stack gemäß `architektur.md` und `architektur-patterns.md`.
- Für geänderten Code sind die kleinsten aussagekräftigen Tests sowie die relevanten statischen Analysen auszuführen.
- Scheitern lokale PHPUnit- oder Symfony-Testbefehle mit `could not find driver` für SQLSrv/PDO, sind die betroffenen Tests im Docker-Container erneut auszuführen:

  ```bash
  docker exec ds sh -lc 'cd /app && <test command>'
  ```

## Umgang mit Abweichungen

- Eine bestehende Implementierung, die von den Architekturdokumenten abweicht, ist kein Vorbild für neuen Code.
- Notwendige Abweichungen müssen vor der Implementierung ausdrücklich begründet und abgestimmt werden.
- Architekturregeln dürfen nicht durch lokale Workarounds, zusätzliche Framework-Abhängigkeiten in `src/Logic` oder direkte Zugriffe zwischen UI und Data umgangen werden.
