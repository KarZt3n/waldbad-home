<?php

namespace App\UI\Common\Http;

use App\Logic\Common\Exception\AccessDeniedException;
use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Common\Exception\ConcurrencyException;
use App\Logic\Common\Exception\DomainException;
use App\Logic\Common\Exception\ResourceNotFoundException;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Event\ExceptionEvent;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;

#[AsEventListener]
readonly class DomainExceptionSubscriber
{
    public function __invoke(ExceptionEvent $event): void
    {
        $exception = $event->getThrowable();
        $status = match (true) {
            $exception instanceof ConcurrencyException => JsonResponse::HTTP_CONFLICT,
            $exception instanceof ResourceNotFoundException => JsonResponse::HTTP_NOT_FOUND,
            $exception instanceof AccessDeniedException => JsonResponse::HTTP_FORBIDDEN,
            $exception instanceof BusinessRuleViolationException => JsonResponse::HTTP_UNPROCESSABLE_ENTITY,
            $exception instanceof BadRequestHttpException,
            $exception instanceof \JsonException => JsonResponse::HTTP_BAD_REQUEST,
            default => null,
        };

        if ($status === null) {
            return;
        }

        $code = $exception instanceof DomainException
            ? strtolower((new \ReflectionClass($exception))->getShortName())
            : 'invalid_request';

        $event->setResponse(new JsonResponse([
            'error' => [
                'code' => $code,
                'message' => $exception->getMessage(),
            ],
        ], $status));
    }
}
