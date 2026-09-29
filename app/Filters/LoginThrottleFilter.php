<?php

namespace App\Filters;

use CodeIgniter\Filters\FilterInterface;
use CodeIgniter\HTTP\RequestInterface;
use CodeIgniter\HTTP\ResponseInterface;

/**
 * Simple file-cache login throttle (shared-hosting friendly, no Redis).
 */
class LoginThrottleFilter implements FilterInterface
{
    private const MAX_ATTEMPTS = 5;
    private const WINDOW_SECONDS = 900; // 15 minutes

    public function before(RequestInterface $request, $arguments = null)
    {
        if (strtolower($request->getMethod()) !== 'post') {
            return null;
        }

        $email = strtolower(trim((string) $request->getPost('email')));
        $ip    = $request->getIPAddress() ?: 'unknown';
        $key   = 'login_throttle_' . md5($ip . '|' . $email);

        $cache = cache();
        $data  = $cache->get($key);
        if (! is_array($data)) {
            return null;
        }

        $attempts = (int) ($data['attempts'] ?? 0);
        $until    = (int) ($data['locked_until'] ?? 0);

        if ($until > time() || $attempts >= self::MAX_ATTEMPTS) {
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
        $key   = 'login_throttle_' . md5(($ip ?: 'unknown') . '|' . $email);
        $cache = cache();
        $data  = $cache->get($key);
        if (! is_array($data)) {
            $data = ['attempts' => 0, 'locked_until' => 0];
        }

        $data['attempts'] = (int) $data['attempts'] + 1;
        if ($data['attempts'] >= self::MAX_ATTEMPTS) {
            $data['locked_until'] = time() + self::WINDOW_SECONDS;
        }

        $cache->save($key, $data, self::WINDOW_SECONDS);
    }

    public static function clear(string $ip, string $email): void
    {
        $email = strtolower(trim($email));
        $key   = 'login_throttle_' . md5(($ip ?: 'unknown') . '|' . $email);
        cache()->delete($key);
    }
}
