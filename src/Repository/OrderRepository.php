<?php
namespace Sakuragi\Repository;

use PDO;

class OrderRepository
{
    private PDO $pdo;

    public function __construct(PDO $pdo)
    {
        $this->pdo = $pdo;
    }

    public function findByUserId(int $userId): array
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                o.order_id,
                o.user_id,
                o.service_id,
                o.employee_id,
                o.status,
                o.payment_status,
                o.order_date,
                o.expected_completion,
                o.total_price,
                s.service_name,
                s.service_category,
                COALESCE(u.full_name, 'Unassigned') as employee_name,
                COALESCE(SUM(od.quantity), 0) as total_quantity,
                ow.stage,
                ow.sample_status,
                ow.priority
            FROM orders o
            JOIN services s ON o.service_id = s.service_id
            LEFT JOIN users u ON o.employee_id = u.user_id
            LEFT JOIN order_details od ON o.order_id = od.order_id
            LEFT JOIN order_workflow ow ON o.order_id = ow.order_id
            WHERE o.user_id = :user_id
            GROUP BY o.order_id
            ORDER BY o.order_date DESC
        ");
        $stmt->execute(['user_id' => $userId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function findById(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                o.order_id,
                o.user_id,
                o.service_id,
                o.employee_id,
                o.status,
                o.payment_status,
                o.order_date,
                o.expected_completion,
                o.total_price
            FROM orders o
            WHERE o.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getDetails(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                s.service_name, 
                s.service_category,
                s.service_price,
                o.status,
                o.payment_status, 
                o.order_date,
                o.expected_completion,
                u.full_name AS employee_name
            FROM orders o
            JOIN services s ON o.service_id = s.service_id
            LEFT JOIN users u ON o.employee_id = u.user_id
            WHERE o.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    public function getItems(int $orderId): array
    {
        $stmt = $this->pdo->prepare("SELECT * FROM order_details WHERE order_id = :order_id");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    public function getPayment(int $orderId): array|false
    {
        $stmt = $this->pdo->prepare("SELECT * FROM payments WHERE order_id = :order_id");
        $stmt->execute(['order_id' => $orderId]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
}
