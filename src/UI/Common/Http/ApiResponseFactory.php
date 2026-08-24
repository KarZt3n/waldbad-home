<?php

namespace App\UI\Common\Http;

use App\Logic\Content\Page\Dto\PageResponse;
use App\Logic\Content\Page\HtmlSanitizerInterface;
use App\Logic\Content\Page\Model\ContentBlock;
use App\Logic\Content\Page\Model\ContentBlockType;
use App\Logic\IdentityAccess\User\Dto\UserResponse;
use App\Logic\IdentityAccess\User\Model\Role;
use Symfony\Component\HttpFoundation\JsonResponse;

readonly class ApiResponseFactory
{
    public function __construct(private HtmlSanitizerInterface $htmlSanitizer)
    {
    }

    public function page(PageResponse $page, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return new JsonResponse($this->pageData($page), $status);
    }

    /**
     * @param list<PageResponse> $pages
     */
    public function pages(array $pages): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map($this->pageData(...), $pages),
            'total' => count($pages),
        ]);
    }

    public function user(UserResponse $user, int $status = JsonResponse::HTTP_OK): JsonResponse
    {
        return new JsonResponse($this->userData($user), $status);
    }

    /**
     * @param list<UserResponse> $users
     */
    public function users(array $users): JsonResponse
    {
        return new JsonResponse([
            'items' => array_map($this->userData(...), $users),
            'total' => count($users),
        ]);
    }

    /**
     * @return array{
     *     id: string,
     *     title: string,
     *     slug: string,
     *     navigationLabel: string,
     *     parentId: string|null,
     *     blocks: list<array{
     *         type: string,
     *         content: string,
     *         mediaUrl: string|null,
     *         mediaAlt: string|null,
     *         mediaSource: string|null,
     *         linkUrl: string|null,
     *         linkLabel: string|null,
     *         layout: string|null,
     *         imageWidthPercent: int|null,
     *         verticalAlignment: string|null,
     *         textAlignment: string|null,
     *         imageFit: string|null,
     *         embeddedPageId: string|null,
     *         eventTitle: string|null,
     *         eventDate: string|null,
     *         eventTime: string|null,
     *         eventIdentifier: string|null,
     *         eventHelpEnabled: bool,
     *         eventHelpButtonLabel: string|null,
     *         eventActivities: list<array{activityId: string, requiredHelpers: int}>,
     *         extensionKey: string|null
     *     }>,
     *     status: string,
     *     visible: bool,
     *     showInNavigation: bool,
     *     navigationPosition: int,
     *     seoTitle: string|null,
     *     seoDescription: string|null,
     *     version: int,
     *     createdAt: string,
     *     updatedAt: string,
     *     publishedAt: string|null
     * }
     */
    private function pageData(PageResponse $page): array
    {
        return [
            'id' => $page->id,
            'title' => $page->title,
            'slug' => $page->slug,
            'navigationLabel' => $page->navigationLabel,
            'parentId' => $page->parentId,
            'blocks' => array_map(
                fn (ContentBlock $block): array => [
                    'type' => $block->type->value,
                    'content' => $this->sanitizedContent($block),
                    'mediaUrl' => $block->mediaUrl,
                    'mediaAlt' => $block->mediaAlt,
                    'mediaSource' => $block->mediaSource,
                    'linkUrl' => $block->linkUrl,
                    'linkLabel' => $block->linkLabel,
                    'layout' => $block->layout,
                    'imageWidthPercent' => $block->imageWidthPercent,
                    'verticalAlignment' => $block->verticalAlignment,
                    'textAlignment' => $block->textAlignment,
                    'imageFit' => $block->imageFit,
                    'embeddedPageId' => $block->embeddedPageId,
                    'eventTitle' => $block->eventTitle === null ? null : trim(strip_tags($block->eventTitle)),
                    'eventDate' => $block->eventDate,
                    'eventTime' => $block->eventTime,
                    'eventIdentifier' => $block->eventIdentifier,
                    'eventHelpEnabled' => $block->eventHelpEnabled,
                    'eventHelpButtonLabel' => $block->eventHelpButtonLabel,
                    'eventActivities' => array_map(
                        static fn (\App\Logic\Content\Page\Model\EventActivityAssignment $activity): array => ['activityId' => $activity->activityId, 'requiredHelpers' => $activity->requiredHelpers],
                        $block->eventActivities,
                    ),
                    'extensionKey' => $block->extensionKey,
                ],
                $page->blocks,
            ),
            'status' => $page->status->value,
            'visible' => $page->visible,
            'showInNavigation' => $page->showInNavigation,
            'navigationPosition' => $page->navigationPosition,
            'seoTitle' => $page->seoTitle,
            'seoDescription' => $page->seoDescription,
            'version' => $page->version,
            'createdAt' => $page->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $page->updatedAt->format(\DateTimeInterface::ATOM),
            'publishedAt' => $page->publishedAt?->format(\DateTimeInterface::ATOM),
        ];
    }

    private function sanitizedContent(ContentBlock $block): string
    {
        return in_array($block->type, [ContentBlockType::Heading, ContentBlockType::Image], true)
            ? $this->htmlSanitizer->sanitizeInline($block->content)
            : $this->htmlSanitizer->sanitize($block->content);
    }

    /**
     * @return array{
     *     id: string,
     *     email: string,
     *     displayName: string,
     *     roles: list<string>,
     *     active: bool,
     *     version: int,
     *     createdAt: string,
     *     updatedAt: string,
     *     lastLoginAt: string|null
     * }
     */
    private function userData(UserResponse $user): array
    {
        return [
            'id' => $user->id,
            'email' => $user->email,
            'displayName' => $user->displayName,
            'roles' => array_map(static fn (Role $role): string => $role->value, $user->roles),
            'active' => $user->active,
            'version' => $user->version,
            'createdAt' => $user->createdAt->format(\DateTimeInterface::ATOM),
            'updatedAt' => $user->updatedAt->format(\DateTimeInterface::ATOM),
            'lastLoginAt' => $user->lastLoginAt?->format(\DateTimeInterface::ATOM),
        ];
    }
}
