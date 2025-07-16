<?php

namespace servd\AssetStorage\Ssh;

use Craft;

class SshService
{
    private string $keyDirectory;
    private string $defaultKeyName = 'id_rsa';

    /**
     * Constructor for SshService.
     */
    public function __construct(?string $keyDirectory)
    {
        if ($keyDirectory !== null) {
            $this->keyDirectory = $keyDirectory;
        } else {
            $this->keyDirectory = Craft::$app->path->getStoragePath() . '/servd-ssh-keys';
        }
    }

    /**
     * Retrieve the SSH key pair.
     *
     * @return array An associative array containing 'publicKey', 'privateKey', and their paths.
     */
    public function getKeyPair(): array
    {
        $publicKeyPath = $this->keyDirectory . '/' . $this->defaultKeyName . '.pub';
        $privateKeyPath = $this->keyDirectory . '/' . $this->defaultKeyName;

        if (file_exists($publicKeyPath) && file_exists($privateKeyPath)) {
            return [
                'publicKey64' => base64_encode(file_get_contents($publicKeyPath)),
                'privateKey64' => base64_encode(file_get_contents($privateKeyPath)),
                'publicKeyPath' => $publicKeyPath,
                'privateKeyPath' => $privateKeyPath,
                'isNew' => false,
            ];
        }

        return $this->generateKeyPair();
    }

    /**
     * Generate a new SSH key pair and save them to the filesystem.
     *
     * @return array An associative array containing 'publicKey', 'privateKey', and their paths.
     */
    private function generateKeyPair(): array
    {
        $privateKeyResource = openssl_pkey_new([
            'private_key_bits' => 2048,
            'private_key_type' => OPENSSL_KEYTYPE_RSA,
        ]);

        openssl_pkey_export($privateKeyResource, $privateKey);
        $publicKey = $this->sshEncodePublicKey($privateKeyResource);

        $privateKeyPath = $this->keyDirectory . '/' . $this->defaultKeyName;
        $publicKeyPath = $this->keyDirectory . '/' . $this->defaultKeyName . '.pub';

        // Ensure the key directory exists
        if (!is_dir($this->keyDirectory)) {
            mkdir($this->keyDirectory, 0700, true);
        }

        // Save keys to the filesystem
        file_put_contents($privateKeyPath, $privateKey);
        file_put_contents($publicKeyPath, $publicKey);
        chmod($privateKeyPath, 0600);
        chmod($publicKeyPath, 0644);

        return [
            'publicKey64' => base64_encode($publicKey),
            'privateKey64' => base64_encode($privateKey),
            'publicKeyPath' => $publicKeyPath,
            'privateKeyPath' => $privateKeyPath,
            'isNew' => true,
        ];
    }

    /**
     * Sign a string using the private key.
     *
     * @param string $data The string to sign.
     * @return string The base64-encoded signature.
     * @throws \Exception If signing fails or the private key is unavailable.
     */
    public function signString(string $data): string
    {
        $keyPair = $this->getKeyPair();
        $privateKey = openssl_pkey_get_private(file_get_contents($keyPair['privateKeyPath']));

        if (!$privateKey) {
            throw new \Exception('Unable to load private key for signing.');
        }

        $signature = '';
        $success = openssl_sign($data, $signature, $privateKey, OPENSSL_ALGO_SHA256);

        if (!$success) {
            throw new \Exception('Failed to sign the data.');
        }

        return base64_encode($signature);
    }

    private function sshEncodePublicKey($privKey) {
        $keyInfo = openssl_pkey_get_details($privKey);
        $buffer  = pack("N", 7) . "ssh-rsa" .
        $this->sshEncodeBuffer($keyInfo['rsa']['e']) . 
        $this->sshEncodeBuffer($keyInfo['rsa']['n']);
        return "ssh-rsa " . base64_encode($buffer) . " Servd-Plugin-Generated-Key";
    }
    
    private function sshEncodeBuffer($buffer) {
        $len = strlen($buffer);
        if (ord($buffer[0]) & 0x80) {
            $len++;
            $buffer = "\x00" . $buffer;
        }
        return pack("Na*", $len, $buffer);
    }
}
