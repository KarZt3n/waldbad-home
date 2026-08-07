<?php

namespace App\UI\Contact\Request\Http;

use App\Logic\Contact\Request\UseCase\SubmitContactRequestUseCase;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\HttpKernel\Exception\TooManyRequestsHttpException;
use Symfony\Component\RateLimiter\RateLimiterFactory;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/public/v1/contact-requests')]
readonly class PublicContactController
{
    public function __construct(private RateLimiterFactory $contactFormLimiter)
    {
    }

    #[Route('', name: 'api_public_contact_submit', methods: ['POST'])]
    public function submit(Request $request, SubmitContactRequestUseCase $useCase): JsonResponse
    {
        $data = $request->getPayload();
        $name = trim($data->getString('name'));
        $email = trim($data->getString('email'));
        $subject = trim($data->getString('subject'));
        $message = trim($data->getString('message'));
        $privacyAccepted = $data->getBoolean('privacyAccepted');

        $limit = $this->contactFormLimiter->create($request->getClientIp() ?? 'unknown')->consume();
        if (!$limit->isAccepted()) {
            throw new TooManyRequestsHttpException($limit->getRetryAfter()->getTimestamp() - time(), 'Bitte warten Sie, bevor Sie eine weitere Nachricht senden.');
        }

        if ($name === '' || $email === '' || $message === '' || !$privacyAccepted) {
            throw new BadRequestHttpException('Name, E-Mail-Adresse, Nachricht und Datenschutzbestätigung sind erforderlich.');
        }
        if (mb_strlen($name) > 120 || mb_strlen($subject) > 180 || mb_strlen($message) > 8000) {
            throw new BadRequestHttpException('Die Anfrage überschreitet die erlaubte Länge.');
        }
        if (filter_var($email, FILTER_VALIDATE_EMAIL) === false) {
            throw new BadRequestHttpException('Die E-Mail-Adresse ist ungültig.');
        }

        $result = $useCase->execute($name, $email, $subject === '' ? null : $subject, $message);

        return new JsonResponse([
            'id' => $result->id,
            'message' => 'Vielen Dank. Ihre Nachricht wurde übermittelt.',
        ], JsonResponse::HTTP_ACCEPTED);
    }
}
