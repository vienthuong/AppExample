<?php declare(strict_types=1);

namespace App\Controller;

use App\Services\OrderListService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Annotation\Route;
use Vin\ShopwareSdk\Data\Context;
use Vin\ShopwareSdk\Data\Webhook\AppAction\AppAction;
use Vin\ShopwareSdk\Data\Webhook\Event\Event;

class OrderController extends AbstractController
{
    /**
     * @var OrderListService
     */
    private $orderListService;

    public function __construct(OrderListService $orderListService)
    {
        $this->orderListService = $orderListService;
    }

    /**
     * @Route("/hooks/order/placed", name="hooks.order.placed", methods={"POST"})
     * Generates an order list with an deep link in order to print it.
     */
    public function orderPlacedEvent(Context $context, Event $event): Response
    {
        $eventData = $event->getData();
        $order = $eventData->getPayload()['order'] ?? null;

        // Should not happen, but return if there is no order.
        if (!$order) {
            return new Response();
        }

        $orderId = $order['id'];

        //Gets the configuration including the data for the order list.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrder($order);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Updates the order with the order list table and the deep link to the order.
        $this->orderListService->updateOrder($context, $orderId, ['customFields' => ['order-list' => $orderListTable]]);

        return new Response();
    }

    /**
     * @Route("/iframe/orderlist", name="iframe.orderList", methods={"GET"})
     * Generates an order list out of all open orders in the admin.
     */
    public function iframeOrderList(Context $context): Response
    {
        //Gets the data for the order list.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationForAllOpenOrders($context);

        //Outputs the order list to the user.
        return $this->render('Order/order-list.html.twig', ['orderListConfiguration' => $orderListConfiguration]);
    }

    /**
     * @Route("/actionbutton/notification", name="actionButton.notification", methods={"POST"})
     */
    public function notification(): Response
    {
        $response = [
            'actionType' => 'notification',
            'payload' => [
                'status' => 'success',
                'message' => 'This is a successful message',
            ]
        ];

        $signature = hash_hmac('sha256', json_encode($response), $_SERVER['SHOP_SECRET']);

        return new Response(json_encode($response), 200, ['shopware-app-signature' => $signature]);
    }

    /**
     * @Route("/actionbutton/reload", name="actionButton.reload", methods={"POST"})
     */
    public function reload(): Response
    {
        $response = [
            'actionType' => 'reload',
            'payload' => []
        ];

        $signature = hash_hmac('sha256', json_encode($response), $_SERVER['APP_SECRET']);

        return new Response(json_encode($response), 200, ['shopware-app-signature' => $signature]);
    }

    /**
     * @Route("/actionbutton/openModal", name="actionButton.openModal", methods={"POST"})
     */
    public function openModal(): Response
    {
        $response = [
            'actionType' => 'openModal',
            'payload' => [
                'iframeUrl' => 'http://myapp.test/iframe/orderlist',
                'size' => 'large',
                'expand' => true,
            ]
        ];

        $signature = hash_hmac('sha256', json_encode($response), $_SERVER['APP_SECRET']);

        return new Response(json_encode($response), 200, ['shopware-app-signature' => $signature]);
    }

    /**
     * @Route("/actionbutton/openNewTab", name="actionButton.openNewTab", methods={"POST"})
     */
    public function openNewTab(): Response
    {
        $response = [
            'actionType' => 'openNewTab',
            'payload' => [
                'redirectUrl' => 'http://google.com',
            ]
        ];

        $signature = hash_hmac('sha256', json_encode($response), $_SERVER['APP_SECRET']);

        return new Response(json_encode($response), 200, ['shopware-app-signature' => $signature]);
    }

    /**
     * @Route("/actionbutton/add/orderlist", name="actionButton.add.orderList", methods={"POST"})
     * Adds or update an order list with an deep link to an existing order.
     */
    public function addOrderListToExistingOrder(Context $context, AppAction $action): Response
    {
        $eventData = $action->getData();
        $orderId = $eventData->getIds()[0];

        //Gets the order list data.
        $orderListConfiguration = $this->orderListService->getOrderListConfigurationFromOrderId($context, $orderId);

        //Gets the order list table as plain html.
        $orderListTable = $this->renderView('Order/order-list-table.html.twig', ['orderListConfiguration' => $orderListConfiguration]);

        //Updates the existing order with the order list and the deep link to the order.
        $this->orderListService->updateOrder($context, $orderId, ['customFields' => ['order-list' => $orderListTable]]);

        return new Response();
    }
}
