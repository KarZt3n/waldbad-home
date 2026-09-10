<?php

namespace App\Logic\Settings\MailTemplate\Query;

use App\Logic\Settings\MailTemplate\Dto\MailTemplateResponse;
use App\Logic\Settings\MailTemplate\Manager\MailTemplateManagerInterface;
use App\Logic\Settings\MailTemplate\Model\MailTemplate;

readonly class GetMailTemplatesQuery
{
    public function __construct(private MailTemplateManagerInterface $manager)
    {
    }

    /**
     * @return list<MailTemplateResponse>
     */
    public function execute(): array
    {
        return array_map(
            static fn (MailTemplate $template): MailTemplateResponse => new MailTemplateResponse(
                key: $template->key->value,
                label: $template->key->label(),
                description: $template->key->description(),
                placeholders: $template->key->placeholders(),
                subject: $template->subject,
                body: $template->body,
                signatureId: $template->signatureId,
                isDefault: $template->isDefault(),
            ),
            $this->manager->resolveAll(),
        );
    }
}
