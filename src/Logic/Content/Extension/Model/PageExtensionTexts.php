<?php

namespace App\Logic\Content\Extension\Model;

use App\Logic\Common\Exception\BusinessRuleViolationException;

/**
 * Die redaktionell angepassten Texte einer Seitenerweiterung. Gespeichert werden nur Abweichungen vom
 * Standardtext (siehe `PageExtension::textFields()`): ein später im Code geänderter Standardtext gilt
 * so automatisch überall, wo nichts angepasst wurde.
 */
readonly class PageExtensionTexts
{
    /** @var array<string, string> */
    public array $overrides;

    /**
     * @param array<string, string> $overrides
     */
    public function __construct(
        public PageExtension $extension,
        array $overrides = [],
    ) {
        $known = [];
        foreach ($overrides as $key => $text) {
            $field = $extension->textField($key);
            // Ein im Code entferntes Feld darf alte gespeicherte Daten nicht unlesbar machen.
            if ($field !== null && $text !== $field->defaultText) {
                $known[$key] = $text;
            }
        }
        $this->overrides = $known;
    }

    public function text(string $key): string
    {
        $field = $this->extension->textField($key) ?? throw new \InvalidArgumentException(sprintf('Unbekanntes Textfeld "%s".', $key));

        return $this->overrides[$key] ?? $field->defaultText;
    }

    /**
     * @return array<string, string> alle Textfelder in der Reihenfolge von `PageExtension::textFields()`
     */
    public function resolved(): array
    {
        $texts = [];
        foreach ($this->extension->textFields() as $field) {
            $texts[$field->key] = $this->overrides[$field->key] ?? $field->defaultText;
        }

        return $texts;
    }

    public function isDefault(string $key): bool
    {
        return !isset($this->overrides[$key]);
    }

    /**
     * Kurzer Fingerabdruck aller Zustimmungstexte (siehe `PageExtensionTextField::$consent`); ändert
     * sich, sobald sich einer dieser Texte ändert. `null` für Erweiterungen ohne Zustimmungstexte.
     */
    public function consentVersion(): ?string
    {
        $consents = [];
        foreach ($this->extension->textFields() as $field) {
            if ($field->consent) {
                $consents[$field->key] = $this->text($field->key);
            }
        }

        return $consents === [] ? null : substr(hash('sha256', json_encode($consents, JSON_THROW_ON_ERROR)), 0, 16);
    }

    /**
     * @param array<string, string> $texts
     */
    public function withTexts(array $texts): self
    {
        $overrides = [];
        foreach ($texts as $key => $text) {
            $field = $this->extension->textField($key) ?? throw new BusinessRuleViolationException(sprintf('Das Textfeld "%s" gibt es für diese Erweiterung nicht.', $key));
            $text = $field->multiline ? trim(str_replace("\r\n", "\n", $text)) : trim($text);
            if ($text === '') {
                throw new BusinessRuleViolationException(sprintf('„%s“ darf nicht leer sein.', $field->label));
            }
            if (mb_strlen($text) > PageExtensionTextField::MAX_LENGTH) {
                throw new BusinessRuleViolationException(sprintf('„%s“ darf höchstens %d Zeichen lang sein.', $field->label, PageExtensionTextField::MAX_LENGTH));
            }
            if (!$field->multiline && str_contains($text, "\n")) {
                throw new BusinessRuleViolationException(sprintf('„%s“ muss einzeilig sein.', $field->label));
            }
            $overrides[$key] = $text;
        }

        return new self($this->extension, $overrides + $this->overrides);
    }
}
