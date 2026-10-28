<?php

namespace App\Logic\Event\Template\UseCase;

use App\Logic\Common\ClockInterface;
use App\Logic\Event\Template\Manager\EventTemplateManagerInterface;

readonly class ReplaceEventTemplateMediaReferenceUseCase
{
    public function __construct(
        private EventTemplateManagerInterface $manager,
        private ClockInterface $clock,
    ) {
    }

    /**
     * @return int Anzahl der geänderten Vorlagen
     */
    public function execute(string $url, ?string $replacementUrl): int
    {
        $changed = 0;
        foreach ($this->manager->all() as $template) {
            if ($template->mediaUrl !== $url) {
                continue;
            }
            $this->manager->save($template->withMediaUrl($replacementUrl, $this->clock->now()));
            ++$changed;
        }

        return $changed;
    }
}
