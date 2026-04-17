<?php

class FirebaseService {
    private string $apiKey;
    private string $authDomain;
    private string $projectId;
    private string $storageBucket;
    private string $messagingSenderId;
    private string $appId;
    private string $serviceAccountEmail;
    private string $serviceAccountPrivateKey;

    public function __construct() {
        $this->apiKey = (string) env('FIREBASE_API_KEY', '');
        $this->authDomain = (string) env('FIREBASE_AUTH_DOMAIN', '');
        $this->projectId = (string) env('FIREBASE_PROJECT_ID', '');
        $this->storageBucket = (string) env('FIREBASE_STORAGE_BUCKET', '');
        $this->messagingSenderId = (string) env('FIREBASE_MESSAGING_SENDER_ID', '');
        $this->appId = (string) env('FIREBASE_APP_ID', '');
        $this->serviceAccountEmail = (string) env('FIREBASE_SERVICE_ACCOUNT_EMAIL', '');
        $this->serviceAccountPrivateKey = str_replace('\\n', "\n", (string) env('FIREBASE_SERVICE_ACCOUNT_PRIVATE_KEY', ''));
    }

    public function isConfigured(): bool {
        return $this->apiKey !== ''
            && $this->projectId !== ''
            && $this->appId !== ''
            && $this->serviceAccountEmail !== ''
            && $this->serviceAccountPrivateKey !== '';
    }

    public function getWebConfig(): array {
        return [
            'apiKey' => $this->apiKey,
            'authDomain' => $this->authDomain,
            'projectId' => $this->projectId,
            'storageBucket' => $this->storageBucket,
            'messagingSenderId' => $this->messagingSenderId,
            'appId' => $this->appId,
        ];
    }

    public function issueChatToken(int $userId, string $role = 'user'): string {
        $uid = 'app_' . $userId;

        return $this->createCustomToken($uid, [
            'app_user_id' => $userId,
            'app_role' => $role,
        ]);
    }

    private function createCustomToken(string $uid, array $claims = []): string {
        $now = time();
        $header = [
            'alg' => 'RS256',
            'typ' => 'JWT',
        ];

        $payload = [
            'iss' => $this->serviceAccountEmail,
            'sub' => $this->serviceAccountEmail,
            'aud' => 'https://identitytoolkit.googleapis.com/google.identity.identitytoolkit.v1.IdentityToolkit',
            'iat' => $now,
            'exp' => $now + 3600,
            'uid' => $uid,
            'claims' => $claims,
        ];

        $segments = [
            $this->base64UrlEncode(json_encode($header, JSON_UNESCAPED_SLASHES)),
            $this->base64UrlEncode(json_encode($payload, JSON_UNESCAPED_SLASHES)),
        ];

        $input = implode('.', $segments);
        $signature = '';
        $ok = openssl_sign($input, $signature, $this->serviceAccountPrivateKey, 'sha256');

        if (!$ok) {
            throw new RuntimeException('Failed to sign Firebase custom token. Check service account private key.');
        }

        $segments[] = $this->base64UrlEncode($signature);

        return implode('.', $segments);
    }

    private function base64UrlEncode(string $input): string {
        return rtrim(strtr(base64_encode($input), '+/', '-_'), '=');
    }
}
