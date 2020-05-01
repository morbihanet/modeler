<?php

namespace Morbihanet\Modeler;

 use Illuminate\Support\Str;

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
         $db = Core::getDb($record);

         /** @var Db $db */
         $dbPivot = Core::getDb($pivot);

         $pivotName = collect(
             [ucfirst($db->getConcern(get_class($db))), ucfirst($db->getConcern(get_class($dbPivot)))]
         )->sort()->implode('');

         if (fnmatch('*_*_*', $pivotName)) {
             $dashes    = explode('_', $pivotName);
             $parts     = explode('_', $pivotName, count($dashes) - 1);
             $suffix    = array_shift($parts);
             $part      = array_pop($parts);
             $part      = str_replace($suffix, '', $part);

             $pivotName = ucfirst(Str::camel(Str::lower($suffix) . '_' . $part));
         }

         /** @var Db $model */
         $model = Core::getDb($pivotName);

         return $model->setCache($db->isCache());
     }

     /**
      * @param Item $pivot
      * @return array
      */
     protected function getPivotKeys(Item $pivot)
     {
         /** @var Item $parent */
         $record = $this;

         /** @var Db $db */
         $db = Core::getDb($record);

         /** @var Db $db */
         $dbPivot = Core::getDb($pivot);

         return [$db->getConcern(get_class($db)) . '_id', $db->getConcern(get_class($dbPivot)) . '_id'];
     }

     /**
      * @param Item $pivot
      * @param array $attributes
      * @return Item
      */
     public function attach(Item $pivot, array $attributes = [])
     {
         $db = $this->getPivotModel($pivot);
         [$fk1, $fk2] = $this->getPivotKeys($pivot);

         return $db->create(array_merge([$fk1 => $this['id'], $fk2 => $pivot['id']], $attributes));
     }

     /**
      * @param $pivots
      * @param array $attributes
      * @return int
      */
     public function attachMany($pivots, array $attributes = []): int
     {
         $i = 0;

         /** @var Item $pivot */
         foreach ($pivots as $pivot) {
             $this->attach($pivot, $attributes);

             ++$i;
         }

         return $i;
     }

     /**
      * @param Item $pivot
      * @return bool
      */
     public function detach(Item $pivot)
     {
         $db = $this->getPivotModel($pivot);
         [$fk1, $fk2] = $this->getPivotKeys($pivot);

         /** @var Iterator $query */
         $query = $db->where($fk1, $this['id']);

         return 0 < $query->where($fk2, $pivot['id'])->destroy();
     }

     /**
      * @param $pivots
      * @return int
      */
     public function detachMany($pivots): int
     {
         $i = 0;

         /** @var Item $pivot */
         foreach ($pivots as $pivot) {
             $this->detach($pivot);

             ++$i;
         }

         return $i;
     }

     /**
      * @param Item $pivot
      * @return mixed|Item|null
      */
     public function sync(Item $pivot, array $attributes = [])
     {
         $db = $this->getPivotModel($pivot);
         [$fk1, $fk2] = $this->getPivotKeys($pivot);

         foreach ($db->where($fk1, $this->getId())->cursor() as $item) {
             if ($item->value($fk2) === $pivot->getId()) {
                 $item->delete();
             }
         }

         return $db->create(array_merge([$fk1 => $this['id'], $fk2 => $pivot['id']], $attributes));
     }

     /**
      * @param $pivots
      * @param array $attributes
      * @return int
      */
     public function syncMany($pivots, array $attributes = []): int
     {
         $i = 0;

         /** @var Item $pivot */
         foreach ($pivots as $pivot) {
             $this->sync($pivot, $attributes);

             ++$i;
         }

         return $i;
     }

     public function getPivots(string $relation, ?string $fk1 = null, ?string $fk2 = null)
     {
         /** @var Db $db */
         $db = Core::getDb($this);

         return $db->belongsToMany($relation, $fk1, $fk2, $this);
     }
 }

