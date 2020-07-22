<?php
namespace Morbihanet\Modeler;

/**
 * @mixin  \Morbihanet\Modeler\Model
 */

class Able
{
    /**
     * @param Item|Model $item
     */
    public static function add($item, string $type, array $attributes = []): bool
    {
        if ($item instanceof Model) {
            $item = $item->retrieveItem();
        }

        if ($item->exists()) {
            $class_model = item_table($item);
            $id_model = $item->getId();
            $db = datum('able');

            if (!$row = $db->where('type', $type)->where('class_model', $class_model)->where('id_model', $id_model)->first()) {
                $row = $db->create(compact('type', 'class_model', 'id_model'));
            }

            $row->update($attributes);

            return true;
        }

        return false;
    }

    /**
     * @param Item|Model $item
     */
    public static function remove($item, string $type): bool
    {
        if ($item instanceof Model) {
            $item = $item->retrieveItem();
        }
        if ($item->exists()) {
            $class_model = item_table($item);
            $id_model = $item->getId();

            if ($row = datum('able')
                ->where('type', $type)
                ->where('class_model', $class_model)
                ->where('id_model', $id_model)
                ->first()
            ) {
                return $row->delete();
            }
        }

        return false;
    }

    public static function fetch(string $type)
    {
        return Core::iterator(function () use ($type) {
            foreach (datum('able')->where('type', $type)->cursor() as $item) {
                yield $item->getDatum()->find($item->id_model);
            }
        });
    }

    public static function __callStatic($name, $arguments)
    {
        return datum('able')::{$name}(...$arguments);
    }
}
