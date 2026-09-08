<?php

namespace App\Tests\UI;

use App\Data\Membership\ContributionRate\Entity\ContributionRateEntity;
use App\Data\Membership\Member\Entity\MemberNumberSequenceEntity;
use App\Logic\IdentityAccess\User\Dto\CreateUserRequest;
use App\Logic\IdentityAccess\User\Model\CmsModule;
use App\Logic\IdentityAccess\User\Model\ModuleAccess;
use App\Logic\IdentityAccess\User\Model\ModuleRole;
use App\Logic\IdentityAccess\User\Model\Role;
use App\Logic\IdentityAccess\User\UseCase\CreateUserUseCase;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\Tools\SchemaTool;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\HttpFoundation\File\UploadedFile;

final class MembershipManagementWorkflowTest extends WebTestCase
{
    private KernelBrowser $client;

    protected function setUp(): void
    {
        $this->client = self::createClient();
        $entityManager = self::getContainer()->get(EntityManagerInterface::class);
        if (!$entityManager instanceof EntityManagerInterface) {
            throw new \LogicException('Der EntityManager ist im Testcontainer nicht verfügbar.');
        }
        $metadata = $entityManager->getMetadataFactory()->getAllMetadata();
        $schemaTool = new SchemaTool($entityManager);
        $schemaTool->dropSchema($metadata);
        $schemaTool->createSchema($metadata);
        $this->seedContributionRates($entityManager);
        $entityManager->persist(new MemberNumberSequenceEntity(id: 1, nextValue: 1));
        $entityManager->flush();
    }

    /**
     * `SchemaTool::createSchema()` baut das Schema direkt aus den Entity-Mappings auf und führt
     * dabei keine Migrationen aus — die in `Version20260907120100` per `postUp()` eingefügten
     * Startwerte der Beitragssätze fehlen daher und müssen für die Tests hier nachgebildet werden.
     */
    private function seedContributionRates(EntityManagerInterface $entityManager): void
    {
        $rates = [
            ['individual_junior', 'Einzelperson bis 21 Jahre', 3000, 'yearly', 'individual', null, 20],
            ['individual_senior', 'Einzelperson über 21 Jahre', 5000, 'yearly', 'individual', 21, null],
            ['family_adult', 'Familie: Elternteil', 4000, 'yearly', 'family', null, null],
            ['family_child_paying', 'Familie: Kind (zahlend)', 3000, 'yearly', 'family', 4, 20],
            ['family_child_exempt', 'Familie: Kind (beitragsfrei)', 0, 'yearly', 'family', null, 3],
            ['work_assignment_surcharge', 'Arbeitseinsatz', 1500, 'yearly', null, 8, 65],
            [null, 'Beitrittsgebühr Einzelperson', 1000, 'once', 'individual', null, null],
            [null, 'Beitrittsgebühr Familie', 2000, 'once', 'family', null, null],
        ];
        foreach ($rates as $index => [$category, $label, $amountCents, $period, $personGroup, $minAge, $maxAge]) {
            $entityManager->persist(new ContributionRateEntity(
                id: sprintf('rate-%d', $index + 1),
                category: $category,
                label: $label,
                amountCents: $amountCents,
                period: $period,
                personGroup: $personGroup,
                minAge: $minAge,
                maxAge: $maxAge,
            ));
        }
        $entityManager->flush();
    }

