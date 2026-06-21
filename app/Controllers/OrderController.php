<?php
require_once __DIR__ . '/../../config/autoload.php';

use Sakuragi\Repository\OrderRepository;
use Sakuragi\Service\OrderService;
use Sakuragi\Config\Database;

class OrderController
{
    private OrderService $orderService;
    private OrderRepository $orderRepo;

    public function __construct(PDO $pdo)
    {
        $this->orderRepo = new OrderRepository($pdo);
        $this->orderService = new OrderService($this->orderRepo);
    }

    public function getCustomerOrders(int $userId): array
    {
        try {
            return $this->orderService->getCustomerOrders($userId);
        } catch (PDOException $e) {
            error_log('Error fetching orders: ' . $e->getMessage());
            return [];
        }
    }

    public function getOrderById(int $orderId, int $userId): array|bool
    {
        try {
            $result = $this->orderService->getOrderById($orderId, $userId);
            if ($result === false) {
                return false;
            }
            return $result;
        } catch (PDOException $e) {
            error_log('Error fetching order details: ' . $e->getMessage());
            return false;
        }
    }
}
