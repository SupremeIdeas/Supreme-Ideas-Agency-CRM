<?php

namespace App\Services\ProviderRadar;

/**
 * Reduces a real API response to its structural shape — key names and value
 * types only, recursively, on a stable sorted order — never the actual
 * values. Used by response-schema watchers so "a new field appeared" is
 * detectable without ever persisting real provider data (prices, plan
 * details, PII) into the radar's own storage.
 */
class ResponseShape
{
    public static function of(mixed $data): mixed
    {
        if (is_array($data)) {
            if (array_is_list($data)) {
                // A list's shape is just the shape of its first element —
                // every row in a catalogue array has the same shape.
                return $data === [] ? [] : [self::of($data[0])];
            }

            $shape = [];
            foreach ($data as $key => $value) {
                $shape[$key] = self::of($value);
            }
            ksort($shape);

            return $shape;
        }

        return get_debug_type($data);
    }
}
