<?php

namespace Shield\Mailgun;

use Illuminate\Http\Request;
use Illuminate\Support\Collection;
use Shield\Shield\Contracts\Service;

/**
 * Class Skeleton
 *
 * @package \Shield\Skeleton
 */
class Mailgun implements Service
{
    public function verify(Request $request, Collection $config): bool
    {
        $tolerance = $config->get('tolerance', 60 * 5);
        $timestamp = $request->input('timestamp');

        if (
            ! $request->isMethod('POST') ||
            abs(time() - $timestamp) > $tolerance
        ) {
            return false;
        }

        $signature = hash_hmac(
            'sha256',
            $request->input('timestamp') . $request->input('token'),
            $config->get('token')
        );

        return $signature === $request->input('signature');
    }

    public function headers(): array
    {
        return [];
    }
}
