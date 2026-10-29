<?php

namespace App\Logic\Content\Extension\Query;

use App\Logic\Content\Extension\Dto\PageExtensionResponse;
use App\Logic\Content\Extension\Dto\PageExtensionTextFieldResponse;
use App\Logic\Content\Extension\Manager\PageExtensionTextsManagerInterface;
use App\Logic\Content\Extension\Model\PageExtension;
use App\Logic\Content\Extension\Model\PageExtensionTextField;

readonly class ListPageExtensionsQuery
{
    public function __construct(private PageExtensionTextsManagerInterface $manager)
    {
    }

    /**
     * @return list<PageExtensionResponse>
     */
    public function execute(): array
    {
        return array_map(function (PageExtension $extension): PageExtensionResponse {
            $texts = $extension->textFields() === [] ? null : $this->manager->resolve($extension);

            return new PageExtensionResponse(
                key: $extension->value,
                label: $extension->label(),
                description: $extension->description(),
                fields: array_map(
                    static fn (PageExtensionTextField $field): PageExtensionTextFieldResponse => new PageExtensionTextFieldResponse(
                        key: $field->key,
                        label: $field->label,
                        text: $texts?->text($field->key) ?? $field->defaultText,
                        defaultText: $field->defaultText,
                        multiline: $field->multiline,
                        consent: $field->consent,
                        isDefault: $texts?->isDefault($field->key) ?? true,
                    ),
                    $extension->textFields(),
                ),
            );
        }, PageExtension::cases());
    }
}
