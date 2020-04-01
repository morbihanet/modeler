<?php

namespace Morbihanet\Modeler;


 trait ManyToManyable
 {
     /**
      * @param IteractorItem $pivot
      * @return Db
      */
     protected function getPivotModel(Item $pivot)
     {
         /** @var Item $parent */
         $record = $this;

         /** @var Db $db */
         $db = $record['__db'];

         /** @var Db $db */
         $dbPivot = $pivot['__db'];

         $parts = explode('\\', get_class($dbPivot));
         array_pop($parts);

         $pivotModel = implode('\\', $parts) . '\\' .
             collect([ucfirst($db->getConcern(get_class($db))), ucfirst($db->getConcern(get_class($dbPivot)))])
                 ->sort()->implode('');

         /** @var Db $model */
         $model = new $pivotModel;

         return $model->setCache($db->isCache());
     }

     /**
      * @param Item $pivot
      * @return array
      */
     protected function getPivotKeys(IteractorItem $pivot)
     {
         /** @var Item $parent */
         $record = $this;

         /** @var Db $db */
         $db = $record['__db'];

         /** @var Db $db */
         $dbPivot = $pivot['__db'];

         return [$db->getConcern(get_class($db)) . '_id', $db->getConcern(get_class($dbPivot)) . '_id'];
     }

     /**
      * @param Item $pivot
      * @param array $attributes
      * @return Item
      */
     public function attach(IteractorItem $pivot, array $attributes = [])
     {
         $db = $this->getPivotModel($pivot);
         [$fk1, $fk2] = $this->getPivotKeys($pivot);

         return $db->create(array_merge([$fk1 => $this['id'], $fk2 => $pivot['id']], $attributes));
     }

     /**
      * @param IteractorItem $pivot
      * @return bool
      */
     public function detach(IteractorItem $pivot)
     {
         $db = $this->getPivotModel($pivot);
         [$fk1, $fk2] = $this->getPivotKeys($pivot);

         return 0 < $db->where($fk1, $this['id'])->where($fk2, $pivot['id'])->destroy();
     }

     /**
      * @param IteractorItem $pivot
      * @return \Mambo\Item|IteractorItem|null
      */
     public function sync(IteractorItem $pivot)
     {
         $db = $this->getPivotModel($pivot);
         [$fk1, $fk2] = $this->getPivotKeys($pivot);

         return $db->firstOrCreate([$fk1 => $this['id'], $fk2 => $pivot['id']]);

     }
 }

