<?php

namespace Morbihanet\Modeler;

use Typesense\Client;
use Typesense\Collection;
use Illuminate\Support\Str;
use Typesense\Exceptions\ObjectNotFound;

class Typesense
{
    protected ?Client $client = null;

    public function __construct(Client $client)
    {
        $this->client = $client;
    }

    public function getClient(): Client
    {
        return $this->client;
    }

    protected function getOrCreateCollectionFromModel($model, ?array $schema = null): Collection
    {
        $collection = is_string($model) ? $model : Str::plural(item_table($model));
        $index = $this->client->getCollections()->{$collection};

        try {
            $index->retrieve();

            return $index;
        } catch (ObjectNotFound $exception) {
            Core::log('ObjectNotFound', $exception);
            $this->client->getCollections()->create($schema ?? $model->typesenseSchema());

            return $this->client->getCollections()->{$collection};
        }
    }

    public function getCollectionIndex($model, ?array $schema = null): Collection
    {
        return $this->getOrCreateCollectionFromModel($model, $schema);
    }

    public function upsertDocument(Collection $collectionIndex, $array): void
    {
        $document = $collectionIndex->getDocuments()[$array['id']];

        try {
            $document->retrieve();
            $document->delete();
            $collectionIndex->getDocuments()->create($array);
        } catch (ObjectNotFound $exception) {
            Core::log('ObjectNotFound', $exception);
            $collectionIndex->getDocuments()->create($array);
        }
    }

    public function deleteDocument(Collection $collectionIndex, $modelId): void
    {
        $document = $collectionIndex->getDocuments()[(string) $modelId];
        $document->delete();
    }
}
