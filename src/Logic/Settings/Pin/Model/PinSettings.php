<?php

namespace App\Logic\Settings\Pin\Model;

/**
 * Einzeiliger Einstellungs-Datensatz (Singleton). Es gibt einen gemeinsamen, globalen PIN sowie
 * optional je `ProtectedAction` einen eigenen, abweichenden PIN. Für eine geschützte Aktion gilt:
 * ist ein eigener PIN hinterlegt, zählt ausschließlich dieser; sonst der globale PIN. Beide sind
 * bewusst geteilte Codes (nicht je Administrator) — kein weiteres Benutzerkonto-Merkmal.
 */
readonly class PinSettings
{
    /**
     * @param list<ProtectedAction> $protectedActions
     * @param array<string, string> $actionPinHashes Schlüssel = `ProtectedAction::$value`
     */
    public function __construct(
        public ?string $globalPinHash,
        public array $protectedActions,
        public array $actionPinHashes,
    ) {
    }

    public function isProtected(ProtectedAction $action): bool
    {
        return in_array($action, $this->protectedActions, true);
    }

    public function hasOwnPin(ProtectedAction $action): bool
    {
        return isset($this->actionPinHashes[$action->value]);
    }

    /**
     * Ob für $action überhaupt ein PIN geprüft werden könnte (eigener oder globaler) — Grundlage
     * dafür, ob sich $action momentan sinnvoll schützen lässt (siehe `UpdateProtectedActionsUseCase`,
     * `ClearActionPinUseCase`).
     */
    public function hasAnyPin(ProtectedAction $action): bool
    {
        return $this->hasOwnPin($action) || $this->globalPinHash !== null;
    }

    public function matchesPin(ProtectedAction $action, string $pin): bool
    {
        $hash = $this->actionPinHashes[$action->value] ?? $this->globalPinHash;

        return $hash !== null && password_verify($pin, $hash);
    }

    public function withGlobalPinHash(string $pinHash): self
    {
        return new self($pinHash, $this->protectedActions, $this->actionPinHashes);
    }

    /**
     * @param list<ProtectedAction> $protectedActions
     */
    public function withProtectedActions(array $protectedActions): self
    {
        return new self($this->globalPinHash, $protectedActions, $this->actionPinHashes);
    }

    /**
     * $pinHash null entfernt den eigenen PIN wieder (Rückfall auf den globalen PIN).
     */
    public function withActionPinHash(ProtectedAction $action, ?string $pinHash): self
    {
        $hashes = $this->actionPinHashes;
        if ($pinHash === null) {
            unset($hashes[$action->value]);
        } else {
            $hashes[$action->value] = $pinHash;
        }

        return new self($this->globalPinHash, $this->protectedActions, $hashes);
    }
}
