<?php

declare(strict_types=1);

namespace Prooph\EventMachine\Persistence;

use Prooph\EventMachine\Persistence\DocumentStore\Index;

interface DocumentStore
{
    /**
     * @return string[] list of all available collections
     */
    public function listCollections(): array;

    /**
     * @param string $prefix
     * @return string[] of collection names
     */
    public function filterCollectionsByPrefix(string $prefix): array;


    /**
     * @param string $collectionName
     * @param Index[] ...$indices
     */
    public function addCollection(string $collectionName, Index ...$indices): void;

    /**
     * @param string $collectionName
     * @throws \Throwable if dropping did not succeed
     */
    public function dropCollection(string $collectionName): void;

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $doc
     * @throws \Throwable if adding did not succeed
     */
    public function addDoc(string $collectionName, string $docId, array $doc): void;

    /**
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if updating did not succeed
     */
    public function updateDoc(string $collectionName, string $docId, array $docOrSubset): void;

    /**
     * @param string $collectionName
     * @param array $filter
     * @param array $set
     * @throws \Throwable in case of connection error or other issues
     */
    public function updateMany(string $collectionName, array $filter, array $set): void;

    /**
     * Same as updateDoc except that doc is added to collection if it does not exist.
     *
     * @param string $collectionName
     * @param string $docId
     * @param array $docOrSubset
     * @throws \Throwable if insert/update did not succeed
     */
    public function upsertDoc(string $collectionName, string $docId, array $docOrSubset): void;

    /**
     * @param string $collectionName
     * @param string $docId
     * @throws \Throwable if deleting did not succeed
     */
    public function deleteDoc(string $collectionName, string $docId): void;

    /**
     * @param string $collectionName
     * @param array $filter
     * @throws \Throwable in case of connection error or other issues
     */
    public function deleteMany(string $collectionName, array $filter): void;

    /**
     * @param string $collectionName
     * @param string $docId
     * @return array|null
     */
    public function getDoc(string $collectionName, string $docId): ?array;

    /**
     * @param string $collectionName
     * @param array $filter
     * @return array[] of documents
     */
    public function filterDocs(string $collectionName, array $filter): array;
}
