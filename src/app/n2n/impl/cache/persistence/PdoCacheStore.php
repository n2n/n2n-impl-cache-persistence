<?php
namespace n2n\impl\cache\persistence;

use n2n\util\cache\CacheStore;
use n2n\util\cache\CacheItem;
use n2n\persistence\Pdo;
use n2n\persistence\PdoException;

class PdoCacheStore implements CacheStore {
	private string $dataTableName = 'cached_data';
	private string $characteristicTableName = 'cached_characteristic';
	private PdoCacheDataSize $pdoCacheDataSize = PdoCacheDataSize::TEXT;
	private bool $tableAutoCreated = true;
	private ?PdoCacheEngine $pdoCacheEngine = null;

	function __construct(private Pdo $pdo) {
	}

	function setDataTableName(string $dataTableName): static {
		$this->dataTableName = $dataTableName;
		$this->pdoCacheEngine = null;
		return $this;
	}

	function getDataTableName(): string {
		return $this->dataTableName;
	}

	public function setCharacteristicTableName(string $characteristicTableName): static {
		$this->characteristicTableName = $characteristicTableName;
		$this->pdoCacheEngine = null;
		return $this;
	}

	public function getCharacteristicTableName(): string {
		return $this->characteristicTableName;
	}

	public function setPdoCacheDataSize(PdoCacheDataSize $pdoCacheDataSize): PdoCacheStore {
		$this->pdoCacheDataSize = $pdoCacheDataSize;
		return $this;
	}

	public function getPdoCacheDataSize(): PdoCacheDataSize {
		return $this->pdoCacheDataSize;
	}

	public function isTableAutoCreated(): bool {
		return $this->tableAutoCreated;
	}

	public function setTableAutoCreated(bool $tableAutoCreated): PdoCacheStore {
		$this->tableAutoCreated = $tableAutoCreated;
		return $this;
	}

	private function tableCheckedCall(\Closure $closure): mixed {
		if ($this->pdoCacheEngine === null) {
			$this->pdoCacheEngine = new PdoCacheEngine($this->pdo, $this->dataTableName, $this->characteristicTableName,
					$this->pdoCacheDataSize);
		}

		try {
			return $closure();
		} catch (PdoException $e) {
			if (!$this->tableAutoCreated || !$this->checkTables()) {
				throw $e;
			}

			return $closure();
		}
	}

	private function checkTables(): bool {
		$tablesCreated = false;

		if (!$this->pdoCacheEngine->doesDataTableExist()) {
			$this->pdoCacheEngine->createDataTable();
			$tablesCreated = true;
		}

		if (!$this->pdoCacheEngine->doesCharacteristicTableExist()) {
			$this->pdoCacheEngine->createCharacteristicTable();
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
		$result = $this->tableCheckedCall(function () use (&$name, &$characteristics) {
			return $this->pdoCacheEngine->read($name, $characteristics);
		});

		return self::parseCacheItem($result);
	}

	private static function parseCacheItem(?array $result): ?CacheItem {
		if ($result === null) {
			return null;
		}

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
			return $this->pdoCacheEngine->findBy($name, $characteristics);
		});

		return array_map(fn ($result) => self::parseCacheItem($result), $results);
	}

	public function removeAll(?string $name, array $characteristicNeedles = null): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->deleteBy($name, $characteristics);
		});
	}

	public function clear(): void {
		$this->tableCheckedCall(function () use (&$name, &$characteristics) {
			$this->pdoCacheEngine->clear();
		});
	}
}

