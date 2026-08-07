# Architektur Patterns

## 0. Interface-Richtlinien
Um Konsistenz und Dependency Inversion zu gewährleisten, gelten folgende Regeln für alle Interfaces:

- **Namenskonvention**: Zwingendes `Interface`-Suffix (z.B. `OrderProviderInterface`, `ShippingManagerInterface`).
- **Ort & Abhängigkeit**: Das Interface liegt zwingend dort, wo es *gebraucht* wird – also meist im `src/Logic` Layer. Die Implementierung in der Data-Schicht hängt vom Logic-Vertrag ab. Dadurch bleibt die Business-Logik unabhängig von Infrastrukturdetails.
- **Ausnahme: UseCases & Queries**: Diese erhalten bewusst kein eigenes Interface. Da ein UseCase eine konkrete fachliche Operation darstellt und es selten mehrere Implementationen derselben Aktion gibt, bringt DI hier nur Boilerplate ohne Mehrwert. Der Vertrag allein durch PHP-Typisierung ausreicht.
    - *Strategische Ausnahme:* Wird das **Strategy-Pattern** benötigt (z.B. A/B-Testing oder unterschiedliche Logiken pro Kunde), kann gezielt ein Interface für den UseCase erstellt werden.

## 1. UseCase Pattern (`src/Logic`)

Das UseCase Pattern bildet das Herzstück der Business-Logik. Zusammen mit dem Read Pattern (Sektion 2) folgt die Architektur einem **Light-CQRS Ansatz** (Command Query Responsibility Segregation), bei dem Schreiboperationen (Commands/UseCases) strikt von Leseoperationen (Queries) getrennt werden, um Komplexität zu reduzieren und die Performance zu optimieren. Jeder Anwendungsfall wird als eigenständige Klasse implementiert, um eine klare Trennung der Verantwortlichkeiten und eine hohe Testbarkeit zu gewährleisten.

### Struktur & Definition
- **Kein Interface**: UseCases benötigen kein separates `UseCaseInterface`. Der Vertrag wird ausschließlich durch die Native PHP Typisierung der Signatur und den Symfony Dependency Injection Container gestellt.
- **Methode**: Die primäre Logik liegt in der Methode `execute()`.
- **Eingabe/Ausgabe**: Daten werden strikt über DTOs (Data Transfer Objects) ausgetauscht.
- **Rückgabewert**: Ein UseCase gibt entweder ein Response-DTO zurück oder `void` (bei reinen Schreiboperationen). Die PHP-Signatur ist native typisiert, sodass zur Laufzeit keine `instanceof`-Prüfungen nötig sind.
- **Fehlerbehandlung**: Business-Fehler werden über spezifische **Domain-Exceptions** signalisiert.
- **Dependency Limitation**: Ein UseCase sollte maximal 3–4 Dependencies injizieren. Ab 5+ Dependencies wird dringend empfohlen, einen **Orchestrator** einzusetzen.

### Orchestrator Pattern (Optional für komplexe Koordination)
Wenn ein UseCase die Zuständigkeit mehrerer Domänen abdeckt (z. B. `Sales`, `Stock` und `Accounting` gleichzeitig), sollte er diese Manager nicht einzeln injizieren.

- **Ort**: `src/Logic/{Module}/{Feature}/Orchestrator/`
- **Zweck**: Bündelt mehrere Manager zu einer einzigen fachlichen Operation (z. B. `OrderFulfillmentOrchestrator`).
- **Vorteil**: Reduziert den UseCase-Konstruktor auf eine einzige Abhängigkeit und isoliert komplexe Multi-Domain-Logik, was die Testbarkeit massiv verbessert.

```php
// src/Logic/Sales/Order/Orchestrator/
readonly class OrderFulfillmentOrchestrator 
{
    public function __construct(
        private OrderManagerInterface $orders,
        private StockManagerInterface $stock, 
        private TransactionManagerInterface $transactions,
    ) {}

    public function fulfillOrder(CreateOrderRequest $request): Order 
    {
        return $this->transactions->execute(function() use ($request) {
            if (!$this->stock->isSufficient($request->items)) {
                throw new InsufficientStockException();
            }
            // Business Model anlegen & persistieren
            $order = new Order(/* ... */);
            return $this->orders->create($order);
        });
    }
}

// Der UseCase profitiert von der schlanken Injektion:
readonly class CreateOrderUseCase {
    public function __construct(
        private OrderFulfillmentOrchestrator $orchestrator, 
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateOrderRequest $request): CreateOrderResponse {
        $order = $this->orchestrator->fulfillOrder($request);
        $this->eventDispatcher->dispatch(new OrderCreatedEvent(orderId: $order->id));
        return new CreateOrderResponse(orderId: $order->id);
    }
}
```

### DTOs (Data Transfer Objects)
- **Typ**: Readonly-Klassen (PHP 8.2+).
- **Namenskonvention**: Suffix `Request` für Eingabe, `Response` für Ausgabe.
- **Typisierung**: Maximale native Typisierung; PHPDocs nur bei komplexen Types (z.B. Generics in Arrays).

### Namenskonvention & Verzeichnisstruktur
- **Klassennamen**: Suffix `UseCase`.
- **Pfad**: `src/Logic/{Module}/{Feature}/{Typ}/...`

**Beispiel-Struktur:**
- `src/Logic/Sales/Order/UseCase/CreateOrderUseCase.php`
- `src/Logic/Sales/Order/Dto/CreateOrderRequest.php`
- `src/Logic/Sales/Order/Dto/CreateOrderResponse.php`

### Input-Validierungsstrategie (Zweistufig)
DTOs im Logic-Layer sind **reine Datenträger** und enthalten keinerlei Framework-Abhängigkeiten. Der Einbau von Symfony Validator Constraints würde das Logic-Layer an ein konkretes Webframework koppeln.

Daher gilt: **Syntaktische Validierung findet ausschließlich im UI-Layer statt**, bevor das DTO erstellt wird. **Semantische / Business-Validierung** erfolgt in der Logic Layer (UseCase und Models).

#### UI Layer — Syntaktische Vorabprüfung
- **Verantwortung**: Controller / Command parsen und prüfen die Rohdaten (meist aus `$_POST`, Request-Bodies oder Konsolenargumenten).
- **Prüfung**: Der Controller prüft Datentypen, Pflichtfelder, String-Längen, E-Mail-Format. Bei einer Abweichung wird eine `ValidationException` geworfen.
- **DTO-Konstruktion**: Erst wenn alle syntaktischen Checks bestanden sind, baut der UI-Layer das typisierte Request-DTO für den UseCase.

#### Logic Layer — Business-Validierung
- **Verantwortung**: Der UseCase und die Business Models prüfen fachliche Regeln (z. B. „Reicht der Bestand?", „Ist der Statusübergang erlaubt?").
- **Prüfung**: Erfolgt innerhalb von `execute()` und in Model-Konstruktoren bzw. -Methoden. Bei Verstoß werden spezifische **Domain-Exceptions** geworfen.
- **Ziel**: Die Business-Regeln bleiben framework-unabhängig und sind auch für nicht-HTTP-Eintrittspunkte (Message Handler, CLI, interne Service-Calls) konsistent gültig.

