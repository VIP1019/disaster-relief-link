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

    private function normalizeCategory($category) {
        $allowed = ['Food', 'Water', 'NFIs', 'Medical', 'General'];
        $value = trim((string) $category);
        foreach ($allowed as $item) {
            if (strcasecmp($value, $item) === 0) {
                return $item;
            }
        }
        return 'General';
    }

    public static function stockStatusForRow($quantity, $reorder_level = null) {
        $qty = (int) $quantity;
        $level = $reorder_level === null || $reorder_level === '' ? 50 : max(1, (int) $reorder_level);
        if ($qty <= $level) {
            return 'Critical';
        }
        if ($qty <= ($level * 2)) {
            return 'Low';
        }
        return 'Stable';
    }

    /**
     * Add new relief item to inventory
     */
    public function addInventoryItem($item_name, $category, $quantity, $unit_of_measure, $cost_per_unit, $description = '', $added_by) {
        $category = $this->normalizeCategory($category);
        if (empty($item_name) || empty($category) || empty($quantity)) {
            return ['success' => false, 'message' => 'Required fields missing'];
        }

        $insert_query = "INSERT INTO {$this->inventory_table} 
                         (item_name, category, quantity, unit_of_measure, description, cost_per_unit, added_by)
                         VALUES (?, ?, ?, ?, ?, ?, ?)";

        $stmt = $this->conn->prepare($insert_query);
        // item_name, category, quantity, unit_of_measure, description, cost_per_unit, added_by
        $stmt->bind_param('ssissdi', $item_name, $category, $quantity, $unit_of_measure, $description, $cost_per_unit, $added_by);

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
        $category = $this->normalizeCategory($category);
        $update_query = "UPDATE {$this->inventory_table} 
                         SET item_name = ?, category = ?, quantity = ?, unit_of_measure = ?, cost_per_unit = ?, description = ?
                         WHERE id = ?";

        $stmt = $this->conn->prepare($update_query);
        $stmt->bind_param('ssisdsi', $item_name, $category, $quantity, $unit_of_measure, $cost_per_unit, $description, $item_id);

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
        $rows = $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
        foreach ($rows as &$row) {
            $row['stock_status'] = self::stockStatusForRow($row['quantity'] ?? 0, $row['reorder_level'] ?? null);
        }
        unset($row);
        return $rows;
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
        $row = $stmt->get_result()->fetch_assoc();
        if ($row) {
            $row['stock_status'] = self::stockStatusForRow($row['quantity'] ?? 0, $row['reorder_level'] ?? null);
        }
        return $row;
    }

    /**
     * Get inventory summary by category
     */
    public function getInventorySummary() {
        $query = "SELECT category, COUNT(*) as item_count, SUM(quantity) as total_quantity,
                         SUM(CASE WHEN quantity <= COALESCE(NULLIF(reorder_level, 0), 50) THEN 1 ELSE 0 END) AS critical_count,
                         SUM(CASE WHEN quantity > COALESCE(NULLIF(reorder_level, 0), 50)
                                   AND quantity <= (COALESCE(NULLIF(reorder_level, 0), 50) * 2) THEN 1 ELSE 0 END) AS low_count
                  FROM {$this->inventory_table}
                  GROUP BY category
                  ORDER BY category";

        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function getLowStockItems() {
        $query = "SELECT *,
                         CASE
                            WHEN quantity <= COALESCE(NULLIF(reorder_level, 0), 50) THEN 'Critical'
                            WHEN quantity <= (COALESCE(NULLIF(reorder_level, 0), 50) * 2) THEN 'Low'
                            ELSE 'Stable'
                         END AS stock_status
                  FROM {$this->inventory_table}
                  WHERE quantity <= (COALESCE(NULLIF(reorder_level, 0), 50) * 2)
                  ORDER BY quantity ASC, item_name ASC";
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    public function createRestockRequest($admin_id, $item_id = null) {
        $items = $this->getLowStockItems();
        if ($item_id !== null && (int) $item_id > 0) {
            $target = $this->getInventoryItem((int) $item_id);
            $items = $target ? [$target] : [];
        }
        if (count($items) === 0) {
            return ['success' => false, 'message' => 'No low-stock item found for restock request.'];
        }
        $names = array_map(function ($item) {
            return ($item['item_name'] ?? 'Item') . ' (' . ($item['quantity'] ?? 0) . ' ' . ($item['unit_of_measure'] ?? 'units') . ' left)';
        }, array_slice($items, 0, 8));
        $details = 'Restock request: ' . implode(', ', $names);
        $ip = $_SERVER['REMOTE_ADDR'] ?? 'system';
        $stmt = $this->conn->prepare('INSERT INTO system_logs (action, user_id, description, ip_address) VALUES (?, ?, ?, ?)');
        $action = 'restock_request';
        $uid = (int) $admin_id;
        $stmt->bind_param('siss', $action, $uid, $details, $ip);
        if ($stmt->execute()) {
            return ['success' => true, 'message' => 'Restock request logged for ' . count($items) . ' item(s).', 'items' => $items];
        }
        return ['success' => false, 'message' => 'Could not log restock request.'];
    }

    public function getAllBarangays() {
        $query = 'SELECT id, name, municipality, province FROM barangays ORDER BY name';
        $stmt = $this->conn->prepare($query);
        $stmt->execute();
        return $stmt->get_result()->fetch_all(MYSQLI_ASSOC);
    }

    /**
     * Remove inventory row if it has never been distributed.
     */
    public function deleteInventoryItem($item_id) {
        $id = (int) $item_id;
        if ($id <= 0) {
            return ['success' => false, 'message' => 'Invalid item'];
        }
        $chk = $this->conn->prepare('SELECT COUNT(*) AS c FROM relief_distributions WHERE inventory_id = ?');
        $chk->bind_param('i', $id);
        $chk->execute();
        $c = (int) ($chk->get_result()->fetch_assoc()['c'] ?? 0);
        if ($c > 0) {
            return ['success' => false, 'message' => 'Cannot delete an item that already has distribution records.'];
        }
        $stmt = $this->conn->prepare("DELETE FROM {$this->inventory_table} WHERE id = ?");
        $stmt->bind_param('i', $id);
        if ($stmt->execute() && $stmt->affected_rows > 0) {
            return ['success' => true, 'message' => 'Inventory item removed'];
        }
        return ['success' => false, 'message' => 'Delete failed or item not found'];
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
        $stmt->bind_param('iiiiis', $report_id, $barangay_id, $inventory_id, $quantity_distributed, $distributed_by, $notes);

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
        $query = "SELECT rd.*, b.name as barangay_name, b.latitude AS barangay_latitude, b.longitude AS barangay_longitude,
                         ri.item_name, ri.category,
                         COALESCE(u.full_name, '—') as distributed_by_name
                  FROM {$this->distribution_table} rd
                  JOIN barangays b ON rd.barangay_id = b.id
                  JOIN relief_inventory ri ON rd.inventory_id = ri.id
                  LEFT JOIN users u ON rd.distributed_by = u.id
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
        $query = "SELECT rd.*, ri.item_name, ri.category,
                         COALESCE(u.full_name, '—') as distributed_by_name
                  FROM {$this->distribution_table} rd
                  JOIN relief_inventory ri ON rd.inventory_id = ri.id
                  LEFT JOIN users u ON rd.distributed_by = u.id
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
     * Dashboard / deploy status: stats plus recent runs for activity feed.
     *
     * @return array<string, mixed>
     */
    public function getDeployStatusSnapshot() {
        $stats = $this->getDistributionStatistics() ?: [];
        $recent = $this->getAllDistributions(null, null);
        $feed = array_slice($recent, 0, 10);

        $barangaysServed = (int) ($stats['barangays_served'] ?? 0);
        $totalRuns = (int) ($stats['total_distributions'] ?? 0);
        $units = (int) ($stats['total_units_distributed'] ?? 0);

        $enRoute = min(12, max(1, $barangaysServed + (int) ceil($totalRuns / 2)));

        $readiness = 88.0;
        if ($totalRuns > 0 && $units > 0) {
            $readiness = min(99.5, 82.0 + min(12.0, $barangaysServed * 1.2) + min(6.0, $totalRuns * 0.4));
        }

        $teams = [
            ['name' => 'Rescue team — Camambugan flood response', 'status' => 'DEPLOYED', 'detail' => 'Coordinated with barangay tanods'],
            ['name' => 'Logistics-1 (Warehouse)', 'status' => $totalRuns > 0 ? 'LOADING' : 'STANDBY', 'detail' => $units . ' units logged out'],
            ['name' => 'Engineering-3 (access routes)', 'status' => $barangaysServed >= 2 ? 'EN ROUTE' : 'STANDBY', 'detail' => $barangaysServed . ' barangays served'],
        ];

        return [
            'statistics' => $stats,
            'en_route_trucks_estimate' => $enRoute,
            'readiness_score' => round($readiness, 1),
            'teams' => $teams,
            'recent_activity' => $feed,
        ];
    }

    /**
     * Resolve barangay PK from official name (for distribution form).
     */
    public function resolveBarangayIdByName($name) {
        if ($name === null || trim((string) $name) === '') {
            return null;
        }
        $n = trim((string) $name);
        $stmt = $this->conn->prepare('SELECT id FROM barangays WHERE LOWER(TRIM(name)) = LOWER(?) LIMIT 1');
        $stmt->bind_param('s', $n);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
    }

    /**
     * Barangay linked to a disaster report.
     */
    public function getBarangayIdForReport($report_id) {
        $rid = (int) $report_id;
        if ($rid <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare('SELECT barangay_id FROM disaster_reports WHERE id = ?');
        $stmt->bind_param('i', $rid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['barangay_id'] : null;
    }

    /**
     * Latest report for a barangay that can still receive relief (not closed).
     */
    public function getLatestActiveReportIdForBarangay($barangay_id) {
        $bid = (int) $barangay_id;
        if ($bid <= 0) {
            return null;
        }
        $stmt = $this->conn->prepare(
            "SELECT id FROM disaster_reports WHERE barangay_id = ? AND status IN ('submitted','reviewed','prioritized') ORDER BY submitted_at DESC LIMIT 1"
        );
        $stmt->bind_param('i', $bid);
        $stmt->execute();
        $row = $stmt->get_result()->fetch_assoc();
        return $row ? (int) $row['id'] : null;
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

    /**
     * Map structured request keys to a warehouse inventory row.
     */
    public function findInventoryForRequestType($type) {
        $type = strtolower((string) $type);
        if ($type === 'food') {
            $q = "SELECT * FROM {$this->inventory_table} WHERE category = 'Food' AND quantity > 0 ORDER BY quantity DESC, id ASC LIMIT 1";
        } elseif ($type === 'hygiene') {
            $q = "SELECT * FROM {$this->inventory_table} WHERE category = 'NFIs' AND (item_name LIKE '%Hygiene%' OR item_name LIKE '%hygiene%') AND quantity > 0 ORDER BY quantity DESC LIMIT 1";
            $res = $this->conn->query($q);
            if ($res && $res->num_rows > 0) {
                return $res->fetch_assoc();
            }
            $q = "SELECT * FROM {$this->inventory_table} WHERE category = 'NFIs' AND quantity > 0 ORDER BY quantity DESC LIMIT 1";
        } elseif ($type === 'water') {
            $q = "SELECT * FROM {$this->inventory_table} WHERE category = 'Water' AND quantity > 0 ORDER BY quantity DESC LIMIT 1";
        } else {
            return null;
        }
        $res = $this->conn->query($q);
        return $res && $res->num_rows > 0 ? $res->fetch_assoc() : null;
    }

    /**
     * Deduct inventory lines from structured report requests; records distributions.
     *
     * @param array<string, int> $requests food, hygiene, water quantities
     */
    public function deployStructuredRequests($report_id, $barangay_id, array $requests, $admin_user_id) {
        $lines = [];
        $labels = ['food' => 'Food packs', 'hygiene' => 'Hygiene kits', 'water' => 'Drinking water'];
        foreach (['food', 'hygiene', 'water'] as $key) {
            $qty = (int) ($requests[$key] ?? 0);
            if ($qty <= 0) {
                continue;
            }
            $item = $this->findInventoryForRequestType($key);
            if (!$item) {
                return [
                    'success' => false,
                    'message' => 'No stock available for ' . ($labels[$key] ?? $key) . ' in central inventory.',
                ];
            }
            $avail = (int) $item['quantity'];
            if ($qty > $avail) {
                $unit = $item['unit_of_measure'] ?: 'units';
                return [
                    'success' => false,
                    'message' => sprintf(
                        'Insufficient stock for %s. Central warehouse only has %d %s remaining; requested %d.',
                        $labels[$key],
                        $avail,
                        $unit,
                        $qty
                    ),
                    'shortage' => ['type' => $key, 'available' => $avail, 'requested' => $qty],
                ];
            }
            $lines[] = ['inventory_id' => (int) $item['id'], 'quantity' => $qty, 'label' => $labels[$key]];
        }

        if (count($lines) === 0) {
            return ['success' => false, 'message' => 'No supply quantities were requested (food, hygiene, or water).'];
        }

        $deductions = [];
        foreach ($lines as $line) {
            $out = $this->recordDistribution(
                $report_id,
                $barangay_id,
                $line['inventory_id'],
                $line['quantity'],
                $admin_user_id,
                'Auto-deploy from structured report requests'
            );
            if (!$out['success']) {
                return $out;
            }
            $deductions[] = [
                'label' => $line['label'],
                'quantity' => $line['quantity'],
                'inventory_id' => $line['inventory_id'],
            ];
        }

        return [
            'success' => true,
            'message' => 'Deployed ' . count($deductions) . ' inventory line(s) and deducted stock.',
            'deductions' => $deductions,
        ];
    }
}
?>
