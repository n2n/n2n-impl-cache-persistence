<?php
namespace n2n\impl\cache\persistence;

use n2n\util\cache\CacheStore;
use n2n\util\cache\CacheItem;
use n2n\persistence\Pdo;
use n2n\util\ex\NotYetImplementedException;
use n2n\persistence\PdoException;

class PdoCacheStore implements CacheStore {
	private string $itemTableName = 'cached_item';
	private string $characteristicTableName = 'cached_characteristic';

	private ?PdoCacheEngine $pdoCacheEngine = null;

	function __construct(private Pdo $pdo) {
	}

	function setCacheTableName(string $cacheTableName): static {
		$this->cacheTableName = $cacheTableName;
		$this->pdoCacheEngine = null;
		return $this;
	}

	function getCachedTableName(): string {
		return $this->itemTableName;
	}

	public function setCharacteristicTableName(string $characteristicTableName): static {
		$this->characteristicTableName = $characteristicTableName;
		$this->pdoCacheEngine = null;
		return $this;
	}

	public function getCharacteristicTableName(): string {
		return $this->characteristicTableName;
	}

	private function tableCheckedCall(\Closure $closure): mixed {
		try {
			return $closure();
		} catch (PdoException $e) {
			if (!$this->checkTables()) {
				throw $e;
			}

			return $closure();
		}
	}

	private function checkTables(): bool {
		$tablesCreated = false;

		if (!$this->pdoCacheEngine->doesItemTableExist()) {
			$this->pdoCacheEngine->createItemTable();
			$tablesCreated = true;
		}

		if (!$this->pdoCacheEngine->doesCharacteristicTableExist()) {
			$this->pdoCacheEngine->createItemTable();
			$tablesCreated = true;
		}

		return $tablesCreated;
	}
	public function store(string $name, array $characteristics, mixed $data, \DateTime $lastMod = null): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics, &$data) {
			$this->pdoCacheEngine->write($name, $characteristics, $data);
		});
	}

	public function get(string $name, array $characteristics): ?CacheItem {
		$result = $this->tableCheckedCall(function () use (&$name, &$characteristics, &$data) {
			return $this->pdoCacheEngine->read($name, $characteristics);
		});

		return self::parseCacheItem($result);
	}

	private static function parseCacheItem(array $result): CacheItem {
		return new CacheItem($result[PdoCacheEngine::NAME_COLUMN], $result[PdoCacheEngine::CHARACTERISTICS_COLUMN],
				$result[PdoCacheEngine::DATA_COLUMN]);
	}

	public function remove(string $name, array $characteristics): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->delete($name, $characteristics);
		});
	}

	public function findAll(string $name, array $characteristicNeedles = null): array {
		$results = $this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->delete($name, $characteristics);
		});

		return array_map(fn ($result) => self::parseCacheItem($result), $results);
	}

	public function removeAll(?string $name, array $characteristicNeedles = null): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->deleteBy($name, $characteristics);
		});
	}

	public function clear(): void {
		$this->pdoCacheEngine->clear();
	}
}

