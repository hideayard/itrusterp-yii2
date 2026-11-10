<?php

namespace app\components;

use Yii;
use yii\db\Query;

class SequenceService
{
    public static function next(string $code, ?int $companyId = null, array $defaults = [], ?string $date = null): string
    {
        $db = Yii::$app->db;
        $transaction = $db->beginTransaction();

        try {
            $sequence = null;
            $lookups = [['code' => $code, 'company_id' => $companyId]];
            if ($companyId !== null) {
                $lookups[] = ['code' => $code, 'company_id' => null];
            }
            foreach ($lookups as $keys) {
                $sequence = (new Query())->from('sequences')->where($keys)->one();
                if ($sequence) {
                    break;
                }
            }

            if (!$sequence) {
                $db->createCommand()->insert('sequences', [
                    'code' => $code,
                    'company_id' => null,
                    'variant' => '',
                    'name' => $defaults['name'] ?? $code,
                    'prefix' => $defaults['prefix'] ?? '',
                    'suffix' => null,
                    'padding' => (int) ($defaults['padding'] ?? 5),
                    'next_number' => self::initialNumber($defaults),
                    'step' => 1,
                    'reset_frequency' => 'never',
                    'period_key' => null,
                    'created_at' => gmdate('Y-m-d H:i:s'),
                    'updated_at' => gmdate('Y-m-d H:i:s'),
                ])->execute();

                $sequence = (new Query())->from('sequences')
                    ->where(['code' => $code, 'company_id' => null])
                    ->orderBy(['id' => SORT_DESC])
                    ->one();
            }

            $number = (int) $sequence['next_number'];
            $name = self::format($sequence, $number, $date);

            $db->createCommand()->update('sequences', [
                'next_number' => $number + max(1, (int) $sequence['step']),
                'updated_at' => gmdate('Y-m-d H:i:s'),
            ], ['id' => $sequence['id']])->execute();

            $transaction->commit();

            return $name;
        } catch (\Throwable $e) {
            $transaction->rollBack();
            throw $e;
        }
    }

    private static function initialNumber(array $defaults): int
    {
        $spec = $defaults['initial_from'] ?? null;
        if (is_array($spec) && isset($spec['table'], $spec['column'], $spec['pattern'])) {
            $highest = 0;
            foreach ((new Query())->from($spec['table'])->select($spec['column'])->column() as $value) {
                if (preg_match($spec['pattern'], (string) $value, $m)) {
                    $highest = max($highest, (int) $m[1]);
                }
            }
            return $highest + 1;
        }
        return 1;
    }

    private static function format(array $sequence, int $number, ?string $date): string
    {
        $ts = $date ? (strtotime($date) ?: time()) : time();
        $replace = [
            '%(year)' => date('Y', $ts),
            '%(month)' => date('m', $ts),
            '%(day)' => date('d', $ts),
            '%(y)' => date('y', $ts),
        ];
        $prefix = strtr((string) ($sequence['prefix'] ?? ''), $replace);
        $suffix = strtr((string) ($sequence['suffix'] ?? ''), $replace);

        return $prefix . str_pad((string) $number, (int) ($sequence['padding'] ?? 5), '0', STR_PAD_LEFT) . $suffix;
    }
}
