<?php

namespace PreisLandoBestsellerAutomation\Services;

use Plenty\Modules\Order\Contracts\OrderItemRepositoryContract;
use Plenty\Modules\Order\Contracts\OrderRepositoryContract;
use Plenty\Modules\Tag\Contracts\TagAvailabilityRepositoryContract;
use Plenty\Modules\Tag\Contracts\TagRelationshipRepositoryContract;
use Plenty\Modules\Tag\Contracts\TagRepositoryContract;
use Plenty\Plugin\ConfigRepository;
use Plenty\Plugin\Log\Loggable;

class BestsellerService
{
    use Loggable;

    /** @var ConfigRepository */
    private $config;

    /** @var OrderRepositoryContract */
    private $orderRepository;

    /** @var OrderItemRepositoryContract */
    private $orderItemRepository;

    /** @var TagRepositoryContract */
    private $tagRepository;

    /** @var TagRelationshipRepositoryContract */
    private $tagRelationshipRepository;

    /** @var TagAvailabilityRepositoryContract */
    private $tagAvailabilityRepository;

    public function __construct(
        ConfigRepository $config,
        OrderRepositoryContract $orderRepository,
        OrderItemRepositoryContract $orderItemRepository,
        TagRepositoryContract $tagRepository,
        TagRelationshipRepositoryContract $tagRelationshipRepository,
        TagAvailabilityRepositoryContract $tagAvailabilityRepository
    ) {
        $this->config = $config;
        $this->orderRepository = $orderRepository;
        $this->orderItemRepository = $orderItemRepository;
        $this->tagRepository = $tagRepository;
        $this->tagRelationshipRepository = $tagRelationshipRepository;
        $this->tagAvailabilityRepository = $tagAvailabilityRepository;
    }

