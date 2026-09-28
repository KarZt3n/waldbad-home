<?php

namespace App\Logic\Membership\Member\UseCase;

use App\Logic\Common\Exception\BusinessRuleViolationException;
use App\Logic\Membership\Member\Dto\MemberResponse;
use App\Logic\Membership\Member\Manager\MemberManagerInterface;
use App\Logic\Membership\Member\Model\PayerType;
use App\Logic\Membership\Member\Model\PaymentMethod;

/**
 * Legt das erste SEPA-Mandat eines neuen Mitglieds (Selbstzahler ohne Mandat) an: Mandatsreferenz
 * „WV-<Mitgliedsnummer>-00001“ — angelehnt an den bisherigen Sage-Bestand, dessen Mandate
 * unverändert bleiben —, Mandatsdatum ist das Eintrittsdatum (beim Mitgliedsantrag der Eingang des
 * Antrags, mit dem die SEPA-Ermächtigung erteilt wurde). Erst danach ist ein Lastschrift-Export
 * möglich.
 */
readonly class CreateMemberMandateUseCase
{
    private const string REFERENCE_FORMAT = 'WV-%s-%05d';

    public function __construct(private MemberManagerInterface $manager)
    {
    }

    public function execute(string $memberId): MemberResponse
    {
        $member = $this->manager->get($memberId);
        if ($member->payerType !== PayerType::SelfPayer || $member->paymentMethod !== PaymentMethod::SepaDirectDebit) {
            throw new BusinessRuleViolationException('Ein SEPA-Mandat gibt es nur für Selbstzahler mit Zahlart SEPA-Lastschrift.');
        }
        if ($member->mandateReference !== null && trim($member->mandateReference) !== '') {
            throw new BusinessRuleViolationException('Für dieses Mitglied ist bereits ein SEPA-Mandat hinterlegt.');
        }

        return MemberResponse::fromMember($this->manager->save(
            $member->withMandate(sprintf(self::REFERENCE_FORMAT, $member->memberNumber, 1), $member->joinedAt, null),
        ));
    }
}
