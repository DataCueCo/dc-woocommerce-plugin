<?php


namespace DataCue\WooCommerce\Modules;


class Category extends Base
{
    public static function generateCategoryItem($id, $withId = false)
    {
        $term = get_term($id);
        $res = [
            'name' => $term->name,
            'link' => get_term_link($term, 'product_cat'),
        ];

        if ($withId) {
            $res['category_id'] = $term->term_id;
        }

        return $res;
    }

    /**
     * Category constructor.
     * @param $client
     * @param array $options
     */
    public function __construct($client, array $options = [])
    {
        parent::__construct($client, $options);

        add_action('create_product_cat', [$this, 'onCategoryCreated'], 10, 2);
        add_action('edited_product_cat', [$this, 'onCategoryUpdated'], 10, 2);
        add_action('delete_product_cat', [$this, 'onCategoryDeleted'], 10, 4);
    }

    public function onCategoryCreated($termId)
    {
        $term = static::generateCategoryItem($termId, true);

        $this->addTaskToQueue('categories', 'create', $termId, ['item' => $term]);
    }

    public function onCategoryUpdated($termId)
    {
        $term = static::generateCategoryItem($termId);

        $this->addTaskToQueue('categories', 'update', $termId, ['categoryId' => $termId, 'item' => $term]);
    }

    public function onCategoryDeleted($termId)
    {
        $this->addTaskToQueue('categories', 'delete', $termId, ['categoryId' => $termId]);
    }
}