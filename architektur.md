# Architekturleitlinien

Dieses Dokument beschreibt die technische Architektur des Projekts. Es dient als Referenz für die Entwicklung und wird kontinuierlich erweitert.

## 1. Schichtenmodell (Layered Architecture)

Die Anwendung ist in drei strikt getrennte Layer unterteilt:

### UI Layer (`src/UI`)
- **Verantwortung**: Präsentation, Handling von User-Input, API-Endpunkte, CLI-Commands.
- **Struktur**: Folgt einer domänenorientierten Struktur `src/UI/{Module}/{Interface}/{Feature}/...` (z.B. `src/UI/Sales/Http/OrderController.php`), um maximale Symmetrie zu den anderen Layern zu gewährleisten.
- **Regel**: Darf ausschließlich die Logic Layer aufrufen. Ein direkter Zugriff auf die Data Layer ist untersagt.

### Logic Layer (`src/Logic`)
- **Verantwortung**: Implementierung der Geschäftslogik und Orchestrierung von Prozessen.
- **Komponenten**:
    - **UseCases / Workflows**: Repräsentieren eine spezifische Geschäftsaktion (z.B. `CreateUserUseCase`). Nutzen DTOs für den Datentransfer.
    - **BusinessQueries**: Spezifische Abfragen für geschäftsrelevante Daten.
    - **Manager**: Steuert Lese- und Schreibzugriffe auf ein einzelnes Business Model (z. B. Caching via "Cache-Aside"). Komplexe Aggregationen über mehrere Domänen hinweg werden bewusst vermieden, um Kopplung zu reduzieren.
    - **Models**: Kernlogik-Objekte zur internen Verarbeitung innerhalb der Logic Layer.
    - **Provider / Processor Interfaces**: Definieren den Datenaustausch mit der Data Layer und ermöglichen Dependency Inversion.
- **Regel**: Ist unabhängig von der UI Layer und definiert die Anforderungen an die Data Layer über Interfaces.

### Data Layer (`src/Data`)
- **Verantwortung**: Persistenz, Datenabruf und Kommunikation mit externen Systemen.
- **Struktur**: Spiegelt exakt die Verzeichnisstruktur der Logic Layer wider (`src/Data/{Module}/{Feature}/...`), um eine konsistente Auffindbarkeit zwischen Domänenlogik und technischer Implementierung zu gewährleisten.
- **Komponenten**:
    - **Repositories**: Zugriff auf Datenbankentitäten.
    - **Entities**: Domain-Modelle für die Persistenz (spiegeln DB-Schema).
    - **Provider / Processor-Implementierungen**: Stellt Daten bereit bzw. persistiert sie (DB, API). Implementieren dabei die aus der Logic Layer importierten Interfaces (Dependency Inversion).
    - **Mapper**: Transformation zwischen Entity und Business Model.
- **Regel**: Kennt keine Geschäftslogik und ist nur für die Bereitstellung/Speicherung von Daten zuständig.

## 2. Datenfluss & Kommunikation

### Flussrichtung
`UI Layer` $\rightarrow$ `Logic Layer` $\rightarrow$ `Data Layer`

### Kommunikationsregeln
1. **Eintrittspunkt**: Die UI Layer ruft immer einen UseCase, Workflow oder eine BusinessQuery in der Logic Layer auf.
2. **Orchestrierung**: Manager in der Logic Layer organisieren den Zugriff (z.B. Cache-Check) und delegieren die eigentliche Arbeit an Provider- oder Processor-Interfaces der Data Layer.
3. **Entkopplung**: Die Logic Layer sollte idealerweise gegen Interfaces der Data Layer programmieren, um die Austauschbarkeit der Datenquelle zu gewährleisten.
4. **Modulkapselung (Adapter)**: Module kommunizieren nicht direkt mit den internen Strukturen anderer Module. Wenn ein UseCase aus Modul A eine Fähigkeit von Modul B benötigt, definiert Modul A ein eigenes Adapter-Interface. Die Implementation von Modul B wird via Dependency Inversion im Container auf dieses Interface gemappt. Dies verhindert enge Kopplung und vereinfacht Tests entscheidend.

