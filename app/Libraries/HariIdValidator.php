<?php

namespace App\Libraries;

use App\Models\HariModel;

class HariIdValidator
{
    /**
     * @param list<int|string> $ids
     *
     * @return list<int>
     */
    public static function filterExisting(array $ids): array
    {
        $valid = array_map('intval', array_column((new HariModel())->findAll(), 'id'));
        $set   = array_flip($valid);
        $out   = [];

        foreach ($ids as $id) {
            $id = (int) $id;
            if ($id > 0 && isset($set[$id])) {
                $out[$id] = $id;
            }
        }

        return array_values($out);
    }

    /**
     * Keep only day[...] keys whose hari_id exists.
     *
     * @param array<string|int, mixed> $dayPost
     *
     * @return array<int, mixed>
     */
    public static function filterDayPost(array $dayPost): array
    {
        $valid = array_flip(self::filterExisting(array_keys($dayPost)));
        $out   = [];

        foreach ($dayPost as $hariId => $payload) {
            $hid = (int) $hariId;
            if (isset($valid[$hid])) {
                $out[$hid] = $payload;
            }
        }

        return $out;
    }
}
