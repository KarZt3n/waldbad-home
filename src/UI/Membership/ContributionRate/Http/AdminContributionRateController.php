<?php

namespace App\UI\Membership\ContributionRate\Http;

use App\Logic\Membership\ContributionRate\Dto\CreateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Dto\UpdateContributionRateRequest;
use App\Logic\Membership\ContributionRate\Model\ContributionCategory;
use App\Logic\Membership\ContributionRate\Model\PendingContributionRateChange;
use App\Logic\Membership\ContributionRate\Model\PersonGroup;
use App\Logic\Membership\ContributionRate\Query\ListContributionRatesQuery;
use App\Logic\Membership\ContributionRate\UseCase\CreateContributionRateUseCase;
use App\Logic\Membership\ContributionRate\UseCase\DeleteContributionRateUseCase;
use App\Logic\Membership\ContributionRate\UseCase\UpdateContributionRateUseCase;
use App\Logic\Membership\PaymentInterval;
use App\UI\IdentityAccess\Security\Permission;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\InputBag;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpKernel\Exception\BadRequestHttpException;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/admin/v1/contribution-rates')]
class AdminContributionRateController extends AbstractController
{
    public function __construct(private readonly ContributionRateResponseFactory $responseFactory)
    {
    }

    #[Route('', name: 'api_admin_contribution_rate_list', methods: ['GET'])]
    public function list(ListContributionRatesQuery $query): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesView->value);

        return new JsonResponse($this->responseFactory->collection($query->execute()));
    }

    #[Route('', name: 'api_admin_contribution_rate_create', methods: ['POST'])]
    public function create(Request $request, CreateContributionRateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesEdit->value);
        $data = $request->getPayload();

        return new JsonResponse($this->responseFactory->rate($useCase->execute(new CreateContributionRateRequest(
            category: $this->category($data),
            label: $this->label($data),
            amountCents: $this->amountCents($data),
            period: $this->period($data),
            personGroup: $this->personGroup($data),
            minAge: $this->optionalInt($data, 'minAge'),
            maxAge: $this->optionalInt($data, 'maxAge'),
            pending: $this->pendingChange($data),
        ))), JsonResponse::HTTP_CREATED);
    }

    #[Route('/{id}', name: 'api_admin_contribution_rate_update', methods: ['PUT'])]
    public function update(string $id, Request $request, UpdateContributionRateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesEdit->value);
        $data = $request->getPayload();

        return new JsonResponse($this->responseFactory->rate($useCase->execute(new UpdateContributionRateRequest(
            id: $id,
            label: $this->label($data),
            amountCents: $this->amountCents($data),
            period: $this->period($data),
            personGroup: $this->personGroup($data),
            minAge: $this->optionalInt($data, 'minAge'),
            maxAge: $this->optionalInt($data, 'maxAge'),
            pending: $this->pendingChange($data),
            validFrom: $this->optionalDate($data, 'validFrom'),
        ))));
    }

    #[Route('/{id}', name: 'api_admin_contribution_rate_delete', methods: ['DELETE'])]
    public function delete(string $id, DeleteContributionRateUseCase $useCase): JsonResponse
    {
        $this->denyAccessUnlessGranted(Permission::ContributionRatesEdit->value);
        $useCase->execute($id);

        return new JsonResponse(null, JsonResponse::HTTP_NO_CONTENT);
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function label(InputBag $data): string
    {
        $label = trim($data->getString('label'));
        if ($label === '' || mb_strlen($label) > 180) {
            throw new BadRequestHttpException('Die Bezeichnung muss zwischen 1 und 180 Zeichen lang sein.');
        }

        return $label;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function category(InputBag $data): ?ContributionCategory
    {
        $value = trim($data->getString('category'));
        if ($value === '') {
            return null;
        }
        try {
            return ContributionCategory::from($value);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Die Kategorie ist ungültig.');
        }
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function personGroup(InputBag $data): ?PersonGroup
    {
        $value = trim($data->getString('personGroup'));
        if ($value === '') {
            return null;
        }
        try {
            return PersonGroup::from($value);
        } catch (\ValueError) {
            throw new BadRequestHttpException('Der Personenkreis ist ungültig.');
        }
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function amountCents(InputBag $data): int
    {
        $amount = $data->get('amountCents');
        if (!is_int($amount) && !(is_string($amount) && ctype_digit($amount))) {
            throw new BadRequestHttpException('Der Betrag muss als Cent-Betrag (ganze Zahl) angegeben werden.');
        }
        $amountCents = (int) $amount;
        if ($amountCents < 0) {
            throw new BadRequestHttpException('Der Betrag darf nicht negativ sein.');
        }

        return $amountCents;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function period(InputBag $data): PaymentInterval
    {
        try {
            return PaymentInterval::from($data->getString('period'));
        } catch (\ValueError) {
            throw new BadRequestHttpException('Der Zeitraum ist ungültig.');
        }
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function optionalInt(InputBag $data, string $key): ?int
    {
        $value = $data->get($key);
        if ($value === null || $value === '') {
            return null;
        }
        if (!is_int($value) && !(is_string($value) && ctype_digit($value))) {
            throw new BadRequestHttpException(sprintf('Das Feld "%s" muss eine ganze Zahl sein.', $key));
        }

        return (int) $value;
    }

    /**
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function optionalDate(InputBag $data, string $key): ?\DateTimeImmutable
    {
        $value = trim($data->getString($key));
        if ($value === '') {
            return null;
        }
        $date = \DateTimeImmutable::createFromFormat('!Y-m-d', $value, new \DateTimeZone('Europe/Berlin'));
        if ($date === false || $date->format('Y-m-d') !== $value) {
            throw new BadRequestHttpException(sprintf('Das Datum im Feld "%s" ist ungültig.', $key));
        }

        return $date;
    }

    /**
     * Die geplante künftige Version des kompletten Beitragssatzes (siehe
     * `PendingContributionRateChange`), verschachtelt unter `pending` im Request-Body — leer
     * (fehlendes `pending` oder ohne `validFrom`) bedeutet „keine geplante Änderung“, was ein zuvor
     * gesetztes Pending beim Speichern wieder löscht.
     *
     * @param InputBag<string|int|float|bool|null> $data
     */
    private function pendingChange(InputBag $data): ?PendingContributionRateChange
    {
        $rawPending = $data->all('pending');
        $values = [];
        foreach ($rawPending as $key => $value) {
            if (!is_string($key) || (!is_scalar($value) && $value !== null)) {
                throw new BadRequestHttpException('Die geplante Änderung muss gültige Beitragsfelder enthalten.');
            }
            $values[$key] = $value;
        }
        $pending = new InputBag($values);
        $validFrom = $this->optionalDate($pending, 'validFrom');
        if ($validFrom === null) {
            return null;
        }

        return new PendingContributionRateChange(
            label: $this->label($pending),
            amountCents: $this->amountCents($pending),
            period: $this->period($pending),
            personGroup: $this->personGroup($pending),
            minAge: $this->optionalInt($pending, 'minAge'),
            maxAge: $this->optionalInt($pending, 'maxAge'),
            validFrom: $validFrom,
        );
    }
}
