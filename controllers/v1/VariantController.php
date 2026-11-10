<?php

namespace app\controllers\v1;

use app\components\ResourceController;

class VariantController extends ResourceController
{
    public function actionCreate()
    {
        $cfg = $this->cfg();
        [$parent] = $this->resolveParentIfNested($cfg);
        $this->authorize('create', $cfg);
        $this->resolveExpand($cfg);

        $product = $parent;
        $product->generateVariants();
        $product->refresh();

        $this->expand = array_values(array_unique(array_merge($this->expand, ['attributes'])));

        return [
            'data' => $this->serializeCollection($product->variants, $cfg),
            'message' => 'Product variants synced successfully.',
        ];
    }
}
