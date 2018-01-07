<?php
declare(strict_types=1);

namespace Prooph\EventMachine\Persistence\DocumentStore;

use Prooph\EventMachine\Persistence\DocumentStore;

final class InMemoryDocumentStore implements DocumentStore
{
    /**
     * @var array indexed by collection name
     */
    private $collections = [];

    /**
     * @return string[] list of all available collections
     */
    public function listCollections(): array
    {
        return array_keys($this->collections);
    }

    /**
     * @param string $prefix
     * @return string[] of collection names
     */
    public function filterCollectionsByPrefix(string $prefix): array
    {
        return array_filter(array_keys($this->collections), function (string $colName) use ($prefix): bool {
            return mb_strpos($colName, $prefix) === 0;
        });
    }

    /**
     * @param string $collectionName
     * @return bool
     */
    public function hasCollection(string $collectionName): bool
    {
        return array_key_exists($collectionName, $this->collections);
    }

    /**
     * @param string $collectionName
     * @param Index[] ...$indices
     */
    public function addCollection(string $collectionName, Index ...$indices): void
    {
        $this->collections[$collectionName] = [];
    }

    /**
     * @param string $collectionName
     * @throws \Throwable if dropping did not succeed
     */
    public function dropCollection(string $collectionName): void
    {
        if($this->hasCollection($collectionName)) {
            unset($this->collections[$collectionName]);
        }
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $doc
     * @throws \Throwable if adding did not succeed
     */
    public function addDoc(string $collectionName, string $docId, array $doc): void
    {
        $this->assertHasCollection($collectionName);

        if($this->hasDoc($collectionName, $docId)) {
            throw new \RuntimeException("Cannot add doc with id $docId. The doc already exists in collection $collectionName");
        }

        $this->collections[$collectionName][$docId] = $doc;
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if updating did not succeed
     */
    public function updateDoc(string $collectionName, string $docId, array $docOrSubset): void
    {
        $this->assertDocExists($collectionName, $docId);

        $this->collections[$collectionName][$docId] = array_merge(
            $this->collections[$collectionName][$docId],
            $docOrSubset
        );
    }

    /**
     * @param string $collectionName
     * @param DocumentStore\Filter\Filter[] $filters
     * @param array $set
     * @throws \Throwable in case of connection error or other issues
     */
    public function updateMany(string $collectionName, array $filters, array $set): void
    {
        $docs = $this->filterDocs($collectionName, $filters);

        foreach ($docs as $docId => $doc) {
            $this->updateDoc($collectionName, $docId, $set);
        }
    }

    /**
     * Same as updateDoc except that doc is added to collection if it does not exist.
     *
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if insert/update did not succeed
     */
    public function upsertDoc(string $collectionName, string $docId, array $docOrSubset): void
    {
        if($this->hasDoc($collectionName, $docId)) {
            $this->updateDoc($collectionName, $docId, $docOrSubset);
        } else {
            $this->addDoc($collectionName, $docId, $docOrSubset);
        }
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @throws \Throwable if deleting did not succeed
     */
    public function deleteDoc(string $collectionName, string $docId): void
    {
        if($this->hasDoc($collectionName, $docId)) {
            unset($this->collections[$collectionName][$docId]);
        }
    }

    /**
     * @param string $collectionName
     * @param DocumentStore\Filter\Filter[] $filters
     * @throws \Throwable in case of connection error or other issues
     */
    public function deleteMany(string $collectionName, array $filters): void
    {
        $docs = $this->filterDocs($collectionName, $filters);

        foreach ($docs as $docId => $doc) {
            $this->deleteDoc($collectionName, $docId);
        }
    }

    /**
     * @param string $collectionName
     * @param string $docId
     * @return array|null
     */
    public function getDoc(string $collectionName, string $docId): ?array
    {
       return $this->collections[$collectionName][$docId] ?? null;
    }

    /**
     * @param string $collectionName
     * @param DocumentStore\Filter\Filter[] $filters
     * @return \Traversable list of docs
     */
    public function filterDocs(string $collectionName, array $filters): \Traversable
    {
        $this->assertHasCollection($collectionName);

        $filteredDocs = [];

        foreach ($this->collections[$collectionName] as $docId => $doc) {
            $matched = true;
            foreach ($filters as $filter) {
                $matched = $filter->match($doc);
            }

            if($matched) {
                $filteredDocs[$docId] = $doc;
            }
        }

        return new \ArrayIterator($filteredDocs);
    }

    private function hasDoc(string $collectionName, string $docId): bool
    {
        if(!$this->hasCollection($collectionName)) {
            return false;
        }

        return array_key_exists($docId, $this->collections[$collectionName]);
    }

    private function assertHasCollection(string $collectionName): void
    {
        if(!$this->hasCollection($collectionName)) {
            throw new \RuntimeException("Unknown collection $collectionName");
        }
    }

    private function assertDocExists(string $collectionName, string $docId): void
    {
        $this->assertHasCollection($collectionName);

        if(!$this->hasDoc($collectionName, $docId)) {
            throw new \RuntimeException("Doc with id $docId does not exist in collection $collectionName");
        }
    }
}