#### Beispiel: Controller mit manueller Validierung
```php
namespace App\UI\Controller;

use App\Logic\Sales\Order\Dto\CreateOrderRequest;
use App\Logic\Sales\Order\UseCase\CreateOrderUseCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

class OrdersController
{
    public function __construct(private CreateOrderUseCase $createOrder) {}

    public function create(Request $request): string
    {
        $data = json_decode($request->getContent(), true);

        // Validierung im UI-Layer, bevor das DTO zustande kommt.
        if (!isset($data['customerId']) || !is_string($data['customerId'])) {
            throw new BadRequestHttpException('customerId is required and must be a string.');
        }

        if (!isset($data['items']) || !is_array($data['items']) || empty($data['items'])) {
            throw new BadRequestHttpException('At least one item is required.');
        }

        $createOrderRequest = new CreateOrderRequest(
            customerId: $data['customerId'],
            items: $data['items'],
        );

        $response = $this->createOrder->execute($createOrderRequest);
        
        return "Order created with ID: {$response->orderId}"; 
    }
}
```

### Event-Publishing (UseCase Strategie)
Wenn ein UseCase einen signifikanten Statuswechsel bewirkt, ist dieser auch für das Publizieren entsprechender **Domänen-Events** zuständig.

- **Verantwortung**: Der UseCase dispatcht das Event am Ende von `execute()`, nach erfolgreicher Geschäftsfertigung.
- **Entkopplung**: Das Logic-Layer spricht ausschließlich gegen ein Interface (z.B. `EventDispatcherInterface`). Das konkrete Framework (Symfony, RabbitMQ, AWS SNS) bleibt im Data/UI-Layer.
- **Granularität**: Es werden spezifische Domänenereignisse auf UseCase-Ebene verwandt (z.B. `OrderCreatedEvent`), nicht generische Model-Ereignisse („Entity Changed").

#### Implementierung
```php
namespace App\Logic\Sales\Order\UseCase;

use App\Logic\Common\EventDispatcherInterface;
use App\Logic\Sales\Order\Dto\CreateOrderRequest;
use App\Logic\Sales\Order\Dto\CreateOrderResponse;
use App\Logic\Sales\Order\Event\OrderCreatedEvent;
use App\Logic\Sales\Order\Exception\InsufficientStockException;

readonly class CreateOrderUseCase
{
    public function __construct(
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateOrderRequest $request): CreateOrderResponse
    {
        // Business Logik hier...
        if ($this->stockTooLow()) {
            throw new InsufficientStockException();
        }

        $orderId = '123';

        // Publish domain event nach erfolgreichem Abschluss.
        // Die EventDispatcherInterface-Implementierung garantiert automatisch
        // ein Post-Commit-Dispatching, sodass bei einem DB-Rollback kein
        // "Geister-Event" in den Message Bus gelangt.
        $this->eventDispatcher->dispatch(new OrderCreatedEvent(orderId: $orderId));

        return new CreateOrderResponse(orderId: $orderId);
    }

    private function stockTooLow(): bool { return false; }
}
```

#### Transaktionale Event-Garantie (Bridge-Konvention)
Um „Geister-Events" bei Datenbank-Rollbacks zu vermeiden, darf kein Async- oder Broadcast-Event vor erfolgreichem Commit in den Message Bus gelangen. Die Logic Layer ruft einfach `dispatch()` auf und bleibt frei von Transaktions-Boilerplate. Die Infrastruktur-Bridge kapselt das Framework-Spezifische und stellt die transaktionale Konsistenz sicher:

```php
namespace App\Data\Infrastructure\Messenger;

use App\Logic\Common\EventDispatcherInterface;
use Symfony\Component\Messenger\MessageBusInterface;
use Symfony\Component\Messenger\Stamp\DispatchAfterCurrentTransactionStamp;

readonly class SymfonyEventDispatcherBridge implements EventDispatcherInterface 
{
    public function __construct(private MessageBusInterface $bus) {}

    public function dispatch(object $event): void 
    {
        $this->bus->dispatch($event, [new DispatchAfterCurrentTransactionStamp()]);
    }
}
```

#### Vorteile
- **Logic bleibt clean**: Der UseCase ist frei von Transaktions-Boilerplate und ORM-Wissen.
- **Konsistenz**: Nur com-mittete Daten erzeugen Events.
- **Testbarkeit**: Das Interface wird im Unit-Test gemockt; die Bridge-Implementierung ist separat in Integrationstests prüfbar.

#### Domain Event
```php
namespace App\Logic\Sales\Order\Event;

readonly class OrderCreatedEvent
{
    public function __construct(
        public string $orderId,
    ) {}
}
```

#### DTOs
```php
namespace App\Logic\Sales\Order\Dto;

readonly class CreateOrderRequest
{
    public function __construct(
        public string $customerId,
        public array $items,
    ) {}
}

readonly class CreateOrderResponse
{
    public function __construct(
        public string $orderId,
    ) {}
}
```

## 2. Read Pattern (`src/Logic` $\rightarrow$ `src/Data`)

Das Read Pattern beschreibt den Weg einer Datenabfrage von der UI bis zur Datenquelle. Es stellt sicher, dass die UI-Layer niemals direkt auf die Data Layer zugreift und Business-Modelle konsistent zurückgegeben werden.

### Der Datenfluss
`UI Layer` $\rightarrow$ **BusinessQuery** $\rightarrow$ **Manager** $\rightarrow$ **Provider**

#### BusinessQuery (Logic Layer)
- **Zweck**: Dient als fachlicher Einstiegspunkt für Leseoperationen. Sie orchestriert Manager oder andere Queries, um ein Endresultat zu formen.
- **Struktur**: Implementiert das gleiche Muster wie UseCases (`execute()` Methode).
- **Input/Output**: Nutzt Request-DTOs (besonders für Filter und Pagination) und gibt Response-DTOs zurück.
- **Pfad**: `src/Logic/{Module}/{Feature}/Query/...`

#### Manager (Logic Layer)
- **Zweck**: Steuert die Bereitstellung der Daten eines einzelnen fachlichen Business-Models. Hier erfolgt primär die Cache-Logik ("Cache-Aside").
- **Verantwortung**: Ausschließlich Read- und Write-Operationen für genau ein Model sowie die Cache-Koordination. Komplexe Aggregationen über verschiedene Domänen hinweg werden bewusst vermieden. Die eigentliche Geschäftslogik liegt in den UseCases oder spezialisierten Services.
- **Granularität**: **Ein Manager pro Business Model**, um Kopplung und zyklische Dependencies zu vermeiden.
- **Schnittstelle**: Besitzt ein eigenes Interface in der Logic Layer zur Gewährleistung von Testbarkeit und Austauschbarkeit.
- **Zustand**: Strikt zustandslos (stateless).

#### Provider (Data Layer)
- **Zweck**: Führt die technische Abfrage gegen die Infrastruktur aus (DB, API).
- **Schnittstelle**: Die Provider-Interfaces liegen zwingend in der **Logic Layer**, um Dependency Inversion zu gewährleisten.
- **Rückgabewert**: Liefert ausschließlich **Kern-Business-Models** zurück.
    - Einfache Listen $\rightarrow$ `array` (mit PHPDoc `@return Model[]`).
    - Paginierten Listen $\rightarrow$ Ein Wrapper-Objekt (z. B. `PaginatedCollection`), das Daten und Metadaten enthält.
- **Fehlerbehandlung**: Wirft im Fehlerfall direkt **Domain-Exceptions**.
- **Parameter**: Nutzt für einfache Lookups primitive Typen (`string`, `int`), für komplexe Abfragen DTOs.
- **Granularität**: Ein Provider pro Business-Model.

### Code Beispiel

#### BusinessQuery & DTOs
```php
namespace App\Logic\Sales\Order\Query;

use App\Logic\Sales\Order\Dto\GetOrdersRequest;
use App\Logic\Sales\Order\Dto\GetOrdersResponse;
use App\Logic\Sales\Order\Manager\OrderManagerInterface;

readonly class GetOrdersQuery
{
    public function __construct(private OrderManagerInterface $orderManager) {}

    public function execute(GetOrdersRequest $request): GetOrdersResponse
    {
        $orders = $this->orderManager->findOrders($request->filter, $request->page);

        return new GetOrdersResponse(orders: $orders);
    }
}
```

#### Manager Interface (Logic Layer)
```php
namespace App\Logic\Sales\Order\Manager;

interface OrderManagerInterface
{
    public function findOrders(array $filters, int $page): array;
    public function invalidateCache(): void;
}
```

#### Manager Implementierung (Read + Cache)
```php
namespace App\Logic\Sales\Order\Manager;

use App\Logic\Sales\Order\OrderProviderInterface;

readonly class OrderManager implements OrderManagerInterface
{
    public function __construct(
        private OrderProviderInterface $orderProvider,
        // private CacheInterface $cache,  <-- Symfony/PSR-6 Cache (injected via DI)
    ) {}

    /**
     * Liefert Orders mit Cache-Aside Pattern.
     */
    public function findOrders(array $filters, int $page): array
    {
        // cacheKey aus filters + page generieren ...
        // if ($cache->has($key)) return $cache->get($key);

        $orders = $this->orderProvider->fetchOrders($filters, $page);
        // $cache->set($key, $orders);
        return $orders;
    }

    /**
     * Invalidiert den Cache für die Order-Domain.
     */
    public function invalidateCache(): void
    {
        // Tags/Keys löschen, die durch den Write betroffen sind ...
    }
}
```

#### Provider Interface (Logic Layer)
```php
namespace App\Logic\Sales\Order;

interface OrderProviderInterface
{
    public function fetchOrders(array $filters, int $page): array;
}
```

#### Provider Implementierung (Data Layer)
```php
namespace App\Data\Sales\Order\Provider;

use App\Logic\Sales\Order\OrderProviderInterface;

readonly class OrderProvider implements OrderProviderInterface
{
    public function fetchOrders(array $filters, int $page): array
    {
        // DB Abfrage und Mapping zu Business-Models
        return [];
    }
}
```
```

## 3. Write Pattern (`src/Logic` $\rightarrow$ `src/Data`)

Das Write Pattern definiert den Weg von einer Zustandsänderung hin zur Persistenz. Es stellt sicher, dass Schreiboperationen atomar erfolgen und die Integrität der Business-Models gewahrt bleibt.

### Der Datenfluss
`UI Layer` $\rightarrow$ **UseCase** $\rightarrow$ **Manager** $\rightarrow$ **Processor**

#### Manager (Logic Layer)
- **Zweck**: Zentrale Steuereinheit pro Business Model, die sowohl Lese- als auch Schreiboperationen koordiniert.
- **Verantwortung**: Führt im Write-Kontext die Persistierung via Processor durch und invalidiert daraufhin zwingend alle relevanten eigenen Read-Caches (Cache-Sidecar). Komplexe Multi-Domain-Aggregationen werden bewusst vermieden; eine solche Orchestrierung ist Aufgabe der UseCases.
- **Granularität**: **Ein Manager pro Business Model**.

#### Processor (Data Layer)
- **Zweck**: Übernimmt die physische Persistierung eines Business-Models.
- **Schnittstelle**: Das `ProcessorInterface` liegt zwingend in der **Logic Layer** (Dependency Inversion).
- **Input**: Ein Business-Model.
- **Rückgabewert**: Das aktualisierte/neu erstellte Business-Model (z.B. zur Rückgabe von generierten IDs).
- **Fehlerbehandlung**: Wirft im Fehlerfall direkt **Domain-Exceptions**.

#### Transaktionssteuerung ("All or Nothing")
Um Schreibvorgänge atomar zu gestalten, wird ein `TransactionManager` eingesetzt, der im Data-Layer implementiert ist (z.B. mittels Doctrine) und gegen ein Interface in der Logic Layer spricht. 
- **Strategie**: Der UseCase übergibt die gesamte Schreiblogik als Callback (`callable`) an den TransactionManager. Die Logik bleibt frei von Boilerplate wie manuellem `beginTransaction()`, `commit()` oder `rollback()`.
- **Mechanik**: Der Manager führt die DB-Transaktion aus. Gibt der Callback einen Wert zurück, wird gecommittet. Tritt eine Exception auf, erfolgt ein automatisches Rollback und die Exception wird hochgeworfen.

```php
// --- Interface (Logic Layer) ---
namespace App\Logic\Common;

interface TransactionManagerInterface
{
    public function execute(callable $work): mixed;
}

// --- Implementierung (Data Layer) ---
namespace App\Data\Infrastructure\Doctrine;

use App\Logic\Common\TransactionManagerInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class EntityManagerTransactionManager implements TransactionManagerInterface
{
    public function __construct(private EntityManagerInterface $em) {}

    public function execute(callable $work): mixed 
    {
        $this->em->beginTransaction();
        try {
            $result = $work();
            $this->em->commit();
            return $result;
        } catch (\Exception $e) {
            $this->em->rollback();
            throw $e;
        } finally {
            if ($this->em->getConnection()->isTransactionActive()) {
                $this->em->rollback();
            }
        }
    }
}
```

### Code Beispiel (Vollständig integriert)
- **Granularität**: Fokus auf eine einzelne Entität pro Processor.

### Code Beispiel

#### Processor Interface & Implementierung
```php
namespace App\Logic\Sales\Order;

use App\Logic\Sales\Order\Model\Order;

interface OrderProcessorInterface
{
    public function save(Order $order): Order;
    public function delete(string $id): void;
}

// --- Data Layer ---
namespace App\Data\Sales\Order\Processor;

use App\Logic\Sales\Order\OrderProcessorInterface;
use App\Logic\Sales\Order\Model\Order;

readonly class OrderProcessor implements OrderProcessorInterface
{
    public function save(Order $order): Order
    {
        // Persistenz-Logik (DB/API) ...
        return $order; // Hier ggf. mit generierter ID zurückgeben
    }

    public function delete(string $id): void
    {
        // Lösch-Logik ...
    }
}
```

#### UseCase mit Transaktionssteuerung
```php
namespace App\Logic\Sales\Order\UseCase;

use App\Logic\Common\EventDispatcherInterface;
use App\Logic\Common\TransactionManagerInterface;
use App\Logic\Sales\Event\OrderCreatedEvent;
use App\Logic\Sales\Order\Dto\CreateOrderRequest;
use App\Logic\Sales\Order\Dto\CreateOrderResponse;
use App\Logic\Sales\Order\Exception\InsufficientStockException;
use App\Logic\Sales\Order\Manager\OrderManagerInterface;
use App\Logic\Sales\Stock\Manager\StockManagerInterface;

readonly class CreateOrderUseCase
{
    public function __construct(
        private TransactionManagerInterface $transactionManager,
        private OrderManagerInterface $orderManager,
        private StockManagerInterface $stockManager,
        private EventDispatcherInterface $eventDispatcher,
    ) {}

    public function execute(CreateOrderRequest $request): CreateOrderResponse 
    {
        return $this->transactionManager->execute(function () use ($request): CreateOrderResponse {
            // 1. Vorbedingungen prüfen -> Fehlschlag löst automatisches Rollback aus.
            if (!$this->stockManager->isSufficient($request->items)) {
                throw new InsufficientStockException();
            }

            // 2. Business Model anlegen und eigene State-Logik ausführen.
            $order = new Order(/* ... */);
            $order->confirm(); 

            // 3. Persistieren (Manager -> Processor).
            $createdOrder = $this->orderManager->create($order);

            // 4. Domänen-Event dispatchen. Die Bridge-Implementierung des
            //    EventDispatcherInterface garantiert ein Post-Commit-Dispatching,
            //    sodass das Event nur den Bus erreicht, wenn die Transaktion
            //    erfolgreich abgeschlossen wurde (siehe Sektion 1).
            $this->eventDispatcher->dispatch(new OrderCreatedEvent(orderId: $createdOrder->id));

            return new CreateOrderResponse(orderId: $createdOrder->id);
        });
    }
}
```

## 4. Validierungsstrategie (Mehrschichtig)

Validierung findet so früh wie möglich statt. Jede Layer prüft, was sie prüfen kann — von syntaktischen Format-Checks im Frontend über PHP-basierte Validierung im UI-Layer bis hin zu komplexen Business-Regeln in der Logic Layer. Ein schlecht formatierter Input schlägt bereits im Controller fehl, wohingegen eine komplexe fachliche Regel erst in der UseCase- oder Model-Schicht geprüft wird.

### Verantwortung pro Layer

| Layer | Was wird geprüft? | Beispiel |
| :--- | :--- | :--- |
| **Frontend** | Sofortiges Format-Feedback | E-Mail-Format, Positive Nummern |
| **UI (Controller)** | Syntaktische Prüfung mit reinem PHP | Pflichtfelder, Datentypen, Längen |
| **Logic (UseCase / Model)** | Business-Regeln, Cross-Entity Constraints, Zustandsgültigkeit | Host auflösbar? Guthaben reicht? Statusübergang erlaubt? |
| **Data (Processor)** | Externe Constraints, DB-Integrität | Unique-Konflikt, API Rate Limits |

### Syntaktische Validierung im Controller

Die UI Layer führt syntaktische Checks mit reinem PHP durch – ohne Framework-Validator oder Annotations auf den DTOs. Request-DTOs in der Logic Layer bleiben unverändert und frei von jeglichen Framework-Abhängigkeiten.

```php
namespace App\UI\Http\Sales\Order;

readonly class OrderController extends AbstractController
{
    public function create(
        Request $request,
        CreateOrderUseCase $useCase
    ): JsonResponse {
        // DTO aus Request bauen ...
        $email = $request->request->get('customerEmail');
        $name = $request->request->get('customerName');
        $items = $request->request->all('items') ?? [];

        // Syntaktische Validierung mit reinem PHP (frühes Fail)
        if (!filter_var($email, FILTER_VALIDATE_EMAIL)) {
            return new JsonResponse(['error' => 'Invalid email format'], 422);
        }

        if (empty($name) || strlen($name) < 2) {
            return new JsonResponse(['error' => 'Customer name is required'], 422);
        }

        if (empty($items)) {
            return new JsonResponse(['error' => 'Order must contain items'], 422);
        }

        $dto = new CreateOrderRequest(
            customerEmail: $email,
            customerName: $name,
            itemIds: $items,
        );

        // UseCase übernimmt Business-Validierung
        $responseDto = $useCase->execute($dto);
        return new JsonResponse(['orderId' => $responseDto->orderId], 201);
    }
}
```

### Business Models ohne Symfony-Attributes

Business Models in `src/Logic` sind frei von Framework-Abhängigkeiten. Ihre Invarianten werden im Konstruktor mit reinem PHP durchgesetzt:

```php
class Order extends BaseModel
{
    public function __construct(public readonly string $email, public readonly float $totalAmount) 
    {
        if ($this->totalAmount < 0) {
            throw new BusinessRuleViolationException('Order amount must be positive');
        }
    }

    public function canShip(): bool 
    {
        return $this->status === OrderStatus::PAID && $this->isAddressComplete();
    }
}
```

### Vorteile
- **Frühes Erkennen**: Formatfehler werden im Controller erkannt, sodass keine UseCase- oder Data-Layer-Logik ausgelastet wird.
- **Framework-Unabhängigkeit**: Die Logic Layer bleibt frei von Symfony-Attributen und ist unabhängig testbar.
- **Schichtenweise Abdeckung**: Jede Schicht validiert ihren Bereich — das Frontend schützt den Controller, der Controller schützt den UseCase, der UseCase schützt die Persistenz.
## 5. Entity vs. Business Model (`src/Data` $\rightarrow$ `src/Logic`)

Um die Geschäftslogik vollständig vom Framework und dem ORM zu entkoppeln, wird eine strikte Trennung zwischen Persistenz-Objekten (Entities) und Domänen-Objekten (Business Models) eingeführt.

### Warum diese Trennung?
1. **Framework-Unabhängigkeit**: Die Logic Layer bleibt ein "Pure PHP" Bereich. Änderungen am ORM oder ein Wechsel der Datenbanktechnologie haben keinen Einfluss auf die Business Rules.
2. **Vermeidung von Side-Effects**: ORMs wie Doctrine nutzen Lazy-Loading Proxies. Wenn diese Objekte in die Logic Layer gelangen, können unerwartete DB-Abfragen (N+1 Problem) an Stellen auftreten, an denen keine Infrastruktur-Logik sein sollte.
3. **Anämische vs. Reichhaltige Modelle**: Persistenz-Entities sind oft "anämisch" (nur Getter/Setter), um die DB-Struktur abzubilden. Business Models hingegen enthalten Rich Domain Logic.

### Definitionen

#### Persistence Entity (`src/Data`)
- **Zweck**: Spiegelung des Datenbank-Schemas.
- **Eigenschaften**: Enthält Framework-Annotationen/Attribute (z.B. `#[ORM\Entity]`).
- **Regel**: Darf niemals die Grenze zur Logic Layer überschreiten.

#### Business Model (`src/Logic`)
- **Zweck**: Repräsentation des fachlichen Objekts innerhalb der Geschäftslogik.
- **Eigenschaften**: POPO (Plain Old PHP Object), readonly wo möglich, enthält Business-Validierung und Logik.
- **Logik-Verteilung**:
    - **Intrinsic Logic** (im Model): Beantwortet Fragen über den eigenen Zustand, die keine externen Abhängigkeiten benötigen (z.B. `getFullName()`, `getAge()`, `isExpired()`).
    - **Extrinsic Logic** (im UseCase): Steuert Prozesse, Koordination und Logik mit externen Abhängigkeiten (z.B. Prüfung von Beständen via Processor).
- **Zustandsübergänge (State Transitions)**: Das Model kapselt die eigenen Statusänderungen. Anstatt `setStatus()` zu nutzen, bietet das Model explizite Methoden an (`confirm()`, `cancel()`), welche die Erlaubtheit des Übergangs prüfen und eine fachliche Exception werfen, falls er gegen die Geschäftsregeln verstößt.
- **Regel**: Kennt keine Details über die Persistenz.

### Übergabe & Mapping
Der Austausch erfolgt ausschließlich über ein Mapping in der Data Layer:
- **Provider (Read)**: `Persistence Entity` $\rightarrow$ `Business Model`
- **Processor (Write)**: `Business Model` $\rightarrow$ `Persistence Entity`

Das Mapping stellt sicher, dass die Logic Layer nur mit stabilen, validen Objekten arbeitet und nicht mit instabilen ORM-Proxies.

## 6. Mapping Pattern (`src/Data`)

Um Redundanz zu vermeiden und eine konsistente Transformation zwischen Persistenz- und Domänenebene zu gewährleisten, werden dedizierte Mapper-Klassen eingesetzt.

### Verantwortung & Ort
- **Ort**: Mappers befinden sich in `src/Data/{Module}/{Feature}/Mapper`.
- **Zweck**: Sie kapseln die Logik der Konvertierung. Dadurch bleiben Provider und Processor schlank und konzentrieren sich nur auf den Datenzugriff bzw. die Persistenz.
- **Status**: Mapper sind strikt zustandslos (stateless).

### Mapping Richtungen (Factory-Pattern)
Ein Mapper implementiert drei klar getrennte Methoden, um Create- und Update-Pfade zu trennen:
1. `toModel(Entity $entity): Model`: Konvertierung von der DB zur Logik (genutzt in Providern).
2. `createEntity(Model $model): Entity`: Erstellt eine brandneue Entity aus dem Model (für INSERT). Gibt das frische Entity-Objekt zurück, das vom Processor persistiert wird.
3. `updateEntity(Model $model, Entity $entity): void`: Schreibt die Felder des Models in eine bestehende, bereits vom EntityManager verwaltete Entity (für UPDATE). Die Entity wird über den PRIMARY KEY im Processor geladen (`$em->find()`), dann an den Mapper übergaben. Nach dem `flush()` erkennt Doctrine dank Dirty-Checking die Änderungen. Der Mapper selbst kennt Doctrine nicht — er ist reines Feld-Mapping.

Die Trennung von Create und Update vermeidet folgende Probleme:
- **Klare Semantik**: Keine optionale Parameter mit implizitem Verhalten (`null` = erstelle neu, vorhanden = aktualisiere).
- **Einfachere Tests**: Jeder Pfad wird isoliert testbar, ohne Mock-Fälle für beides abzudecken.
- **Doctrine-Lifecycle-Korrekte Trennung**: Eine Entity muss beim UPDATE bereits vom EntityManager verwaltet sein (`$em->find()`). Der Mapper sollte sich nicht darum kümmern — das ist Prozessoraufgabe.

### DTO-zu-Model-Mapping (Logic Layer)
Um die UseCases schlank zu halten, findet die Transformation von Eingabe-DTOs (`Request`) in Business Models nicht durch Inline-Logik im UseCase statt. Stattdessen kommen dedizierte **ModelFactories** oder **Logic-Maps** zum Einsatz.

- **Ort**: `src/Logic/{Module}/{Feature}/Mapping/`
- **Verantwortung**: Abbildung der Struktur von einem DTO auf ein Business Model (einschließlich Child-Models).
- **Dependency Rule**: Diese Mapper liegen zwingend in der Logic Layer und kennen keine Entities oder Provider.

#### Factory-Pattern (Create-Szenario)
Für neue Objekte liefert die Factory ein komplett neues, valides Business-Model.

```php
namespace App\Logic\Sales\Order\Mapping;

use App\Logic\Sales\Order\Dto\CreateOrderRequest;
use App\Logic\Sales\Order\Model\Order;
use App\Logic\Sales\Order\Model\OrderItem;
use App\Logic\Sales\Order\Model\OrderStatus;

readonly class OrderModelFactory
{
    public function createFromRequest(CreateOrderRequest $request): Order
    {
        return new Order(
            customerId: $request->customerId,
            // Delegierung der Child-Objects
            items: array_map(
                fn($data) => new OrderItem(productId: $data['productId'], quantity: $data['quantity']),
                $request->items
            ),
            status: OrderStatus::Created,
        );
    }
}
```

#### Factory-Pattern für Updates (Full Rebuild)
Das einfachste Verfahren. Die Factory erhält den Request-DTO und erstellt wie gewohnt ein neues Model mit allen Daten des Requests — inklusive der ID des bestehenden Objekts. Da Models im Logic Layer rein funktional sind, ist das Erstellen eines neuen Modells billiger als ein Objekt zu kopieren und zu manipulieren.

```php
namespace App\Logic\Sales\Order\Mapping;

use App\Logic\Sales\Order\Dto\UpdateOrderRequest; // Enthält id:, email:, amount: ...
use App\Logic\Sales\Order\Model\Order;

readonly class OrderModelFactory
{
    public function updateFromRequest(UpdateOrderRequest $request): Order
    {
        return new Order(
            id: $request->id,         // ID aus dem Request übernehmen
            customerEmail: $request->customerEmail ?? null,
            totalAmount: $request->totalAmount,
            // ... restliche Felder (bei Partial Updates ggf. per Provider ergänzt)
        );
    }
}
```

#### Rebuild-Factor-Pattern für Updates (Partial & Full)
Um das Business Model "Create-only" und schlank zu halten, sind Update-Operationen immer Aufgabe einer Factory.
Sowohl bei einem kompletten Neuberechnungsprozess als auch bei der Verwendung von Partial-DTOs (Updates), greifen wir ausschließlich auf eine zentralisierte Factory zurück:

```php
namespace App\Logic\Sales\Order\Mapping;

use App\Logic\Sales\Order\Dto\UpdateOrderRequest; // Enthält id:, email?, amount? ... 
use App\Logic\Sales\Order\Model\Order;

readonly class OrderModelFactory 
{
    public function rebuildFromPartialUpdate(Order $current, UpdateOrderRequest $request): Order
    {
        return new self(
            id: $request->id,
            customerEmail: $request->customerEmail ?? $current->customerEmail,
            totalAmount: $request->totalAmount ?? $current->totalAmount,
            // ... übrige Felder werden unverändert übernommen.
        );
    }
}

// Verwendung im UseCase:
$dto = new UpdateOrderRequest(id: '123', customerEmail: 'neuer@test.de');
$updatedOrder = $this->orderModelFactory->rebuildFromPartialUpdate($existingOrder, $dto);
```

### Code Beispiel (Data Layer Mapping)

#### Mapper Implementierung
```php
namespace App\Data\Sales\Order\Mapper;

use App\Data\Sales\Order\Entity\OrderEntity;
use App\Logic\Sales\Order\Model\Order;

readonly class OrderMapper
{
    public function toModel(OrderEntity $entity): Order
    {
        return new Order(
            id: $entity->getId(),
            customerEmail: $entity->getEmail(),
            totalAmount: $entity->getAmount(),
            // ... weitere Felder
        );
    }

    public function createEntity(Order $model): OrderEntity
    {
        return (new OrderEntity())
            ->setEmail($model->customerEmail)
            ->setAmount($model->totalAmount);
            // ... weitere Felder
    }

    public function updateEntity(Order $model, OrderEntity $entity): void
    {
        $entity->setEmail($model->customerEmail);
        $entity->setAmount($model->totalAmount);
        // ... weitere Felder
    }
}
```

#### Integration in Processor (Complete/Update-Unterscheidung)
Der Processor entscheidet basierend auf der Model-ID, ob er `createEntity()` oder `updateEntity()` aufruft. Bei einem Update lädt er zunächst die bestehende Entity vom EntityManager, sodass Doctrine den PRIMARY KEY und den Lifecycle korrekt verwaltet:

```php
namespace App\Data\Sales\Order\Processor;

use App\Data\Sales\Order\Entity\OrderEntity;
use App\Data\Sales\Order\Mapper\OrderMapper;
use App\Logic\Sales\Order\Model\Order;
use App\Logic\Sales\Order\OrderProcessorInterface;
use Doctrine\ORM\EntityManagerInterface;

readonly class OrderProcessor implements OrderProcessorInterface
{
    public function __construct(
        private EntityManagerInterface $em,
        private OrderMapper $mapper,
    ) {}

    public function save(Order $model): Order
    {
        if ($model->id === null) {
            // CREATE-Pfad: Neue Entity erzeugen und persistieren
            $entity = $this->mapper->createEntity($model);
            $this->em->persist($entity);
            $this->em->flush();

            // ID vom Entity zurück in das Model übernehmen
            return new Order(
                id: $entity->getId(),
                customerEmail: $model->customerEmail,
                totalAmount: $model->totalAmount,
                orderItems: $model->orderItems,
                status: $model->status,
            );
        }

        // UPDATE-Pfad: Bestehende Entity laden und aktualisieren
        $entity = $this->em->getRepository(OrderEntity::class)->find($model->id);

        if ($entity === null) {
            throw new OrderNotFoundException($model->id);
        }

        $this->mapper->updateEntity($model, $entity);
        $this->em->flush(); // Doctrine Dirty-Checking übernimmt den Rest

        return $model;
    }
}
```

## 7. Externe Systemintegration (`src/Data`)

Die Anbindung von Drittsystemen (REST APIs, Soap, Message Queues) wird technologisch identisch zur Datenbank-Persistenz behandelt, um die Logic Layer vor technischen Details der Kommunikation zu schützen.

### Architektur & Datenfluss
Der Fluss folgt dem Read- und Write-Pattern:
`Logic Layer` $\rightarrow$ `Provider / Processor` $\rightarrow$ `External Client` $\rightarrow$ `API/Drittsystem`

#### 1. External Clients (Infrastruktur)
- **Zweck**: Technische Umsetzung der Kommunikation (z.B. via Symfony HttpClient).
- **Verantwortung**: Header, Authentication, Request-Body-Formatierung und reine HTTP-Antworten.
- **Ort**: `src/Data/{Module}/{Feature}/Client`.

#### 2. Integration in Provider/Processor
Die Provider/Processor fungieren als Adapter zwischen dem Client und der Domäne:
- **Read (Provider)**: Ruft den Client auf, empfängt die Rohantwort (z.B. JSON) und nutzt einen Mapper, um diese in ein **Business Model** zu transformieren.
- **Write (Processor)**: Transformiert das Business Model über einen Mapper in das gewünschte API-Format und sendet es via Client an das Drittsystem.

#### 3. Fehlerhandling & Exception Translation
Technische Fehler dürfen nicht ungefiltert in die Logic Layer gelangen. Der Provider/Processor muss folgende Übersetzung vornehmen:
- **HTTP 404** $\rightarrow$ `ResourceNotFoundException` (Domain)
- **HTTP 401/403** $\rightarrow$ `ExternalSystemAccessException` (Domain)
- **HTTP 500 / Timeout** $\rightarrow$ `ExternalSystemUnavailableException` (Domain)

### Code Beispiel (Konzept)
```php
namespace App\Data\Sales\Order\Provider;

use App\Data\Sales\Order\Client\ShippingApiClient;
use App\Data\Sales\Order\Mapper\ShippingApiMapper;
use App\Logic\Sales\Order\Model\ShippingStatus;
use App\Logic\Common\Exception\ExternalSystemUnavailableException;

readonly class ShippingProvider implements ShippingProviderInterface
{
    public function __construct(
        private ShippingApiClient $client, 
        private ShippingApiMapper $mapper
    ) {}

    public function getStatus(string $trackingId): ShippingStatus
    {
        try {
            $response = $this->client->fetchStatus($trackingId);
            return $this->mapper->toModel($response);
        } catch (TransportException $e) {
            throw new ExternalSystemUnavailableException('Shipping API is down', 0, $e);
        }
    }
}
```

## 8. UI Controller Pattern (`src/UI`)

Die Controller fungieren als reine Adapter zwischen dem externen Eintrittspunkt (HTTP/CLI) und der Logic Layer. Sie enthalten keine Geschäftslogik.

### Verantwortlichkeiten & Workflow
Ein Controller führt strikt folgende Schritte aus:
1. **Syntaktische Validierung**: Prüfung, ob die notwendigen Parameter vorhanden sind und das richtige Format haben.
2. **Authentifizierung & Grob-Autorisierung**: Sicherstellung, dass der User eingeloggt ist und über die erforderliche Rolle verfügt (z.B. via Symfony Security).
3. **DTO-Transformation**: Umwandlung des HTTP-Requests in ein spezifisches Request-DTO für den UseCase oder die BusinessQuery.
4. **Delegation**: Aufruf eines `UseCase` (für Schreiboperationen) oder einer `BusinessQuery` (für Leseoperationen).
5. **Response-Mapping**: Transformation des Response-DTOs in eine HTTP-Antwort (z.B. JSON via `JsonResponse`).

### Kommunikation mit der Logic Layer
- **Commands/Writes**: Der Controller ruft einen UseCase auf $\rightarrow$ Ergebnis ist oft `void` oder ein Bestätigungs-DTO.
- **Queries/Reads**: Der Controller ruft eine BusinessQuery auf $\rightarrow$ Ergebnis ist ein Response-DTO zur Darstellung.

### Entkoppelte Autorisierung (Fine-Grained)
Während die grob-körnige Autorisierung (Rollen) im Controller/Framework erfolgt, wird die feinkörnige Logik delegiert:
- Der Controller nutzt Framework-Voter oder Security-Services.
- Diese rufen im Hintergrund eine `BusinessQuery` oder einen `PermissionService` in der **Logic Layer** auf, um basierend auf fachlichen Regeln zu entscheiden (z.B. "Besitzt der User dieses Objekt?").

### Code Beispiel (Konzept)
```php
namespace App\UI\Http\Sales\Order;

use App\Logic\Sales\Order\UseCase\CreateOrderUseCase;
use App\Logic\Sales\Order\Query\GetOrderQuery;
use App\Logic\Sales\Order\Dto\CreateOrderRequest;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class OrderController extends AbstractController
{
    public function create(Request $request, CreateOrderUseCase $useCase): JsonResponse
    {
        // 1. Syntaktische Validierung & Request-DTO Mapping
        $dto = new CreateOrderRequest(
            customerId: $request->get('customer_id'),
            items: $request->get('items')
        );

        // 2. Delegation an UseCase
        $responseDto = $useCase->execute($dto);

        return new JsonResponse(['orderId' => $responseDto->orderId], 201);
    }

    public function show(string $id, GetOrderQuery $query): JsonResponse
    {
        // Grob-autorisierung erfolgt via Symfony Attribute/Voter
        // Feinkörnige Prüfung wird innerhalb der Query oder über Voter -> Logic Layer gelöst.

        $responseDto = $query->execute(new GetOrderRequest($id));

        return new JsonResponse($responseDto->toArray());
    }
}
```

## 9. Exception Hierarchie & Error Handling (`src/Logic` $\rightarrow$ `src/UI`)

Um eine konsistente Fehlerbehandlung über alle Layer hinweg zu gewährleisten, wird eine strukturierte Exception-Hierarchie eingesetzt. Dies erlaubt es der UI-Layer, Exceptions gruppiert und damit automatisiert in HTTP-Statuscodes zu übersetzen, ohne jede einzelne Exception explizit kennen zu müssen.

### Die Hierarchie (`src/Logic/Common/Exception`)
Alle fachlichen Exceptions erben von einer abstrakten Basisklasse `DomainException`.

#### Code-Skelett (Basisimplementierung)
```php
// src/Logic/Common/Exception/
namespace App\Logic\Common\Exception;

abstract class DomainException extends \RuntimeException {}

class ResourceNotFoundException extends DomainException {}
class BusinessRuleViolationException extends DomainException {}
class AccessDeniedException extends DomainException {}
class InfrastructureException extends DomainException {}
```

#### 2. Kategorien & HTTP-Mapping
Die Basis-Exceptions definieren das Verhalten im UI-Layer. Module implementieren darauf aufbauend spezifische Exceptions (z. B. `OrderNotFoundException` erbt von `ResourceNotFoundException`).

| Exception Gruppe | HTTP Status | Zweck | Beispiel |
| :--- | :--- | :--- | :--- |
| `ResourceNotFoundException` | **404 Not Found** | Ressource existiert nicht. | `UserNotFoundException` |
| `BusinessRuleViolationException` | **422 Unprocessable Entity** | Geschäftsregel verletzt. | `InsufficientFundsException` |
| `AccessDeniedException` | **403 Forbidden** | Fehlende Berechtigung. | `PermissionDeniedException` |
| `InfrastructureException` | **503 Service Unavailable** | Technische Fehler (extern). | `ExternalApiTimeoutException` |

### Umsetzung im UI-Layer (Centralized Handling)
Die Übersetzung erfolgt in einem zentralen Exception-Subscriber oder Listener der UI-Layer. Anstatt `try-catch`-Blöcke in jedem Controller zu nutzen, fängt dieser Subscriber die Exceptions global ab:

```php
// Konzeptueller Logik-Auszug des Subscribers
if ($exception instanceof ResourceNotFoundException) {
    return new JsonResponse(['error' => $exception->getMessage()], 404);
} 
if ($exception instanceof BusinessRuleViolationException) {
    return new JsonResponse(['error' => $exception->getMessage()], 422);
}
// ... etc.
```

### Vorteile dieses Ansatzes
- **Entkopplung**: Die Logic Layer definiert nur *was* passiert ist (z.B. "Order nicht gefunden"). Die UI Layer entscheidet, *wie* dies dem User präsentiert wird (HTTP 404).
- **Wartbarkeit**: Neue Exceptions in der Logic Layer müssen lediglich einer bestehenden Kategorie zugeordnet werden und funktionieren sofort im Frontend/API ohne Anpassungen am UI-Code.
- **Konsistenz**: Alle Endpunkte reagieren bei gleichen Fehlerarten identisch.

## 10. Event Handling & Messaging (`src/Logic` $\rightarrow$ `src/Data`)

Das System nutzt eine differenzierte Strategie für Ereignisse, um Entkopplung zu gewährleisten und gleichzeitig die Last auf die APIs gering zu halten.

### Typen von Events
Je nach Reichweite und Zeitkritikalität kommen verschiedene Mechanismen zum Einsatz:

#### 1. Internal Events (Synchron)
- **Zweck**: Sofortige Nebenwirkungen innerhalb desselben Request-Zyklus.
- **Interface**: `InternalEventDispatcher`
- **Payload**: Übergabe von Business-Models ist zulässig, da der Zustand konsistent bleibt.
- **Beispiel**: Aktualisierung eines lokalen In-Memory Caches nach einer Änderung.

#### 2. Async Events (Lokal Asynchron)
- **Zweck**: Zeitintensive Aufgaben, die nicht den HTTP-Response verzögern dürfen.
- **Interface**: `AsyncEventPublisher`
- **Payload**: Nutzung von dedizierten **Event-DTOs**. Es wird das Prinzip des *Event-Carried State Transfer* angewandt (nur notwendige Daten senden), um unnötige Callbacks zum Quellsystem zu vermeiden.
- **Beispiel**: Versenden einer Bestätigungsmail nach Bestellung.

#### 3. Broadcast Events (Extern/Service-übergreifend)
- **Zweck**: Information anderer Microservices über eine Zustandsänderung.
- **Interface**: `BroadcastEventPublisher`
- **Payload**: Kompakte, fachspezifische Event-DTOs (*Event-Carried State Transfer*).
- **Besonderheit**: Die Definitionen dieser Broadcast-Events (DTOs/Schemas) werden in einem **separaten externen Repository** gepflegt. Dies ermöglicht es anderen Services, die Messages versioniert zu importieren, ohne Abhängigkeiten zum Haupt-Repository zu haben.
- **Beispiel**: Benachrichtigung des Logistik-Systems über eine neue Bestellung.

### Transaktions-Sicherheit (Transactional Consistency)
Um "Geister-Events" durch Datenbank-Rollbacks zu vermeiden, ist das Dispatching von Async- und Broadcast-Events zwingend an einen erfolgreichen DB-Commit gebunden. Die konkrete Umsetzung erfolgt über die **Bridge-Konvention** aus Sektion 1: Der UseCase ruft `dispatch()` innerhalb der Transaktions-Closure auf; die `SymfonyEventDispatcherBridge` im Data Layer setzt automatisch den `DispatchAfterCurrentTransactionStamp`, sodass nur com-mittete Daten Events erzeugen. Ein Fehlschlag in der DB verhindert das Erreichen des Message Bus.

### Zusammenfassung Payload-Strategie
| Event Typ | Timing | Payload | Transport |
| :--- | :--- | :--- | :--- |
| **Internal** | Synchron | Models / Objekte | In-Process (EventDispatcher) |
| **Async** | Asynchron | Event-DTOs $\rightarrow$ State Transfer | Message Bus (Local) |
| **Broadcast** | Asynchron | Event-DTOs $\rightarrow$ State Transfer | Message Broker (External) |

## 11. Concurrency & Locking (`src/Logic` $\rightarrow$ `src/Data`)

Zur Vermeidung von Race Conditions bei gleichzeitigen Zugriffen auf dieselben Daten wird standardmäßig das **Optimistic Locking** eingesetzt.

### Funktionsweise des Optimistic Locking
Anstatt Ressourcen exklusiv zu sperren, vertraut das System darauf, dass Konflikte selten auftreten, und prüft die Versionierung beim Schreiben.

1. **Versionierung**: Jede persistierte Einheit im Data Layer besitzt eine Versionsnummer (INT). Diese wird in das entsprechende Business Model der Logic Layer übernommen.
2. **Schreibvorgang**: Beim Speichern eines Modells über einen Processor muss die ursprüngliche Versionsnummer mitgegeben werden. Der Data Layer führt das Update nur aus, wenn die Version in der Datenbank noch mit der des Modells übereinstimmt (`WHERE id = :id AND version = :old_version`).
3. **Konflikterkennung**: Schlägt das Update fehl (0 Zeilen aktualisiert), wirft der Processor eine fachliche `ConcurrencyException` (erbt von `BusinessRuleViolationException`).

### UI-Reaktion
Die UI Layer fängt die `ConcurrencyException` im zentralen Exception-Subscriber ab und übersetzt sie in eine benutzerfreundliche Antwort:
- **HTTP Status**: `409 Conflict`.
- **Botschaft**: Der Benutzer wird informiert, dass der Datensatz in der Zwischenzeit von einem anderen Prozess geändert wurde und ein Refresh/Neu-Laden erforderlich ist.

## 12. Konfiguration & Feature Flags (`src/Logic` $\leftarrow` `src/Data`)

Um die Logic Layer unabhängig von Symfony's Parameter-System zu halten und gleichzeitig pragmatisch zu bleiben, wird ein hybrider Ansatz verfolgt.

### Strategie: Pragmatisches Autowiring
Es wird das Standard-Autowiring von Symfony genutzt, um Konfigurationsaufwand zu minimieren und die Entwicklungsgeschwindigkeit zu erhöhen.

#### 1. Automatische Auflösung (Standard)
Wenn ein Interface (z.B. in `src/Logic`) genau eine korrespondierende Implementierung (z.B. in `src/Data`) in der Anwendung besitzt, erfolgt das Wiring vollständig automatisch durch Symfony. Es ist **keine** manuelle Konfiguration in der `services.yaml` erforderlich.

#### 2. Explizite Bindings (Ausnahme)
Manuelle Einträge in der `services.yaml` sind nur in folgenden Fällen zwingend:
- **Mehrfache Implementierungen**: Wenn ein Interface mehrere Implementierungen besitzt und explizit gesteuert werden muss, welche Version aktuell aktiv ist (z.B. `S3Storage` vs. `LocalStorage`).
- **Externe Bibliotheken**: Wenn Abhängigkeiten innerhalb von Third-Party-Paketen manuell konfiguriert werden müssen.

### Konfigurations-Beispiel (`services.yaml`)
```yaml
services:
    _defaults:
        autowire: true
        autoconfigure: true

    # Nur nötig, wenn mehr als eine Implementierung existiert
    App\Logic\Common\StorageInterface: '@App\Data\Infrastructure\S3Storage'
```

### Hybrid-Strategie für Einstellungen
Je nach Komplexität der benötigten Konfiguration werden zwei Wege genutzt:

#### 1. Direktes Wiring (Einfache Fälle)
Bei einer geringen Anzahl von Parametern (ca. 1-3 Werte) erfolgt die Injektion direkt über den Konstruktor mittels Symfony's Standard-Autowiring/Wiring.
- **Vorteil**: Minimaler Overhead, kein zusätzlicher Boilerplate-Code.

#### 2. Settings-Objekte (Komplexe Fälle / Gruppen)
Sobald eine thematische Gruppe von Einstellungen existiert oder die Anzahl der Parameter steigt, werden diese in einem **Settings-Objekt** gebündelt. Dies ist eine einfache `readonly` Klasse ohne eigene Logik (`POPO`).
- **Zweck**: Vermeidung von "Constructor Bloat" und bessere Gruppierung verwandter Werte.
- **Vorteil**: Einfachere Testbarkeit und saubere Übergabe an Unterdienste.

**Beispiel für ein Settings-Objekt:**
```php
readonly class OrderSettings 
{
    public function __construct(
        public int $maxItemsPerOrder,
        public float $freeShippingThreshold,
        public bool $isInternationalShippingEnabled
    ) {}
}
```

## 13. Testing Strategy & Structure

Die Testsuite ist so aufgebaut, dass sie die Schichtenmodell-Architektur widerspiegelt. Dies erleichtert die Wartung und stellt sicher, dass jede Komponente auf der richtigen Abstraktionsebene geprüft wird.

### Verzeichnisstruktur & Mapping
Die Struktur unter `tests/` folgt strikt der Symmetrie zu `src/`:

| Test Typ | Pfad in `tests/` | Spiegelt $\rightarrow$ | Werkzeug / Ansatz | Fokus |
| :--- | :--- | :--- | :--- | :--- |
| **Unit** | `tests/Unit/Logic/` | `src/Logic/` | PHPUnit + Mocks | Pure Business Logic & Edge Cases. |
| **Integration** | `tests/Integration/Data/` | `src/Data/` | Real DB (Test-Env) | Repositories, Mapper, Infrastruktur. |
| **Functional UI**| `tests/Functional/UI/` | `src/UI/` | Symfony `WebTestCase` | Einzelspezifische Endpunkte / Requests. |
| **Functional E2E**| `tests/Functional/Scenarios/`| (Szenario-basiert) | Symfony `WebTestCase` | Komplexe User Flows (Multi-Step). |

### Detaillierte Guidelines

#### 1. Unit Tests (`tests/Unit/Logic`)
Da die Logic Layer "Pure PHP" ist, müssen diese Tests extrem schnell sein.
- **Kein Framework**: Es wird kein Symfony Kernel gebootet.
- **Mocking**: Interfaces der Data Layer (`Provider`, `Processor`, `TransactionManager`) werden gemockt.
- **Business Models**: Werden *nicht* gemockt, sondern echt verwendet (da sie zustandslos/POPOs sind).

#### 2. Integration Tests (`tests/Integration/Data`)
Hier wird die Brücke zur Infrastruktur geprüft.
- **Datenbank**: Nutzung einer dedizierten Test-DB. Jeder Test sollte in einer Transaktion laufen, die am Ende gerolled wird (oder via Database-Reset).
- **Mapper-Tests**: Explizite Prüfung: `Entity` $\rightarrow$ `toModel()` $\rightarrow$ `Business Model`.

#### 3. Functional Tests - UI-Mirroring (`tests/Functional/UI`)
Diese nutzen den Symfony `WebTestCase` für isolierte Endpunkt-Tests.
*   **Vorteil:** Garantiert genaue HTTP-Antworten (Status-Codes, JSON-Struktur) und isoliert Fehler präzise auf einen Controller.
*   **Nachteil:** Hoher Wartungsaufwand bei jeder API-Änderung. Oft redundante Coverage, wenn UseCases bereits im Logic Layer getestet sind.

#### 4. Functional Tests - Scenarios (`tests/Functional/Scenarios`)
Diese testen den vollständigen Blackbox-Durchlauf über mehrere Endpunkte.
*   **Vorteil:** Stellt sicher, dass komplette Business Flows funktionieren (z.B. Order → Rechnung → Email). Geringere Wartung durch weniger Testfälle und Validierung der Layer-Integration.
*   **Nachteil:** Schwierigeres Debugging. Wenn ein Scenario fehltschlägt, muss man herausfinden, ob der Fehler an Schritt 1 (Validierung), am UseCase oder einem späteren DB-Schritt lag.

#### 5. Empfehlungen zur Auswahl:
- Nutze **UI-Mirroring**, wenn die API-Kontrakte kritisch sind und häufig von externen Systemen verwendet werden.
- Nutze **Scenarios**, um den Business-Wert sicherzustellen und komplexe Transaktionen zu prüfen, die über einen einzigen Request hinausgehen.

#### 4. Mocking Guidelines
Um "fragile Tests" zu vermeiden, gilt:
- **Mock a Interface, not a Class**: Mocke immer das Interface (z.B. `OrderProviderInterface`), niemals die konkrete Implementierung (`OrderProvider`).
- **No Mocks for Models/DTOs**: Value Objects und Business Models werden immer echt instanziiert.
- **Avoid Mocking 3rd Party Libs**: Wenn externe Libs getestet werden müssen, schreibe einen eigenen Wrapper/Interface in die Logic Layer und mocke diesen.
