<?php

declare(strict_types=1);

namespace DoctrineMigrations;

use Doctrine\DBAL\Schema\Schema;
use Doctrine\Migrations\AbstractMigration;

final class Version20260827121000 extends AbstractMigration
{
    public function getDescription(): string
    {
        return 'Moves every decryptable legacy IBAN into plaintext storage.';
    }

    public function up(Schema $schema): void
    {
        $rows = $this->connection->fetchAllAssociative(
            'SELECT id, iban_encrypted FROM membership_application WHERE iban IS NULL AND iban_encrypted IS NOT NULL',
        );
        if ($rows === []) {
            return;
        }

        $kernelSecret = $this->kernelSecret();
        if ($kernelSecret === null) {
            throw new \RuntimeException('Bestehende IBAN-Daten können ohne APP_SECRET nicht migriert werden.');
        }

        foreach ($rows as $row) {
            $id = $row['id'] ?? null;
            $encryptedIban = $row['iban_encrypted'] ?? null;
            if (!is_string($id) || !is_string($encryptedIban)) {
                throw new \RuntimeException('Ein bestehender Mitgliedsantrag enthält ungültige IBAN-Daten.');
            }

            try {
                $iban = $this->decrypt($encryptedIban, $kernelSecret);
            } catch (\RuntimeException $exception) {
                throw new \RuntimeException(
                    'Mindestens eine bestehende IBAN konnte mit dem aktuellen APP_SECRET nicht entschlüsselt werden.',
                    previous: $exception,
                );
            }

            $this->connection->executeStatement(
                'UPDATE membership_application SET iban = :iban, iban_encrypted = NULL WHERE id = :id',
                ['iban' => $iban, 'id' => $id],
            );
        }
    }

    public function down(Schema $schema): void
    {
        $this->throwIrreversibleMigrationException(
            'Die in Klartext überführten IBANs werden nicht erneut verschlüsselt.',
        );
    }

    private function kernelSecret(): ?string
    {
        $secret = $_SERVER['APP_SECRET'] ?? $_ENV['APP_SECRET'] ?? null;

        return is_string($secret) && $secret !== '' ? $secret : null;
    }

    private function decrypt(string $cipherText, string $kernelSecret): string
    {
        if (!str_starts_with($cipherText, 'v1:')) {
            throw new \RuntimeException('Das Format der verschlüsselten Mitgliedschaftsdaten ist unbekannt.');
        }
        $payload = base64_decode(substr($cipherText, 3), true);
        if ($payload === false || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Die verschlüsselten Mitgliedschaftsdaten sind beschädigt.');
        }
        $key = sodium_crypto_generichash(
            $kernelSecret."\0membership-applications",
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        );
        $plainText = sodium_crypto_secretbox_open(
            substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES),
            $key,
        );
        if ($plainText === false) {
            throw new \RuntimeException('Die Mitgliedschaftsdaten konnten nicht entschlüsselt werden.');
        }

        return $plainText;
    }
}
