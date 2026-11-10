<?php

namespace app\controllers\v1;

use app\components\AccountPoster;
use app\components\ResourceController;

/**
 * Posting jurnal untuk dokumen Accounts (invoice/bill/credit note/refund).
 *
 * Sumber memakai `Account` facade (computeAccountMove + post/cancel). Yang diport di sini:
 * `post` (draft → posted, resolusi akun, debit/credit balance) dan `reset-to-draft`.
 * Rekonsiliasi pembayaran & lock date belum diport.
 */
class AccountMoveController extends ResourceController
{
    protected function verbs(): array
    {
        return array_merge(parent::verbs(), [
            'post' => ['POST'],
            'reset-to-draft' => ['POST'],
        ]);
    }

    public function actionPost($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $move = $this->findModel($cfg, $id);

        $result = (new AccountPoster())->post((int) $move->id);

        return [
            'data' => $this->serialize($this->fresh($cfg, $id), $cfg, true),
            'posting' => $result,
            'message' => ($cfg['label'] ?? 'Entry') . ' posted successfully.',
        ];
    }

    public function actionResetToDraft($id)
    {
        $cfg = $this->cfg();
        $this->authorize('update', $cfg);
        $move = $this->findModel($cfg, $id);

        $result = (new AccountPoster())->resetToDraft((int) $move->id);

        return [
            'data' => $this->serialize($this->fresh($cfg, $id), $cfg, true),
            'posting' => $result,
            'message' => ($cfg['label'] ?? 'Entry') . ' reset to draft successfully.',
        ];
    }

    private function fresh(array $cfg, $id)
    {
        return $this->findModel($cfg, $id);
    }
}
