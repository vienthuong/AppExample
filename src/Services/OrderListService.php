<?php declare(strict_types=1);

namespace App\Services;

use Vin\ShopwareSdk\Data\Context;
use Vin\ShopwareSdk\Data\Criteria;
use Vin\ShopwareSdk\Data\Entity\Order\OrderDefinition;
use Vin\ShopwareSdk\Data\Entity\Order\OrderEntity;
use Vin\ShopwareSdk\Data\Entity\OrderLineItem\OrderLineItemEntity;
use Vin\ShopwareSdk\Data\FieldSorting;
use Vin\ShopwareSdk\Data\Filter\EqualsAnyFilter;
use Vin\ShopwareSdk\Data\Filter\EqualsFilter;
use Vin\ShopwareSdk\Factory\RepositoryFactory;

class OrderListService
{
    //Updates an existing order.
    //These steps are necessary because you need to create a new version for each order which you want to edit.
    //A new version will be created and then merged if you finished editing.
    public function updateOrder(Context $context, string $orderId, array $data): void
    {
        $orderRepository = RepositoryFactory::create(OrderDefinition::ENTITY_NAME);

        //Creates a new version of the order.
        $versionResponse = $orderRepository->createVersion($orderId, $context);

        $versionId = $versionResponse->getVersionId();

        $data['id'] = $orderId;

        //Updates the order.
        $orderRepository->update($data, $context);

        //Merges the changes into the new version of the order.
        $orderRepository->mergeVersion($orderId, $context);
    }

    //Generates the order list table data from an order.
    public function getOrderListConfigurationFromOrder(OrderEntity $order): array
    {
        //Get the formatted line items.
        $lineItems = $this->mapLineItemsForConfigurationOrderListTable($order);

        return $this->setConfigurationOrderListTable($lineItems);
    }

    //Generates the order list table data from an order id.
    public function getOrderListConfigurationFromOrderId(Context $context, string $orderId): array
    {
        $orderRepository = RepositoryFactory::create(OrderDefinition::ENTITY_NAME);

        $criteria = new Criteria();

        $criteria->addAssociation('lineItems');
        $criteria->getAssociation('lineItems')->setTotalCountMode(1);
        $criteria->setIncludes([
            'order' => [
                'orderNumber',
                'lineItems',
            ],
            'order_line_item' => [
                'quantity',
                'payload',
                'label',
            ],
        ]);

        //Get the order with the line items.
        /** @var OrderEntity $order */
        $order = $orderRepository->get($orderId, $criteria, $context);

        if (empty($order)) {
            return [];
        }

        //Get the formatted line items.
        $lineItems = $this->mapLineItemsForConfigurationOrderListTable($order);

        return $this->setConfigurationOrderListTable($lineItems);
    }

    //Generates the order list table data from all open orders.
    public function getOrderListConfigurationForAllOpenOrders(Context $context): array
    {
        $orderRepository = RepositoryFactory::create(OrderDefinition::ENTITY_NAME);

        $criteria = new Criteria();

        $criteria->addAssociation('lineItems');
        $criteria->getAssociation('lineItems')->setTotalCountMode(1);
        $criteria->addSorting(new FieldSorting('orderDateTime', FieldSorting::ASCENDING));
        $criteria->addFilter(new EqualsFilter('stateMachineState.name', 'open'));

        $criteria->setIncludes([
            'order' => [
                'orderNumber',
                'lineItems',
            ],
            'order_line_item' => [
                'quantity',
                'payload',
                'label',
            ],
        ]);

        //Get all open orders with their line items.
        $ordersSearchResult = $orderRepository->search($criteria, $context);

        $lineItems = [];

        //Format the line items
        /** @var OrderEntity $order */
        foreach ($ordersSearchResult->getEntities() as $order) {
            $lineItems = array_merge(
                $lineItems,
                $order->lineItems ? $this->mapLineItemsForConfigurationOrderListTable($order) : []
            );
        }

        return $this->setConfigurationOrderListTable($lineItems);
    }

    private function mapLineItemsForConfigurationOrderListTable(OrderEntity $order): array
    {
        /** @var OrderLineItemEntity $lineItem */
        foreach ($order->lineItems as $lineItem) {
            $lineItems[] = [
                'orderNumber' => $order->orderNumber,
                'productNumber' => $lineItem->payload['productNumber'],
                'label' => $lineItem->label,
                'quantity' => $lineItem->quantity,
                'checked' => '',
            ];
        }

        return $lineItems;
    }

    //Set the configuration for the order-list-table template with the line items.
    private function setConfigurationOrderListTable(array $lineItems): array
    {
        return [
            'header' => [
                'Order number' => 'orderNumber',
                'Product number' => 'productNumber',
                'label' => 'label',
                'quantity' => 'quantity',
                'checked' => 'checked',
            ],
            'lineItems' => $lineItems,
        ];
    }
}
