<?php
namespace Sakuragi\Service;

use Sakuragi\Repository\OrderRepository;

class OrderService
{
    private OrderRepository $orderRepo;

    public function __construct(OrderRepository $orderRepo)
    {
        $this->orderRepo = $orderRepo;
    }

    public function getCustomerOrders(int $userId): array
    {
        return $this->orderRepo->findByUserId($userId);
    }

    public function getOrderById(int $orderId, int $userId): array|false
    {
        $order = $this->orderRepo->findById($orderId);
        if (!$order || $order['user_id'] != $userId) {
            return false;
        }

        $details = $this->orderRepo->getDetails($orderId);
        if (!$details) {
            return false;
        }

        return [
            ...$order,
            ...$details,
            'items' => $this->orderRepo->getItems($orderId),
            'payment' => $this->orderRepo->getPayment($orderId),
        ];
    }
}