    /**
     * Berechnet die Bestseller und pflegt den Tag.
     *
     * @param bool $manual True, wenn ueber den geschuetzten REST-Endpunkt gestartet.
     * @return array
     */
    public function run($manual = false)
    {
        $enabled = $this->boolConfig('bestseller.enabled', false);
        $dryRun = $this->boolConfig('bestseller.dryRun', true);

        if (!$enabled && !$manual) {
            return [
                'ok' => true,
                'skipped' => true,
                'reason' => 'Automatik ist deaktiviert.'
            ];
        }

        $days = $this->intConfig('bestseller.days', 30, 1, 365);
        $topCount = $this->intConfig('bestseller.topCount', 30, 1, 200);
        $statusFrom = $this->floatConfig('bestseller.statusFrom', 5.0);
        $statusTo = $this->floatConfig('bestseller.statusTo', 7.0);
        $tagName = trim((string)$this->config->get('bestseller.tagName', 'Bestseller'));
        $excludedStatuses = $this->csvFloats((string)$this->config->get('bestseller.excludedStatuses', ''));
        $referrerIds = $this->csvFloats((string)$this->config->get('bestseller.referrerIds', ''));

        if ($tagName === '') {
            $tagName = 'Bestseller';
        }
        if ($statusFrom > $statusTo) {
            $tmp = $statusFrom;
            $statusFrom = $statusTo;
            $statusTo = $tmp;
        }

        $from = (new \DateTimeImmutable('now'))->modify('-' . $days . ' days');
        $to = new \DateTimeImmutable('now');
        $fromIso = $from->format(DATE_ATOM);
        $toIso = $to->format(DATE_ATOM);

        $this->orderRepository->clearFilters();

        // Die Filter entsprechen der PlentyONE-Order-Suche:
        // Sales order = orderTypeId 1, Datums- und Statusbereich inklusive Grenzen.
        $filters = [
            'orderTypeId' => 1,
            'createdAt' => 'between:' . $fromIso . ',' . $toIso,
            'statusId' => 'between:' . $statusFrom . ',' . $statusTo
        ];
        $this->orderRepository->setFilters($filters);

        $sales = [];
        $ordersProcessed = 0;
        $positionsProcessed = 0;
        $page = 1;
        $itemsPerPage = 100;

        do {
            $result = $this->orderRepository->searchOrders($page, $itemsPerPage, [], false);
            $orders = $result->getResult();

            foreach ($orders as $order) {
                $orderStatus = (float)$this->getValue($order, 'statusId', 0);
                if ($this->floatInList($orderStatus, $excludedStatuses)) {
                    continue;
                }

                $orderReferrer = (float)$this->getValue($order, 'referrerId', 0);
                if (!empty($referrerIds) && !$this->floatInList($orderReferrer, $referrerIds)) {
                    continue;
                }

                $orderId = (int)$this->getValue($order, 'id', 0);
                if ($orderId <= 0) {
                    continue;
                }

                $ordersProcessed++;
                $orderItems = $this->getValue($order, 'orderItems', null);

                // Falls die Order-Suche die Positionen in der jeweiligen Systemversion
                // nicht eager-laedt, holen wir sie pro Auftrag nach.
                if (!$this->isIterableWithContent($orderItems)) {
                    $orderItems = $this->loadOrderItems($orderId);
                }

                foreach ($orderItems as $item) {
                    $typeId = (int)$this->getValue($item, 'typeId', 0);

                    // Zaehlen: normale Variante, Bundle-Hauptposition, Set-Hauptposition.
                    // Komponenten (3/14) werden bewusst NICHT zusaetzlich gezaehlt.
                    if (!in_array($typeId, [1, 2, 13], true)) {
                        continue;
                    }

                    $variationId = (int)$this->getValue($item, 'itemVariationId', 0);
                    $quantity = (float)$this->getValue($item, 'quantity', 0);
                    if ($variationId <= 0 || $quantity <= 0) {
                        continue;
                    }

                    if (!isset($sales[$variationId])) {
                        $sales[$variationId] = 0.0;
                    }
                    $sales[$variationId] += $quantity;
                    $positionsProcessed++;
                }
            }

            $isLastPage = $result->isLastPage();
            $page++;
        } while (!$isLastPage && $page <= 1000);

        $this->orderRepository->clearFilters();

        arsort($sales, SORT_NUMERIC);
        $topSales = array_slice($sales, 0, $topCount, true);
        $topVariationIds = array_map('intval', array_keys($topSales));

        $summary = [
            'ok' => true,
            'manual' => (bool)$manual,
            'dryRun' => (bool)$dryRun,
            'period' => [
                'days' => $days,
                'from' => $fromIso,
                'to' => $toIso
            ],
            'statusFrom' => $statusFrom,
            'statusTo' => $statusTo,
            'excludedStatuses' => $excludedStatuses,
            'referrerIds' => $referrerIds,
            'ordersProcessed' => $ordersProcessed,
            'positionsProcessed' => $positionsProcessed,
            'variationsWithSales' => count($sales),
            'topCount' => count($topVariationIds),
            'tagName' => $tagName,
            'top' => []
        ];

        foreach ($topSales as $variationId => $quantity) {
            $summary['top'][] = [
                'variationId' => (int)$variationId,
                'quantity' => (float)$quantity
            ];
        }

        if (empty($topVariationIds)) {
            $summary['ok'] = false;
            $summary['message'] = 'Keine passenden Verkaufspositionen gefunden. Tags wurden aus Sicherheitsgruenden nicht geaendert.';
            $this->logSummary($summary);
            return $summary;
        }

        if ($dryRun) {
            $summary['message'] = 'Testmodus aktiv: Auswertung fertig, Tags wurden nicht geaendert.';
            $this->logSummary($summary);
            return $summary;
        }

        $tag = $this->getOrCreateTag($tagName);
        $tagId = (int)$this->getValue($tag, 'id', 0);
        if ($tagId <= 0) {
            throw new \RuntimeException('Bestseller-Tag konnte nicht ermittelt oder erstellt werden.');
        }

        $this->ensureVariationAvailability($tagId);

        $existingRelations = $this->tagRelationshipRepository->findByTagId($tagId);
        $existingVariationIds = [];

        foreach ($existingRelations as $relation) {
            $tagType = (string)$this->getValue($relation, 'tagType', '');
            $relationshipValue = (int)$this->getValue($relation, 'relationshipValue', 0);
            if (($tagType === '' || $tagType === 'variation') && $relationshipValue > 0) {
                $existingVariationIds[$relationshipValue] = true;
            }
        }

        $targetMap = array_fill_keys($topVariationIds, true);
        $removed = [];
        $added = [];

        foreach (array_keys($existingVariationIds) as $variationId) {
            if (!isset($targetMap[$variationId])) {
                $this->tagRelationshipRepository->deleteRelation((int)$variationId, $tagId);
                $removed[] = (int)$variationId;
            }
        }

        foreach ($topVariationIds as $variationId) {
            if (!isset($existingVariationIds[$variationId])) {
                $this->tagRelationshipRepository->create([
                    'tagId' => $tagId,
                    'tagType' => 'variation',
                    'relationshipValue' => (int)$variationId
                ]);
                $added[] = (int)$variationId;
            }
        }

        $summary['tagId'] = $tagId;
        $summary['added'] = $added;
        $summary['removed'] = $removed;
        $summary['message'] = 'Bestseller-Tag wurde aktualisiert.';
        $this->logSummary($summary);

        return $summary;
    }

