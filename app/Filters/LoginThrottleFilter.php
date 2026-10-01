<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Simple file-cache login throttle (shared-hosting friendly, no Redis).
 * Limits per IP+email (tight) and per IP overall (wider spray protection).
 */
class LoginThrottleFilter implements FilterInterface
{
    private const MAX_ATTEMPTS = 5;
    private const MAX_ATTEMPTS_IP = 30;
    private const WINDOW_SECONDS = 900; // 15 minutes

    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $email = strtolower(trim((string) $request->getPost('email')));
        $ip    = $request->getIPAddress() ?: 'unknown';

        if ($this->isLocked($this->pairKey($ip, $email), self::MAX_ATTEMPTS)
            || $this->isLocked($this->ipKey($ip), self::MAX_ATTEMPTS_IP)
        ) {
            return redirect()->back()->with(
                'error',
                'Terlalu banyak percobaan login. Coba lagi dalam beberapa menit.',
            );
        }

        return null;
    }

    public function after(RequestInterface $request, ResponseInterface $response, $arguments = null)
    {
        return null;
    }

    public static function recordFailure(string $ip, string $email): void
    {
        $email = strtolower(trim($email));
        $ip    = $ip ?: 'unknown';

        self::bump(self::pairKey($ip, $email), self::MAX_ATTEMPTS);
        self::bump(self::ipKey($ip), self::MAX_ATTEMPTS_IP);
    }

    public static function clear(string $ip, string $email): void
    {
        $email = strtolower(trim($email));
        $ip    = $ip ?: 'unknown';
        $cache = cache();
        $cache->delete(self::pairKey($ip, $email));
        // Do not clear IP bucket on success — still protects spray against other emails.
    }

    private function isLocked(string $key, int $maxAttempts): bool
    {
        $data = cache()->get($key);
        if (! is_array($data)) {
            return false;
        }

        $attempts = (int) ($data['attempts'] ?? 0);
        $until    = (int) ($data['locked_until'] ?? 0);

        return $until > time() || $attempts >= $maxAttempts;
    }

    private static function bump(string $key, int $maxAttempts): void
    {
        $cache = cache();
        $data  = $cache->get($key);
        if (! is_array($data)) {
            $data = ['attempts' => 0, 'locked_until' => 0];
        }

        $data['attempts'] = (int) $data['attempts'] + 1;
        if ($data['attempts'] >= $maxAttempts) {
            $data['locked_until'] = time() + self::WINDOW_SECONDS;
        }

        $cache->save($key, $data, self::WINDOW_SECONDS);
    }

    private static function pairKey(string $ip, string $email): string
    {
        return 'login_throttle_' . md5($ip . '|' . $email);
    }

    private static function ipKey(string $ip): string
    {
        return 'login_throttle_ip_' . md5($ip);
    }
}
