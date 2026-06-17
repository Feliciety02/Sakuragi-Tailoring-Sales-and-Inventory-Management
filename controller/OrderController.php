<?php
class OrderController
{
    private $pdo;

    public function __construct($pdo)
    {
        $this->pdo = $pdo;
    }

    /**
     * Get orders for a specific customer
     *
     * @param int $user_id
     * @return array
     */
    public function getCustomerOrders($user_id)
    {
        try {
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
                    COALESCE(SUM(od.quantity), 0) as total_quantity
                FROM 
                    orders o
                JOIN 
                    services s ON o.service_id = s.service_id
                LEFT JOIN 
                    users u ON o.employee_id = u.user_id
                LEFT JOIN
                    order_details od ON o.order_id = od.order_id
                WHERE 
                    o.user_id = :user_id
                GROUP BY 
                    o.order_id
                ORDER BY 
                    o.order_date DESC
            ");

            $stmt->execute(['user_id' => $user_id]);
            return $stmt->fetchAll(PDO::FETCH_ASSOC);
        } catch (PDOException $e) {
            error_log('Error fetching orders: ' . $e->getMessage());
            return [];
        }
    }

    /**
     * Get a specific order by ID
     *
     * @param int $order_id
     * @param int $user_id
     * @return array|bool
     */
    public function getOrderById($order_id, $user_id)
    {
        try {
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
                FROM 
                    orders o
                WHERE 
                    o.order_id = :order_id AND o.user_id = :user_id
            ");
            $stmt->execute([
                'order_id' => $order_id,
                'user_id' => $user_id,
            ]);

            $order = $stmt->fetch(PDO::FETCH_ASSOC);
            if (!$order) {
                return false;
            }

            // Get additional order information
            $orderDetails = $this->getOrderDetails($order_id);

            // Merge all order information
            $result = array_merge($order, $orderDetails);

            // Get order items
            $result['items'] = $this->getOrderItems($order_id);

            // Get payment information
            $result['payment'] = $this->getOrderPayment($order_id);

            return $result;
        } catch (PDOException $e) {
            error_log('Error fetching order details: ' . $e->getMessage());
            return false;
        }
    }

    /**
     * Get additional order details
     *
     * @param int $order_id
     * @return array
     */
    private function getOrderDetails($order_id)
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
            FROM 
                orders o
            JOIN 
                services s ON o.service_id = s.service_id
            LEFT JOIN 
                users u ON o.employee_id = u.user_id
            WHERE 
                o.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }

    /**
     * Get items for an order
     *
     * @param int $order_id
     * @return array
     */
    private function getOrderItems($order_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                od.*
            FROM 
                order_details od
            WHERE 
                od.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $order_id]);
        return $stmt->fetchAll(PDO::FETCH_ASSOC);
    }

    /**
     * Get payment information for an order
     *
     * @param int $order_id
     * @return array|bool
     */ private function getOrderPayment($order_id)
    {
        $stmt = $this->pdo->prepare("
            SELECT 
                p.*
            FROM 
                payments p
            WHERE 
                p.order_id = :order_id
        ");
        $stmt->execute(['order_id' => $order_id]);
        return $stmt->fetch(PDO::FETCH_ASSOC);
    }
} ?>