    private function loadOrderItems($orderId)
    {
        $all = [];
        $page = 1;
        do {
            $result = $this->orderItemRepository->search($orderId, $page, 100);
            foreach ($result->getResult() as $item) {
                $all[] = $item;
            }
            $isLastPage = $result->isLastPage();
            $page++;
        } while (!$isLastPage && $page <= 100);

        return $all;
    }

    private function getOrCreateTag($tagName)
    {
        try {
            $tag = $this->tagRepository->getTagByName($tagName);
            if ($tag && (int)$this->getValue($tag, 'id', 0) > 0) {
                return $tag;
            }
        } catch (\Throwable $e) {
            // Nicht vorhanden -> neu anlegen.
        }

        return $this->tagRepository->create($tagName);
    }

    private function ensureVariationAvailability($tagId)
    {
        $tags = $this->tagRepository->getTagsByAvailability('variation');
        foreach ($tags as $tag) {
            if ((int)$this->getValue($tag, 'id', 0) === (int)$tagId) {
                return;
            }
        }

        $this->tagAvailabilityRepository->create([
            'tagId' => (int)$tagId,
            'tagType' => 'variation'
        ]);
    }

    private function getValue($source, $key, $default = null)
    {
        if (is_array($source)) {
            return array_key_exists($key, $source) ? $source[$key] : $default;
        }
        if (is_object($source)) {
            if (isset($source->{$key}) || property_exists($source, $key)) {
                return $source->{$key};
            }
            if (method_exists($source, 'toArray')) {
                $data = $source->toArray();
                return array_key_exists($key, $data) ? $data[$key] : $default;
            }
        }
        return $default;
    }

    private function isIterableWithContent($value)
    {
        if (is_array($value)) {
            return count($value) > 0;
        }
        if ($value instanceof \Traversable) {
            foreach ($value as $unused) {
                return true;
            }
        }
        return false;
    }

    private function boolConfig($key, $default)
    {
        $value = $this->config->get($key, $default ? '1' : '0');
        return in_array((string)$value, ['1', 'true', 'yes', 'on'], true);
    }

    private function intConfig($key, $default, $min, $max)
    {
        $value = (int)$this->config->get($key, $default);
        return max($min, min($max, $value));
    }

    private function floatConfig($key, $default)
    {
        $value = str_replace(',', '.', (string)$this->config->get($key, (string)$default));
        return (float)$value;
    }

    private function csvFloats($csv)
    {
        $result = [];
        foreach (preg_split('/\s*,\s*/', trim($csv), -1, PREG_SPLIT_NO_EMPTY) as $part) {
            $result[] = (float)str_replace(',', '.', $part);
        }
        return $result;
    }

    private function floatInList($needle, array $haystack)
    {
        foreach ($haystack as $value) {
            if (abs((float)$needle - (float)$value) < 0.0001) {
                return true;
            }
        }
        return false;
    }

    private function logSummary(array $summary)
    {
        try {
            $this->getLogger(__CLASS__ . '::run')->info(
                'PreisLandoBestsellerAutomation::run',
                $summary
            );
        } catch (\Throwable $e) {
            // Die Automatik soll nicht wegen eines Logging-Problems abbrechen.
        }
    }
}
