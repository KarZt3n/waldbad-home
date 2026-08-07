<?php

namespace App\Data\Membership\Application\Security;

use App\Logic\Membership\Application\SensitiveDataCipherInterface;

readonly class SodiumSensitiveDataCipher implements SensitiveDataCipherInterface
{
    private string $key;

    public function __construct(string $kernelSecret)
    {
        $this->key = sodium_crypto_generichash(
            $kernelSecret."\0membership-applications",
            '',
            SODIUM_CRYPTO_SECRETBOX_KEYBYTES,
        );
    }

    public function encrypt(string $plainText): string
    {
        $nonce = random_bytes(SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $cipherText = sodium_crypto_secretbox($plainText, $nonce, $this->key);

        return 'v1:'.base64_encode($nonce.$cipherText);
    }

    public function decrypt(string $cipherText): string
    {
        if (!str_starts_with($cipherText, 'v1:')) {
            throw new \RuntimeException('Das Format der verschlüsselten Mitgliedschaftsdaten ist unbekannt.');
        }
        $payload = base64_decode(substr($cipherText, 3), true);
        if ($payload === false || strlen($payload) <= SODIUM_CRYPTO_SECRETBOX_NONCEBYTES) {
            throw new \RuntimeException('Die verschlüsselten Mitgliedschaftsdaten sind beschädigt.');
        }
        $nonce = substr($payload, 0, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $encrypted = substr($payload, SODIUM_CRYPTO_SECRETBOX_NONCEBYTES);
        $plainText = sodium_crypto_secretbox_open($encrypted, $nonce, $this->key);
        if ($plainText === false) {
            throw new \RuntimeException('Die Mitgliedschaftsdaten konnten nicht entschlüsselt werden.');
        }

        return $plainText;
    }
}
