<?php
/**
 * Relief Management Class
 * Handles relief inventory and distribution management
 */

require_once __DIR__ . '/../config/Database.php';

class ReliefManagement {
    private $conn;
    private $inventory_table = 'relief_inventory';
    private $distribution_table = 'relief_distributions';

    public function __construct() {
        $db = new Database();
        $this->conn = $db->getConnection();
    }

    // ==================== INVENTORY MANAGEMENT ====================

    /**
     * Add new relief item to inventory
     */
    public function addInventoryItem($item_name, $category, $quantity, $unit_of_measure, $cost_per_unit, $description = '', $added_by) {
        if (empty($item_name) || empty($category) || empty($quantity)) {
            return ['success' => false, 'message' => 'Required fields missing'];
        }

        $insert_query = "INSERT INTO {$this->inventory_table} 
                         (item_name, category, quantity, unit_of_measure, description, cost_per_unit, added_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        $stmt->bind_param('ssiisdi', $item_name, $category, $quantity, $unit_of_measure, $description, $cost_per_unit, $added_by);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Item added to inventory', 'item_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Failed to add item: ' . $this->conn->error];
        }
    }

    /**
     * Update inventory item
     */
    public function updateInventoryItem($item_id, $item_name, $category, $quantity, $unit_of_measure, $cost_per_unit, $description = '') {
        $update_query = "UPDATE {$this->inventory_table} 
                         SET item_name = ?, category = ?, quantity = ?, unit_of_measure = ?, cost_per_unit = ?, description = ?
                         WHERE id = ?";

        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('ssiisdsi', $item_name, $category, $quantity, $unit_of_measure, $cost_per_unit, $description, $item_id);

        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Item updated successfully'];
        } else {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    /**
     * Get all inventory items
     */
    public function getAllInventoryItems($category = null) {
        $query = "SELECT ri.*, u.full_name as added_by_name
                  FROM {$this->inventory_table} ri
                  LEFT JOIN users u ON ri.added_by = u.id
                  WHERE 1=1";

        if ($category) {
            $query .= " AND ri.category = ?";
        }

        $query .= " ORDER BY ri.category, ri.item_name";

        $stmt = $this->conn->prepare($query);

        if ($category) {
            $stmt->bind_param('s', $category);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get inventory item by ID
     */
    public function getInventoryItem($item_id) {
        $query = "SELECT ri.*, u.full_name as added_by_name
                  FROM {$this->inventory_table} ri
                  LEFT JOIN users u ON ri.added_by = u.id
                  WHERE ri.id = ?";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $item_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Get inventory summary by category
     */
    public function getInventorySummary() {
        $query = "SELECT category, COUNT(*) as item_count, SUM(quantity) as total_quantity
                  FROM {$this->inventory_table}
                  GROUP BY category
                  ORDER BY category";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Update inventory quantity (after distribution)
     */
    public function updateInventoryQuantity($item_id, $quantity_decrease) {
        $query = "UPDATE {$this->inventory_table} SET quantity = quantity - ? WHERE id = ? AND quantity >= ?";
        
        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('iii', $quantity_decrease, $item_id, $quantity_decrease);

        if ($stmt->execute()) {
            if ($stmt->affected_rows > 0) {
                return ['success' => true, 'message' => 'Inventory updated'];
            } else {
                return ['success' => false, 'message' => 'Insufficient inventory quantity'];
            }
        } else {
            return ['success' => false, 'message' => 'Update failed'];
        }
    }

    // ==================== DISTRIBUTION MANAGEMENT ====================

    /**
     * Record relief distribution
     */
    public function recordDistribution($report_id, $barangay_id, $inventory_id, $quantity_distributed, $distributed_by, $notes = '') {
        if (empty($report_id) || empty($barangay_id) || empty($inventory_id) || empty($quantity_distributed)) {
            return ['success' => false, 'message' => 'Required fields missing'];
        }

        // First, update inventory quantity
        $inventory_update = $this->updateInventoryQuantity($inventory_id, $quantity_distributed);
        
        if (!$inventory_update['success']) {
            return $inventory_update;
        }

        // Record the distribution
        $insert_query = "INSERT INTO {$this->distribution_table} 
                         (report_id, barangay_id, inventory_id, quantity_distributed, distributed_by, notes)
                         VALUES (?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        $stmt->bind_param('iiiis', $report_id, $barangay_id, $inventory_id, $quantity_distributed, $distributed_by, $notes);

        if ($stmt->execute()) {
            // Update disaster report status
            $this->updateReportDistributionStatus($report_id);
            
            return ['success' => true, 'message' => 'Distribution recorded successfully', 'distribution_id' => $this->conn->insert_id];
        } else {
            return ['success' => false, 'message' => 'Distribution recording failed'];
        }
    }

    /**
     * Get all distributions
     */
    public function getAllDistributions($barangay_id = null, $report_id = null) {
        $query = "SELECT rd.*, b.name as barangay_name, ri.item_name, ri.category, u.full_name as distributed_by_name
                  FROM {$this->distribution_table} rd
                  JOIN barangays b ON rd.barangay_id = b.id
                  JOIN relief_inventory ri ON rd.inventory_id = ri.id
                  JOIN users u ON rd.distributed_by = u.id
                  WHERE 1=1";

        if ($barangay_id) {
            $query .= " AND rd.barangay_id = ?";
        }
        if ($report_id) {
            $query .= " AND rd.report_id = ?";
        }

        $query .= " ORDER BY rd.distribution_date DESC";

        $stmt = $this->conn->prepare($query);

        if ($barangay_id && $report_id) {
            $stmt->bind_param('ii', $barangay_id, $report_id);
        } elseif ($barangay_id) {
            $stmt->bind_param('i', $barangay_id);
        } elseif ($report_id) {
            $stmt->bind_param('i', $report_id);
        }

        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get distribution history for a barangay
     */
    public function getBarangayDistributionHistory($barangay_id) {
        $query = "SELECT rd.*, ri.item_name, ri.category, u.full_name as distributed_by_name
                  FROM {$this->distribution_table} rd
                  JOIN relief_inventory ri ON rd.inventory_id = ri.id
                  JOIN users u ON rd.distributed_by = u.id
                  WHERE rd.barangay_id = ?
                  ORDER BY rd.distribution_date DESC";

        $stmt = $this->conn->prepare($query);
        $stmt->bind_param('i', $barangay_id);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Get distribution statistics
     */
    public function getDistributionStatistics() {
        $stats_query = "SELECT 
                            COUNT(DISTINCT barangay_id) as barangays_served,
                            COUNT(*) as total_distributions,
                            COUNT(DISTINCT inventory_id) as items_distributed,
                            SUM(quantity_distributed) as total_units_distributed
                        FROM {$this->distribution_table}";

        $stmt = $this->conn->prepare($stats_query);
        $stmt->execute();
        return $stmt->get_result()->fetch_assoc();
    }

    /**
     * Update disaster report status after distribution
     */
    private function updateReportDistributionStatus($report_id) {
        $update_query = "UPDATE disaster_reports SET status = 'relief_distributed' WHERE id = ?";
        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('i', $report_id);
        $stmt->execute();
    }
}
?>