    public function testMemberCanBeCreatedEditedCommentedAndRecalculated(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $member = $this->responseData();
        $memberNumber = $this->string($member, 'memberNumber');
        $memberId = $this->string($member, 'id');
        self::assertMatchesRegularExpression('/^Bad-\d{5}$/', $memberNumber);
        self::assertSame($memberNumber, $member['primaryMemberNumber']);
        self::assertSame('individual_senior', $member['contributionCategory']);
        self::assertSame(5000, $member['contributionAmountCents']);
        self::assertSame(1500, $member['workAssignmentSurchargeCents']);
        $oneTimeCharges = $this->arrayList($member, 'oneTimeCharges');
        self::assertCount(1, $oneTimeCharges);
        $joiningFee = $oneTimeCharges[0];
        self::assertIsArray($joiningFee);
        self::assertSame('Beitrittsgebühr Einzelperson', $joiningFee['label']);
        self::assertSame(1000, $joiningFee['amountCents']);

        $this->client->request('GET', '/api/admin/v1/members', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total']);

        $update = $this->validMember();
        $update['memberNumber'] = $memberNumber;
        $update['primaryMemberNumber'] = $member['primaryMemberNumber'];
        $update['mandateReference'] = $member['mandateReference'];
        $update['nextBookingMonth'] = $member['nextBookingMonth'];
        $update['nextBookingYear'] = $member['nextBookingYear'];
        $update['version'] = $member['version'];
        $update['city'] = 'Brück';
        $this->client->jsonRequest('PUT', '/api/admin/v1/members/'.$memberId, $update, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame('Brück', $this->responseData()['city']);

        $this->client->jsonRequest('POST', '/api/admin/v1/members/'.$memberId.'/remarks', ['text' => 'Karte ausgestellt.'], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $withRemark = $this->responseData();
        $remarks = $this->arrayList($withRemark, 'remarks');
        self::assertCount(1, $remarks);
        $firstRemark = $remarks[0];
        self::assertIsArray($firstRemark);
        self::assertSame('Karte ausgestellt.', $firstRemark['text']);
        self::assertSame('Membership Admin', $firstRemark['authorDisplayName']);

        $this->client->request('POST', '/api/admin/v1/members/'.$memberId.'/recalculate-contribution', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame('individual_senior', $this->responseData()['contributionCategory']);
    }

    public function testMemberCannotBeDeletedWhileAnotherMemberPaysThroughItButCanAfterwards(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $payer = $this->responseData();

        $dependent = $this->validMember();
        $dependent['firstName'] = 'Deps';
        $dependent['payerType'] = 'other_member';
        $dependent['payerMemberId'] = $payer['id'];
        $dependent['accountHolder'] = null;
        $dependent['iban'] = null;
        $dependent['bankName'] = null;
        $dependent['mandateReference'] = null;
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $dependent, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $dependentId = $this->responseData()['id'];

        $this->client->request('DELETE', '/api/admin/v1/members/'.$payer['id'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('zahlen über dieses Mitglied', $this->responseData()['error']['message']);

        $this->client->request('DELETE', '/api/admin/v1/members/'.$dependentId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', '/api/admin/v1/members/'.$payer['id'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('GET', '/api/admin/v1/members', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['total']);
    }

    public function testMemberCannotBeDeletedWhileUsedAsPrimaryMemberNumberButCanAfterwards(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $head = $this->validMember();
        $head['familyRole'] = 'head';
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $head, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $headMember = $this->responseData();

        // Bewusst als Selbstzahler (statt über den Kopf zahlend): so greift beim Löschversuch des
        // Kopfes ausschließlich die Hauptnummer-Abhängigkeit, nicht zusätzlich die Zahler-Prüfung.
        $child = $this->validMember();
        $child['firstName'] = 'Kind';
        $child['birthDate'] = '2015-01-01';
        $child['familyRole'] = 'child';
        $child['primaryMemberNumber'] = $headMember['memberNumber'];
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $child, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $childId = $this->responseData()['id'];

        $this->client->request('DELETE', '/api/admin/v1/members/'.$headMember['id'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
        self::assertStringContainsString('Hauptnummer', $this->responseData()['error']['message']);

        $this->client->request('DELETE', '/api/admin/v1/members/'.$childId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);

        $this->client->request('DELETE', '/api/admin/v1/members/'.$headMember['id'], server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);
    }

    public function testContributionRateCanBeUpdatedAndAffectsAnnualAmount(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->request('GET', '/api/admin/v1/contribution-rates', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $rates = $this->responseData();
        self::assertSame(8, $rates['total']);
        $items = $this->arrayList($rates, 'items');
        $juniorRate = null;
        foreach ($items as $item) {
            self::assertIsArray($item);
            if ($item['category'] === 'individual_junior') {
                $juniorRate = $item;
            }
        }
        self::assertIsArray($juniorRate);
        self::assertSame(3000, $juniorRate['amountCents']);
        self::assertSame(3000, $juniorRate['annualAmountCents']);
        $juniorRateId = $this->string($juniorRate, 'id');

        $this->client->jsonRequest('PUT', '/api/admin/v1/contribution-rates/'.$juniorRateId, [
            'label' => 'Einzelperson bis 18 Jahre',
            'amountCents' => 1500,
            'period' => 'half_yearly',
            'minAge' => null,
            'maxAge' => 18,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $updated = $this->responseData();
        self::assertSame(1500, $updated['amountCents']);
        self::assertSame(3000, $updated['annualAmountCents']);
        self::assertSame(18, $updated['maxAge']);

        $this->client->jsonRequest('POST', '/api/admin/v1/contribution-rates', [
            'label' => 'Einmalige Beitrittsgebühr (Einzelperson)',
            'amountCents' => 1000,
            'period' => 'yearly',
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $custom = $this->responseData();
        self::assertNull($custom['category']);
        $customId = $this->string($custom, 'id');

        $this->client->request('DELETE', '/api/admin/v1/contribution-rates/'.$customId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);

        // Auch eine feste Grundkategorie kann gelöscht werden ...
        $this->client->request('DELETE', '/api/admin/v1/contribution-rates/'.$juniorRateId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(204);
        $this->client->request('GET', '/api/admin/v1/contribution-rates', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $afterDelete = $this->responseData();
        self::assertSame(7, $afterDelete['total']);

        // ... und über dieselbe Kategorie wieder neu angelegt werden.
        $this->client->jsonRequest('POST', '/api/admin/v1/contribution-rates', [
            'category' => 'individual_junior',
            'label' => 'Einzelperson bis 20 Jahre',
            'amountCents' => 3000,
            'period' => 'yearly',
            'maxAge' => 20,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $recreated = $this->responseData();
        self::assertSame('individual_junior', $recreated['category']);

        $this->client->jsonRequest('POST', '/api/admin/v1/contribution-rates', [
            'category' => 'individual_junior',
            'label' => 'Doppelte Kategorie',
            'amountCents' => 3000,
            'period' => 'yearly',
            'maxAge' => 20,
        ], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testMembersCanBeExportedAndImported(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/admin/v1/members/export?format=json', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('application/json', (string) $this->client->getResponse()->headers->get('Content-Type'));
        $content = $this->client->getResponse()->getContent();
        self::assertIsString($content);
        $exported = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        self::assertIsArray($exported);
        self::assertIsArray($exported['members']);
        self::assertCount(1, $exported['members']);

        $csv = "memberNumber;primaryMemberNumber;salutation;lastName;firstName;birthDate;street;postalCode;city;email;phone;familyRole;joinedAt;leftAt;active;function;accountHolder;iban;bankName;mandateReference;paymentMethod;paymentInterval;paymentDay;payerType;payerMemberId;nextBookingMonth;nextBookingYear\n"
            ."M-9001;M-9001;mr;Import;Ida;1980-05-01;Waldweg 1;14822;Borkheide;;;none;2026-01-01;;1;member;Ida Import;DE89370400440532013000;;M-9001;sepa_direct_debit;yearly;first;self_payer;;3;2027\n";
        $tmpFile = tempnam(sys_get_temp_dir(), 'members-import');
        self::assertIsString($tmpFile);
        file_put_contents($tmpFile, $csv);

        $this->client->request('POST', '/api/admin/v1/members/import', ['format' => 'csv'], ['file' => new UploadedFile($tmpFile, 'members.csv', 'text/csv', null, true)], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $importResult = $this->responseData();
        self::assertSame(1, $importResult['created']);
        self::assertSame(0, $importResult['updated']);
        self::assertSame([], $importResult['errors']);

        $this->client->request('POST', '/api/admin/v1/members/import', ['format' => 'csv'], ['file' => new UploadedFile($tmpFile, 'members.csv', 'text/csv', null, true)], ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $secondImport = $this->responseData();
        self::assertSame(0, $secondImport['created']);
        self::assertSame(1, $secondImport['updated']);
    }

    public function testSearchMatchesMultipleWordsAcrossDifferentFieldsRegardlessOfOrder(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $other = $this->validMember();
        $other['lastName'] = 'Beispiel';
        $other['firstName'] = 'Hans';
        $other['city'] = 'Potsdam';
        $other['email'] = 'hans@example.test';
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $other, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);

        // Nachname und Ort, in umgekehrter Reihenfolge zur Eingabe und über zwei verschiedene
        // Felder verteilt — eine reine Teilstring-Suche über einen einzelnen kombinierten Begriff
        // würde das nicht finden.
        $this->client->request('GET', '/api/admin/v1/members?search='.urlencode('Borkheide Musterfrau'), server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $result = $this->responseData();
        self::assertSame(1, $result['total']);
        self::assertSame('Musterfrau', $this->arrayList($result, 'items')[0]['lastName']);

        $this->client->request('GET', '/api/admin/v1/members?search='.urlencode('Hans Beispiel'), server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(1, $this->responseData()['total']);

        $this->client->request('GET', '/api/admin/v1/members?search='.urlencode('Musterfrau Potsdam'), server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(0, $this->responseData()['total']);
    }

    public function testMembershipApplicationCanBeReleasedIntoMembersExactlyOnce(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', [
            'membershipType' => 'individual',
            'applicants' => [[
                'salutation' => 'ms',
                'firstName' => 'Erika',
                'lastName' => 'Musterfrau',
                'birthDate' => '1990-06-15',
                'street' => 'Kirchanger',
                'houseNumber' => '14',
                'postalCode' => '14822',
                'city' => 'Borkheide',
                'phone' => '+49 123 456789',
                'email' => 'erika@example.test',
            ]],
            'accountHolder' => 'Erika Musterfrau',
            'iban' => 'DE89370400440532013000',
            'bankName' => 'Testbank',
            'signerName' => 'Erika Musterfrau',
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'sepaAccepted' => true,
            'emailConsent' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $applicationId = $this->string($this->responseData(), 'id');

        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/release', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $released = $this->responseData();
        self::assertNotNull($released['releasedAt']);
        $releasedMemberIds = $this->arrayList($released, 'releasedMemberIds');
        self::assertCount(1, $releasedMemberIds);
        $releasedMemberId = $releasedMemberIds[0];
        self::assertIsString($releasedMemberId);

        $this->client->request('GET', '/api/admin/v1/members/'.$releasedMemberId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $member = $this->responseData();
        self::assertSame('Musterfrau', $member['lastName']);
        // Die im Antrag erfasste Anrede wird übernommen, statt pauschal auf „Divers“ zu defaulten.
        self::assertSame('ms', $member['salutation']);
        self::assertSame('none', $member['familyRole']);
        self::assertSame('self_payer', $member['payerType']);
        $oneTimeCharges = $this->arrayList($member, 'oneTimeCharges');
        self::assertCount(1, $oneTimeCharges);
        $joiningFee = $oneTimeCharges[0];
        self::assertIsArray($joiningFee);
        self::assertSame('Beitrittsgebühr Einzelperson', $joiningFee['label']);

        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/release', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(422);
    }

    public function testOnlyTheFamilyHeadIsChargedTheFamilyJoiningFee(): void
    {
        $this->client->jsonRequest('POST', '/api/public/v1/membership-applications', [
            'membershipType' => 'family',
            'applicants' => [
                [
                    'salutation' => 'ms', 'firstName' => 'Maria', 'lastName' => 'Muster', 'birthDate' => '1985-01-01',
                    'street' => 'Kirchanger', 'houseNumber' => '14', 'postalCode' => '14822', 'city' => 'Borkheide',
                    'phone' => null, 'email' => 'maria@example.test',
                ],
                [
                    'salutation' => 'mr', 'firstName' => 'Max', 'lastName' => 'Muster', 'birthDate' => '1983-01-01',
                    'street' => 'Kirchanger', 'houseNumber' => '14', 'postalCode' => '14822', 'city' => 'Borkheide',
                    'phone' => null, 'email' => null,
                ],
            ],
            'accountHolder' => 'Maria Muster',
            'iban' => 'DE89370400440532013000',
            'bankName' => 'Testbank',
            'signerName' => 'Maria Muster',
            'termsAccepted' => true,
            'privacyAccepted' => true,
            'sepaAccepted' => true,
            'emailConsent' => true,
        ]);
        self::assertResponseStatusCodeSame(202);
        $applicationId = $this->string($this->responseData(), 'id');

        $csrfToken = $this->loginAsSuperAdmin();
        $this->client->request('POST', '/api/admin/v1/membership-applications/'.$applicationId.'/release', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $releasedMemberIds = $this->arrayList($this->responseData(), 'releasedMemberIds');
        self::assertCount(2, $releasedMemberIds);

        foreach ($releasedMemberIds as $index => $memberId) {
            self::assertIsString($memberId);
            $this->client->request('GET', '/api/admin/v1/members/'.$memberId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
            self::assertResponseIsSuccessful();
            $member = $this->responseData();
            $charges = $this->arrayList($member, 'oneTimeCharges');
            if ($index === 0) {
                self::assertSame('head', $member['familyRole']);
                self::assertCount(1, $charges);
                $charge = $charges[0];
                self::assertIsArray($charge);
                self::assertSame('Beitrittsgebühr Familie', $charge['label']);
                self::assertSame(2000, $charge['amountCents']);
            } else {
                self::assertSame('partner', $member['familyRole']);
                self::assertSame([], $charges);
            }
        }
    }

    public function testHouseholdAndPayerTotalAreAggregatedAcrossFamily(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        // Der Kopf wird zuerst angelegt, bekommt zu diesem Zeitpunkt mangels Kind im Haushalt noch
        // den normalen Einzelpersonen-Satz (siehe Moduldokumentation: die Berechnung erfolgt nur
        // beim jeweils bearbeiteten Datensatz, nicht rückwirkend für Geschwister).
        $head = array_replace($this->validMember(), [
            'lastName' => 'Familie', 'firstName' => 'Kopf', 'birthDate' => '1980-01-01', 'familyRole' => 'head',
        ]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $head, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $headData = $this->responseData();
        $headId = $this->string($headData, 'id');
        $headNumber = $this->string($headData, 'memberNumber');
        self::assertSame(5000, $headData['contributionAmountCents']); // individual_senior, noch ohne Kind im Haushalt

        $child = array_replace($this->validMember(), [
            'lastName' => 'Familie', 'firstName' => 'Kind', 'birthDate' => '2016-01-01', 'familyRole' => 'child',
            'primaryMemberNumber' => $headNumber, 'payerType' => 'other_member', 'payerMemberId' => $headId,
            'accountHolder' => null, 'iban' => null, 'mandateReference' => null,
        ]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $child, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $childData = $this->responseData();
        $childId = $this->string($childData, 'id');
        self::assertSame(4500, $this->int($childData, 'contributionAmountCents') + $this->int($childData, 'workAssignmentSurchargeCents')); // family_child_paying (3000) + Arbeitseinsatz (1500)

        // Jetzt existiert das Kind im Haushalt; eine Neuberechnung des Kopfs greift den Familienrabatt.
        $this->client->request('POST', '/api/admin/v1/members/'.$headId.'/recalculate-contribution', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $recalculatedHead = $this->responseData();
        self::assertSame(5500, $this->int($recalculatedHead, 'contributionAmountCents') + $this->int($recalculatedHead, 'workAssignmentSurchargeCents')); // family_adult (4000) + Arbeitseinsatz (1500)

        // Vom Kind aus abgefragt: Haushalt enthält beide, Zahler ist der Kopf, Summe über beide.
        $this->client->request('GET', '/api/admin/v1/members/'.$childId.'/household', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $household = $this->responseData();
        self::assertSame($headId, $this->string($this->arrayData($household, 'payer'), 'id'));
        self::assertCount(2, $this->arrayList($household, 'householdMembers'));
        self::assertCount(2, $this->arrayList($household, 'payerEntries'));
        self::assertSame(10000, $this->int($household, 'payerTotalAnnualCents'));

        // Vom Kopf selbst aus abgefragt: identische Zahler-Summe (derselbe Selbstzahler).
        $this->client->request('GET', '/api/admin/v1/members/'.$headId.'/household', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame(10000, $this->int($this->responseData(), 'payerTotalAnnualCents'));
    }

    public function testRecalculatingOneHouseholdMemberRecalculatesTheWholeFamily(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $head = array_replace($this->validMember(), [
            'lastName' => 'Familie', 'firstName' => 'Kopf', 'birthDate' => '1980-01-01', 'familyRole' => 'head',
        ]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $head, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $headData = $this->responseData();
        $headId = $this->string($headData, 'id');
        $headNumber = $this->string($headData, 'memberNumber');
        self::assertSame(5000, $this->int($headData, 'contributionAmountCents')); // individual_senior, noch ohne Kind im Haushalt

        $child = array_replace($this->validMember(), [
            'lastName' => 'Familie', 'firstName' => 'Kind', 'birthDate' => '2016-01-01', 'familyRole' => 'child',
            'primaryMemberNumber' => $headNumber, 'payerType' => 'other_member', 'payerMemberId' => $headId,
            'accountHolder' => null, 'iban' => null, 'mandateReference' => null,
        ]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $child, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $childId = $this->string($this->responseData(), 'id');

        // Die Neuberechnung wird über das KIND ausgelöst, nicht über den Kopf. Trotzdem muss auch
        // der Kopf (ein anderes Mitglied desselben Haushalts) den Familienrabatt bekommen, da eine
        // Neuberechnung immer den gesamten Haushalt aktualisiert.
        $this->client->request('POST', '/api/admin/v1/members/'.$childId.'/recalculate-contribution', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame('family_child_paying', $this->responseData()['contributionCategory']);

        $this->client->request('GET', '/api/admin/v1/members/'.$headId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $reloadedHead = $this->responseData();
        self::assertSame('family_adult', $reloadedHead['contributionCategory']);
        self::assertSame(5500, $this->int($reloadedHead, 'contributionAmountCents') + $this->int($reloadedHead, 'workAssignmentSurchargeCents'));
    }

    public function testDashboardReportsMemberCountsAndTotalContribution(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $this->client->jsonRequest('POST', '/api/admin/v1/members', $this->validMember(), ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);

        $inactive = array_replace($this->validMember(), ['lastName' => 'Inaktiv', 'active' => false]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $inactive, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);

        $this->client->request('GET', '/api/admin/v1/membership-dashboard', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $dashboard = $this->responseData();
        self::assertSame(2, $dashboard['totalMembers']);
        self::assertSame(1, $dashboard['activeMembers']);
        self::assertSame(0, $dashboard['pendingApplications']);
        // Je Mitglied: 5000 Beitrag (individual_senior) + 1500 Arbeitseinsatz-Zuschlag.
        self::assertSame(13000, $dashboard['totalContributionCents']);
    }

    public function testRecalculateAllUpdatesEveryMemberIncludingWholeHouseholds(): void
    {
        $csrfToken = $this->loginAsSuperAdmin();

        $standalone = array_replace($this->validMember(), ['lastName' => 'Einzelperson']);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $standalone, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $standaloneId = $this->string($this->responseData(), 'id');

        $head = array_replace($this->validMember(), [
            'lastName' => 'Familie', 'firstName' => 'Kopf', 'birthDate' => '1980-01-01', 'familyRole' => 'head',
        ]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $head, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);
        $headData = $this->responseData();
        $headId = $this->string($headData, 'id');
        $headNumber = $this->string($headData, 'memberNumber');
        self::assertSame(5000, $this->int($headData, 'contributionAmountCents')); // individual_senior, noch ohne Kind im Haushalt

        $child = array_replace($this->validMember(), [
            'lastName' => 'Familie', 'firstName' => 'Kind', 'birthDate' => '2016-01-01', 'familyRole' => 'child',
            'primaryMemberNumber' => $headNumber, 'payerType' => 'other_member', 'payerMemberId' => $headId,
            'accountHolder' => null, 'iban' => null, 'mandateReference' => null,
        ]);
        $this->client->jsonRequest('POST', '/api/admin/v1/members', $child, ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseStatusCodeSame(201);

        // Zum Zeitpunkt der Anlage des Kindes wurde nur dessen eigener (neuer) Datensatz berechnet
        // — der Familienrabatt des bereits vorher angelegten Kopfes wird erst durch die
        // Sammel-Neuberechnung nachgezogen.
        $this->client->request('GET', '/api/admin/v1/members/'.$headId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame('individual_senior', $this->responseData()['contributionCategory']);

        $this->client->request('POST', '/api/admin/v1/members/recalculate-contributions', server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $result = $this->responseData();
        self::assertSame(3, $result['updated']);
        self::assertSame([], $result['errors']);

        $this->client->request('GET', '/api/admin/v1/members/'.$headId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        $reloadedHead = $this->responseData();
        self::assertSame('family_adult', $reloadedHead['contributionCategory']);

        $this->client->request('GET', '/api/admin/v1/members/'.$standaloneId, server: ['HTTP_X_CSRF_TOKEN' => $csrfToken]);
        self::assertResponseIsSuccessful();
        self::assertSame('individual_senior', $this->responseData()['contributionCategory']);
    }

    /**
     * @return array<string, mixed>
     */
    private function validMember(): array
    {
        return [
            'primaryMemberNumber' => null,
            'salutation' => 'ms',
            'lastName' => 'Musterfrau',
            'firstName' => 'Erika',
            'birthDate' => '1990-06-15',
            'street' => 'Kirchanger 14',
            'postalCode' => '14822',
            'city' => 'Borkheide',
            'email' => 'erika@example.test',
            'phone' => null,
            'familyRole' => 'none',
            'joinedAt' => '2026-01-01',
            'leftAt' => null,
            'active' => true,
            'function' => 'member',
            'accountHolder' => 'Erika Musterfrau',
            'iban' => 'DE89370400440532013000',
            'bankName' => 'Testbank',
            'mandateReference' => null,
            'paymentMethod' => 'sepa_direct_debit',
            'paymentInterval' => 'yearly',
            'paymentDay' => 'first',
            'payerType' => 'self_payer',
            'payerMemberId' => null,
            'nextBookingMonth' => null,
            'nextBookingYear' => null,
        ];
    }

    private function loginAsSuperAdmin(): string
    {
        $createUser = self::getContainer()->get(CreateUserUseCase::class);
        if (!$createUser instanceof CreateUserUseCase) {
            throw new \LogicException('Die Benutzeranlage ist im Testcontainer nicht verfügbar.');
        }
        $createUser->execute(new CreateUserRequest(
            email: 'membership-management-admin@example.test',
            displayName: 'Membership Admin',
            plainPassword: 'Ein-sicheres-Testpasswort-2026',
            roles: [Role::SuperAdmin],
            moduleAccess: [
                new ModuleAccess(CmsModule::Members, ModuleRole::Editor),
                new ModuleAccess(CmsModule::ContributionRates, ModuleRole::Editor),
                new ModuleAccess(CmsModule::MembershipApplications, ModuleRole::Editor),
            ],
        ));
        $this->client->jsonRequest('POST', '/api/auth/v1/login', [
            'email' => 'membership-management-admin@example.test',
            'password' => 'Ein-sicheres-Testpasswort-2026',
        ]);
        self::assertResponseIsSuccessful();

        return $this->string($this->responseData(), 'csrfToken');
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function string(array $data, string $key): string
    {
        self::assertIsString($data[$key]);

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     */
    private function int(array $data, string $key): int
    {
        self::assertIsInt($data[$key]);

        return $data[$key];
    }

    /**
     * @param array<array-key, mixed> $data
     * @return array<array-key, mixed>
     */
    private function arrayData(array $data, string $key): array
    {
        self::assertIsArray($data[$key]);

        return $data[$key];
    }

    /**
     * @param array<string, mixed> $data
     * @return list<mixed>
     */
    private function arrayList(array $data, string $key): array
    {
        self::assertIsArray($data[$key]);
        self::assertTrue(array_is_list($data[$key]));

        return $data[$key];
    }

    /**
     * @return array<string, mixed>
     */
    private function responseData(): array
    {
        $content = $this->client->getResponse()->getContent();
        if (!is_string($content)) {
            throw new \LogicException('Die Testantwort enthält keinen lesbaren Inhalt.');
        }
        $data = json_decode($content, true, 512, JSON_THROW_ON_ERROR);
        if (!is_array($data) || array_is_list($data)) {
            throw new \LogicException('Die Testantwort enthält kein JSON-Objekt.');
        }

        $response = [];
        foreach ($data as $key => $value) {
            if (!is_string($key)) {
                throw new \LogicException('Die Testantwort enthält einen ungültigen Schlüssel.');
            }
            $response[$key] = $value;
        }

        return $response;
    }
}