## 3. Validierungsstrategie

Das Prinzip lautet: Jeder Layer validiert das, was er in seinem Kontext validieren kann. Eine detaillierte Beschreibung mit Layer-Verantwortlichkeiten und Codebeispielen findet sich in [architektur-patterns.md Sektion 4](./architektur-patterns.md#4-validierungsstrategie-mehrschichtig).

- **UI Layer**: Syntaktische Validierung (z. B. Format, Pflichtfelder, Typen).
- **Logic Layer**: Semantische/Business-Validierung (z. B. Logik-Checks gegen Geschäftsregeln, Berechtigungen).
- **Data Layer**: Technische Integrität (z. B. Unique-Constraints der Datenbank, korrekte Datentypen in der Persistenzschicht).

## 4. Fehlerbehandlung & Exceptions

Die Fehlerbehandlung folgt einem strikten Trennungsprinzip zwischen Domain-Logik und Präsentationsschicht.

### Domain Exceptions
In der **Logic Layer** werden spezifische Domain-Exceptions geworfen (z. B. `UserNotFoundException`, `InsufficientFundsException`). Diese Exceptions beschreiben das *was* des Fehlers, nicht die technische Umsetzung.

### Zentralisierte Fehlerübersetzung
Die **UI Layer** übernimmt die Übersetzung dieser Exceptions in benutzerfreundliche Antworten:
- Es wird ein zentraler **Exception-Subscriber/Listener** eingesetzt, um `try-catch`-Blöcke in den Controllern zu vermeiden.
- Dieser Subscriber fängt die Domain-Exceptions ab und wandelt sie in das entsprechende Format (z.B. HTTP 404 für `NotFoundException`, HTTP 400 für Business-Fehler) um.

## 5. Asynchrone Kommunikation (Message Bus)

Zur Entkopplung zeitintensiver Prozesse wird ein Message-Bus eingesetzt. Dabei gilt folgende Struktur zur Vermeidung von Framework-Abhängigkeiten in der Logikschicht:

### Messagen & Dispatching
- **Messages**: Liegen in `src/Logic`, da sie eine geschäftliche Absicht (Business Intent) repräsentieren.
- **Dispatcher Entkopplung**: Die Logic Layer nutzt ein eigenes Interface (z. B. `EventPublisherInterface`), um Nachrichten zu versenden. Die technische Implementierung dieses Interfaces erfolgt außerhalb der Logikschicht (Framework-Bridge), sodass die Business-Logik unabhängig vom Symfony Messenger bleibt.

### Message Handler
- **Ort**: Message-Handler befinden sich in `src/UI/{Module}/Handlers/{Feature}/{Name}Handler.php`.
- **One-to-One Prinzip**: Es gilt strikt eine Nachricht pro Handler. Jeder Handler bleibt schlank und übernimmt nur die Transformation der Message und delegiert sie an den korrekten UseCase oder Workflow.
- **Rolle**: Da ein asynchroner Nachrichteneingang ein externer Eintrittspunkt ist, fungiert der Handler als Adapter. Er nimmt die Message entgegen und delegiert die eigentliche Verarbeitung an einen genau einem bestimmten UseCase oder Workflow in der Logic Layer.

## 6. Teststrategie & Symmetrie
Um eine hohe Codequalität und Wartbarkeit zu gewährleisten, wird eine pyramidale Teststrategie verfolgt. Dabei gilt das Prinzip der **Symmetrie**: Die Verzeichnisstruktur unter `tests/` spiegelt exakt die Struktur von `src/` wider, um die Auffindbarkeit von Tests sicherzustellen.

### Unit Tests (Logic Layer)
- **Fokus**: Reine Geschäftslogik, Models, UseCases.
- **Regel**: Diese Tests dürfen *keine* echte Datenbank oder externe APIs nutzen. Abhängigkeiten zur Data Layer werden zwingend durch Mocks/Doubles der Interfaces ersetzt.
- **Ziel**: Schnelle Ausführung und vollständige Abdeckung der Edge-Cases in der Business-Logik.

### Integration Tests (Data Layer)
- **Fokus**: Korrekte Persistenz (Repositories, Processor) und Mapping.
- **Regel**: Nutzen eine echte Test-Datenbank oder einen In-Memory-Speicher. Hier wird geprüft, ob die technische Implementierung der Data Layer korrekt funktioniert.

### Functional Tests (UI Layer & Scenarios)
- **Fokus**: Durchlauf kompletter Business-Szenarien und Validierung von API-Endpunkten.
- **Werkzeug**: Einsatz des Symfony `WebTestCase`.
- **Ziel**: Sicherstellung, dass die Kette UI $\rightarrow$ Logic $\rightarrow$ Data konsistent funktioniert.

## 7. Logging & Observability

Für das System-Logging wird der PSR-3 Standard verwendet:
- **Standard**: In allen Layern ist die direkte Verwendung des `Psr\Log\LoggerInterface` zulässig.
- **Entkopplung**: Da es sich um ein Interface handelt, bleibt die Business-Logik unabhängig von der konkreten Implementierung (z.B. Monolog).
- **Konfiguration**: Die Steuerung (Kanäle, Log-Level, Speicherorte) erfolgt rein über die Framework-Konfiguration, nicht im Code.

## 8. Security & Autorisierung

Die Absicherung der Anwendung erfolgt auf drei Ebenen:

### 1. Authentifizierung
Die Identitätsprüfung (Authentication) wird primär über die Framework-Infrastruktur in der **UI Layer** gelöst (z.B. JWT, Session), da dies ein technisches Anliegen darstellt.

### 2. Grobkörnige Autorisierung
Die Prüfung allgemeiner Zugriffsrechte basierend auf Rollen (z.B. `ROLE_ADMIN`) erfolgt ebenfalls in der **UI Layer** direkt an den Controllern oder Handlern mittels Framework-Annotationen/Attributen.

### 3. Feinkörnige / Business-Autorisierung
Für objektspezifische Berechtigungen (z. B. *"Darf dieser User dieses Dokument bearbeiten?"*) wird folgendes Muster angewendet:
- **UI Layer**: Einsatz von Symfony Voters, die als Adapter fungieren.
- **Logic Layer**: Die eigentliche Entscheidungsgrundlage liegt in einem `PermissionService` oder einer `BusinessQuery`. Der Voter ruft diesen Service auf, um eine Entscheidung basierend auf Business-Regeln zu treffen.
- **Ziel**: Die fachlichen Berechtigungsregeln bleiben unabhängig vom Framework und zentral in der Logic Layer gesammelt.

## 9. Komponenten-Definitionen
Weitere Details zur technischen Umsetzung finden sich in der [architektur-patterns.md](./architektur-patterns.md).

| Komponente | Ort | Zweck |
| :--- | :--- | :--- |
| UseCase / Workflow | `src/Logic` | Einzelschrittliche Geschäftsoperation. |
| BusinessQuery | `src/Logic` | Funktionale Abfrage von Geschäftsdaten. |
| Manager | `src/Logic` | Cache-Steuerung und Koordination von Providern/Processoren. |
| Provider Interface | `src/Logic` | Vertragsdefinition für Datenbereitstellung (Read). |
| Provider Implementation | `src/Data` | konkrete Bereitstellung von Daten (DB, API). |
| Processor Interface | `src/Logic` | Vertragsdefinition für Datenpersistierung (Write). |
| Processor Implementation | `src/Data` | Konkrete Verarbeitung/Persistierung von Daten. |
