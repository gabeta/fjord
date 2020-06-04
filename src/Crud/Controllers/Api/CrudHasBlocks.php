<?php

namespace Fjord\Crud\Controllers\Api;

use Fjord\Crud\Models\FormBlock;
use Fjord\Crud\Fields\Blocks\Blocks;
use Fjord\Crud\Requests\CrudReadRequest;
use Fjord\Crud\Requests\CrudUpdateRequest;
use Fjord\Crud\Controllers\Api\Blocks\ManagesBlocksMedia;
use Fjord\Crud\Controllers\Api\Blocks\ManagesBlocksRelations;

trait CrudHasBlocks
{
    use ManagesBlocksMedia,
        ManagesBlocksRelations;

    /**
     * Update form_block.
     *
     * @param CrudUpdateRequest $request
     * @param string|integer $id
     * @param string $form_name
     * @param string $field_id
     * @param string $block_id
     * @return CrudJs
     */
    public function loadBlock(CrudReadRequest $request, $identifier, $form_name, $field_id, $block_id)
    {
        $this->formExists($form_name) ?: abort(404);
        $field = $this->getForm($form_name)->findField($field_id) ?? abort(404);
        $field instanceof Blocks ?: abort(404);

        $model = $this->findOrFail($identifier);

        return crud(
            $field->getRelationQuery($model)->findOrFail($block_id)
        );
    }

    /**
     * Update form_block.
     *
     * @param CrudUpdateRequest $request
     * @param string|integer $id
     * @param string $form_name
     * @param string $field_id
     * @return CrudJs
     */
    public function loadBlocks(CrudReadRequest $request, $identifier, $form_name, $field_id)
    {
        $this->formExists($form_name) ?: abort(404);
        $field = $this->getForm($form_name)->findField($field_id) ?? abort(404);
        $field instanceof Blocks ?: abort(404);

        $model = $this->findOrFail($identifier);

        return crud(
            $field->getResults($model)
        );
    }

    /**
     * Update form_block.
     *
     * @param CrudUpdateRequest $request
     * @param string|integer $id
     * @param string $form_name
     * @param string $field_id
     * @return CrudJs
     */
    public function storeBlock(CrudUpdateRequest $request, $identifier, $form_name, $field_id)
    {
        $this->formExists($form_name) ?: abort(404);
        $field = $this->getForm($form_name)->findField($field_id) ?? abort(404);
        $field instanceof Blocks ?: abort(404);

        $field->hasRepeatable($request->type) ?: abort(404);

        $model = $this->findOrFail($identifier);

        $order_column = FormBlock::where([
            'type' => $request->type,
            'model_type' => $this->model,
            'model_id' => $model->id,
            'field_id' => $field->id
        ])->count();

        $block = new FormBlock();
        $block->type = $request->type;
        $block->model_type = $this->model;
        $block->model_id = $model->id;
        $block->field_id = $field->id;
        $block->order_column = $order_column;
        $block->save();

        return crud($block);
    }

    /**
     * Update form_block.
     *
     * @param CrudUpdateRequest $request
     * @param string|integer $id
     * @param string $form_name
     * @param string $field_id
     * @param integer $block_id
     * @return FormBlock
     */
    public function updateBlock(CrudUpdateRequest $request, $identifier, $form_name, $field_id, $block_id)
    {
        $this->formExists($form_name) ?: abort(404);
        $field = $this->getForm($form_name)->findField($field_id) ?? abort(404);
        $field instanceof Blocks ?: abort(404);

        $model = $this->findOrFail($identifier);

        $block = $model->{$field_id}()->findOrFail($block_id);

        // Validate request.
        $this->validate($request, $field->getRepeatable($block->type));

        $block->update($request->all());

        return $block;
    }

    /**
     * Update form_block.
     *
     * @param CrudUpdateRequest $request
     * @param string|integer $id
     * @param string $form_name
     * @param string $field_id
     * @param integer $block_id
     * @return integer
     */
    public function destroyBlock(CrudUpdateRequest $request, $identifier, $form_name, $field_id, $block_id)
    {
        $this->formExists($form_name) ?: abort(404);
        $field = $this->getForm($form_name)->findField($field_id) ?? abort(404);
        $field instanceof Blocks ?: abort(404);

        $model = $this->findOrFail($identifier);

        $block = $model->{$field_id}()->findOrFail($block_id);

        return $block->delete();
    }
}
